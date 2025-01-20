<?php
/**
 * Outputs the add events form.
 *
 * @package wdm-customization
 */

do_action( 'wdm_show_add_event_errors' );
?>
<form id="wdm_add_events_form" method="post">
	<table aria-describedby="wdm_add_events_form_info">
		<tr>
			<th scope="row">
				<label for="wdm_customizer_event_type_selector">
					<?php
					echo esc_html( __( 'Select Event Type', 'wdm-customization' ) );
					?>
				</label>
			</th>
			<td>
				<select name=<?php echo esc_attr( 'wdm_customizer_event_type_selector' ); ?> id="wdm_customizer_event_type_selector" required>
					<option value=""> <?php echo esc_html( __( 'Select Event Type', 'wdm-customization' ) ); ?></option>
						<?php
						foreach ( $event_types as $event ) {
							?>
						<option value=<?php echo esc_attr( $event->option_value ); ?>>
							<?php echo esc_html( $event->option_name ); ?>
						</option>
							<?php
						}
						?>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wdm_customizer_is_alt_event">
					<?php echo esc_html( __( 'Is Alternate Event', 'wdm-customization' ) ); ?>
				</label>
			</th>
			<td>
				<input type="radio" name="wdm_customizer_is_alt_event" value="1" required />
				<?php echo esc_html( __( 'Yes', 'wdm-customization' ) ); ?>
				</input>
				<input type="radio" name="wdm_customizer_is_alt_event" value="-1" required />
				<?php echo esc_html( __( 'No', 'wdm-customization' ) ); ?>
				</input>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label>
					<?php echo esc_html( __( 'Is Team Event', 'wdm-customization' ) ); ?>
				</label>
			</th>
			<td>
				<input type="radio" name="wdm_customizer_is_team_event" id="wdm_customizer_is_team_event_yes" value="1" required />
				<?php echo esc_html( __( 'Yes', 'wdm-customization' ) ); ?>
				</input>
				<input type="radio" name="wdm_customizer_is_team_event" id="wdm_customizer_is_team_event_no" value="-1" required />
				<?php echo esc_html( __( 'No', 'wdm-customization' ) ); ?>
				</input>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wdm_customizer_event_start_time">
					<?php echo esc_html( __( 'Event Start Time', 'wdm-customization' ) ); ?>
				</label>
			</th>
			<td>
				<input type="text" name="wdm_customizer_event_start_time" id="wdm_customizer_event_start_time" />
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wdm_customizer_event_end_time">
					<?php echo esc_html( __( 'Event End Time', 'wdm-customization' ) ); ?>
				</label>
			</th>
			<td>
				<input type="text" name="wdm_customizer_event_end_time" id="wdm_customizer_event_end_time" />
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wdm_customizer_event_deadline_time">
					<?php echo esc_html( __( 'Event Deadline Time', 'wdm-customization' ) ); ?>
				</label>
			</th>
			<td>
				<input type="text" name="wdm_customizer_event_deadline_time" id="wdm_customizer_event_deadline_time" />
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wdm_customizer_min_event_team_member">
					<?php echo esc_html( __( 'Minimum number of members required for a team', 'wdm-customization' ) ); ?>
				</label>
			</th>
			<td>
				<input type="number" name="wdm_customizer_min_event_team_member"
					id="wdm_customizer_min_event_team_member" min="2" required />
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wdm_customizer_max_event_team_member">
					<?php echo esc_html( __( 'Maximum number of members required for a team', 'wdm-customization' ) ); ?>
				</label>
			</th>
			<td>
				<input type="number" name="wdm_customizer_max_event_team_member"
					id="wdm_customizer_max_event_team_member" min="2" required />
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wdm_customizer_alt_event_type_selector">
					<?php
					echo esc_html( __( 'Select Alternate Event Type', 'wdm-customization' ) );
					?>
				</label>
			</th>
			<td>
				<select name=<?php echo esc_attr( 'wdm_customizer_alt_event_type_selector' ); ?> id="wdm_customizer_alt_event_type_selector" required>
					<option value="-1"> <?php echo esc_html( __( 'Select Alternate Event as fallback', 'wdm-customization' ) ); ?></option>
						<?php
						foreach ( $created_events as $event ) {
							if (
								intval( $event->event_start_time ) > time() &&
								intval( $event->is_team_event ) === 0 &&
								intval( $event->is_alt_event ) === 1
							) {
								?>
								<option value=<?php echo esc_attr( $event->event_id ); ?>><?php echo esc_html( $event->event_name . '-' . wp_date( WDM_DT_FORMAT, $event->event_start_time, new DateTimeZone( WDM_TIMEZONE ) ) ); ?></option>
								<?php
							}
						}
						?>
				</select>
			</td>
		</tr>
		<tr>
			<td colspan="2" style="text-align: center;">
				<input type="submit" name="wdm_add_events_form_submit"
					value="<?php echo esc_attr( __( 'Add Event', 'wdm-customization' ) ); ?>" />
			</td>
		</tr>
	</table>
	<?php
	wp_nonce_field( 'sample-sec-two' );
	?>
</form>
