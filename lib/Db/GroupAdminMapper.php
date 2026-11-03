<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Sébastien Marinier
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DelegateGroups\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<GroupAdmin>
 */
class GroupAdminMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'delegate_groups_admins', GroupAdmin::class);
	}

	/**
	 * @return string[]
	 */
	public function findUserIdsByGroup(string $groupId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('user_id')
			->from($this->getTableName())
			->where($qb->expr()->eq('group_id', $qb->createNamedParameter($groupId)));
		return $this->fetchColumn($qb->executeQuery(), 'user_id');
	}

	/**
	 * @return string[]
	 */
	public function findGroupIdsByUser(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('group_id')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
		return $this->fetchColumn($qb->executeQuery(), 'group_id');
	}

	/**
	 * Admins of every group that has some
	 *
	 * @return array<string, string[]> user ids by group id
	 */
	public function findAllGrouped(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('group_id', 'user_id')
			->from($this->getTableName());
		$result = $qb->executeQuery();
		$admins = [];
		while ($row = $result->fetch()) {
			$admins[(string)$row['group_id']][] = (string)$row['user_id'];
		}
		$result->closeCursor();
		return $admins;
	}

	public function exists(string $groupId, string $userId): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from($this->getTableName())
			->where($qb->expr()->eq('group_id', $qb->createNamedParameter($groupId)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->setMaxResults(1);
		$result = $qb->executeQuery();
		$found = $result->fetch() !== false;
		$result->closeCursor();
		return $found;
	}

	/**
	 * Replaces the admins of a group, in a transaction
	 *
	 * @param string[] $userIds
	 */
	public function replaceForGroup(string $groupId, array $userIds): void {
		$this->db->beginTransaction();
		try {
			$this->deleteByGroup($groupId);
			foreach ($userIds as $userId) {
				$admin = new GroupAdmin();
				$admin->setGroupId($groupId);
				$admin->setUserId($userId);
				$this->insert($admin);
			}
			$this->db->commit();
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
	}

	public function deleteByGroup(string $groupId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('group_id', $qb->createNamedParameter($groupId)));
		$qb->executeStatement();
	}

	public function deleteByUser(string $userId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
		$qb->executeStatement();
	}

	/**
	 * @return string[]
	 */
	private function fetchColumn(\OCP\DB\IResult $result, string $column): array {
		$values = [];
		while ($row = $result->fetch()) {
			$values[] = (string)$row[$column];
		}
		$result->closeCursor();
		return $values;
	}
}
