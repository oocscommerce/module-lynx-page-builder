<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Model;

use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use OOCS\LynxPageBuilder\Api\DesignRepositoryInterface;
use OOCS\LynxPageBuilder\Model\ResourceModel\Design as DesignResource;

/**
 * Loads, saves and deletes designs.
 */
class DesignRepository implements DesignRepositoryInterface
{
    /**
     * Designs loaded in this request, by id
     *
     * @var array<int, Design>
     */
    private array $designs = [];

    /**
     * Design factory, resource
     *
     * @param DesignFactory $designFactory
     * @param DesignResource $resource
     */
    public function __construct(
        private readonly DesignFactory $designFactory,
        private readonly DesignResource $resource,
    ) {
    }

    /**
     * Get By Id
     *
     * @param int $id
     * @return Design
     */
    public function getById(int $id): Design
    {
        if (!isset($this->designs[$id])) {
            $design = $this->designFactory->create();
            $this->resource->load($design, $id);

            if (!$design->getId()) {
                throw new NoSuchEntityException(__('The design with the "%1" ID doesn\'t exist.', $id));
            }

            $this->designs[$id] = $design;
        }

        return $this->designs[$id];
    }

    /**
     * Save
     *
     * @param Design $design
     * @return Design
     */
    public function save(Design $design): Design
    {
        try {
            $this->resource->save($design);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__('Could not save the design: %1', $e->getMessage()), $e);
        }

        return $this->designs[(int) $design->getId()] = $design;
    }

    /**
     * Delete
     *
     * @param Design $design
     * @return void
     */
    public function delete(Design $design): void
    {
        $id = (int) $design->getId();

        try {
            $this->resource->delete($design);
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(__('Could not delete the design: %1', $e->getMessage()), $e);
        }

        unset($this->designs[$id]);
    }
}
