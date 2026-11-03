<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Sébastien Marinier
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DelegateGroups\Settings;

use OCA\DelegateGroups\AppInfo\Application;
use OCA\DelegateGroups\Service\GroupAdminService;
use OCA\DelegateGroups\Service\SettingsService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IGroupManager;
use OCP\Settings\ISettings;
use OCP\Util;

class Admin implements ISettings {
	public function __construct(
		private SettingsService $settings,
		private IInitialState $initialState,
		private IGroupManager $groupManager,
		private GroupAdminService $groupAdmins,
	) {
	}

	public function getForm(): TemplateResponse {
		$this->initialState->provideInitialState('options', $this->settings->getOptions());
		$this->initialState->provideInitialState('openGroups', $this->settings->getOpenGroupIds());
		$this->initialState->provideInitialState('groups', $this->listGroups());
		Util::addScript(Application::APP_ID, Application::APP_ID . '-admin');
		Util::addStyle(Application::APP_ID, Application::APP_ID . '-admin');
		return new TemplateResponse(Application::APP_ID, 'admin');
	}

	/**
	 * All the groups but the protected ones, sorted by name, with their group admins:
	 * platform admins designate the group admins and open the groups from the same list
	 *
	 * @return list<array{id: string, displayName: string, memberCount: int, admins: list<array{id: string, displayName: string, enabled: bool}>}>
	 */
	private function listGroups(): array {
		$admins = $this->groupAdmins->getAllGroupAdmins();
		$groups = [];
		foreach ($this->groupManager->search('') as $group) {
			if ($this->groupAdmins->isProtectedGroup($group->getGID())) {
				continue;
			}
			$count = $group->count();
			$groups[] = [
				'id' => $group->getGID(),
				'displayName' => $group->getDisplayName(),
				'memberCount' => $count === false ? 0 : (int)$count,
				'admins' => array_map(GroupAdminService::serializeUser(...), $admins[$group->getGID()] ?? []),
			];
		}
		usort($groups, fn ($a, $b) => strnatcasecmp($a['displayName'], $b['displayName']));
		return $groups;
	}

	public function getSection(): string {
		return Application::APP_ID;
	}

	public function getPriority(): int {
		return 10;
	}
}
