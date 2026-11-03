<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Sébastien Marinier
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DelegateGroups\Notification;

use OCA\DelegateGroups\AppInfo\Application;
use OCA\DelegateGroups\Db\JoinRequestMapper;
use OCA\DelegateGroups\Service\GroupAdminService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IGroupManager;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use OCP\Notification\UnknownNotificationException;

class Notifier implements INotifier {
	public function __construct(
		private IFactory $l10nFactory,
		private IURLGenerator $urlGenerator,
		private IUserManager $userManager,
		private IGroupManager $groupManager,
		private JoinRequestMapper $mapper,
		private GroupAdminService $groupAdmins,
	) {
	}

	public function getID(): string {
		return Application::APP_ID;
	}

	public function getName(): string {
		return $this->l10nFactory->get(Application::APP_ID)->t('My Groups');
	}

	public function prepare(INotification $notification, string $languageCode): INotification {
		if ($notification->getApp() !== Application::APP_ID) {
			throw new UnknownNotificationException();
		}
		$l = $this->l10nFactory->get(Application::APP_ID, $languageCode);
		$params = $notification->getSubjectParameters();

		$group = $this->groupManager->get((string)($params['group'] ?? ''));
		if ($group === null) {
			throw new UnknownNotificationException('Group deleted');
		}
		$groupParam = ['type' => 'user-group', 'id' => $group->getGID(), 'name' => $group->getDisplayName()];
		$notification->setIcon($this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath(Application::APP_ID, 'app-dark.svg')));

		switch ($notification->getSubject()) {
			case 'join_request':
				// Only pending requests are worth a notification
				try {
					$request = $this->mapper->findById((int)$notification->getObjectId());
				} catch (DoesNotExistException) {
					throw new UnknownNotificationException('Request deleted');
				}
				if (!$request->isPending()) {
					throw new UnknownNotificationException('Request processed');
				}
				// The recipient may have been removed from the group admins since
				$recipient = $this->userManager->get($notification->getUser());
				if ($recipient === null || !$this->groupAdmins->isGroupAdmin($recipient, $group)) {
					throw new UnknownNotificationException('No longer a group admin');
				}
				$requester = $this->userManager->get((string)$params['requester']);
				$userParam = ['type' => 'user', 'id' => (string)$params['requester'], 'name' => $requester?->getDisplayName() ?? (string)$params['requester']];
				$this->setSubject($notification, $l->t('{user} asked to join the group {group}'), ['user' => $userParam, 'group' => $groupParam]);
				$notification->setLink($this->urlGenerator->linkToRouteAbsolute('delegate_groups.page.request', ['id' => $notification->getObjectId()]));
				break;

			case 'request_accepted':
				$this->setSubject($notification, $l->t('Your request to join the group {group} was accepted'), ['group' => $groupParam]);
				$notification->setLink($this->urlGenerator->linkToRouteAbsolute('delegate_groups.page.index'));
				break;

			case 'request_refused':
				$this->setSubject($notification, $l->t('Your request to join the group {group} was refused'), ['group' => $groupParam]);
				$notification->setLink($this->urlGenerator->linkToRouteAbsolute('delegate_groups.page.index'));
				break;

			default:
				throw new UnknownNotificationException();
		}
		return $notification;
	}

	private function setSubject(INotification $notification, string $subject, array $parameters): void {
		$placeholders = $replacements = [];
		foreach ($parameters as $key => $parameter) {
			$placeholders[] = '{' . $key . '}';
			$replacements[] = $parameter['name'];
		}
		$notification->setParsedSubject(str_replace($placeholders, $replacements, $subject))
			->setRichSubject($subject, $parameters);
	}
}
