<?php
namespace BU\WordPress\Plugins;

class BU_SSL_Tests extends \WP_UnitTestCase {

	function test_headers() {
		global $bu_ssl;

		$headers = apply_filters( 'wp_headers', array() );

		$this->assertEquals( $headers['Content-Security-Policy'], 'upgrade-insecure-requests' );
	}
}

