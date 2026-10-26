<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use OOCS\LynxPageBuilder\Model\ResourceModel\Design\CollectionFactory;

/**
 * The designs a store can be pointed at, for the field that points it (lynx_design/general/active)
 */
class Design implements OptionSourceInterface
{
    /**
     * Collection factory
     *
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
    ) {
    }

    /**
     * To Option Array
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        $collection = $this->collectionFactory->create();
        $collection->setOrder('id', 'ASC');

        $options = [];

        foreach ($collection as $design) {
            $options[] = [
                'value' => (int) $design->getId(),
                'label' => $design->getName(),
            ];
        }

        return $options;
    }
}
