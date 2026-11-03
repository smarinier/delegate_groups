<!--
  - SPDX-FileCopyrightText: 2026 Sébastien Marinier
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="dg-page">
		<NcLoadingIcon v-if="loading" :size="44" />
		<NcEmptyContent v-else-if="!group" :name="t('delegate_groups', 'You are not a member of this group')">
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
			<div class="dg-info">
				<NcAvatar :display-name="group.displayName" is-no-user :size="64" />
				<div>
					<h2>{{ group.displayName }}</h2>
					<p class="dg-subtitle">
						{{ n('delegate_groups', '%n member', '%n members', group.memberCount) }}
					</p>
				</div>
				<RolePill />
			</div>
		</template>
	</div>
</template>

<script setup>
import { n, t } from '@nextcloud/l10n'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import ShieldOffIcon from 'vue-material-design-icons/ShieldOff.vue'
import RolePill from '../components/RolePill.vue'
import { RouterLink } from 'vue-router'

defineProps({
	group: { type: Object, default: null },
	loading: { type: Boolean, default: false },
})
</script>

<style scoped lang="scss">
.dg-info {
	display: flex;
	align-items: center;
	flex-wrap: wrap;
	gap: calc(var(--default-grid-baseline) * 4);
	margin-top: calc(var(--default-grid-baseline) * 4);

	.dg-subtitle {
		margin-bottom: 0;
	}
}
</style>
