<!--
  - SPDX-FileCopyrightText: 2026 Sébastien Marinier
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="dg-page dg-review">
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

		<div v-else class="dg-review__card">
			<h2>{{ t('delegate_groups', 'Membership request') }}</h2>
			<NcAvatar :user="request.user.id" :display-name="request.user.displayName" :size="64" />
			<p class="dg-review__who">
				<strong>{{ request.user.displayName }}</strong> ({{ request.user.id }})
				<br>
				{{ t('delegate_groups', 'wants to join the group “{group}”', { group: request.group.displayName }) }}
			</p>
			<p class="dg-subtitle">
				{{ t('delegate_groups', 'Request sent on {date}', { date: formatDateTime(request.createdAt) }) }}
			</p>

			<div v-if="request.status === 'pending'" class="dg-review__buttons">
				<NcButton variant="primary" :disabled="busy" @click="decide(true)">
					<template #icon>
						<CheckIcon :size="20" />
					</template>
					{{ t('delegate_groups', 'Accept the membership') }}
				</NcButton>
				<NcButton :disabled="busy" @click="decide(false)">
					{{ t('delegate_groups', 'Refuse') }}
				</NcButton>
			</div>
			<NcNoteCard v-else :type="request.status === 'accepted' ? 'success' : 'info'" :text="outcome" />

			<NcButton variant="tertiary" :to="{ name: 'group', params: { groupId: request.group.id } }">
				{{ t('delegate_groups', 'See all the members of the group') }}
			</NcButton>
		</div>
	</div>
</template>

<script setup>
import { showError, showSuccess } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import { computed, onMounted, ref } from 'vue'
import CheckIcon from 'vue-material-design-icons/Check.vue'
import ShieldOffIcon from 'vue-material-design-icons/ShieldOff.vue'
import api, { errorData, errorMessage } from '../api.js'
import { formatDate, formatDateTime } from '../format.js'

const props = defineProps({
	requestId: { type: Number, required: true },
})
const emit = defineEmits(['changed'])

const request = ref(null)
const loading = ref(true)
const loadError = ref(null)
const busy = ref(false)

const outcome = computed(() => {
	const r = request.value
	const params = {
		user: r.user.displayName,
		by: r.decidedBy?.displayName ?? t('delegate_groups', 'a platform administrator'),
		date: r.decidedAt ? formatDate(r.decidedAt) : '',
	}
	switch (r.status) {
	case 'accepted':
		return t('delegate_groups', 'Request accepted by {by} on {date}: {user} is a member of the group.', params)
	case 'refused':
		return t('delegate_groups', 'Request refused by {by} on {date}.', params)
	default:
		return t('delegate_groups', 'This request was cancelled by its author: it is no longer active.')
	}
})

onMounted(async () => {
	try {
		request.value = await api.getRequest(props.requestId)
	} catch (error) {
		loadError.value = errorMessage(error, t('delegate_groups', 'Could not load the request'))
	} finally {
		loading.value = false
	}
})

async function decide(accept) {
	busy.value = true
	try {
		request.value = accept ? await api.acceptRequest(props.requestId) : await api.refuseRequest(props.requestId)
		showSuccess(accept
			? t('delegate_groups', '{user} joined the group', { user: request.value.user.displayName })
			: t('delegate_groups', 'The request of {user} was refused', { user: request.value.user.displayName }))
		emit('changed')
	} catch (error) {
		// Already processed by another admin: the server sends the current state
		const data = errorData(error)
		if (data.status) {
			request.value = data
		}
		showError(errorMessage(error, t('delegate_groups', 'Could not process the request')))
	} finally {
		busy.value = false
	}
}
</script>

<style scoped lang="scss">
.dg-review__card {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: calc(var(--default-grid-baseline) * 3);
	max-width: 520px;
	margin: calc(var(--default-grid-baseline) * 8) auto;
	padding: calc(var(--default-grid-baseline) * 6);
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-container-large, 16px);
	text-align: center;
}

.dg-review__who {
	font-size: 1.1em;
}

.dg-review__buttons {
	display: flex;
	gap: calc(var(--default-grid-baseline) * 3);
}
</style>
