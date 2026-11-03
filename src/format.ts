/**
 * SPDX-FileCopyrightText: 2026 Sébastien Marinier
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { getCanonicalLocale } from '@nextcloud/l10n'

/**
 * @param {number} timestamp unix timestamp, in seconds
 */
export function formatDate(timestamp) {
	return new Date(timestamp * 1000).toLocaleDateString(getCanonicalLocale())
}

/**
 * @param {number} timestamp unix timestamp, in seconds
 */
export function formatDateTime(timestamp) {
	return new Date(timestamp * 1000).toLocaleString(getCanonicalLocale(), { dateStyle: 'short', timeStyle: 'short' })
}
