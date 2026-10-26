<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Model\ResourceModel;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Context;
use OOCS\LynxPageBuilder\Model\Layout;

/**
 * Design resource; deleting a design drops its layout rows.
 */
class Design extends AbstractDb
{
    /**
     * JSON columns, decoded after load and encoded before save
     *
     * @var array<string,array{0:array,1:array}>
     */
    protected $_serializableFields = [
        'settings' => [[], []],
        'parents'  => [[], []],
    ];

    /**
     * Context, cache, connection name
     *
     * @param Context $context
     * @param CacheInterface $cache
     * @param string|null $connectionName
     */
    public function __construct(
        Context $context,
        private readonly CacheInterface $cache,
        ?string $connectionName = null,
    ) {
        parent::__construct($context, $connectionName);
    }

    /**
     * Construct
     */
    protected function _construct()
    {
        $this->_init('lynx_pagebuilder_design', 'id');
    }

    /**
     * The layouts go with the design, and so must what was cached of them
     *
     * @param AbstractModel $object
     * @return $this
     */
    protected function _afterDelete(AbstractModel $object)
    {
        $this->cache->clean([Layout::CACHE_TAG . '_' . (int) $object->getId()]);

        return parent::_afterDelete($object);
    }
}
