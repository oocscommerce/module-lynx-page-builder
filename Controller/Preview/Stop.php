<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Controller\Preview;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Store\Model\StoreManagerInterface;
use OOCS\LynxPageBuilder\Model\PreviewMode;

/**
 * Leaves preview mode: what the pill's link asks for
 */
class Stop implements HttpGetActionInterface
{
    /**
     * Preview, redirect factory, redirect, request, store manager
     *
     * @param PreviewMode $preview
     * @param RedirectFactory $redirectFactory
     * @param RedirectInterface $redirect
     * @param RequestInterface $request
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly PreviewMode $preview,
        private readonly RedirectFactory $redirectFactory,
        private readonly RedirectInterface $redirect,
        private readonly RequestInterface $request,
        private readonly StoreManagerInterface $storeManager,
    ) {
    }

    /**
     * Execute
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $this->preview->stop();

        // Back to the page the pill was on, which is now itself again
        $referer = (string) $this->redirect->getRefererUrl();

        if (!$referer || str_contains($referer, '/lynx_pagebuilder/')) {
            $referer = $this->storeManager->getStore()->getBaseUrl();
        }

        return $this->redirectFactory->create()->setUrl($referer);
    }
}
