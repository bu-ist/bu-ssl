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
     *     wp bu-ssl findimages --ssldebug
     *
     * @synopsis [--ssldebug]
     */
    function findimages( $args, $assoc_args ) {        
        global $wpdb;

        $postids = get_posts( array(
                'post_type'                 => array( 'any' ),
                'nopaging'                  => true,
                'cache_results'             => false,
                'update_post_meta_cache'    => false,
                'update_post_term_cache'    => false,
                'fields'                    => 'ids',
            ) );
        
        WP_CLI::log( sprintf( "Scanning site #%d: Checking %d posts", get_current_blog_id(), count( $postids ) ) );

        $this->ssl->remove_all_postmeta();

        if ( $postids ) {
            foreach ( $postids as $id ){ 
                $post = get_post( $id );

                $urls = $this->ssl->search_for_insecure_images_by_post( $id );
                $debug = '';

                if( isset( $assoc_args['ssldebug'] ) ){
                    $debug = "\n" . var_export( $urls, true ) . "\n";
                }
                
                $this->ssl->do_update_postmeta( $id, $urls );
                
                if( count( $urls ) ){
                    WP_CLI::warning( sprintf( 
                        "#%d '%s' - %d insecure image(s)", 
                        $id, 
                        $post->post_title,
                        count( $urls ), 
                        get_permalink( $post )
                    ) . $debug );
                }
            }
            WP_CLI::success( sprintf( "%d posts scanned.", count( $postids ) ) );
        }
    }
}

WP_CLI::add_command( 'bu-ssl', 'SSL_CLI' );