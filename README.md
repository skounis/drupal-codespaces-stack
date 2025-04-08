# Drupal CMS + DDEV in GitHub Codespaces

This repository is a **ready-to-fork and run example** that sets up [Drupal CMS](https://www.drupal.org/project/cms) inside a [GitHub Codespace](https://github.com/features/codespaces) using [DDEV](https://ddev.com/). It provides a fully orchestrated developer environment for rapid prototyping and testing of Drupal projects in the cloud — no local setup required.

![DrupalCMSDDEVinGitHub-teaser](https://github.com/user-attachments/assets/89d598fa-dfd3-4316-aa61-3340db01b712)


## Purpose

The goal of this project is not just to install Drupal CMS, but to **demonstrate how DDEV can be used inside a GitHub Codespace** to:

- Run a complete Drupal CMS environment (core + contrib modules/themes)
- Automate setup via a `Makefile`
- Launch the site in a browser from within the Codespace container
- Provide a clean starting point for your own Drupal projects

## What’s Included

- **Drupal CMS 1.1.x**: A curated Drupal distribution with smart defaults
- **DDEV**: Local dev tooling inside the Codespace container
- **Makefile**: Automates setup (cleaning, starting DDEV, Composer install)
- **Devcontainer config**: VS Code & Codespaces setup with useful PHP extensions
- **Post-install hooks**: Automatically fetch and copy the Drupal CMS profile
- **Contrib themes & modules** like `corporateclean`, `webform`, `project_browser`, etc.

## Quick Start

### 1. Fork this repository

Click the **“Use this template”** button or fork it to your GitHub account.

### 2. Open in GitHub Codespaces

From your forked repository:

```bash
Code → Open with Codespaces → Create new Codespace
```

### 3. Wait for setup to complete

The `postCreateCommand` in `.devcontainer/devcontainer.json` will automatically:

- Run `ddev config` to initialize DDEV
- Run `make` to:
  - Clean the Drupal CMS directory
  - Start/restart DDEV
  - Run Composer install
  - Run post-install hooks
  - Launch Drupal in a browser tab

### 4. View the site

Once setup completes, a browser window will open pointing to your running **Drupal CMS** site inside the Codespace container.

## Included Commands

```bash
make           # Full setup: clean, start DDEV, install Drupal CMS, launch browser
make clean     # Clean up everything except composer.json
make ddev      # Start or restart the DDEV container
make setup     # Run Composer install and post-install hooks
make launch    # Open the Drupal CMS instance in the browser
```

## Requirements

> You don’t need to install anything locally to use this — just a GitHub account with Codespaces enabled.

If running locally (optional):
- Docker
- DDEV
- GNU Make

## Extending This Template

This is a great starting point for your own Drupal projects using Codespaces. You can:

- Add your own modules/themes in `composer.json`
- Modify the custom profile
- Change the default site installation options
- Add database seeders or migrations

## License

[MIT License](LICENSE)
