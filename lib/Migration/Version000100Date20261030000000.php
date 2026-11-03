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

class Version000100Date20261030000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('delegate_groups_requests')) {
			$table = $schema->createTable('delegate_groups_requests');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$table->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('group_id', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('status', Types::SMALLINT, ['notnull' => true, 'default' => 0]);
			$table->addColumn('created_at', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
			$table->addColumn('decided_at', Types::BIGINT, ['notnull' => false, 'unsigned' => true]);
			$table->addColumn('decided_by', Types::STRING, ['notnull' => false, 'length' => 64]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['group_id', 'status'], 'dg_req_group_status');
			$table->addIndex(['user_id', 'status'], 'dg_req_user_status');
		}
		return $schema;
	}
}
