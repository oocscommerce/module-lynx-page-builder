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
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use OOCS\LynxPageBuilder\Api\DesignRepositoryInterface;
use OOCS\LynxPageBuilder\Helper\Data as Helper;

/**
 * Deletes a design, and with it every layout in it (the foreign key cascades)
 */
class Delete extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'OOCS_LynxPageBuilder::manage';

    /**
     * Context, design repository, scope config, store manager
     *
     * @param Context $context
     * @param DesignRepositoryInterface $designRepository
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Context $context,
        private readonly DesignRepositoryInterface $designRepository,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly StoreManagerInterface $storeManager,
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
        $id = (int) $this->getRequest()->getParam('id');

        try {
            try {
                $design = $this->designRepository->getById($id);
            } catch (NoSuchEntityException) {
                throw new LocalizedException(__('This design no longer exists.'));
            }

            if ($design->isReadonly()) {
                throw new LocalizedException(__('This design is read-only, so it cannot be deleted.'));
            }

            if ($this->isInUse($id)) {
                throw new LocalizedException(
                    __('A store is set to this design. Point it at another design first.')
                );
            }

            $this->designRepository->delete($design);

            $this->messageManager->addSuccessMessage(__('You deleted the design.'));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Could not delete the design.'));
        }

        return $this->resultRedirectFactory->create()->setPath('*/*/index');
    }

    /**
     * Whether any store view renders with this design
     *
     * @param int $designId
     * @return bool
     */
    private function isInUse(int $designId): bool
    {
        foreach ($this->storeManager->getStores() as $store) {
            $active = (int) $this->scopeConfig->getValue(
                Helper::XML_PATH_ACTIVE_DESIGN,
                ScopeInterface::SCOPE_STORE,
                $store->getCode()
            );

            if ($active === $designId) {
                return true;
            }
        }

        return false;
    }
}
