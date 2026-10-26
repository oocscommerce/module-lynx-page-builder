<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Model\ResourceModel\Layout;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * Layout collection.
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
            \OOCS\LynxPageBuilder\Model\Layout::class,
            \OOCS\LynxPageBuilder\Model\ResourceModel\Layout::class
        );
    }
}
