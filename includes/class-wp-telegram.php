<?php

class WP_Telegram {

	private $namespace = "tg_col";
	protected $bot_token;
	protected $endpoint;

	public $lastError;

	public function __construct() {
		$this->bot_token = get_option( $this->namespace )['bot_token'];
		$this->endpoint  = "https://api.telegram.org/bot%s";
	}

	/**
	 * Get route URL
	 *
	 * @param $route
	 *
	 * @return string
	 */

	private function get_route( $route ) {
		$endpoint = sprintf( "https://api.telegram.org/bot%s", $this->bot_token );

		return $endpoint . $route;
	}

	/**
	 * Make request
	 *
	 * @param $route
	 * @param $args
	 *
	 * @return array|WP_Error
	 */

	private function make_request( $route, $args = array() ) {

		$endpoint = $this->get_route( $route );

		return wp_remote_post( $endpoint, array(
			'body' => $args
		) );

	}

	/**
	 * Send telegram venue
	 *
	 * @param $latitude
	 * @param $longitude
	 * @param $chat_id
	 *
	 * @return bool
	 */

	public function sendLocation( $latitude, $longitude, $chat_id ) {

		$request = $this->make_request( "/sendVenue", array(
			'chat_id'   => $chat_id,
			'latitude'  => $latitude,
			'longitude' => $longitude
		) );

		if ( is_wp_error( $request ) ) {
			$this->lastError = __( "Ooops! Server failure, try again!", "two-factor-login-telegram" );

			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $request ) );

		if ( $body->ok == 1 ) {
			return true;
		}

		$this->lastError = sprintf( __( "%s (Error code %d)", $body->description, $body->error_code, "two-factor-login-telegram" ) );

		return false;

	}

	/**
	 * Get Telegram updates
	 * @return array|bool|mixed|object
	 */

	public function getUpdates(){

		$request = $this->make_request( "/getUpdates", array(
			"allowed_updates" => "callback_query",
			"limit" => 1,
			"timeout" => 10,
			"offset" => -1
		) );

		if ( is_wp_error( $request ) ) {
			$this->lastError = __( "Ooops! Server failure, try again!", "two-factor-login-telegram" );
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $request ) );
		return $body;

	}

	/**
	 * Send telegram message
	 *
	 * @param $msg
	 * @param $chat_id
	 * @param $reply_markup false | mixed
	 * @param $return_object bool
	 *
	 * @return bool
	 */

	public function sendMessage( $msg, $chat_id, $reply_markup = false, $return_object = false ) {

		$data = array(
			'chat_id' => $chat_id,
			'text'    => $msg
		);

		if ( $reply_markup !== false ) {
			$data["reply_markup"] = $reply_markup;
		}

		$request = $this->make_request( "/sendMessage", $data );

		if ( is_wp_error( $request ) ) {
			$this->lastError = __( "Ooops! Server failure, try again!", "two-factor-login-telegram" );

			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $request ) );

		if ( $body->ok == 1 ) {

			if ($return_object)
				return $body;

			return true;
		}

		$this->lastError = sprintf( __( "%s (Error code %d)", $body->description, $body->error_code, "two-factor-login-telegram" ) );

		return false;

	}

	/**
	 * Set bot token
	 *
	 * @param $bot_token
	 *
	 * @return $this
	 */

	public function set_bot_token( $bot_token ) {
		$this->bot_token = $bot_token;

		return $this;
	}

	/**
	 * Get info about bot
	 * @return bool | object
	 */

	public function get_me() {

		$request = $this->make_request( "/getMe" );

		if ( is_wp_error( $request ) ) {
			$this->lastError = __( "Ooops! Server failure, try again!", "two-factor-login-telegram" );

			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $request ) );
		if ( $body->ok != 1 ) {
			$this->lastError = sprintf( __( "%s (Error code %d)", $body->description, $body->error_code, "two-factor-login-telegram" ) );

			return false;
		}

		return $body->result;


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

		$keyboard             = array(
			array(
				array( 'text' => __('Grant access!', 'two-factor-login-telegram'), 'callback_data' => "1" ),
				array( 'text' => __('Deny access!', 'two-factor-login-telegram'), 'callback_data' => "2" )
			)
		);

		$inlineKeyboardMarkup = array(
			'inline_keyboard' => $keyboard
		);

		return $this->sendMessage( sprintf( __( "This is your access code: %s", "two-factor-login-telegram" ), $token ), $chat_id, json_encode($inlineKeyboardMarkup), true );
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

		/**
		 * @from 1.2
		 * Get IP address behind CloudFlare proxy
		 */

		$ip_address = ( isset( $_SERVER["HTTP_CF_CONNECTING_IP"] ) ? $_SERVER["HTTP_CF_CONNECTING_IP"] : $_SERVER['REMOTE_ADDR'] );

		/**
		 * @from 1.6
		 * Send location based from IP
		 */

		if ( $this->sendMessage( sprintf( __( "Failed attempt to access for the user: %s (IP: %s)", "two-factor-login-telegram" ), $user_login, $ip_address ), $chat_id ) ) {

			if ( get_option( $this->namespace )['ipstack_enabled'] === '1' ) {

				$location = WP_Factor_Utils::get_location( $ip_address );
				if ( $location !== false ) {
					$this->sendLocation( $location->latitude, $location->longitude, $chat_id );
				}

			}

			return true;
		}

		return false;

	}

	/**
	 *
	 * @from 1.6
	 *
	 * Send a User successful login notification to Telegram
	 *
	 * @param $user_login
	 *
	 * @return bool
	 */

	public function send_tg_successful_login( $user_login, $otp = false ) {
		$chat_id = get_option( $this->namespace )['chat_id'];

		$ip_address = ( isset( $_SERVER["HTTP_CF_CONNECTING_IP"] ) ? $_SERVER["HTTP_CF_CONNECTING_IP"] : $_SERVER['REMOTE_ADDR'] );

		if ( $this->sendMessage( sprintf( __( "Valid authentication for the user: %s (IP: %s, OTP: %s)", "two-factor-login-telegram" ), $user_login, $ip_address, ($otp ? "YES" : "NO") ), $chat_id ) ) {

			if ( get_option( $this->namespace )['ipstack_enabled'] === '1' ) {

				$location = WP_Factor_Utils::get_location( $ip_address );
				if ( $location !== false ) {
					$this->sendLocation( $location->latitude, $location->longitude, $chat_id );
				}

			}

		}

		return false;

	}

}

