<!--
  - SPDX-FileCopyrightText: 2026 Sébastien Marinier
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcSettingsSection :name="t('delegate_groups', 'Group admins and membership requests')"
		:description="t('delegate_groups', 'Designate the admins of each group, and choose the groups users can ask to join from the “My Groups” app. The group admins receive the requests.')">
		<div class="dg-open-groups">
			<div class="dg-open-groups__toolbar">
				<NcTextField v-model="filter"
					:label="t('delegate_groups', 'Filter the groups')"
					type="search"
					class="dg-open-groups__filter">
					<template #icon>
						<MagnifyIcon :size="20" />
					</template>
				</NcTextField>
				<NcCheckboxRadioSwitch v-model="onlyOpen" type="switch">
					{{ t('delegate_groups', 'Only the open groups') }}
				</NcCheckboxRadioSwitch>
				<span class="dg-open-groups__count">
					{{ n('delegate_groups', '%n group open', '%n groups open', openGroups.length) }}
				</span>
			</div>

			<ul v-if="visibleGroups.length" class="dg-open-groups__list" :aria-busy="saving">
				<li v-for="group in visibleGroups" :key="group.id" class="dg-open-groups__row">
					<NcCheckboxRadioSwitch :model-value="openGroups.includes(group.id)"
						:disabled="saving || (!group.admins.length && !openGroups.includes(group.id))"
						class="dg-open-groups__switch"
						@update:model-value="toggle(group.id, $event)">
						<span class="dg-open-groups__name">{{ group.displayName }}</span>
						<span class="dg-open-groups__sub">
							{{ n('delegate_groups', '%n member', '%n members', group.memberCount) }}
							·
							<span v-if="group.admins.length">
								{{ adminNames(group) }}
							</span>
							<span v-else-if="openGroups.includes(group.id)" class="dg-open-groups__warning">
								{{ t('delegate_groups', 'no group admin any more: not offered to users, uncheck it') }}
							</span>
							<span v-else>
								{{ t('delegate_groups', 'no group admin: designate one to open the group') }}
							</span>
						</span>
					</NcCheckboxRadioSwitch>
					<NcButton variant="secondary" @click="editedGroup = group">
						<template #icon>
							<AccountCogIcon :size="20" />
						</template>
						{{ t('delegate_groups', 'Admins') }}
					</NcButton>
				</li>
			</ul>
			<p v-else-if="groups.length" class="dg-open-groups__empty">
				{{ t('delegate_groups', 'No group matches') }}
			</p>
			<p v-else class="dg-open-groups__empty">
				{{ t('delegate_groups', 'No group yet.') }}
			</p>
		</div>

		<GroupAdminsDialog v-if="editedGroup"
			:group="editedGroup"
			@saved="(admins) => (editedGroup.admins = admins)"
			@close="editedGroup = null" />

		<NcNoteCard type="info">
			<p>{{ t('delegate_groups', 'Group admins are designated here only, by the platform admins. They are not the Nextcloud group admins of the Accounts settings: they only manage the membership of their groups, and get no right on the accounts themselves.') }}</p>
			<p>{{ t('delegate_groups', 'A group can only be opened to requests when it has at least one group admin, since nobody could handle them otherwise.') }}</p>
			<p>{{ t('delegate_groups', 'The “admin” group can never be opened to requests.') }}</p>
		</NcNoteCard>
	</NcSettingsSection>
</template>

<script setup>
import { showError, showSuccess } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import { n, t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { computed, ref } from 'vue'
import AccountCogIcon from 'vue-material-design-icons/AccountCog.vue'
import MagnifyIcon from 'vue-material-design-icons/Magnify.vue'
import api, { errorMessage } from './api.js'
import GroupAdminsDialog from './components/GroupAdminsDialog.vue'

const groups = ref(loadState('delegate_groups', 'groups', []))
const editedGroup = ref(null)
const openGroups = ref(loadState('delegate_groups', 'openGroups', []))
const saving = ref(false)
const filter = ref('')
const onlyOpen = ref(false)

// The list keeps its order (by name) when a box is checked, so nothing moves under the mouse
const visibleGroups = computed(() => {
	const query = filter.value.trim().toLocaleLowerCase()
	return groups.value.filter((group) => (!onlyOpen.value || openGroups.value.includes(group.id))
		&& (query === ''
			|| group.displayName.toLocaleLowerCase().includes(query)
			|| group.id.toLocaleLowerCase().includes(query)))
})

/**
 * @param {object} group a group of the list
 */
function adminNames(group) {
	const names = group.admins.map((admin) => admin.displayName)
	return n('delegate_groups', 'admin: {names}', 'admins: {names}', names.length, { names: names.join(', ') })
}

/**
 * @param {string} groupId the group checked or unchecked
 * @param {boolean} open its new state
 */
async function toggle(groupId, open) {
	const ids = open
		? [...openGroups.value, groupId]
		: openGroups.value.filter((id) => id !== groupId)
	saving.value = true
	try {
		const data = await api.saveSettings(ids)
		openGroups.value = data.openGroups
		showSuccess(t('delegate_groups', 'Settings saved'))
	} catch (error) {
		showError(errorMessage(error, t('delegate_groups', 'Could not save the settings')))
	} finally {
		saving.value = false
	}
}
</script>

<style lang="scss" scoped>
.dg-open-groups {
	max-width: 700px;
	margin-bottom: calc(var(--default-grid-baseline) * 4);

	&__toolbar {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		gap: calc(var(--default-grid-baseline) * 4);
		margin-bottom: calc(var(--default-grid-baseline) * 2);
	}

	&__filter {
		flex: 1 1 250px;
		max-width: 350px;
	}

	&__count {
		color: var(--color-text-maxcontrast);
	}

	&__list {
		max-height: 420px;
		overflow-y: auto;
		border: 1px solid var(--color-border);
		border-radius: var(--border-radius-large);
		padding: var(--default-grid-baseline) calc(var(--default-grid-baseline) * 2);

		li + li {
			border-top: 1px solid var(--color-border);
		}
	}

	&__row {
		display: flex;
		align-items: center;
		gap: calc(var(--default-grid-baseline) * 2);
	}

	&__switch {
		flex: 1 1 auto;
		min-width: 0;
	}

	&__name {
		display: block;
		font-weight: bold;
	}

	&__sub {
		display: block;
		color: var(--color-text-maxcontrast);
		font-size: .9em;
	}

	&__warning {
		color: var(--color-warning-text, var(--color-text-maxcontrast));
		font-style: italic;
	}

	&__empty {
		color: var(--color-text-maxcontrast);
		padding: calc(var(--default-grid-baseline) * 2) 0;
	}
}
</style>
