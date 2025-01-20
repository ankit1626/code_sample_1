<?php
/**
 * File comment.
 *
 * @package wdm-customization
 */

/**
 * Class comment.
 */
class DB_Helper {

	use Events;
	use Event_Registration;
	use Team_Requests;

	/**
	 * The single instance of the class.
	 *
	 * @var object $instance
	 */
	private static $instance;

	/**
	 * Private constructor to prevent direct instantiation.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
	}
	/**
	 * Gets the instance of the DB_Helper class.
	 *
	 * @since 1.0.0
	 *
	 * @return DB_Helper The instance of the DB_Helper class.
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}


	/**
	 * Inserts data into the given table.
	 *
	 * @since 1.0.0
	 *
	 * @param string $table The name of the table to insert into.
	 * @param array  $data  The data to insert.
	 * @throws Exception - Will throw an exception if the data was not inserted.
	 */
	protected function insert_data( $table, $data ) {
		global $wpdb;
		if ( ! is_object( $wpdb ) ) {
			throw new Exception( esc_html( __( 'Database object not found.', 'wdm-customization' ) ) );
		}
		$result = $wpdb->insert( $table, $data );
		if ( ! is_int( $result ) ) {
			throw new Exception( esc_html( __( 'Unable to insert the data.', 'wdm-customization' ) ) );
		}
		return empty( $wpdb->insert_id ) ? -1 : $wpdb->insert_id;
	}

	/**
	 * Gets the IDs of the corporate accounts owned by a user.
	 *
	 * @param string $user_id The ID of the user.
	 * @return array The IDs of the corporate accounts owned by the user. Empty array if not found.
	 */
	protected function get_owned_corporate_accounts( string $user_id ) {
		if ( ! is_string( $user_id ) || empty( $user_id ) || ! method_exists( 'MPCA_Corporate_Account', 'get_all_by_user_id' ) || ! method_exists( 'MPCA_Corporate_Account', 'find_corporate_account_by_obj_id' ) || ! method_exists( 'MPCA_Corporate_Account', 'get_obj' ) ) {
			return array();
		}
		$corporate_accounts = MPCA_Corporate_Account::get_all_by_user_id( $user_id );
		if ( ! is_array( $corporate_accounts ) ) {
			return array();
		}

		$active_corporate_accounts = array();
		foreach ( $corporate_accounts as $corporate_account ) {
			if ( ! isset( $corporate_account->obj_id ) ||
				empty( $corporate_account->obj_id ) ||
				! isset( $corporate_account->obj_type ) ||
				empty( $corporate_account->obj_type ) ) {
				continue;
			}
			$corporate_account_obj = MPCA_Corporate_Account::find_corporate_account_by_obj_id( $corporate_account->obj_id, $corporate_account->obj_type );
			if ( ! is_a( $corporate_account_obj, 'MPCA_Corporate_Account' ) ) {
				continue;
			}
			$trans_or_sub = $corporate_account_obj->get_obj();
			if ( ! is_object( $trans_or_sub ) ) {
				continue;
			}
			if ( method_exists( $trans_or_sub, 'is_active' ) && $trans_or_sub->is_active() ) {
				$active_corporate_accounts[] = $corporate_account->id;
			}
		}
		return $active_corporate_accounts;
	}

	/**
	 * Gets the active Mepr products for a user.
	 *
	 * @param string $user_id The ID of the user.
	 * @return array The IDs of the active Mepr products. Empty array if not found.
	 */
	protected function wdm_get_user_active_mepr_products( string $user_id ) {
		if ( ! is_string( $user_id ) || empty( $user_id ) ) {
			return array();
		}
		$user = new MeprUser( $user_id );
		if ( false === $user || ! isset( $user->ID ) ) {
			return array();
		}
		$active_prodcuts = $user->active_product_subscriptions( 'ids' );
		return $active_prodcuts;
	}

	/**
	 * Checks if a user has a given Mepr product.
	 *
	 * @since 1.0.0
	 *
	 * @param string $user_id    The ID of the user.
	 * @param string $product_id The ID of the Mepr product to check.
	 * @return bool True if the user has the Mepr product, false otherwise.
	 */
	protected function wdm_check_user_active_mepr_products( string $user_id, string $product_id ) {
		$active_prds = $this->wdm_get_user_active_mepr_products( $user_id );
		if ( empty( $active_prds ) || ! is_array( $active_prds ) ) {
			return false;
		}
		if ( ! is_string( $product_id ) ) {
			return false;
		}

		if ( ! in_array( $product_id, $active_prds, true ) ) {
			return false;
		}
		return true;
	}
}
