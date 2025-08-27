<?php namespace MC\WP_CLI_Commands;

class Patterns extends \WP_CLI_Command {
    /**
     * Export all wp_block (synced patterns) to JSON files.
     *
     * ## OPTIONS
     * [--dir=<path>]
     * : Destination directory. Default: current theme's patterns/blocks-sync
     *
     * [--no-pretty]
     * : Disable pretty-printed JSON
     *
     * [--prefix=<slug-prefix>]
     * : Slug prefix for exported JSON slug field. Default: theme textdomain
     */
    public function export( $args, $assoc_args ) {
        $impl = new Patterns_Export();
        $impl->export( $args, $assoc_args );
    }

    /**
     * Import all wp_block (synced patterns) from JSON files.
     *
     * ## OPTIONS
     * [--dir=<path>]
     * : Source directory of JSON files. Default: current theme's patterns/blocks-sync
     *
     * [--dry-run]
     * : Show what would change, but do not modify anything
     *
     * [--no-verbose]
     * : Quieter logs (default is verbose)
     */
    public function import( $args, $assoc_args ) {
        $impl = new Patterns_Import();
        $impl->import( $args, $assoc_args );
    }
}
