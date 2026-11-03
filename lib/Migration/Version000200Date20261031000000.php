<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Sébastien Marinier
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DelegateGroups\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Group admins of the app, designated by platform admins: they replace the
 * Nextcloud subadmins, which also get rights over the accounts of their groups
 * (password, email, deletion) that this app must not hand out.
 */
class Version000200Date20261031000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('delegate_groups_admins')) {
			$table = $schema->createTable('delegate_groups_admins');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$table->addColumn('group_id', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['group_id', 'user_id'], 'dg_admins_group_user');
			$table->addIndex(['user_id'], 'dg_admins_user');
		}
		return $schema;
	}
}
