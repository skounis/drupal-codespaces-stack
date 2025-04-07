# AI

AI (artificial intelligence) integration for Drupal - a unified framework for
integrating various AI models and services into your Drupal site.

## Dependencies

- Drupal ^10.3 || ^11
- [Key module](https://www.drupal.org/project/key)
- Requires at least one AI Provider to be installed and configured.

## Getting Started

1. Enable the AI module.
2. Choose and enable an AI Provider module:
   1. Choose the AI Provider for your chosen LLM/AI service from the list at [https://project.pages.drupalcode.org/ai/providers/matris/](https://project.pages.drupalcode.org/ai/providers/matris/)
   2. Download the AI Provider and install and configure according to its
      instructions.
3. Once your AI Provider(s) is configured, the AI module will select appropriate
   defaults for the various [Operation Types](https://project.pages.drupalcode.org/ai/developers/base_calls/#the-operation-types-and-how-to-use-them).
   You can make your own selections by visiting `/admin/config/ai/settings` and
   updating the settings manually.
4. Enable your desired submodules for specific functionality.

## Develop for it

Check the [developers guide](developers/developer_information.md) for
information on how to develop for the AI module.

## Documentation

This documentation is generated using MKDocs from the source files located in
the `docs/` directory. To build the docs locally:

1. Install MkDocs: `pip install mkdocs mkdocs-material`
2. Run `mkdocs serve` in the project root
3. Open `http://localhost:8000` in your browser
