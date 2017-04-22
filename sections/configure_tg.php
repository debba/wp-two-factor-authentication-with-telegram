<?php
if( isset( $_GET[ 'tab' ] ) ) {
	$active_tab = $_GET[ 'tab' ];
} else {
	$active_tab = 'config';
}
?>

<div id="wft-wrap" class="wrap">

	<h1><?php _e("Configura", "two-factor-login-telegram"); ?> - <?php _e( "Autenticazione a due fattori con Telegram", "two-factor-login-telegram" ); ?></h1>

    <h2 class="wpft-tab-wrapper nav-tab-wrapper">
        <a href="<?php echo admin_url( 'options-general.php?page=tg-conf&tab=config' ); ?>" class="nav-tab <?php echo $active_tab == 'config' ? 'nav-tab-active' : ''; ?>"><?php _e("Configura", "two-factor-login-telegram"); ?></a>
        <a href="<?php echo admin_url( 'options-general.php?page=tg-conf&tab=howto' ); ?>" class="nav-tab <?php echo $active_tab == 'howto' ? 'nav-tab-active' : ''; ?>"><span class="dashicons dashicons-editor-help"></span> <?php _e("Guida", "two-factor-login-telegram"); ?></a>
    </h2>

    <div class="wpft-container">

        <?php

            if ($active_tab == "howto") {

        ?>

                <h2><?php _e("Guida", "two-factor-login-telegram"); ?></h2>

                <div id="wpft-howto">
                    <h3><?php _e("Bot token", "two-factor-login-telegram"); ?></h3>
                    <div>
                        <p>
                            <?php _e('Per abilitare l\' <strong>autenticazione a due fattori con Telegram</strong> devi indicare un token valido di un Bot Telegram.', "two-factor-login-telegram"); ?><br />
	                        <?php _e('Non hai mai creato un bot su Telegram? E\' semplicissimo!', "two-factor-login-telegram"); ?><br />

                            <ol>
                                <li><?php
                                    _e(sprintf('Apri Telegram e avvia una conversazione con %s', '<a href="https://telegram.me/botfather" target="_blank">@BotFather</a>'), 'two-factor-login-telegram'); ?></li>
                                <li><?php
	                                _e(sprintf('Digita il comando %s per creare un nuovo bot.', '<code>/newbot</code>'), 'two-factor-login-telegram'); ?></li>
                                <li><?php
	                                _e('Scegli uno username e un nome per il nuovo bot.', 'two-factor-login-telegram'); ?></li>
                                <li>
                                    <?php _e('Se tutto è andato a buon fine, il bot è stato creato. All\'interno della risposta sarà presente il tuo <strong>Bot Token</strong>', 'two-factor-login-telegram'); ?>

                                    <img style="width:500px;height:auto;" src="<?php echo plugins_url("/assets/img/help-api-token.png", WP_FACTOR_TG_FILE); ?>">

                                </li>
                            </ol>

                        </p>
                    </div>
                    <h3><?php _e("Ottenere la Chat ID dell'utente Telegram", "two-factor-login-telegram"); ?></h3>
                    <div>
                        <p>
                            <?php _e("La Chat ID identifica il tuo utente su Telegram.", "wp-factor-telegram"); ?><br />
	                        <?php _e("Non sai quale sia la tua chat id? Segui questi semplici passi.", "wp-factor-telegram"); ?>

                        <ol>
                            <li><?php
			                    _e(sprintf('Apri Telegram e avvia una conversazione con %s', '<a href="https://telegram.me/WordPressLoginBot" target="_blank">@WordpressLoginBot</a>'), 'two-factor-login-telegram'); ?></li>
                            <li><?php
			                    _e(sprintf('Digita il comando %s per ottenere la tua Chat ID.', '<code>/get_id</code>'), 'two-factor-login-telegram'); ?></li>
                            <li><?php
			                    _e('All\' interno della risposta sarà presente la <strong>Chat ID</strong>', 'two-factor-login-telegram'); ?></li>
                        </ol>

                        </p>
                    </div>
                    <h3><?php _e("Attivazione del servizio", "two-factor-login-telegram"); ?></h3>
                    <div>
                        <p>
                            <?php _e('Per attivare il servizio, apri una conversazione col Bot da te creato ed indicato nelle impostazioni del plugin e schiaccia su <strong>Avvia</strong>', 'two-factor-login-telegram'); ?>.
                        </p>
                    </div>
                </div>

        <?php

            }

            else {

	            ?>

                <div class="wpft-notice wpft-notice-warning">
                    <p><span class="dashicons dashicons-editor-help"></span> <?php _e(sprintf('Prima volta? <a href="%s">Segui la guida</a> per impostare al meglio il plugin.', admin_url( 'options-general.php?page=tg-conf&tab=howto' ) ), "two-factor-login-telegram"); ?></p>
                </div>

                <form method="post" enctype="multipart/form-data" action="options.php">

		            <?php

		            settings_fields( 'tg_col' );
		            do_settings_sections( 'tg_col.php' );
		            ?>
                    <p class="submit">
                        <input type="submit" class="button-primary" value="<?php _e( 'Save Changes' ) ?>"/>
                    </p>

                </form>

	            <?php
            }
        ?>

    </div>

</div>

<?php do_action("tft_copyright"); ?>