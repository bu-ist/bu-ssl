<?php
/**
 * BU SSL
 *
 * Hi, I help transition WordPress sites to SSL.
 *
 * @package BU_SSL
 * @author Boston University IS&T
 * @license http://creativecommons.org/licenses/GPL/2.0/ GNU General Public License, Free Software Foundation
 *
 * @wordpress-plugin
 * Plugin Name: BU SSL
 * Plugin URI: http://www.bu.edu/tech/
 * Description: Hi, I help transition WordPress sites to SSL.
 * Version: 0.1
 * Author: Boston University IS&T
 * Author URI: http://www.bu.edu/tech/
 * Contributors: Andrew Bauer
 * License: GPL 2.0
 */

namespace BU\WordPress\Plugins;

// Specify plugin version with a constant.
define( 'BU_SSL_VERSION', '0.1' );

// Set BU_SSL_DEBUG to true if you want to enable debugging.
// Note: This flag seems to not be used anywhere at the moment.
define( 'BU_SSL_DEBUG', false );

/*
 * Camo Image Proxy
 * @see: https://github.com/atmos/camo
 * @see: https://github.com/willwashburn/Phpamo
*/
if ( ! defined( 'BU_SSL_CAMO_KEY' ) || ! defined( 'BU_SSL_CAMO_DOMAIN' ) ) {
	define( 'BU_SSL_CAMO_DISABLED', true );
}

// Add wp-cli commands.
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	include __DIR__ . '/inc/wpcli.php';
}
// Use the phpamo library.
require_once __DIR__ . '/vendor/willwashburn/phpamo/src/Client.php';

// Add customizable settings in wp-admin.
require_once __DIR__ . '/inc/settings.php';

/**
 * Main plugin class
 */
class SSL {
	/**
	 * The Content Security Policy to be used
	 *
	 * @var string
	 */
	private $csp;

	/**
	 * The CSP type
	 *
	 * @var string
	 */
	private $csp_type = 'Content-Security-Policy';

	/**
	 * The plugin's default options
	 *
	 * @var array
	 */
	public $options = array(
		'post_meta_key'             => '_bu_ssl_found_http_urls',
		'always_redirect'           => false,
		'enable_csp'                => false,
		'enforce_csp'               => false,
		'override_url_scheme'       => true,
		'content_security_policy'   => "default-src https: 'unsafe-inline' 'unsafe-eval'",
		'csp_report_url'            => '',
	);

	/**
	 * The html tags that might have insecure content we'll want to proxy or warn about
	 *
	 * @var array
	 */
	private $tags = array(
		array(
			'name' => 'img',
			'attribute' => 'src',
		),
		array(
			'name' => 'img',
			'attribute' => 'srcset',
		),
		array(
			'name' => 'picture',
			'children' => 'source',
			'attribute' => 'srcset',
		),
		array(
			'name' => 'audio',
			'attribute' => 'src',
		),
		array(
			'name' => 'audio',
			'children' => 'source',
			'attribute' => 'src',
		),
		array(
			'name' => 'video',
			'attribute' => 'src',
		),
		array(
			'name' => 'video',
			'children' => 'source',
			'attribute' => 'src',
		),
		array(
			'name' => 'object',
			'attribute' => 'data',
		),
		array(
			'name' => 'iframe',
			'attribute' => 'src',
		),
		array(
			'name' => 'script',
			'attribute' => 'src',
		),
		array(
			'name' => 'link',
			'attribute' => 'href',
		),
	);

	/**
	 * Class constructor
	 */
	function __construct() {
		// Register actions.
		// add_action( 'wp_head',                      array( $this, 'add_meta_tags' ) );
		add_action( 'template_redirect',            array( $this, 'do_redirect' ) );
		add_action( 'edit_form_top',                array( $this, 'maybe_editor_warning' ) );
		add_action( 'save_post',                    array( $this, 'update_post' ) );
		add_action( 'manage_posts_custom_column',   array( $this, 'display_posts_column_ssl_status' ), 10, 2 );
		add_action( 'manage_pages_custom_column',   array( $this, 'display_posts_column_ssl_status' ), 10, 2 );

		// Register filters.
		// add_filter( 'wp_headers',                   array( $this, 'add_headers' ) );
		add_filter( 'the_content',                  array( $this, 'proxy_insecure_images' ), 999 );
		add_filter( 'manage_posts_columns',         array( $this, 'add_posts_column_ssl_status' ) );
		add_filter( 'manage_pages_columns',         array( $this, 'add_posts_column_ssl_status' ) );
		add_filter( 'set_url_scheme',               array( $this, 'filter_url_scheme' ), 10, 3 );

		register_deactivation_hook( __FILE__, array( $this, 'deactivation_hook' ) );

		// Get saved options from db.
		$saved_options = get_option( 'bu_ssl_settings' );

		// Merge saved options with the default options.
		if ( ! empty( $saved_options ) ) {
			$this->options = array_merge( $this->options, $saved_options );
		}

		// Build the Content-Security-Policy if the enable_csp option is turned on.
		if ( $this->options['enable_csp'] ) {
			self::build_csp();
		}
	}

	/**
	 * Checks if camo is disabled
	 *
	 * @return boolean The value of the BU_SSL_CAMO_DISABLED flag
	 */
	public function is_camo_disabled() {
		return defined( 'BU_SSL_CAMO_DISABLED' ) && BU_SSL_CAMO_DISABLED;
	}

	/**
	 * Builds the content security policy string based on the set options
	 *
	 * @return void
	 */
	public function build_csp() {
		$csp = $this->options['content_security_policy'];

		if ( ! empty( $this->options['csp_report_url'] ) ) {
			$csp .= '; report-uri ';
			$csp .= $this->options['csp_report_url'];
		}

		$this->csp = _wp_specialchars( wp_check_invalid_utf8( $csp ), 'double' );

		if ( ! $this->options['enforce_csp'] ) {
			$this->csp_type .= '-Report-Only';
		}
	}

	/**
	 * Prevents authenticated users from being sent to an insecure connection
	 *
	 * @param string      $url         The complete URL including scheme and path.
	 * @param string      $scheme      Scheme applied to the URL. One of 'http', 'https', or 'relative'.
	 * @param string|null $orig_scheme Scheme requested for the URL. One of 'http', 'https', 'login', 'login_post', 'admin', 'relative', 'rest', 'rpc', or null.
	 * @return string The filtered URL.
	 */
	public function filter_url_scheme( $url, $scheme, $orig_scheme ) {
		if ( $this->options['override_url_scheme'] ) {

			if ( is_user_logged_in() && force_ssl_admin() && 'http' === $scheme ) {
				// Don't send authenticated users to an insecure connection.
				$url = set_url_scheme( $url, 'https' );
			}
		}
		return $url;
	}

	/**
	 * Adds CSP meta tags
	 *
	 * @return void
	 */
	public function add_meta_tags() {
		if ( $this->options['enable_csp'] ) {
			printf(
				'<meta http-equiv="%s" content="%s" />' . "\n",
				esc_html( $this->csp_type ),
				esc_html( $this->csp )
			);
		}
	}
	/**
	 * Adds CSP headers
	 *
	 * @param array $headers The list of headers to be sent.
	 * @return array The filtered headers.
	 */
	public function add_headers( $headers ) {
		if ( $this->options['enable_csp'] ) {
			$headers[ $this->csp_type ] = sprintf( '%s', $this->csp );
		}
		return $headers;
	}

	/**
	 * Redirects to safe connection
	 *
	 * @return void
	 */
	public function do_redirect() {
		if ( $this->options['always_redirect'] && ! is_ssl() ) {
			wp_safe_redirect( site_url( $_SERVER['REQUEST_URI'], 'https' ) );
		}
	}

	/**
	 * Adds SSL check column to posts list
	 *
	 * @param array $columns An array of columns.
	 * @return array The filtered array of columns.
	 */
	public function add_posts_column_ssl_status( $columns ) {
		return array_merge( $columns,
			array(
				'bu-ssl' => __( 'SSL Check', 'bu-ssl' ),
		) );
	}

	/**
	 * Populates the SSL check column in posts list.
	 *
	 * @param string $column The column name.
	 * @param int    $post_id The current post's ID.
	 * @return void
	 */
	public function display_posts_column_ssl_status( $column, $post_id ) {
		if ( 'bu-ssl' === $column ) {
			// Check if the post has insecure content and display appropriate icon.
			echo count( self::has_insecure_content( $post_id ) ) ? '&#10071;' : '&#9989;';
		}
	}

	/**
	 * Removes all postmeta added by this plugin.
	 *
	 * @return bool Whether the post meta key was deleted from the database.
	 */
	public function remove_all_postmeta() {
		return delete_post_meta_by_key( $this->options['post_meta_key'] );
	}

	/**
	 * Performs graceful plugin deactivation.
	 *
	 * @return void
	 */
	public function deactivation_hook() {
		// Remove all post meta on deactivation.
		$this->remove_all_postmeta();
	}

	/**
	 * Searches for insecure content in a post's content
	 *
	 * @param string $content The post content.
	 * @return array The array of insecure URLs found.
	 */
	public function search_for_insecure_content( $content ) {
		// Replace any internal http urls with relative urls.
		$content = str_replace( get_site_url( null, null, 'http' ), get_site_url( null, null, 'relative' ), $content );

		// Load content into a DOMDocument object.
		$dom = new \DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML(
			mb_convert_encoding( '<!DOCTYPE html><html lang="en"><body>' . $content . '</body></html>', 'HTML-ENTITIES', 'UTF-8' )
		);
		libxml_use_internal_errors( false );

		// Declare the array which will hold all of the insecure urls.
		$insecure_urls_per_tag = array();

		// Iterate over the specified tags we are going to search for insecure urls.
		foreach ( $this->tags as $tag ) {

			// Query the content for the specified tag.
			$dom_node_list = $dom->getElementsByTagName( $tag['name'] );

			// Initilize dom_node_list_array which will hold all of the DOMNodeList's we will check for insecure content.
			$dom_node_list_array = array();

			// Get any children's DOMNodeList's from the base DOMNodeList.
			if ( array_key_exists( 'children', $tag ) ) {
				foreach ( $dom_node_list as $parent_element ) {
					$dom_node_list_array[] = $parent_element->getElementsByTagName( $tag['children'] );
				}
			} else {
				// Since there are no children, just add the base DOMNodeList to dom_node_list_array.
				$dom_node_list_array[] = $dom_node_list;
			}

			// Iterate over all DOMNodeList's.
			foreach ( $dom_node_list_array as $dom_node_list ) {
				// Iterate over all elements in DOMNodeList.
				foreach ( $dom_node_list as $element ) {
					// Get the url from the specified attribute.
					$attribute_value = $element->getAttribute( $tag['attribute'] );

					if ( '' !== $attribute_value ) {

						$element_urls = array();
						if ( 'srcset' === $tag['attribute'] ) {
							$element_urls = $this->get_urls_from_srcset_attribute( $attribute_value );
						} else {
							$element_urls = array( $attribute_value );
						}

						foreach ( $element_urls as $url ) {
							$parsed_url = wp_parse_url( $url );

							// If url is valid and the url scheme is http.
							if ( false !== $parsed_url && 'http' === $parsed_url['scheme'] ) {

								// If url is not already in $insecure_urls_per_tag, add it to $insecure_urls_per_tag.
								if ( ! array_key_exists( $tag['name'], $insecure_urls_per_tag ) || ! in_array( $url, $insecure_urls_per_tag[ $tag['name'] ], true ) ) {
									$insecure_urls_per_tag[ $tag['name'] ][] = $url;
								}
							}
						}
					}
				} // End foreach().
			} // End foreach().
		} // End foreach().

		// Return list of insecure urls.
		return $insecure_urls_per_tag;
	}

	/**
	 * Parse urls from html element srcset attribute
	 *
	 * @param string $srcset The element's srcset attribute value.
	 * @return array The urls found in the srcset.
	 */
	public function get_urls_from_srcset_attribute( $srcset ) {
		$srcset_urls = array();

		// Break the srcset value into entries.
		$srcset_entries = explode( ',', $srcset );
		// Iterate over srcset_entries.
		foreach ( $srcset_entries as $srcset_entry ) {
			$trimmed_srcset_entry = trim( $srcset_entry );
			// Break the srcset entry into [ url, width ] and add the possible url to $srcset_urls.
			$srcset_urls[] = explode( ' ', $trimmed_srcset_entry )[0];
		}

		// Return the urls found.
		return $srcset_urls;
	}

	/**
	 * Checks if post has insecure content
	 *
	 * @param string $post_id The post ID.
	 * @return array The array of insecure urls.
	 */
	public function has_insecure_content( $post_id ) {
		// Get base post_meta_key from options.
		$meta_key = $this->options['post_meta_key'];

		// Get post meta for insecure urls if it exists. Else, search for insecure content and update post meta.
		if ( metadata_exists( 'post', $post_id, $meta_key ) ) {
			$urls = get_post_meta( $post_id, $meta_key, true );
		} else {
			// Get the post object from the post id.
			$post = get_post( $post_id );

			// Search for insecure content.
			$urls = self::search_for_insecure_content( $post->post_content, $search_type );

			update_post_meta( $post_id, $meta_key, $urls );
		}

		// Return the insecure urls array.
		return $urls;
	}

	/**
	 * Proxies insecure images
	 *
	 * @param string  $content The post content.
	 * @param boolean $force_ssl Force use of proxied images regardless of is_ssl result.
	 * @return string The filtered content.
	 */
	public function proxy_insecure_images( $content, $force_ssl = false ) {
		global $post;

		// If camo is enabled and the site is using SSL or the force SSL parameter is set to true.
		if ( ! self::is_camo_disabled() && ( is_ssl() || $force_ssl ) ) {
			// Configure the $camo object.
			$camo = new \WillWashburn\Camo\Client();
			$camo->setDomain( BU_SSL_CAMO_DOMAIN );
			$camo->setCamoKey( BU_SSL_CAMO_KEY );

			// Get list of insecure urls.
			$insecure_urls_per_tag = self::has_insecure_content( $post->post_id );
			if ( $insecure_urls_per_tag ) {

				// Get insecure urls from img and picture tags.
				$insecure_imgs     = isset( $insecure_urls_per_tag['img'] ) && is_array( $insecure_urls_per_tag['img'] ) ? $insecure_urls_per_tag['img'] : array();
				$insecure_pictures = isset( $insecure_urls_per_tag['picture'] ) && is_array( $insecure_urls_per_tag['picture'] ) ? $insecure_urls_per_tag['picture'] : array();

				// Merge insecure image urls.
				$insecure_images = array_unique( array_merge( $insecure_imgs, $insecure_pictures ) );

				if ( $insecure_images ) {
					// Create a proxy url and replace the insecure image url with it.
					foreach ( $insecure_images as $insecure_url ) {
						$content = str_replace( $insecure_url, $camo->proxy( $insecure_url ), $content );
					}
				}
			}
		}
		return $content;
	}

	/**
	 * On post update, checks for insecure content.
	 *
	 * @param int $post_id The post ID.
	 * @return array The list of insecure urls. (cedas: Not sure if this is neccesary, we might remove it)
	 */
	public function update_post( $post_id ) {
		// Skip if this is just a revision.
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Get the post object from the post id.
		$post = get_post( $post_id );

		// Get list of insecure urls.
		$urls = self::search_for_insecure_content( $post->post_content );

		// Update post meta with list of insecure urls.
		update_post_meta( $post_id, $this->options['post_meta_key'], $urls );

		return $urls;
	}

	/**
	 * Displays an editor warning if the post has insecure content.
	 *
	 * @return void
	 */
	public function maybe_editor_warning() {
		global $post;
		if ( count( self::has_insecure_content( $post->ID ) ) ) {
			$message = __( '&#x1F513; This post contains content loaded over an insecure connection.' );
			// $message .= __( ' These images will be filtered through a <a href="#">secure image proxy</a>.' );
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html( $message )
			);
		}
	}
}
$bu_ssl = new SSL();
