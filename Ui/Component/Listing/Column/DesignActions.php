<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * What can be done to a design from its row
 */
class DesignActions extends Column
{
    /**
     * Context, ui component factory, url builder, components, data
     *
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param UrlInterface $urlBuilder
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * Prepare Data Source
     *
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }

        $name = $this->getData('name');

        foreach ($dataSource['data']['items'] as &$item) {
            $id = (int) ($item['id'] ?? 0);
            if (!$id) {
                continue;
            }

            $actions = [
                'edit' => [
                    'href'  => $this->urlBuilder->getUrl('lynxpagebuilder/design/edit', ['id' => $id]),
                    'label' => __('Edit'),
                ],
                'build' => [
                    'href'  => $this->urlBuilder->getUrl('lynxpagebuilder/index/index', ['design' => $id]),
                    'label' => __('Build'),
                ],
                'layouts' => [
                    'href'  => $this->urlBuilder->getUrl('lynxpagebuilder/layout/index', ['design_id' => $id]),
                    'label' => __('Layouts'),
                ],
                'duplicate' => [
                    'href'  => $this->urlBuilder->getUrl('lynxpagebuilder/design/duplicate', ['id' => $id]),
                    'label' => __('Duplicate'),
                ],
            ];

            // A read-only design has no delete; Design\Delete refuses it as well.
            if (empty($item['readonly'])) {
                $actions['delete'] = [
                    'href'    => $this->urlBuilder->getUrl('lynxpagebuilder/design/delete', ['id' => $id]),
                    'label'   => __('Delete'),
                    'confirm' => [
                        'title'   => __('Delete "%1"', $item['name'] ?? ''),
                        'message' => __('This deletes the design and every layout in it.'),
                    ],
                ];
            }

            $item[$name] = $actions;
        }

        return $dataSource;
    }
}
