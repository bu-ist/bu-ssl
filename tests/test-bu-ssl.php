<?php
namespace BU\WordPress\Plugins;

class BU_SSL_Tests extends \WP_UnitTestCase {

	public $ssl;

	function setUp(){
		parent::setUp();

		$this->ssl = new \BU\WordPress\Plugins\SSL();
	}

	function test_headers() {
		$_SERVER['HTTPS'] = 'on';
		$headers = apply_filters( 'wp_headers', array() );

		$this->assertEquals( $headers['Content-Security-Policy'], 'upgrade-insecure-requests' );
	}

	function test_insecure_image_seearch(){
		$insecure_image = 'http://media.giphy.com/media/oXWwl0eUy2mNW/giphy.gif';
		$secure_image = 'https://media.giphy.com/media/oXWwl0eUy2mNW/giphy.gif';

		$post_1 = $this->factory->post->create_and_get( array(
			'post_content' => '<img src="' . $insecure_image . '" />',
		) );

		$post_2 = $this->factory->post->create_and_get( array(
			'post_content' => '<img src="' . $secure_image . '" />',
		) );

		// Positive match
		$urls_found_post_1 = $this->ssl->search_for_insecure_images( $post_1->post_content );
		$this->assertContains( $insecure_image, $post_1->post_content );
		$this->assertGreaterThan( 0, count( $urls_found_post_1 ) );

		// Negative match
		$urls_found_post_2 = $this->ssl->search_for_insecure_images( $post_2->post_content );
		$this->assertContains( $secure_image, $post_2->post_content );
		$this->assertEquals( 0, count( $urls_found_post_2 ) );
	} 
}

