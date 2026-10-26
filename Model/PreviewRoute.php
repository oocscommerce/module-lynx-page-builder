<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Model;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Indexer\Category\Product\TableMaintainer;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Search\Model\ResourceModel\Query\CollectionFactory as SearchQueryCollectionFactory;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * The storefront route the preview opens for the page being edited
 */
class PreviewRoute
{
    /**
     * Target, builder pages, product collection factory, category collection factory, store manager, visibility
     *
     * @param BuilderTarget $target
     * @param PreviewPagebuilder $previewPagebuilder
     * @param ProductCollectionFactory $productCollectionFactory
     * @param CategoryCollectionFactory $categoryCollectionFactory
     * @param StoreManagerInterface $storeManager
     * @param Visibility $visibility
     * @param TableMaintainer $tableMaintainer
     * @param ResourceConnection $resource
     * @param SearchQueryCollectionFactory $searchQueryCollectionFactory
     */
    public function __construct(
        private readonly BuilderTarget $target,
        private readonly PreviewPagebuilder $previewPagebuilder,
        private readonly ProductCollectionFactory $productCollectionFactory,
        private readonly CategoryCollectionFactory $categoryCollectionFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly Visibility $visibility,
        private readonly TableMaintainer $tableMaintainer,
        private readonly ResourceConnection $resource,
        private readonly SearchQueryCollectionFactory $searchQueryCollectionFactory,
    ) {
    }

    /**
     * Get
     *
     * @return string
     */
    public function get(): string
    {
        // A layout is previewed as itself, and there is no sample to look up
        $source = $this->target->getContentSource();

        if ($source !== null) {
            return $source->getPreviewRoute();
        }

        return $this->forPage($this->target->getPageCode(), $this->target->getStore());
    }

    /**
     * The route of a page on a store, with a sample entity appended when the page needs one
     *
     * @param string $code
     * @param StoreInterface $store
     * @return string
     */
    public function forPage(string $code, StoreInterface $store): string
    {
        $page  = $this->previewPagebuilder->get($code) ?? [];
        $route = (string) ($page['route'] ?? '');

        return match ($page['sample'] ?? '') {
            'product'     => $this->append($route, 'id', $this->getProductId($store, (string) ($page['type'] ?? ''))),
            'category'    => $this->append($route, 'id', $this->getCategoryId($store)),
            // An empty query text is bounced back by Result\Index
            'search_term' => $this->append($route, 'q', $this->getSearchTerm($store)),
            default       => $route,
        };
    }

    /**
     * Append
     *
     * @param string $route
     * @param string $key
     * @param string $value
     * @return string
     */
    private function append(string $route, string $key, string $value): string
    {
        return $value === '' ? $route : $route . '/' . $key . '/' . rawurlencode($value);
    }

    /**
     * Get Product Id
     *
     * @param StoreInterface $store
     * @param string $typeId
     * @return string
     */
    private function getProductId(StoreInterface $store, string $typeId): string
    {
        return (string) ($this->getProduct($store, $typeId)->getId() ?: '');
    }

    /**
     * A term the search page has something to answer with
     *
     * @param StoreInterface $store
     * @return string
     */
    private function getSearchTerm(StoreInterface $store): string
    {
        $queries = $this->searchQueryCollectionFactory->create();
        $queries->setPopularQueryFilter([(int) $store->getId()])->setPageSize(1);

        return trim((string) $queries->getFirstItem()->getQueryText());
    }

    /**
     * Get Product
     *
     * @param StoreInterface $store
     * @param string $typeId
     * @return \Magento\Catalog\Model\Product
     */
    private function getProduct(StoreInterface $store, string $typeId)
    {
        $collection = $this->productCollectionFactory->create();
        $collection->addStoreFilter((int) $store->getId())
            ->addAttributeToSelect('name')
            ->addAttributeToFilter('status', Status::STATUS_ENABLED)
            ->setVisibility($this->visibility->getVisibleInSiteIds())
            ->setPageSize(1);

        if ($typeId !== '') {
            $collection->addAttributeToFilter('type_id', $typeId);
        }

        return $collection->getFirstItem();
    }

    /**
     * A category worth previewing a category page on: one that actually lists products
     *
     * @param StoreInterface $store
     * @return string
     */
    private function getCategoryId(StoreInterface $store): string
    {
        $storeId = (int) $store->getId();
        $rootId = (int) $this->storeManager->getGroup($store->getStoreGroupId())->getRootCategoryId();

        $collection = $this->categoryCollectionFactory->create();
        $collection->setStoreId($storeId)
            ->addAttributeToFilter('is_active', 1)
            ->addFieldToFilter('path', ['like' => '1/' . $rootId . '/%'])
            // Not "display mode is PRODUCTS or PRODUCTS_AND_PAGE"
            ->addAttributeToFilter(
                'display_mode',
                [['neq' => Category::DM_PAGE], ['null' => true]],
                'left'
            )
            ->setOrder('level', 'ASC')
            ->setOrder('position', 'ASC')
            // Position repeats across branches, so without a last word the database is free to answer with a different
            ->setOrder('entity_id', 'ASC')
            ->setPageSize(1);

        // Has something to list
        $indexTable = $this->tableMaintainer->getMainTable($storeId);
        if ($this->resource->getConnection()->isTableExists($indexTable)) {
            $collection->getSelect()
                ->join(['cat_index' => $indexTable], 'cat_index.category_id = e.entity_id', [])
                ->group('e.entity_id');
        }

        return (string) ($collection->getFirstItem()->getId() ?: '');
    }
}
