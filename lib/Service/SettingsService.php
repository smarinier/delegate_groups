<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Sébastien Marinier
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DelegateGroups\Service;

use OCA\DelegateGroups\AppInfo\Application;
use OCP\IAppConfig;
use OCP\IGroupManager;

class SettingsService {
	/** Groups that can never be opened to requests */
	private array $PROTECTED_GROUPS = [];

	private const KEY_OPEN_GROUPS = 'open_groups';
	private const KEY_IGNORED_GROUPS = 'ignored_groups';

	public function __construct(
		private IAppConfig $appConfig,
		private IGroupManager $groupManager,
	) {
	}

	/**
	 * Groups opened to membership requests by platform admins
	 *
	 * @return string[]
	 */
	public function getOpenGroupIds(): array {
		$this->loadProtectedGroups();
		$ids = $this->appConfig->getValueArray(Application::APP_ID, self::KEY_OPEN_GROUPS);
		return array_values(array_filter($ids, fn ($id) => is_string($id) && !in_array($id, $this->PROTECTED_GROUPS, true)));
	}

	public function isOpenGroup(string $groupId): bool {
		return in_array($groupId, $this->getOpenGroupIds(), true);
	}

	/**
	 * @param string[] $groupIds
	 * @return string[] the ids actually saved
	 */
	public function setOpenGroupIds(array $groupIds): array {
		$ids = [];
		foreach ($groupIds as $id) {
			if (is_string($id) && !in_array($id, $this->PROTECTED_GROUPS, true) && $this->groupManager->groupExists($id)) {
				$ids[$id] = $id;
			}
		}
		$ids = array_values($ids);
		$this->appConfig->setValueArray(Application::APP_ID, self::KEY_OPEN_GROUPS, $ids);
		return $ids;
	}

	public function removeOpenGroup(string $groupId): void {
		$ids = $this->getOpenGroupIds();
		if (in_array($groupId, $ids, true)) {
			$this->appConfig->setValueArray(Application::APP_ID, self::KEY_OPEN_GROUPS, array_values(array_diff($ids, [$groupId])));
		}
	}

	private function loadProtectedGroups(): void {
		if (empty($this->PROTECTED_GROUPS)) {
			$this->PROTECTED_GROUPS = ['admin'];
			$ignoredGroups = explode(',', $this->appConfig->getValueString(Application::APP_ID, self::KEY_IGNORED_GROUPS));
			foreach ($ignoredGroups as $groupId) {
				if (is_string($groupId)) {
					$this->PROTECTED_GROUPS[] = $groupId;
				}
			}
		}
	}

	public function isIgnoredGroup(string $groupId): bool {
		$ignoredGroups = explode(',', $this->appConfig->getValueString(Application::APP_ID, self::KEY_IGNORED_GROUPS));
		return in_array($groupId, $ignoredGroups, true);
	}

	public function getProtectedGroups(): array {
		$this->loadProtectedGroups();
		return $this->PROTECTED_GROUPS;
	}
}
