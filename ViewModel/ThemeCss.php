<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use OOCS\LynxPageBuilder\Helper\Data;
use OOCS\LynxPageBuilder\Model\PreviewMode;
use OOCS\LynxPageBuilder\Model\ThemeSettings;

/**
 * The design's stylesheet for <head>
 */
class ThemeCss implements ArgumentInterface
{
    /**
     * Theme settings, helper, preview
     *
     * @param ThemeSettings $themeSettings
     * @param Data $helper
     * @param PreviewMode $preview
     */
    public function __construct(
        private readonly ThemeSettings $themeSettings,
        private readonly Data $helper,
        private readonly PreviewMode $preview,
    ) {
    }

    /**
     * Compiled CSS of the previewed design, or of the store's active one
     *
     * @return string
     */
    public function getCss(): string
    {
        $designId = $this->preview->getDesignId() ?: $this->helper->getActiveDesignId();

        return $this->themeSettings->getCss($designId);
    }
}
