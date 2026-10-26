<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Model;

use OOCS\LynxPageBuilder\Api\LayoutSourceInterface;

/**
 * The layout sources registered in etc/di.xml, keyed by type (cms_page, category)
 */
class LayoutSources
{
    /**
     * Sources
     *
     * @param array $sources
     */
    public function __construct(
        private readonly array $sources = [],
    ) {
        foreach ($sources as $type => $source) {
            if (!$source instanceof LayoutSourceInterface) {
                throw new \InvalidArgumentException(
                    \sprintf('Layout source "%s" must implement %s.', $type, LayoutSourceInterface::class)
                );
            }
        }
    }

    /**
     * Get
     *
     * @param string $type
     * @return LayoutSourceInterface|null
     */
    public function get(string $type): ?LayoutSourceInterface
    {
        return $this->sources[$type] ?? null;
    }

    /**
     * Get All
     *
     * @return array<string, LayoutSourceInterface>
     */
    public function getAll(): array
    {
        return $this->sources;
    }
}
