<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Block\Adminhtml\Design\Edit;

use Magento\Backend\Model\UrlInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

/**
 * Opens the grid of the layouts this design holds (Controller\Adminhtml\Layout\Index)
 */
class LayoutsButton implements ButtonProviderInterface
{
    /**
     * Url, request
     *
     * @param UrlInterface $url
     * @param RequestInterface $request
     */
    public function __construct(
        private readonly UrlInterface $url,
        private readonly RequestInterface $request,
    ) {
    }

    /**
     * Get Button Data
     *
     * @return array
     */
    public function getButtonData(): array
    {
        $id = (int) $this->request->getParam('id');
        if (!$id) {
            return [];
        }

        return [
            'label'      => __('Layouts'),
            'on_click'   => \sprintf(
                "location.href = '%s';",
                $this->url->getUrl('lynxpagebuilder/layout/index', ['design_id' => $id])
            ),
            'sort_order' => 20,
        ];
    }
}
