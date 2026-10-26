<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Api;

use Magento\Framework\Exception\NoSuchEntityException;

/**
 * An entity that keeps its own Lynx layout outside the designs (a CMS page, a category), by id
 */
interface LayoutSourceInterface
{
    /**
     * The full action name the storefront renders it under
     *
     * @return string
     */
    public function getPagePath(): string;

    /**
     * The entity's id from an id or another key it is known by (a CMS page's identifier)
     *
     * @param string $key
     * @param int $storeId
     * @return int
     * @throws NoSuchEntityException
     */
    public function resolveId(string $key, int $storeId): int;

    /**
     * Its title in the store view
     *
     * @param int $id
     * @param int $storeId
     * @return string
     * @throws NoSuchEntityException
     */
    public function getLabel(int $id, int $storeId): string;

    /**
     * The layout as stored, an empty list when it keeps none
     *
     * @param int $id
     * @param int $storeId
     * @return array<int, array<string, mixed>>
     * @throws NoSuchEntityException
     */
    public function getNodes(int $id, int $storeId): array;

    /**
     * Store the layout; an empty list removes it
     *
     * @param int $id
     * @param int $storeId
     * @param array $nodes
     * @return void
     * @throws NoSuchEntityException
     */
    public function saveNodes(int $id, int $storeId, array $nodes): void;

    /**
     * Whether "Render with Lynx" is on for the store view
     *
     * @param int $id
     * @param int $storeId
     * @return bool
     * @throws NoSuchEntityException
     */
    public function isEnabled(int $id, int $storeId): bool;

    /**
     * Switch "Render with Lynx"
     *
     * @param int $id
     * @param int $storeId
     * @param bool $enabled
     * @return void
     * @throws NoSuchEntityException
     */
    public function setEnabled(int $id, int $storeId, bool $enabled): void;

    /**
     * Whether the store view shows it at all: assigned to the store and active
     *
     * @param int $id
     * @param int $storeId
     * @return bool
     * @throws NoSuchEntityException
     */
    public function isAvailable(int $id, int $storeId): bool;

    /**
     * The storefront route it renders under, as a visitor reaches it
     *
     * @param int $id
     * @param int $storeId
     * @return string
     * @throws NoSuchEntityException
     */
    public function getPreviewRoute(int $id, int $storeId): string;

    /**
     * Whether its "Render with Lynx" and layout differ by store view
     *
     * @return bool
     */
    public function isStoreScoped(): bool;
}
