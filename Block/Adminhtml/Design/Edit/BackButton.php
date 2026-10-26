<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Block\Adminhtml\Design\Edit;

use Magento\Backend\Model\UrlInterface;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

/**
 * Back button of the design form.
 */
class BackButton implements ButtonProviderInterface
{
    /**
     * The admin URL model, not the storefront one Magento\Framework\UrlInterface resolves to
     *
     * @param UrlInterface $url
     */
    public function __construct(
        private readonly UrlInterface $url,
    ) {
    }

    /**
     * Get Button Data
     *
     * @return array
     */
    public function getButtonData(): array
    {
        return [
            'label'      => __('Back'),
            'on_click'   => \sprintf("location.href = '%s';", $this->url->getUrl('lynxpagebuilder/design/index')),
            'class'      => 'back',
            'sort_order' => 10,
        ];
    }
}
