<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Sébastien Marinier
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DelegateGroups\Service;

use OCA\DelegateGroups\Db\JoinRequest;
use OCA\DelegateGroups\Db\JoinRequestMapper;
use OCA\DelegateGroups\Exception\MembershipException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;

/**
 * Group membership as seen by users and group admins.
 *
 * Group admins are the ones of this app (GroupAdminService), designated by
 * platform admins in the settings: not the Nextcloud subadmins.
 */
class MembershipService {
	private const SEARCH_LIMIT = 20;

	public function __construct(
		private IGroupManager $groupManager,
		private GroupAdminService $groupAdmins,
		private IUserManager $userManager,
		private JoinRequestMapper $mapper,
		private SettingsService $settings,
		private RequestNotifier $notifier,
		private ITimeFactory $timeFactory,
		private IL10N $l,
	) {
	}

	/**
	 * Groups the user belongs to or administers, and the user's pending requests
	 */
	public function getMyGroups(IUser $user): array {
		$groups = [];
		foreach ($this->groupManager->getUserGroups($user) as $group) {
			$groups[$group->getGID()] = $group;
		}
		foreach ($this->groupAdmins->getAdministeredGroups($user) as $group) {
			$groups[$group->getGID()] = $group;
		}

		$result = [];
		foreach ($groups as $group) {
			if ($this->settings->isIgnoredGroup($group->getGID())) {
				continue;
			}
			$result[] = $this->serializeGroup($group, $user);
		}
		usort($result, fn ($a, $b) => strcasecmp($a['displayName'], $b['displayName']));

		$requests = [];
		foreach ($this->mapper->findPendingByUser($user->getUID()) as $request) {
			$group = $this->groupManager->get($request->getGroupId());
			if ($group !== null) {
				$requests[] = [
					'id' => $request->getId(),
					'createdAt' => $request->getCreatedAt(),
					'group' => ['id' => $group->getGID(), 'displayName' => $group->getDisplayName()],
				];
			}
		}

		return ['groups' => $result, 'requests' => $requests];
	}

	/**
	 * Groups the user may ask to join
	 */
	public function getJoinableGroups(IUser $user): array {
		$pending = [];
		foreach ($this->mapper->findPendingByUser($user->getUID()) as $request) {
			$pending[$request->getGroupId()] = $request->getId();
		}

		$result = [];
		foreach ($this->settings->getOpenGroupIds() as $groupId) {
			$group = $this->groupManager->get($groupId);
			if ($group === null || !$this->isJoinable($user, $group)) {
				continue;
			}
			$result[] = [
				'id' => $group->getGID(),
				'displayName' => $group->getDisplayName(),
				'memberCount' => $this->countMembers($group),
				'requestId' => $pending[$groupId] ?? null,
			];
		}
		usort($result, fn ($a, $b) => strcasecmp($a['displayName'], $b['displayName']));
		return $result;
	}

	/**
	 * A group can be joined on request when platform admins opened it,
	 * when it has at least one admin to handle the request,
	 * and when the user is not already a member.
	 */
	private function isJoinable(IUser $user, IGroup $group): bool {
		return $this->settings->isOpenGroup($group->getGID())
			&& !$group->inGroup($user)
			&& $this->groupAdmins->getGroupAdmins($group) !== [];
	}

	public function requestJoin(IUser $user, string $groupId): JoinRequest {
		// Same answer for a missing group and a closed one: group ids cannot be probed
		$group = $this->groupManager->get($groupId);
		if ($group === null || !$this->settings->isOpenGroup($group->getGID())) {
			throw MembershipException::forbidden($this->l->t('This group does not accept membership requests.'));
		}
		if ($group->inGroup($user)) {
			throw MembershipException::conflict($this->l->t('You are already a member of this group.'));
		}
		if (!$this->isJoinable($user, $group)) {
			throw MembershipException::forbidden($this->l->t('This group does not accept membership requests.'));
		}
		$existing = $this->mapper->findPending($user->getUID(), $groupId);
		if ($existing !== null) {
			throw MembershipException::conflict($this->l->t('You already asked to join this group.'), ['requestId' => $existing->getId()]);
		}

		$request = new JoinRequest();
		$request->setUserId($user->getUID());
		$request->setGroupId($groupId);
		$request->setStatus(JoinRequest::STATUS_PENDING);
		$request->setCreatedAt($this->timeFactory->getTime());
		$request = $this->mapper->insert($request);

		$this->notifier->notifyNewRequest($request, $user, $group, $this->groupAdmins->getGroupAdmins($group));
		return $request;
	}

	public function cancelRequest(IUser $user, int $requestId): void {
		$request = $this->findRequest($requestId);
		if ($request->getUserId() !== $user->getUID()) {
			throw MembershipException::notFound($this->l->t('Request not found.'));
		}
		if (!$this->close($request, JoinRequest::STATUS_CANCELLED, $user->getUID())) {
			throw MembershipException::conflict($this->l->t('This request has already been processed.'));
		}
		$this->notifier->clearRequest($request);
	}

	/**
	 * A request, as seen by an admin of its group
	 */
	public function getRequest(IUser $admin, int $requestId): array {
		[$request, $group] = $this->findAdministeredRequest($admin, $requestId);
		return $this->serializeRequest($request, $group);
	}

	public function decide(IUser $admin, int $requestId, bool $accept): array {
		[$request, $group] = $this->findAdministeredRequest($admin, $requestId);
		if (!$request->isPending()) {
			throw MembershipException::conflict($this->l->t('This request has already been processed.'), $this->serializeRequest($request, $group));
		}
		$requester = $this->userManager->get($request->getUserId());
		if ($requester === null) {
			throw MembershipException::notFound($this->l->t('The account that made this request no longer exists.'));
		}
		if ($accept && !$requester->isEnabled()) {
			throw MembershipException::forbidden($this->l->t('The account that made this request is disabled.'));
		}

		// Close the request before adding the user, so that the UserAddedEvent listener has nothing left to do.
		// Only one decision wins: a cancellation or another admin may have closed it meanwhile.
		if (!$this->close($request, $accept ? JoinRequest::STATUS_ACCEPTED : JoinRequest::STATUS_REFUSED, $admin->getUID())) {
			$request = $this->findRequest($requestId);
			throw MembershipException::conflict($this->l->t('This request has already been processed.'), $this->serializeRequest($request, $group));
		}
		if ($accept && !$group->inGroup($requester)) {
			$group->addUser($requester);
		}
		$this->notifier->notifyDecision($request);
		return $this->serializeRequest($request, $group);
	}

	/**
	 * Members and pending requests of a group, for one of its admins
	 */
	public function getMembers(IUser $admin, string $groupId): array {
		$group = $this->getAdministeredGroup($admin, $groupId);
		$adminIds = $this->getAdminIds($group);

		$members = [];
		foreach ($group->getUsers() as $user) {
			$members[] = [
				'id' => $user->getUID(),
				'displayName' => $user->getDisplayName(),
				'isAdmin' => isset($adminIds[$user->getUID()]),
				'isSelf' => $user->getUID() === $admin->getUID(),
			];
		}
		usort($members, fn ($a, $b) => strcasecmp($a['displayName'], $b['displayName']));

		$requests = [];
		foreach ($this->mapper->findPendingByGroup($groupId) as $request) {
			$requests[] = $this->serializeRequest($request, $group);
		}

		return [
			'group' => $this->serializeGroup($group, $admin),
			'members' => $members,
			'requests' => $requests,
		];
	}

	public function addMember(IUser $admin, string $groupId, string $userId): void {
		$group = $this->getAdministeredGroup($admin, $groupId);
		$user = $this->userManager->get($userId);
		if ($user === null || !$user->isEnabled()) {
			throw MembershipException::notFound($this->l->t('Account not found.'));
		}
		if ($group->inGroup($user)) {
			throw MembershipException::conflict($this->l->t('This account is already a member of the group.'));
		}
		$request = $this->mapper->findPending($userId, $groupId);
		if ($request !== null && $this->close($request, JoinRequest::STATUS_ACCEPTED, $admin->getUID())) {
			$this->notifier->notifyDecision($request);
		}
		$group->addUser($user);
	}

	public function removeMember(IUser $admin, string $groupId, string $userId): void {
		$group = $this->getAdministeredGroup($admin, $groupId);
		if ($userId === $admin->getUID()) {
			throw MembershipException::forbidden($this->l->t('You cannot remove yourself from the group.'));
		}
		$user = $this->userManager->get($userId);
		if ($user === null || !$group->inGroup($user)) {
			throw MembershipException::notFound($this->l->t('This account is not a member of the group.'));
		}
		if ($this->groupAdmins->isGroupAdmin($user, $group)) {
			throw MembershipException::forbidden($this->l->t('Group admins are managed by the platform administrators.'));
		}
		$group->removeUser($user);
	}

	/**
	 * Search among all the accounts of the instance, for an admin adding members
	 */
	public function searchUsers(IUser $admin, string $groupId, string $search): array {
		$group = $this->getAdministeredGroup($admin, $groupId);
		$search = trim($search);
		if (mb_strlen($search) < 2) {
			return [];
		}

		$users = [];
		foreach ($this->userManager->search($search, self::SEARCH_LIMIT) as $user) {
			$users[$user->getUID()] = $user;
		}
		foreach ($this->userManager->searchDisplayName($search, self::SEARCH_LIMIT) as $user) {
			$users[$user->getUID()] = $user;
		}

		$result = [];
		foreach ($users as $user) {
			if (!$user->isEnabled()) {
				continue;
			}
			$result[] = [
				'id' => $user->getUID(),
				'displayName' => $user->getDisplayName(),
				'isMember' => $group->inGroup($user),
			];
		}
		usort($result, fn ($a, $b) => strcasecmp($a['displayName'], $b['displayName']));
		return array_slice($result, 0, self::SEARCH_LIMIT);
	}

	/**
	 * A user joined a group by any means: a pending request for it is now pointless
	 */
	public function onUserAdded(IUser $user, IGroup $group, ?IUser $by): void {
		$request = $this->mapper->findPending($user->getUID(), $group->getGID());
		if ($request !== null && $this->close($request, JoinRequest::STATUS_ACCEPTED, $by?->getUID())) {
			$this->notifier->notifyDecision($request);
		}
	}

	/**
	 * @return bool false when the request was no longer pending (processed concurrently)
	 */
	private function close(JoinRequest $request, int $status, ?string $by): bool {
		$request->setStatus($status);
		$request->setDecidedAt($this->timeFactory->getTime());
		$request->setDecidedBy($by);
		return $this->mapper->closeIfPending($request);
	}

	private function findRequest(int $requestId): JoinRequest {
		try {
			return $this->mapper->findById($requestId);
		} catch (DoesNotExistException) {
			throw MembershipException::notFound($this->l->t('Request not found.'));
		}
	}

	/**
	 * Same answer for a missing group and a group administered by someone else:
	 * group ids cannot be probed
	 */
	private function getAdministeredGroup(IUser $admin, string $groupId): IGroup {
		$group = $this->groupManager->get($groupId);
		if ($group === null || !$this->groupAdmins->isGroupAdmin($admin, $group)) {
			throw MembershipException::forbidden($this->l->t('You are not an administrator of this group.'));
		}
		return $group;
	}

	/**
	 * Same answer for a missing request and a request of a group administered by someone else:
	 * request ids cannot be probed
	 *
	 * @return array{0: JoinRequest, 1: IGroup}
	 */
	private function findAdministeredRequest(IUser $admin, int $requestId): array {
		$request = $this->findRequest($requestId);
		$group = $this->groupManager->get($request->getGroupId());
		if ($group === null || !$this->groupAdmins->isGroupAdmin($admin, $group)) {
			throw MembershipException::notFound($this->l->t('Request not found.'));
		}
		return [$request, $group];
	}

	/**
	 * @return array<string, true>
	 */
	private function getAdminIds(IGroup $group): array {
		$ids = [];
		foreach ($this->groupAdmins->getGroupAdmins($group) as $user) {
			$ids[$user->getUID()] = true;
		}
		return $ids;
	}

	private function countMembers(IGroup $group): int {
		$count = $group->count();
		return $count === false ? 0 : (int)$count;
	}

	private function serializeGroup(IGroup $group, IUser $user): array {
		$isAdmin = $this->groupAdmins->isGroupAdmin($user, $group);
		return [
			'id' => $group->getGID(),
			'displayName' => $group->getDisplayName(),
			'memberCount' => $this->countMembers($group),
			'isMember' => $group->inGroup($user),
			'isAdmin' => $isAdmin,
			'pendingCount' => $isAdmin ? count($this->mapper->findPendingByGroup($group->getGID())) : 0,
		];
	}

	private function serializeRequest(JoinRequest $request, IGroup $group): array {
		$requester = $this->userManager->get($request->getUserId());
		$decidedBy = $request->getDecidedBy() !== null ? $this->userManager->get($request->getDecidedBy()) : null;
		return [
			'id' => $request->getId(),
			'status' => $request->getStatusName(),
			'createdAt' => $request->getCreatedAt(),
			'decidedAt' => $request->getDecidedAt(),
			'decidedBy' => $decidedBy === null ? null : ['id' => $decidedBy->getUID(), 'displayName' => $decidedBy->getDisplayName()],
			'user' => ['id' => $request->getUserId(), 'displayName' => $requester?->getDisplayName() ?? $request->getUserId()],
			'group' => ['id' => $group->getGID(), 'displayName' => $group->getDisplayName()],
		];
	}
}
