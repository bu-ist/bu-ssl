<?php
/*
Plugin Name: BU SSL
Plugin URI: http://www.bu.edu/tech/
Description: Hi, I help transition WordPress sites to SSL.
Version: 0.1
Author: Boston University IS&T
Contributors: Andrew Bauer

GNU General Public License, Free Software Foundation <http://creativecommons.org/licenses/GPL/2.0/>

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

namespace BU\WordPress\Plugins;

define( 'BU_SSL_VERSION', '0.1' );
// define( 'BU_SSL_DEBUG', true );

/* 
 * Camo Image Proxy
 * @see: https://github.com/atmos/camo
 * @see: https://github.com/willwashburn/Phpamo
*/
if( ! defined( 'BU_SSL_CAMO_KEY' ) ){
    define( 'BU_SSL_CAMO_KEY', 'YOUR_CAMO_KEY_HERE' );
}

if( ! defined( 'BU_SSL_CAMO_DOMAIN' ) ){
    define( 'BU_SSL_CAMO_DOMAIN', 'sample-camo-domain.herokuapp.com' );
}

if ( defined('WP_CLI') && WP_CLI ) {
    include __DIR__ . '/inc/wpcli.php';
}

require_once __DIR__ . '/vendor/willwashburn/phpamo/src/Client.php';

class SSL {

    private static $camo_key        = BU_SSL_CAMO_KEY; 
    private static $camo_domain     = BU_SSL_CAMO_DOMAIN;

    public static $post_meta_key    = '_bu_ssl_found_http_urls';
    public static $always_redirect  = FALSE;
    public static $set_meta_tags    = TRUE;

    // regex adopted from @imme_emosol https://mathiasbynens.be/demo/url-regex
    public static $http_img_regex   = '@<img.*src.*(http:\/\/[^\s/$.?#].[^\s\'"]*).+>@iS';


    function __construct() {
        // add_action( 'init',                  array( $this, 'init' ) );
        add_action( 'wp_head',                  array( $this, 'add_meta' ) );
        add_action( 'template_redirect',        array( $this, 'do_redirect' ) );
        add_action( 'edit_form_top',            array( $this, 'maybe_editor_warning' ) );
        add_action( 'save_post',                array( $this, 'update_post' ) );

        add_filter( 'wp_headers',               array( $this, 'add_headers' ) );
        add_filter( 'the_content',              array( $this, 'proxy_insecure_images' ), 999 );
    }

    public static function init(){

    }

    public static function is_debug(){
            return ( defined( 'BU_SSL_DEBUG' ) && BU_SSL_DEBUG );
    }

    public static function add_meta(){
        if( self::$set_meta_tags ){
                echo '<meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests" />'."\n";
        }
    }

    public static function add_headers( $headers ){
        $headers['Content-Security-Policy'] = 'upgrade-insecure-requests';
        return $headers;
    }

    public static function do_redirect(){
        if( self::$always_redirect && !is_ssl() ){
            wp_redirect( site_url( $_SERVER['REQUEST_URI'], 'https' ) );
        }
    }

    public function search_for_insecure_images( $content ){
        preg_match_all( self::$http_img_regex, $content, $urls, PREG_SET_ORDER );
        foreach ( $urls as $k => $u ) {
            if( 0 === strpos( $u[1], get_site_url( null, null, 'http' ) ) ){
                array_splice( $urls, $k, 1 );
            }
        }
        return $urls;
    }

    public function has_insecure_images( $content ){
        $urls = self::search_for_insecure_images( $content );
        return ( 0 !== count( $urls[0] ) );
    }

    public function proxy_insecure_images( $content, $force_ssl=false ){
        if( is_ssl() || $force_ssl ){
            $camo = new \WillWashburn\Camo\Client();
            $camo->setDomain( self::$camo_domain );
            $camo->setCamoKey( self::$camo_key );
            
            $urls = self::search_for_insecure_images( $content );

            foreach ( $urls as $k => $u ) {
                $content = str_replace( $u[1], $camo->proxy( $u[1] ), $content );
            }
        }

        return $content;
    }

    public function update_post( $post_ID ){
        if ( wp_is_post_revision( $post_ID ) ){
            return;
        }

        $post = get_post( $post_ID );   

        $content = str_replace( get_site_url( null, null, 'http' ), get_site_url( null, null, 'relative' ), $post->post_content );     
        $urls = self::search_for_insecure_images( $content );

        if( count( $urls ) ){
            update_post_meta( $post_ID, self::$post_meta_key, $urls );
        } else {
            delete_post_meta( $post_ID, self::$post_meta_key );   
        }

        return $urls;
    }

    public function maybe_editor_warning(){
        global $post;
        
        $urls = get_post_meta( $post->ID, self::$post_meta_key, true );

        if( empty( $urls ) ){
            $urls = self::update_post( $post->ID );
        }

        if( count( $urls ) ){
            printf( 
                '<div class="notice notice-error"><p>%s</p></div>',
                 __('&#x1F513; This post contains images loaded over an insecure connection. These images will be filtered through a <a href="#">secure image proxy</a>.') 
            );
        }
    }
} 
$bu_ssl = new SSL();

