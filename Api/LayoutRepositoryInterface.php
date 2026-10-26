<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Api;

interface LayoutRepositoryInterface
{

    /**
     * Save Layout
     *
     * @param \OOCS\LynxPageBuilder\Api\Data\LayoutInterface $layout
     * @return \OOCS\LynxPageBuilder\Api\Data\LayoutInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function save(
        \OOCS\LynxPageBuilder\Api\Data\LayoutInterface $layout
    );

    /**
     * Retrieve Layout
     *
     * @param string $id
     * @return \OOCS\LynxPageBuilder\Api\Data\LayoutInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getById($id);

    /**
     * Retrieve Layout matching the specified criteria
     *
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return \OOCS\LynxPageBuilder\Api\Data\LayoutSearchResultsInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getList(
        \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
    );

    /**
     * The nodes a design holds for a path, decoded, or an empty list when it holds none
     *
     * @param int $designId
     * @param string $path
     * @return array<int, array<string, mixed>>
     */
    public function getLayout($designId, $path);

    /**
     * The path of the page layout this request read with getLayout()
     *
     * @return string|null
     */
    public function getContentPath(): ?string;

    /**
     * Delete Layout
     *
     * @param \OOCS\LynxPageBuilder\Api\Data\LayoutInterface $layout
     * @return bool true on success
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function delete(
        \OOCS\LynxPageBuilder\Api\Data\LayoutInterface $layout
    );

    /**
     * Delete Layout by ID
     *
     * @param string $id
     * @return bool true on success
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function deleteById($id);
}
