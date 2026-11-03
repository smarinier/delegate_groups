<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Sébastien Marinier
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DelegateGroups\Exception;

use OCP\AppFramework\Http;

/**
 * Business error, carrying the HTTP status the API should answer with
 * and a message that can be shown to the user (already translated).
 */
class MembershipException extends \Exception {
	public function __construct(
		string $message,
		private int $status = Http::STATUS_BAD_REQUEST,
		private array $data = [],
	) {
		parent::__construct($message);
	}

	public static function notFound(string $message): self {
		return new self($message, Http::STATUS_NOT_FOUND);
	}

	public static function forbidden(string $message): self {
		return new self($message, Http::STATUS_FORBIDDEN);
	}

	public static function conflict(string $message, array $data = []): self {
		return new self($message, Http::STATUS_CONFLICT, $data);
	}

	public function getStatus(): int {
		return $this->status;
	}

	public function getData(): array {
		return $this->data;
	}
}
