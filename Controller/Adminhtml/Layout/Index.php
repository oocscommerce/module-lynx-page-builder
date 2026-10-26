<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Controller\Adminhtml\Layout;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use OOCS\LynxPageBuilder\Api\DesignRepositoryInterface;

/**
 * The layouts one design holds, as a grid: one row per layout it has saved of its own
 */
class Index extends Action implements HttpGetActionInterface
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
        try {
            $design = $this->designRepository->getById((int) $this->getRequest()->getParam('design_id'));
        } catch (NoSuchEntityException) {
            $this->messageManager->addErrorMessage(__('This design no longer exists.'));

            return $this->resultRedirectFactory->create()->setPath('lynxpagebuilder/design/index');
        }

        $page = $this->resultFactory->create('page');
        $page->setActiveMenu('OOCS_LynxPageBuilder::design');
        $page->getConfig()->getTitle()->prepend(__('Layouts of "%1"', $design->getName()));

        return $page;
    }
}
