=== DevBrothers Cyrillic URL ===
Contributors: lzolotarev
Tags: transliteration, cyrillic, slugs, url, seo
Requires at least: 5.8
Tested up to: 6.9
Stable tag: 1.0.0
Requires PHP: 7.4
Requires Plugins: devbrothers-admin-panel
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatic transliteration of Cyrillic URLs to Latin according to ISO 9 standard. WooCommerce support.

== Description ==

DevBrothers Cyrillic URL automatically converts Cyrillic characters in URLs (slugs) to Latin letters according to the international ISO 9 standard.

= Key Features =

* **Automatic transliteration** when creating posts, pages, categories, and products
* **ISO 9 standard** - international standard for Cyrillic transliteration
* **Flexible settings** - choose post types for automatic conversion
* **WooCommerce support** - works with products and categories
* **Bulk conversion** - update existing URLs with one click
* **DevBrothers integration** - unified admin panel
* **Custom Post Types support** - automatic detection of custom types
* **Security** - nonces, sanitization, escaping

= Transliteration Examples =

* `новая-запись` → `novaya-zapis`
* `категория-блога` → `kategoriya-bloga`
* `товар-интернет-магазина` → `tovar-internet-magazina`

= Who is this plugin for? =

* Website owners with Russian content
* WooCommerce stores with Russian products
* Blogs and news portals
* Corporate websites
* Any projects requiring readable Latin URLs

= Dependencies =

This plugin requires [DevBrothers Admin Panel](https://wordpress.org/plugins/devbrothers-admin-panel/) to be installed.

= DevBrothers Integration =

The plugin is fully integrated into DevBrothers Admin Panel and accessible through the unified admin interface:
* Centralized settings
* Unified interface style
* Quick navigation between plugins
* Settings categories with anchors

== Installation ==

= Automatic Installation =

1. Install DevBrothers Admin Panel (base plugin)
2. Go to 'Plugins' → 'Add New'
3. Search for 'DevBrothers Cyrillic URL'
4. Click 'Install'
5. Activate the plugin

= Manual Installation =

1. Install DevBrothers Admin Panel
2. Upload the `devbrothers-cyrillic-url` folder to `/wp-content/plugins/`
3. Activate the plugin through the 'Plugins' menu
4. Go to 'DevBrothers' → 'Cyrillic URL' to configure

= After Activation =

1. Open DevBrothers → Cyrillic URL
2. Select post types for automatic transliteration
3. (Optional) Run bulk conversion of existing URLs
4. Done! New posts will be automatically transliterated

== Frequently Asked Questions ==

= Is DevBrothers Admin Panel required? =

Yes, this is the base plugin that provides a unified admin panel for all DevBrothers plugins.

= What transliteration standard is used? =

The plugin uses the international ISO 9 standard for Cyrillic transliteration.

= Does it work with WooCommerce? =

Yes! The plugin automatically detects WooCommerce post types (products, categories) and allows you to enable transliteration for them.

= Can I convert existing URLs? =

Yes, in the "URL Conversion" section there is a button for bulk conversion of all existing posts and terms.

= Is bulk conversion safe? =

Yes, but it is recommended to make a database backup before bulk conversion.

= Are Custom Post Types supported? =

Yes, the plugin automatically detects all registered post types and allows you to select them in settings.

= Can I configure transliteration rules? =

The current version uses standard ISO 9 rules. The ability to customize is planned for future versions.

= Does it affect performance? =

No, transliteration only occurs when saving a post and does not affect site performance.

== Screenshots ==

1. Main settings page - post type selection
2. Bulk URL conversion with progress bar
3. Integration in DevBrothers Admin Panel
4. URL transliteration example

== Changelog ==

= 1.0.0 =
* Initial release
* Automatic transliteration according to ISO 9 standard
* Support for all post types
* WooCommerce support
* Bulk conversion of existing URLs
* Integration with DevBrothers Admin Panel
* Flexible settings
* Taxonomy support

== Upgrade Notice ==

= 1.0.0 =
Initial release of DevBrothers Cyrillic URL plugin.
