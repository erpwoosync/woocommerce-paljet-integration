<tr valign="top">
	<th scope="row" class="titledesc">
		<label for="<?php echo esc_attr( $opts['id'] ); ?>"><?php echo esc_html( $opts['title'] ); ?></label>
	</th>
	<td class="forminp forminp-<?php echo esc_attr( sanitize_title( $opts['type'] ) ); ?>">

		<table class="wp-list-table widefat striped">
			<tr>
				<th><?php esc_html_e( 'Max execution time', 'paljet' ); ?></th>
				<td><?php printf( esc_html__( '%d secs', 'paljet' ), PALJET_MAX_TIME ); ?></td>
			</tr>
		</table>

	</td>
</tr>