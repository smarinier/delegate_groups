<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Sébastien Marinier
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DelegateGroups\AppInfo;

use OCA\DelegateGroups\Listener\CleanupListener;
use OCA\DelegateGroups\Notification\Notifier;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Group\Events\GroupDeletedEvent;
use OCP\Group\Events\UserAddedEvent;
use OCP\User\Events\UserDeletedEvent;

class Application extends App implements IBootstrap {
	public const APP_ID = 'delegate_groups';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerNotifierService(Notifier::class);
		$context->registerEventListener(UserAddedEvent::class, CleanupListener::class);
		$context->registerEventListener(UserDeletedEvent::class, CleanupListener::class);
		$context->registerEventListener(GroupDeletedEvent::class, CleanupListener::class);
	}

	public function boot(IBootContext $context): void {
	}
}
