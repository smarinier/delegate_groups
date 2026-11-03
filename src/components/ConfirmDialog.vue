<!--
  - SPDX-FileCopyrightText: 2026 Sébastien Marinier
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcDialog :name="name"
		:message="message"
		:buttons="buttons"
		@update:open="(open) => !open && emit('close')" />
</template>

<script setup>
import { t } from '@nextcloud/l10n'
import NcDialog from '@nextcloud/vue/components/NcDialog'

const props = defineProps({
	name: { type: String, required: true },
	message: { type: String, default: '' },
	confirmLabel: { type: String, required: true },
	/** Called when the user confirms */
	action: { type: Function, required: true },
})
const emit = defineEmits(['close'])

const buttons = [
	{ label: t('delegate_groups', 'Cancel') },
	{ label: props.confirmLabel, variant: 'error', callback: () => props.action() },
]
</script>
