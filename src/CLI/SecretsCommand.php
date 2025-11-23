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

		WP_CLI\Utils\format_items( $assoc_args['format'], $data, array( 'id', 'label', 'pantheon_secret_name', 'php_constant_name', 'status' ) );
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
	 * [--enable-constant]
	 * : Enable the constant.
	 *
	 * ## EXAMPLES
	 *
	 *     wp pantheon-secrets create --name=MY_SECRET --label="My Secret" --value="supersecret" --constant=MY_SECRET_CONST --enable-constant
	 */
	public function create( $args, $assoc_args ) {
		$name = $assoc_args['name'];
		$label = $assoc_args['label'];
		$value = $assoc_args['value'];
		$constant = $assoc_args['constant'] ?? $name;
		$load_context = $assoc_args['load-context'] ?? 'manual';
		$enabled = isset( $assoc_args['enable-constant'] );

		if ( ! $this->validate_name( $name ) ) {
			return;
		}

		if ( $constant && ! $this->validate_name( $constant ) ) {
			return;
		}

		$api = new PantheonSecretsAPI();
		if ( ! $api->set_secret( $name, $value ) ) {
			WP_CLI::error( 'Failed to create secret in Pantheon.' );
		}

		$repository = new SecretsRepository();
		$secret = new Secret(
			array(
				'pantheon_secret_name' => $name,
				'label' => $label,
				'php_constant_name' => $constant,
				'is_constant_enabled' => $enabled,
				'load_context' => $load_context,
				'environment' => 'dev', // TODO: Get actual environment.
				'is_plugin_owned' => true,
				'status' => 'active',
			)
		);

		if ( $repository->save( $secret ) ) {
			WP_CLI::success( 'Secret created.' );
		} else {
			WP_CLI::error( 'Failed to save secret metadata.' );
		}
	}

	/**
	 * Update a secret.
	 *
	 * ## OPTIONS
	 *
	 * <name>
	 * : The Pantheon secret name.
	 *
	 * [--label=<label>]
	 * : The secret label.
	 *
	 * [--value=<value>]
	 * : The secret value.
	 *
	 * [--constant=<constant>]
	 * : The PHP constant name.
	 *
	 * [--load-context=<context>]
	 * : The load context (plugin, mu_plugin, manual).
	 *
	 * [--enable-constant]
	 * : Enable the constant.
	 *
	 * [--disable-constant]
	 * : Disable the constant.
	 *
	 * ## EXAMPLES
	 *
	 *     wp pantheon-secrets update MY_SECRET --value="newvalue"
	 */
	public function update( $args, $assoc_args ) {
		$name = $args[0];

		if ( ! $this->validate_name( $name ) ) {
			return;
		}

		$repository = new SecretsRepository();
		$secret = $repository->get_by_name_and_env( $name, 'dev' ); // TODO: Env.

		if ( ! $secret ) {
			WP_CLI::error( 'Secret not found.' );
		}

		if ( isset( $assoc_args['value'] ) ) {
			$api = new PantheonSecretsAPI();
			if ( ! $api->set_secret( $name, $assoc_args['value'] ) ) {
				WP_CLI::error( 'Failed to update secret in Pantheon.' );
			}
		}

		if ( isset( $assoc_args['label'] ) ) {
			$secret->set_label( $assoc_args['label'] );
		}

		if ( isset( $assoc_args['constant'] ) ) {
			if ( ! $this->validate_name( $assoc_args['constant'] ) ) {
				return;
			}
			$secret->set_php_constant_name( $assoc_args['constant'] );
		}

		if ( isset( $assoc_args['load-context'] ) ) {
			$secret->set_load_context( $assoc_args['load-context'] );
		}

		if ( isset( $assoc_args['enable-constant'] ) ) {
			$secret->set_is_constant_enabled( true );
		} elseif ( isset( $assoc_args['disable-constant'] ) ) {
			$secret->set_is_constant_enabled( false );
		}

		if ( $repository->save( $secret ) ) {
			WP_CLI::success( 'Secret updated.' );
		} else {
			WP_CLI::error( 'Failed to save secret metadata.' );
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
	 */
	public function delete( $args, $assoc_args ) {
		$name = $args[0];
		$repository = new SecretsRepository();
		$secret = $repository->get_by_name_and_env( $name, 'dev' ); // TODO: Env.

		if ( ! $secret ) {
			WP_CLI::error( 'Secret not found.' );
		}

		if ( isset( $assoc_args['force'] ) ) {
			$api = new PantheonSecretsAPI();
			if ( ! $api->delete_secret( $name ) ) {
				WP_CLI::warning( 'Failed to delete secret from Pantheon.' );
			}
		}

		if ( $repository->delete( $secret->get_id() ) ) {
			WP_CLI::success( 'Secret deleted.' );
		} else {
			WP_CLI::error( 'Failed to delete secret metadata.' );
		}
	}

	/**
	 * Sync secrets from Pantheon.
	 *
	 * ## EXAMPLES
	 *
	 *     wp pantheon-secrets sync
	 */
	public function sync( $args, $assoc_args ) {
		$service = new \PantheonSecretsManager\Service\SecretsSyncService();
		$stats = $service->sync();

		WP_CLI::success(
			sprintf(
				'Sync completed. Created: %d, Updated: %d, Deleted: %d',
				$stats['created'],
				$stats['updated'],
				$stats['deleted']
			)
		);
	}

	/**
	 * Define constants for a specific context.
	 *
	 * ## OPTIONS
	 *
	 * <context>
	 * : The load context (plugin, mu_plugin).
	 *
	 * ## EXAMPLES
	 *
	 *     wp pantheon-secrets define-constants plugin
	 */
	public function define_constants( $args, $assoc_args ) {
		$context = $args[0];
		if ( ! in_array( $context, array( 'plugin', 'mu_plugin', 'manual' ), true ) ) {
			WP_CLI::error( 'Invalid context. Must be one of: plugin, mu_plugin, manual.' );
		}
		$resolver = new \PantheonSecretsManager\Service\SecretResolver();
		$resolver->define_constants( $context );
		WP_CLI::success( 'Constants defined.' );
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
