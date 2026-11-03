<!--
  - SPDX-FileCopyrightText: 2026 Sébastien Marinier
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcDialog :name="t('delegate_groups', 'Add a member to “{group}”', { group: group.displayName })"
		size="normal"
		@update:open="(open) => !open && emit('close')">
		<p class="dg-add__intro">
			{{ t('delegate_groups', 'Search among all the accounts of this platform.') }}
		</p>
		<NcTextField v-model="search"
			:label="t('delegate_groups', 'Name or account id')"
			@update:model-value="onSearch" />

		<NcLoadingIcon v-if="loading" :size="32" class="dg-add__loading" />
		<ul v-else-if="results.length" class="dg-add__list" role="listbox">
			<li v-for="user in results"
				:key="user.id"
				role="option"
				:aria-selected="selected === user.id"
				:aria-disabled="user.isMember"
				:class="{ 'dg-add__item--selected': selected === user.id, 'dg-add__item--disabled': user.isMember }"
				@click="!user.isMember && (selected = user.id)">
				<NcAvatar :user="user.id" :display-name="user.displayName" :size="32" disable-menu hide-status />
				<div class="dg-add__name">
					<strong>{{ user.displayName }}</strong>
					<span>{{ user.isMember ? t('delegate_groups', '{user} · already a member of the group', { user: user.id }) : user.id }}</span>
				</div>
			</li>
		</ul>
		<p v-else-if="search.trim().length >= 2" class="dg-add__empty">
			{{ t('delegate_groups', 'No account found.') }}
		</p>

		<p class="dg-add__hint">
			{{ t('delegate_groups', 'An account added by an administrator joins the group immediately, without a request.') }}
		</p>

		<template #actions>
			<NcButton @click="emit('close')">
				{{ t('delegate_groups', 'Cancel') }}
			</NcButton>
			<NcButton variant="primary" :disabled="!selected || adding" @click="add">
				{{ t('delegate_groups', 'Add') }}
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
import { ref } from 'vue'
import api, { errorMessage } from '../api.js'

const props = defineProps({
	group: { type: Object, required: true },
})
const emit = defineEmits(['close', 'added'])

const search = ref('')
const results = ref([])
const selected = ref(null)
const loading = ref(false)
const adding = ref(false)

let timer = null
let lastQuery = 0

function onSearch() {
	clearTimeout(timer)
	selected.value = null
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
		const users = await api.searchUsers(props.group.id, search.value)
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

async function add() {
	const user = results.value.find((result) => result.id === selected.value)
	adding.value = true
	try {
		await api.addMember(props.group.id, user.id)
		showSuccess(t('delegate_groups', '{user} was added to the group', { user: user.displayName }))
		emit('added')
		emit('close')
	} catch (error) {
		showError(errorMessage(error, t('delegate_groups', 'Could not add the member')))
	} finally {
		adding.value = false
	}
}
</script>

<style scoped lang="scss">
.dg-add__intro,
.dg-add__hint,
.dg-add__empty {
	color: var(--color-text-maxcontrast);
	margin: calc(var(--default-grid-baseline) * 2) 0;
}

.dg-add__hint {
	font-style: italic;
}

.dg-add__loading {
	margin: calc(var(--default-grid-baseline) * 4);
}

.dg-add__list {
	margin-top: calc(var(--default-grid-baseline) * 2);
	max-height: 260px;
	overflow-y: auto;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	padding: var(--default-grid-baseline);

	li {
		display: flex;
		align-items: center;
		gap: calc(var(--default-grid-baseline) * 3);
		padding: calc(var(--default-grid-baseline) * 2);
		border-radius: var(--border-radius-element, 8px);
		cursor: pointer;

		&:hover {
			background-color: var(--color-background-hover);
		}
	}

	.dg-add__item--selected,
	.dg-add__item--selected:hover {
		background-color: var(--color-primary-element-light);
	}

	.dg-add__item--disabled {
		cursor: default;
		opacity: .6;

		&:hover {
			background-color: transparent;
		}
	}
}

.dg-add__name {
	display: flex;
	flex-direction: column;

	span {
		color: var(--color-text-maxcontrast);
	}
}
</style>
