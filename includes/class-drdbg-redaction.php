<?php
/**
 * Dr. Debug — Secret Redaction
 *
 * Redacts sensitive data from error messages and context before writing
 * to the database. Scans for key=value patterns (password, token, etc.)
 * and for value patterns that look like secrets (Bearer tokens, API
 * keys, Stripe-style keys).
 *
 * @package Dr_Debug
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Class Drdbg_Redaction
 *
 * Provides static methods to scrub sensitive information from strings
 * and error data arrays. Called BEFORE writing to the database.
 */
class Drdbg_Redaction {

        /**
         * Key name patterns that indicate sensitive data.
         *
         * Used in key=value and key:value matching.
         *
         * @since 1.0.0
         * @var string[]
         */
        private static $sensitive_keys = array(
                'password',
                'passwd',
                'pwd',
                'pass',
                'secret',
                'token',
                'api_key',
                'apikey',
                'authorization',
                'auth',
                'key',
                'private_key',
                'access_key',
                'session_id',
        );

        /**
         * Patterns for values that look like secrets.
         *
         * Matched directly against text — the entire match is replaced
         * with '[REDACTED]'.
         *
         * @since 1.0.0
         * @var string[]
         */
        private static $value_patterns = array(
                '/Bearer\s+[A-Za-z0-9\-._~+\/]+=*/i',   // Bearer tokens.
                '/Basic\s+[A-Za-z0-9\-._~+\/]+=*/i',     // Basic auth headers.
                '/sk-[a-zA-Z0-9]{20,}/',                  // OpenAI-style API key.
                '/pk_[a-zA-Z0-9_]{20,}/',                 // Stripe-style publishable key.
        );

        /**
         * Redact sensitive data from a string.
         *
         * Processing order:
         * 1. Replace values in key=value and key:value patterns.
         * 2. Replace values matching known secret patterns.
         *
         * @since 1.0.0
         *
         * @param string $text Input text that may contain secrets.
         * @return string Text with secrets replaced by '[REDACTED]'.
         */
        public static function redact( $text ) {
                if ( empty( $text ) ) {
                        return $text;
                }

                // Redact by key=value / key:value patterns.
                foreach ( self::$sensitive_keys as $key ) {
                        // Match: key = value | key: value | key => value
                        $text = preg_replace(
                                '/(' . preg_quote( $key, '/' ) . ')(\s*[=:]\s*)([^\s,;&"\'}\]]+)/i',
                                '$1$2[REDACTED]',
                                $text
                        );

                        // Also handle array syntax: 'key' => 'value' or "key" => "value".
                        $text = preg_replace(
                                '/(' . preg_quote( $key, '/' ) . ')(\s*=>\s*)([^\s,;&"\'}\]]+)/i',
                                '$1$2[REDACTED]',
                                $text
                        );
                }

                // Redact by value patterns (Bearer tokens, API keys, etc.).
                foreach ( self::$value_patterns as $pattern ) {
                        $text = preg_replace( $pattern, '[REDACTED]', $text );
                }

                return $text;
        }

        /**
         * Redact all sensitive fields of an error data array.
         *
         * Modifies the array in-place by reference. Redacts:
         * - message
         * - stack_trace
         * - request_url
         * - extra
         *
         * @since 1.0.0
         *
         * @param array $data Error data array (passed by reference).
         */
        public static function redact_error_data( &$data ) {
                $fields_to_redact = array( 'message', 'stack_trace', 'request_url', 'extra' );

                foreach ( $fields_to_redact as $field ) {
                        if ( isset( $data[ $field ] ) && is_string( $data[ $field ] ) ) {
                                $data[ $field ] = self::redact( $data[ $field ] );
                        }
                }
        }

        /**
         * Check whether redaction is enabled in plugin settings.
         *
         * @since 1.0.0
         *
         * @return bool True if redaction is enabled, false otherwise.
         */
        public static function is_enabled() {
                $settings = get_option( 'drdbg_settings', array() );
                return ! empty( $settings['redaction_enabled'] );
        }
}
