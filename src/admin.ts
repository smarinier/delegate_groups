/**
 * SPDX-FileCopyrightText: 2026 Sébastien Marinier
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { createApp } from 'vue'
import AdminSettings from './AdminSettings.vue'
import '@nextcloud/dialogs/style.css'

createApp(AdminSettings).mount('#delegate_groups-admin')
