=== Lightweight Integration for IndexNow ===
Contributors: nicolamustone
Tags: indexnow, seo, bing, indexing, performance
Requires at least: 6.0
Tested up to: 6.9
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
3. That’s it. The plugin will automatically send IndexNow pings when you publish, update, or trash supported content.
4. Optionally, go to **Settings → General → IndexNow**:
   * An API key is already generated for you. You can keep it or replace it with your own.
   * (Optional) Tick “IndexNow Key File” if you have uploaded `YOUR_KEY.txt` containing only your key to your site root.

== Frequently Asked Questions ==

= Why should I use this plugin instead of others? =

Because it is **super lightweight**:

* It has literally **two options** which are also optional.
* It adds **no JavaScript** and **no CSS** to your site.
* It does **not** output anything on the front end.
* It **works immediately** after activation. No configuration is required unless you want to change the key or use a key file.

If you like small, transparent plugins that do exactly one thing, this is for you.

= Do I have to configure anything before it works? =

No. On activation, the plugin generates an IndexNow-compatible API key and uses it automatically.

You only need to:

* Replace the key if you prefer to use your own.
* Optionally tick the key file checkbox if you have created `YOUR_KEY.txt` in your site root.

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

= 1.0.0 =

* Initial release as Lightweight Integration for IndexNow.
* Auto-generated IndexNow API key on activation.
* Pings on publish/update/trash for posts, pages, and WooCommerce products.
* Optional key file support via simple checkbox.
* Localhost detection and automatic skip.
* Developer hooks for maximum flexibility.