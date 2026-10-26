<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Api\Data;

interface LayoutSearchResultsInterface extends \Magento\Framework\Api\SearchResultsInterface
{

    /**
     * Get Layout list
     *
     * @return \OOCS\LynxPageBuilder\Api\Data\LayoutInterface[]
     */
    public function getItems();

    /**
     * Set id list
     *
     * @param \OOCS\LynxPageBuilder\Api\Data\LayoutInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}
