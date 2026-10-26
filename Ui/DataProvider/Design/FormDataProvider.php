<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Ui\DataProvider\Design;

use Magento\Ui\DataProvider\AbstractDataProvider;
use OOCS\LynxPageBuilder\Model\ResourceModel\Design\CollectionFactory;

/**
 * Fills the design form
 */
class FormDataProvider extends AbstractDataProvider
{
    /**
     * Name, primary field name, request field name, collection factory, meta, data
     *
     * @param mixed $name
     * @param mixed $primaryFieldName
     * @param mixed $requestFieldName
     * @param CollectionFactory $collectionFactory
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();

        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    /**
     * Get Data
     *
     * @return array
     */
    public function getData(): array
    {
        $data = [];

        foreach ($this->collection as $design) {
            $row = $design->getData();
            // The parents column is a list of ids; the form's dynamic rows want one record per parent
            $row['parents'] = array_map(
                static fn (int $position, int $id): array => ['design' => $id, 'position' => $position],
                array_keys($design->getParents()),
                $design->getParents()
            );
            $data[$design->getId()] = $row;
        }

        return $data;
    }
}
