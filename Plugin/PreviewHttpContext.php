<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Plugin;

use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Http\Context as HttpContext;
use OOCS\LynxPageBuilder\Model\PreviewMode;

/**
 * Puts the design being previewed into the vary context
 */
class PreviewHttpContext
{
    /**
     * Http context, preview
     *
     * @param HttpContext $httpContext
     * @param PreviewMode $preview
     */
    public function __construct(
        private readonly HttpContext $httpContext,
        private readonly PreviewMode $preview,
    ) {
    }

    /**
     * Before Execute: every action, those without dispatch() too, as core's customer context plugin
     *
     * @param ActionInterface $subject
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeExecute(ActionInterface $subject): void
    {
        $designId = $this->preview->getDesignId();

        // Nothing set when nobody is previewing: Context::getData() drops entries equal to their default anyway
        if ($designId) {
            $this->httpContext->setValue(PreviewMode::CONTEXT_KEY, $designId, 0);
        }
    }
}
