<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Controller\Adminhtml\Media;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use OOCS\LynxPageBuilder\Model\MediaStorage;

/**
 * Stores an image the editor uploads into the media folder.
 */
class Upload extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Magento_MediaGalleryUiApi::upload_assets';

    /**
     * Context, json factory, media storage
     *
     * @param Context $context
     * @param JsonFactory $jsonFactory
     * @param MediaStorage $mediaStorage
     */
    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly MediaStorage $mediaStorage,
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
        $result = $this->jsonFactory->create();

        try {
            $path = $this->mediaStorage->save('image', (string) $this->getRequest()->getParam('path', ''));

            return $result->setData(['success' => true, 'path' => $path]);
        } catch (\Exception $e) {
            return $result->setData(['success' => false, 'error' => $e->getMessage()]);
        }
    }
}
