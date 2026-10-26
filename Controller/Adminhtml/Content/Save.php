<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Controller\Adminhtml\Content;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json as ResultJson;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use OOCS\LynxPageBuilder\Model\BuilderTarget;
use Psr\Log\LoggerInterface;

/**
 * What the editor saves when it was opened on a single layout (Api\ContentSourceInterface)
 */
class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'OOCS_LynxPageBuilder::save';

    /**
     * Context, json, target, result json factory, logger
     *
     * @param Context $context
     * @param Json $json
     * @param BuilderTarget $target
     * @param JsonFactory $resultJsonFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        private readonly Json $json,
        private readonly BuilderTarget $target,
        private readonly JsonFactory $resultJsonFactory,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct($context);
    }

    /**
     * Execute
     *
     * @return ResultJson
     */
    public function execute(): ResultJson
    {
        $result   = $this->resultJsonFactory->create();
        $source = $this->target->getContentSource();

        if ($source === null) {
            return $result->setHttpResponseCode(400)->setData([
                'success' => false,
                'message' => __('This editor is not open on a CMS page or category.'),
            ]);
        }

        // Everything the editor is told is said in this answer
        try {
            $content = [];
            $data    = (array) $this->getRequest()->getPostValue();

            if (!empty($data['layout'])) {
                $content = $this->json->unserialize($data['layout'])['content'] ?? [];
            }

            $source->saveNodes($content);
        } catch (LocalizedException $e) {
            return $result->setHttpResponseCode(400)->setData([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Lynx Page Builder: saving the layout failed', [
                'layout'  => $source->getUrlParams(),
                'exception' => $e,
            ]);

            return $result->setHttpResponseCode(500)->setData([
                'success' => false,
                'message' => __('The layout could not be saved. See the log for what went wrong.'),
            ]);
        }

        return $result->setData([
            'success' => true,
            'message' => $content
                ? __('Layout saved.')
                : __('No components left, so the page shows what it rendered before.'),
        ]);
    }
}
