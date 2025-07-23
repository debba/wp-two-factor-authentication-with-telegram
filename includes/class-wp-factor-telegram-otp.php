<?php

class WP_Factor_Telegram_OTP extends WP_Factor_Auth_Method
{
    /**
     * Generate a unique authentication code that doesn't exist in database
     * @param int $length Code length
     * @return string
     */
    private function get_unique_auth_code($length = 5)
    {
        do {
            $token = $this->generate_auth_code($length);
        } while ($this->token_exists($token));

        return $token;
    }

    /**
     * Check if a token exists in the database
     * @param string $token The token to check
     * @return bool True if exists, false otherwise
     */
    private function token_exists($token)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'telegram_auth_codes';
        $current_datetime = current_time('mysql');
        $hashed_token = $this->hash_code($this->normalize_code($token));

        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name 
             WHERE auth_code = %s 
             AND expiration_date > %s",
            $hashed_token,
            $current_datetime
        ));

        return ($result > 0);
    }

    /**
     * Invalidate all existing auth codes for a user
     * @param int $user_id The user ID
     * @return bool True on success, false on failure
     */
    private function invalidate_existing_auth_codes($user_id)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'telegram_auth_codes';

        $result = $wpdb->update(
            $table_name,
            array('expiration_date' => current_time('mysql')),
            array('user_id' => $user_id),
            array('%s'),
            array('%d')
        );

        return $result !== false;
    }

    /**
     * Cleanup old auth codes for a user (keep only 5 most recent)
     * @param int $user_id The user ID
     * @return bool True on success, false on failure
     */
    private function cleanup_old_auth_codes($user_id)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'telegram_auth_codes';

        // Count existing codes for the user
        $auth_codes_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE user_id = %d",
            $user_id
        ));

        // If more than 5 codes exist, delete the oldest ones
        if ($auth_codes_count > 5) {
            $wpdb->query($wpdb->prepare(
                "DELETE FROM $table_name 
                 WHERE user_id = %d 
                 AND id NOT IN (
                     SELECT id FROM (
                         SELECT id FROM $table_name 
                         WHERE user_id = %d 
                         ORDER BY creation_date DESC 
                         LIMIT 5
                     ) AS recent_codes
                 )",
                $user_id,
                $user_id
            ));
        }

        return true;
    }

    /**
     * Save an authentication code for a user
     * @param mixed $user User object or user ID
     * @param int $authcode_length Length of the code to generate
     * @return string|false The generated code on success, false on failure
     */
    public function save_authcode($user, $authcode_length = 5)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'telegram_auth_codes';
        $auth_code = $this->get_unique_auth_code($authcode_length);
        $user_id = is_object($user) ? $user->ID : intval($user);

        $creation_date = current_time('mysql');
        $expiration_date = date('Y-m-d H:i:s', strtotime($creation_date) + WP_FACTOR_AUTHCODE_EXPIRE_SECONDS);

        $this->invalidate_existing_auth_codes($user_id);

        $result = $wpdb->insert(
            $table_name,
            array(
                'auth_code' => $this->hash_code($this->normalize_code($auth_code)),
                'user_id' => $user_id,
                'creation_date' => $creation_date,
                'expiration_date' => $expiration_date
            ),
            array('%s', '%d', '%s', '%s')
        );

        if ($wpdb->insert_id) {
            $this->cleanup_old_auth_codes($user_id);
            return $auth_code;
        }

        return false;
    }

    /**
     * Validate an authentication code for a user
     * @param string $authcode The code to validate
     * @param int $user_id The user ID
     * @return string 'valid', 'invalid', or 'expired'
     */
    public function validate_authcode($authcode, $user_id)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'telegram_auth_codes';
        $hashed_auth_code = $this->hash_code($authcode);
        $current_datetime = current_time('mysql');

        // Check if token exists for this user
        $token_exists_query = $wpdb->prepare(
            "SELECT COUNT(*) 
            FROM $table_name 
            WHERE auth_code = %s 
            AND user_id = %d",
            $hashed_auth_code,
            $user_id
        );

        $token_exists = ($wpdb->get_var($token_exists_query) > 0);

        if (!$token_exists) {
            return 'invalid';
        }

        // Check if token is not expired
        $valid_token_query = $wpdb->prepare(
            "SELECT COUNT(*) 
            FROM $table_name 
            WHERE auth_code = %s 
            AND user_id = %d 
            AND expiration_date > %s",
            $hashed_auth_code,
            $user_id,
            $current_datetime
        );

        $is_valid = ($wpdb->get_var($valid_token_query) > 0);

        if (!$is_valid) {
            return 'expired';
        }

        return 'valid';
    }

    /**
     * Validate code for tokencheck (different validation method)
     * @param string $authcode The code to validate
     * @param string $chat_id The Telegram chat ID
     * @return bool True if valid, false otherwise
     */
    public function validate_tokencheck_authcode($authcode, $chat_id)
    {
        return $this->hash_code($authcode) === get_transient("wp2fa_telegram_authcode_" . $chat_id);
    }

    // Implementation of abstract methods

    /**
     * Validate a code for a specific user
     * @param string $code The code to validate
     * @param int $user_id The user ID
     * @return bool True if valid, false otherwise
     */
    public function validate_code($code, $user_id)
    {
        $normalized_code = $this->normalize_code($code);
        $result = $this->validate_authcode($normalized_code, $user_id);
        return $result === 'valid';
    }

    /**
     * Generate new codes for a user (creates one OTP code)
     * @param int $user_id The user ID
     * @param array $options Additional options (length, etc.)
     * @return array The generated codes
     */
    public function generate_codes($user_id, $options = [])
    {
        $length = isset($options['length']) ? $options['length'] : 5;
        $code = $this->save_authcode($user_id, $length);
        return $code ? [$code] : [];
    }

    /**
     * Check if user has active codes
     * @param int $user_id The user ID
     * @return bool True if user has codes, false otherwise
     */
    public function has_codes($user_id)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'telegram_auth_codes';
        $current_datetime = current_time('mysql');

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name 
             WHERE user_id = %d 
             AND expiration_date > %s",
            $user_id,
            $current_datetime
        ));

        return ($count > 0);
    }

    /**
     * Delete all codes for a user
     * @param int $user_id The user ID
     * @return bool True on success, false on failure
     */
    public function delete_user_codes($user_id)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'telegram_auth_codes';

        $result = $wpdb->delete(
            $table_name,
            array('user_id' => $user_id),
            array('%d')
        );

        return $result !== false;
    }
}
