<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Model;

/**
 * The pages the builder can be pointed at: global etc/di.xml, read by the editor and the storefront preview alike
 */
class PreviewPagebuilder
{
    /**
     * Pages
     *
     * @param array $pages
     */
    public function __construct(private array $pages = [])
    {
        // Stable, so pages sharing a number stay in the order they were declared.
        uasort(
            $this->pages,
            static fn (array $a, array $b): int => (int) ($a['sort_order'] ?? PHP_INT_MAX)
                <=> (int) ($b['sort_order'] ?? PHP_INT_MAX)
        );
    }

    /**
     * Get All
     *
     * @return array<string,array{label:string,path:string,route:string}>
     */
    public function getAll(): array
    {
        return $this->pages;
    }

    /**
     * Get
     *
     * @param string $code
     * @return array{label:string,path:string,route:string}|null
     */
    public function get(string $code): ?array
    {
        return $this->pages[$code] ?? null;
    }

    /**
     * The page the editor opens when the URL names none
     *
     * @return string
     */
    public function getDefaultCode(): string
    {
        return (string) array_key_first($this->pages);
    }
}
