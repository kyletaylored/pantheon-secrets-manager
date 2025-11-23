<?php
/**
 * Plugin Name: Pantheon Secrets Manager
 * Plugin URI: https://github.com/kyletaylored/pantheon-secrets-manager
 * Description: Manage Pantheon secrets and map them to PHP constants securely.
 * Version: 1.0.0
 * Author: Kyle Taylor
 * Author URI: https://github.com/kyletaylored
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.0
 * Requires PHP: 8.2
 * Text Domain: pantheon-secrets-manager
 * Domain Path: /languages
 */

namespace PantheonSecretsManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PANTHEON_SECRETS_MANAGER_VERSION', '1.0.0' );
define( 'PANTHEON_SECRETS_MANAGER_PATH', plugin_dir_path( __FILE__ ) );
define( 'PANTHEON_SECRETS_MANAGER_URL', plugin_dir_url( __FILE__ ) );

if ( file_exists( PANTHEON_SECRETS_MANAGER_PATH . 'vendor/autoload.php' ) ) {
	require_once PANTHEON_SECRETS_MANAGER_PATH . 'vendor/autoload.php';
}

/**
 * Bootstrap the plugin.
 */
function bootstrap() {
	// Initialize services here.
	$resolver = new \PantheonSecretsManager\Service\SecretResolver();
	$resolver->define_constants( 'plugin' );

	if ( is_admin() ) {
		$admin_menu = new \PantheonSecretsManager\Admin\AdminMenu();
		$admin_menu->init();
	}

	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		WP_CLI::add_command( 'pantheon-secrets', '\PantheonSecretsManager\CLI\SecretsCommand' );
	}
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\\bootstrap' );

/**
 * Activation hook.
 */
function activate() {
	$role = get_role( 'administrator' );
	if ( $role ) {
		$role->add_cap( 'manage_pantheon_secrets' );
	}
}

register_activation_hook( __FILE__, __NAMESPACE__ . '\\activate' );
