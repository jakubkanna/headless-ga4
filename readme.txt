=== Headless GA4 ===
Contributors: jakubkanna
Tags: analytics, ga4, dashboard, admin, headless
Requires at least: 6.4
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 0.2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display lightweight GA4 traffic data in the WordPress dashboard for headless frontends.

== Description ==

Headless GA4 adds a compact Google Analytics 4 widget to the main WordPress dashboard.

It is designed for sites where:

* the frontend is not rendered by WordPress
* GA4 tracking already happens on the frontend
* WordPress only needs to read and display GA4 reporting data for admins

The plugin uses the Google Analytics Data API and a Google service account. It does not inject frontend tracking code.

Features:

* settings page for GA4 configuration
* dashboard widget with core traffic metrics
* last 7 days trend chart
* top pages and top traffic sources
* transient caching
* manual refresh action

== Privacy ==

Headless GA4 does not track visitors or inject analytics scripts.

The plugin only sends server-side requests to Google when an administrator configures a GA4 property and opens the dashboard or tests the connection. Those requests use the service account credentials you provide in the plugin settings.

== External services ==

This plugin connects to Google services to authenticate requests and fetch Google Analytics 4 report data. These requests are only made after an administrator configures the plugin and then opens the dashboard widget, refreshes the data, or uses the Test connection button.

= Google OAuth token service (`oauth2.googleapis.com`) =

Used to exchange the configured Google service account credentials for a short-lived access token.

Data sent: a signed JWT assertion containing the service account email, requested scope, token endpoint, issued-at time, and expiration time.

Terms of service: https://developers.google.com/terms
Privacy policy: https://policies.google.com/privacy

= Google Analytics Data API (`analyticsdata.googleapis.com`) =

Used to request GA4 report data for the configured GA4 property.

Data sent: the GA4 property ID, report request parameters such as date ranges, dimensions, metrics, and limits, plus the OAuth access token in the request headers.

Terms of service: https://developers.google.com/terms
Privacy policy: https://policies.google.com/privacy

== Installation ==

1. Upload the `headless-ga4` folder to `/wp-content/plugins/`.
2. Activate the plugin through the WordPress Plugins screen.
3. Go to `Settings > Headless GA4`.
4. Enter your GA4 property ID.
5. Paste your service account JSON credentials.
6. Save the settings.
7. Use the `Test connection` button.
8. Open the main Dashboard screen to view the widget.

== Frequently Asked Questions ==

= What is a service account? =

A service account is a Google Cloud machine identity used by the plugin to read your GA4 reporting data without requiring a human Google login inside wp-admin.

= Does this plugin install tracking on my site? =

No. This plugin only reads GA4 report data. Your frontend must already send data to GA4.

= What do I need from Google? =

You need:

* a Google Cloud project
* the Google Analytics Data API enabled
* a service account
* the full service account JSON credentials
* Viewer access for that service account email in your GA4 property
* your GA4 numeric property ID

= How do I set it up? =

1. Create a Google Cloud project.
2. In Google Cloud Console, open `APIs & Services`, click `Enable APIs and Services`, search for `Google Analytics Data API`, open it, and click `Enable`.
3. Create a service account.
4. Open the service account, go to `Keys`, click `Add key`, choose `Create new key`, then select `JSON` and download the file.
5. Copy the service account email address.
6. In GA4, open `Admin`, then `Property access management`, add that service account email, and grant it `Viewer` access.
7. In GA4, open `Admin`, then `Property settings`, and copy the numeric `Property ID`.
8. Paste the property ID and the full service account JSON into the plugin settings.
9. Save the settings and test the connection.

= Why do I not see any data? =

Possible reasons:

* the frontend is not sending GA4 events
* the wrong property ID was entered
* the service account email was not added in `GA4 > Admin > Property access management`
* the service account JSON is invalid
* the property has not received enough recent traffic

== Screenshots ==

1. Settings page for GA4 property ID and service account JSON
2. Dashboard widget with summary cards, trend chart, top pages, and top traffic sources

== Changelog ==

= 0.2.1 =

* Fix recursive service account JSON sanitization when saving settings
* Fix clearing stored service account credentials

= 0.2.0 =

* Add dashboard period filters for weekly, monthly, yearly, and all-time reports
* Add period-aware GA4 report caching
* Improve the sessions trend diagram with combined bar and line chart display

= 0.1.1 =

* Replace inline admin CSS with an enqueued stylesheet
* Move admin screen markup into template files
* Remove deprecated OpenSSL cleanup call
* Add external service disclosures for Google services

= 0.1.0 =

* Initial release
* Settings page with service account configuration
* Dashboard widget with traffic summary
* GA4 Data API integration using a service account

== Upgrade Notice ==

= 0.2.1 =

Fixes a critical error when saving service account JSON credentials.

= 0.2.0 =

Adds dashboard report period filters and an improved sessions diagram.

= 0.1.1 =

Maintenance release for plugin review fixes.

= 0.1.0 =

Initial public release.
