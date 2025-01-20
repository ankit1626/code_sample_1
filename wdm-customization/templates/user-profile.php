<?php
/**
 * User Profile Template.
 *
 * @package wdm-customization
 */

?>
<h1><?php esc_html_e( 'Enrolled Single Events', 'wdm-customization' ); ?></h1>
<table aria-describedby="wdm_add_events_form_info">
	<tr>
		<th><?php esc_html_e( 'Event Name', 'wdm-customization' ); ?></th>
		<th><?php esc_html_e( 'Start Time', 'wdm-customization' ); ?></th>
	</tr>

<?php
foreach ( $single_events as $event ) {
	echo '<tr><td>' . esc_html( $event->event_name ) . '</td><td>' . esc_html( wp_date( WDM_DT_FORMAT, $event->event_start_time, new DateTimeZone( WDM_TIMEZONE ) ) ) . '</td></tr>';
}
?>
</table>
<h1><?php esc_html_e( 'Enrolled Team Events', 'wdm-customization' ); ?></h1>
<table aria-describedby="wdm_add_events_form_info">
	<tr>
		<th><?php esc_html_e( 'Event Name', 'wdm-customization' ); ?></th>
		<th><?php esc_html_e( 'Start Time', 'wdm-customization' ); ?></th>
	</tr>
	<?php
	foreach ( $team_events as $event ) {
		echo '<tr><td>' . esc_html( $event->event_name ) . '</td><td>' . esc_html( wp_date( WDM_DT_FORMAT, $event->event_start_time, new DateTimeZone( WDM_TIMEZONE ) ) ) . '</td></tr>';
	}
	?>
</table>
