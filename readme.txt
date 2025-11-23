=== Pantheon Secrets Manager ===
Contributors: kyletaylored
Tags: pantheon, secrets, security, environment, configuration
Requires at least: 6.0
Tested up to: 6.4
Requires PHP: 8.2
Stable tag: trunk
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manage Pantheon secrets and map them to PHP constants securely.

== Description ==

The Pantheon Secrets Manager WordPress plugin provides a secure, Pantheon-native way to manage secrets that are typically stored as hardcoded values or plaintext options.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/pantheon-secrets-manager` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Use the Pantheon Secrets menu to configure your secrets.

== Frequently Asked Questions ==

= Does this store secrets in the database? =

No, secrets are stored in Pantheon's secure secrets service. The plugin only stores metadata mapping the secrets to PHP constants.

== Changelog ==

= 1.0.0 =
* Initial release.
