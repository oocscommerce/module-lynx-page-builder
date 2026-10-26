<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use OOCS\LynxPageBuilder\Model\PreviewMode;

/**
 * Adds the preview handle on top of the page's own handles
 */
class AddPreviewLayoutHandle implements ObserverInterface
{
    public const HANDLE = 'lynx_pagebuilder_preview';

    /**
     * Preview
     *
     * @param PreviewMode $preview
     */
    public function __construct(
        private readonly PreviewMode $preview,
    ) {
    }

    /**
     * Execute
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        if (!$this->preview->isActive()) {
            return;
        }

        $observer->getEvent()->getLayout()->getUpdate()->addHandle(self::HANDLE);
    }
}
