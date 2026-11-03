<!--
  - SPDX-FileCopyrightText: 2026 Sébastien Marinier
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<span class="dg-pill" :class="{ 'dg-pill--admin': admin }">
		<ShieldAccountIcon v-if="admin" :size="16" />
		{{ label ?? (admin ? t('delegate_groups', 'Administrator') : t('delegate_groups', 'Member')) }}
	</span>
</template>

<script setup>
import { t } from '@nextcloud/l10n'
import ShieldAccountIcon from 'vue-material-design-icons/ShieldAccount.vue'

defineProps({
	admin: { type: Boolean, default: false },
	label: { type: String, default: null },
})
</script>
