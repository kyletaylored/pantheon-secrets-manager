<?php
/**
 * WP-CLI Commands.
 *
 * @package PantheonSecretsManager\CLI
 */

namespace PantheonSecretsManager\CLI;

use WP_CLI;
use WP_CLI_Command;
use PantheonSecretsManager\Service\SecretsRepository;
use PantheonSecretsManager\API\PantheonSecretsAPI;
use PantheonSecretsManager\Model\Secret;

/**
 * Manage Pantheon Secrets.
 */
class SecretsCommand extends WP_CLI_Command {




	/**
	 * List secrets.
	 *
	 * ## OPTIONS
	 *
	 * [--environment=<env>]
	 * : Filter by environment.
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp pantheon-secrets list
	 *     wp pantheon-secrets list --environment=dev
	 *
	 * @param array $args       Command arguments.
	 * @param array $assoc_args Command associative arguments.
	 */
	public function list( $args, $assoc_args ) {
		$environment = $assoc_args['environment'] ?? 'dev'; // TODO: Get actual environment.
		$repository = new SecretsRepository();
		$secrets = $repository->get_all_by_env( $environment );

		$data = array_map(
			function ( $secret ) {
				return $secret->to_array();
			},
			$secrets
		);

		\WP_CLI\Utils\format_items( $assoc_args['format'], $data, array( 'id', 'label', 'pantheon_secret_name', 'php_constant_name', 'status' ) );
	}

	/**
	 * Create a secret.
	 *
	 * ## OPTIONS
	 *
	 * --name=<name>
	 * : The Pantheon secret name.
	 *
	 * --label=<label>
	 * : The secret label.
	 *
	 * --value=<value>
	 * : The secret value.
	 *
	 * [--constant=<constant>]
	 * : The PHP constant name.
	 *
	 * [--load-context=<context>]
	 * : The load context (plugin, mu_plugin, manual).
	 * ---
	 * default: manual
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp pantheon-secrets create MY_SECRET "supersecret" --label="My Secret" --enable-constant
	 *
	 * @param array $args       Command arguments.
	 * @param array $assoc_args Command associative arguments.
	 */
	public function create( $args, $assoc_args ) {
		list($name, $value) = $args;

		if ( ! $this->validate_name( $name ) ) {
			\WP_CLI::error( 'Invalid Pantheon Secret Name. Must contain only uppercase letters, numbers, and underscores.' );
		}

		$label = $assoc_args['label'] ?? '';
		$php_constant_name = $assoc_args['constant-name'] ?? $name;
		$is_constant_enabled = \WP_CLI\Utils\get_flag_value( $assoc_args, 'enable-constant', false );
		$load_context = $assoc_args['context'];

		if ( ! empty( $php_constant_name ) && ! $this->validate_name( $php_constant_name ) ) {
			\WP_CLI::error( 'Invalid PHP Constant Name. Must contain only uppercase letters, numbers, and underscores.' );
		}

		// Save to Pantheon.
		$api = new PantheonSecretsAPI();
		if ( ! $api->set_secret( $name, $value ) ) {
			\WP_CLI::error( 'Failed to save secret to Pantheon.' );
		}

		// Save to DB.
		$repository = new SecretsRepository();
		$data = array(
			'pantheon_secret_name' => $name,
			'label' => $label,
			'php_constant_name' => $php_constant_name,
			'is_constant_enabled' => $is_constant_enabled,
			'load_context' => $load_context,
			'environment' => 'dev', // TODO: Get actual environment.
			'is_plugin_owned' => true,
			'status' => 'active',
		);

		$secret = new Secret( $data );
		if ( $repository->save( $secret ) ) {
			\WP_CLI::success( 'Secret created.' );
		} else {
			\WP_CLI::error( 'Failed to save secret metadata.' );
		}
	}

	/**
	 * Update an existing secret.
	 *
	 * ## OPTIONS
	 *
	 * <name>
	 * : The Pantheon secret name.
	 *
	 * [--value=<value>]
	 * : The new secret value.
	 *
	 * [--label=<label>]
	 * : The new secret label.
	 *
	 * [--constant-name=<constant-name>]
	 * : The new PHP constant name.
	 *
	 * [--enable-constant]
	 * : Enable the PHP constant.
	 *
	 * [--disable-constant]
	 * : Disable the PHP constant.
	 *
	 * [--context=<context>]
	 * : The new load context.
	 *
	 * ## EXAMPLES
	 *
	 *     wp pantheon-secrets update MY_SECRET --value="newvalue"
	 *
	 * @param array $args       Command arguments.
	 * @param array $assoc_args Command associative arguments.
	 */
	public function update( $args, $assoc_args ) {
		list($name) = $args;

		if ( ! $this->validate_name( $name ) ) {
			\WP_CLI::error( 'Invalid Pantheon Secret Name.' );
		}

		$repository = new SecretsRepository();
		$secret = $repository->get_by_name_and_env( $name, 'dev' ); // TODO: Environment.

		if ( ! $secret ) {
			\WP_CLI::error( 'Secret not found.' );
		}

		// Update Pantheon if value provided.
		if ( isset( $assoc_args['value'] ) ) {
			$api = new PantheonSecretsAPI();
			if ( ! $api->set_secret( $name, $assoc_args['value'] ) ) {
				\WP_CLI::error( 'Failed to update secret in Pantheon.' );
			}
		}

		// Update Local Metadata.
		if ( isset( $assoc_args['label'] ) ) {
			$secret->set_label( $assoc_args['label'] );
		}
		if ( isset( $assoc_args['constant-name'] ) ) {
			if ( ! $this->validate_name( $assoc_args['constant-name'] ) ) {
				\WP_CLI::error( 'Invalid PHP Constant Name.' );
			}
			$secret->set_php_constant_name( $assoc_args['constant-name'] );
		}
		if ( isset( $assoc_args['enable-constant'] ) ) {
			$secret->set_is_constant_enabled( true );
		}
		if ( isset( $assoc_args['disable-constant'] ) ) {
			$secret->set_is_constant_enabled( false );
		}
		if ( isset( $assoc_args['context'] ) ) {
			$secret->set_load_context( $assoc_args['context'] );
		}

		if ( $repository->save( $secret ) ) {
			\WP_CLI::success( 'Secret updated.' );
		} else {
			\WP_CLI::error( 'Failed to update secret metadata.' );
		}
	}

	/**
	 * Delete a secret.
	 *
	 * ## OPTIONS
	 *
	 * <name>
	 * : The Pantheon secret name.
	 *
	 * [--force]
	 * : Delete from Pantheon as well.
	 *
	 * ## EXAMPLES
	 *
	 *     wp pantheon-secrets delete MY_SECRET --force
	 *
	 * @param array $args       Command arguments.
	 * @param array $assoc_args Command associative arguments.
	 */
	public function delete( $args, $assoc_args ) {
		list($name) = $args;

		$repository = new SecretsRepository();
		$secret = $repository->get_by_name_and_env( $name, 'dev' ); // TODO: Environment.

		if ( ! $secret ) {
			\WP_CLI::error( 'Secret not found.' );
		}

		if ( $repository->delete( $secret->get_id() ) ) {
			if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'force', false ) ) {
				$api = new PantheonSecretsAPI();
				if ( $api->delete_secret( $name ) ) {
					\WP_CLI::success( 'Secret deleted from Pantheon and local database.' );
				} else {
					\WP_CLI::warning( 'Secret deleted locally but failed to delete from Pantheon.' );
				}
			} else {
				\WP_CLI::success( 'Secret deleted locally.' );
			}
		} else {
			\WP_CLI::error( 'Failed to delete secret.' );
		}
	}

	/**
	 * Sync secrets from Pantheon.
	 *
	 * ## EXAMPLES
	 *
	 *     wp pantheon-secrets sync
	 *
	 * @param array $args       Command arguments.
	 * @param array $assoc_args Command associative arguments.
	 */
	public function sync( $args, $assoc_args ) {
		$service = new \PantheonSecretsManager\Service\SecretsSyncService();
		$stats = $service->sync();

		\WP_CLI::success( sprintf( 'Sync completed. Created: %d, Updated: %d, Deleted: %d', $stats['created'], $stats['updated'], $stats['deleted'] ) );
	}

	/**
	 * Define constants for a specific context.
	 *
	 * ## OPTIONS
	 *
	 * <context>
	 * : The context to load (plugin, mu_plugin, manual).
	 *
	 * ## EXAMPLES
	 *
	 *     wp pantheon-secrets define-constants plugin
	 *
	 * @param array $args       Command arguments.
	 * @param array $assoc_args Command associative arguments.
	 */
	public function define_constants( $args, $assoc_args ) {
		list($context) = $args;

		if ( ! in_array( $context, array( 'plugin', 'mu_plugin', 'manual' ), true ) ) {
			\WP_CLI::error( 'Invalid context. Must be one of: plugin, mu_plugin, manual.' );
		}

		$resolver = new \PantheonSecretsManager\Service\SecretResolver();
		$resolver->define_constants( $context );

		\WP_CLI::success( "Constants defined for context: $context" );
	}

	/**
	 * Validate secret name.
	 *
	 * @param string $name Secret name.
	 * @return bool True if valid.
	 */
	private function validate_name( string $name ): bool {
		if ( ! preg_match( '/^[A-Z0-9_]+$/', $name ) ) {
			WP_CLI::error( 'Invalid secret name. Must contain only uppercase letters, numbers, and underscores.' );
			return false;
		}
		return true;
	}
}
