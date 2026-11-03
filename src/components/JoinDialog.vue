<!--
  - SPDX-FileCopyrightText: 2026 Sébastien Marinier
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcDialog :name="t('delegate_groups', 'Join a group')"
		size="normal"
		@update:open="(open) => !open && emit('close')">
		<p class="dg-join__intro">
			{{ t('delegate_groups', 'Choose the group you want to join.') }}
		</p>
		<NcTextField v-model="search" :label="t('delegate_groups', 'Search a group')" />

		<NcLoadingIcon v-if="loading" :size="32" class="dg-join__loading" />
		<ul v-else-if="filtered.length" class="dg-join__list">
			<li v-for="group in filtered" :key="group.id">
				<NcAvatar :display-name="group.displayName" is-no-user :size="36" />
				<div class="dg-join__name">
					<strong>{{ group.displayName }}</strong>
					<span>{{ n('delegate_groups', '%n member', '%n members', group.memberCount) }}</span>
				</div>
				<NcButton v-if="!group.requestId"
					variant="primary"
					:disabled="busy === group.id"
					@click="request(group)">
					{{ t('delegate_groups', 'Request') }}
				</NcButton>
				<span v-else class="dg-join__sent">{{ t('delegate_groups', 'Request sent') }}</span>
			</li>
		</ul>
		<p v-else class="dg-join__empty">
			{{ search
				? t('delegate_groups', 'No group matches your search.')
				: t('delegate_groups', 'No group is open to your membership requests at the moment.') }}
		</p>

		<NcNoteCard type="info" :heading="t('delegate_groups', 'The group you are looking for is not listed?')">
			<p>{{ t('delegate_groups', 'Only the groups open to membership requests, and that you are not a member of yet, are listed here.') }}</p>
			<p>{{ t('delegate_groups', 'This is not final: contact the referent of the group, who can add you directly or have the group opened to requests.') }}</p>
		</NcNoteCard>

		<template #actions>
			<NcButton @click="emit('close')">
				{{ t('delegate_groups', 'Close') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script setup>
import { showError, showSuccess } from '@nextcloud/dialogs'
import { n, t } from '@nextcloud/l10n'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { computed, onMounted, ref } from 'vue'
import api, { errorData, errorMessage } from '../api.js'

const emit = defineEmits(['close', 'requested'])

const groups = ref([])
const loading = ref(true)
const search = ref('')
const busy = ref(null)

const filtered = computed(() => {
	const query = search.value.trim().toLocaleLowerCase()
	return query
		? groups.value.filter((group) => group.displayName.toLocaleLowerCase().includes(query))
		: groups.value
})

onMounted(async () => {
	try {
		groups.value = await api.joinableGroups()
	} catch (error) {
		showError(errorMessage(error, t('delegate_groups', 'Could not load the groups')))
	} finally {
		loading.value = false
	}
})

async function request(group) {
	busy.value = group.id
	try {
		const { id } = await api.requestJoin(group.id)
		group.requestId = id
		showSuccess(t('delegate_groups', 'Your request was sent to the administrators of the group “{group}”.', { group: group.displayName }))
		emit('requested')
	} catch (error) {
		group.requestId = errorData(error).requestId ?? null
		showError(errorMessage(error, t('delegate_groups', 'Could not send the request')))
	} finally {
		busy.value = null
	}
}
</script>

<style scoped lang="scss">
.dg-join__intro {
	color: var(--color-text-maxcontrast);
	margin-bottom: calc(var(--default-grid-baseline) * 2);
}

.dg-join__loading {
	margin: calc(var(--default-grid-baseline) * 4);
}

.dg-join__list {
	margin: calc(var(--default-grid-baseline) * 2) 0;

	li {
		display: flex;
		align-items: center;
		gap: calc(var(--default-grid-baseline) * 3);
		padding: calc(var(--default-grid-baseline) * 2) 0;
		border-bottom: 1px solid var(--color-border);
	}
}

.dg-join__name {
	flex: 1;
	display: flex;
	flex-direction: column;

	span {
		color: var(--color-text-maxcontrast);
	}
}

.dg-join__sent {
	color: var(--color-text-maxcontrast);
	font-style: italic;
	padding: 0 calc(var(--default-grid-baseline) * 3);
}

.dg-join__empty {
	color: var(--color-text-maxcontrast);
	margin: calc(var(--default-grid-baseline) * 4) 0;
}
</style>
