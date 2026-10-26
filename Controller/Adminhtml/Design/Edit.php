<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Controller\Adminhtml\Design;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Result\Page;
use OOCS\LynxPageBuilder\Api\DesignRepositoryInterface;

/**
 * The design form, for a new or an existing design.
 */
class Edit extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'OOCS_LynxPageBuilder::manage';

    /**
     * Context, design repository
     *
     * @param Context $context
     * @param DesignRepositoryInterface $designRepository
     */
    public function __construct(
        Context $context,
        private readonly DesignRepositoryInterface $designRepository,
    ) {
        parent::__construct($context);
    }

    /**
     * Execute
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $id = (int) $this->getRequest()->getParam('id');

        if ($id) {
            try {
                $design = $this->designRepository->getById($id);
            } catch (NoSuchEntityException) {
                $this->messageManager->addErrorMessage(__('This design no longer exists.'));

                return $this->resultRedirectFactory->create()->setPath('*/*/index');
            }
        }

        /** @var Page $page */
        $page = $this->resultFactory->create('page');
        $page->setActiveMenu('OOCS_LynxPageBuilder::design');
        $page->getConfig()->getTitle()->prepend($id ? $design->getName() : __('New Design'));

        return $page;
    }
}
