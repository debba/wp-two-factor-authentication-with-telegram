<?php
if ( isset( $_GET['tab'] ) ) {
	$active_tab = $_GET['tab'];
} else {
	$active_tab = 'config';
}
?>

    <div id="wft-wrap" class="wrap">

        <h1><?php _e( "Setup", "two-factor-login-telegram" ); ?>
            - <?php _e( "Two Factor Authentication with Telegram", "two-factor-login-telegram" ); ?></h1>

        <h2 class="wpft-tab-wrapper nav-tab-wrapper">
            <a href="<?php echo admin_url( 'options-general.php?page=tg-conf&tab=config' ); ?>"
               class="nav-tab <?php echo $active_tab == 'config' ? 'nav-tab-active' : ''; ?>"><?php _e( "Setup", "two-factor-login-telegram" ); ?></a>
            <a href="<?php echo admin_url( 'options-general.php?page=tg-conf&tab=howto' ); ?>"
               class="nav-tab <?php echo $active_tab == 'howto' ? 'nav-tab-active' : ''; ?>"><span
                        class="dashicons dashicons-editor-help"></span> <?php _e( "FAQ", "two-factor-login-telegram" ); ?>
            </a>

			<?php

			if ( $this->is_valid_bot() && get_the_author_meta( "tg_wp_factor_chat_id", get_current_user_id() ) !== false ) {
				?>

                <a href="<?php echo admin_url( 'options-general.php?page=tg-conf&tab=suggestions' ); ?>"
                   class="nav-tab <?php echo $active_tab == 'support' ? 'nav-tab-active' : ''; ?>"><span
                            class="dashicons dashicons-heart"></span> <?php _e( "Suggestions", "two-factor-login-telegram" ); ?>
                </a>

				<?php
			}

			?>

        </h2>

        <div class="wpft-container">

			<?php

			if ( $active_tab == "howto" ) {

				?>

                <h2><?php _e( "FAQ", "two-factor-login-telegram" ); ?></h2>

                <div id="wpft-howto">
                    <h3><?php _e( "Bot token", "two-factor-login-telegram" ); ?></h3>
                    <div>
                        <p>
							<?php _e( 'If you want to enable <strong>Two Factor Authentication with Telegram</strong> plugin you need to provide a valid token for a Telegram Bot.', "two-factor-login-telegram" ); ?>
                            <br/>
							<?php _e( 'Have you ever created a bot in Telegram? It\'s so easy!', "two-factor-login-telegram" ); ?>
                            <br/>

                        <ol>
                            <li>
								<?php
								printf( __( 'Open Telegram and start a conversation with %s', "two-factor-login-telegram" ), '<a href="https://telegram.me/botfather" target="_blank">@BotFather</a>' );
								?>
                            </li>

                            <li>
								<?php
								printf( __( 'Type command %s to create a new bot', "two-factor-login-telegram" ), '<code>/newbot</code>' );
								?>
                            </li>
                            <li><?php
								_e( 'Provide username and name for the new bot.', 'two-factor-login-telegram' ); ?></li>
                            <li>
								<?php _e( 'In the anwser will be your <strong>Bot Token</strong>', 'two-factor-login-telegram' ); ?>

                                <img style="width:500px;height:auto;"
                                     src="<?php echo plugins_url( "/assets/img/help-api-token.png", WP_FACTOR_TG_FILE ); ?>">

                            </li>
                        </ol>

                        </p>
                    </div>
                    <h3><?php _e( "Get Chat ID for Telegram user", "two-factor-login-telegram" ); ?></h3>
                    <div>
                        <p>
							<?php _e( "Chat ID identifies your user profile in Telegram.", "wp-factor-telegram" ); ?>
                            <br/>
							<?php _e( "You have no idea what is your Chat ID? Follow these simple steps.", "wp-factor-telegram" ); ?>

                        <ol>

                            <li>
								<?php
								printf( __( 'Open Telegram and start a conversation with %s', "two-factor-login-telegram" ), '<a href="https://telegram.me/WordPressLoginBot" target="_blank">@WordpressLoginBot</a>' );
								?>
                            </li>

                            <li>
								<?php
								printf( __( 'Type command %s to obtain your Chat ID.', "two-factor-login-telegram" ), '<code>/get_id</code>' );
								?>
                            </li>
                            <li>
								<?php
								_e( "Inside of the answer you'll find your <strong>Chat ID</strong>", 'two-factor-login-telegram' );
								?>
                            </li>

                        </ol>

                        </p>
                    </div>
                    <h3><?php _e( "Activation of service", "two-factor-login-telegram" ); ?></h3>
                    <div>
                        <p>
							<?php _e( 'Open a conversation with the created bot that you provided for the plugin and push <strong>Start</strong>', 'two-factor-login-telegram' ); ?>
                            .
                        </p>
                    </div>
                </div>

				<?php

			} else if ( $active_tab == "suggestions" ) {
				?>

                <div id="wpft-suggestions">
                    <h3><?php _e( "Suggestions", "two-factor-login-telegram" ); ?></h3>
                    <div>
                        <p>
                            <em>
								<?php _e( "We developed this plugin to improve the security in WordPress. We love Telegram and let's hope that this plugin would be agreeable.", "two-factor-login-telegram" ); ?>
                                <br/><?php _e( "We consider these early versions only a test, but we have got so many ideas for the future. ", "two-factor-login-telegram" ); ?>
                                <br/>
								<?php _e( "If you like the project and you've suggestions or you want report problems, please compile this form.", "two-factor-login-telegram" ); ?>
                            </em>
                        </p>
                        <p style="text-align:right;">
							<?php _e( "Thanks", "two-factor-login-telegram" ); ?> - dueclic.
                        </p>
                    </div>

                    <form method="post" enctype="multipart/form-data" action="" id="form_suggestions">

                        <table class="form-table">
                            <tbody>

                            <tr class="css_class">
                                <th scope="row"><label
                                            for="your_name"> <?php _e( "Your name", "two-factor-login-telegram" ); ?> </label>
                                </th>
                                <td><input class="regular-text css_class" type="text" id="your_name" name="your_name"
                                           value=""
                                           placeholder="<?php _e( "Your name", "two-factor-login-telegram" ); ?>"></td>

                            </tr>
                            <tr class="css_class">
                                <th scope="row"><label
                                            for="your_email"><?php _e( "Your email", "two-factor-login-telegram" ); ?></label>
                                </th>
                                <td><input class="regular-text css_class" type="text" id="your_email" name="your_email"
                                           value=""
                                           placeholder="<?php _e( "Your email", "two-factor-login-telegram" ); ?>"></td>
                            </tr>
                            <tr class="css_class">
                                <th scope="row"><label
                                            for="your_name"><?php _e( "Message", "two-factor-login-telegram" ); ?></label>
                                </th>
                                <td><textarea id="your_message" name="your_message" rows="20" cols="20"
                                              style="width:350px;height:200px;" placeholder="<?php _e( "Leave a message", "two-factor-login-telegram" ); ?>"></textarea>
                                </td>
                            </tr>

                            </tbody>
                        </table>

                        <p class="submit">
                            <input type="submit" class="button-primary"
                                   value="<?php _e( 'Send', "two-factor-login-telegram" ) ?>"/>
                        </p>

                        <div class="wpft-notice wpft-notice-error response-email-error">

                            <p></p>

                        </div>

                        <div class="wpft-notice wpft-notice-success response-email-success">

                            <p class="first"></p>
                            <p><?php _e('If you want, follow us on ', "two-factor-login-telegram"); ?><a class="social-foot" href="https://www.facebook.com/dueclic/"><span class="dashicons dashicons-facebook bg-fb"></span></a> <?php _e(" and keep update!", "two-factor-login-telegram"); ?></p>

                        </div>

                    </form>

                </div>

				<?php
			} else {

				?>

                <div class="wpft-notice wpft-notice-warning">
                    <p>
                        <span class="dashicons dashicons-editor-help"></span> <?php

						printf( __( 'First time? <a href="%s">Take a look into the FAQ!</a>', "two-factor-login-telegram" ), admin_url( 'options-general.php?page=tg-conf&tab=howto' ) );

						?>
                    </p>
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

<?php do_action( "tft_copyright" ); ?>