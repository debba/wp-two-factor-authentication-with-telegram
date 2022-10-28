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
	 * Send telegram message
	 *
	 * @param $msg
	 * @param $chat_id
	 *
	 * @return bool
	 */

	public function send( $msg, $chat_id ) {

		$request = $this->make_request( "/sendMessage", array(
			'chat_id' 		=> $chat_id,
			'text'    		=> $msg,
			'parse_mode'	=> 'HTML'
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
			$this->lastError = sprintf( esc_html__( "%s (Error code %d)", "two-factor-login-telegram" ), $body->description, $body->error_code);

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


		return $this->send( sprintf( esc_html__( "This is your access code: %s", "two-factor-login-telegram" ), $token ), $chat_id );
	}

	/**
	 * Send a User failed login notification to Telegram
	 *
	 * @param $user_login
	 *
	 * @return bool
	 */

	public function send_tg_failed_login( $user_login ) {
		
		// Get plugin options
		$options = get_option($this->namespace);
		
		// Get Chat ID
		$chat_id = $options['chat_id'];

		/**
		 * @from 1.2
		 * Get IP address behind CloudFlare proxy
		 */

		// Get IP from computer attempting to login
		$ip_address = (isset($_SERVER["HTTP_CF_CONNECTING_IP"]) ? wp_unslash($_SERVER["HTTP_CF_CONNECTING_IP"]) : wp_unslash($_SERVER['REMOTE_ADDR']));

		
		 if ( $options['show_site_name'] === '1' && $options['show_site_url'] === '1' ) {

			// Get site name
			$site_name = get_bloginfo('name');

			// Get site URL
			$site_url = home_url();
			
			// Message with site name
			/* translators: 1. Site name, 2. Site URL, 3. Username, 4. IP address */
			$msg = sprintf(__("Failed attempt to access to the site <strong>%s</strong> (%s) for the user: %s (IP: %s)", "two-factor-login-telegram"), $site_name, $site_url, $user_login, $ip_address);

		 } elseif ( $options['show_site_name'] === '1' ) {

			// Get site name
			$site_name = get_bloginfo('name');

			// Message with URL
			/* translators: 1. Site name, 2. Username, 3. IP address */
			$msg = sprintf(__("Failed attempt to access to the site <strong>%s</strong> for the user: %s (IP: %s)", "two-factor-login-telegram"), $site_name, $user_login, $ip_address);

		} elseif ( $options['show_site_url'] === '1' ) {

			// Get site URL
			$site_url = home_url();

			// Message with URL
			/* translators: 1. Site URL, 2. Username, 3. IP address */
			$msg = sprintf(__("Failed attempt to access to the site %s for the user: %s (IP: %s)", "two-factor-login-telegram"), $site_url, $user_login, $ip_address);

		} else {

			// Message just with Username and IP address
			/* translators: 1. Username, 2. IP address */
			$msg = sprintf(__("Failed attempt to access for the user: %s (IP: %s)", "two-factor-login-telegram"), $user_login, $ip_address);

		}

		return $this->send( $msg, $chat_id );
	}

}

