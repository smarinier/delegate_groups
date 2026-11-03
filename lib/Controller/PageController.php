<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Sébastien Marinier
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DelegateGroups\Controller;

use OCA\DelegateGroups\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\Util;

#[OpenAPI(scope: OpenAPI::SCOPE_IGNORE)]
class PageController extends Controller {
	public function __construct(
		IRequest $request,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * Front-end routes are handled by vue-router (history mode):
	 * every URL it knows renders the same page, so it can be reloaded or bookmarked.
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[FrontpageRoute(verb: 'GET', url: '/')]
	public function index(): TemplateResponse {
		return $this->render();
	}

	/**
	 * A group: its members for its admins, its card for its members
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[FrontpageRoute(verb: 'GET', url: '/group/{groupId}', requirements: ['groupId' => '.+'])]
	public function group(string $groupId): TemplateResponse {
		return $this->render();
	}

	/**
	 * Target of the link sent by email to the group admins
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[FrontpageRoute(verb: 'GET', url: '/request/{id}', requirements: ['id' => '\d+'])]
	public function request(int $id): TemplateResponse {
		return $this->render();
	}

	private function render(): TemplateResponse {
		Util::addScript(Application::APP_ID, Application::APP_ID . '-main');
		Util::addStyle(Application::APP_ID, Application::APP_ID . '-main');
		return new TemplateResponse(Application::APP_ID, 'main');
	}
}
