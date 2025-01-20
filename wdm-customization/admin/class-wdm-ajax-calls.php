<?php
/**
 * Summary of WDM_Ajax_Calls
 *
 * @package wdm-customization
 */

/**
 * Class WDM_Ajax_Calls
 */
class WDM_Ajax_Calls extends DB_Helper {
	/**
	 * Summary of instance
	 *
	 * @var $instance
	 */
	private static $instance = null;
	/**
	 * Private constructor to prevent direct instantiation.
	 *
	 * This function is hooked into WordPress using the action "plugins_loaded".
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		add_action( 'wp_ajax_wdm_get_events', array( $this, 'wdm_get_events' ) );
		add_action( 'wp_ajax_wdm_list_subacc_users', array( $this, 'wdm_list_subacc_users' ) );
		add_action( 'wp_ajax_wdm_add_team_member', array( $this, 'wdm_add_team_member' ) );
		add_action( 'wp_ajax_wdm_get_incoming_requests', array( $this, 'wdm_get_incoming_requests' ) );
		add_action( 'wp_ajax_wdm_decline_team_request', array( $this, 'wdm_decline_team_request' ) );
		add_action( 'wp_ajax_wdm_accept_team_request', array( $this, 'wdm_accept_team_request' ) );
		add_action( 'wp_ajax_wdm_get_team_members', array( $this, 'wdm_get_team_members' ) );
		add_action( 'wp_ajax_wdm_remove_team_members', array( $this, 'wdm_remove_team_member' ) );
	}

	/**
	 * AJAX callback to remove a team member from a team.
	 *
	 * This function is triggered by an AJAX request and sends a JSON response.
	 *
	 * @since 1.0.0
	 */
	public function wdm_remove_team_member() {
		check_ajax_referer( 'sample-code-sec' );
		$event_id = isset( $_POST['event_id'] ) ? intval( $_POST['event_id'] ) : 0;
		$user_id  = isset( $_POST['user_id'] ) ? intval( $_POST['user_id'] ) : 0;
		$team_id  = isset( $_POST['team_id'] ) ? intval( $_POST['team_id'] ) : 0;
		try {
			$this->remove_team_member( $team_id, $user_id, $event_id );
		} catch ( Exception $e ) {
			wp_send_json_error( new WP_Error( 'error', $e->getMessage() ) );
		}
		wp_send_json_success( '', 200 );
	}

	/**
	 * AJAX callback to get all team members for an event.
	 *
	 * This function is triggered by an AJAX request and sends a JSON response.
	 *
	 * @since 1.0.0
	 */
	public function wdm_get_team_members() {
		check_ajax_referer( 'sample-code-sec' );
		$event_id = isset( $_POST['event_id'] ) ? intval( $_POST['event_id'] ) : 0;
		try {
			$members = $this->get_team_members( $event_id );
		} catch ( Exception $e ) {
			wp_send_json_error( new WP_Error( 'error', $e->getMessage() ) );
		}
		wp_send_json_success( $members, 200 );
	}
	/**
	 * Gets the instance of the WDM_Ajax_Calls class.
	 *
	 * @since 1.0.0
	 *
	 * @return WDM_Ajax_Calls The instance of the WDM_Ajax_Calls class.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * AJAX callback to get enrolled team events for the current user.
	 *
	 * This function is triggered by an AJAX request and sends a JSON response.
	 *
	 * @since 1.0.0
	 */
	public function wdm_get_events() {
		check_ajax_referer( 'sample-code-sec' );
		$events = $this->get_enrolled_team_events( get_current_user_id() );
		if ( is_array( $events ) && count( $events ) > 0 ) {
			$upcoming_team_event = $events[0];
			$event_keys          = wdm_get_mepr_events( false );
			if ( is_array( $event_keys ) && count( $event_keys ) > 0 ) {
				foreach ( $event_keys as $obj ) {
					if ( $obj->option_value === $upcoming_team_event->event_name ) {
						$upcoming_team_event->event_o_name = $obj->option_name;
					}
				}
			}
			$upcoming_team_event->event_start_time = wp_date( WDM_DT_FORMAT, $upcoming_team_event->event_start_time, new DateTimeZone( WDM_TIMEZONE ) );
			wp_send_json_success( $upcoming_team_event, 200 );
		}
		wp_send_json_success( '', 200 );
	}


	/**
	 * AJAX callback to add a team member to an event.
	 *
	 * This function is triggered by an AJAX request and sends a JSON response.
	 *
	 * @since 1.0.0
	 */
	public function wdm_add_team_member() {
		check_ajax_referer( 'sample-code-sec' );
		$requestee_id = isset( $_POST['user_id'] ) ? intval( $_POST['user_id'] ) : 0;
		$event_id     = isset( $_POST['event_id'] ) ? intval( $_POST['event_id'] ) : 0;
		try {
			$added = $this->add_team_requests( $requestee_id, get_current_user_id(), $event_id );
		} catch ( Exception $e ) {
			wp_send_json_error( new WP_Error( 'error', $e->getMessage() ) );
		}
		wp_send_json_success( $added, 200 );
	}

	/**
	 * Lists all the sub-accounts of the current user.
	 *
	 * This function is triggered by an AJAX request and sends a JSON response.
	 *
	 * @since 1.0.0
	 */
	public function wdm_list_subacc_users() {
		check_ajax_referer( 'sample-code-sec' );
		global $wpdb;
		if ( ! is_object( $wpdb ) ) {
			wp_send_json_error( new WP_Error( 'error', 'Object not found' ) );
		}
		if ( ! isset( $_POST['event_id'] ) || empty( $_POST['event_id'] ) ) {
			wp_send_json_error( new WP_Error( 'error', 'Event ID not found' ) );
		}
		$user_id           = get_current_user_id();
		$sub_accs          = is_array( get_user_meta( $user_id, 'mpca_corporate_account_id' ) ) ? get_user_meta( $user_id, 'mpca_corporate_account_id' ) : array();
		$user_corporate_id = array_unique( array_merge( $sub_accs, $this->get_owned_corporate_accounts( $user_id ) ) );
		if ( empty( $user_id ) || empty( $user_corporate_id ) || ! is_array( $user_corporate_id ) || count( $user_corporate_id ) > 1 ) {
			wp_send_json_error( new WP_Error( 'error', 'Either you are not logged in with the corporate account or you are enrolled in more than one corporate account' ) );
		}
		$selected_event_id = intval( $_POST['event_id'] );

		$users = $wpdb->get_results(
			$wpdb->prepare(
				"
		SELECT um.user_id, u.user_email, u.display_name 
		FROM {$wpdb->usermeta} um
		JOIN {$wpdb->users} u ON um.user_id = u.ID
		WHERE um.meta_key = 'mpca_corporate_account_id' 
			AND um.meta_value = %d 
			AND um.user_id IN (
			SELECT user_id 
			FROM wdm_event_registration 
			WHERE event_id = %d 
				AND user_id NOT IN (
				SELECT user_id 
				FROM {$wpdb->usermeta} 
				WHERE meta_key = 'mpca_corporate_account_id' 
				GROUP BY user_id 
				HAVING COUNT(*) > 1
				)
			)
			AND um.user_id != %d
			AND um.user_id NOT IN (
				SELECT requestee_id
				FROM wdm_team_requests
				WHERE event_id = %d
				AND requester_id = %d
			)
			AND um.user_id NOT IN (
				SELECT requester_id
				FROM wdm_team_requests
				WHERE event_id = %d
				AND requestee_id = %d
			)
			AND um.user_id NOT IN (
				SELECT requestee_id
				FROM wdm_team_requests
				WHERE event_id = %d
				AND team_id = %d
			);",
				intval( $user_corporate_id[0] ),
				$selected_event_id,
				$user_id,
				$selected_event_id,
				$user_id,
				$selected_event_id,
				$user_id,
				$selected_event_id,
				$this->get_team_id_for_user( $user_id, $selected_event_id )
			)
		);
		try {
			$owner_acc = $this->get_owner_of_corporate_account( $user_corporate_id[0], $user_id, $selected_event_id );
		} catch ( Exception $e ) {
			if ( $e->getMessage() === 'Chapter Account Membership is not selected.' ) {
				$users = array();
				wp_send_json_success( $users, 200 );
			}
			wp_send_json_error( new WP_Error( 'error', $e->getMessage() ) );
		}
		if ( ! empty( $owner_acc ) && is_object( $owner_acc ) ) {

			$req_send_by_owner = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT request_id
					 FROM wdm_team_requests
					 WHERE event_id = %d
				     AND requester_id = %d
					 AND requestee_id = %d',
					$selected_event_id,
					$owner_acc->user_id,
					get_current_user_id(),
				)
			);

			$req_received_by_owner = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT request_id
					 FROM wdm_team_requests
					 WHERE event_id = %d
				     AND requester_id = %d
					 AND requestee_id = %d',
					$selected_event_id,
					get_current_user_id(),
					$owner_acc->user_id,
				)
			);

			$req_received_by_owner_from_team = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT request_id
					FROM wdm_team_requests
					WHERE event_id = %d
					AND team_id = %d
					AND requestee_id = %d',
					$selected_event_id,
					$this->get_team_id_for_user( get_current_user_id(), $selected_event_id ),
					$owner_acc->user_id
				)
			);

			if ( empty( $req_send_by_owner ) && empty( $req_received_by_owner ) && empty( $req_received_by_owner_from_team ) ) {
				$users[] = $owner_acc;
			}
		}
		wp_send_json_success( $users, 200 );
	}

	/**
	 * Returns the owner of a given corporate account.
	 *
	 * @param int   $corporate_account_id The ID of the corporate account.
	 * @param int   $s_user_id The ID of the user to exclude.
	 * @param mixed $event_id The ID of the event.
	 *
	 * @return array|object The data of the owner of the corporate account. Empty array if not found.
	 * @throws Exception If the chapter account is not set.
	 */
	private function get_owner_of_corporate_account( $corporate_account_id, $s_user_id, $event_id ) {
		if ( empty( $corporate_account_id ) || empty( $s_user_id ) ) {
			return array();
		}
		global $wpdb;
		$owner_id = $wpdb->get_var(
			$wpdb->prepare(
				"Select user_id from {$wpdb->prefix}mepr_corporate_accounts where id = %d",
				intval( $corporate_account_id )
			)
		);
		if ( empty( $owner_id ) || ! is_string( $owner_id ) ) {
			return array();
		}
		$eligible_product      = strval( get_option( 'wdm_chapter_acc_membership', '-1' ) );
		$eligible_product_paid = strval( get_option( 'wdm_selected_membership', '-1' ) );
		if ( ! is_string( $eligible_product ) || empty( $eligible_product ) || '-1' === $eligible_product ) {
			throw new Exception( 'Chapter Account Membership is not selected.' );
		}
		if ( ! is_string( $eligible_product_paid ) || empty( $eligible_product_paid ) || '-1' === $eligible_product_paid ) {
			throw new Exception( 'Chapter Account Membership is not selected.' );
		}
		$is_active  = $this->wdm_check_user_active_mepr_products( $owner_id, $eligible_product );
		$is_active2 = $this->wdm_check_user_active_mepr_products( $owner_id, $eligible_product_paid );
		if ( strval( $s_user_id ) === $owner_id ) {
			if ( ! $is_active || ! $is_active2 ) {
				throw new Exception( esc_html( __( 'You are not subscribed to the required chapter account.', 'wdm-customization' ) . get_the_title( $eligible_product ) ) );
			}
			return array();
		}
		$is_active3 = $wpdb->get_var(
			$wpdb->prepare(
				'Select * from wdm_event_registration where event_id = %d and user_id = %d',
				array( $event_id, $owner_id )
			)
		);
		$is_active3 = ( empty( $is_active3 ) || ! is_string( $is_active3 ) ) ? false : true;
		if ( $is_active && $is_active2 && $is_active3 ) {
			$user_data = get_userdata( $owner_id );
			$user_data = (object) array(
				'user_id'      => $user_data->data->ID,
				'user_email'   => $user_data->data->user_email,
				'display_name' => $user_data->data->display_name,
			);
			return $user_data;
		}
		return array();
	}

	/**
	 * AJAX callback to get all incoming team requests for the current user.
	 *
	 * This function is triggered by an AJAX request and sends a JSON response.
	 *
	 * @since 1.0.0
	 */
	public function wdm_get_incoming_requests() {
		check_ajax_referer( 'sample-code-sec' );
		$event_id = isset( $_POST['event_id'] ) ? intval( $_POST['event_id'] ) : 0;
		$type     = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : '';
		try {

			$requests = $this->get_team_request( get_current_user_id(), $event_id, $type );
		} catch ( Exception $e ) {
			wp_send_json_error( new WP_Error( 'error', $e->getMessage() ) );
		}
		wp_send_json_success( $requests, 200 );
	}

	/**
	 * AJAX callback to decline a team request.
	 *
	 * This function is triggered by an AJAX request and sends a JSON response.
	 *
	 * @since 1.0.0
	 */
	public function wdm_decline_team_request() {
		check_ajax_referer( 'sample-code-sec' );
		$request_id = isset( $_POST['request_id'] ) ? intval( $_POST['request_id'] ) : 0;
		$event_id   = isset( $_POST['event_id'] ) ? intval( $_POST['event_id'] ) : 0;
		try {
			$this->decline_team_request( $request_id, $event_id );
		} catch ( Exception $e ) {
			wp_send_json_error( new WP_Error( 'error', $e->getMessage() ) );
		}
		wp_send_json_success( '', 200 );
	}

	/**
	 * AJAX callback to accept a team request.
	 *
	 * This function is triggered by an AJAX request and sends a JSON response.
	 *
	 * @since 1.0.0
	 */
	public function wdm_accept_team_request() {
		check_ajax_referer( 'sample-code-sec' );
		$request_id = isset( $_POST['request_id'] ) ? intval( $_POST['request_id'] ) : 0;
		try {
			$this->accept_team_request( $request_id );
		} catch ( Exception $e ) {
			wp_send_json_error( new WP_Error( 'error', $e->getMessage() ) );
		}
		wp_send_json_success( '', 200 );
	}
}
