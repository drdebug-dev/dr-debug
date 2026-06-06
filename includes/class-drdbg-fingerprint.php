<?php
/**
 * Dr. Debug — Fingerprint Generation
 *
 * Generates SHA-1 fingerprints from normalised error messages and file
 * paths to uniquely identify and group recurring errors. Line numbers
 * are intentionally excluded from the fingerprint so that the same
 * error on different lines is still grouped together.
 *
 * @package Dr_Debug
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Class Drdbg_Fingerprint
 *
 * Provides static methods for normalising error messages and generating
 * fingerprints used to deduplicate and group errors.
 */
class Drdbg_Fingerprint {

        /**
         * Normalise an error message for fingerprinting.
         *
         * Processing order:
         * 1. Remove hex addresses (0x7f…).
         * 2. Replace single-quoted strings with '?'.
         * 3. Replace double-quoted strings with '?'.
         * 4. Replace standalone numbers with '#'.
         * 5. Collapse multiple spaces and trim.
         *
         * @since 1.0.0
         *
         * @param string $message Raw error message.
         * @return string Normalised message.
         */
        public static function normalize( $message ) {
                // Remove hex addresses (e.g. 0x7f8a3b2c1d0e).
                $msg = preg_replace( '/0x[0-9a-fA-F]+/', '', $message );

                // Replace quoted strings with '?'.
                $msg = preg_replace( "/'[^']*'/", '?', $msg );
                $msg = preg_replace( '/"[^"]*"/', '?', $msg );

                // Replace standalone numbers with '#'.
                $msg = preg_replace( '/\b\d+\b/', '#', $msg );

                // Collapse multiple spaces and trim.
                $msg = preg_replace( '/\s+/', ' ', $msg );
                $msg = trim( $msg );

                return $msg;
        }

        /**
         * Generate a fingerprint from a normalised message and file path.
         *
         * Line number is intentionally NOT included in the fingerprint.
         * This means the same error on different lines of the same file
         * will share a fingerprint and be grouped together.
         *
         * @since 1.0.0
         *
         * @param string $message Raw error message.
         * @param string $file    File path where the error occurred.
         * @return string 40-character SHA-1 hex digest.
         */
        public static function generate( $message, $file ) {
                $normalized = self::normalize( $message );
                return sha1( $normalized . ':' . $file );
        }
}
