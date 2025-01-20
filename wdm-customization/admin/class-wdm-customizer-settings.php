<?php
/**
 * File Comment.
 *
 * @package wdm-customization
 */

/**
 * Class Comment.
 */
class WDM_Customizer_Settings extends DB_Helper {
	/**
	 *  The single instance of the class.
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
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'init', array( $this, 'add_cstm_endpoint' ) );
		add_action( 'template_redirect', array( $this, 'cstm_endpoint_template_redirect' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_init', array( $this, 'wdm_create_cstm_settings' ) );
		add_action(
			'admin_init',
			function () {
				if ( ! wp_next_scheduled( 'wdm_daily_event', array() ) ) {
					wp_schedule_event( time(), 'daily', 'wdm_daily_event', array() );
				}
			},
			10,
			1
		);
		add_action( 'mepr-txn-status-complete', array( $this, 'wdm_auto_enroll_user_in_events' ), 10, 1 );
		add_action( 'wdm_new_event_added', array( $this, 'wdm_auto_enroll_bulk_user_in_events' ), 10, 2 );
		add_action( 'show_user_profile', array( $this, 'add_enrollment_details_to_user_profile' ), 10, 1 );
		add_action( 'edit_user_profile', array( $this, 'add_enrollment_details_to_user_profile' ), 10, 1 );
		add_action( 'template_redirect', array( $this, 'restrict_access_to_site' ) );
		add_action(
			'wdm_new_event_added',
			function ( int $event_id, array $data ) {
				if ( 0 === $data['is_team_event'] ) {
					return;
				}
				$deadline = intval( $data['deadline'] );
				wp_schedule_single_event( $deadline, 'wdm_enroll_user_in_alternate_event', array( $event_id, $data ), false );
			},
			10,
			2
		);
		add_action( 'wdm_enroll_user_in_alternate_event', array( $this, 'enroll_user_in_alternate_event' ), 10, 2 );

		add_action( 'wdm_daily_event', array( $this, 'wdm_cron_jobs' ), 10, 1 );
		add_filter( 'update_user_metadata', array( $this, 'wdm_restrict_event_type_update' ), 10, 5 );
		add_action( 'updated_user_meta', array( $this, 'wdm_handel_event_type_update' ), 10, 4 );
	}

	/**
	 * Handle the update of mepr_bh meta key.
	 *
	 * Remove the user from all the events they were enrolled in and then
	 * auto enroll them in new events based on the new value of mepr_bh.
	 *
	 * @param int    $meta_id    The ID of the updated meta key.
	 * @param int    $user_id    The ID of the user whose meta key was updated.
	 * @param string $meta_key   The key of the updated meta key.
	 * @param mixed  $meta_value The value of the updated meta key.
	 */
	public function wdm_handel_event_type_update( $meta_id, $user_id, $meta_key, $meta_value ) {
		if ( 'mepr_bh' !== $meta_key ) {
			return;
		}
		global $wpdb;
		$all_enrolled_events = $wpdb->get_results(
			$wpdb->prepare(
				'Select event_id,team_id from wdm_event_registration where user_id = %d',
				$user_id
			)
		);
		if ( ! empty( $all_enrolled_events ) ) {
			foreach ( $all_enrolled_events as $enrolled_event ) {
				$event_id = intval( $enrolled_event->event_id );
				$team_id  = intval( $enrolled_event->team_id );
				if ( $team_id > 0 ) {
					$this->remove_team_member( $team_id, $user_id, $event_id );
				}
				$this->remove_user_from_event( $user_id, $event_id );
			}
		}
		$this->auto_enroll_in_events( $user_id );
	}

	/**
	 * Prevents the user from updating the event type if the deadline date for some event in which the user is enrolled has been passed.
	 *
	 * Also, removes the user from all events in which the user is enrolled, and then auto-enrolls the user in events according to the new event type.
	 *
	 * @since 1.0.0
	 *
	 * @param bool   $check      Whether to update the user meta or not.
	 * @param int    $user_id    The ID of the user whose meta is being updated.
	 * @param string $meta_key   The meta key being updated.
	 * @param mixed  $meta_value The new value for the meta key.
	 * @param mixed  $prev_value The previous value for the meta key.
	 *
	 * @return bool Whether to update the user meta or not.
	 */
	public function wdm_restrict_event_type_update( $check, $user_id, $meta_key, $meta_value, $prev_value ) {
		if ( 'mepr_bh' !== $meta_key ) {
			return $check;
		}
		$user_id = intval( $user_id );
		global $wpdb;
		if ( is_object( $wpdb ) ) {
			$result = $wpdb->get_results(
				$wpdb->prepare(
					'Select event_id,deadline from wdm_events where event_id IN (select event_id from wdm_event_registration where user_id = %d) AND deadline <= %d',
					$user_id,
					time()
				)
			);
			if ( ! empty( $result ) ) {
				return false;  // Cannot update because the deadline date for some event in which the user is enrolled has been passed.
			}
		}
		return $check;
	}

	/**
	 * Removes expired events.
	 *
	 * @since 1.0.0
	 */
	public function wdm_cron_jobs() {
		$this->remove_expired_events();
	}

	/**
	 * Enrolls users in alternate events if the team size is less than the required amount.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $event_id The ID of the event to enroll users in alternate events for.
	 * @param array $data     The data of the event in which the users are enrolled in.
	 */
	public function enroll_user_in_alternate_event( int $event_id, array $data ) {
		$useless_teams   = array();
		$alternate_event = intval( $data['alt_event_id'] );
		$users           = $this->get_users_enrolled_in_event( intval( $event_id ) );
		foreach ( $users as $user ) {
			$user_id   = intval( $user->user_id );
			$team_id   = intval( $user->team_id );
			$team_size = intval( $this->get_team_size_or_members( $team_id ) );
			if ( intval( $data['min_team_member_count'] ) > $team_size ) {
				$this->enroll_user_in_event( $user_id, $alternate_event );
				$useless_teams[] = $team_id;
			}
		}
		if ( ! is_array( $useless_teams ) || count( $useless_teams ) < 1 ) {
			return;
		}
		$useless_teams = implode( ',', array_unique( $useless_teams ) );
		global $wpdb;
		if ( ! is_object( $wpdb ) ) {
			return;
		}
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM wdm_teams WHERE team_id IN ({$useless_teams})" // phpcs:ignore
			)
		);
	}

	/**
	 * Redirects users to the teams page if they have an upcoming deadline
	 * within 14 days for a team event they are enrolled in.
	 *
	 * This function is hooked into the "template_redirect" action which
	 * is triggered before the page content is generated. It checks if
	 * the user is logged in and if they have any team events with an
	 * upcoming deadline within 14 days. If so, it redirects the user to
	 * the teams page.
	 *
	 * @since 1.0.0
	 */
	public function restrict_access_to_site() {
		if ( ! is_user_logged_in() ) {
			return;
		}
		global $wp;
		if ( strpos( $wp->request, 'account' ) !== false ) {
			return;
		}
		$user_id     = get_current_user_id();
		$team_events = $this->get_enrolled_team_events( $user_id );
		foreach ( $team_events as $event ) {
			if (
				! wp_doing_ajax() &&

				intval( $event->deadline ) - time() <= 86400 * 14 &&
				intval( $event->min_team_member_count ) > $this->get_team_size_or_members( intval( $event->team_id ) )
			) {
				wp_safe_redirect( home_url( 'teams' ) );
				exit;
			}
		}
	}

	/**
	 * Adds a section to the user profile page to show the events a user is enrolled in.
	 *
	 * This function is hooked into the "show_user_profile" and "edit_user_profile"
	 * actions which are triggered when the user profile page is loaded. It
	 * checks if the user is logged in and if they have any events they are
	 * enrolled in. If so, it loads the user-profile.php template which shows
	 * the events.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_User $user_profile The user profile object.
	 */
	public function add_enrollment_details_to_user_profile( $user_profile ) {
		if ( empty( $user_profile ) || ! is_object( $user_profile ) || ! is_a( $user_profile, 'WP_User' ) ) {
			return;
		}
		$user_id       = intval( $user_profile->ID );
		$single_events = $this->get_enrolled_single_event( $user_id );
		$team_events   = $this->get_enrolled_team_events( $user_id );
		require_once WDM_CUSTOMIZATION_DIR . '/templates/user-profile.php';
	}


	/**
	 * Handles the template redirect for the custom endpoint 'teams'.
	 *
	 * This function is hooked into WordPress using the action "template_redirect".
	 *
	 * @since 1.0.0
	 */
	public function cstm_endpoint_template_redirect() {
		global $wp_query;
		if ( ! isset( $wp_query->query_vars['wdm-teams'] ) ) {
			return;
		}
		require_once WDM_CUSTOMIZATION_DIR . '/templates/team-management-react.php';
	}
	/**
	 * Adds a custom endpoint for teams.
	 *
	 * This function is hooked into WordPress using the action "init".
	 *
	 * @since 1.0.0
	 */
	public function add_cstm_endpoint() {
		if ( ! defined( 'EP_ROOT' ) ) {
			return;
		}
		add_rewrite_endpoint( 'teams', EP_ROOT, 'wdm-teams' );
	}



	/**
	 * Registers the customizer setting to select the membership.
	 *
	 * @since 1.0.0
	 */
	public function wdm_create_cstm_settings() {
		register_setting( 'general', 'wdm_selected_membership' );
		register_setting( 'general', 'wdm_chapter_acc_membership' );
		add_settings_field(
			'wdm_selected_membership',
			__( 'Member Account Membership', 'wdm-customization' ),
			fn() => $this->render_membership_selector( 'wdm_selected_membership' ),
			'general',
		);
		add_settings_field(
			'wdm_chapter_acc_membership',
			__( 'Chapter Account Membership', 'wdm-customization' ),
			fn() => $this->render_membership_selector( 'wdm_chapter_acc_membership' ),
			'general',
		);
	}

	/**
	 * Renders a dropdown selector for selecting a membership.
	 *
	 * @since 1.0.0
	 *
	 * @param string $id The ID of the setting to render the selector for.
	 */
	private function render_membership_selector( string $id ) {
		echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $id ) . '">';
				$memberships = get_posts(
					array(
						'post_type'      => 'memberpressproduct',
						'posts_per_page' => -1,
					)
				);
				echo '<option value="-1"' . selected( -1, get_option( $id ), false ) . '>--Select--</option>';
		foreach ( $memberships as $membership ) {
			if ( ! is_a( $membership, 'WP_Post' ) ) {
				continue;
			}
			echo '<option value="' . esc_attr( $membership->ID ) . '"' . selected( $membership->ID, get_option( $id ), false ) . '>' . esc_html( $membership->post_title ) . '</option>';
		}
				echo '</select>';
	}

	/**
	 * Register the admin menu.
	 *
	 * @since 1.0.0
	 */
	public function admin_menu() {
		add_menu_page(
			__( 'Customizer Settings', 'wdm-customization' ),
			__( 'Customizer Settings', 'wdm-customization' ),
			'manage_options',
			'wdm-customization-settings',
			fn() => $this->settings_page(),
		);
		add_submenu_page(
			'wdm-customization-settings',
			__( 'Export Events Data', 'wdm-customization' ),
			__( 'Export Events Data', 'wdm-customization' ),
			'manage_options',
			'wdm-export-events-data',
			fn() => $this->export_events_data(),
		);
	}

	/**
	 * Handles the export of the events data.
	 *
	 * @since 1.0.0
	 */
	private function export_events_data() {
		echo '<h2>' . esc_html( __( 'Export events data', 'wdm-customization' ) ) . '</h2>';
		if ( isset( $_POST['wdm_export_events_data_submit'] ) && isset( $_POST['wdm_selected_event'] ) ) {
			check_admin_referer( 'sample-sec-code' );
			echo '<form action="" method="post">';
			echo '<select id="wdm_selected_event" name="wdm_selected_event">';
			$events = $this->get_events();
			echo '<option value="-1"' . selected( -1, sanitize_text_field( wp_unslash( $_POST['wdm_selected_event'] ) ), false ) . '>' . esc_html( __( 'All', 'wdm-customization' ) ) . '</option>';
			foreach ( $events as $event ) {
				echo '<option value="' . esc_attr( $event->event_id ) . '"' . selected( $event->event_id, sanitize_text_field( wp_unslash( $_POST['wdm_selected_event'] ) ), false ) . '>' . esc_html( $event->event_name ) . ' - ' . esc_html( wp_date( WDM_DT_FORMAT, $event->event_start_time, new DateTimeZone( WDM_TIMEZONE ) ) ) . '</option>';
			}
			echo '</select>';
			wp_nonce_field( 'sample-sec-code' );
			echo '<input type="submit" name="wdm_export_events_data_submit" value="Export"/>';
			echo '</form>';
			global $wpdb;
			if ( intval( $_POST['wdm_selected_event'] ) === -1 ) {
				$results = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT wr.event_id,we.event_name,wr.team_id, GROUP_CONCAT(wu.user_email) AS user_emails, GROUP_CONCAT(wu.display_name) AS user_names, GROUP_CONCAT(wr.user_id) AS user_ids FROM `wdm_event_registration` AS wr JOIN `wdm_events` AS we ON wr.event_id = we.event_id JOIN {$wpdb->users} AS wu ON wr.user_id = wu.ID GROUP BY wr.team_id, wr.event_id ORDER BY wr.event_id ASC, wr.team_id DESC;"
					)
				);
				echo '<div id="wdm_export_events_data_container">';
				echo '<table id="wdm_export_events_data_teams">';
				echo '<thead>';

				echo '<tr>';
				echo '<th>' . esc_html__( 'Event ID', 'wdm-customization' ) . '</th>';
				echo '<th>' . esc_html__( 'Event Name', 'wdm-customization' ) . '</th>';
				echo '<th>' . esc_html__( 'Participant', 'wdm-customization' ) . '</th>';
				echo '<th>' . esc_html__( 'Participant Email', 'wdm-customization' ) . '</th>';
				echo '<th>' . esc_html__( 'School Name', 'wdm-customization' ) . '</th>';
				echo '</tr>';
				echo '</thead>';
				echo '<tbody>';

				foreach ( $results as $result ) {
					$event_id   = $result->event_id;
					$event_name = $result->event_name;
					$team_id    = intval( $result->team_id );
					if ( $team_id > 0 ) {
						$user_emails = $result->user_emails;
						$user_names  = $result->user_names;
						$user_ids    = explode( ',', $result->user_ids );
						echo '<tr>';
						echo '<td>' . esc_html( $event_id ) . '</td>';
						echo '<td>' . esc_html( $event_name ) . '</td>';
						echo '<td>' . esc_html( $user_names ) . '</td>';
						echo '<td>' . esc_html( $user_emails ) . '</td>';
						echo '<td>' . esc_html( get_user_meta( $user_ids[0], 'mepr_school_name', true ) ) . '</td>';
						echo '</tr>';
					} else {
						$user_emails = explode( ',', $result->user_emails );
						$user_names  = explode( ',', $result->user_names );
						$user_ids    = explode( ',', $result->user_ids );
						foreach ( $user_emails as $key => $value ) {
							echo '<tr>';
							echo '<td>' . esc_html( $event_id ) . '</td>';
							echo '<td>' . esc_html( $event_name ) . '</td>';
							echo '<td>' . esc_html( $user_names[ $key ] ) . '</td>';
							echo '<td>' . esc_html( $value ) . '</td>';
							echo '<td>' . esc_html( get_user_meta( $user_ids[ $key ], 'mepr_school_name', true ) ) . '</td>';
							echo '</tr>';
						}
					}
				}
				echo '</tbody>';
				echo '</table>';
				echo '</div>';
			} elseif ( $this->is_team_event( sanitize_text_field( wp_unslash( $_POST['wdm_selected_event'] ) ) ) === '1' ) {
				$results = $wpdb->get_results(
					$wpdb->prepare(
						'SELECT GROUP_CONCAT(er.user_id) AS team_members,COUNT(er.user_id) AS count_of_team_members ,er.team_id 
						FROM wdm_event_registration AS er 
						WHERE event_id = %d AND
                    	team_id IS NOT NULL
						GROUP BY er.team_id
                    	ORDER BY count_of_team_members DESC;',
						intval( $_POST['wdm_selected_event'] )
					)
				);
				echo '<div id="wdm_export_events_data_container">';
				echo '<table id="wdm_export_events_data_teams">';
				echo '<thead>';

				echo '<tr>';
				echo '<th>' . esc_html__( 'Team No', 'wdm-customization' ) . '</th>';
				echo '<th>' . esc_html__( 'Team Member', 'wdm-customization' ) . '</th>';
				echo '<th>' . esc_html__( 'Team Member Email', 'wdm-customization' ) . '</th>';
				echo '<th>' . esc_html__( 'School Name', 'wdm-customization' ) . '</th>';
				echo '</tr>';
				echo '</thead>';
				echo '<tbody>';
				foreach ( $results as $key => $result ) {
					$team_members = explode( ',', $result->team_members );
					$school_name  = get_user_meta( $team_members[0], 'mepr_school_name', true );
					$team_members = array_map( 'intval', $team_members );
					$team_members = array_map( 'get_userdata', $team_members );
					$team_no      = strval( $key + 1 );
					foreach ( $team_members as $member ) {
						echo '<tr>';
						echo '<td>' . esc_html( $team_no ) . '</td>';
						echo '<td>' . esc_html( $member->display_name ) . '</td>';
						echo '<td>' . esc_html( $member->user_email ) . '</td>';
						echo '<td>' . esc_html( $school_name ) . '</td>';
						echo '</tr>';
					}
				}
				echo '</tbody>';
				echo '</table>';
				echo '</div>';
			} elseif ( $this->is_team_event( sanitize_text_field( wp_unslash( $_POST['wdm_selected_event'] ) ) ) === '0' ) {
				$results = $wpdb->get_results(
					$wpdb->prepare(
						'SELECT user_id
						FROM wdm_event_registration
						WHERE event_id = %d AND
                    	team_id IS NULL;',
						intval( $_POST['wdm_selected_event'] )
					)
				);
				echo '<div id="wdm_export_events_data_container">';
				echo '<table id="wdm_export_events_data_single">';
				echo '<thead>';

				echo '<tr>';
				echo '<th>' . esc_html__( 'User No', 'wdm-customization' ) . '</th>';
				echo '<th>' . esc_html__( 'Enrollee Name', 'wdm-customization' ) . '</th>';
				echo '<th>' . esc_html__( 'Enrollee Email', 'wdm-customization' ) . '</th>';
				echo '<th>' . esc_html__( 'School Name', 'wdm-customization' ) . '</th>';
				echo '</tr>';
				echo '</thead>';
				echo '<tbody>';
				foreach ( $results as $key => $result ) {
					$user = get_userdata( intval( $result->user_id ) );
					echo '<tr>';
					echo '<td>' . esc_html( $key + 1 ) . '</td>';
					echo '<td>' . esc_html( $user->display_name ) . '</td>';
					echo '<td>' . esc_html( $user->user_email ) . '</td>';
					echo '<td>' . esc_html( get_user_meta( $user->ID, 'mepr_school_name', true ) ) . '</td>';
					echo '</tr>';

				}
				echo '</tbody>';
				echo '</table>';
				echo '</div>';
			}
		} else {
			echo '<form action="" method="post">';
			echo '<select id="wdm_selected_event" name="wdm_selected_event">';
			$events = $this->get_events();
			echo '<option value="-1">' . esc_html( __( 'All', 'wdm-customization' ) ) . '</option>';
			foreach ( $events as $event ) {
				echo '<option value="' . esc_attr( $event->event_id ) . '">' . esc_html( $event->event_name ) . ' - ' . esc_html( wp_date( WDM_DT_FORMAT, $event->event_start_time, new DateTimeZone( WDM_TIMEZONE ) ) ) . '</option>';
			}
			echo '</select>';
			wp_nonce_field( 'sample-sec-code' );
			echo '<input type="submit" name="wdm_export_events_data_submit" value="Export"/>';
			echo '</form>';
		}
	}

	/**
	 * Outputs the settings page content.
	 *
	 * @since 1.0.0
	 */
	private function settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			echo '<b>' . esc_html( __( 'You do not have sufficient permissions.', 'wdm-customization' ) ) . '</b>';
			return;
		}

		if ( isset( $_POST['wdm_add_events_form_submit'] ) ) {
			check_admin_referer( 'sample-sec-two' );
			try {
				//phpcs:disable
				$this->add_event(
					$_POST['wdm_customizer_event_type_selector'], 
					intval( $_POST['wdm_customizer_is_alt_event'] ),
					intval( $_POST['wdm_customizer_is_team_event'] ),
					intval( $_POST['wdm_customizer_event_start_time'] ),
					intval( $_POST['wdm_customizer_event_end_time'] ),
					intval( $_POST['wdm_customizer_min_event_team_member'] ),
					intval( $_POST['wdm_customizer_max_event_team_member'] ),
					intval( get_current_user_id() ),
					intval( $_POST['wdm_customizer_event_deadline_time'] ),
					intval( $_POST['wdm_customizer_alt_event_type_selector'] ),
				);
				//phpcs:enable
			} catch ( Exception $e ) {
				$err = $e->getMessage();
				add_action(
					'wdm_show_add_event_errors',
					function () use ( $err ) {
						echo "<div class='wdm-err'>" . esc_html( $err ) . '</div>';
					},
					10,
					1
				);
			}
		}
		$event_types    = wdm_get_mepr_events( false );
		$created_events = $this->get_events();
		require_once WDM_CUSTOMIZATION_DIR . '/templates/add-events-form.php';
		require_once WDM_CUSTOMIZATION_DIR . '/templates/tabular-events.php';
	}

	/**
	 * Enqueues the styles and scripts required for the admin interface.
	 *
	 * @since 1.0.0
	 */
	public function admin_enqueue_scripts() {
		/** Jquery */
		wp_enqueue_script( 'jquery' );
		/** Flatpickr */
		wp_enqueue_style( 'wdm-flatpickr-style', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css', array(), '1.0.0' );
		wp_enqueue_script( 'wdm-flatpickr-script', 'https://cdn.jsdelivr.net/npm/flatpickr', array(), '1.0.0', true );

		/** Datatables */
		wp_enqueue_style( 'wdm-datatables-style', 'https://cdn.datatables.net/v/dt/dt-2.1.8/b-3.1.2/b-html5-3.1.2/rg-1.5.0/datatables.min.css', array(), '1.0.0' );
		wp_enqueue_script( 'wdm-datatables-script', 'https://cdn.datatables.net/v/dt/dt-2.1.8/b-3.1.2/b-html5-3.1.2/rg-1.5.0/datatables.min.js', array( 'jquery' ), '1.0.0', true );

		/**Custom */
		wp_enqueue_style( 'wdm-customization-admin-style', plugin_dir_url( __DIR__ ) . '/admin/admin-style.css', array(), '1.0.0' );
		wp_enqueue_script( 'wdm-customization-admin-script', plugin_dir_url( __DIR__ ) . '/admin/admin-style.js', array( 'jquery' ), '1.0.0', true );
	}

	/**
	 * Enqueues the React scripts and styles for the team management interface.
	 *
	 * If the current URL contains '/teams', it will enqueue the React scripts and styles.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_scripts() {
		global $wp;
		if ( ! is_object( $wp ) ) {
			return;
		}
		if ( strpos( $wp->request, 'teams' ) !== false ) {
			$files = glob( plugin_dir_path( __DIR__ ) . '/templates/team-management-react/build/static/js/main.*.js' );
			if ( is_array( $files ) && count( $files ) === 1 ) {
				$filename = basename( $files[0] );
				wp_enqueue_script( 'wdm-customization-team-management-react', plugin_dir_url( __DIR__ ) . '/templates/team-management-react/build/static/js/' . $filename, array( 'jquery' ), '1.0.0', true );
			}
			$files = glob( plugin_dir_path( __DIR__ ) . '/templates/team-management-react/build/static/css/main.*.css' );
			if ( is_array( $files ) && count( $files ) === 1 ) {
				$filename = basename( $files[0] );
				wp_enqueue_style( 'wdm-customization-team-management-react-style', plugin_dir_url( __DIR__ ) . '/templates/team-management-react/build/static/css/' . $filename, array(), '1.0.0' );
			}
			$logo_url = '';
			if ( class_exists( 'MeprOptions' ) && method_exists( 'MeprOptions', 'fetch' ) ) {
				$mepr_options = MeprOptions::fetch();
				if ( isset( $mepr_options->design_logo_img ) && ! empty( $mepr_options->design_logo_img ) ) {
					$logo_url = esc_url( wp_get_attachment_url( $mepr_options->design_logo_img ) );
				}
			}
			wp_localize_script(
				'wdm-customization-team-management-react',
				'wdm_ajax_admin_obj',
				array(
					'ajax_url'  => admin_url( 'admin-ajax.php' ),
					'nonce'     => wp_create_nonce( 'sample-code-sec' ),
					'user_id'   => get_current_user_id(),
					'site_logo' => $logo_url,
				)
			);
		}
	}

	/**
	 * Gets the instance of the WDM_Customizer_Settings class.
	 *
	 * @since 1.0.0
	 *
	 * @return WDM_Customizer_Settings The instance of the WDM_Customizer_Settings class.
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Enrolls a user in all the events which start time is greater than current time.
	 * This function is triggered when a new transaction is added.
	 *
	 * @since 1.0.0
	 *
	 * @param object $transaction The transaction that was added.
	 *
	 * @return null|array {
	 *     'success' array List of events in which user was enrolled successfully.
	 *     'failed'  array List of events in which user was not enrolled.
	 * }
	 */
	public function wdm_auto_enroll_user_in_events( $transaction ) {
		if ( empty( $transaction ) || ! isset( $transaction->user_id ) ) {
			return;
		}
		$this->auto_enroll_in_events( $transaction->user_id );
	}

	/**
	 * Enrolls a user in all the events which start time is greater than current time.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id The ID of the user to enroll.
	 *
	 * @return null|array {
	 *     'success' array List of events in which user was enrolled successfully.
	 *     'failed'  array List of events in which user was not enrolled.
	 * }
	 */
	private function auto_enroll_in_events( int $user_id ) {
		if ( empty( $user_id ) ) {
			return;
		}
		$event_type = get_user_meta( $user_id, 'mepr_bh', true );
		if ( empty( $event_type ) || ! is_string( $event_type ) ) {
			return;
		}
		$event_types_present = wdm_get_mepr_events();
		if ( ! is_array( $event_types_present ) || ! in_array( $event_type, $event_types_present, true ) ) {
			return;
		}
		$events                = $this->get_events_by_type( $event_type );
		$failed_to_enrolled    = array();
		$enrolled_successfully = array();
		foreach ( $events as $event ) {
			if ( intval( $event->is_alt_event ) === 1 || intval( $event->event_start_time ) <= time() ) {
				continue;
			}
			try {
				$this->enroll_user_in_event( intval( $user_id ), intval( $event->event_id ) );
			} catch ( Exception $e ) {
				$failed_to_enrolled[] = $event->event_id;
				continue;
			}
			$enrolled_successfully[] = $event->event_id;
		}
		return array(
			'success' => $enrolled_successfully,
			'failed'  => $failed_to_enrolled,
		);
	}

	/**
	 * Enrolls all users in all the events which start time is greater than current time.
	 * This function is triggered when a new event is added.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $event_id The ID of the event which was added.
	 * @param array $event_data The data of the event which was added.
	 *
	 * @return null
	 */
	public function wdm_auto_enroll_bulk_user_in_events( $event_id, $event_data ) {
		if ( empty( $event_id ) ||
			! is_array( $event_data ) ||
			empty( $event_data ) ||
			! isset( $event_data['event_name'] ) ||
			intval( $event_data['is_alt_event'] ) === 1
		) {
			return;
		}
		global $wpdb;
		if ( ! is_object( $wpdb ) ) {
			return;
		}
		$evt_name = $event_data['event_name'];
		$ids      = $wpdb->get_col(
			$wpdb->prepare( "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = %s", 'mepr_bh', $evt_name )
		);
		if ( ! is_array( $ids ) || empty( $ids ) ) {
			return;
		}
		$errors = array();
		foreach ( $ids as $user_id ) {
			try {
				$this->enroll_user_in_event( $user_id, $event_id );
			} catch ( Exception $e ) {
				$errors[] = $e->getMessage() . ' - ' . "Issue enrolling user {$user_id} in {$event_id}";
			}
		}
		if ( ! empty( $errors ) ) {
			foreach ( $errors as $error ) {
				add_action(
					'wdm_show_add_event_errors',
					function () use ( $error ) {
						echo "<div class='wdm-err'>" . esc_html( $error ) . '</div>';
					}
				);
			}
		}
	}
}
