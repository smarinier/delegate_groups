/**
 * SPDX-FileCopyrightText: 2026 Sébastien Marinier
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { createAppConfig } from '@nextcloud/vite-config'
import { join } from 'node:path'

export default createAppConfig({
	main: join(import.meta.dirname, 'src', 'main.ts'),
	admin: join(import.meta.dirname, 'src', 'admin.ts'),
}, {
	inlineCSS: false,
})
