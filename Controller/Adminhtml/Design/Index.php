<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Controller\Adminhtml\Design;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\Page;

/**
 * The design grid.
 */
class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'OOCS_LynxPageBuilder::manage';

    /**
     * Execute
     *
     * @return Page
     */
    public function execute(): Page
    {
        /** @var Page $page */
        $page = $this->resultFactory->create('page');
        $page->setActiveMenu('OOCS_LynxPageBuilder::design');
        $page->getConfig()->getTitle()->prepend(__('Designs'));

        return $page;
    }
}
