<?php

class SSL_CLI extends WP_CLI_Command {
    public $ssl;

    function __construct() {
        $this->ssl = new \BU\WordPress\Plugins\SSL();
    }

    /**
     * Updates all postmeta on a site.
     * 
     * ## OPTIONS
     * 
     * <site_id> : ID of the site 
     * 
     * ## EXAMPLES
     * 
     *     wp bu-ssl updatemeta --site=103
     *
     * @synopsis --site=<site_id> [--ssldebug]
     */
    function updatemeta( $args, $assoc_args ) {

        switch_to_blog( $assoc_args['site'] );
        
        global $wpdb;

        $postids = get_posts( array(
                'post_type'                 => array( 'any' ),
                'nopaging'                  => true,
                'cache_results'             => false,
                'update_post_meta_cache'    => false,
                'update_post_term_cache'    => false,
                'fields'                    => 'ids',
            ) );

        if ( $postids ) {
            foreach ( $postids as $id ){ 
                $post = get_post( $id );
                $content = str_replace( get_site_url( null, null, 'http' ), get_site_url( null, null, 'relative' ), $post->post_content );

                if( $this->ssl->has_insecure_images( $content ) ){
                    $urls = $this->ssl->update_post( $id );
                    $debug = ( isset( $assoc_args['ssldebug'] ) ) ? "\n" . var_export( $urls, true ) . "\n" : '';

                    WP_CLI::success( sprintf( 
                        "#%d '%s' (%d updated) - %s", 
                        $id, 
                        $post->post_title,
                        count( $urls ), 
                        get_permalink( $post )
                    ) . $debug );
                }
            }
        }

        restore_current_blog();
    }
}

WP_CLI::add_command( 'bu-ssl', 'SSL_CLI' );