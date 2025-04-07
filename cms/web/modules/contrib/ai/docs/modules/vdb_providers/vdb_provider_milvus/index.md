# Milvus Vector Database Provider
## What is the Milvus Vector Database Provider?
This Drupal module provides integration with Milvus (local) and Zilliz (managed
cloud). It includes features for inserting, deleting, and managing vector data
for use with the AI Core module and its sub-modules. For more information about
Milvus, please see [their website](https://milvus.io/).

## Dependencies
1. An enabled and configured AI Core module
2. A correctly set up Milvus local or Zilliz cloud

This Provider is primarily for use with the [AI Search module](https://project.pages.drupalcode.org/ai/modules/ai_search/),
so once it has been configured you may need to follow the configuration guide
for that module if you are using it.

## Installation
1. Enable the module.
2. Configure the connection to the database at /admin/config/ai/vdb_providers/milvus
3. Configure your AI-related modules to use the provider as required.

### Using with DDEV.
1. Copy the `ddev-example.docker-compose.milvus.yaml` to your `.ddev` folder.
   1. Assuming your project uses the `web` docroot, you can use the below 
      command: 
      ```
      cp web/modules/contrib/ai/vdb_providers/vdb_provider_milvus/docs/docker-compose-examples/ddev-example.docker-compose.milvus.yaml .ddev/docker-compose.milvus.yaml
      ```
2. Run `ddev restart` 
3. Access your Milvus UI at `https://{project}.ddev.site:8521`
4. Set up your Milvus Vector Database Plugin configuration to use:
   1. Host: `http://milvus`
   2. Port: `19530`