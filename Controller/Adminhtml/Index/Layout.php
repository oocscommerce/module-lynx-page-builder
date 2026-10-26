<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use OOCS\LynxPageBuilder\Api\LayoutRepositoryInterface;
use OOCS\LynxPageBuilder\Model\BuilderTarget;

/**
 * The content zone one design holds for one page, for the editor's "Copy page from design"
 */
class Layout extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'OOCS_LynxPageBuilder::manage';

    /**
     * Context, result json factory, layout repository, target
     *
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param LayoutRepositoryInterface $layoutRepository
     * @param BuilderTarget $target
     */
    public function __construct(
        Context $context,
        private readonly JsonFactory $resultJsonFactory,
        private readonly LayoutRepositoryInterface $layoutRepository,
        private readonly BuilderTarget $target,
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
        // Already the decoded nodes, and an empty list when the design holds no document.
        $nodes = $this->layoutRepository->getLayout($this->target->getDesignId(), $this->target->getPagePath());

        return $this->resultJsonFactory->create()->setData(['content' => $nodes]);
    }
}
