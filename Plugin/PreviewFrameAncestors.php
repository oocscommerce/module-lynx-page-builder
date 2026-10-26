<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Plugin;

use Magento\Csp\Api\CspRendererInterface;
use Magento\Csp\Model\Collector\DynamicCollector;
use Magento\Csp\Model\Policy\FetchPolicy;
use Magento\Framework\App\Response\HeaderProvider\XFrameOptions;
use Magento\Framework\App\Response\HttpInterface as HttpResponse;
use OOCS\LynxPageBuilder\Helper\Data as Helper;
use OOCS\LynxPageBuilder\Model\PreviewMode;

/**
 * Lets the editor frame the storefront it previews when the two are on different origins
 */
class PreviewFrameAncestors
{
    private const POLICY = 'frame-ancestors';

    /**
     * Preview, dynamic collector, helper
     *
     * @param PreviewMode $preview
     * @param DynamicCollector $dynamicCollector
     * @param Helper $helper
     */
    public function __construct(
        private readonly PreviewMode $preview,
        private readonly DynamicCollector $dynamicCollector,
        private readonly Helper $helper,
    ) {
    }

    /**
     * After Can Apply
     *
     * @param XFrameOptions $subject
     * @param bool $result
     * @return bool
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterCanApply(XFrameOptions $subject, bool $result): bool
    {
        return $result && !$this->preview->isActive();
    }

    /**
     * Before the policies are collected: the admin's origin joins frame-ancestors
     *
     * @param CspRendererInterface $subject
     * @param HttpResponse $response
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeRender(CspRendererInterface $subject, HttpResponse $response): void
    {
        $origin = $this->helper->getAdminOrigin();

        if ($origin !== '' && $this->preview->isActive()) {
            $this->dynamicCollector->add(new FetchPolicy(self::POLICY, false, [$origin], [], true));
        }
    }

    /**
     * Only where Magento wrote a report-only policy: an enforced one already carries the admin's origin (beforeRender)
     *
     * @param CspRendererInterface $subject
     * @param mixed $result
     * @param HttpResponse $response
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterRender(CspRendererInterface $subject, $result, HttpResponse $response): void
    {
        $origin = $this->helper->getAdminOrigin();

        if ($origin === ''
            || !$this->preview->isActive()
            || !$response->getHeader('Content-Security-Policy-Report-Only')
            || $response->getHeader('Content-Security-Policy')
        ) {
            return;
        }

        $response->setHeader('Content-Security-Policy', self::POLICY . " 'self' " . $origin . ';', true);
    }
}
