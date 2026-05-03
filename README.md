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
