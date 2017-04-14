<?php

class WP_Telegram {

	private $namespace = "tg_col";
	protected $bot_token;
	protected $endpoint;

	public $lastError;

	public function __construct() {
		$this->bot_token = get_option( $this->namespace )['bot_token'];
		$this->endpoint  = sprintf( "https://api.telegram.org/bot%s/sendMessage", $this->bot_token );
	}

	/**
	 * Send telegram message
	 *
	 * @param $msg
	 * @param chat_id
	 *
	 * @return bool
	 */

	public function send( $msg, $chat_id ) {

		$request = wp_remote_post( $this->endpoint, array(

			'body' => array(
				'chat_id' => $chat_id,
				'text'    => $msg
			)

		) );

		if ( is_wp_error( $request ) ) {
			$this->lastError = __( "Errore interno, riprova.", "two-factor-telegram" );

			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $request ) );

		if ( $body->ok == 1 )
			return true;

		$this->lastError = sprintf( __( "%s (Codice errore %d)", $body->description, $body->error_code, "two-factor-telegram" ) );

		return false;

	}

	/**
	 * Send authentication token with Telegram
	 *
	 * @param $token
	 * @param $chat_id bool
	 *
	 * @return bool
	 */

	public function send_tg_token( $token, $chat_id = false ) {

		if ( $chat_id === false ) {
			$chat_id = get_user_meta( get_current_user_id(), "tg_wp_factor_chat_id" );
		}

		return $this->send( __( "Il tuo codice di accesso è: " . $token, "two-factor-telegram" ), $chat_id );
	}

	/**
	 * Send a User failed login notification to Telegram
	 *
	 * @param $user_login
	 *
	 * @return bool
	 */

	public function send_tg_failed_login( $user_login ) {
		$chat_id = get_option( $this->namespace )['chat_id'];

		return $this->send( __( "Tentativo di accesso fallito da parte dell'utente: " . $user_login . " IP: " . $_SERVER['REMOTE_ADDR'] ), $chat_id );
	}

}

