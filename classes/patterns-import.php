<?php namespace MC\WP_CLI_Commands;

class Patterns_Import extends
    \WP_CLI_Command {
    /**
     * Import all wp_block (synced patterns) from JSON files in a directory.
     * Creates/updates by slug, assigns categories, preserves sync status, deletes missing ones.
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
     *
     * ## EXAMPLES
     * wp patterns import
     * wp patterns import --dir=<path> --no-verbose --dry-run
     */
    public function import( $args, $assoc_args ) {
        $blocks_dir = isset( $assoc_args[ 'dir' ] ) ? (string) $assoc_args[ 'dir' ] : get_theme_file_path( '/patterns/blocks-sync' );
        $dry_run    = isset( $assoc_args[ 'dry-run' ] );
        $verbose    = ! isset( $assoc_args[ 'no-verbose' ] );

        if ( ! is_dir( $blocks_dir ) ) {
            \WP_CLI::error( "Directory not found: {$blocks_dir}" );
        }

        $taxonomy = 'wp_pattern_category';
        if ( ! taxonomy_exists( $taxonomy ) ) {
            register_taxonomy( $taxonomy, 'wp_block', [ 'public' => false, 'show_ui' => false ] );
        }

        $log = function( string $msg ) use ( $verbose ) {
            if ( $verbose ) {
                \WP_CLI::log( $msg );
            }
        };

        $ensure_terms = function( int $post_id, array $cats ) use ( $taxonomy, $dry_run, $log ) {
            if ( empty( $cats ) ) {
                return;
            }
            $term_ids = [];
            foreach ( $cats as $name ) {
                $name = trim( (string) $name );
                if ( $name === '' ) {
                    continue;
                }
                $term = term_exists( $name, $taxonomy );
                if ( ! $term ) {
                    if ( $dry_run ) {
                        $log( "[DRY] Would create term '{$name}'" );
                        continue;
                    }
                    $created = wp_insert_term( $name, $taxonomy );
                    if ( is_wp_error( $created ) ) {
                        \WP_CLI::warning( "Term create failed '{$name}': " . $created->get_error_message() );
                        continue;
                    }
                    $term_ids[] = (int) $created[ 'term_id' ];
                } else {
                    $term_ids[] = (int) ( is_array( $term ) ? $term[ 'term_id' ] : $term );
                }
            }
            if ( $term_ids ) {
                if ( $dry_run ) {
                    $log( "[DRY] Would assign terms to #{$post_id}: " . implode( ',', $term_ids ) );

                    return;
                }
                $res = wp_set_object_terms( $post_id, $term_ids, $taxonomy );
                if ( is_wp_error( $res ) ) {
                    \WP_CLI::warning( "Assign terms failed for #{$post_id}: " . $res->get_error_message() );
                }
            }
        };

        $apply_sync_status = function( int $post_id, ?string $status ) use ( $dry_run, $log ) {
            $status = ( $status === 'unsynced' ) ? 'unsynced' : 'synced';
            if ( $status === 'unsynced' ) {
                if ( $dry_run ) {
                    $log( "[DRY] Would set syncStatus=unsynced on #{$post_id}" );

                    return;
                }
                update_post_meta( $post_id, 'wp_pattern_sync_status', 'unsynced' );
            } else {
                if ( $dry_run ) {
                    $log( "[DRY] Would clear syncStatus meta on #{$post_id} (synced)" );

                    return;
                }
                delete_post_meta( $post_id, 'wp_pattern_sync_status' );
            }
        };

        $files = glob( rtrim( $blocks_dir, '/' ) . '/*.json' ) ?: [];
        if ( ! $files ) {
            \WP_CLI::success( "No JSON files found in {$blocks_dir}; nothing to import." );

            return;
        }

        $seen      = [];
        $created   = 0;
        $updated   = 0;
        $unchanged = 0;

        foreach ( $files as $file ) {
            $raw = file_get_contents( $file );
            if ( $raw === false ) {
                \WP_CLI::warning( "Cannot read {$file}" );
                continue;
            }
            $data = json_decode( $raw, true );
            if ( ! is_array( $data ) ) {
                \WP_CLI::warning( "Invalid JSON {$file}" );
                continue;
            }

            $slug    = isset( $data[ 'slug' ] ) ? sanitize_title( (string) $data[ 'slug' ] ) : null;
            $title   = isset( $data[ 'title' ] ) ? (string) $data[ 'title' ] : ( $slug ?? '' );
            $content = $data[ 'content' ] ?? null;
            $cats    = ( isset( $data[ 'categories' ] ) && is_array( $data[ 'categories' ] ) ) ? $data[ 'categories' ] : [];
            $sync    = isset( $data[ 'syncStatus' ] ) ? (string) $data[ 'syncStatus' ] : null;

            if ( ! $slug || ! $content ) {
                \WP_CLI::warning( "Skip (needs slug+content): {$file}" );
                continue;
            }
            $seen[] = $slug;

            $existing = get_page_by_path( $slug, OBJECT, 'wp_block' );
            $postarr  = [
                'post_type'    => 'wp_block',
                'post_status'  => 'publish',
                'post_name'    => $slug,
                'post_title'   => ( $title !== '' ) ? $title : $slug,
                'post_content' => (string) $content,
            ];

            if ( $existing ) {
                $needs = ( $existing->post_content !== $postarr[ 'post_content' ] ) || ( $existing->post_title !== $postarr[ 'post_title' ] );
                if ( $needs ) {
                    if ( $dry_run ) {
                        $log( "[DRY] Would update {$slug} (#{$existing->ID})" );
                    } else {
                        $postarr[ 'ID' ] = $existing->ID;
                        $id              = wp_update_post( $postarr, true );
                        if ( is_wp_error( $id ) ) {
                            \WP_CLI::warning( "Update failed {$slug}: " . $id->get_error_message() );
                            continue;
                        }
                        \WP_CLI::success( "Updated {$slug} (#{$id})" );
                        $updated ++;
                        $ensure_terms( (int) $id, $cats );
                        $apply_sync_status( (int) $id, $sync );
                    }
                } else {
                    $log( "Unchanged {$slug} (#{$existing->ID})" );
                    $unchanged ++;
                    if ( ! $dry_run ) {
                        $ensure_terms( (int) $existing->ID, $cats );
                        $apply_sync_status( (int) $existing->ID, $sync );
                    }
                }
            } else {
                if ( $dry_run ) {
                    $log( "[DRY] Would create {$slug}" );
                } else {
                    $id = wp_insert_post( $postarr, true );
                    if ( is_wp_error( $id ) ) {
                        \WP_CLI::warning( "Create failed {$slug}: " . $id->get_error_message() );
                        continue;
                    }
                    \WP_CLI::success( "Created {$slug} (#{$id})" );
                    $created ++;
                    $ensure_terms( (int) $id, $cats );
                    $apply_sync_status( (int) $id, $sync );
                }
            }
        }

        $q = new \WP_Query( [
            'post_type'      => 'wp_block',
            'posts_per_page' => - 1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ] );
        foreach ( $q->posts as $id ) {
            $slug = get_post_field( 'post_name', $id );
            if ( ! in_array( $slug, $seen, true ) ) {
                if ( $dry_run ) {
                    $log( "[DRY] Would delete missing {$slug} (#{$id})" );
                } else {
                    wp_delete_post( (int) $id, true );
                    \WP_CLI::success( "Deleted missing {$slug} (#{$id})" );
                }
            }
        }

        \WP_CLI::success( ( $dry_run ? "[DRY] " : "" ) . "Done. Created: {$created}, Updated: {$updated}, Unchanged: {$unchanged}" );
    }
}
