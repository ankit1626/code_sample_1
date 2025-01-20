<?php
/**
 * File comment.
 *
 * @package wdm-customization
 */

/**
 * Class DB_Initializer
 */
class DB_Initializer {
	/**
	 * The single instance of the class.
	 *
	 * @var object $instance
	 */
	private static $instance;
	/**
	 * Charset used for database.
	 *
	 * @var string $charset_collate
	 */
	private $charset_collate = '';
	/**
	 * Custom Table Names.
	 *
	 * @var array $table_names
	 */
	private $table_names = array( 'wdm_events', 'wdm_teams', 'wdm_event_registration', 'wdm_team_requests' );
	/**
	 * Sql queries to create custom tables.
	 *
	 * @var array $queries.
	 */
	private $queries = array(
		'CREATE TABLE IF NOT EXISTS `wdm_events` (
            event_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            event_name VARCHAR(200) NOT NULL,
			is_alt_event BOOLEAN NOT NULL,
			is_team_event BOOLEAN NOT NULL,
            event_start_time INT UNSIGNED NOT NULL,
            event_end_time INT UNSIGNED NOT NULL,
            min_team_member_count TINYINT UNSIGNED NOT NULL,
            max_team_member_count TINYINT UNSIGNED NOT NULL,
			event_organizer_id BIGINT(20) NOT NULL,
            deadline INT UNSIGNED NOT NULL,
			alt_event_id INT UNSIGNED,
            PRIMARY KEY (event_id),
			FOREIGN KEY (alt_event_id) REFERENCES wdm_events(event_id) ON DELETE CASCADE
        )',
		'CREATE TABLE IF NOT EXISTS `wdm_teams` (
            team_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            team_members TEXT,
            PRIMARY KEY (team_id)
        )',
		'CREATE TABLE IF NOT EXISTS `wdm_event_registration` (
            user_id BIGINT(20) NOT NULL,
            event_id INT UNSIGNED NOT NULL,
            team_id INT UNSIGNED,
            count_of_team_members TINYINT UNSIGNED,
            PRIMARY KEY (user_id, event_id),
            FOREIGN KEY (event_id) REFERENCES wdm_events(event_id) ON DELETE CASCADE,
            FOREIGN KEY (team_id) REFERENCES wdm_teams(team_id) ON DELETE SET NULL
        )',
		'CREATE TABLE IF NOT EXISTS wdm_team_requests (
			request_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            requestee_id BIGINT(20) NOT NULL,
            requester_id BIGINT(20) NOT NULL,
            event_id INT UNSIGNED NOT NULL,
            status TINYINT NOT NULL,
            expires_on INT UNSIGNED NOT NULL,
			team_id INT UNSIGNED,
            PRIMARY KEY (request_id),
			UNIQUE KEY (requestee_id, requester_id, event_id),
			UNIQUE KEY (requestee_id, team_id, event_id),
            FOREIGN KEY (event_id) REFERENCES wdm_events(event_id) ON DELETE CASCADE,
			FOREIGN KEY (team_id) REFERENCES wdm_teams(team_id) ON DELETE CASCADE
        )',
	);

	/**
	 * Creates the database tables for events, teams, event registration, and team requests.
	 *
	 * @since 1.0.0
	 *
	 * @throws Exception - Will thrown an exception if wasn't able to create the tables.
	 */
	private function create_tables() {
		global $wpdb;
		if ( ! is_object( $wpdb ) ) {
			throw new Exception( esc_html__( 'Database object not found', 'wdm-customization' ) );
		}
		$this->charset_collate = $wpdb->get_charset_collate();
		if ( empty( $this->charset_collate ) ) {
			throw new Exception( esc_html__( 'Charset not defined', 'wdm-customization' ) );
		}
		if ( ! function_exists( 'dbDelta' ) ) {
			throw new Exception( esc_html__( 'dbDelta not found', 'wdm-customization' ) );
		}
		array_map(
			function ( $query ) {
				$query = $query . $this->charset_collate;
				return dbDelta( $query, true );
			},
			$this->queries
		);
		$tables = $wpdb->get_col( "SHOW TABLES LIKE 'wdm_%'" );
		if ( ! is_array( $tables ) ) {
			throw new Exception( esc_html__( 'Unable to reterive tables', 'wdm-customization' ) );
		}
		foreach ( $this->table_names as $table ) {
			if ( ! in_array( $table, $tables, true ) ) {
				throw new Exception( esc_html( $table ) . esc_html__( 'not found', 'wdm-customization' ) );
			}
		}

		do_action( 'wdm_db_tables_created' );
	}

	/**
	 * Initializes the database tables.
	 *
	 * @since 1.0.0
	 *
	 * @throws Exception - Will thrown an exception if wasn't able to create the tables.
	 */
	private function __construct() {
		if ( ! defined( 'WDM_CUSTOMIZATION_BASENAME' ) || ! defined( 'ABSPATH' ) ) {
			throw new Exception( esc_html__( 'Constants not defined', 'wdm-customization' ) );
		}
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		try {
			$this->create_tables();
		} catch ( Exception $e ) {
			deactivate_plugins( WDM_CUSTOMIZATION_BASENAME );
		}
	}

	/**
	 * Gets the instance of the DB_Initializer class.
	 *
	 * @since 1.0.0
	 *
	 * @return DB_Initializer The instance of the DB_Initializer class.
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
}
