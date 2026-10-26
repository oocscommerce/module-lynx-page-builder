<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Api;

use OOCS\LynxPageBuilder\Model\Design;

/**
 * Designs by id: the one place a design row is loaded, saved and deleted
 */
interface DesignRepositoryInterface
{
    /**
     * Get By Id
     *
     * @param int $id
     * @return Design
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById(int $id): Design;

    /**
     * Save
     *
     * @param Design $design
     * @return Design
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function save(Design $design): Design;

    /**
     * Delete
     *
     * @param Design $design
     * @return void
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function delete(Design $design): void;
}
