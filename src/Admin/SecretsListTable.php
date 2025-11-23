<?php
/**
 * Secrets List Table.
 *
 * @package PantheonSecretsManager\Admin
 */

namespace PantheonSecretsManager\Admin;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

use PantheonSecretsManager\Service\SecretsRepository;

/**
 * Class SecretsListTable
 */
class SecretsListTable extends \WP_List_Table {


	/**
	 * Secrets repository.
	 *
	 * @var SecretsRepository
	 */
	private SecretsRepository $repository;

	/**
	 * Constructor.
	 *
	 * @param SecretsRepository $repository The secrets repository.
	 */
	public function __construct( SecretsRepository $repository ) {
		parent::__construct(
			array(
				'singular' => 'secret',
				'plural' => 'secrets',
				'ajax' => false,
			)
		);
		$this->repository = $repository;
	}

	/**
	 * Get columns.
	 *
	 * @return array
	 */
	public function get_columns(): array {
		return array(
			'cb' => '<input type="checkbox" />',
			'label' => __( 'Label', 'pantheon-secrets-manager' ),
			'pantheon_secret_name' => __( 'Pantheon Secret Name', 'pantheon-secrets-manager' ),
			'php_constant_name' => __( 'PHP Constant', 'pantheon-secrets-manager' ),
			'status' => __( 'Status', 'pantheon-secrets-manager' ),
			'environment' => __( 'Environment', 'pantheon-secrets-manager' ),
			'load_context' => __( 'Load Context', 'pantheon-secrets-manager' ),
		);
	}

	/**
	 * Prepare items.
	 */
	public function prepare_items(): void {
		$columns = $this->get_columns();
		$hidden = array();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		// TODO: Fetch items from repository and assign to $this->items.
		// For now, just an empty array.
		$this->items = array();
	}

	/**
	 * Column default.
	 *
	 * @param object $item        The item.
	 * @param string $column_name The column name.
	 * @return string
	 */
	protected function column_default( $item, $column_name ): string {
		switch ( $column_name ) {
			case 'label':
			case 'pantheon_secret_name':
			case 'php_constant_name':
			case 'status':
			case 'environment':
			case 'load_context':
				return esc_html( $item->$column_name );
			default:
				return print_r( $item, true );
		}
	}

	/**
	 * Column cb.
	 *
	 * @param object $item The item.
	 * @return string
	 */
	protected function column_cb( $item ): string {
		return sprintf(
			'<input type="checkbox" name="secret[]" value="%s" />',
			$item->id
		);
	}
}
