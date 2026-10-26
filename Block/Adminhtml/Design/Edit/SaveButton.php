<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Block\Adminhtml\Design\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

/**
 * Save button of the design form.
 */
class SaveButton implements ButtonProviderInterface
{
    /**
     * Get Button Data
     *
     * @return array
     */
    public function getButtonData(): array
    {
        return [
            'label' => __('Save Design'),
            'class' => 'save primary',
            // Submits the form component rather than following a URL, which is what a form's Save has to do
            'data_attribute' => [
                'mage-init' => [
                    'buttonAdapter' => [
                        'actions' => [
                            [
                                'targetName' => 'lynx_pagebuilder_design_form.lynx_pagebuilder_design_form',
                                'actionName' => 'save',
                                'params'     => [false],
                            ],
                        ],
                    ],
                ],
            ],
            'sort_order' => 90,
        ];
    }
}
