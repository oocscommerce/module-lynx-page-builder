<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Model;

use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\SerializerInterface;
use OOCS\LynxComponent\Model\Cache\Type\ComponentCache;
use OOCS\LynxPageBuilder\Api\Data\LayoutInterface;
use OOCS\LynxPageBuilder\Api\Data\LayoutInterfaceFactory;
use OOCS\LynxPageBuilder\Api\Data\LayoutSearchResultsInterface;
use OOCS\LynxPageBuilder\Api\Data\LayoutSearchResultsInterfaceFactory;
use OOCS\LynxPageBuilder\Api\DesignRepositoryInterface;
use OOCS\LynxPageBuilder\Api\LayoutRepositoryInterface;
use OOCS\LynxPageBuilder\Model\ResourceModel\Layout as ResourceLayout;
use OOCS\LynxPageBuilder\Model\ResourceModel\Layout\CollectionFactory as LayoutCollectionFactory;

/**
 * Reads and writes layout rows, resolving a path through the design's parent chain.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class LayoutRepository implements LayoutRepositoryInterface
{
    /**
     * The zones every page shares. Anything else read is a page's own document.
     */
    private const SHARED_ZONES = ['header', 'footer', 'backstage'];

    /**
     * @var array<string,array<int,array<string,mixed>>>
     */
    private array $cacheLayout = [];

    /**
     * @var string|null
     */
    private ?string $contentPath = null;

    /**
     * Resource, layout factory, layout collection factory, search results factory, collection processor
     *
     * @param ResourceLayout $resource
     * @param LayoutInterfaceFactory $layoutFactory
     * @param LayoutCollectionFactory $layoutCollectionFactory
     * @param LayoutSearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionProcessor
     * @param ComponentCache $cache
     * @param SerializerInterface $serializer
     * @param DesignRepositoryInterface $designRepository
     * @param EventManager $eventManager
     */
    public function __construct(
        private readonly ResourceLayout $resource,
        private readonly LayoutInterfaceFactory $layoutFactory,
        private readonly LayoutCollectionFactory $layoutCollectionFactory,
        private readonly LayoutSearchResultsInterfaceFactory $searchResultsFactory,
        private readonly CollectionProcessorInterface $collectionProcessor,
        private readonly ComponentCache $cache,
        private readonly SerializerInterface $serializer,
        private readonly DesignRepositoryInterface $designRepository,
        private readonly EventManager $eventManager,
    ) {
    }

    /**
     * Save
     *
     * @param LayoutInterface $layout
     * @return LayoutInterface
     */
    public function save(LayoutInterface $layout)
    {
        try {
            $this->resource->save($layout);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(__($exception->getMessage()));
        }
        $this->purgeInheriting($layout);
        return $layout;
    }

    /**
     * Purge the pages of the designs that read this layout through this design
     *
     * @param LayoutInterface $layout
     * @return void
     */
    private function purgeInheriting(LayoutInterface $layout): void
    {
        $designId = (int) $layout->getData('design_id');
        $path = (string) $layout->getPath();

        foreach ($this->resource->getInheritingDesignIds($designId, $path) as $childId) {
            $this->eventManager->dispatch('clean_cache_by_tags', [
                'object' => $this->layoutFactory->create()->setData(['design_id' => $childId, 'path' => $path]),
            ]);
        }
    }

    /**
     * Get By Id
     *
     * @param mixed $id
     * @return LayoutInterface
     */
    public function getById($id)
    {
        $layout = $this->layoutFactory->create();
        $this->resource->load($layout, $id);
        if (!$layout->getId()) {
            throw new NoSuchEntityException(__('Layout with id "%1" does not exist.', $id));
        }
        return $layout;
    }

    /**
     * Get List
     *
     * @param SearchCriteriaInterface $criteria
     * @return LayoutSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $criteria)
    {
        $collection = $this->layoutCollectionFactory->create();

        $this->collectionProcessor->process($criteria, $collection);

        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($criteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());
        return $searchResults;
    }

    /**
     * Get Content Path
     *
     * @return string|null
     */
    public function getContentPath(): ?string
    {
        return $this->contentPath;
    }

    /**
     * Get Layout
     *
     * @param mixed $designId
     * @param mixed $path
     * @return array<int,array<string,mixed>>
     */
    public function getLayout($designId, $path)
    {
        // The path as it arrives here, after every plugin that rewrites it
        if (!\in_array($path, self::SHARED_ZONES, true)) {
            $this->contentPath = (string) $path;
        }

        $cacheKey = "{$designId}_{$path}";

        if (!isset($this->cacheLayout[$cacheKey])) {
            // The memo holds the decoded nodes rather than the layout, so a request that asks twice decodes once
            $value = $this->cache->load('lynx_layout_' . $cacheKey);

            if ($value === false) {
                $value = $this->resource->getLayoutValue(
                    $this->getDesignIdsWithParents((int) $designId),
                    (string) $path
                );
                $this->cache->save(
                    $value,
                    'lynx_layout_' . $cacheKey,
                    [Layout::CACHE_TAG, Layout::CACHE_TAG . '_' . (int) $designId]
                );
            }

            // Decoded here rather than by each caller
            try {
                $nodes = $value === '' ? [] : $this->serializer->unserialize($value);
            } catch (\InvalidArgumentException) {
                // A row that does not parse renders as an empty layout
                $nodes = [];
            }
            $this->cacheLayout[$cacheKey] = \is_array($nodes) ? $nodes : [];
        }

        return $this->cacheLayout[$cacheKey];
    }

    /**
     * The design itself first, then its parents in order, without duplicates
     *
     * @param int $designId
     * @return int[]
     */
    private function getDesignIdsWithParents(int $designId): array
    {
        try {
            $parents = $this->designRepository->getById($designId)->getParents();
        } catch (NoSuchEntityException) {
            $parents = [];
        }

        return array_values(array_unique([$designId, ...$parents]));
    }

    /**
     * Delete
     *
     * @param LayoutInterface $layout
     * @return bool
     */
    public function delete(LayoutInterface $layout)
    {
        try {
            $this->resource->delete($layout);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(__($exception->getMessage()));
        }
        $this->purgeInheriting($layout);
        return true;
    }

    /**
     * Delete By Id
     *
     * @param mixed $id
     * @return bool
     */
    public function deleteById($id)
    {
        return $this->delete($this->getById($id));
    }
}
