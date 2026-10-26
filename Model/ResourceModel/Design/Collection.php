<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Model\ResourceModel\Design;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * Design collection.
 */
class Collection extends AbstractCollection
{
    /**
     * @var mixed
     */
    protected $_idFieldName = 'id';

    /**
     * Construct
     */
    protected function _construct()
    {
        $this->_init(
            \OOCS\LynxPageBuilder\Model\Design::class,
            \OOCS\LynxPageBuilder\Model\ResourceModel\Design::class
        );
    }

    /**
     * Decodes the JSON columns of every item, as the resource does for a single load
     *
     * @return $this
     */
    protected function _afterLoad()
    {
        foreach ($this->_items as $item) {
            $this->getResource()->unserializeFields($item);
        }

        return parent::_afterLoad();
    }
}
