<?php
/**
 * This file contains all the methods related to event registration.
 *
 * @package wdm-customization
 */

/**
 * Trait Event_Registration
 */
trait Event_Registration {
	/**
	 * Enrolls a user in an event.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id            The ID of the user to enroll.
	 * @param int $event_id           The ID of the event to enroll the user in.
	 * @param int $team_id            The ID of the team to enroll the user in. Defaults to null.
	 * @param int $count_of_team_members The number of team members to enroll in the event. Defaults to null.
	 *
	 * @throws Exception - Will thrown an exception if the user was not enrolled.
	 */
	protected function enroll_user_in_event( int $user_id, int $event_id, int $team_id = -1, int $count_of_team_members = -1 ) {
		$data = array(
			'user_id'               => $user_id,
			'event_id'              => $event_id,
			'team_id'               => $team_id,
			'count_of_team_members' => $count_of_team_members,
		);
		$data = apply_filters( 'wdm_event_enrollment', $data );
		try {
			$this->validate_registration_data( $data );
			$this->insert_data(
				'wdm_event_registration',
				$data
			);
			wp_cache_delete( 'wdm_enrolled_team_events_' . $user_id, 'wdm_customization' );
			wp_cache_delete( 'wdm_enrolled_single_events_' . $user_id, 'wdm_customization' );
			wp_cache_delete( 'wdm_users_enrolled_in_event_' . $event_id, 'wdm_customization' );
		} catch ( Exception $e ) {
			throw $e;
		}
		do_action( 'wdm_new_user_enrolled', $data );
	}



	/**
	 * Validates the data which is required to enroll a user in an event.
	 * Checks if all the required fields are present and are integers.
	 * Checks if the user and event are valid.
	 * Checks if the team id and count_of_team_members are valid.
	 * Sets the team id and count of team members to null if not valid.
	 *
	 * @param mixed $data - The data to validate.
	 *
	 * @throws Exception - Will throw an exception if the data is invalid.
	 */
	private function validate_registration_data( &$data ) {
		$required_keys = array(
			'user_id',
			'event_id',
			'team_id',
			'count_of_team_members',
		);

		foreach ( $required_keys as $required_key ) {
			if ( ! isset( $data[ $required_key ] ) || empty( $data[ $required_key ] ) || ! is_int( $data[ $required_key ] ) ) {
				throw new Exception( esc_html( __( 'All the fields are required and are suppose to be integers', 'wdm-customization' ) ) );
			}
		}
		if ( ! is_a( get_user_by( 'id', $data['user_id'] ), 'WP_User' ) ) {
			throw new Exception( esc_html( __( 'Invalid User', 'wdm-customization' ) ) );
		}
		$event = $this->get_events( $data['event_id'] );
		if ( empty( $event ) || false === $event ) {
			throw new Exception( esc_html( __( 'Invalid Event', 'wdm-customization' ) ) );
		}
		if ( ! class_exists( 'MeprUtils' ) || ! class_exists( 'MeprUser' ) ) {
			throw new Exception( esc_html( __( 'Please install and activate MemberPress', 'wdm-customization' ) ) );
		}
		$eligible_product    = intval( get_option( 'wdm_selected_membership', -1 ) );
		$is_active           = $this->wdm_check_user_active_mepr_products( $data['user_id'], strval( $eligible_product ) );
		$event               = $event[0];
		$event_start_date    = intval( $event->event_start_time );
		$event_expiry_date   = intval( $event->event_end_time );
		$event_deadline_date = intval( $event->deadline );
		if ( $event_start_date <= time() && 1 !== intval( $event->is_alt_event ) ) {
			throw new Exception( esc_html( __( 'The Event has already started. Hence we cannot register you for this event.', 'wdm-customization' ) ) );
		}
		if ( ! $is_active ||
			is_bool( strtotime( MeprUser::get_user_product_expires_at_date( $data['user_id'], $eligible_product ) ) ) ||
			strtotime( MeprUser::get_user_product_expires_at_date( $data['user_id'], $eligible_product ) ) <= $event_expiry_date
		) {
			throw new Exception( esc_html( __( 'You are not subscribed. Please purchase ', 'wdm-customization' ) . get_the_title( $eligible_product ) ) );
		}
		if ( 1 === intval( $event->is_team_event ) && $event_deadline_date <= time() ) {
			throw new Exception( esc_html( __( 'The Deadline has passed. You cannot register for this team event.', 'wdm-customization' ) . $event->event_name . ' - ' . wp_date( WDM_DT_FORMAT, $event->event_start_time, WDM_TIMEZONE ) ) );
		}

		$data['count_of_team_members'] = null;
		$data['team_id']               = null;
	}

	/**
	 * Gets the events in which a user is enrolled.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id The ID of the user for whom to get the enrolled events.
	 *
	 * @return array An array of the events in which the user is enrolled. The array
	 *               will contain the event_id, event_name and event_start_time for
	 *               each event.
	 */
	protected function get_enrolled_team_events( int $user_id ) {
		if ( empty( $user_id ) || ! is_int( $user_id ) || $user_id < 0 ) {
			return array();
		}
		global $wpdb;
		if ( empty( $wpdb ) || ! is_object( $wpdb ) ) {
			return array();
		}
		$events = wp_cache_get( 'wdm_enrolled_team_events_' . $user_id, 'wdm_customization' );
		if ( false !== $events && is_array( $events ) ) {
			return $events;
		}
		$events = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT 
			wdm_event_registration.event_id, 
			wdm_events.event_name, 
			wdm_events.event_start_time,
			wdm_events.deadline,
			wdm_events.min_team_member_count,
			wdm_event_registration.team_id 
			FROM wdm_event_registration
			JOIN wdm_events 
			ON wdm_events.event_id = wdm_event_registration.event_id
			WHERE 
			wdm_events.is_team_event = 1 AND
			wdm_event_registration.user_id = %d
			ORDER BY wdm_events.event_start_time ASC',
				array( $user_id )
			)
		);
		wp_cache_set( 'wdm_enrolled_team_events_' . $user_id, $events, 'wdm_customization', 86400 );
		return $events;
	}

	/**
	 * Gets the events in which a user is enrolled and which are not team events.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id The ID of the user for whom to get the enrolled events.
	 *
	 * @return array An array of the events in which the user is enrolled. The array
	 *               will contain the event_id, event_name and event_start_time for
	 *               each event.
	 */
	protected function get_enrolled_single_event( int $user_id ) {
		if ( empty( $user_id ) || ! is_int( $user_id ) || $user_id < 0 ) {
			return array();
		}
		global $wpdb;
		if ( empty( $wpdb ) || ! is_object( $wpdb ) ) {
			return array();
		}
		$events = wp_cache_get( 'wdm_enrolled_single_events_' . $user_id, 'wdm_customization' );
		if ( false !== $events && is_array( $events ) ) {
			return $events;
		}
		$events = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT 
			wdm_event_registration.event_id, 
			wdm_events.event_name, 
			wdm_events.event_start_time,
			wdm_events.deadline
			FROM wdm_event_registration
			JOIN wdm_events 
			ON wdm_events.event_id = wdm_event_registration.event_id
			WHERE 
			wdm_events.is_team_event = 0 AND
			wdm_event_registration.user_id = %d',
				array( $user_id )
			)
		);
		wp_cache_set( 'wdm_enrolled_single_events_' . $user_id, $events, 'wdm_customization', 86400 );
		return $events;
	}

	/**
	 * Gets all users enrolled in a specific event.
	 *
	 * @since 1.0.0
	 *
	 * @param int $event_id The ID of the event to get the enrolled users for.
	 *
	 * @return array An array of objects with the user_id of the users enrolled in
	 *               the event.
	 */
	protected function get_users_enrolled_in_event( int $event_id ) {
		if ( empty( $event_id ) || ! is_int( $event_id ) || $event_id < 0 ) {
			return array();
		}
		global $wpdb;
		if ( empty( $wpdb ) || ! is_object( $wpdb ) ) {
			return array();
		}
		$users = wp_cache_get( 'wdm_users_enrolled_in_event_' . $event_id, 'wdm_customization' );
		if ( false !== $users && is_array( $users ) ) {
			return $users;
		}
		$users = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT user_id, team_id FROM 
				wdm_event_registration 
				where event_id = %d',
				array( $event_id )
			)
		);
		wp_cache_set( 'wdm_users_enrolled_in_event_' . $event_id, $users, 'wdm_customization', 86400 );
		return $users;
	}

	/**
	 * Removes a user from a specific event.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id The ID of the user to remove from the event.
	 * @param int $event_id The ID of the event to remove the user from.
	 *
	 * @return bool True on success, false on failure.
	 */
	protected function remove_user_from_event( int $user_id, int $event_id ) {
		if ( empty( $user_id ) || ! is_int( $user_id ) || $user_id < 0 ) {
			return false;
		}
		if ( empty( $event_id ) || ! is_int( $event_id ) || $event_id < 0 ) {
			return false;
		}
		global $wpdb;
		if ( empty( $wpdb ) || ! is_object( $wpdb ) ) {
			return false;
		}
		$result = $wpdb->delete(
			'wdm_event_registration',
			array(
				'user_id'  => $user_id,
				'event_id' => $event_id,
			),
			array( '%d', '%d' )
		);
		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM wdm_team_requests WHERE (requestee_id = %d OR requestor_id = %d) AND event_id = %d AND team_id IS NULL',
				$user_id,
				$user_id,
				$event_id
			)
		);
		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM wdm_team_requests WHERE requestor_id = %d AND event_id = %d',
				$user_id,
				$event_id
			)
		);
		if ( is_int( $result ) ) {
			wp_cache_delete( 'wdm_enrolled_single_events_' . $user_id, 'wdm_customization' );
			wp_cache_delete( 'wdm_enrolled_team_events_' . $user_id, 'wdm_customization' );
			wp_cache_delete( 'wdm_users_enrolled_in_event_' . $event_id, 'wdm_customization' );
		}
		return false === $result ? false : true;
	}
}
