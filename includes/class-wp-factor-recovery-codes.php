<?php

class WP_Factor_Recovery_Codes extends WP_Factor_Auth_Method
{
    /**
     * Generate N random recovery codes
     * @param int $num
     * @param int $length
     * @return array
     */
    public function generate_recovery_codes($num = 8, $length = 10)
    {
        $codes = [];
        for ($i = 0; $i < $num; $i++) {
            $codes[] = $this->generate_auth_code($length);
        }
        return $codes;
    }


    /**
     * Get hashed recovery codes for user
     * @param int $user_id
     * @return array
     */
    public function get_user_recovery_codes($user_id)
    {
        $codes = get_user_meta($user_id, 'tg_wp_factor_recovery_codes', true);
        if (!is_array($codes))
            return [];
        return $codes;
    }

    /**
     * Save hashed recovery codes for user
     * @param int $user_id
     * @param array $codes
     */
    public function set_user_recovery_codes($user_id, $codes)
    {
        $hashed = array_map(function ($c) {
            return $this->hash_code($this->normalize_code($c));
        }, $codes);
        update_user_meta($user_id, 'tg_wp_factor_recovery_codes', $hashed);
    }

    /**
     * Consume (delete) a recovery code if valid
     * @param int $user_id
     * @param string $code
     * @return bool
     */
    public function consume_recovery_code($user_id, $code)
    {
        $hashed = $this->hash_code($this->normalize_code($code));
        $codes = $this->get_user_recovery_codes($user_id);
        $idx = array_search($hashed, $codes);
        if ($idx !== false) {
            unset($codes[$idx]);
            update_user_meta($user_id, 'tg_wp_factor_recovery_codes', array_values($codes));
            return true;
        }
        return false;
    }

    /**
     * Check if user has recovery codes
     * @param int $user_id
     * @return bool
     */
    public function has_recovery_codes($user_id)
    {
        $codes = $this->get_user_recovery_codes($user_id);
        return is_array($codes) && count($codes) > 0;
    }

    /**
     * Regenerate recovery codes for user
     * @param int $user_id
     * @param int $num
     * @param int $length
     * @return array The new plain text codes
     */
    public function regenerate_recovery_codes($user_id, $num = 8, $length = 10)
    {
        $codes = $this->generate_recovery_codes($num, $length);
        $this->set_user_recovery_codes($user_id, $codes);
        return $codes;
    }

    /**
     * Validate and consume a recovery code for login
     * @param string $code The recovery code entered by user
     * @param int $user_id User ID
     * @return bool True if code is valid and has been consumed
     */
    public function validate_recovery_code($code, $user_id)
    {
        $normalized_code = $this->normalize_code($code);

        if (empty($normalized_code)) {
            return false;
        }

        if (!$this->has_recovery_codes($user_id)) {
            return false;
        }

        return $this->consume_recovery_code($user_id, $normalized_code);
    }

    /**
     * Delete all recovery codes for a user
     * @param int $user_id
     */
    public function delete_user_recovery_codes($user_id)
    {
        delete_user_meta($user_id, 'tg_wp_factor_recovery_codes');
    }

    // Implementation of abstract methods

    /**
     * Validate a code for a specific user (alias for validate_recovery_code)
     * @param string $code The code to validate
     * @param int $user_id The user ID
     * @return bool True if valid, false otherwise
     */
    public function validate_code($code, $user_id)
    {
        return $this->validate_recovery_code($code, $user_id);
    }

    /**
     * Generate new codes for a user
     * @param int $user_id The user ID
     * @param array $options Additional options (num, length)
     * @return array The generated codes
     */
    public function generate_codes($user_id, $options = [])
    {
        $num = isset($options['num']) ? $options['num'] : 8;
        $length = isset($options['length']) ? $options['length'] : 10;
        return $this->regenerate_recovery_codes($user_id, $num, $length);
    }

    /**
     * Check if user has active codes (alias for has_recovery_codes)
     * @param int $user_id The user ID
     * @return bool True if user has codes, false otherwise
     */
    public function has_codes($user_id)
    {
        return $this->has_recovery_codes($user_id);
    }

    /**
     * Delete all codes for a user (alias for delete_user_recovery_codes)
     * @param int $user_id The user ID
     * @return bool True on success, false on failure
     */
    public function delete_user_codes($user_id)
    {
        $this->delete_user_recovery_codes($user_id);
        return true;
    }
}