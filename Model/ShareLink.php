<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Model;

use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Math\Random;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\Stdlib\StringUtils;
use Magento\Framework\Url as FrontendUrl;
use OOCS\LynxPageBuilder\Model\ResourceModel\Share as ShareResource;

/**
 * Makes a password-protected preview link for a design, for the editor's dialog and the MCP tool alike
 */
class ShareLink
{
    public const MIN_PASSWORD = 8;

    /**
     * Share factory and resource, frontend url, encryptor, string utils
     *
     * @param ShareFactory $shareFactory
     * @param ShareResource $shareResource
     * @param FrontendUrl $frontendUrl
     * @param EncryptorInterface $encryptor
     * @param StringUtils $stringUtils
     * @param DateTime $dateTime
     * @param Random $random
     */
    public function __construct(
        private readonly ShareFactory $shareFactory,
        private readonly ShareResource $shareResource,
        // The concrete frontend builder, not UrlInterface: in the admin area that interface is Backend\Model\Url
        private readonly FrontendUrl $frontendUrl,
        private readonly EncryptorInterface $encryptor,
        private readonly StringUtils $stringUtils,
        private readonly DateTime $dateTime,
        private readonly Random $random,
    ) {
    }

    /**
     * Store the link hashed and return its URL; the password is the caller's to hand over
     *
     * @param int $designId
     * @param string $password
     * @param int $hours
     * @param int $storeId
     * @return array{id:int,url:string,expires:int}
     * @throws LocalizedException
     */
    public function create(int $designId, string $password, int $hours, int $storeId): array
    {
        if ($this->stringUtils->strlen($password) < self::MIN_PASSWORD) {
            throw new LocalizedException(__('The password needs at least %1 characters.', self::MIN_PASSWORD));
        }
        if (!\in_array($hours, Share::HOURS, true)) {
            throw new LocalizedException(__('Pick how long the link should work.'));
        }

        // 24 random bytes: 48 hex characters in the URL, and nothing to enumerate.
        $token   = $this->random->getRandomString(48);
        $expires = $this->dateTime->gmtTimestamp() + $hours * 3600;

        $share = $this->shareFactory->create();
        $share->setData([
            'design_id'     => $designId,
            'token_hash'    => hash('sha256', $token),
            'password_hash' => $this->encryptor->getHash($password, true),
            'expires_at'    => $this->dateTime->gmtDate(null, $expires),
        ]);
        $this->shareResource->save($share);

        return [
            'id'      => (int) $share->getId(),
            'url'     => $this->frontendUrl->getUrl('lynx/share/open', [
                'key'           => $token,
                '_scope'        => $storeId,
                '_scope_to_url' => true,
                '_nosid'        => true,
            ]),
            'expires' => $expires,
        ];
    }
}
