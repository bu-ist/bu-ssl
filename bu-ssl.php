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
define( 'BU_SSL_CAMO_KEY', 'YOUR_CAMO_KEY_HERE' );
define( 'BU_SSL_CAMO_DOMAIN', 'sample-camo-domain.herokuapp.com' );
require_once 'vendor/willwashburn/phpamo/src/Client.php';

class SSL {

        private static $camo_key        = BU_SSL_CAMO_KEY; 
        private static $camo_domain     = BU_SSL_CAMO_DOMAIN;

        public static $set_meta_tags    = TRUE;

        // regex adopted from @imme_emosol https://mathiasbynens.be/demo/url-regex
        public static $http_img_regex   = '@<img.*src.*(http:\/\/(-\.)?([^\s/?\.#-]+\.?)+(/[^\s]*)?)"|\'.+>@iS';


        function __construct() {

                // add_action( 'init',                  array( $this, 'init' ) );
                add_action( 'wp_head',                  array( $this, 'add_meta' ) );
                add_action( 'template_redirect',        array( $this, 'do_redirect' ) );
                add_action( 'edit_form_top',            array( $this, 'maybe_editor_warning' ) );

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
                if( !is_ssl() ){
                        wp_redirect( site_url( $_SERVER['REQUEST_URI'], 'https' ) );
                }
        }

        public function search_for_insecure_images( $content ){
            preg_match_all( self::$http_img_regex, $content, $urls, PREG_SET_ORDER );
            return $urls;
        }

        public function has_insecure_images( $content ){
            $urls = self::search_for_insecure_images( $content );
            return ( 0 !== count( $urls[0] ) );
        }

        public function proxy_insecure_images( $content ){
                $camo = new \WillWashburn\Camo\Client();
                $camo->setDomain( self::$camo_domain );
                $camo->setCamoKey( self::$camo_key );
                
                $urls = self::search_for_insecure_images( $content );

                foreach ( $urls as $k => $u ) {
                        $content = str_replace( $u[1], $camo->proxy( $u[1] ), $content );
                }

                return $content;
        }

        public function maybe_editor_warning(){
            global $post;
            if( self::has_insecure_images( $post->post_content ) ){
                printf( 
                    '<div class="notice notice-error"><p>%s</p></div>',
                     __('This post contains images loaded over an insecure connection. These images will be filtered through a <a href="#">secure image proxy</a>.') 
                    );
            }
        }
} 
$bu_ssl = new SSL();

