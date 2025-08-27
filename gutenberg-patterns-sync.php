<?php
/*
Plugin Name: Gutenberg Patterns Sync (Export/Import)
Version: 1.0.0
License: GPL3.0+
Plugin URI: https://maximeculea.fr
Description: Export and import block patterns (wp_block) as JSON via WP-CLI. Dev‑oriented to sync local patterns with other environments (staging, production).
Author: Maxime Culea
Author URI: https://maximeculea.fr
*/

// don't load directly
if ( ! defined( 'ABSPATH' ) ) {
    die( '-1' );
}

// Plugin constants
define( 'MC_PATTERNS_CLI_VERSION', '1.0.0' );
define( 'MC_PATTERNS_CLI_MIN_PHP_VERSION', '7.4' );

// Plugin URL and PATH
define( 'MC_PATTERNS_CLI_URL', plugin_dir_url( __FILE__ ) );
define( 'MC_PATTERNS_CLI_DIR', plugin_dir_path( __FILE__ ) );
define( 'MC_PATTERNS_CLI_PLUGIN_DIRNAME', basename( rtrim( dirname( __FILE__ ), '/' ) ) );

/**
 * Autoload all the things \o/
 */
require_once MC_PATTERNS_CLI_DIR . 'autoload.php';

add_action( 'plugins_loaded', 'init_wide_patterns_cli_plugin' );
/**
 * Init the plugin
 */
function init_wide_patterns_cli_plugin() {
    if ( ! defined( 'WP_CLI' ) ) {
        return;
    }

    // Register the unified `patterns` command with subcommands `export` and `import`.
    \WP_CLI::add_command( 'patterns', 'MC\WP_CLI_Commands\Patterns' );
}
