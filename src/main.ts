/**
 * SPDX-FileCopyrightText: 2026 Sébastien Marinier
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { createApp } from 'vue'
import App from './App.vue'
import router from './router.js'
import '@nextcloud/dialogs/style.css'

createApp(App).use(router).mount('#content')
