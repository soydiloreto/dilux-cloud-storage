# Dilux Cloud Storage

> Offload WordPress media to cloud object storage (Azure Blob, Dilux One Cloud) with a transparent PHP stream wrapper — no URL rewriting, no database migration.

[![License: GPL v2+](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)](LICENSE)

## What is this?

Dilux Cloud Storage is a WordPress plugin that moves your media library to cloud object storage and serves files directly from the cloud — without breaking the Media Library UI, third-party plugins, or existing post content.

It uses a **PHP stream wrapper** to intercept every read and write to `/wp-content/uploads/`, so WordPress, WooCommerce, page builders, image editors, and any plugin that calls standard filesystem functions (`fopen`, `file_get_contents`, `unlink`, etc.) keep working unchanged.

## For end users

If you just want to **install and use** the plugin on your WordPress site, get it from the official directory:

🔗 **[wordpress.org/plugins/dilux-cloud-storage](https://wordpress.org/plugins/dilux-cloud-storage/)** *(coming soon — pending wp.org review)*

User-facing documentation (features, installation, configuration, FAQ) lives in [`readme.txt`](readme.txt) — that's the version rendered on the wp.org plugin page.

## For developers

This README and the rest of this repository are aimed at developers who want to **contribute, fork, or run the plugin from source**.

## Development setup

The repository ships with a [`@wordpress/env`](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/) configuration so any contributor can spin up a fresh WordPress install with the plugin pre-mounted in two commands.

### Prerequisites

- **Docker** (Docker Desktop on macOS/Windows, or Docker Engine on Linux) — running.
- **Node.js 18+** and **npm**.

### Start the environment

```bash
git clone https://github.com/soydiloreto/dilux-cloud-storage.git
cd dilux-cloud-storage
npm install
npm run env:start
```

When it finishes, open **http://localhost:8888**. Log in with `admin` / `password`. The plugin is already mounted in `wp-content/plugins/dilux-cloud-storage/` — just activate it from the **Plugins** screen.

### Day-to-day commands

| Command | What it does |
|---------|--------------|
| `npm run env:start` | Start the local WordPress + MySQL containers. |
| `npm run env:stop` | Stop the containers (preserves the database). |
| `npm run env:destroy` | Tear everything down and wipe the local database. Use this if your dev install gets into a bad state. |
| `npm run env:reset` | `destroy` followed by `start` — fresh install. |
| `npm run env:cli -- <command>` | Run a `wp-cli` command inside the container. Example: `npm run env:cli -- plugin list`. |
| `npm run env:logs` | Tail the WordPress container logs (useful when `WP_DEBUG_LOG` writes errors). |

### Configuration

The default environment is configured in [`.wp-env.json`](.wp-env.json):

- **WordPress core**: latest stable.
- **PHP version**: 8.2.
- **Plugin**: this repository, auto-mounted.
- **Debug mode**: `WP_DEBUG`, `WP_DEBUG_LOG`, and `SCRIPT_DEBUG` enabled. `WP_DEBUG_DISPLAY` is off so errors don't render in pages — they go to `wp-content/debug.log` instead.

To override any of these on your local machine without committing the changes, create a `.wp-env.override.json` file (it's already in `.gitignore`). See the [`@wordpress/env` documentation](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/) for the full schema.

### Manual install (alternative)

If you prefer your own WordPress setup, you can clone this repo directly into `wp-content/plugins/dilux-cloud-storage/` of your existing WordPress install. The plugin has no build step — it runs straight from source.

## Architecture overview

| Path | What it contains |
|------|------------------|
| `dilux-cloud-storage.php` | Main plugin file — bootstraps everything and loads `includes/`. |
| `includes/` | Core PHP classes: config manager, stream wrapper, sync engine, admin pages, REST handlers. |
| `templates/` | Admin page views (rendered by the admin classes). |
| `assets/` | Plugin runtime assets — JS, CSS, images bundled with the plugin. |
| `languages/` | Translations: `es_AR`, `es_ES`, `es_MX`, `pt_BR`, `pt_PT`, `fr_FR`, `de_DE`, `it_IT`. |
| `.wordpress-org/` | wp.org listing visuals — banner, icon, screenshots. Uploaded by CI to the SVN `assets/` directory, **not** to the published plugin zip. |
| `readme.txt` | wp.org plugin page content (description, FAQ, changelog). |
| `.github/workflows/` | CI/CD: deploy to wp.org SVN on tag push, plus PR checks. |
| `.distignore` | List of paths excluded from the wp.org deploy (this README, dev tooling, etc. live in GitHub but are never shipped to wp.org). |

## Contributing

Contributions are welcome — bug reports, feature requests, and pull requests. See [CONTRIBUTING.md](CONTRIBUTING.md) for branch naming, PR workflow, and coding conventions.

## Reporting security issues

⚠️ **Please do not open public issues for security vulnerabilities.** See [SECURITY.md](SECURITY.md) for the private reporting process via GitHub Security Advisories.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
