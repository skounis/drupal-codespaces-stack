#!/bin/bash

# Usage: ./scripts/export_block_full.sh <block_id> <block_machine_name> <recipe>
# Example: ./scripts/export_block_full.sh 2 olivero_aboutus extra_footer

BLOCK_ID="$1"
BLOCK_NAME="$2"
RECIPE="${3:-extra_footer}"
CONTENT_DIR="recipes/${RECIPE}/default_content"
CONFIG_DIR="recipes/${RECIPE}/config"
CONTENT_FILENAME="block_content.block_content.${BLOCK_NAME}.yml"
CONFIG_FILENAME="block.block.${BLOCK_NAME}.yml"
EXPORT_DIR="cms/sites/default/files/sync"

mkdir -p "$CONTENT_DIR"
mkdir -p "$CONFIG_DIR"

echo "Exporting block content via DDEV exec..."
ddev exec "cd cms && ./vendor/bin/drush default-content:export block_content $BLOCK_ID" > "${CONTENT_DIR}/${CONTENT_FILENAME}"

if [ $? -eq 0 ]; then
  echo "✅ Block content exported to ${CONTENT_DIR}/${CONTENT_FILENAME}"
else
  echo "❌ Failed to export block content"
  exit 1
fi

# Copy block config file (from host filesystem)
if [ -f "${EXPORT_DIR}/${CONFIG_FILENAME}" ]; then
  cp "${EXPORT_DIR}/${CONFIG_FILENAME}" "${CONFIG_DIR}/${CONFIG_FILENAME}"
  echo "✅ Block placement config copied to ${CONFIG_DIR}/${CONFIG_FILENAME}"
else
  echo "⚠️  Config file not found: ${EXPORT_DIR}/${CONFIG_FILENAME}"
fi












# Find block content file
# BLOCK_CONTENT=$(grep -irl "$SEARCH" "$EXPORT_DIR/block_content.block_content."*)
# BLOCK_ID=$(basename "$BLOCK_CONTENT" .yml | sed 's/block_content\.block_content\.//')

