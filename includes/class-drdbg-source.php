<?php
/**
 * Dr. Debug — Source Detection
 *
 * Detects the source type (plugin, theme, core, or unknown) and slug
 * from a file path. Used to attribute errors to their origin so that
 * users can quickly identify which plugin or theme is causing issues.
 *
 * @package Dr_Debug
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Class Drdbg_Source
 *
 * Provides a static method to detect the source type and slug from a
 * given file path. Uses pattern matching against standard WordPress
 * directory structures.
 */
class Drdbg_Source {

        /**
         * Detect source type and slug from a file path.
         *
         * Detection order:
         * 1. Plugin:  /wp-content/plugins/SLUG/  → type 1, slug = directory name.
         * 2. Theme:   /wp-content/themes/SLUG/   → type 2, slug = directory name.
         * 3. Core:    /wp-includes/ or /wp-admin/ → type 3, slug = 'wordpress'.
         * 4. Core:    Inside ABSPATH but not wp-content → type 3, slug = 'wordpress'.
         * 5. Unknown: Everything else → type 0, slug = ''.
         *
         * @since 1.0.0
         *
         * @param string $file Absolute file path.
         * @return array {
         *     @type int    $type Source type constant (0-3).
         *     @type string $slug Source slug (plugin/theme directory name or 'wordpress').
         * }
         */
        public static function detect( $file ) {
                // Handle empty input.
                if ( empty( $file ) ) {
                        return array(
                                'type' => DRDBG_SOURCE_UNKNOWN,
                                'slug' => '',
                        );
                }

                // Normalise path separators to forward slashes.
                $file = str_replace( '\\', '/', $file );

                // 1. Plugin: /wp-content/plugins/SLUG/
                if ( preg_match( '#/wp-content/plugins/([^/]+)/#', $file, $m ) ) {
                        return array(
                                'type' => DRDBG_SOURCE_PLUGIN,
                                'slug' => $m[1],
                        );
                }

                // 2. Theme: /wp-content/themes/SLUG/
                if ( preg_match( '#/wp-content/themes/([^/]+)/#', $file, $m ) ) {
                        return array(
                                'type' => DRDBG_SOURCE_THEME,
                                'slug' => $m[1],
                        );
                }

                // 3. Core: /wp-includes/ or /wp-admin/
                if ( preg_match( '#/(wp-includes|wp-admin)/#', $file ) ) {
                        return array(
                                'type' => DRDBG_SOURCE_CORE,
                                'slug' => 'wordpress',
                        );
                }

                // 4. Core fallback: inside ABSPATH but not inside wp-content.
                if ( defined( 'ABSPATH' ) ) {
                        $abs_path = str_replace( '\\', '/', ABSPATH );
                        if ( strpos( $file, $abs_path ) === 0 && strpos( $file, '/wp-content/' ) === false ) {
                                return array(
                                        'type' => DRDBG_SOURCE_CORE,
                                        'slug' => 'wordpress',
                                );
                        }
                }

                // 5. Unknown.
                return array(
                        'type' => DRDBG_SOURCE_UNKNOWN,
                        'slug' => '',
                );
        }
}
