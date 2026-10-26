<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Magento\Framework\Stdlib\DateTime\DateTime;
use OOCS\LynxPageBuilder\Model\Share as ShareModel;

/**
 * The share links table, and what is asked of it: which row a URL token names, whether a row still lets anyone in
 */
class Share extends AbstractDb
{
    /**
     * Resource context, clock
     *
     * @param Context $context
     * @param DateTime $dateTime
     * @param string|null $connectionName
     */
    public function __construct(
        Context $context,
        private readonly DateTime $dateTime,
        ?string $connectionName = null
    ) {
        parent::__construct($context, $connectionName);
    }

    /**
     * Construct
     */
    protected function _construct()
    {
        $this->_init('lynx_pagebuilder_share', 'id');
    }

    /**
     * The row a URL token names, alive or not
     *
     * @param string $token
     * @return array<string, mixed>|null
     */
    public function findByToken(string $token): ?array
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable())
            ->where('token_hash = ?', hash('sha256', $token))
            ->limit(1);

        $row = $connection->fetchRow($select);

        return $row ?: null;
    }

    /**
     * Is Alive
     *
     * @param int $id
     * @return bool
     */
    public function isAlive(int $id): bool
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), ['id'])
            ->where('id = ?', $id)
            ->where('expires_at > ?', $this->now());

        return (bool) $connection->fetchOne($select);
    }

    /**
     * Get Alive By Design
     *
     * @param int $designId
     * @return array<int, array<string, mixed>> newest first
     */
    public function getAliveByDesign(int $designId): array
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), ['id', 'expires_at', 'created_at'])
            ->where('design_id = ?', $designId)
            ->where('expires_at > ?', $this->now())
            ->order('id DESC');

        return $connection->fetchAll($select);
    }

    /**
     * One more wrong password: the count goes up, and at MAX_ATTEMPTS the link is locked for LOCK_SECONDS and the
     *
     * @param int $id
     * @param int $attempts
     * @return void
     */
    public function recordFailure(int $id, int $attempts): void
    {
        $locked = $attempts + 1 >= ShareModel::MAX_ATTEMPTS;

        $this->getConnection()->update(
            $this->getMainTable(),
            [
                'attempts'     => $locked ? 0 : $attempts + 1,
                'locked_until' => $locked
                    ? $this->dateTime->gmtDate(null, $this->dateTime->gmtTimestamp() + ShareModel::LOCK_SECONDS)
                    : null,
            ],
            ['id = ?' => $id]
        );
    }

    /**
     * Clear Failures
     *
     * @param int $id
     * @return void
     */
    public function clearFailures(int $id): void
    {
        $this->getConnection()->update(
            $this->getMainTable(),
            ['attempts' => 0, 'locked_until' => null],
            ['id = ?' => $id]
        );
    }

    /**
     * Drop the links whose time is up: nobody can open them, and revoking already deletes
     *
     * @return int Rows deleted
     */
    public function deleteExpired(): int
    {
        return $this->getConnection()->delete($this->getMainTable(), ['expires_at <= ?' => $this->now()]);
    }

    /**
     * Now in UTC, the time expires_at is written in
     *
     * @return string
     */
    private function now(): string
    {
        return $this->dateTime->gmtDate();
    }
}
