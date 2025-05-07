CMS_DIR := .

# List nodes for a given content type
list-nodes:
	@read -p "Enter the content type machine name: " TYPE; \
	echo "Fetching nodes of type '$$TYPE'..."; \
	$(CMS_DIR)/vendor/bin/drush sql:query "\
		SELECT nid, title FROM node_field_data \
		WHERE type = '$$TYPE' ORDER BY nid;" | column -t; \
	ids=$$($(CMS_DIR)/vendor/bin/drush sql:query --extra=--skip-column-names "\
		SELECT nid FROM node_field_data \
		WHERE type = '$$TYPE' ORDER BY nid;" | paste -sd, -); \
	ids=$$(echo $$ids | sed 's/,$$//'); \
	echo "Node IDs (comma-separated): $$ids"

# Export a node by its ID
export-node:
	@read -p "Enter the node ID to export (or Ctrl+C to cancel): " NODE_ID; \
	if [ -z "$$NODE_ID" ]; then \
		echo "No ID provided. Aborting."; \
		exit 1; \
	fi; \
	echo "Exporting node $$NODE_ID..."; \
	$(CMS_DIR)/vendor/bin/drush dce node $$NODE_ID > node-$$NODE_ID.yml -y; \
	echo "Node exported to node-$$NODE_ID.yml"


export-nodes:
	@read -p "Enter the content type (bundle) machine name: " BUNDLE; \
	read -p "Enter comma-separated node IDs to export (e.g., 1,4,5,6): " IDS; \
	if [ -z "$$BUNDLE" ] || [ -z "$$IDS" ]; then \
		echo "Content type or node IDs not provided. Aborting."; \
		exit 1; \
	fi; \
	echo "$$IDS" | tr ',' '\n' | while read NODE_ID; do \
		PADDED_ID=`printf "%02d" $$NODE_ID`; \
		echo "Exporting node $$NODE_ID as node-$$BUNDLE-$$PADDED_ID.yml..."; \
		$(CMS_DIR)/vendor/bin/drush dce node $$NODE_ID > node-$$BUNDLE-$$PADDED_ID.yml -y; \
	done; \
	echo "Export complete."


list-media:
	@echo "Fetching real media image filenames..."; \
	$(CMS_DIR)/vendor/bin/drush sql:query "\
		SELECT mfd.mid, m.uuid, mfd.bundle, f.filename \
		FROM media_field_data mfd \
		JOIN media m ON mfd.mid = m.mid \
		JOIN media__field_media_image img ON mfd.mid = img.entity_id \
		JOIN file_managed f ON img.field_media_image_target_id = f.fid \
		ORDER BY mfd.mid;" > media.txt; \
	cat media.txt | column -t


export-media-all:
	@echo "Exporting all media listed in media.txt..."; \
	while IFS= read -r line; do \
		MID=$$(echo "$$line" | awk '{print $$1}'); \
		UUID=$$(echo "$$line" | awk '{print $$2}'); \
		FILENAME=$$(echo "$$line" | awk '{for(i=4;i<=NF;++i) printf "%s%s", $$i, (i<NF?" ":""); printf "\n"}'); \
		if [ -n "$$MID" ] && [ -n "$$UUID" ] && [ -n "$$FILENAME" ]; then \
			PADDED_ID=$$(printf "%02d" $$MID); \
			CLEAN_FILENAME=$$(echo "$$FILENAME" | tr -d '\r' | tr ' ' '-' | tr -cd '[:alnum:]._-'); \
#			OUTFILE="media-$$PADDED_ID-$$UUID-$$CLEAN_FILENAME.yml"; \
			OUTFILE="media-$$PADDED_ID-$$UUID.yml"; \
			echo "Exporting media ID $$MID to $$OUTFILE..."; \
			if $(CMS_DIR)/vendor/bin/drush dce media $$MID -y > "$$OUTFILE"; then \
				echo "✔ Exported $$OUTFILE"; \
			else \
				echo "✘ Failed to export media ID $$MID" >&2; \
			fi; \
		fi; \
	done < media.txt; \
	echo "All media exports complete."


list-files:
	@echo "Listing file entities used in media images..."; \
	$(CMS_DIR)/vendor/bin/drush sql:query "\
		SELECT f.fid, f.uuid, f.filename, f.uri \
		FROM media_field_data mfd \
		JOIN media__field_media_image img ON mfd.mid = img.entity_id \
		JOIN file_managed f ON img.field_media_image_target_id = f.fid \
		ORDER BY f.fid;" > files.txt; \
	cat files.txt | column -t


export-files:
	@echo "Exporting file entities from files.txt..."; \
	while read -r line; do \
		FID=$$(echo "$$line" | awk '{print $$1}'); \
		FILENAME=$$(echo "$$line" | cut -d' ' -f3-); \
		if [ -n "$$FID" ] && [ -n "$$FILENAME" ]; then \
			CLEAN_NAME=$$(echo "$$FILENAME" | tr -d '\r' | tr ' ' '-' | tr -cd '[:alnum:]._-'); \
#			OUTFILE="file-$$FID--$$CLEAN_NAME.yml"; \
			OUTFILE="file-$$FID.yml"; \
			echo "Exporting file $$FID to $$OUTFILE..."; \
			$(CMS_DIR)/vendor/bin/drush dce file $$FID -y > "$$OUTFILE"; \
		fi; \
	done < files.txt; \
	echo "All file exports complete."
