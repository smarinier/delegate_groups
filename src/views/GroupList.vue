<!--
  - SPDX-FileCopyrightText: 2026 Sébastien Marinier
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="dg-page">
		<h2 class="dg-heading">{{ t('delegate_groups', 'My groups') }}</h2>
		<p class="dg-subtitle">
			{{ t('delegate_groups', 'The groups you belong to or manage.') }}
		</p>

		<NcLoadingIcon v-if="loading" :size="44" />
		<NcEmptyContent v-else-if="!groups.length && !requests.length"
			:name="t('delegate_groups', 'You are not a member of any group')"
			:description="t('delegate_groups', 'You can ask to join the groups open to membership requests.')">
			<template #icon>
				<AccountGroupIcon />
			</template>
			<template #action>
				<NcButton variant="primary" @click="emit('join')">
					{{ t('delegate_groups', 'Join a group') }}
				</NcButton>
			</template>
		</NcEmptyContent>

		<table v-else class="dg-table">
			<thead>
				<tr>
					<th>{{ t('delegate_groups', 'Group') }}</th>
					<th>{{ t('delegate_groups', 'Members') }}</th>
					<th>{{ t('delegate_groups', 'My role') }}</th>
					<th><span class="hidden-visually">{{ t('delegate_groups', 'Actions') }}</span></th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="request in requests" :key="'r' + request.id" class="dg-pending">
					<td>
						<div class="dg-name">
							<NcAvatar :display-name="request.group.displayName" is-no-user :size="36" />
							<div>
								<span class="dg-title">{{ request.group.displayName }}</span>
								<span class="dg-sub">{{ t('delegate_groups', 'Request sent on {date}', { date: formatDate(request.createdAt) }) }}</span>
							</div>
						</div>
					</td>
					<td>—</td>
					<td>
						<span class="dg-pill dg-pill--pending">{{ t('delegate_groups', 'Awaiting approval') }}</span>
					</td>
					<td class="dg-actions">
						<NcButton variant="tertiary" @click="askCancel(request)">
							{{ t('delegate_groups', 'Cancel the request') }}
						</NcButton>
					</td>
				</tr>
				<tr v-for="group in groups"
					:key="group.id"
					class="dg-clickable"
					@click="router.push({ name: 'group', params: { groupId: group.id } })">
					<td>
						<div class="dg-name">
							<NcAvatar :display-name="group.displayName" is-no-user :size="36" />
							<div>
								<span class="dg-title">{{ group.displayName }}</span>
								<span v-if="!group.isMember" class="dg-sub">{{ t('delegate_groups', 'You manage this group without being a member') }}</span>
							</div>
						</div>
					</td>
					<td>{{ group.memberCount }}</td>
					<td>
						<RolePill :admin="group.isAdmin" />
					</td>
					<td class="dg-actions">
						<div v-if="group.isAdmin">
							<NcCounterBubble v-if="group.pendingCount"
								:count="group.pendingCount"
								active
								:title="n('delegate_groups', '%n pending request', '%n pending requests', group.pendingCount)" />
							<NcButton :to="{ name: 'group', params: { groupId: group.id } }" @click.stop>
								{{ t('delegate_groups', 'Manage members') }}
							</NcButton>
						</div>
					</td>
				</tr>
			</tbody>
		</table>

		<ConfirmDialog v-if="cancelling"
			:name="t('delegate_groups', 'Cancel the request')"
			:message="t('delegate_groups', 'Cancel your request to join the group “{group}”?', { group: cancelling.group.displayName })"
			:confirm-label="t('delegate_groups', 'Cancel the request')"
			:action="cancel"
			@close="cancelling = null" />
	</div>
</template>

<script setup>
import { showError, showSuccess } from '@nextcloud/dialogs'
import { n, t } from '@nextcloud/l10n'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCounterBubble from '@nextcloud/vue/components/NcCounterBubble'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import { ref } from 'vue'
import AccountGroupIcon from 'vue-material-design-icons/AccountGroup.vue'
import api, { errorMessage } from '../api.js'
import ConfirmDialog from '../components/ConfirmDialog.vue'
import RolePill from '../components/RolePill.vue'
import { formatDate } from '../format.js'
import { useRouter } from 'vue-router'

const router = useRouter()

defineProps({
	groups: { type: Array, required: true },
	requests: { type: Array, required: true },
	loading: { type: Boolean, default: false },
})
const emit = defineEmits(['join', 'changed'])

const cancelling = ref(null)

function askCancel(request) {
	cancelling.value = request
}

async function cancel() {
	const request = cancelling.value
	try {
		await api.cancelRequest(request.id)
		showSuccess(t('delegate_groups', 'Your request was cancelled'))
	} catch (error) {
		showError(errorMessage(error, t('delegate_groups', 'Could not cancel the request')))
	}
	emit('changed')
}
</script>
