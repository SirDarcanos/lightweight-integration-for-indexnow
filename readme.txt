=== Lightweight Integration for IndexNow ===
Contributors: nicolamustone
Tags: indexnow, seo, bing, indexing, performance
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A tiny, no-bloat IndexNow integration for WordPress.

== Description ==

Lightweight Integration for IndexNow is an independent plugin by Nicola Mustone. It is not affiliated with or endorsed by IndexNow or its participating search engines.

This plugin automatically sends IndexNow pings whenever relevant content is published, updated, or moved to the trash. It is intentionally minimal:

* No JavaScript.
* No CSS.
* No front-end output at all.
* Only **two** options in Settings → General.

Out of the box, it:

* Auto-generates an IndexNow API key on activation (you can keep it or replace it with your own).
* Sends IndexNow pings when posts, pages, and WooCommerce products are published or updated.
* Sends IndexNow pings when those posts are moved to the trash.
* Optionally includes your key file URL (`https://example.com/YOUR_KEY.txt`) if you check the “key file” box in Settings.
* Skips all pings on `localhost`, `127.0.0.1`, and `::1`.
* Shows an admin notice (for admins) if an IndexNow request fails.

### External service and data sharing

This plugin connects to the IndexNow service at https://api.indexnow.org/indexnow to notify participating search engines about changed content. Activating the plugin enables automatic submissions when supported content is published, updated, or trashed; activation itself does not make a request.

Each submission sends your site's hostname, your IndexNow API key, the content permalink, and related taxonomy archive URLs. If the key-file checkbox is enabled, it also sends your key-file URL. The plugin does not send post bodies, user accounts, or visitor analytics. To stop submissions, clear the API key or deactivate the plugin.

IndexNow may share submitted URLs with participating search engines. See https://www.indexnow.org/ and the service terms at https://www.indexnow.org/terms before enabling this integration.

The plugin generates an API key but does not create or serve its verification file. Upload `YOUR_KEY.txt`, containing only your API key, to your site's public root so IndexNow can verify ownership. The key-file checkbox controls whether its URL is explicitly included in requests.

### Developer hooks

You can extend or customize the behavior with the following filters and actions:

**Filters**

* `nm_indexnow_should_ping_on_save( bool $should_ping, int $post_id, WP_Post $post, bool $update )`  
  Control whether a ping should be sent on `save_post`.

* `nm_indexnow_should_ping_on_trashed( bool $should_ping, int $post_id, WP_Post $post )`  
  Control whether a ping should be sent when a post is trashed.

* `nm_indexnow_supported_post_types( array $post_types )`  
  Change which post types are tracked (defaults: `post`, `page`, `product`).

* `nm_indexnow_taxonomy_map( array $tax_map )`  
  Map post types to taxonomies whose archive URLs should be pinged.  
  Defaults:  
  * `post` → `category`, `post_tag`  
  * `product` → `product_cat`, `product_tag`

* `nm_indexnow_collect_urls( array $urls, WP_Post $post )`  
  Final filter on the list of URLs before they are sent to IndexNow.

* `nm_indexnow_key_location_url( string $key_location, string $api_key )`  
  Override the key file URL used as `keyLocation` in the IndexNow payload.

* `nm_indexnow_endpoint( string $endpoint )`  
  Change the IndexNow endpoint URL (defaults to `https://api.indexnow.org/indexnow`).

* `nm_indexnow_request_args( array $args, string $endpoint, array $body )`  
  Modify the `wp_remote_post()` request arguments.

**Actions**

* `nm_indexnow_before_submit( array $urls, array $body )`  
  Fires right before the IndexNow request is sent.

* `nm_indexnow_after_submit( array $urls, array $response, array $body )`  
  Fires after a successful IndexNow request.

* `nm_indexnow_submit_failed( array $urls, WP_Error|array $response, array $body )`  
  Fires when the IndexNow request fails (network error or HTTP status ≥ 400).

== Installation ==

1. Upload the `lightweight-integration-for-indexnow` folder to `/wp-content/plugins/` or install the plugin ZIP via the Plugins screen.
2. Activate the plugin through the “Plugins” menu in WordPress.
3. Go to **Settings → General → IndexNow** and keep the generated API key or replace it with your own.
4. Upload `YOUR_KEY.txt`, containing only that key, to your site's public root for ownership verification. Tick “IndexNow Key File” to explicitly include the file URL in requests.
5. The plugin sends IndexNow pings when you publish, update, or trash supported content.

== Frequently Asked Questions ==

= Why should I use this plugin instead of others? =

Because it is **super lightweight**:

* It has literally **two options** which are also optional.
* It adds **no JavaScript** and **no CSS** to your site.
* It does **not** output anything on the front end.
* It generates an API key on activation. You only need to host the verification file and optionally change the key.

If you like small, transparent plugins that do exactly one thing, this is for you.

= Do I have to configure anything before it works? =

On activation, the plugin generates an IndexNow-compatible API key and uses it for submissions. IndexNow still needs to verify that key at `https://example.com/YOUR_KEY.txt`; the plugin does not create or serve this file.

Upload a file containing only your key to your site's public root. You can replace the generated key with your own, and tick the key-file checkbox to explicitly send the verification file URL.

= Which content types are pinged by default? =

By default, the plugin pings:

* Posts (`post`)
* Pages (`page`)
* WooCommerce products (`product`)

You can change this via the `nm_indexnow_supported_post_types` filter.

= Which URLs are sent for each post? =

For each supported post, the plugin includes:

* The post’s permalink.
* The archive URLs for mapped taxonomies, by default:
  * `post` → `category`, `post_tag`
  * `product` → `product_cat`, `product_tag`

You can modify this via `nm_indexnow_taxonomy_map` and `nm_indexnow_collect_urls`.

= Does this plugin work on localhost? =

The plugin **explicitly skips** IndexNow pings on:

* `localhost`
* `127.0.0.1`
* `::1`

This avoids pointless external requests on local environments.

== Screenshots ==

1. IndexNow settings in Settings → General.

== Changelog ==

= Unreleased =

* Fix the hostname sent in IndexNow requests and skip IPv6 localhost correctly.
* Declare minimum WordPress and PHP versions in the plugin header.
* Document IndexNow data sharing and the required verification file.

= 1.0.0 =

* Initial release as Lightweight Integration for IndexNow.
* Auto-generated IndexNow API key on activation.
* Pings on publish/update/trash for posts, pages, and WooCommerce products.
* Optional key file support via simple checkbox.
* Localhost detection and automatic skip.
* Developer hooks for maximum flexibility.