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
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use OOCS\LynxPageBuilder\Api\DesignRepositoryInterface;
use OOCS\LynxPageBuilder\Model\DesignFactory;

/**
 * Copies a design: a row with a new name and the original's theme settings
 */
class Duplicate extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'OOCS_LynxPageBuilder::manage';

    /**
     * Context, design repository, design factory
     *
     * @param Context $context
     * @param DesignRepositoryInterface $designRepository
     * @param DesignFactory $designFactory
     * @param DateTime $dateTime
     */
    public function __construct(
        Context $context,
        private readonly DesignRepositoryInterface $designRepository,
        private readonly DesignFactory $designFactory,
        private readonly DateTime $dateTime,
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
                $source = $this->designRepository->getById($id);
            } catch (NoSuchEntityException) {
                throw new LocalizedException(__('This design no longer exists.'));
            }

            $copy = $this->designFactory->create();
            // "Default-temp-1789614115": the source name, a marker, and the Unix time
            $copy->setData('name', (string) __('%1-temp-%2', $source->getName(), $this->dateTime->gmtTimestamp()));
            $copy->setData('settings', $source->getData('settings'));
            // The original first: its own rows beat what it inherits, and so must they for the copy
            $copy->setParents(array_values(array_unique([(int) $source->getId(), ...$source->getParents()])));
            $this->designRepository->save($copy);

            $this->messageManager->addSuccessMessage(__('You duplicated "%1".', $source->getName()));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Could not duplicate the design.'));
        }

        return $this->resultRedirectFactory->create()->setPath('*/*/index');
    }
}
