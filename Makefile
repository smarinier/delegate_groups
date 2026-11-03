# SPDX-FileCopyrightText: 2026 Sébastien Marinier
# SPDX-License-Identifier: AGPL-3.0-or-later
#
# Build and packaging of the app, following the Nextcloud app conventions.
#
#   make              same as make build
#   make build        installs the npm dependencies (npm ci) and builds the front end for production
#   make dev          same, development build
#   make appstore     builds, then makes build/artifacts/appstore/delegate_groups.tar.gz
#                     (signed if the app certificate is found, see below)
#   make clean        removes the compiled assets and the build directory
#   make distclean    same, plus node_modules
#
# Signing (optional, for the Nextcloud app store):
#   the key and certificate are looked for in $(cert_dir) (default ~/.nextcloud/certificates),
#   named delegate_groups.key and delegate_groups.crt. When they are present, `make appstore`:
#   - signs the app code with `occ integrity:sign-app` (appinfo/signature.json in the archive),
#   - prints the archive signature to paste in the app store upload form.
#   `occ` is run with $(occ) (default: php ../../occ), e.g. make appstore occ="sudo -u www-data php /var/www/nextcloud/occ"

app_name := delegate_groups
version := $(shell sed -n 's|.*<version>\(.*\)</version>.*|\1|p' appinfo/info.xml)

build_dir := $(CURDIR)/build
appstore_dir := $(build_dir)/artifacts/appstore
sign_dir := $(build_dir)/sign
package := $(appstore_dir)/$(app_name).tar.gz

cert_dir ?= $(HOME)/.nextcloud/certificates
occ ?= php $(CURDIR)/../../occ

# What the app needs at runtime: everything else (sources, node_modules, configs) stays out
app_files := appinfo css img js l10n lib templates COPYING README.md

.PHONY: all build dev npm-install appstore sign clean distclean

all: build

npm-install:
	npm ci

# js/ and css/ are emptied first: vite adds new hashed chunks on each build but never removes the old ones
build: npm-install
	rm -rf js css
	npm run build

dev: npm-install
	rm -rf js css
	npm run dev

appstore: build
	rm -rf $(appstore_dir) $(sign_dir)
	mkdir -p $(appstore_dir) $(sign_dir)/$(app_name)
	cp -r $(app_files) $(sign_dir)/$(app_name)/
	@if [ -f "$(cert_dir)/$(app_name).key" ] && [ -f "$(cert_dir)/$(app_name).crt" ]; then \
		echo "Signing the app code with $(cert_dir)/$(app_name).crt"; \
		$(occ) integrity:sign-app \
			--privateKey="$(cert_dir)/$(app_name).key" \
			--certificate="$(cert_dir)/$(app_name).crt" \
			--path="$(sign_dir)/$(app_name)"; \
	else \
		echo "No certificate in $(cert_dir): the app code is not signed"; \
	fi
	tar -czf $(package) -C $(sign_dir) $(app_name)
	@echo "Package: $(package) (version $(version))"
	@if [ -f "$(cert_dir)/$(app_name).key" ]; then \
		echo "Signature for the app store upload form:"; \
		openssl dgst -sha512 -sign "$(cert_dir)/$(app_name).key" $(package) | openssl base64; \
	fi

clean:
	rm -rf $(build_dir) js css

distclean: clean
	rm -rf node_modules
