<?php namespace MC\WP_CLI_Commands;

class Patterns_Export extends
    \WP_CLI_Command {
    /**
     * Export all wp_block (synced patterns) to JSON files (one per block).
     *
     * ## OPTIONS
     * [--dir=<path>]
     * : Destination directory. Default: current theme's patterns/blocks-sync
     *
     * [--pretty]
     * : Pretty-print JSON. Default: true
     *
     * [--prefix=<slug_prefix>]
     * : Slug prefix to include in exported JSON slug field. Default: theme textdomain
     *
     * ## EXAMPLES
     * wp patterns export
     * wp patterns export --dir=<path> --no-pretty --prefix=<slug_prefix>
     */
    public function export( $args, $assoc_args ) {
        $blocks_dir = isset( $assoc_args[ 'dir' ] ) ? (string) $assoc_args[ 'dir' ] : get_theme_file_path( '/patterns/blocks-sync' );
        $pretty     = ! isset( $assoc_args[ 'no-pretty' ] );
        $prefix     = isset( $assoc_args[ 'prefix' ] ) ? (string) $assoc_args[ 'prefix' ] : ( function_exists( 'wp_get_theme' ) ? wp_get_theme()->get( 'TextDomain' ) : '' );

        if ( ! is_dir( $blocks_dir ) && ! wp_mkdir_p( $blocks_dir ) ) {
            \WP_CLI::error( "Cannot create blocks-dir: {$blocks_dir}" );
        }

        $taxonomy = 'wp_pattern_category';
        if ( ! taxonomy_exists( $taxonomy ) ) {
            register_taxonomy( $taxonomy, 'wp_block', [ 'public' => false, 'show_ui' => false ] );
        }

        $q = new \WP_Query( [
            'post_type'      => 'wp_block',
            'posts_per_page' => - 1,
            'orderby'        => 'name',
            'order'          => 'ASC',
            'post_status'    => 'publish',
        ] );

        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | ( $pretty ? JSON_PRETTY_PRINT : 0 );
        $count = 0;

        foreach ( $q->posts as $post ) {
            $title   = get_the_title( $post );
            $slug    = ! empty( $prefix ) ? sprintf( '%s/%s', $prefix, sanitize_title( $title ) ) : sanitize_title( $title );
            $content = get_post_field( 'post_content', $post );

            $terms = get_the_terms( $post, $taxonomy );
            $cats  = [];
            if ( is_array( $terms ) ) {
                foreach ( $terms as $t ) {
                    $cats[] = $t->name;
                }
                $cats = array_values( array_unique( $cats ) );
            }

            $data = [
                'title'      => $title,
                'slug'       => $slug,
                'content'    => (string) $content,
                'categories' => $cats,
                'syncStatus' => get_post_meta( $post->ID, 'wp_pattern_sync_status', true ) ?: 'synced',
            ];

            $json = json_encode( $data, $flags );
            if ( $json === false ) {
                \WP_CLI::warning( "JSON encode failed for {$slug}" );
                continue;
            }

            $file = rtrim( $blocks_dir, '/' ) . sprintf( '/%s.json', sanitize_title( $title ) );
            if ( file_put_contents( $file, $json ) === false ) {
                \WP_CLI::warning( "Failed to write {$file}" );
                continue;
            }

            \WP_CLI::log( "[OK] {$slug} -> {$file}" );
            $count ++;
        }

        \WP_CLI::success( "Exported {$count} block(s) to {$blocks_dir}" );
    }
}
