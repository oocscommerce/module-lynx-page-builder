<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Model;

use OOCS\LynxPageBuilder\Api\ExtensionInterface;

/**
 * The editor extensions installed, as etc/di.xml collects them
 */
class Extensions
{
    /**
     * Extensions
     *
     * @param ExtensionInterface[] $extensions
     */
    public function __construct(
        private readonly array $extensions = [],
    ) {
    }

    /**
     * Get Active
     *
     * @return ExtensionInterface[]
     */
    public function getActive(): array
    {
        return array_values(array_filter(
            $this->extensions,
            static fn (ExtensionInterface $extension): bool => $extension->getScriptUrl() !== ''
        ));
    }
}
