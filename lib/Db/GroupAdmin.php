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
 * A user designated by platform admins to administer a group in this app
 *
 * @method string getGroupId()
 * @method void setGroupId(string $groupId)
 * @method string getUserId()
 * @method void setUserId(string $userId)
 */
class GroupAdmin extends Entity {
	protected $groupId;
	protected $userId;

	public function __construct() {
		$this->addType('groupId', Types::STRING);
		$this->addType('userId', Types::STRING);
	}
}
