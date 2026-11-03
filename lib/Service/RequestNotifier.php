<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Sébastien Marinier
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DelegateGroups\Service;

use OCA\DelegateGroups\AppInfo\Application;
use OCA\DelegateGroups\Db\JoinRequest;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IGroup;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\L10N\IFactory;
use OCP\Mail\IMailer;
use OCP\Notification\IManager as INotificationManager;
use Psr\Log\LoggerInterface;

/**
 * Tells the people concerned by a membership request: emails and Nextcloud notifications.
 */
class RequestNotifier {
	/** Notification object sent to group admins about a pending request */
	public const OBJECT_REQUEST = 'join_request';
	/** Notification object sent to the requester about the decision */
	public const OBJECT_RESULT = 'join_request_result';

	public function __construct(
		private INotificationManager $notificationManager,
		private IMailer $mailer,
		private IURLGenerator $urlGenerator,
		private IFactory $l10nFactory,
		private ITimeFactory $timeFactory,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * @param IUser[] $admins
	 */
	public function notifyNewRequest(JoinRequest $request, IUser $requester, IGroup $group, array $admins): void {
		$link = $this->urlGenerator->linkToRouteAbsolute('delegate_groups.page.request', ['id' => $request->getId()]);
		foreach ($admins as $admin) {
			$notification = $this->notificationManager->createNotification();
			$notification->setApp(Application::APP_ID)
				->setUser($admin->getUID())
				->setDateTime($this->timeFactory->getDateTime())
				->setObject(self::OBJECT_REQUEST, (string)$request->getId())
				->setSubject('join_request', ['requester' => $requester->getUID(), 'group' => $group->getGID()]);
			$this->notificationManager->notify($notification);

			$this->sendRequestEmail($admin, $requester, $group, $link);
		}
	}

	private function sendRequestEmail(IUser $admin, IUser $requester, IGroup $group, string $link): void {
		$email = $admin->getEMailAddress();
		if ($email === null || $email === '') {
			return;
		}
		$l = $this->l10nFactory->get(Application::APP_ID, $this->l10nFactory->getUserLanguage($admin));
		$groupName = $group->getDisplayName();

		$template = $this->mailer->createEMailTemplate('delegate_groups.JoinRequest', [
			'requester' => $requester->getUID(),
			'group' => $group->getGID(),
		]);
		$template->setSubject($l->t('Membership request for the group "%s"', [$groupName]));
		$template->addHeader();
		$template->addHeading($l->t('New membership request'));
		$template->addBodyText($l->t('%1$s (%2$s) asks to join the group "%3$s", of which you are an administrator.', [
			$requester->getDisplayName(), $requester->getUID(), $groupName,
		]));
		$template->addBodyText($l->t('You can accept or refuse this request with the button below, or from the "My Groups" app.'));
		$template->addBodyButton($l->t('Review the request'), $link);
		$template->addBodyText($l->t('You receive this message because you administer the group "%s". Each administrator of the group receives it.', [$groupName]));
		$template->addFooter();

		try {
			$message = $this->mailer->createMessage();
			$message->setTo([$email => $admin->getDisplayName()]);
			$message->useTemplate($template);
			$this->mailer->send($message);
		} catch (\Exception $e) {
			$this->logger->error('Could not send the membership request email to ' . $admin->getUID(), ['exception' => $e]);
		}
	}

	public function notifyDecision(JoinRequest $request): void {
		$this->clearRequest($request);

		$notification = $this->notificationManager->createNotification();
		$notification->setApp(Application::APP_ID)
			->setUser($request->getUserId())
			->setDateTime($this->timeFactory->getDateTime())
			->setObject(self::OBJECT_RESULT, (string)$request->getId())
			->setSubject($request->getStatus() === JoinRequest::STATUS_ACCEPTED ? 'request_accepted' : 'request_refused', [
				'group' => $request->getGroupId(),
			]);
		$this->notificationManager->notify($notification);
	}

	/**
	 * Removes the notifications sent to the group admins about this request
	 */
	public function clearRequest(JoinRequest $request): void {
		$notification = $this->notificationManager->createNotification();
		$notification->setApp(Application::APP_ID)
			->setObject(self::OBJECT_REQUEST, (string)$request->getId());
		$this->notificationManager->markProcessed($notification);
	}
}
