<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Ui\Component\Listing\Column;

use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use OOCS\LynxPageBuilder\Model\PreviewPagebuilder;

/**
 * The Page column of the layout grid: what a path is, in the words the editor's page switcher uses
 */
class LayoutPage extends Column
{
    private const SHARED = [
        'header'    => 'Header',
        'footer'    => 'Footer',
        'backstage' => 'Backstage',
    ];

    /**
     * Context, ui component factory, builder pages, components, data
     *
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param PreviewPagebuilder $previewPagebuilder
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly PreviewPagebuilder $previewPagebuilder,
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

        $labels = self::SHARED;
        foreach ($this->previewPagebuilder->getAll() as $page) {
            if (!empty($page['path'])) {
                $labels[(string) $page['path']] = (string) $page['label'];
            }
        }

        $name = $this->getData('name');
        foreach ($dataSource['data']['items'] as &$item) {
            $label = $labels[(string) ($item['path'] ?? '')] ?? '';
            $item[$name] = $label === '' ? '' : (string) __($label);
        }

        return $dataSource;
    }
}
