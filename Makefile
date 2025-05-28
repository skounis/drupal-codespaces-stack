# ENV Variables
include .env
export

# Variables
CMS_DIR := cms
COMPOSER_FILE := $(CMS_DIR)/composer.json
USE_DDEV ?= true

ifeq ($(USE_DDEV),false)
	EXEC := sh -c
else
	EXEC := ddev exec
endif

.PHONY: clean ddev start restart purge setup launch \
	install stock-recipes prepare full-stock-install full-extra-install full-install \
	devenv themes login \
	export-block export-node dcex list-block apply-recipe apply-recipes

# Default target to run all steps
all: clean ddev full-install launch


help:
	@echo ""
	@echo "=== Drupal CMS Makefile Help ==="
	@echo ""
	@echo "Quick Start:"
	@echo "  make full-install             # Install everything using DDEV (default)"
	@echo "  make full-install USE_DDEV=false   # Install on local Apache/PHP/Composer stack"
	@echo ""
	@echo "Main targets:"
	@echo "  all                  Run clean, setup, full-install and launch"
	@echo "  full-install         Purge and fully install Drupal CMS with recipes and themes"
	@echo "  full-stock-install   Install Drupal with stock recipes"
	@echo "  full-extra-install   Apply extra UX recipes and enhancements"
	@echo "  setup                Install PHP dependencies and run post-install scripts"
	@echo "  launch               Open the site in your browser (DDEV only)"
	@echo "  login                Get one-time login link via drush"
	@echo ""
	@echo "Development & Config:"
	@echo "  prepare              Enable dev modules and themes"
	@echo "  devenv               Enable default_content and block_content"
	@echo "  themes               Enable basecore and corporateclean themes"
	@echo "  install              Run drush site:install with default parameters"
	@echo "  stock-recipes        Apply default content recipes (blog, news, etc.)"
	@echo ""
	@echo "System Management:"
	@echo "  clean                Remove all files from CMS_DIR except composer.json"
	@echo "  ddev                 Start or restart DDEV environment"
	@echo "  purge                Delete DDEV environment and restart"
	@echo "  start, restart       Manage DDEV instance manually"
	@echo "  doctor               Check if required tools are installed (based on USE_DDEV)"
	@echo ""
	@echo "Content Export:"
	@echo "  dcex                 Export full config and copy to ./sync"
	@echo "  export-block         Export a single block (requires BLOCK_ID, BLOCK_NAME, RECIPE)"
	@echo "  export-node          Export a specific node to YAML"
	@echo ""
	@echo "Recipes:"
	@echo "  apply-recipe         Apply a recipe (RECIPE=name required)"
	@echo "  apply-recipes        Apply all default extra UX recipes"
	@echo ""
	@echo "Options:"
	@echo "  USE_DDEV=true|false  Use DDEV or run locally (default is true)"
	@echo ""

doctor:
	@echo "Running environment checks..."
ifeq ($(USE_DDEV),false)
	@echo "USE_DDEV=false — Checking for local tools..."
	@command -v php >/dev/null 2>&1 || { echo >&2 "❌ PHP is not installed."; exit 1; }
	@command -v composer >/dev/null 2>&1 || { echo >&2 "❌ Composer is not installed."; exit 1; }
	@command -v drush >/dev/null 2>&1 || { echo >&2 "❌ Drush is not installed."; exit 1; }
	@echo "✅ All required local tools are installed."
else
	@echo "USE_DDEV=true — Checking for DDEV..."
	@command -v ddev >/dev/null 2>&1 || { echo >&2 "❌ DDEV is not installed."; exit 1; }
	@ddev version >/dev/null 2>&1 || { echo >&2 "❌ DDEV is not configured properly."; exit 1; }
	@echo "✅ DDEV is installed and working."
endif




# Clean the cms/ folder but keep composer.json
clean:
	@echo "Cleaning the $(CMS_DIR) folder but keeping $(COMPOSER_FILE)..."
	@find $(CMS_DIR) -mindepth 1 -depth -ignore_readdir_race ! -name $(notdir $(COMPOSER_FILE)) -exec rm -rf {} +

# Start or restart ddev
ddev:
	@echo "Checking ddev status..."
	@if [ "$(USE_DDEV)" = "true" ]; then \
		if ddev describe >/dev/null 2>&1; then \
			echo "ddev is already running. Restarting..."; \
			ddev restart; \
		else \
			echo "ddev is not running. Starting..."; \
			ddev start; \
		fi; \
	else \
		echo "Skipping ddev start (USE_DDEV=false)"; \
	fi

start:
	@echo "Starting ddev..."
	@if [ "$(USE_DDEV)" = "true" ]; then ddev start; else echo "Skipping start (USE_DDEV=false)"; fi

restart:
	@echo "Restarting ddev..."
	@if [ "$(USE_DDEV)" = "true" ]; then ddev restart; else echo "Skipping restart (USE_DDEV=false)"; fi

# Restart ddev and purge all saved data
purge:
	@echo "Purging ddev project and all associated data..."
	@if [ "$(USE_DDEV)" = "true" ]; then ddev delete -Oy && ddev start; else echo "Skipping purge (USE_DDEV=false)"; fi

# Run setup commands inside the container or local
setup:
	@echo "Running setup commands in $(CMS_DIR)..."
	@echo "Commands to be executed:"
	@echo "1. cd $(CMS_DIR)"
	@echo "2. composer install"
	@echo "3. composer run-script post-install-cmd"
	@$(EXEC) "cd $(CMS_DIR) && composer install && composer run-script post-install-cmd"

# Launch the browser using ddev
launch:
	@echo "Launching the browser with ddev..."
	@if [ "$(USE_DDEV)" = "true" ]; then ddev launch; else echo "Open your browser to http://localhost manually."; fi

# Install the CMS using the `drupal_cms_installer`
install:
ifeq ($(USE_DDEV),false)
	@$(EXEC) "cd $(CMS_DIR) && ./vendor/bin/drush site:install \
		drupal_cms_installer \
		--db-url='$(DB_URL)' \
		--account-mail=admin@example.com \
		--account-name=admin \
		--account-pass=admin \
		--site-name='My Site' \
		--yes"
else
	@$(EXEC) "cd $(CMS_DIR) && ./vendor/bin/drush site:install \
		drupal_cms_installer \
		--account-mail=admin@example.com \
		--account-name=admin \
		--account-pass=admin \
		--site-name='My Site' \
		--yes"
endif

# Apply stock recipes
stock-recipes:
	@$(EXEC) "cd $(CMS_DIR) && ./vendor/bin/drush recipe ../recipes/drupal_cms_blog"
	@$(EXEC) "cd $(CMS_DIR) && ./vendor/bin/drush recipe ../recipes/drupal_cms_events"
	@$(EXEC) "cd $(CMS_DIR) && ./vendor/bin/drush recipe ../recipes/drupal_cms_news"
	@$(EXEC) "cd $(CMS_DIR) && ./vendor/bin/drush recipe ../recipes/drupal_cms_case_study"
	@$(EXEC) "cd $(CMS_DIR) && ./vendor/bin/drush recipe ../recipes/drupal_cms_person"
	@$(EXEC) "cd $(CMS_DIR) && ./vendor/bin/drush recipe ../recipes/drupal_cms_project"
	@$(EXEC) "cd $(CMS_DIR) && ./vendor/bin/drush cr"

# Prepare dev environment and enable themes
prepare:
	@$(MAKE) devenv USE_DDEV=$(USE_DDEV)
	@$(MAKE) themes USE_DDEV=$(USE_DDEV)

# Install the stock Drupal CMS
full-stock-install: 
	@$(MAKE) install USE_DDEV=$(USE_DDEV)
	@$(MAKE) stock-recipes USE_DDEV=$(USE_DDEV)

# Improve the CMS with Extra UX
full-extra-install:
	@$(MAKE) themes USE_DDEV=$(USE_DDEV)
	@$(MAKE) devenv USE_DDEV=$(USE_DDEV)
	@$(MAKE) apply-recipes USE_DDEV=$(USE_DDEV)

# Install stock Drupal CMS with all the Extra UX
full-install:
	@$(MAKE) clean USE_DDEV=$(USE_DDEV)
	@$(MAKE) purge USE_DDEV=$(USE_DDEV)
	@$(MAKE) setup USE_DDEV=$(USE_DDEV)
	@$(MAKE) full-stock-install USE_DDEV=$(USE_DDEV)
	@$(MAKE) full-extra-install USE_DDEV=$(USE_DDEV)

# Prepare the CMS for development
devenv: 
	@$(EXEC) "cd $(CMS_DIR) && composer require 'drupal/default_content:^2.0@alpha'"
	@$(EXEC) "cd $(CMS_DIR) && ./vendor/bin/drush en block_content -y"
	@$(EXEC) "cd $(CMS_DIR) && ./vendor/bin/drush en default_content -y"
	@$(EXEC) "cd $(CMS_DIR) && ./vendor/bin/drush en simple_styleguide -y"

# Enable contrib themes and set default
themes:
	@$(EXEC) "cd $(CMS_DIR) && ./vendor/bin/drush then basecore -y"
	@$(EXEC) "cd $(CMS_DIR) && ./vendor/bin/drush then corporateclean -y"
	@$(EXEC) "cd $(CMS_DIR) && ./vendor/bin/drush config:set system.theme default corporateclean -y"
	@$(EXEC) "cd $(CMS_DIR) && ./vendor/bin/drush cr"

# Shortcut: log into the site
login:
	@$(EXEC) "cd $(CMS_DIR) && ./vendor/bin/drush uli"

# Export block content
export-block:
	@$(EXEC) "cd $(CMS_DIR) && ./vendor/bin/drush cex -y"
	@scripts/export_block.sh $(BLOCK_ID) $(BLOCK_NAME) $(RECIPE)

# export-node:
# 	@$(EXEC) "cd $(CMS_DIR) && ./vendor/bin/drush dce node 15 > 15.yml -y"

export-node:
	@read -p "Enter the node ID to export (or Ctrl+C to cancel): " NODE_ID; \
	if [ -z "$$NODE_ID" ]; then \
		echo "No ID provided. Aborting."; \
		exit 1; \
	fi; \
	$(EXEC) "cd $(CMS_DIR) && ./vendor/bin/drush dce node $$NODE_ID > $$NODE_ID.yml -y"

# list-menu:
# 	@read -p "Enter the menu machine name: " menu; \
# 	$(EXEC) "cd $(CMS_DIR) && ./vendor/bin/drush sql:query 'SELECT id, title FROM menu_link_content_data  WHERE menu_name = '\''$$menu'\'''"

list-menu:
	@read -p "Enter the menu machine name: " menu; \
	echo "Fetching menu items from '$$menu'..."; \
	$(EXEC) "cd $(CMS_DIR) && ./vendor/bin/drush sql:query 'SELECT id, title FROM menu_link_content_data WHERE menu_name = '\''$$menu'\'''"; \
	ids=$$($(EXEC) "cd $(CMS_DIR) && ./vendor/bin/drush sql:query --extra=--skip-column-names 'SELECT id FROM menu_link_content_data WHERE menu_name = '\''$$menu'\'''" | paste -sd, -); \
	ids=$$(echo $$ids | sed 's/,$$//'); \
	echo "IDs (comma-separated): $$ids"

export-menu-all:
	$(EXEC) "cd $(CMS_DIR) && ./vendor/bin/drush dcer menu_link_content --folder=../../tmp"

export-menu:
	@read -p "Enter comma-separated menu_link_content IDs to export: " ids; \
	for id in $$(echo $$ids | tr ',' ' '); do \
		echo "Exporting menu_link_content $$id..."; \
		$(EXEC) "cd $(CMS_DIR) && ./vendor/bin/drush dcer menu_link_content $$id --folder=../../tmp"; \
	done

# Export config and copy locally
dcex:
	@rm -rf ./sync
	@mkdir -p ./sync
	@echo "Exporting configuration from Drupal..."
	@$(EXEC) "cd $(CMS_DIR) && ./vendor/bin/drush cex -y"
	@echo "Copying sync directory to ./sync..."
	@cp cms/web/sites/default/files/sync . -R
	@cp -R cms/web/sites/default/files/sync/* ./sync || echo "Sync directory does not exist, skipping copy."

# List all custom blocks
list-block:
	@$(EXEC) "cd cms && ./vendor/bin/drush php:script ../../scripts/list_blocks.php"

# Apply a recipe by name (default: extra_footer)
# Usage: make apply-recipe RECIPE=extra_footer
apply-recipe:
	@echo "Applying recipe: $(RECIPE)"
	@$(EXEC) "cd cms && ./vendor/bin/drush recipe ../../recipes/$(RECIPE)"
	@$(EXEC) "cd $(CMS_DIR) && ./vendor/bin/drush cr"

# Shortcut for the default recipe
apply-recipes:
#	@$(MAKE) apply-recipe RECIPE=extra_form USE_DDEV=$(USE_DDEV)
	@$(MAKE) apply-recipe RECIPE=extra_block USE_DDEV=$(USE_DDEV)
	@$(MAKE) apply-recipe RECIPE=extra_project USE_DDEV=$(USE_DDEV)
	@$(MAKE) apply-recipe RECIPE=extra_page USE_DDEV=$(USE_DDEV)
	@$(MAKE) apply-recipe RECIPE=extra_landing_page USE_DDEV=$(USE_DDEV)
	@$(MAKE) apply-recipe RECIPE=extra_content USE_DDEV=$(USE_DDEV)
	@$(MAKE) apply-recipe RECIPE=extra_styleguide USE_DDEV=$(USE_DDEV)
	@$(EXEC) "cd cms && ./vendor/bin/drush config:set system.site page.front /landing-page -y"
	@$(EXEC) "cd cms && ./vendor/bin/drush config:set system.site name 'Corporate Clean' -y"
	# Exclude landing pages from latest
	@$(EXEC) "cd cms && ./vendor/bin/drush config:import --partial --source=../../recipes/extra_landing_page/config/sync -y"
	# Disable the default Home menu link
	# ./vendor/bin/drush php:eval "foreach (\Drupal::entityTypeManager()->getStorage('menu_link_content')->loadMultiple() as \$link) { if (\$link->get('link')->first()->getUrl()->toUriString() === 'route:<front>') { \$link->set('enabled', FALSE)->save(); echo 'Disabled ID: ' . \$link->id() . PHP_EOL; } }"

# -----------------------------------------------------------------------------
# Recipe Sync Tasks
#
# Context:
# This monorepo (e.g. /home/skounis/drupal/drupal-codespaces) is used to develop
# and test all Drupal recipes in a unified environment. However, some recipes
# (e.g. extra_landing_page) are meant to be published separately through their
# own Git repositories (e.g. /home/skounis/drupal/recipes/extra_landing_page).
#
# Current constraint: We cannot clone or check out the standalone repositories
# into the monorepo workspace. As a result, editing happens *only in the monorepo*,
# and changes are then propagated (one-way sync) to the standalone recipe repos.
#
# Assumptions:
# - All edits occur in ./recipes/ inside the monorepo.
# - Each corresponding published repo already exists under $(RECIPE_REPOS_BASE).
# - Sync direction is only monorepo → standalone (never the reverse).
# - Optional: Each standalone repo has its own git version control.
#
# Usage:
#   make sync-recipe RECIPE=extra_landing_page   # Sync single recipe
#   make sync-all-recipes                        # Sync all recipes to their repos
# -----------------------------------------------------------------------------

# Path to the directory containing the standalone recipe repos
RECIPE_REPOS_BASE := /home/skounis/drupal

# Sync a recipe to its standalone repo
# Usage: make sync-recipe RECIPE=extra_landing_page
sync-recipe:
	@if [ -z "$(RECIPE)" ]; then \
		echo "❌ Please provide a RECIPE name (e.g. make sync-recipe RECIPE=extra_landing_page)"; \
		exit 1; \
	fi
	@echo "Syncing recipe '$(RECIPE)' from monorepo to standalone repo..."
	@rsync -av --delete --exclude='.git' --exclude='.DS_Store' \
		./recipes/$(RECIPE)/ $(RECIPE_REPOS_BASE)/$(RECIPE)/
	@echo "✅ Sync complete: ./recipes/$(RECIPE) → $(RECIPE_REPOS_BASE)/$(RECIPE)"

# Sync all recipes to their respective standalone repos (if destination exists)
sync-all-recipes:
	@echo "Syncing all recipes from monorepo to standalone repos..."
	@for dir in ./recipes/*/; do \
		name=$$(basename $$dir); \
		dest="$(RECIPE_REPOS_BASE)/$$name"; \
		if [ -d "$$dest" ]; then \
			echo "→ Syncing $$name..."; \
			rsync -av --delete --exclude='.git' --exclude='.DS_Store' \
				"./recipes/$$name/" "$$dest/"; \
		else \
			echo "⚠️ Destination repo $$dest not found. Skipping."; \
		fi \
	done
	@echo "✅ All recipes synced."

