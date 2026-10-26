<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Plugin;

use OOCS\LynxComponent\Model\PageCacheIdentities;
use OOCS\LynxPageBuilder\Api\LayoutRepositoryInterface;

/**
 * Tags the full page cache entry of a page built out of a stored layout
 */
class LayoutCacheTags
{
    /**
     * Identities
     *
     * @param PageCacheIdentities $identities
     */
    public function __construct(
        private readonly PageCacheIdentities $identities,
    ) {
    }

    /**
     * After Get Layout
     *
     * @param LayoutRepositoryInterface $subject
     * @param mixed $result
     * @param int|string $designId
     * @param string $path
     * @return mixed
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetLayout(LayoutRepositoryInterface $subject, $result, $designId, $path)
    {
        if (!\in_array($path, ['header', 'footer', 'backstage'], true)) {
            $this->identities->add(['lx_' . $designId, 'lx_' . $designId . '_' . $path]);
        }

        return $result;
    }
}
