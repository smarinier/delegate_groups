<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Sébastien Marinier
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DelegateGroups\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<JoinRequest>
 */
class JoinRequestMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'delegate_groups_requests', JoinRequest::class);
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function findById(int $id): JoinRequest {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/**
	 * @return JoinRequest[]
	 */
	public function findPendingByGroup(string $groupId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('group_id', $qb->createNamedParameter($groupId)))
			->andWhere($qb->expr()->eq('status', $qb->createNamedParameter(JoinRequest::STATUS_PENDING, IQueryBuilder::PARAM_INT)))
			->orderBy('created_at', 'ASC');
		return $this->findEntities($qb);
	}

	/**
	 * @return JoinRequest[]
	 */
	public function findPendingByUser(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('status', $qb->createNamedParameter(JoinRequest::STATUS_PENDING, IQueryBuilder::PARAM_INT)))
			->orderBy('created_at', 'ASC');
		return $this->findEntities($qb);
	}

	public function findPending(string $userId, string $groupId): ?JoinRequest {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('group_id', $qb->createNamedParameter($groupId)))
			->andWhere($qb->expr()->eq('status', $qb->createNamedParameter(JoinRequest::STATUS_PENDING, IQueryBuilder::PARAM_INT)))
			->setMaxResults(1);
		$requests = $this->findEntities($qb);
		return $requests[0] ?? null;
	}

	/**
	 * Closes a request only if it is still pending, in a single statement,
	 * so that two concurrent decisions (accept / refuse / cancel) cannot both win.
	 *
	 * @return bool false when the request was already processed meanwhile
	 */
	public function closeIfPending(JoinRequest $request): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('status', $qb->createNamedParameter($request->getStatus(), IQueryBuilder::PARAM_INT))
			->set('decided_at', $qb->createNamedParameter($request->getDecidedAt(), IQueryBuilder::PARAM_INT))
			->set('decided_by', $qb->createNamedParameter($request->getDecidedBy()))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($request->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('status', $qb->createNamedParameter(JoinRequest::STATUS_PENDING, IQueryBuilder::PARAM_INT)));
		$closed = $qb->executeStatement() === 1;
		if ($closed) {
			$request->resetUpdatedFields();
		}
		return $closed;
	}

	public function deleteByUser(string $userId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
		$qb->executeStatement();
	}

	public function deleteByGroup(string $groupId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('group_id', $qb->createNamedParameter($groupId)));
		$qb->executeStatement();
	}
}
