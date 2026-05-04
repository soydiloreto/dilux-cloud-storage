# dilux-cloud-storage — developer task runner.
#
# All PHP-based commands run inside the official `composer:2` Docker
# image. That keeps the host clean of plugin-specific PHP extensions
# (dom, mbstring, xml, xmlwriter, etc.) and matches what GitHub
# Actions runs in CI: same composer, same vendor dir, same lockfile.
#
# WP-CLI (used by the i18n target and wp-env) ships with the
# `wordpress:cli` image, so we wire that one up too.
#
# Usage:
#   make            # = make help
#   make install    # composer install
#   make check      # everything CI runs
#   make release    # check + version-alignment dry-run
#
# Override DOCKER=0 to invoke local binaries instead (only viable on
# hosts that have a full PHP CLI with the required extensions).

SHELL := /bin/bash

# -- Docker plumbing ---------------------------------------------------
# Mount the project read/write at /app, run as the host user so
# composer doesn't leave root-owned files in vendor/.
DOCKER ?= 1
DOCKER_USER := $(shell id -u):$(shell id -g)
DOCKER_RUN := docker run --rm -u $(DOCKER_USER) -v $(CURDIR):/app -w /app
COMPOSER_IMAGE := composer:2
WP_CLI_IMAGE   := wordpress:cli
PHP_IMAGE      := php:8.3-cli

ifeq ($(DOCKER),1)
PHP        := $(DOCKER_RUN) $(COMPOSER_IMAGE) php
COMPOSER   := $(DOCKER_RUN) $(COMPOSER_IMAGE) composer
WP_CLI     := $(DOCKER_RUN) --network host $(WP_CLI_IMAGE)
PSALM_PHP  := $(DOCKER_RUN) $(PHP_IMAGE)
else
PHP        := php
COMPOSER   := composer
WP_CLI     := wp
PSALM_PHP  :=
endif

VENDOR_BIN := ./vendor/bin

# -- Default target ----------------------------------------------------
.DEFAULT_GOAL := help

.PHONY: help
help: ## Show this help.
	@awk 'BEGIN {FS = ":.*##"; printf "\nTargets:\n"} \
	  /^[a-zA-Z0-9_-]+:.*##/ {printf "  \033[1;32m%-18s\033[0m %s\n", $$1, $$2}' \
	  $(MAKEFILE_LIST)
	@echo
	@echo "Override the Docker mode with DOCKER=0 to use local binaries."

# -- Setup -------------------------------------------------------------
.PHONY: install
install: ## Install dev dependencies (composer install).
	$(COMPOSER) install --no-interaction --prefer-dist --no-progress

.PHONY: update
update: ## Update dev dependencies (composer update).
	$(COMPOSER) update --no-interaction --prefer-dist --no-progress

# -- Linting / static analysis ----------------------------------------
.PHONY: lint
lint: ## PHPCS + WordPress Coding Standards.
	$(DOCKER_RUN) $(COMPOSER_IMAGE) $(VENDOR_BIN)/phpcs

.PHONY: lint-fix
lint-fix: ## Auto-fix PHPCS violations where possible.
	$(DOCKER_RUN) $(COMPOSER_IMAGE) $(VENDOR_BIN)/phpcbf

.PHONY: stan
stan: ## PHPStan level 8 (no baseline).
	$(DOCKER_RUN) $(COMPOSER_IMAGE) $(VENDOR_BIN)/phpstan analyse --memory-limit=2G --no-progress

.PHONY: psalm
psalm: ## Psalm taint analysis (XSS / SQLi / RCE).
	$(PSALM_PHP) $(VENDOR_BIN)/psalm --taint-analysis --no-cache --no-progress

.PHONY: i18n
i18n: ## Generate dilux-cloud-storage.pot via WP-CLI.
	mkdir -p build
	$(WP_CLI) wp i18n make-pot . build/dilux-cloud-storage.pot \
	    --slug=dilux-cloud-storage \
	    --domain=dilux-cloud-storage \
	    --exclude=tests,vendor,node_modules,.wordpress-org,assets,docs,build

# -- Tests -------------------------------------------------------------
.PHONY: test
test: test-unit ## Run the unit-test suite (default — fast, no WP needed).

.PHONY: test-unit
test-unit: ## Run only the unit-test suite (no WordPress runtime).
	$(DOCKER_RUN) $(COMPOSER_IMAGE) $(VENDOR_BIN)/phpunit --testsuite unit

.PHONY: test-integration
test-integration: ## Run integration tests against the wp-env stack (must be `make env` first).
	$(DOCKER_RUN) --network host $(COMPOSER_IMAGE) $(VENDOR_BIN)/phpunit --testsuite integration

# -- Aggregate ---------------------------------------------------------
.PHONY: check
check: lint stan psalm test ## Run every quality gate CI runs (lint, stan, psalm, unit tests).
	@echo "✔ All checks passed."

# -- Local dev environment (wp-env) ------------------------------------
.PHONY: env env-up
env: env-up ## Alias of env-up.
env-up: ## Start the local wp-env Docker stack.
	npx wp-env start

.PHONY: env-down
env-down: ## Stop the local wp-env Docker stack.
	npx wp-env stop

.PHONY: env-clean
env-clean: ## Destroy the local wp-env Docker stack and its volumes.
	npx wp-env destroy

# -- Deploy / release --------------------------------------------------
.PHONY: deploy-test
deploy-test: ## Deploy current branch to the mug-website-v2 sibling repo for manual smoke-testing.
	@if [ ! -x /home/pablo/.local/bin/dilux-deploy-to-mug ]; then \
	  echo "deploy-to-mug script not found — install it first."; exit 1; \
	fi
	/home/pablo/.local/bin/dilux-deploy-to-mug

.PHONY: release
release: check ## Pre-release validation: full quality gate + version-alignment dry-run.
	@echo "── version alignment check ─────────────────────────────"
	@PHP_VERSION=$$(grep -E '^\s*\*\s*Version:' dilux-cloud-storage.php | head -1 | sed -E 's/.*Version:\s*//'); \
	 STABLE_TAG=$$(grep -E '^Stable tag:' readme.txt | sed -E 's/Stable tag:\s*//'); \
	 PHP_BASE=$$(echo $$PHP_VERSION | sed -E 's/-(dev|alpha|beta|rc).*$$//'); \
	 echo "  PHP header Version : $$PHP_VERSION"; \
	 echo "  PHP base (no -dev) : $$PHP_BASE"; \
	 echo "  readme Stable tag  : $$STABLE_TAG"; \
	 if [ "$$PHP_BASE" = "$$STABLE_TAG" ]; then \
	   echo "  → match ✔"; \
	 else \
	   echo "  → MISMATCH ✗ (PHP base must equal readme Stable tag at tag time)"; exit 1; \
	 fi
	@echo "✔ Ready to tag."

# -- Cleanup -----------------------------------------------------------
.PHONY: clean
clean: ## Remove caches, build artefacts, and temporary files.
	rm -rf build .phpunit.result.cache .phpunit.cache .phpcs-cache .phpstan .psalm
	@echo "✔ Cleaned."
