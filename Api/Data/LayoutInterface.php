<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Api\Data;

interface LayoutInterface
{
    public const ID = 'id';
    public const SCOPE_ID = 'scope_id';
    public const SCOPE = 'scope';
    public const VALUE = 'value';
    public const PATH = 'path';
    public const UPDATED_AT = 'updated_at';

    /**
     * Get id
     *
     * @return string|null
     */
    public function getId();

    /**
     * Set id
     *
     * @param string $id
     * @return $this
     */
    public function setId($id);

    /**
     * Get scope
     *
     * @return string|null
     */
    public function getScope();

    /**
     * Set scope
     *
     * @param string $scope
     * @return $this
     */
    public function setScope($scope);

    /**
     * Get scope_id
     *
     * @return string|null
     */
    public function getScopeId();

    /**
     * Set scope_id
     *
     * @param string $scopeId
     * @return $this
     */
    public function setScopeId($scopeId);

    /**
     * Get path
     *
     * @return string|null
     */
    public function getPath();

    /**
     * Set path
     *
     * @param string $path
     * @return $this
     */
    public function setPath($path);

    /**
     * Get value
     *
     * @return string|null
     */
    public function getValue();

    /**
     * Set value
     *
     * @param string $value
     * @return $this
     */
    public function setValue($value);

    /**
     * Get updated_at
     *
     * @return string|null
     */
    public function getUpdatedAt();

    /**
     * Set updated_at
     *
     * @param string $updatedAt
     * @return $this
     */
    public function setUpdatedAt($updatedAt);
}
