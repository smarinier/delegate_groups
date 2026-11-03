/**
 * SPDX-FileCopyrightText: 2026 Sébastien Marinier
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import axios from '@nextcloud/axios'
import { addPasswordConfirmationInterceptors, PwdConfirmationMode } from '@nextcloud/password-confirmation'
import { generateOcsUrl } from '@nextcloud/router'
import '@nextcloud/password-confirmation/style.css'

// Shows the password dialog when the server asks for a recent confirmation
addPasswordConfirmationInterceptors(axios)

async function call(method, path, { params, data, confirm = false } = {}) {
	const response = await axios.request({
		method,
		url: generateOcsUrl('apps/delegate_groups/api/v1/' + path),
		params,
		data,
		headers: { 'OCS-APIRequest': 'true' },
		// Actions that change group memberships or settings (#[PasswordConfirmationRequired])
		...(confirm ? { confirmPassword: PwdConfirmationMode.Lax } : {}),
	})
	return response.data.ocs.data
}

/**
 * Message of an API error, as sent by the server, or the fallback
 *
 * @param {Error} error the axios error
 * @param {string} fallback message to use when the server gave none
 */
export function errorMessage(error, fallback) {
	return error?.response?.data?.ocs?.data?.message || fallback
}

/**
 * Data sent along with an API error
 *
 * @param {Error} error the axios error
 */
export function errorData(error) {
	return error?.response?.data?.ocs?.data ?? {}
}

export default {
	myGroups: () => call('get', 'groups'),
	joinableGroups: () => call('get', 'joinable'),
	requestJoin: (groupId) => call('post', 'requests', { data: { groupId } }),
	getRequest: (id) => call('get', `requests/${id}`),
	cancelRequest: (id) => call('delete', `requests/${id}`),
	acceptRequest: (id) => call('post', `requests/${id}/accept`, { confirm: true }),
	refuseRequest: (id) => call('post', `requests/${id}/refuse`),
	members: (groupId) => call('get', 'members', { params: { groupId } }),
	addMember: (groupId, userId) => call('post', 'members', { data: { groupId, userId }, confirm: true }),
	removeMember: (groupId, userId) => call('delete', 'members', { params: { groupId, userId }, confirm: true }),
	searchUsers: (groupId, search) => call('get', 'users', { params: { groupId, search } }),
	saveSettings: (openGroups) => call('put', 'settings', { data: { openGroups }, confirm: true }),
	searchAdminCandidates: (search) => call('get', 'admins/users', { params: { search } }),
	saveGroupAdmins: (groupId, userIds) => call('put', 'admins', { data: { groupId, userIds }, confirm: true }),
}
