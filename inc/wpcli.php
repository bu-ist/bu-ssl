<?php

class SSL_CLI extends WP_CLI_Command {
    public $ssl;

    function __construct() {
        $this->ssl = new \BU\WordPress\Plugins\SSL();
    }

    /**
     * Find insecure images & update postmeta
     * 
     * ## OPTIONS
     * 
     * <site_id> : ID of the site 
     * 
     * ## EXAMPLES
     * 
     *     wp bu-ssl findimages --site=103
     *
     * @synopsis --site=<site_id> [--ssldebug]
     */
    function findimages( $args, $assoc_args ) {

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

                if( $urls = $this->ssl->has_insecure_images( $id ) ){
                    $debug = '';

                    if( isset( $assoc_args['ssldebug'] ) ){
                        $content = str_replace( get_site_url( null, null, 'http' ), get_site_url( null, null, 'relative' ), $post->post_content );
                        $debug = "\n" . var_export( $urls, true ) . "\n";
                    }
                    
                    $this->ssl->do_update_postmeta( $id, $urls );

                    WP_CLI::warning( sprintf( 
                        "#%d '%s' - %d insecure image(s)", 
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