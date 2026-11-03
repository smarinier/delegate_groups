/**
 * SPDX-FileCopyrightText: 2026 Sébastien Marinier
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { generateUrl } from '@nextcloud/router'
import { createRouter, createWebHistory } from 'vue-router'

// History-mode routes, as the Nextcloud apps do (no #):
//   /apps/delegate_groups/                 my groups
//   /apps/delegate_groups/group/<gid>      a group (members for its admins)
//   /apps/delegate_groups/request/<id>     review of a membership request (link of the email)
// Each of these URLs is also declared on the server (PageController), so a reload works.

// App.vue picks the view from the route name (it needs the groups it loaded),
// so the records do not render anything by themselves
const view = { render: () => null }

const router = createRouter({
	// generateUrl keeps "index.php/" when pretty URLs are not enabled
	history: createWebHistory(generateUrl('/apps/delegate_groups')),
	routes: [
		{ path: '/', name: 'groups', component: view },
		// (.+) keeps the slashes of a group id unencoded: some web servers refuse %2F
		{ path: '/group/:groupId(.+)', name: 'group', component: view },
		{ path: '/request/:requestId(\\d+)', name: 'request', component: view },
		{ path: '/:pathMatch(.*)*', redirect: { name: 'groups' } },
	],
})

export default router
