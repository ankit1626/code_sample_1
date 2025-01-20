<?php
/**
 * Outputs the tabular events.
 *
 * @package wdm-customization
 */

?>
<h1><?php echo esc_html( __( 'Event List', 'wdm-customization' ) ); ?></h1>
		<p><?php echo wp_kses( '<span id="wdm_timezone_placeholder"></span>', 'post' ); ?></p>
		<div class="wdm-container">
			<table id="wdm_event_list_table" class="stripe hover" aria-describedby="wdm_event_list_table_info">
				<thead>
					<tr>
						<th><?php echo esc_html( __( 'Event Name', 'wdm-customization' ) ); ?></th>
						<th><?php echo esc_html( __( 'Team Event', 'wdm-customization' ) ); ?></th>
						<th><?php echo esc_html( __( 'Event Start Time', 'wdm-customization' ) ); ?></th>
						<th><?php echo esc_html( __( 'Event End Time', 'wdm-customization' ) ); ?></th>
						<th><?php echo esc_html( __( 'Event Deadline Time', 'wdm-customization' ) ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					$events = $this->get_events();
					foreach ( $events as $event ) {
						?>
						<tr>
							<td><?php echo esc_html( $event->event_name ); ?></td>
							<td><?php echo esc_html( '1' === $event->is_team_event ? 'Yes' : 'No' ); ?></td>
							<td class='wdm_event_timestamp'>
								<?php
									echo esc_html(
										wp_date( WDM_DT_FORMAT, $event->event_start_time, new DateTimeZone( WDM_TIMEZONE ) )
									);
								?>
							</td>
							<td class='wdm_event_timestamp'>
								<?php
									echo esc_html(
										wp_date( WDM_DT_FORMAT, $event->event_end_time, new DateTimeZone( WDM_TIMEZONE ) )
									);
								?>
							</td>
							<td class='wdm_event_timestamp'>
								<?php
									echo esc_html(
										wp_date( WDM_DT_FORMAT, $event->deadline, new DateTimeZone( WDM_TIMEZONE ) )
									);
								?>
							</td>
						</tr>
						<?php
					}
					?>
				</tbody>
			</table>
		</div>
