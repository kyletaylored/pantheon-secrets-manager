<?php
/**
 * Secrets Repository.
 *
 * @package PantheonSecretsManager\Service
 */

namespace PantheonSecretsManager\Service;

use PantheonSecretsManager\Model\Secret;

/**
 * Class SecretsRepository
 */
class SecretsRepository {






	/**
	 * The table name.
	 *
	 * @var string
	 */
	private string $table_name;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'pantheon_secrets';
	}

	/**
	 * Create the secrets table.
	 */
	public function create_table(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $this->table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			pantheon_secret_name varchar(255) NOT NULL,
			label varchar(255) NOT NULL,
			php_constant_name varchar(255) DEFAULT NULL,
			is_constant_enabled tinyint(1) NOT NULL DEFAULT 0,
			load_context varchar(50) NOT NULL DEFAULT 'manual',
			environment varchar(50) NOT NULL,
			is_plugin_owned tinyint(1) NOT NULL DEFAULT 0,
			is_deleted_locally tinyint(1) NOT NULL DEFAULT 0,
			status varchar(50) NOT NULL DEFAULT 'active',
			last_synced_at datetime DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY pantheon_secret_name_environment (pantheon_secret_name, environment)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Save a secret.
	 *
	 * @param Secret $secret The secret to save.
	 * @return int|false The secret ID or false on failure.
	 */
	public function save( Secret $secret ) {
		global $wpdb;

		$data = $secret->to_array();
		unset( $data['id'] ); // Let DB handle ID.
		unset( $data['created_at'] ); // Let DB handle created_at.
		unset( $data['updated_at'] ); // Let DB handle updated_at.

		// Format for wpdb->insert/update.
		$format = array(
			'%s', // pantheon_secret_name.
			'%s', // label.
			'%s', // php_constant_name.
			'%d', // is_constant_enabled.
			'%s', // load_context.
			'%s', // environment.
			'%d', // is_plugin_owned.
			'%d', // is_deleted_locally.
			'%s', // status.
			'%s', // last_synced_at.
		);

		if ( $secret->get_id() ) {
			$updated = $wpdb->update(
				$this->table_name,
				$data,
				array( 'id' => $secret->get_id() ),
				$format,
				array( '%d' )
			);
			return false !== $updated ? $secret->get_id() : false;
		} else {
			$inserted = $wpdb->insert(
				$this->table_name,
				$data,
				$format
			);
			return $inserted ? $wpdb->insert_id : false;
		}
	}

	/**
	 * Get a secret by ID.
	 *
	 * @param int $id The secret ID.
	 * @return Secret|null The secret or null if not found.
	 */
	public function get( int $id ): ?Secret {
		global $wpdb;

		$row = $wpdb->get_row(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare( "SELECT * FROM $this->table_name WHERE id = %d", $id ),
			ARRAY_A
		);

		if ( ! $row ) {
			return null;
		}

		return new Secret( $row );
	}

	/**
	 * Get a secret by Pantheon secret name and environment.
	 *
	 * @param string $name The Pantheon secret name.
	 * @param string $environment The environment.
	 * @return Secret|null The secret or null if not found.
	 */
	public function get_by_name_and_env( string $name, string $environment ): ?Secret {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM $this->table_name WHERE pantheon_secret_name = %s AND environment = %s",
				$name,
				$environment
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return null;
		}

		return new Secret( $row );
	}

	/**
	 * Get all secrets for an environment.
	 *
	 * @param string $environment The environment.
	 * @return Secret[] Array of secrets.
	 */
	public function get_all_by_env( string $environment ): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare( "SELECT * FROM $this->table_name WHERE environment = %s", $environment ),
			ARRAY_A
		);

		return array_map(
			function ( $row ) {
				return new Secret( $row );
			},
			$rows
		);
	}

	/**
	 * Delete a secret.
	 *
	 * @param int $id The secret ID.
	 * @return bool True on success, false on failure.
	 */
	public function delete( int $id ): bool {
		global $wpdb;

		return (bool) $wpdb->delete(
			$this->table_name,
			array( 'id' => $id ),
			array( '%d' )
		);
	}
}
