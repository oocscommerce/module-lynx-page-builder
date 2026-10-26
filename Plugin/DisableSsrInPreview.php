<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Plugin;

use OOCS\LynxInertia\Service\SsrGate;
use OOCS\LynxPageBuilder\Model\PreviewMode;

/**
 * Keeps a design preview off the server side renderer
 */
class DisableSsrInPreview
{
    /**
     * Preview mode
     *
     * @param PreviewMode $previewMode
     */
    public function __construct(
        private readonly PreviewMode $previewMode,
    ) {
    }

    /**
     * The route list cannot be what keeps SSR out of a preview
     *
     * @param SsrGate $subject
     * @param bool $result
     * @return bool
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterIsEnabled(SsrGate $subject, bool $result): bool
    {
        return $result && !$this->previewMode->isActive();
    }
}
