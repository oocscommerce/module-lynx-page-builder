<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Controller\Preview;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use OOCS\LynxComponent\Helper\PreviewSample;

/**
 * Renders a previewed page's layout without its own controller, whose checks (an order just placed) the editor cannot
 * pass; the providers answer with sample data. Only the preview router hands requests here.
 */
class Sample implements HttpGetActionInterface, HttpPostActionInterface
{
    /**
     * Page factory, preview sample flag
     *
     * @param PageFactory $pageFactory
     * @param PreviewSample $previewSample
     */
    public function __construct(
        private readonly PageFactory $pageFactory,
        private readonly PreviewSample $previewSample,
    ) {
    }

    /**
     * Execute
     *
     * @return Page
     * @throws NotFoundException
     */
    public function execute(): Page
    {
        // Reachable by its own URL too, as every action is: that request is not a preview
        if (!$this->previewSample->isActive()) {
            throw new NotFoundException(__('Page not found.'));
        }

        // The default handle is the full action name the router set: the previewed page's own
        return $this->pageFactory->create();
    }
}
