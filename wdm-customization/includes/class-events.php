<?php
/**
 * Trait Events
 *
 * @package wdm-customization
 */

/**
 * Trait Events
 */
trait Events {
		/**
		 * Adds a new event to the database.
		 *
		 * @since 1.0.0
		 * @param string $evt_nm - Event Name.
		 * @param int    $is_alt_event - If the event is an alternate event.
		 * @param int    $evt_type - Event Type.
		 * @param int    $evt_st_time - Event Start Time.
		 * @param int    $evt_end_time - Event End Time.
		 * @param int    $min_team_mem_count - Team Member Count.
		 * @param int    $max_team_mem_count - Team Member Count.
		 * @param int    $evt_organizer_id - Team Member Count.
		 * @param int    $deadline - Deadline.
		 * @param int    $alt_evt_id - Alternate Event.
		 * @throws Exception - Will thrown an exception if event details wasn't added to the database.
		 */
	protected function add_event(
		string $evt_nm,
		int $is_alt_event,
		int $evt_type,
		int $evt_st_time,
		int $evt_end_time,
		int $min_team_mem_count,
		int $max_team_mem_count,
		int $evt_organizer_id,
		int $deadline,
		int $alt_evt_id
	) {
		$data = array(
			'event_name'            => $evt_nm,
			'is_alt_event'          => $is_alt_event,
			'is_team_event'         => $evt_type,
			'event_start_time'      => $evt_st_time,
			'event_end_time'        => $evt_end_time,
			'min_team_member_count' => $min_team_mem_count,
			'max_team_member_count' => $max_team_mem_count,
			'event_organizer_id'    => $evt_organizer_id,
			'deadline'              => $deadline,
			'alt_event_id'          => $alt_evt_id,
		);
		$data = apply_filters( 'wdm_event_data', $data );
		try {
			$this->validate_event_data( $data );
			$eve_id = $this->insert_data(
				'wdm_events',
				$data
			);
			wp_cache_delete( 'wdm_events', 'wdm_customization' );
			wp_cache_delete( 'wdm_events_' . $evt_type, 'wdm_customization' );
		} catch ( Exception $e ) {
			throw $e;
		}
		do_action( 'wdm_new_event_added', $eve_id, $data );
	}

		/**
		 * Validates the event data.
		 *
		 * @since 1.0.0
		 * @param array $data - The event data.
		 * @throws Exception - Will thrown an exception if event details wasn't valid.
		 */
	private function validate_event_data( &$data ) {
		$required_keys = array(
			'event_name',
			'is_alt_event',
			'is_team_event',
			'event_start_time',
			'event_end_time',
			'min_team_member_count',
			'max_team_member_count',
			'event_organizer_id',
			'deadline',
			'alt_event_id',
		);
		foreach ( $required_keys as $required_key ) {
			if ( ! isset( $data[ $required_key ] ) || empty( $data[ $required_key ] ) ) {
				throw new Exception( esc_html( __( 'All the fields are required.', 'wdm-customization' ) ) );
			}
		}
		$integer_keys = array_slice( $required_keys, 1 );
		foreach ( $integer_keys as $integer_key ) {
			if ( ! is_int( $data[ $integer_key ] ) ) {
				throw new Exception( esc_html( __( 'The Start time,End time,Deadline time and Team Member Count must be integers', 'wdm-customization' ) ) );
			}
		}
		$event_names = wdm_get_mepr_events();
		if ( strlen( $data['event_name'] ) > 200 || ! in_array( $data['event_name'], $event_names, true ) ) {
			throw new Exception( esc_html( __( 'Invalid Event Name', 'wdm-customization' ) ) );
		}
		if ( ! in_array( $data['is_team_event'], array( 1, -1 ), true ) ) {
			throw new Exception( esc_html( __( 'Please let us know if it is a team-event or not.', 'wdm-customization' ) ) );
		}
		if ( ! in_array( $data['is_alt_event'], array( 1, -1 ), true ) ) {
			throw new Exception( esc_html( __( 'Please let us know if it is an alternate event or not.', 'wdm-customization' ) ) );
		}
		if ( ! function_exists( 'time' ) || $data['event_start_time'] <= time() ) {
			throw new Exception( esc_html( __( 'Start Time must be greater than current time.', 'wdm-customization' ) ) );
		}
		if ( $data['event_start_time'] > $data['event_end_time'] ) {
			throw new Exception( esc_html( __( 'End Time must be greater than Start Time.', 'wdm-customization' ) ) );
		}
		if ( $data['deadline'] <= $data['event_start_time'] || $data['deadline'] >= $data['event_end_time'] ) {
			throw new Exception( esc_html( __( 'Deadline Time must be between Start Time and End Time.', 'wdm-customization' ) ) );
		}
		if ( 1 === $data['is_team_event'] && $data['min_team_member_count'] < 2 ) {
			throw new Exception( esc_html( __( 'Minimum number of members required for a team is 2.', 'wdm-customization' ) ) );
		}
		if ( 1 === $data['is_team_event'] && ( $data['max_team_member_count'] < 2 || $data['max_team_member_count'] < $data['min_team_member_count'] ) ) {
			throw new Exception( esc_html( __( 'Inavlid number of team members', 'wdm-customization' ) ) );
		}
		if ( ! is_int( $data['event_organizer_id'] ) || ! is_int( $data['alt_event_id'] ) ) {
			throw new Exception( esc_html( __( 'Invalid Alternate Event id', 'wdm-customization' ) ) );
		}
		$user = get_user_by( 'id', $data['event_organizer_id'] );
		if ( false === $user || ! $user->has_cap( 'manage_options' ) ) {
			throw new Exception( esc_html( __( 'Invalid User', 'wdm-customization' ) ) );
		}
		if ( 1 === $data['is_team_event'] ) {
			$scheduled_event = $this->get_events( $data['alt_event_id'] );
			if ( ! is_array( $scheduled_event ) || empty( $scheduled_event ) || count( $scheduled_event ) !== 1 ) {
				throw new Exception( esc_html( __( 'Alternate Event Not Found', 'wdm-customization' ) ) );
			}
			$scheduled_event = $scheduled_event[0];
			// The alternative event can start early or later than the original event. Also,since alternative event is a individual event deadline doesn't matter.
			if ( $data['event_end_time'] > $scheduled_event->event_end_time ) {
				throw new Exception( esc_html( __( 'End Time for a team-event can not be less than end time of its alternate event', 'wdm-customization' ) ) );
			}
		}
		/** Client wanted to remove this validation
		foreach ( $scheduled_events as $event ) {
			if ( intval( $event->event_organizer_id ) !== $data['event_organizer_id'] ) {
				continue;
			}
			if ( intval( $event->event_start_time ) <= $data['event_start_time'] && $data['event_start_time'] <= intval( $event->event_end_time ) ) {
				throw new Exception( esc_html( __( 'An other event is already scheduled during that time', 'wdm-customization' ) ) );
			}
			if ( intval( $event->event_start_time ) <= $data['event_end_time'] && $data['event_end_time'] <= intval( $event->event_end_time ) ) {
				throw new Exception( esc_html( __( 'An other event is already scheduled during that time', 'wdm-customization' ) ) );
			}
		}*/
		if ( -1 === $data['is_alt_event'] ) {
			$data['is_alt_event'] = 0;
		}
		if ( -1 === $data['is_team_event'] || 1 === $data['is_alt_event'] ) {
			$data['is_team_event']         = 0;
			$data['alt_event_id']          = null;
			$data['max_team_member_count'] = 0;
			$data['min_team_member_count'] = 0;
		}
	}

	/**
	 * Gets all events or a specific event.
	 *
	 * @since 1.0.0
	 *
	 * @param int $event_id The ID of the event to get. Defaults to -1 which returns all events.
	 *
	 * @return object[] The events.
	 */
	protected function get_events( int $event_id = -1 ) {
		global $wpdb;
		if ( ! is_object( $wpdb ) ) {
			return array();
		}
		if ( -1 === $event_id ) {
			$events = wp_cache_get( 'wdm_events', 'wdm_customization' );
			if ( false === $events ) {
				$events = $wpdb->get_results( 'SELECT * FROM wdm_events' );
				wp_cache_set( 'wdm_events', $events, 'wdm_customization', 86400 );
			}
		} else {
			$events = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM `wdm_events` WHERE `event_id` = %d',
					array( $event_id )
				)
			);
		}
		return $events;
	}

	/**
	 * Gets all events of a specific type.
	 *
	 * @since 1.0.0
	 *
	 * @param string $event_type The name of the event type to get. Must be a string and not empty.
	 *
	 * @return object[] The events of the given type.
	 */
	protected function get_events_by_type( string $event_type ) {
		if ( empty( $event_type ) || ! is_string( $event_type ) ) {
			return array();
		}
		$event_type = esc_sql( $event_type );
		global $wpdb;
		if ( ! is_object( $wpdb ) ) {
			return array();
		}
		$events = wp_cache_get( 'wdm_events_' . $event_type, 'wdm_customization' );
		if ( false !== $events && is_array( $events ) ) {
			return $events;
		}
		$events = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM `wdm_events` WHERE `event_name` = %s',
				array( $event_type )
			)
		);
		wp_cache_set( 'wdm_events_' . $event_type, $events, 'wdm_customization', 86400 );
		return $events;
	}

	/**
	 * Gets the minimum and maximum number of participants allowed in a team
	 * for a given event.
	 *
	 * @since 1.0.0
	 *
	 * @param int $event_id The ID of the event for which to get the
	 *                      participant count.
	 *
	 * @return array An array containing two keys - 'min' and 'max' - which
	 *               represent the minimum and maximum number of participants
	 *               allowed in a team for the given event.
	 *
	 * @throws Exception If the event ID is invalid or the database object is not
	 *                   found.
	 */
	protected function get_max_and_min_participants_for_event( int $event_id ) {
		if ( ! isset( $event_id ) || empty( $event_id ) || ! is_int( $event_id ) || $event_id <= 0 ) {
			throw new Exception( esc_html__( 'Invalid Event ID', 'wdm-customization' ) );
		}
		global $wpdb;
		if ( ! is_object( $wpdb ) ) {
			throw new Exception( esc_html__( 'Database Object not found', 'wdm-customization' ) );
		}
		$event = $this->get_events( $event_id );
		if ( empty( $event ) || ! is_array( $event ) || ! is_object( $event[0] ) ) {
			throw new Exception( esc_html__( 'Event Not Found', 'wdm-customization' ) );
		}
		$event = $event[0];
		return array(
			'max' => intval( $event->max_team_member_count ),
			'min' => intval( $event->min_team_member_count ),
		);
	}

	/**
	 * Gets the start, end and deadline times for a given event.
	 *
	 * @since 1.0.0
	 *
	 * @param int $event_id The ID of the event for which to get the timings.
	 *
	 * @return array An array containing three keys - 'start', 'end', and 'dead' -
	 *               which represent the start time, end time, and deadline time of
	 *               the event.
	 *
	 * @throws Exception If the event ID is invalid or the database object is not
	 *                   found.
	 */
	protected function get_evt_timings( int $event_id ) {
		if ( ! isset( $event_id ) || empty( $event_id ) || ! is_int( $event_id ) || $event_id <= 0 ) {
			throw new Exception( esc_html__( 'Invalid Event ID', 'wdm-customization' ) );
		}
		global $wpdb;
		if ( ! is_object( $wpdb ) ) {
			throw new Exception( esc_html__( 'Database Object not found', 'wdm-customization' ) );
		}
		$event = $this->get_events( $event_id );
		if ( empty( $event ) || ! is_array( $event ) || ! is_object( $event[0] ) ) {
			throw new Exception( esc_html__( 'Event Not Found', 'wdm-customization' ) );
		}
		$event = $event[0];
		return array(
			'start' => intval( $event->event_start_time ),
			'end'   => intval( $event->event_end_time ),
			'dead'  => intval( $event->deadline ),
		);
	}

	/**
	 * Deletes an event from the database.
	 *
	 * @since 1.0.0
	 *
	 * @param int $event_id The ID of the event to delete.
	 *
	 * @return void
	 */
	private function delete_event( int $event_id ) {
		global $wpdb;
		if ( ! is_object( $wpdb ) ) {
			return;
		}
		if ( empty( $event_id ) || ! is_int( $event_id ) || $event_id <= 0 ) {
			return;
		}
		$event = $this->get_events( $event_id );
		if ( empty( $event ) || ! is_array( $event ) || ! is_object( $event[0] ) ) {
			return;
		}
		$event = $event[0];
		$wpdb->delete( 'wdm_events', array( 'event_id' => $event_id ) );
		wp_cache_delete( 'wdm_events_' . $event->event_name, 'wdm_customization' );
		wp_cache_delete( 'wdm_events', 'wdm_customization' );
	}

	/**
	 * Deletes all expired events from the database.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function remove_expired_events() {
		global $wpdb;
		if ( ! is_object( $wpdb ) ) {
			return;
		}
		$events = $this->get_events();
		foreach ( $events as $event ) {
			if ( intval( $event->event_end_time ) <= time() ) {
				$this->delete_event( intval( $event->event_id ) );
			}
		}
	}

	/**
	 * Checks if the given event is a team event or not.
	 *
	 * @since 1.0.0
	 *
	 * @param int $event_id The ID of the event.
	 *
	 * @return bool True if the event is a team event, false if it is not.
	 */
	protected function is_team_event( int $event_id ) {
		$event = $this->get_events( $event_id );
		if ( empty( $event ) || ! is_array( $event ) || ! is_object( $event[0] ) ) {
			return false;
		}
		$event = $event[0];
		return $event->is_team_event;
	}
}
