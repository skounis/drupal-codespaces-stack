#!/bin/bash

# Usage: ./scripts/export_block_full.sh <block_id> <block_machine_name> <recipe>
# Example: ./scripts/export_block_full.sh 2 olivero_aboutus extra_footer

BLOCK_ID="$1"
BLOCK_NAME="$2"
RECIPE="${3:-extra_footer}"

CONFIG_EXPORT_DIR="cms/web/sites/default/files/sync"
CONFIG_DIR="recipes/${RECIPE}/config"
CONTENT_DIR="recipes/${RECIPE}/content/block_content"
CONFIG_FILENAME="block.block.${BLOCK_NAME}.yml"
CONTENT_FILENAME="block_content.block_content.${BLOCK_NAME}.yml"

mkdir -p "$CONFIG_DIR"
mkdir -p "$CONTENT_DIR"

echo "=== Step 1: Copying block placement config ==="
if [ -f "${CONFIG_EXPORT_DIR}/${CONFIG_FILENAME}" ]; then
  cp "${CONFIG_EXPORT_DIR}/${CONFIG_FILENAME}" "${CONFIG_DIR}/${CONFIG_FILENAME}"
  echo "✅ Copied config to ${CONFIG_DIR}/${CONFIG_FILENAME}"
else
  echo "⚠️  Config file not found: ${CONFIG_EXPORT_DIR}/${CONFIG_FILENAME}"
  echo "➡️  Make sure you've run 'ddev drush cex' inside the container after placing the block."
fi

echo "=== Step 2: Exporting block content via DDEV ==="
ddev exec "cd cms && ./vendor/bin/drush default-content:export block_content $BLOCK_ID" > "${CONTENT_DIR}/${CONTENT_FILENAME}"

if [ $? -eq 0 ]; then
  echo "✅ Exported block content to ${CONTENT_DIR}/${CONTENT_FILENAME}"
else
  echo "❌ Failed to export block content for block_content ID ${BLOCK_ID}"
  exit 1
fi

echo "✅ All done: block config + content exported for '${BLOCK_NAME}' into recipe '${RECIPE}'"












# Find block content file
# BLOCK_CONTENT=$(grep -irl "$SEARCH" "$EXPORT_DIR/block_content.block_content."*)
# BLOCK_ID=$(basename "$BLOCK_CONTENT" .yml | sed 's/block_content\.block_content\.//')

