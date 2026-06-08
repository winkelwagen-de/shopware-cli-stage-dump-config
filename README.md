# Shopware GDPR Dump Config

Community registry of [shopware-cli](https://developer.shopware.com/docs/products/tools/cli/project-commands/mysql-dump.html) dump rules for **plugin-owned data** — custom plugin tables and columns plugins add to Shopware core tables.

Standard Shopware core columns are handled by shopware-cli itself via `--anonymize` and `--clean`. This package extends that for installed plugins.

## Install

```bash
composer require friendsofshopware/shopware-gdpr-dump
```

Add to your `.shopware-project.yml`:

```yaml
include:
  - vendor/friendsofshopware/shopware-gdpr-dump/shopware-gdpr-dump.yml
```

## Create a GDPR dump

Always pass both flags so Shopware core data is anonymized and ephemeral tables are stripped:

```bash
shopware-cli project dump --anonymize --clean --output dump.sql.zst
```

## Contribute a plugin config

See [docs/contributing-a-plugin.md](docs/contributing-a-plugin.md).

Quick start:

```bash
git clone git@github.com:friendsofshopware/shopware-gdpr-dump.git
cd shopware-gdpr-dump && composer install
bin/scan-plugin-migrations /path/to/shopware
# review plugins/, cleanup, then open a PR
bin/cleanup-plugin-config plugins/vendor/plugin.yaml
bin/build-dump-config
bin/validate-dump-config
```

## Repository layout

| Path | Purpose |
|------|---------|
| `plugins/*.yaml` | Per-plugin authoring configs (maintained here) |
| `config/fragments/` | Reusable PII shorthand bundles |
| `dist/shopware-gdpr-dump.yml` | Generated merged config (built on merge to `main`) |
| `shopware-gdpr-dump.yml` | Same as `dist/` — shipped in the Composer package |

## License

MIT
