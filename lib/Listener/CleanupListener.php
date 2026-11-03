<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Sébastien Marinier
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DelegateGroups\Listener;

use OCA\DelegateGroups\Db\JoinRequestMapper;
use OCA\DelegateGroups\Service\GroupAdminService;
use OCA\DelegateGroups\Service\MembershipService;
use OCA\DelegateGroups\Service\SettingsService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Group\Events\GroupDeletedEvent;
use OCP\Group\Events\UserAddedEvent;
use OCP\IUserSession;
use OCP\User\Events\UserDeletedEvent;

/**
 * Keeps requests consistent with changes made outside of the app
 *
 * @template-implements IEventListener<Event>
 */
class CleanupListener implements IEventListener {
	public function __construct(
		private MembershipService $membershipService,
		private SettingsService $settings,
		private JoinRequestMapper $mapper,
		private GroupAdminService $groupAdmins,
		private IUserSession $userSession,
	) {
	}

	public function handle(Event $event): void {
		if ($event instanceof UserAddedEvent) {
			$this->membershipService->onUserAdded($event->getUser(), $event->getGroup(), $this->userSession->getUser());
		} elseif ($event instanceof UserDeletedEvent) {
			$this->mapper->deleteByUser($event->getUid());
			$this->groupAdmins->onUserDeleted($event->getUid());
		} elseif ($event instanceof GroupDeletedEvent) {
			$this->mapper->deleteByGroup($event->getGroup()->getGID());
			$this->groupAdmins->onGroupDeleted($event->getGroup()->getGID());
			$this->settings->removeOpenGroup($event->getGroup()->getGID());
		}
	}
}
