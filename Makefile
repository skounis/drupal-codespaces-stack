# Variables
CMS_DIR := cms
COMPOSER_FILE := $(CMS_DIR)/composer.json

.PHONY: clean start restart instructions

# Default target to run all steps
all: clean start instructions

# Clean the cms/ folder but keep composer.json
clean:
	@echo "Cleaning the $(CMS_DIR) folder but keeping $(COMPOSER_FILE)..."
	@find $(CMS_DIR) -mindepth 1 ! -name $(notdir $(COMPOSER_FILE)) -exec rm -rf {} +

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

# Display instructions
instructions:
	@echo "Run the following commands to complete the setup:"
	@echo "1. ddev ssh"
	@echo "2. cd $(CMS_DIR)"
	@echo "3. composer install"
	@echo "4. composer run-script post-install-cmd"