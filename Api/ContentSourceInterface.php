<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Api;

/**
 * A single layout the editor can be opened on, instead of a page of a design
 */
interface ContentSourceInterface
{
    /**
     * Whether the editor was opened on one of these
     *
     * @return bool
     */
    public function isActive(): bool;

    /**
     * What the editor's title bar says it is editing
     *
     * @return string
     */
    public function getLabel(): string;

    /**
     * The full action name the storefront renders it under
     *
     * @return string
     */
    public function getPagePath(): string;

    /**
     * The content zone as stored, and an empty list for a layout not built yet
     *
     * @return array<int, array<string, mixed>>
     */
    public function getNodes(): array;

    /**
     * Store the content zone
     *
     * @param array $nodes
     * @return void
     */
    public function saveNodes(array $nodes): void;

    /**
     * The storefront route the preview frame opens: the page as a visitor reaches it
     *
     * @return string
     */
    public function getPreviewRoute(): string;

    /**
     * Where Exit goes, which is wherever the editor was opened from
     *
     * @return string
     */
    public function getExitUrl(): string;

    /**
     * What identifies this layout in a URL
     *
     * @return array<string, int|string>
     */
    public function getUrlParams(): array;
}
