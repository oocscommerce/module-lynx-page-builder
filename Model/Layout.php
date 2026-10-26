<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Model;

use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Model\AbstractModel;
use OOCS\LynxPageBuilder\Api\Data\LayoutInterface;

/**
 * One stored zone layout of a design: a row of lynx_pagebuilder_layout.
 */
class Layout extends AbstractModel implements LayoutInterface, IdentityInterface
{
    public const CACHE_TAG = 'lpb';

    /**
     * @var string
     */
    protected $_cacheTag = self::CACHE_TAG;

    /**
     * Prefix of model events names
     *
     * @var string
     */
    protected $_eventPrefix = 'pb_layout';

    /**
     * Construct
     */
    public function _construct()
    {
        $this->_init(ResourceModel\Layout::class);
    }

    /**
     * Get Id
     *
     * @return string|null
     */
    public function getId()
    {
        return $this->getData(self::ID);
    }

    /**
     * Set Id
     *
     * @param string $id
     * @return $this
     */
    public function setId($id)
    {
        return $this->setData(self::ID, $id);
    }

    /**
     * Get Scope
     *
     * @return string|null
     */
    public function getScope()
    {
        return $this->getData(self::SCOPE);
    }

    /**
     * Set Scope
     *
     * @param string $scope
     * @return $this
     */
    public function setScope($scope)
    {
        return $this->setData(self::SCOPE, $scope);
    }

    /**
     * Get Scope Id
     *
     * @return string|null
     */
    public function getScopeId()
    {
        return $this->getData(self::SCOPE_ID);
    }

    /**
     * Set Scope Id
     *
     * @param string $scopeId
     * @return $this
     */
    public function setScopeId($scopeId)
    {
        return $this->setData(self::SCOPE_ID, $scopeId);
    }

    /**
     * Get Path
     *
     * @return string|null
     */
    public function getPath()
    {
        return $this->getData(self::PATH);
    }

    /**
     * Set Path
     *
     * @param string $path
     * @return $this
     */
    public function setPath($path)
    {
        return $this->setData(self::PATH, $path);
    }

    /**
     * Get Value
     *
     * @return string|null
     */
    public function getValue()
    {
        return $this->getData(self::VALUE);
    }

    /**
     * Set Value
     *
     * @param string $value
     * @return $this
     */
    public function setValue($value)
    {
        return $this->setData(self::VALUE, $value);
    }

    /**
     * The full page cache tags this layout's pages carry
     *
     * @return string[]
     */
    public function getIdentities()
    {
        $path = (string) $this->getPath();
        $tag = 'lx_' . (int) $this->getData('design_id');

        return [\in_array($path, ['header', 'footer', 'backstage'], true) ? $tag : $tag . '_' . $path];
    }

    /**
     * Get Updated At
     *
     * @return string|null
     */
    public function getUpdatedAt()
    {
        return $this->getData(self::UPDATED_AT);
    }

    /**
     * Set Updated At
     *
     * @param string $updatedAt
     * @return $this
     */
    public function setUpdatedAt($updatedAt)
    {
        return $this->setData(self::UPDATED_AT, $updatedAt);
    }
}
