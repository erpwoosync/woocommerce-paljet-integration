<?php $option_value = $value['value']; ?>
<tr valign="top">
	<th scope="row" class="titledesc">
		<label for="<?php echo esc_attr( $value['id'] ); ?>"><?php echo esc_html( $value['title'] ); ?> <?php echo $tooltip_html; // WPCS: XSS ok. ?></label>
	</th>
	<td class="forminp forminp-<?php echo esc_attr( sanitize_title( $value['type'] ) ); ?>">
		<div class="box-<?php echo $value['type_sync']; ?>">
			<select
				name="<?php echo esc_attr( $value['id'] ); ?><?php echo ( 'input_multiselect' === $value['type'] ) ? '[]' : ''; ?>"
				id="<?php echo esc_attr( $value['id'] ); ?>"
				style="<?php echo esc_attr( $value['css'] ); ?>"
				class="<?php echo esc_attr( $value['class'] ); ?> select-<?php echo $value['type_sync']; ?>"
				<?php echo implode( ' ', $custom_attributes ); // WPCS: XSS ok. ?>
				<?php echo 'input_multiselect' === $value['type'] ? 'multiple="multiple"' : ''; ?>
				>
				<?php
				foreach ( $value['options'] as $key => $val ) {
					?>
					<option value="<?php echo esc_attr( $key ); ?>"
						<?php

						if ( is_array( $option_value ) ) {
							selected( in_array( (string) $key, $option_value, true ), true );
						} else {
							selected( $option_value, (string) $key );
						}

						?>
					><?php echo esc_html( $val ); ?></option>
					<?php
				}
				?>
			</select> <?php echo $description; // WPCS: XSS ok. ?>
		</div>
		
		<?php if( isset( $value['text_link'] ) ) : ?>
			<a href="" class="paljet_sync_<?php echo $value['type_sync']; ?>" data-type="<?php echo $value['type_sync']; ?>">
				<?php echo $value['text_link']; ?>
			</a>
			<div class="message_paljet_sync_<?php echo $value['type_sync']; ?>"></div>
		<?php endif; ?>
	</td>
</tr>