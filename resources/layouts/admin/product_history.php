<?php
	if( empty( $histories ) ) :
		esc_html_e( 'Empty history', 'paljet' );
		return;
	endif;
?>

<table class="table">
	<tbody>
		<?php foreach( $histories as $history ) : ?>
			<tr>
				<td><?php echo $history; ?></td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>