<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Layout resource: a path's value across a design chain, and the designs inheriting it.
 */
class Layout extends AbstractDb
{
    /**
     * Construct
     */
    protected function _construct()
    {
        $this->_init('lynx_pagebuilder_layout', 'id');
    }

    /**
     * The layout for a path, from the first of the given designs that holds one, or '' when none of them does
     *
     * @param int[] $designIds
     * @param string $path
     * @return string
     */
    public function getLayoutValue(array $designIds, string $path): string
    {
        if ($designIds === []) {
            return '';
        }

        // Cast before they reach the SQL: FIELD() takes its list inline
        $designIds = array_map('intval', $designIds);

        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), ['value'])
            ->where('design_id IN (?)', $designIds)
            ->where('path = ?', $path)
            ->order(new \Zend_Db_Expr('FIELD(design_id, ' . implode(',', $designIds) . ')'))
            ->limit(1);

        return (string) $connection->fetchOne($select);
    }

    /**
     * The designs that read this path from the given design
     *
     * @param int $designId
     * @param string $path
     * @return int[]
     */
    public function getInheritingDesignIds(int $designId, string $path): array
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from(['d' => $this->getTable('lynx_pagebuilder_design')], ['id'])
            ->joinLeft(
                ['l' => $this->getMainTable()],
                'l.design_id = d.id AND l.path = ' . $connection->quote($path),
                []
            )
            ->where('JSON_CONTAINS(d.parents, ?)', (string) $designId)
            ->where('l.id IS NULL');

        return array_map('intval', $connection->fetchCol($select));
    }

    /**
     * The id of the row a design holds for a path, or 0 when it holds none
     *
     * @param int $designId
     * @param string $path
     * @return int
     */
    public function getRowId(int $designId, string $path): int
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), ['id'])
            ->where('design_id = ?', $designId)
            ->where('path = ?', $path);

        return (int) $connection->fetchOne($select);
    }

    /**
     * Path => the first design of the chain holding a row for it, for every path any of them holds
     *
     * @param int[] $designIds the design first, then its parents in order
     * @return array<string, int>
     */
    public function getOwners(array $designIds): array
    {
        if ($designIds === []) {
            return [];
        }
        $designIds = array_map('intval', $designIds);
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), ['path', 'design_id'])
            ->where('design_id IN (?)', $designIds)
            ->order(new \Zend_Db_Expr('FIELD(design_id, ' . implode(',', $designIds) . ')'));

        $owners = [];
        foreach ($connection->fetchAll($select) as $row) {
            $owners[(string) $row['path']] ??= (int) $row['design_id'];
        }

        return $owners;
    }
}
