<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Api;

/**
 * A module that adds to the editor without being part of it
 */
interface ExtensionInterface
{
    /**
     * URL of the ES module bundle to load, or '' when the extension is off for this request
     *
     * @return string
     */
    public function getScriptUrl(): string;

    /**
     * Handed to the bundle's default export as its only argument, JSON-encoded on the way
     *
     * @return array<string, mixed>
     */
    public function getConfig(): array;

    /**
     * Hosts the extension's JavaScript calls from the editor page
     *
     * @return string[]
     */
    public function getConnectSources(): array;
}
