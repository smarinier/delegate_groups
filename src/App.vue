<!--
  - SPDX-FileCopyrightText: 2026 Sébastien Marinier
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcContent app-name="delegate_groups">
		<NcAppNavigation>
			<NcAppNavigationNew :text="t('delegate_groups', 'Join a group')" @click="joinOpen = true">
				<template #icon>
					<AccountPlusIcon :size="20" />
				</template>
			</NcAppNavigationNew>
			<template #list>
				<NcAppNavigationItem :name="t('delegate_groups', 'All my groups')"
					:to="{ name: 'groups' }">
					<template #icon>
						<AccountGroupIcon :size="20" />
					</template>
					<template #counter>
						<NcCounterBubble v-if="groups.length" :count="groups.length" />
					</template>
				</NcAppNavigationItem>
				<NcAppNavigationCaption v-if="groups.length" :name="t('delegate_groups', 'My groups')" />
				<NcAppNavigationItem v-for="group in groups"
					:key="group.id"
					:name="group.displayName"
					:to="{ name: 'group', params: { groupId: group.id } }">
					<template #icon>
						<ShieldAccountIcon v-if="group.isAdmin" :size="20" />
						<AccountMultipleIcon v-else :size="20" />
					</template>
					<template #counter>
						<div class="dg-counters">
							<NcCounterBubble v-if="group.pendingCount"
								:count="group.pendingCount"
								active
								:title="n('delegate_groups', '%n pending request', '%n pending requests', group.pendingCount)" />
							<NcCounterBubble :count="group.memberCount"
								:title="n('delegate_groups', '%n member', '%n members', group.memberCount)" />
						</div>
					</template>
				</NcAppNavigationItem>
			</template>
		</NcAppNavigation>

		<NcAppContent>
			<GroupList v-if="route.name === 'groups'"
				:groups="groups"
				:requests="requests"
				:loading="loading"
				@join="joinOpen = true"
				@changed="refresh" />
			<GroupMembers v-else-if="route.name === 'group' && currentGroup?.canSeeMembers"
				:key="groupId"
				:group-id="groupId"
				@changed="refresh" />
			<GroupInfo v-else-if="route.name === 'group'"
				:group="currentGroup"
				:loading="loading" />
			<RequestReview v-else
				:key="requestId"
				:request-id="requestId"
				@changed="refresh" />
		</NcAppContent>

		<JoinDialog v-if="joinOpen" @close="joinOpen = false" @requested="refresh" />
	</NcContent>
</template>

<script setup>
import { showError } from '@nextcloud/dialogs'
import { n, t } from '@nextcloud/l10n'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationCaption from '@nextcloud/vue/components/NcAppNavigationCaption'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcAppNavigationNew from '@nextcloud/vue/components/NcAppNavigationNew'
import NcContent from '@nextcloud/vue/components/NcContent'
import NcCounterBubble from '@nextcloud/vue/components/NcCounterBubble'
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import AccountGroupIcon from 'vue-material-design-icons/AccountGroup.vue'
import AccountMultipleIcon from 'vue-material-design-icons/AccountMultiple.vue'
import AccountPlusIcon from 'vue-material-design-icons/AccountPlus.vue'
import ShieldAccountIcon from 'vue-material-design-icons/ShieldAccount.vue'
import api, { errorMessage } from './api.js'
import JoinDialog from './components/JoinDialog.vue'
import GroupInfo from './views/GroupInfo.vue'
import GroupList from './views/GroupList.vue'
import GroupMembers from './views/GroupMembers.vue'
import RequestReview from './views/RequestReview.vue'

const route = useRoute()
const groupId = computed(() => (route.name === 'group' ? String(route.params.groupId) : null))
const requestId = computed(() => (route.name === 'request' ? Number(route.params.requestId) : null))

const groups = ref([])
const requests = ref([])
const loading = ref(true)
const joinOpen = ref(false)

// Admins manage the members of the group; members see them when the platform allows it
// (users_see_members), or else only its card
const currentGroup = computed(() => groups.value.find((group) => group.id === groupId.value) ?? null)

async function refresh() {
	try {
		const data = await api.myGroups()
		groups.value = data.groups
		requests.value = data.requests
	} catch (error) {
		showError(errorMessage(error, t('delegate_groups', 'Could not load your groups')))
	} finally {
		loading.value = false
	}
}

onMounted(refresh)
</script>

<style lang="scss">
/* Shared by all the views of the app */
.dg-page {
	--dg-page-padding: calc(var(--default-grid-baseline) * 8);
	padding: var(--app-navigation-padding) var(--dg-page-padding) calc(var(--default-grid-baseline) * 4);
	max-width: 1100px;

	h2 {
		margin: var(--default-grid-baseline) 0 0;
		font-size: 1.6em;
	}

	/* First line of a page: leaves room for the navigation toggle, as the App Store does */
	.dg-heading {
		margin: 0;
		margin-inline-start: calc(var(--default-clickable-area) + var(--app-navigation-padding) * 2 - var(--dg-page-padding));
		min-height: var(--default-clickable-area);
		line-height: var(--default-clickable-area);
	}
}

.dg-breadcrumb {
	color: var(--color-text-maxcontrast);

	&:hover {
		text-decoration: underline;
	}
}

.dg-counters {
	display: flex;
	gap: var(--default-grid-baseline);
}

.dg-subtitle {
	color: var(--color-text-maxcontrast);
	margin-bottom: calc(var(--default-grid-baseline) * 4);
}

.dg-table {
	width: 100%;
	border-collapse: collapse;

	th {
		text-align: start;
		color: var(--color-text-maxcontrast);
		font-weight: bold;
		padding: var(--default-grid-baseline) calc(var(--default-grid-baseline) * 2);
		border-bottom: 1px solid var(--color-border);
	}

	td {
		padding: calc(var(--default-grid-baseline) * 2);
		border-bottom: 1px solid var(--color-border);
		vertical-align: middle;
	}

	tr.dg-clickable {
		cursor: pointer;

		&:hover {
			background-color: var(--color-background-hover);
		}
	}

	tr.dg-pending td {
		background-color: var(--color-warning-light, rgba(255, 196, 0, .12));
		font-style: italic;
	}

	.dg-actions {
		text-align: end;

		> div {
			display: inline-flex;
			gap: var(--default-grid-baseline);
			align-items: center;
		}
	}
}

.dg-name {
	display: flex;
	align-items: center;
	gap: calc(var(--default-grid-baseline) * 3);

	.dg-title {
		display: block;
		font-weight: bold;
	}

	.dg-sub {
		display: block;
		color: var(--color-text-maxcontrast);
		font-size: .9em;
	}
}

.dg-pill {
	display: inline-flex;
	align-items: center;
	gap: var(--default-grid-baseline);
	padding: 2px 12px;
	border-radius: var(--border-radius-pill);
	white-space: nowrap;
	font-style: normal;
	background-color: var(--color-background-dark);
	color: var(--color-main-text);

	&--admin {
		background-color: var(--color-primary-element-light);
		color: var(--color-primary-element-light-text);
		font-weight: bold;
	}

	&--pending {
		background-color: var(--color-warning);
		color: var(--color-warning-text, #000);
		font-weight: bold;
	}
}
</style>
