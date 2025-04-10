# Variables
CMS_DIR := cms
COMPOSER_FILE := $(CMS_DIR)/composer.json

.PHONY: clean ddev setup launch

# Default target to run all steps
all: clean ddev setup launch

# Clean the cms/ folder but keep composer.json
clean:
	@echo "Cleaning the $(CMS_DIR) folder but keeping $(COMPOSER_FILE)..."
	@find $(CMS_DIR) -mindepth 1 -depth -ignore_readdir_race ! -name $(notdir $(COMPOSER_FILE)) -exec rm -rf {} +

# Start or restart ddev
ddev:
	@echo "Checking ddev status..."
	@if ddev describe >/dev/null 2>&1; then \
		echo "ddev is already running. Restarting..."; \
		ddev restart; \
	else \
		echo "ddev is not running. Starting..."; \
		ddev start; \
	fi

start:
	@echo "Starting ddev..."
	@ddev start

restart:
	@echo "Restarting ddev..."
	@ddev restart

# Restart ddev and purge all saved data
purge:
	@echo "Purging ddev project and all associated data..."
	@ddev delete -Oy
	@echo "Starting ddev after purge..."
	@ddev start


# Run setup commands inside the ddev container
setup:
	@echo "Running setup commands inside the ddev container..."
	@echo "Commands to be executed:"
	@echo "1. cd $(CMS_DIR)"
	@echo "2. composer install"
	@echo "3. composer run-script post-install-cmd"
	@ddev exec "cd $(CMS_DIR) && composer install && composer run-script post-install-cmd"


# Launch the browser using ddev
launch:
	@echo "Launching the browser with ddev..."
	@ddev launch

# Prepare the CMS for development
devenv: 
	@ddev exec "cd $(CMS_DIR) && composer require 'drupal/default_content:^2.0@alpha'"
	@ddev exec "cd $(CMS_DIR) && ./vendor/bin/drush en block_content -y"
	@ddev exec "cd $(CMS_DIR) && ./vendor/bin/drush en default_content -y"

#
# Work with recipes
#
# make export-block BLOCK_ID=1 BLOCK_NAME=drupal_cms_olivero_about RECIPE=extra_footer
export-block:
	@ddev exec "cd $(CMS_DIR) && ./vendor/bin/drush cex -y"
	@scripts/export_block.sh $(BLOCK_ID) $(BLOCK_NAME) $(RECIPE)

export-node:
	@ddev exec "cd $(CMS_DIR) && ./vendor/bin/drush dce node 5 > test.yml -y"

dcex:
	@ddev exec "cd $(CMS_DIR) && ./vendor/bin/drush cex -y"


# How to capture a block
# 1. Find the block ID using the following command: 
#    make list-block`
# 2. Use the block ID and name to run the export command: 
#    make export-block BLOCK_ID=1 BLOCK_NAME=drupal_cms_olivero_about RECIPE=extra_footer

# List all custom blocks
list-block:
	@ddev exec "cd cms && ./vendor/bin/drush php:script ../../scripts/list_blocks.php"

# List and capture all custom blocks configuration files
# Usage:
# make export-block-type TYPE=basic_block RECIPE=extra_footer
# export-block-type:
# 	@echo "Listing config files for block_content type: $(TYPE)"
# 	@find cms/web/sites/default/files/sync -name "*$(TYPE)*.yml"
# 	@echo "Copying to recipes/$(RECIPE)/config"
# 	@mkdir -p recipes/$(RECIPE)/config
# 	@find cms/web/sites/default/files/sync -name "*$(TYPE)*.yml" -exec cp {} recipes/$(RECIPE)/config/ \;
# 	@echo "✅ Done: all config files for '$(TYPE)' copied to recipes/$(RECIPE)/config"


# Apply a recipe by name (default: extra_footer)
# Usage: make apply-recipe RECIPE=extra_footer
apply-recipe:
	@echo "Applying recipe: $(RECIPE)"
	# Path to recipes is relative to the `web` folder
	@ddev exec "cd cms && ./vendor/bin/drush recipe ../../recipes/$(RECIPE)"

# Shortcut for the default recipe
apply-recipes:
	@$(MAKE) apply-recipe RECIPE=extra_footer
	@$(MAKE) apply-recipe RECIPE=extra_project