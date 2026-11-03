<!--
  - SPDX-FileCopyrightText: 2026 Sébastien Marinier
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="dg-page">
		<NcLoadingIcon v-if="loading" :size="44" />
		<NcEmptyContent v-else-if="loadError" :name="loadError">
			<template #icon>
				<ShieldOffIcon />
			</template>
			<template #action>
				<NcButton :to="{ name: 'groups' }">
					{{ t('delegate_groups', 'Back to my groups') }}
				</NcButton>
			</template>
		</NcEmptyContent>

		<template v-else>
			<RouterLink class="dg-breadcrumb dg-heading" :to="{ name: 'groups' }">{{ t('delegate_groups', 'My groups') }} ›</RouterLink>
			<div class="dg-members__header">
				<h2>{{ group.displayName }}</h2>
				<RolePill admin :label="t('delegate_groups', 'You are an administrator')" />
				<NcButton class="dg-members__add" variant="primary" @click="addOpen = true">
					<template #icon>
						<AccountPlusIcon :size="20" />
					</template>
					{{ t('delegate_groups', 'Add a member') }}
				</NcButton>
			</div>
			<p class="dg-subtitle">
				{{ n('delegate_groups', '%n member', '%n members', members.length) }}
				<template v-if="requests.length">
					· {{ n('delegate_groups', '%n pending request', '%n pending requests', requests.length) }}
				</template>
			</p>

			<NcTextField v-model="filter" class="dg-members__filter" :label="t('delegate_groups', 'Filter the members')" />

			<table class="dg-table">
				<thead>
					<tr>
						<th>{{ t('delegate_groups', 'Member') }}</th>
						<th>{{ t('delegate_groups', 'Account') }}</th>
						<th>{{ t('delegate_groups', 'Status') }}</th>
						<th class="dg-actions">
							{{ t('delegate_groups', 'Actions') }}
						</th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="request in filteredRequests" :key="'r' + request.id" class="dg-pending">
						<td>
							<div class="dg-name">
								<NcAvatar :user="request.user.id" :display-name="request.user.displayName" :size="32" hide-status />
								<span>{{ request.user.displayName }}</span>
							</div>
						</td>
						<td>{{ request.user.id }}</td>
						<td>
							<span class="dg-pill dg-pill--pending">{{ t('delegate_groups', 'Request of {date}', { date: formatDate(request.createdAt) }) }}</span>
						</td>
						<td class="dg-actions">
							<div>
								<NcButton variant="success" :disabled="busy" @click="decide(request, true)">
									{{ t('delegate_groups', 'Accept') }}
								</NcButton>
								<NcButton variant="error" :disabled="busy" @click="decide(request, false)">
									{{ t('delegate_groups', 'Refuse') }}
								</NcButton>
							</div>
						</td>
					</tr>
					<tr v-for="member in filteredMembers" :key="member.id">
						<td>
							<div class="dg-name">
								<NcAvatar :user="member.id" :display-name="member.displayName" :size="32" />
								<span :class="{ 'dg-members__self': member.isSelf }">
									{{ member.isSelf ? t('delegate_groups', '{user} (you)', { user: member.displayName }) : member.displayName }}
								</span>
							</div>
						</td>
						<td>{{ member.id }}</td>
						<td>
							<RolePill :admin="member.isAdmin" />
						</td>
						<td class="dg-actions">
							<span :title="removeBlockedReason(member)">
								<NcButton variant="tertiary"
									:disabled="busy || !!removeBlockedReason(member)"
									@click="removing = member">
									{{ t('delegate_groups', 'Remove') }}
								</NcButton>
							</span>
						</td>
					</tr>
				</tbody>
			</table>
			<p v-if="filter && !filteredMembers.length && !filteredRequests.length" class="dg-subtitle">
				{{ t('delegate_groups', 'No member matches your filter.') }}
			</p>
		</template>

		<AddMemberDialog v-if="addOpen"
			:group="group"
			@close="addOpen = false"
			@added="reload" />
		<ConfirmDialog v-if="removing"
			:name="t('delegate_groups', 'Remove a member')"
			:message="t('delegate_groups', 'Remove {user} from the group “{group}”?', { user: removing.displayName, group: group.displayName })"
			:confirm-label="t('delegate_groups', 'Remove')"
			:action="remove"
			@close="removing = null" />
	</div>
</template>

<script setup>
import { showError, showSuccess } from '@nextcloud/dialogs'
import { n, t } from '@nextcloud/l10n'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { computed, onMounted, ref } from 'vue'
import AccountPlusIcon from 'vue-material-design-icons/AccountPlus.vue'
import ShieldOffIcon from 'vue-material-design-icons/ShieldOff.vue'
import api, { errorMessage } from '../api.js'
import AddMemberDialog from '../components/AddMemberDialog.vue'
import ConfirmDialog from '../components/ConfirmDialog.vue'
import RolePill from '../components/RolePill.vue'
import { formatDate } from '../format.js'
import { RouterLink } from 'vue-router'

const props = defineProps({
	groupId: { type: String, required: true },
})
const emit = defineEmits(['changed'])

const group = ref(null)
const members = ref([])
const requests = ref([])
const loading = ref(true)
const loadError = ref(null)
const busy = ref(false)
const filter = ref('')
const addOpen = ref(false)
const removing = ref(null)

function matches(...values) {
	const query = filter.value.trim().toLocaleLowerCase()
	return !query || values.some((value) => value.toLocaleLowerCase().includes(query))
}
const filteredMembers = computed(() => members.value.filter((member) => matches(member.displayName, member.id)))
const filteredRequests = computed(() => requests.value.filter((request) => matches(request.user.displayName, request.user.id)))

function removeBlockedReason(member) {
	if (member.isSelf) {
		return t('delegate_groups', 'You cannot remove yourself from the group')
	}
	if (member.isAdmin) {
		return t('delegate_groups', 'Group admins are managed by the platform administrators')
	}
	return undefined
}

async function reload() {
	try {
		const data = await api.members(props.groupId)
		group.value = data.group
		members.value = data.members
		requests.value = data.requests
		loadError.value = null
	} catch (error) {
		loadError.value = errorMessage(error, t('delegate_groups', 'Could not load the members of the group'))
	} finally {
		loading.value = false
	}
	emit('changed')
}

async function decide(request, accept) {
	busy.value = true
	try {
		if (accept) {
			await api.acceptRequest(request.id)
			showSuccess(t('delegate_groups', '{user} joined the group', { user: request.user.displayName }))
		} else {
			await api.refuseRequest(request.id)
			showSuccess(t('delegate_groups', 'The request of {user} was refused', { user: request.user.displayName }))
		}
	} catch (error) {
		showError(errorMessage(error, t('delegate_groups', 'Could not process the request')))
	} finally {
		busy.value = false
	}
	await reload()
}

async function remove() {
	const member = removing.value
	busy.value = true
	try {
		await api.removeMember(props.groupId, member.id)
		showSuccess(t('delegate_groups', '{user} was removed from the group', { user: member.displayName }))
	} catch (error) {
		showError(errorMessage(error, t('delegate_groups', 'Could not remove the member')))
	} finally {
		busy.value = false
	}
	await reload()
}

onMounted(reload)
</script>

<style scoped lang="scss">
.dg-members__header {
	display: flex;
	align-items: center;
	flex-wrap: wrap;
	gap: calc(var(--default-grid-baseline) * 3);
}

.dg-members__add {
	margin-inline-start: auto;
}

.dg-members__filter {
	max-width: 360px;
	margin-bottom: calc(var(--default-grid-baseline) * 3);
}

.dg-members__self {
	font-weight: bold;
}
</style>
