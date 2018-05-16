<?php

final class WP_Factor_Utils {

	private static $namespace = "tg_col";

	/**
	 *
	 * Get location by IP using IPStack
	 *
	 * @param $ip
	 *
	 * @return false | mixed
	 */

	public static function get_location( $ip ) {

		$endpoint = sprintf( "http://api.ipstack.com/%s?access_key=%s", $ip, get_option(self::$namespace)['ipstack_apikey'] );

		$request = wp_remote_get( $endpoint );

		if ( is_wp_error( $request ) ) {
			return false;
		}

		$response = json_decode( wp_remote_retrieve_body( $request ) );

		return $response;

	}

}