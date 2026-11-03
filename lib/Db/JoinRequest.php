<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Sébastien Marinier
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DelegateGroups\Db;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * A membership request of a user for a group
 *
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getGroupId()
 * @method void setGroupId(string $groupId)
 * @method int getStatus()
 * @method void setStatus(int $status)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method int|null getDecidedAt()
 * @method void setDecidedAt(?int $decidedAt)
 * @method string|null getDecidedBy()
 * @method void setDecidedBy(?string $decidedBy)
 */
class JoinRequest extends Entity {
	public const STATUS_PENDING = 0;
	public const STATUS_ACCEPTED = 1;
	public const STATUS_REFUSED = 2;
	public const STATUS_CANCELLED = 3;

	protected $userId;
	protected $groupId;
	protected $status;
	protected $createdAt;
	protected $decidedAt;
	protected $decidedBy;

	public function __construct() {
		$this->addType('userId', Types::STRING);
		$this->addType('groupId', Types::STRING);
		$this->addType('status', Types::SMALLINT);
		$this->addType('createdAt', Types::BIGINT);
		$this->addType('decidedAt', Types::BIGINT);
		$this->addType('decidedBy', Types::STRING);
	}

	public function isPending(): bool {
		return $this->getStatus() === self::STATUS_PENDING;
	}

	public function getStatusName(): string {
		return match ($this->getStatus()) {
			self::STATUS_PENDING => 'pending',
			self::STATUS_ACCEPTED => 'accepted',
			self::STATUS_REFUSED => 'refused',
			default => 'cancelled',
		};
	}
}
