<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Helper;

use Magento\Backend\App\Area\FrontNameResolver;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;

/**
 * The active design id and the admin origin the preview bridge talks to.
 */
class Data extends AbstractHelper
{
    /**
     * Which design a store renders with; always read at store scope so core fallback applies
     */
    public const XML_PATH_ACTIVE_DESIGN = 'lynx_design/general/active';

    /**
     * The design the current store renders with
     *
     * @return int
     */
    public function getActiveDesignId()
    {
        return (int) $this->scopeConfig->getValue(
            self::XML_PATH_ACTIVE_DESIGN,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Scheme, host and port of the admin: its custom URL when one is set, the default scope's base URL otherwise
     *
     * @return string
     */
    public function getAdminOrigin(): string
    {
        $url = $this->scopeConfig->isSetFlag(FrontNameResolver::XML_PATH_USE_CUSTOM_ADMIN_URL)
            ? (string) $this->scopeConfig->getValue(FrontNameResolver::XML_PATH_CUSTOM_ADMIN_URL)
            : (string) $this->scopeConfig->getValue(Store::XML_PATH_SECURE_BASE_URL);

        // phpcs:ignore Magento2.Functions.DiscouragedFunction
        $parts = parse_url($url);

        if (empty($parts['scheme']) || empty($parts['host'])) {
            return '';
        }

        return $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
    }
}
