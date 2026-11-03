<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Sébastien Marinier
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DelegateGroups\Service;

use OCA\DelegateGroups\Db\GroupAdminMapper;
use OCA\DelegateGroups\Exception\MembershipException;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;

/**
 * Group admins of this app, designated by platform admins in the app settings.
 *
 * They are deliberately not the Nextcloud subadmins: a subadmin also controls
 * the accounts of their groups (password, email, disabling, deletion), so letting
 * them add any account to their group would let them take over that account.
 * A group admin of this app only manages the membership of their groups.
 */
class GroupAdminService {
	public function __construct(
		private GroupAdminMapper $mapper,
		private IGroupManager $groupManager,
		private IUserManager $userManager,
		private IL10N $l,
		private SettingsService $settings,
	) {
	}

	/**
	 * Groups that can never be delegated: their admins would gain platform rights
	 */
	public function isProtectedGroup(string $groupId): bool {
		return in_array($groupId, $this->settings->getProtectedGroups(), true);
	}

	public function isGroupAdmin(IUser $user, IGroup $group): bool {
		return $user->isEnabled()
			&& !$this->isProtectedGroup($group->getGID())
			&& $this->mapper->exists($group->getGID(), $user->getUID());
	}

	/**
	 * @return IGroup[]
	 */
	public function getAdministeredGroups(IUser $user): array {
		if (!$user->isEnabled()) {
			return [];
		}
		$groups = [];
		foreach ($this->mapper->findGroupIdsByUser($user->getUID()) as $groupId) {
			$group = $this->isProtectedGroup($groupId) ? null : $this->groupManager->get($groupId);
			if ($group !== null) {
				$groups[] = $group;
			}
		}
		return $groups;
	}

	/**
	 * Admins of a group who can act: existing and enabled accounts
	 *
	 * @return IUser[]
	 */
	public function getGroupAdmins(IGroup $group): array {
		if ($this->isProtectedGroup($group->getGID())) {
			return [];
		}
		return $this->toUsers($this->mapper->findUserIdsByGroup($group->getGID()), true);
	}

	/**
	 * Admins of every group, for the settings page (disabled accounts included, so they can be removed)
	 *
	 * @return array<string, IUser[]> by group id
	 */
	public function getAllGroupAdmins(): array {
		$result = [];
		foreach ($this->mapper->findAllGrouped() as $groupId => $userIds) {
			$result[$groupId] = $this->toUsers($userIds, false);
		}
		return $result;
	}

	/**
	 * Platform admins only (checked by the controller)
	 *
	 * @param string[] $userIds
	 * @return IUser[] the admins actually saved
	 */
	public function setGroupAdmins(string $groupId, array $userIds): array {
		$group = $this->groupManager->get($groupId);
		if ($group === null) {
			throw MembershipException::notFound($this->l->t('Group not found.'));
		}
		if ($this->isProtectedGroup($groupId)) {
			throw MembershipException::forbidden($this->l->t('The “admin” group cannot have group admins.'));
		}
		$users = [];
		foreach ($userIds as $userId) {
			$user = is_string($userId) ? $this->userManager->get($userId) : null;
			if ($user === null) {
				throw MembershipException::notFound($this->l->t('Account not found.'));
			}
			$users[$user->getUID()] = $user;
		}
		$this->mapper->replaceForGroup($groupId, array_keys($users));
		return array_values($users);
	}

	/**
	 * Search among all the enabled accounts, for platform admins designating group admins
	 *
	 * @return list<array{id: string, displayName: string, enabled: bool}>
	 */
	public function searchAccounts(string $search, int $limit = 20): array {
		$search = trim($search);
		if (mb_strlen($search) < 2) {
			return [];
		}
		$users = [];
		foreach ($this->userManager->search($search, $limit) as $user) {
			$users[$user->getUID()] = $user;
		}
		foreach ($this->userManager->searchDisplayName($search, $limit) as $user) {
			$users[$user->getUID()] = $user;
		}
		$result = [];
		foreach ($users as $user) {
			if ($user->isEnabled()) {
				$result[] = self::serializeUser($user);
			}
		}
		usort($result, fn ($a, $b) => strcasecmp($a['displayName'], $b['displayName']));
		return array_slice($result, 0, $limit);
	}

	/**
	 * @return array{id: string, displayName: string, enabled: bool}
	 */
	public static function serializeUser(IUser $user): array {
		return ['id' => $user->getUID(), 'displayName' => $user->getDisplayName(), 'enabled' => $user->isEnabled()];
	}

	public function onUserDeleted(string $userId): void {
		$this->mapper->deleteByUser($userId);
	}

	public function onGroupDeleted(string $groupId): void {
		$this->mapper->deleteByGroup($groupId);
	}

	/**
	 * @param string[] $userIds
	 * @return IUser[]
	 */
	private function toUsers(array $userIds, bool $enabledOnly): array {
		$users = [];
		foreach ($userIds as $userId) {
			$user = $this->userManager->get($userId);
			if ($user !== null && (!$enabledOnly || $user->isEnabled())) {
				$users[] = $user;
			}
		}
		return $users;
	}
}
