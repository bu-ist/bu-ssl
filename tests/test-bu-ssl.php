<?php
namespace BU\WordPress\Plugins;

class BU_SSL_Tests extends \WP_UnitTestCase {

	function setUp() {
		parent::setUp();
	}

	function test_headers() {
		$this->markTestSkipped( 'Skipping CSP header check.' );
		$ssl = new SSL();

		$_SERVER['HTTPS'] = 'on';
		$headers = apply_filters( 'wp_headers', array() );

		// Default to Report-Only
		$this->assertArrayHasKey( 'Content-Security-Policy-Report-Only', $headers );
		$this->assertEquals( $headers['Content-Security-Policy-Report-Only'], $ssl->options['content_security_policy'] );
	}

	function test_insecure_image_seearch() {
		$ssl = new SSL();

		$insecure_image = 'http://media.giphy.com/media/oXWwl0eUy2mNW/giphy.gif';
		$secure_image = 'https://media.giphy.com/media/oXWwl0eUy2mNW/giphy.gif';

		$post_1 = $this->factory->post->create_and_get( array(
			'post_content' => '<img src="' . $insecure_image . '" />',
		) );

		$post_2 = $this->factory->post->create_and_get( array(
			'post_content' => '<img src="' . $secure_image . '" />',
		) );

		// Positive match
		$urls_found_post_1 = $ssl->search_for_insecure_content( $post_1->post_content, 'img' );
		$this->assertContains( $insecure_image, $post_1->post_content );
		$this->assertGreaterThan( 0, count( $urls_found_post_1 ) );

		// Negative match
		$urls_found_post_2 = $ssl->search_for_insecure_content( $post_2->post_content, 'img' );
		$this->assertContains( $secure_image, $post_2->post_content );
		$this->assertEquals( 0, count( $urls_found_post_2 ) );
	}

	function test_insecure_embed_seearch() {
		$ssl = new SSL();

		$insecure_stylesheet = 'http://media.giphy.com/media/oXWwl0eUy2mNW/giphy.css';
		$secure_stylesheet = 'https://media.giphy.com/media/oXWwl0eUy2mNW/giphy.css';

		$post_1 = $this->factory->post->create_and_get( array(
			'post_content' => '<video src="' . $insecure_stylesheet . '"></video',
		) );

		$post_2 = $this->factory->post->create_and_get( array(
			'post_content' => '<video src="' . $secure_stylesheet . '"></video>',
		) );

		// Positive match
		$urls_found_post_1 = $ssl->search_for_insecure_content( $post_1->post_content );
		$this->assertContains( $insecure_stylesheet, $post_1->post_content );
		$this->assertGreaterThan( 0, count( $urls_found_post_1 ) );

		// Negative match
		$urls_found_post_2 = $ssl->search_for_insecure_content( $post_2->post_content );
		$this->assertContains( $secure_stylesheet, $post_2->post_content );
		$this->assertEquals( 0, count( $urls_found_post_2 ) );
	}
}

