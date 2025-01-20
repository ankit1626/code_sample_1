<?php
/**
 * Summary of Team_Requests
 *
 * @package wdm-customization
 */

/**
 * Summary of Team_Requests
 */
trait Team_Requests {

	/**
	 * Adds a team request.
	 *
	 * @since 1.0.0
	 * @param int $requestee_id The ID of the user to whom the request is made.
	 * @param int $requester_id The ID of the user making the request.
	 * @param int $event_id The ID of the event for which the request is made.
	 * @throws Exception - If the request could not be added to the database.
	 */
	protected function add_team_requests( int $requestee_id, int $requester_id, int $event_id ) {
		$data = array(
			'requestee_id' => $requestee_id,
			'requester_id' => $requester_id,
			'event_id'     => $event_id,
		);
		$data = apply_filters( 'wdm_add_team_request', $data );
		try {
			$this->validate_team_request_data( $data, true );
			$this->insert_data(
				'wdm_team_requests',
				$data
			);
		} catch ( Exception $e ) {
			throw $e;
		}
		do_action( 'wdm_add_team_request', $data );
	}

	/**
	 * Deletes a team request with the given request id.
	 *
	 * @since 1.0.0
	 * @param int $request_id The id of the team request to delete.
	 * @throws Exception - If the request id is invalid or database object is not found.
	 */
	protected function delete_team_request( int $request_id ) {
		if ( empty( $request_id ) || ! is_int( $request_id ) || $request_id <= 0 ) {
			throw new Exception( 'Invalid Request' );
		}
		global $wpdb;
		if ( ! is_object( $wpdb ) ) {
			throw new Exception( esc_html__( 'Something Went Wrong Please Try Again' ) );
		}
		$wpdb->delete(
			'wdm_team_requests',
			array( 'request_id' => $request_id ),
		);
	}

	/**
	 * Declines a team request with the given request id.
	 *
	 * @since 1.0.0
	 * @param int $request_id The id of the team request to decline.
	 * @param int $event_id The id of the event for which the request is made.
	 * @throws Exception - If the request id is invalid or database object is not found.
	 */
	protected function decline_team_request( int $request_id, int $event_id ) {
		if ( empty( $request_id ) || ! is_int( $request_id ) || $request_id < 1 ) {
			throw new Exception( 'Invalid Request' );
		}
		if ( empty( $event_id ) || ! is_int( $event_id ) || $event_id < 1 ) {
			throw new Exception( 'Invalid Event Id' );
		}
		global $wpdb;
		if ( ! is_object( $wpdb ) ) {
			throw new Exception( esc_html__( 'Database Object not found', 'wdm-customization' ) );
		}
		$event_timings = $this->get_evt_timings( $event_id );
		if ( $event_timings['dead'] <= time() ) {
			throw new Exception( esc_html__( 'Deadline is over.', 'wdm-customization' ) );
		}
		$result = $wpdb->query(
			$wpdb->prepare(
				'UPDATE wdm_team_requests SET status = -1 WHERE request_id = %d AND expires_on > %d',
				$request_id,
				time()
			)
		);
		if ( ! is_int( $result ) && 1 !== $result ) {
			throw new Exception( esc_html__( 'Something Went Wrong Please Try Again' ) );
		}
	}

	/**
	 * Accepts a team request with the given request id.
	 *
	 * @since 1.0.0
	 * @param int $request_id The id of the team request to accept.
	 * @throws Exception - If the request id is invalid or database object is not found.
	 */
	protected function accept_team_request( int $request_id ) {
		if ( empty( $request_id ) || ! is_int( $request_id ) || $request_id < 1 ) {
			throw new Exception( esc_html__( 'Invalid Request', 'wdm-customization' ) );
		}
		global $wpdb;
		if ( ! is_object( $wpdb ) ) {
			throw new Exception( esc_html__( 'Database Object not found', 'wdm-customization' ) );
		}

		$request_obj = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM wdm_team_requests tr WHERE tr.request_id = %d',
				$request_id,
			)
		);
		if ( empty( $request_obj ) || ! is_object( $request_obj ) ) {
			throw new Exception( 'Request Not Found' );
		}
		$event_id      = intval( $request_obj->event_id );
		$requester_id  = intval( $request_obj->requester_id );
		$requestee_id  = intval( $request_obj->requestee_id );
		$team_id       = null === $request_obj->team_id ? null : intval( $request_obj->team_id );
		$expires_on    = intval( $request_obj->expires_on );
		$event_timings = $this->get_evt_timings( $event_id );
		if ( $event_timings['dead'] <= time() || $expires_on <= time() ) {
			throw new Exception( esc_html__( 'Deadline is over.', 'wdm-customization' ) );
		}
		try {
			$constrains = $this->get_max_and_min_participants_for_event( $event_id );
			if ( null !== $team_id && is_int( $team_id ) && $team_id > 0 ) {
				$active_members = $this->get_team_size_or_members( $team_id );
				if ( $active_members >= $constrains['max'] ) {
					$this->decline_team_request( $request_id, $event_id );
					throw new Exception( esc_html__( 'Team is full', 'wdm-customization' ) );
				}
			}
			$wpdb->query( 'START TRANSACTION;' );
			// This adds the team id to wdm_teams table only.
			$team_id = null === $team_id ? $this->create_team( $requester_id, $requestee_id ) : $this->update_existing_team_in_wdm_teams( $team_id, $requestee_id );

			// Removing the new team member from his previous team and updating his team id to null in event registration table.
			$previous_team_of_requestee = $this->get_team_id_for_user( $requestee_id, $event_id );
			if (
				null !== $previous_team_of_requestee &&
				is_int( $previous_team_of_requestee ) &&
				$previous_team_of_requestee > 0 &&
				$previous_team_of_requestee !== $team_id
			) {
				$this->remove_team_member( $previous_team_of_requestee, $requestee_id, $event_id );
			}

			// Updating the event registration table for requester and requestee.
			if ( null === $request_obj->team_id ) {
				$this->update_teamid_in_event_registrations( $event_id, $team_id, $requester_id );
			}
			$this->update_teamid_in_event_registrations( $event_id, $team_id, $requestee_id );

			// Marking the request as accepted.
			$this->mark_request_as_accepted( $request_id, $team_id );

			// Deleting common requests from the requestee account.
			$this->get_common_requests_bw_requestee_and_team( $request_obj );

			// Updating the old request when team_id wasn't present of requester and requestee both.
			if ( null === $request_obj->team_id ) {
				$this->cascade_pending_requests_to_the_new_formed_team( $team_id, $requester_id, $event_id );
			}
			if ( 0 === $previous_team_of_requestee || null === $previous_team_of_requestee ) {
				$this->cascade_pending_requests_to_the_new_formed_team( $team_id, $requestee_id, $event_id );
			}
			$wpdb->query( 'COMMIT;' );
		} catch ( Exception $e ) {
			$wpdb->query( 'ROLLBACK;' );
			throw $e;
		}
	}

	/**
	 * This function deletes common pending requests sent by requester or his team and requestee.
	 * This function is called when a request is accepted.
	 * It prevents the requestee from getting multiple requests from the same person or team.
	 *
	 * @since 1.0.0
	 *
	 * @param object $request_obj The request object with request details.
	 * @throws Exception If the request object is not valid.
	 */
	private function get_common_requests_bw_requestee_and_team( object $request_obj ) {
		global $wpdb;
		if ( ! is_object( $wpdb ) || ! is_object( $request_obj ) ) {
			throw new Exception( esc_html__( 'Database Object not found', 'wdm-customization' ) );
		}

		$request_id   = intval( $request_obj->request_id );
		$event_id     = intval( $request_obj->event_id );
		$requester_id = intval( $request_obj->requester_id );
		$requestee_id = intval( $request_obj->requestee_id );
		$team_id      = null === $request_obj->team_id ? null : intval( $request_obj->team_id );

		$requests_send_by_requester_or_team = null === $team_id ?
		$wpdb->get_col(
			$wpdb->prepare(
				'SELECT requestee_id FROM wdm_team_requests 
				 WHERE team_id IS NULL 
				 AND event_id = %d
				 AND requester_id = %d
				 AND request_id != %d',
				array( $event_id, $requester_id, $request_id )
			)
		) : $wpdb->get_col(
			$wpdb->prepare(
				'SELECT requestee_id FROM wdm_team_requests 
				 WHERE team_id = %d 
				 AND event_id = %d
				 AND request_id != %d',
				array( $team_id, $event_id, $request_id )
			)
		);

		$requests_send_by_new_user = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT requestee_id,request_id FROM wdm_team_requests
				 WHERE team_id IS NULL
				 AND event_id = %d
				 AND requester_id = %d
				 AND request_id != %d
				 AND status = 0',
				array( $event_id, $requestee_id, $request_id )
			)
		);
		if ( is_array( $requests_send_by_new_user ) && ! empty( $requests_send_by_new_user ) && count( $requests_send_by_new_user ) > 0 ) {
			foreach ( $requests_send_by_new_user as $request ) {
				if ( in_array( $request->requestee_id, $requests_send_by_requester_or_team, true ) ) {
					$this->delete_team_request( intval( $request->request_id ) );
				}
			}
		}
	}

	/**
	 * Marks a team request as accepted.
	 *
	 * @since 1.0.0
	 *
	 * @param int $request_id The ID of the team request to mark as accepted.
	 * @param int $team_id The ID of the team to which the request was made.
	 *
	 * @throws Exception If the request ID is invalid or the database object is not found.
	 */
	private function mark_request_as_accepted( int $request_id, int $team_id ) {
		if ( ! is_int( $request_id ) ||
		! is_int( $team_id ) ||
		$team_id < 1 ||
		$request_id < 1
		) {
			throw new Exception( esc_html__( 'Request Not Found', 'wdm-customization' ) );
		}
		global $wpdb;
		$wpdb->update(
			'wdm_team_requests',
			array(
				'status'  => 1,
				'team_id' => $team_id,
			),
			array(
				'request_id' => $request_id,
			),
			array( '%d', '%d' ),
			array( '%d' ),
		);
	}

	/**
	 * Retrieves the IDs of all the users who have requested to join a team.
	 *
	 * @since 1.0.0
	 *
	 * @param int $team_id The ID of the team.
	 *
	 * @return array The IDs of the users who have requested to join the team.
	 *
	 * @throws Exception If the team ID is invalid or the database object is not found.
	 */
	private function get_team_requesters_ids( int $team_id ) {
		global $wpdb;
		if ( ! is_int( $team_id ) ) {
			throw new Exception( esc_html__( 'Invalid value for argument "team_id".', 'wdm-customization' ) );
		}
				$requester_id = $wpdb->get_var(
					$wpdb->prepare(
						'SELECT DISTINCT requester_id FROM wdm_team_requests WHERE team_id = %d AND status = 0',
						$team_id
					)
				);

		return $requester_id;
	}

	/**
	 * Retrieves the size of a team or the team members.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $team_id The ID of the team.
	 * @param string $get     Whether to retrieve the size of the team or the team members. Defaults to size.
	 *
	 * @return int|array The size of the team or the team members.
	 * @throws Exception If the team ID is invalid or the database object is not found.
	 */
	protected function get_team_size_or_members( int $team_id, string $get = 'size' ) {
		global $wpdb;
		if ( ! in_array( $get, array( 'size', 'members' ), true ) ) {
			throw new Exception( esc_html__( 'Invalid value for argument "get".', 'wdm-customization' ) );
		}
		if ( ! is_int( $team_id ) ) {
			throw new Exception( esc_html__( 'Invalid value for argument "team_id".', 'wdm-customization' ) );
		}
		$team_members = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT team_members FROM wdm_teams WHERE team_id = %d ',
				$team_id
			)
		);
		$team_members = maybe_unserialize( $team_members );
		if ( ! is_array( $team_members ) ) {
			return 0;
		}
		return 'size' === $get ? count( $team_members ) : $team_members;
	}

	/**
	 * Removes a user from a team.
	 *
	 * @since 1.0.0
	 *
	 * @param int $team_id The ID of the team from which to remove the user.
	 * @param int $user_id The ID of the user to remove from the team.
	 * @param int $event_id The ID of the event for which the user is removed.
	 *
	 * @throws Exception If the team ID or user ID is invalid or the database object is not found.
	 */
	protected function remove_team_member( int $team_id, int $user_id, int $event_id ) {
		global $wpdb;
		if ( ! is_object( $wpdb ) ) {
			throw new Exception( esc_html__( 'Database Object not found', 'wdm-customization' ) );
		}
		if (
			! is_int( $team_id ) ||
			! is_int( $user_id ) ||
			! is_int( $event_id ) ||
			$team_id <= 0 ||
			$user_id <= 0 ||
			$event_id <= 0
		) {
			throw new Exception( esc_html__( 'Cannot remove the said team member', 'wdm-customization' ) );
		}
		$event_timings = $this->get_evt_timings( $event_id );
		if ( $event_timings['dead'] <= time() ) {
			throw new Exception( esc_html__( 'Deadline is over.', 'wdm-customization' ) );
		}
		$this->update_existing_team_in_wdm_teams( $team_id, $user_id, 'remove' );
		$this->update_teamid_in_event_registrations( $event_id, null, $user_id );
	}


	/**
	 * Creates a new team.
	 *
	 * @since 1.0.0
	 *
	 * @param int $requester_id The ID of the user who requested the team.
	 * @param int $requestee_id The ID of the user who the team is being requested for.
	 * @return int The ID of the newly created team.
	 *
	 * @throws Exception If the team could not be created.
	 */
	private function create_team( int $requester_id, int $requestee_id ) {
		global $wpdb;
		if ( ! is_object( $wpdb ) ) {
			throw new Exception( esc_html__( 'Database Object not found', 'wdm-customization' ) );
		}
		if (
			! is_int( $requester_id ) ||
			! is_int( $requestee_id ) ||
			! get_user_by( 'id', $requester_id ) ||
			! get_user_by( 'id', $requestee_id )
		) {
			throw new Exception( esc_html__( 'Invalid User ID', 'wdm-customization' ) );
		}
		$result = $wpdb->insert(
			'wdm_teams',
			array(
				'team_members' => maybe_serialize( array( $requester_id, $requestee_id ) ),
			),
			array( '%s' )
		);
		if ( is_int( $result ) ) {
			$new_team_id = $wpdb->insert_id;
			return $new_team_id;
		}
		throw new Exception( esc_html( $wpdb->last_error ) );
	}


	/**
	 * Updates an existing team to include a new member.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $team_id The ID of the team to add the new member to.
	 * @param int    $new_member The ID of the user to add to the team.
	 * @param string $action The action to perform. Either "add" to add the user to the team, or "remove" to remove it.
	 *
	 * @throws Exception If the update fails for any reason.
	 */
	private function update_existing_team_in_wdm_teams( int $team_id, int $new_member, string $action = 'add' ) {
		global $wpdb;
		if ( ! is_object( $wpdb ) ) {
			throw new Exception( esc_html__( 'Database Object not found', 'wdm-customization' ) );
		}
		if ( empty( $team_id ) || ! is_int( $team_id ) || $team_id <= 0 ) {
			throw new Exception( esc_html__( 'Invalid Team ID', 'wdm-customization' ) );
		}
		if ( ! is_int( $new_member ) || ! get_user_by( 'id', $new_member ) ) {
			throw new Exception( esc_html__( 'Invalid User', 'wdm-customization' ) );
		}
		if ( ! in_array( $action, array( 'add', 'remove' ), true ) ) {
			throw new Exception( esc_html__( 'Invalid Action', 'wdm-customization' ) );
		}
		$existing_team_members = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT team_members FROM wdm_teams WHERE team_id = %d',
				$team_id
			)
		);
		$existing_team_members = maybe_unserialize( $existing_team_members );
		$existing_team_members = 'add' === $action ? array_merge( $existing_team_members, array( $new_member ) ) : array_diff( $existing_team_members, array( $new_member ) );
		if ( 'remove' === $action && count( $existing_team_members ) === 1 ) {
			$wpdb->delete(
				'wdm_teams',
				array( 'team_id' => $team_id ),
				array( '%d' )
			);
			return;
		}
		$existing_team_members = maybe_serialize( $existing_team_members );
		$results               = $wpdb->update(
			'wdm_teams',
			array(
				'team_members' => $existing_team_members,
			),
			array( 'team_id' => $team_id ),
			array( '%s' ),
			array( '%d' )
		);
		if ( ! is_int( $results ) ) {
			throw new Exception( esc_html( $wpdb->last_error ) );
		}
		return $team_id;
	}

	/**
	 * Updates the team ID of the given user in the event registrations table.
	 *
	 * @since 1.0.0
	 *
	 * @param int $event_id The ID of the event for which to update the team ID.
	 * @param int $team_id The ID of the team to which to update the user's registration.
	 *                     If null, the user's registration will be removed from any team.
	 * @param int $user_id The ID of the user whose team ID is being updated.
	 *
	 * @return int The ID of the team to which the user was added.
	 *
	 * @throws Exception If the update fails for any reason.
	 */
	private function update_teamid_in_event_registrations( int $event_id, ?int $team_id, int $user_id ) {
		global $wpdb;
		if ( ! is_object( $wpdb ) ) {
			throw new Exception( esc_html__( 'Database Object not found', 'wdm-customization' ) );
		}
		if ( empty( $event_id ) || ! is_int( $event_id ) || $event_id <= 0 ) {
			throw new Exception( esc_html__( 'Invalid Event ID', 'wdm-customization' ) );
		}
		if ( null !== $team_id && ( empty( $team_id ) || ! is_int( $team_id ) || $team_id <= 0 ) ) {
			throw new Exception( esc_html__( 'Invalid Team ID', 'wdm-customization' ) );
		}
		if ( empty( $user_id ) || ! is_int( $user_id ) || $user_id <= 0 || ! get_user_by( 'id', $user_id ) ) {
			throw new Exception( esc_html__( 'Invalid User ID', 'wdm-customization' ) );
		}
		$results = $wpdb->update(
			'wdm_event_registration',
			array(
				'team_id' => $team_id,
			),
			array(
				'user_id'  => $user_id,
				'event_id' => $event_id,
			)
		);
		if ( ! is_int( $results ) ) {
			throw new Exception( esc_html( $wpdb->last_error ) );
		}
		return $team_id;
	}



	/**
	 * Returns an array of team requests for the given user and event.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $requestee_id The ID of the user for whom to retrieve the requests.
	 * @param int    $event_id     The ID of the event for which to retrieve the requests.
	 * @param string $type      The type of request to retrieve. Can be either 'incoming' (default) or 'outgoing'.
	 *
	 * @return array An array of team requests. Each element is an object with the following properties:
	 *               - request_id: The ID of the request.
	 *               - requester_id: The ID of the user who sent the request.
	 *               - status: The status of the request. Can be either 0 (pending) or 1 (accepted).
	 *               - user_email: The email address of the user who sent the request.
	 *               - display_name: The display name of the user who sent the request.
	 *
	 * @throws Exception If the query fails for any reason.
	 */
	protected function get_team_request( int $requestee_id, int $event_id, string $type = 'incoming' ) {
		if (
			! isset( $requestee_id ) ||
			empty( $requestee_id ) ||
			! isset( $event_id ) ||
			empty( $event_id ) ||
			! is_int( $requestee_id ) ||
			! is_int( $event_id ) ||
			! is_string( $type ) ||
			! in_array( $type, array( 'incoming', 'outgoing' ), true )
		) {
			throw new Exception( 'Invalid Request' );
		}
		if ( ! get_user_by( 'id', $requestee_id ) ) {
			throw new Exception( 'Invalid User' );
		}
		global $wpdb;
		if ( ! is_object( $wpdb ) ) {
			throw new Exception( esc_html__( 'Something Went Wrong Please Try Again' ) );
		}
		$requests = array();
		if ( 'incoming' === $type ) {
			$requests = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT tr.request_id,tr.requester_id,tr.status, u.user_email, u.display_name FROM wdm_team_requests tr
                JOIN {$wpdb->users} u ON tr.requester_id = u.ID
                WHERE requestee_id = %d AND event_id = %d",
					$requestee_id,
					$event_id
				)
			);
		} elseif ( 'outgoing' === $type ) {
			$current_team_id = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT team_id FROM wdm_event_registration WHERE event_id = %d AND user_id = %d',
					$event_id,
					$requestee_id
				)
			);

			if ( null === $current_team_id->team_id ) {
				$requests = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT tr.request_id,tr.requestee_id,tr.status, u.user_email, u.display_name FROM wdm_team_requests tr
						JOIN {$wpdb->users} u ON tr.requestee_id = u.ID
						WHERE requester_id = %d AND event_id = %d",
						$requestee_id,
						$event_id
					)
				);
			} else {
				$requests = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT tr.request_id,tr.requestee_id,tr.status, u.user_email, u.display_name FROM wdm_team_requests tr
						JOIN {$wpdb->users} u ON tr.requestee_id = u.ID
						WHERE team_id = %d AND event_id = %d AND u.ID != %d",
						$current_team_id->team_id,
						$event_id,
						get_current_user_id(),
					)
				);
			}
		}

		return $requests;
	}
	/**
	 * Validates the team request data.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data The team request data to validate.
	 * @param bool  $insert Whether to also set default values for a new team request.
	 *
	 * @throws Exception If the team request data is invalid.
	 */
	private function validate_team_request_data( array &$data, bool $insert = false ) {
		$required_fields = array( 'requestee_id', 'requester_id', 'event_id' );
		foreach ( $required_fields as $field ) {
			if ( ! isset( $data[ $field ] ) || empty( $data[ $field ] ) || ! is_int( $data[ $field ] ) || 1 > $data[ $field ] ) {
				throw new Exception( 'Missing required field.' );
			}
		}

		if ( ! get_user_by( 'id', $data['requestee_id'] ) || ! get_user_by( 'id', $data['requester_id'] ) ) {
			throw new Exception( 'Invalid user.' );
		}
		$r_sub_accs = is_array( get_user_meta( $data['requestee_id'], 'mpca_corporate_account_id' ) ) ? get_user_meta( $data['requestee_id'], 'mpca_corporate_account_id' ) : array();

		$r_mca_id = array_unique( array_merge( $r_sub_accs, $this->get_owned_corporate_accounts( $data['requestee_id'] ) ) );

		$re_sub_accs = is_array( get_user_meta( $data['requester_id'], 'mpca_corporate_account_id' ) ) ? get_user_meta( $data['requester_id'], 'mpca_corporate_account_id' ) : array();

		$re_mca_id = array_unique( array_merge( $re_sub_accs, $this->get_owned_corporate_accounts( $data['requester_id'] ) ) );

		if ( empty( $r_mca_id ) ||
		empty( $re_mca_id ) ||
		! is_array( $r_mca_id ) ||
		! is_array( $re_mca_id ) ||
		count( $r_mca_id ) !== 1 ||
		count( $re_mca_id ) !== 1 ||
		$r_mca_id[0] !== $re_mca_id[0]
		) {
			throw new Exception( 'Members should belong to same corporate account.' );
		}

		if ( $insert ) {
			$event_timings = $this->get_evt_timings( $data['event_id'] );
			if ( $event_timings['dead'] <= time() ) {
				throw new Exception( esc_html__( 'Deadline is over.', 'wdm-customization' ) );
			}
			$data['status']     = 0;
			$temp_time          = $event_timings['dead'] - ( time() + 1200 );
			$data['expires_on'] = $temp_time > ( 86400 * 7 ) ? time() + ( 86400 * 7 ) : time() + $temp_time;
			global $wpdb;
			if ( ! is_object( $wpdb ) ) {
				throw new Exception( esc_html__( 'Something Went Wrong Please Try Again' ) );
			}
			$team_id         = $wpdb->get_col(
				$wpdb->prepare(
					'Select team_id from wdm_event_registration WHERE event_id = %d AND user_id = %d',
					$data['event_id'],
					$data['requester_id'],
				)
			);
			$data['team_id'] = null === $team_id[0] ? null : intval( $team_id[0] );
			if ( null !== $data['team_id'] ) {
				$check_swapped_request_exists = $wpdb->get_col(
					$wpdb->prepare(
						'SELECT request_id FROM wdm_team_requests WHERE requester_id = %d AND requestee_id = %d AND event_id = %d AND team_id = %d',
						$data['requestee_id'],
						$data['requester_id'],
						$data['event_id'],
						$data['team_id']
					)
				);
			} else {
					$check_swapped_request_exists = $wpdb->get_col(
						$wpdb->prepare(
							'SELECT request_id FROM wdm_team_requests WHERE requester_id = %d AND requestee_id = %d AND event_id = %d AND team_id IS NULL',
							$data['requestee_id'],
							$data['requester_id'],
							$data['event_id']
						)
					);
			}

			if ( null !== $check_swapped_request_exists[0] ) {
				throw new Exception( esc_html__( 'You have already recieved an request from this user please check your incoming requests', 'wdm-customization' ) );
			}
		}
	}

	/**
	 * Returns the team id for a given user and event.
	 *
	 * @param int $user_id The ID of the user.
	 * @param int $event_id The ID of the event.
	 * @return int|null The team id of the user. Null if not found.
	 */
	protected function get_team_id_for_user( int $user_id, int $event_id ) {
		if ( ! is_int( $user_id ) || ! is_int( $event_id ) ) {
			return null;
		}
		global $wpdb;
		$team_id = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT team_id FROM wdm_event_registration WHERE user_id = %d AND event_id = %d',
				array( $user_id, $event_id )
			)
		);
		return intval( $team_id );
	}

	/**
	 * When a user joins a team, all the pending team requests for the user are transferred to the team.
	 *
	 * @since 1.0.0
	 * @param int $team_id The ID of the team.
	 * @param int $user_id The ID of the user.
	 * @param int $event_id The ID of the event.
	 * @throws Exception If the database object is not found.
	 */
	private function cascade_pending_requests_to_the_new_formed_team( int $team_id, int $user_id, int $event_id ) {
		global $wpdb;
		if ( ! is_object( $wpdb ) ) {
			throw new Exception( esc_html__( 'Database Object not found', 'wdm-customization' ) );
		}

				$wpdb->update(
					'wdm_team_requests',
					array(
						'team_id' => $team_id,
					),
					array(
						'requester_id' => $user_id,
						'event_id'     => $event_id,
						'team_id'      => null,
					),
				);
	}
	/**
	 * Gets the members of the team that the current user is a member of, for the given event.
	 *
	 * @since 1.0.0
	 *
	 * @param int $event_id The ID of the event.
	 *
	 * @return array The team members.
	 */
	protected function get_team_members( int $event_id ) {
		global $wpdb;
		$team_members = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT er.user_id,u.user_email,u.display_name,er.team_id
				FROM wdm_event_registration er
				JOIN {$wpdb->users} u ON er.user_id = u.ID
				WHERE er.team_id = %d",
				$this->get_team_id_for_user( get_current_user_id(), $event_id )
			)
		);
		return $team_members;
	}
}
