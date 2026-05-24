=== Store Locator ===
Contributors: biokyma
Tags: store locator, google maps, csv import, shops
Requires at least: 6.5
Tested up to: 6.5
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later

Custom store locator with Google Map, search by CAP/city, radius filter and Embla carousel of store cards. Includes a CSV importer for TeamSystem exports and a WP-CLI command for bulk imports.

== Description ==

* Custom post type `store` managed from WP admin.
* Search by CAP or city with 25 / 50 / 100 KM radius filter.
* Map uses Google Maps JavaScript API with custom branded SVG markers (AdvancedMarkerElement).
* Horizontal card carousel (Embla Carousel from CDN).
* CSV import compatible with the TeamSystem export format.
* Server-side geocoding via Google Geocoding API; address-hash caching avoids
  re-geocoding unchanged addresses on re-import.
* REST API endpoints: `/wp-json/store-locator/v1/stores` and `/geocode?q=…`.

== Installation ==

1. Drop the `store-locator` folder into `wp-content/plugins/` and activate it from the Plugins screen.
2. Go to **Store Locator → Impostazioni** and paste your Google API keys (Maps JavaScript API + Geocoding API). The same key can be used for both; make sure both APIs are enabled in your Google Cloud project.
3. Add stores manually via **Store Locator → Aggiungi nuovo**, or use the CSV importer below.
4. Place the shortcode `[store_locator]` on any page or post.

== CSV Import ==

Go to **Store Locator → Import CSV** and upload the TeamSystem export. UTF-8 encoded, comma-separated, CRLF line endings. Expected columns (exact headers):

`CodCli, RagioneSociale, Indirizzo, CAP, Citta, PR, ISO, Stato, Telefono, CodCat, Categoria, UltAcq`

Column mapping:

* `CodCli` → `_sl_external_id` (unique key, used to match on re-import)
* `RagioneSociale` → post title
* `Indirizzo` → `_sl_address`
* `CAP` → `_sl_cap`
* `Citta` → `_sl_city`
* `PR` → `_sl_province`
* `ISO` → `_sl_country` (default `IT` if empty)
* `Telefono` → `_sl_phone_raw` (display) + `_sl_phone_tel` (normalized `+39…`)
* `Categoria` → `_sl_category` + auto-assigned to taxonomy `store_category`
* `CodCat` → `_sl_category_code`
* `Stato`, `UltAcq` → ignored

The import upserts by `CodCli` and geocodes any row whose coordinates are missing or whose address has changed (sha1 hash of the address fields). Geocoding is throttled to ~10 req/sec.

For large imports (the initial 800-row file) the browser-based importer streams in 25-row chunks via AJAX; you can also use WP-CLI:

`wp store-locator import /path/to/export.csv [--force-geocode]`

A summary is shown on completion. Rows that failed (missing data or geocode failure) are downloadable as a CSV with an `error` column.

== Shortcode ==

`[store_locator]`

Outputs the search bar, the Google Map, and the carousel. The shortcode reads:

* center / zoom / suggerimenti text from the settings page,
* stores from the REST API endpoint `/wp-json/store-locator/v1/stores`.

== REST API ==

* `GET /wp-json/store-locator/v1/stores` — JSON array of all stores with coordinates and a pre-built `directions_url`.
* `GET /wp-json/store-locator/v1/geocode?q={query}` — server-side proxy to Google Geocoding so the front-end never sees the API key. Rate-limited to 30 requests / minute / IP.

== Theming ==

The plugin CSS uses CSS custom properties so the host theme can rebrand without overriding rules:

`--sl-brand` (default `#7a8f5a`), `--sl-brand-dark` (`#1a1a1a`), `--sl-map-h`, `--sl-card-w`, `--sl-radius`, `--sl-shadow`.

== Uninstall ==

The uninstall hook deletes only the `sl_settings` option. Store posts and meta are preserved so accidental deactivation does not lose data.

== Changelog ==

= 1.0.0 =
* Initial release.
