<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Model;

use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Model\AbstractModel;

/**
 * A design: a name and the layouts that belong to it (lynx_pagebuilder_layout rows keyed by its id)
 */
class Design extends AbstractModel implements IdentityInterface
{
    /**
     * The design an install starts with: etc/config.xml names it as the fallback, and it cannot be deleted
     */
    public const DEFAULT_ID = 1;

    public const CACHE_TAG = 'lynx_pb_design';

    /**
     * @var string
     */
    protected $_cacheTag = self::CACHE_TAG;

    /**
     * @var string
     */
    protected $_eventPrefix = 'lynx_pb_design';

    /**
     * Construct
     */
    protected function _construct()
    {
        $this->_init(ResourceModel\Design::class);
    }

    /**
     * Whether the editor may write to this design and the grid may delete it
     *
     * @return bool
     */
    public function isReadonly(): bool
    {
        return (bool) $this->getData('readonly');
    }

    /**
     * The theme settings as saved (Model\ThemeSettings normalizes them for use); the resource decodes the column
     *
     * @return array<string,mixed>
     */
    public function getSettings(): array
    {
        return (array) $this->getData('settings');
    }

    /**
     * Set Settings
     *
     * @param array $settings
     * @return self
     */
    public function setSettings(array $settings): self
    {
        return $this->setData('settings', $settings);
    }

    /**
     * The designs this one falls back to, in priority order
     *
     * @return int[]
     */
    public function getParents(): array
    {
        return array_values(array_map('intval', (array) $this->getData('parents')));
    }

    /**
     * Set Parents
     *
     * @param int[] $parents Design ids, first is tried first
     * @return self
     */
    public function setParents(array $parents): self
    {
        // A list, not an object: [] and [3, 1] are what the column holds, never {}.
        return $this->setData('parents', array_values($parents));
    }

    /**
     * The full page cache tag every page rendered out of this design carries
     *
     * @return string[]
     */
    public function getIdentities()
    {
        $purge = $this->isDeleted() || (!$this->isObjectNew() && $this->dataHasChangedFor('settings'));

        return $purge ? ['lx_' . (int) $this->getId()] : [];
    }
}
