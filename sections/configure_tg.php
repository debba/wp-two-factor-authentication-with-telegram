<div id="wft-wrap" class="wrap">

	<h1><?php _e( "Configura Two Factor Authentication con Telegram" ); ?></h1>

	<form method="post" enctype="multipart/form-data" action="options.php">
		<hr>
		<?php

		settings_fields( 'tg_col' );
		do_settings_sections( 'tg_col.php' );
		?>
		<p class="submit">
			<input type="submit" class="button-primary" value="<?php _e( 'Save Changes' ) ?>"/>
		</p>

	</form>

</div>