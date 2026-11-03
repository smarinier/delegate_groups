<!--
  - SPDX-FileCopyrightText: 2026 Sébastien Marinier
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcDialog :name="t('delegate_groups', 'Admins of the group “{group}”', { group: group.displayName })"
		size="normal"
		@update:open="(open) => !open && emit('close')">
		<p class="dg-admins__intro">
			{{ t('delegate_groups', 'Group admins handle the membership requests of this group, and can add or remove its members. They get no other right on the accounts.') }}
		</p>

		<ul v-if="admins.length" class="dg-admins__list">
			<li v-for="admin in admins" :key="admin.id" class="dg-admins__item">
				<NcAvatar :user="admin.id" :display-name="admin.displayName" :size="32" disable-menu hide-status />
				<div class="dg-admins__name">
					<strong>{{ admin.displayName }}</strong>
					<span>{{ admin.enabled ? admin.id : t('delegate_groups', '{user} · disabled account', { user: admin.id }) }}</span>
				</div>
				<NcButton variant="tertiary"
					:aria-label="t('delegate_groups', 'Remove {user} from the group admins', { user: admin.displayName })"
					@click="remove(admin.id)">
					<template #icon>
						<CloseIcon :size="20" />
					</template>
				</NcButton>
			</li>
		</ul>
		<p v-else class="dg-admins__empty">
			{{ t('delegate_groups', 'No group admin: the group cannot be opened to membership requests.') }}
		</p>

		<NcTextField v-model="search"
			:label="t('delegate_groups', 'Add an admin: name or account id')"
			class="dg-admins__search"
			@update:model-value="onSearch" />
		<NcLoadingIcon v-if="loading" :size="32" class="dg-admins__loading" />
		<ul v-else-if="results.length" class="dg-admins__list dg-admins__results" role="listbox">
			<li v-for="user in results"
				:key="user.id"
				role="option"
				:aria-selected="false"
				:aria-disabled="isAdmin(user.id)"
				:class="['dg-admins__item', 'dg-admins__result', { 'dg-admins__result--disabled': isAdmin(user.id) }]"
				@click="add(user)">
				<NcAvatar :user="user.id" :display-name="user.displayName" :size="32" disable-menu hide-status />
				<div class="dg-admins__name">
					<strong>{{ user.displayName }}</strong>
					<span>{{ isAdmin(user.id) ? t('delegate_groups', '{user} · already a group admin', { user: user.id }) : user.id }}</span>
				</div>
			</li>
		</ul>
		<p v-else-if="search.trim().length >= 2" class="dg-admins__empty">
			{{ t('delegate_groups', 'No account found.') }}
		</p>

		<template #actions>
			<NcButton @click="emit('close')">
				{{ t('delegate_groups', 'Cancel') }}
			</NcButton>
			<NcButton variant="primary" :disabled="!changed || saving" @click="save">
				{{ t('delegate_groups', 'Save') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script setup>
import { showError, showSuccess } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { computed, ref } from 'vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import api, { errorMessage } from '../api.js'

const props = defineProps({
	group: { type: Object, required: true },
})
const emit = defineEmits(['close', 'saved'])

const admins = ref([...props.group.admins])
const search = ref('')
const results = ref([])
const loading = ref(false)
const saving = ref(false)

const changed = computed(() => {
	const before = props.group.admins.map((admin) => admin.id).sort().join('\n')
	const after = admins.value.map((admin) => admin.id).sort().join('\n')
	return before !== after
})

/**
 * @param {string} userId an account id
 */
function isAdmin(userId) {
	return admins.value.some((admin) => admin.id === userId)
}

/**
 * @param {object} user a search result
 */
function add(user) {
	if (!isAdmin(user.id)) {
		admins.value = [...admins.value, user]
	}
}

/**
 * @param {string} userId an account id
 */
function remove(userId) {
	admins.value = admins.value.filter((admin) => admin.id !== userId)
}

let timer = null
let lastQuery = 0

function onSearch() {
	clearTimeout(timer)
	if (search.value.trim().length < 2) {
		results.value = []
		return
	}
	timer = setTimeout(runSearch, 300)
}

async function runSearch() {
	const query = ++lastQuery
	loading.value = true
	try {
		const users = await api.searchAdminCandidates(search.value)
		if (query === lastQuery) {
			results.value = users
		}
	} catch (error) {
		showError(errorMessage(error, t('delegate_groups', 'Could not search the accounts')))
	} finally {
		if (query === lastQuery) {
			loading.value = false
		}
	}
}

async function save() {
	saving.value = true
	try {
		const data = await api.saveGroupAdmins(props.group.id, admins.value.map((admin) => admin.id))
		showSuccess(t('delegate_groups', 'Group admins saved'))
		emit('saved', data.admins)
		emit('close')
	} catch (error) {
		showError(errorMessage(error, t('delegate_groups', 'Could not save the group admins')))
	} finally {
		saving.value = false
	}
}
</script>

<style scoped lang="scss">
.dg-admins__intro,
.dg-admins__empty {
	color: var(--color-text-maxcontrast);
	margin: calc(var(--default-grid-baseline) * 2) 0;
}

.dg-admins__search {
	margin-top: calc(var(--default-grid-baseline) * 4);
}

.dg-admins__loading {
	margin: calc(var(--default-grid-baseline) * 4);
}

.dg-admins__list {
	margin-top: calc(var(--default-grid-baseline) * 2);
	max-height: 220px;
	overflow-y: auto;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
}

.dg-admins__item {
	display: flex;
	align-items: center;
	gap: calc(var(--default-grid-baseline) * 3);
	padding: var(--default-grid-baseline) calc(var(--default-grid-baseline) * 2);

	& + & {
		border-top: 1px solid var(--color-border);
	}
}

.dg-admins__result {
	cursor: pointer;

	&:hover {
		background-color: var(--color-background-hover);
	}

	&--disabled {
		cursor: default;
		opacity: .6;
	}
}

.dg-admins__name {
	display: flex;
	flex-direction: column;
	flex: 1 1 auto;
	min-width: 0;

	span {
		color: var(--color-text-maxcontrast);
		font-size: .9em;
	}
}
</style>
