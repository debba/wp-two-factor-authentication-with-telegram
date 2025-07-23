<?php

class WP_Factor_Auth_Factory
{
    const METHOD_TELEGRAM_OTP = 'telegram_otp';
    const METHOD_RECOVERY_CODES = 'recovery_codes';

    /**
     * @var array Auth method instances cache
     */
    private static $instances = [];

    /**
     * Create or get an authentication method instance
     * @param string $method_type The type of authentication method
     * @return WP_Factor_Auth_Method|null The authentication method instance
     */
    public static function create($method_type)
    {
        if (isset(self::$instances[$method_type])) {
            return self::$instances[$method_type];
        }

        switch ($method_type) {
            case self::METHOD_TELEGRAM_OTP:
                self::$instances[$method_type] = new WP_Factor_Telegram_OTP();
                break;

            case self::METHOD_RECOVERY_CODES:
                self::$instances[$method_type] = new WP_Factor_Recovery_Codes();
                break;

            default:
                return null;
        }

        return self::$instances[$method_type];
    }

    /**
     * Get the appropriate auth method based on login method
     * @param string $login_method The login method from form data
     * @return WP_Factor_Auth_Method|null
     */
    public static function getByLoginMethod($login_method)
    {
        switch ($login_method) {
            case 'recovery':
                return self::create(self::METHOD_RECOVERY_CODES);

            case 'telegram':
            default:
                return self::create(self::METHOD_TELEGRAM_OTP);
        }
    }

    /**
     * Validate a code using the appropriate method
     * @param string $code The code to validate
     * @param int $user_id The user ID
     * @param string $login_method The login method
     * @return bool True if valid, false otherwise
     */
    public static function validateByMethod($code, $user_id, $login_method = 'telegram')
    {
        $auth_method = self::getByLoginMethod($login_method);
        if (!$auth_method) {
            return false;
        }

        return $auth_method->validate_code($code, $user_id);
    }

}
