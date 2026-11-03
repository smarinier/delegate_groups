<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Sébastien Marinier
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DelegateGroups\Controller;

use OCA\DelegateGroups\AppInfo\Application;
use OCA\DelegateGroups\Exception\MembershipException;
use OCA\DelegateGroups\Service\GroupAdminService;
use OCA\DelegateGroups\Service\MembershipService;
use OCA\DelegateGroups\Service\SettingsService;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\Attribute\PasswordConfirmationRequired;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;

#[OpenAPI(scope: OpenAPI::SCOPE_IGNORE)]
class ApiController extends OCSController {
	public function __construct(
		IRequest $request,
		private IUserSession $userSession,
		private MembershipService $membership,
		private SettingsService $settings,
		private GroupAdminService $groupAdmins,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * Groups of the current user, and their pending requests
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/groups')]
	public function groups(): DataResponse {
		return $this->run(fn (IUser $user) => $this->membership->getMyGroups($user));
	}

	/**
	 * Groups the current user may ask to join
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/joinable')]
	public function joinable(): DataResponse {
		return $this->run(fn (IUser $user) => $this->membership->getJoinableGroups($user));
	}

	/**
	 * Each request emails every admin of the group: limited against flooding
	 */
	#[NoAdminRequired]
	#[UserRateLimit(limit: 5, period: 3600)]
	#[ApiRoute(verb: 'POST', url: '/api/v1/requests')]
	public function createRequest(string $groupId): DataResponse {
		return $this->run(fn (IUser $user) => ['id' => $this->membership->requestJoin($user, $groupId)->getId()]);
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/requests/{id}', requirements: ['id' => '\d+'])]
	public function getRequest(int $id): DataResponse {
		return $this->run(fn (IUser $user) => $this->membership->getRequest($user, $id));
	}

	/**
	 * Cancellation by the requester
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/v1/requests/{id}', requirements: ['id' => '\d+'])]
	public function cancelRequest(int $id): DataResponse {
		return $this->run(function (IUser $user) use ($id) {
			$this->membership->cancelRequest($user, $id);
			return [];
		});
	}

	#[NoAdminRequired]
	#[PasswordConfirmationRequired]
	#[ApiRoute(verb: 'POST', url: '/api/v1/requests/{id}/accept', requirements: ['id' => '\d+'])]
	public function acceptRequest(int $id): DataResponse {
		return $this->run(fn (IUser $user) => $this->membership->decide($user, $id, true));
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/v1/requests/{id}/refuse', requirements: ['id' => '\d+'])]
	public function refuseRequest(int $id): DataResponse {
		return $this->run(fn (IUser $user) => $this->membership->decide($user, $id, false));
	}

	/**
	 * Members of a group administered by the current user
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/members')]
	public function members(string $groupId): DataResponse {
		return $this->run(fn (IUser $user) => $this->membership->getMembers($user, $groupId));
	}

	#[NoAdminRequired]
	#[PasswordConfirmationRequired]
	#[ApiRoute(verb: 'POST', url: '/api/v1/members')]
	public function addMember(string $groupId, string $userId): DataResponse {
		return $this->run(function (IUser $user) use ($groupId, $userId) {
			$this->membership->addMember($user, $groupId, $userId);
			return [];
		});
	}

	#[NoAdminRequired]
	#[PasswordConfirmationRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/v1/members')]
	public function removeMember(string $groupId, string $userId): DataResponse {
		return $this->run(function (IUser $user) use ($groupId, $userId) {
			$this->membership->removeMember($user, $groupId, $userId);
			return [];
		});
	}

	/**
	 * Search accounts to add to a group administered by the current user
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/users')]
	public function searchUsers(string $groupId, string $search = ''): DataResponse {
		return $this->run(fn (IUser $user) => $this->membership->searchUsers($user, $groupId, $search));
	}

	/**
	 * Platform admins only: groups opened to membership requests
	 *
	 * @param string[] $openGroups
	 */
	#[PasswordConfirmationRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/v1/settings')]
	public function saveSettings(array $openGroups = []): DataResponse {
		return new DataResponse(['openGroups' => $this->settings->setOpenGroupIds($openGroups)]);
	}

	/**
	 * Platform admins only: search accounts to designate as group admins
	 */
	#[ApiRoute(verb: 'GET', url: '/api/v1/admins/users')]
	public function searchAdminCandidates(string $search = ''): DataResponse {
		return $this->run(fn () => $this->groupAdmins->searchAccounts($search));
	}

	/**
	 * Platform admins only: designate the group admins of a group (replaces the list)
	 *
	 * @param string[] $userIds
	 */
	#[PasswordConfirmationRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/v1/admins')]
	public function saveGroupAdmins(string $groupId, array $userIds = []): DataResponse {
		return $this->run(fn () => [
			'admins' => array_map(GroupAdminService::serializeUser(...), $this->groupAdmins->setGroupAdmins($groupId, $userIds)),
		]);
	}

	private function run(callable $action): DataResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new DataResponse([], 401);
		}
		try {
			return new DataResponse($action($user));
		} catch (MembershipException $e) {
			return new DataResponse(['message' => $e->getMessage()] + $e->getData(), $e->getStatus());
		}
	}
}
