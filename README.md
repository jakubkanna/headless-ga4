# Headless GA4

Standalone WordPress plugin that displays lightweight GA4 traffic data in the WordPress dashboard for headless frontends.

## What it does

- adds a settings page under `Settings > Headless GA4`
- authenticates to the Google Analytics Data API with a service account
- displays a compact dashboard widget with:
  - users
  - sessions
  - views
  - engaged sessions
  - 7-day trend
  - top pages
  - top traffic sources

## Setup

1. Create a Google Cloud project.
2. Enable the Google Analytics Data API.
3. Create a service account.
4. Generate the service account JSON credentials.
5. Add the service account email as a Viewer or Reader in your GA4 property.
6. Copy the numeric GA4 property ID.
7. Open `Settings > Headless GA4` in WordPress.
8. Paste the property ID and service account JSON.
9. Save settings and use `Test connection`.

## Notes

- This plugin does not inject GA4 tracking code.
- The frontend must already send traffic to GA4.
- The plugin stores settings in WordPress options and caches report data with transients.
