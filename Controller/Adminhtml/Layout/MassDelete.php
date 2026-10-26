<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Controller\Adminhtml\Layout;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Ui\Component\MassAction\Filter;
use OOCS\LynxPageBuilder\Api\DesignRepositoryInterface;
use OOCS\LynxPageBuilder\Api\LayoutRepositoryInterface;
use OOCS\LynxPageBuilder\Model\ResourceModel\Layout\CollectionFactory;

/**
 * Deletes the selected layouts of a design, which hands those pages back to the design's parents
 */
class MassDelete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'OOCS_LynxPageBuilder::save';

    /**
     * Context, filter, collection factory, layout repository, design repository
     *
     * @param Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     * @param LayoutRepositoryInterface $layoutRepository
     * @param DesignRepositoryInterface $designRepository
     */
    public function __construct(
        Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
        private readonly LayoutRepositoryInterface $layoutRepository,
        private readonly DesignRepositoryInterface $designRepository,
    ) {
        parent::__construct($context);
    }

    /**
     * Execute
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $designId = (int) $this->getRequest()->getParam('design_id');
        $redirect = $this->resultRedirectFactory->create()->setPath('*/*/index', ['design_id' => $designId]);

        try {
            try {
                $design = $this->designRepository->getById($designId);
            } catch (NoSuchEntityException) {
                throw new LocalizedException(__('This design no longer exists.'));
            }

            if ($design->isReadonly()) {
                throw new LocalizedException(__('This design is read-only. Duplicate it to make changes.'));
            }

            $collection = $this->filter->getCollection($this->collectionFactory->create());
            $collection->addFieldToFilter('design_id', $designId);

            $deleted = 0;
            foreach ($collection as $layout) {
                $this->layoutRepository->delete($layout);
                $deleted++;
            }

            $this->messageManager->addSuccessMessage(__('You deleted %1 layout(s).', $deleted));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Could not delete the layouts.'));
        }

        return $redirect;
    }
}
