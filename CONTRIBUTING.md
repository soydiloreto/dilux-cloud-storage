# Contributing to Dilux Cloud Storage

Thanks for your interest in contributing. This document covers how to report issues, submit pull requests, and what to expect from the review process.

## Reporting bugs and requesting features

Open a [new issue](https://github.com/soydiloreto/dilux-cloud-storage/issues/new/choose) and pick the appropriate template:

- **Bug report** — something is broken or behaves unexpectedly.
- **Feature request** — you'd like the plugin to do something it doesn't do today.

For **end-user support questions** (how do I configure this?, my upload is not working, etc.) please use the [wp.org support forum](https://wordpress.org/support/plugin/dilux-cloud-storage/) instead — that's where most users look for answers and where we maintain a public Q&A.

For **security vulnerabilities**, see [SECURITY.md](SECURITY.md). Do not open a public issue.

## Pull request workflow

1. **Fork** the repository and clone your fork locally.
2. **Branch** from `main` using a descriptive name following the convention below.
3. **Commit** your changes (see commit message conventions below).
4. **Push** the branch to your fork.
5. **Open a pull request** against `main`. Fill in the PR template — explain *why* the change matters, not just *what* it does.
6. **Wait for CI to pass.** All required checks must be green before review.
7. **Address review feedback** by pushing additional commits to the same branch (we squash on merge, so commit count doesn't matter).

### Branch naming

Use one of these prefixes, followed by a short kebab-case description:

| Prefix | Used for |
|--------|----------|
| `feat/` | New user-visible functionality |
| `fix/` | Bug fixes |
| `chore/` | Maintenance tasks, version bumps, no behavior change |
| `docs/` | Documentation-only changes |
| `ci/` | CI/CD configuration changes |
| `refactor/` | Internal restructure with no behavior change |
| `style/` | Code style / linter / formatting changes |

Examples: `feat/s3-provider`, `fix/decrypt-fallback-banner`, `docs/contributing-guide`.

### Commit messages

We follow [Conventional Commits](https://www.conventionalcommits.org/). The first line is `<type>(<optional-scope>): <subject>`, where type is one of `feat`, `fix`, `chore`, `docs`, `ci`, `refactor`, `style`, `test`. Examples:

```
feat(provider): add S3 cloud storage provider
fix(admin): surface decrypt failures in the connection-health banner
chore: bump to 1.2.0
```

The body explains the *why* — context, motivation, alternatives considered. Wrap at ~72 characters.

## Coding conventions

For now: **follow the style of the surrounding code.** A formal linter configuration (PHP_CodeSniffer with WordPress Coding Standards) will be introduced in a later iteration; until then, consistency with existing files is the rule.

A few hard rules:

- **PHP 7.4+** is the minimum supported version. Do not use syntax or functions added in later versions without a fallback.
- **No hard dependencies on Composer packages** in the runtime path. The plugin must run on a fresh WordPress install with no extra setup.
- **All user-facing strings** must be wrapped in WordPress translation functions (`__()`, `_e()`, `_n()`, etc.) with the text domain `dilux-cloud-storage`.
- **All user input** must be sanitized (`sanitize_text_field`, `wp_kses`, etc.) and all output must be escaped (`esc_html`, `esc_attr`, `esc_url`).
- **Never log credentials.** API keys, access keys, and decrypted secrets must not appear in `error_log` even when debug mode is on.

## Versioning

We follow [Semantic Versioning](https://semver.org/) for the plugin's public version (`MAJOR.MINOR.PATCH`):

- **PATCH** (1.1.0 → 1.1.1): bug fixes only, no behavior change for the user beyond the fix itself.
- **MINOR** (1.1.0 → 1.2.0): new user-visible functionality, backwards-compatible.
- **MAJOR** (1.x → 2.0): backwards-incompatible changes (rare; plugins try hard to avoid).

Repository-only changes (this CONTRIBUTING.md, CI workflows, dev tooling, etc.) **do not** trigger a version bump — they are excluded from the wp.org deploy via [`.distignore`](.distignore) and are invisible to end users.

## Releasing (maintainers only)

Release flow is documented for maintainers. Briefly:

1. All PRs targeting the next release are merged into `main`.
2. The maintainer bumps `Version:` in `dilux-cloud-storage.php`, `Stable tag:` in `readme.txt`, and adds a `== Changelog ==` entry.
3. The maintainer creates and pushes a git tag matching the version (bare number, no `v` prefix — e.g. `1.2.0`).
4. The GitHub Action `deploy.yml` automatically pushes the release to wp.org SVN and uploads a zip to GitHub Releases.

## Code of Conduct

This project follows the [Contributor Covenant Code of Conduct](CODE_OF_CONDUCT.md). By participating, you agree to abide by its terms.

## License

By contributing, you agree that your contributions will be licensed under the [GPL-2.0-or-later](LICENSE).
