<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Model;

use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\App\Request\Http as Request;
use Magento\Framework\Session\Config\ConfigInterface as SessionConfig;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\Cookie\PublicCookieMetadata;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use OOCS\LynxPageBuilder\Helper\PreviewToken;
use OOCS\LynxPageBuilder\Model\ResourceModel\Share as ShareResource;

/**
 * Whether this browser is previewing a design, and which one
 *
 * @SuppressWarnings(PHPMD.CookieAndSessionMisuse)
 */
class PreviewMode
{
    /**
     * The vary context key: FPC keeps the pages of a browser previewing a design apart from the live ones
     */
    public const CONTEXT_KEY = 'lynx_preview';

    private const COOKIE = 'lynx_preview';

    /**
     * Expiry for the pill's countdown: the token cookie is HttpOnly and the pill is cached HTML; only the token decides
     */
    private const COOKIE_UNTIL = 'lynx_preview_until';

    /**
     * @var int|null
     */
    private ?int $designId = null;

    /**
     * Cookie manager, cookie metadata factory, session config, token, share resource, request, http context
     *
     * @param CookieManagerInterface $cookieManager
     * @param CookieMetadataFactory $cookieMetadataFactory
     * @param SessionConfig $sessionConfig
     * @param PreviewToken $token
     * @param ShareResource $shareResource
     * @param Request $request
     * @param HttpContext $httpContext
     * @param DateTime $dateTime
     */
    public function __construct(
        private readonly CookieManagerInterface $cookieManager,
        private readonly CookieMetadataFactory $cookieMetadataFactory,
        private readonly SessionConfig $sessionConfig,
        private readonly PreviewToken $token,
        private readonly ShareResource $shareResource,
        private readonly Request $request,
        private readonly HttpContext $httpContext,
        private readonly DateTime $dateTime,
    ) {
    }

    /**
     * Written on every request to the preview route, which is what keeps the clock of the token inside it moving
     *
     * @param string $token
     * @return void
     */
    public function start(string $token): void
    {
        // Kept exactly as long as the token inside it: two hours for the editor, a share link's own expiry
        $expiry = $this->token->read($token)['expiry'] ?? 0;
        $metadata = $this->crossSite($this->cookieMetadataFactory->createPublicCookieMetadata())
            ->setDuration($expiry > 0 ? max(1, $expiry - $this->dateTime->gmtTimestamp()) : PreviewToken::LIFETIME)
            ->setPath($this->sessionConfig->getCookiePath())
            ->setHttpOnly(true);

        $this->cookieManager->setPublicCookie(self::COOKIE, $token, $metadata);

        if ($expiry > 0) {
            $this->cookieManager->setPublicCookie(
                self::COOKIE_UNTIL,
                (string) $expiry,
                $this->crossSite($this->cookieMetadataFactory->createPublicCookieMetadata())
                    ->setDuration(max(1, $expiry - $this->dateTime->gmtTimestamp()))
                    ->setPath($this->sessionConfig->getCookiePath())
                    ->setHttpOnly(false)
            );
        }

        $this->designId = $this->designOf($token);
        // Into this response's vary too: the redirect that follows must not land on the live page's cache entry
        if ($this->designId) {
            $this->httpContext->setValue(self::CONTEXT_KEY, $this->designId, 0);
        }
    }

    /**
     * SameSite=None on a secure request, so the cookie is kept inside the editor's frame when the admin is on another
     *
     * @param PublicCookieMetadata $metadata
     * @return PublicCookieMetadata
     */
    private function crossSite(PublicCookieMetadata $metadata): PublicCookieMetadata
    {
        if (!$this->request->isSecure()) {
            return $metadata;
        }

        return $metadata->setSecure(true)->setSameSite('None');
    }

    /**
     * Is Active
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->getDesignId() > 0;
    }

    /**
     * The design being previewed, or 0 when this browser is not previewing
     *
     * @return int
     */
    public function getDesignId(): int
    {
        if ($this->designId !== null) {
            return $this->designId;
        }

        $cookie = (string) $this->cookieManager->getCookie(self::COOKIE, '');

        return $this->designId = $cookie ? $this->designOf($cookie) : 0;
    }

    /**
     * The design a token previews, or 0
     *
     * @param string $token
     * @return int
     */
    private function designOf(string $token): int
    {
        $payload = $this->token->read($token);

        if ($payload === null) {
            return 0;
        }

        if ($payload['share'] > 0 && !$this->shareResource->isAlive($payload['share'])) {
            return 0;
        }

        return $payload['design'];
    }

    /**
     * Stop
     *
     * @return void
     */
    public function stop(): void
    {
        $metadata = $this->cookieMetadataFactory->createSensitiveCookieMetadata()
            ->setPath($this->sessionConfig->getCookiePath());

        $this->cookieManager->deleteCookie(self::COOKIE, $metadata);
        $this->cookieManager->deleteCookie(self::COOKIE_UNTIL, $metadata);
        // Out of this response's vary too, or the redirect lands on the preview's cache entry
        $this->httpContext->unsValue(self::CONTEXT_KEY);

        $this->designId = 0;
    }
}
