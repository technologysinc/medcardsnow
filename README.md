# MedCardsNow.com

WordPress source for [medcardsnow.com](https://medcardsnow.com) — version-controlled backup and deployment reference.

## Stack

- **CMS:** WordPress
- **Hosting:** Hostinger (shared hosting)
- **PHP / Server:** Apache (CloudLinux)

## Repository structure

.
├── wp-admin/ # WordPress core (do not edit directly)
├── wp-includes/ # WordPress core (do not edit directly)
├── wp-content/
│ ├── themes/ # Active theme and customizations
│ ├── plugins/ # Installed plugins
│ └── uploads/ # Media (ignored — see .gitignore)
└── wp-config.php # Site config (ignored — contains DB credentials)


## What's excluded

The following are intentionally left out of version control (see `.gitignore`):

| Path | Reason |
|---|---|
| `wp-config.php` | Contains database credentials and secret keys |
| `wp-content/uploads/` | Media library — large, regenerable, not code |
| `wp-content/cache/` | Generated cache files |
| `*.log` | Server/application logs |

## Restoring a fresh environment

1. Clone this repo into `public_html` (or your target web root).
2. Copy `wp-config-sample.php` → `wp-config.php` and fill in your DB credentials.
3. Import the site database separately (not stored in this repo).
4. Re-upload `wp-content/uploads/` from your latest backup.

## Notes

This repo is a **source backup / deployment mirror**, not a development environment — most day-to-day edits are still made via the WordPress admin or hosting file manager. Treat this as the safety net, not the primary workflow.

---
*Private repository — do not make public without first auditing for exposed credentials or API keys.*
