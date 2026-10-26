<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Controller\Adminhtml\Share;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use OOCS\LynxPageBuilder\Model\BuilderTarget;
use OOCS\LynxPageBuilder\Model\ResourceModel\Share as ShareResource;
use OOCS\LynxPageBuilder\Model\ShareFactory;

/**
 * Ends a share link: the row goes, and with it the preview of whoever opened the link (Model\PreviewMode checks the
 */
class Revoke extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'OOCS_LynxPageBuilder::share';

    /**
     * Context, result json factory, target, share factory, share resource
     *
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param BuilderTarget $target
     * @param ShareFactory $shareFactory
     * @param ShareResource $shareResource
     */
    public function __construct(
        Context $context,
        private readonly JsonFactory $resultJsonFactory,
        private readonly BuilderTarget $target,
        private readonly ShareFactory $shareFactory,
        private readonly ShareResource $shareResource,
    ) {
        parent::__construct($context);
    }

    /**
     * Execute
     *
     * @return Json
     */
    public function execute(): Json
    {
        $result = $this->resultJsonFactory->create();
        $share  = $this->shareFactory->create();
        $this->shareResource->load($share, (int) $this->getRequest()->getParam('id'));

        if (!$share->getId() || (int) $share->getDesignId() !== $this->target->getDesignId()) {
            return $result->setHttpResponseCode(404)->setData([
                'success' => false,
                'message' => __('That link is already gone.'),
            ]);
        }

        $this->shareResource->delete($share);

        return $result->setData(['success' => true]);
    }
}
