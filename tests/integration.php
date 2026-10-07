<?php
/**
 * Functional checks against a real WordPress database, via the isolated .wp-env.tests.json environment.
 * All HTTP is intercepted; no content or key is sent to a search engine.
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 'Run this file through the wp-env test configuration.' );
}

if ( ! defined( 'NM_INDEXNOW_TEST_ENV' ) || ! NM_INDEXNOW_TEST_ENV ) {
	WP_CLI::error( 'Refusing to modify fixtures outside the isolated wp-env test environment.' );
}

if ( ! function_exists( 'nm_indexnow_activate' ) ) {
	WP_CLI::error( 'Activate the plugin in the test environment first.' );
}

$options = array( 'nm_indexnow_api_key', 'nm_indexnow_pregenerated_api_key', 'nm_indexnow_key_location', 'nm_indexnow_last_error' );
$snapshot = array();
$missing = new stdClass();
foreach ( $options as $option ) {
	$snapshot[ $option ] = get_option( $option, $missing );
}
$original_user = get_current_user_id();
$requests = array();
$posts = array();
$terms = array();
$failures = array();
$passed = 0;
$home = 'http://localhost:8889';
$response = array( 'headers' => array(), 'body' => '', 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array() );
$home_filter = static function () use ( &$home ) { return $home; };
$http_filter = static function ( $preempt, $args, $url ) use ( &$requests, &$response ) {
	$requests[] = array( 'url' => $url, 'args' => $args, 'body' => json_decode( $args['body'] ?? '{}', true ) );
	return $response;
};
add_filter( 'pre_option_home', $home_filter );
add_filter( 'pre_http_request', $http_filter, PHP_INT_MAX, 3 );
set_error_handler( static function ( $severity, $message, $file, $line ) {
	if ( error_reporting() & $severity ) {
		throw new ErrorException( $message, 0, $severity, $file, $line );
	}
	return false;
}, E_WARNING | E_NOTICE | E_USER_WARNING | E_USER_NOTICE );

$assert = static function ( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};
$test = static function ( $name, $callback ) use ( &$failures, &$passed ) {
	try {
		$callback();
		++$passed;
		WP_CLI::log( 'PASS: ' . $name );
	} catch ( Throwable $error ) {
		$failures[] = $name . ': ' . $error->getMessage();
		WP_CLI::warning( 'FAIL: ' . end( $failures ) );
	}
};

try {
	$test( 'Activation generates and preserves a valid key', static function () use ( $assert ) {
		delete_option( 'nm_indexnow_api_key' );
		delete_option( 'nm_indexnow_pregenerated_api_key' );
		nm_indexnow_activate();
		$key = get_option( 'nm_indexnow_api_key' );
		$assert( 1 === preg_match( '/^[a-f0-9]{32}$/', $key ), 'Expected a 32-character hex key.' );
		update_option( 'nm_indexnow_api_key', 'custom-key' );
		nm_indexnow_activate();
		$assert( 'custom-key' === get_option( 'nm_indexnow_api_key' ), 'Activation overwrote the user key.' );
	} );
	update_option( 'nm_indexnow_api_key', str_repeat( 'a', 32 ) );
	update_option( 'nm_indexnow_key_location', '' );

	$test( 'Settings register sanitizers and checkbox behavior', static function () use ( $assert ) {
		nm_indexnow_register_settings();
		$settings = get_registered_settings();
		$assert( 'sanitize_text_field' === $settings['nm_indexnow_api_key']['sanitize_callback'], 'Key sanitizer missing.' );
		$assert( 'nm_indexnow_sanitize_checkbox' === $settings['nm_indexnow_key_location']['sanitize_callback'], 'Checkbox sanitizer missing.' );
		$assert( 'abc' === sanitize_option( 'nm_indexnow_api_key', '<b>abc</b>' ), 'Key sanitization failed.' );
		$assert( '1' === nm_indexnow_sanitize_checkbox( 'yes' ) && '' === nm_indexnow_sanitize_checkbox( '' ), 'Checkbox normalization failed.' );
	} );

	foreach ( array( 'http://localhost', 'http://127.0.0.1', 'http://[::1]' ) as $local_home ) {
		$test( 'No submissions on ' . $local_home, static function () use ( $local_home, &$home, &$requests, $assert ) {
			$home = $local_home;
			$assert( nm_indexnow_is_localhost(), 'Local host was not detected.' );
			$requests = array();
			$post = new WP_Post( (object) array( 'ID' => 999999, 'post_status' => 'publish', 'post_type' => 'post' ) );
			nm_indexnow_notify_on_save( $post->ID, $post, false );
			$assert( array() === $requests, 'Localhost triggered HTTP.' );
		} );
	}
	$home = 'http://localhost:8889';
	register_post_type( 'product', array( 'public' => true ) );
	foreach ( array( 'post', 'page', 'product', 'attachment' ) as $type ) {
		$id = wp_insert_post( array( 'post_title' => 'IndexNow test ' . $type, 'post_type' => $type, 'post_status' => 'publish' ), true );
		$assert( ! is_wp_error( $id ), 'Unable to create fixture.' );
		$posts[ $type ] = $id;
	}
	$draft = wp_insert_post( array( 'post_title' => 'IndexNow draft', 'post_status' => 'draft' ) );
	$posts['draft'] = $draft;
	$term = wp_insert_term( 'IndexNow test category ' . wp_generate_uuid4(), 'category' );
	$assert( ! is_wp_error( $term ), 'Unable to create category fixture.' );
	$terms[] = $term['term_id'];
	wp_set_post_terms( $posts['post'], $terms, 'category' );
	$home = 'https://indexnow.example';

	$test( 'URL collection includes permalink and taxonomy archive', static function () use ( $posts, $terms, $assert ) {
		$urls = nm_indexnow_collect_urls_for_post( get_post( $posts['post'] ) );
		$assert( in_array( get_permalink( $posts['post'] ), $urls, true ), 'Missing permalink.' );
		$assert( in_array( get_term_link( $terms[0], 'category' ), $urls, true ), 'Missing taxonomy archive.' );
	} );
	foreach ( array( 'post', 'page', 'product' ) as $type ) {
		$test( 'Published ' . $type . ' sends a valid payload', static function () use ( $type, $posts, &$requests, $assert ) {
			$requests = array();
			nm_indexnow_notify_on_save( $posts[ $type ], get_post( $posts[ $type ] ), true );
			$assert( 1 === count( $requests ), 'Expected one request.' );
			$request = $requests[0];
			$assert( 'indexnow.example' === $request['body']['host'], 'Payload host must match the site hostname.' );
			$assert( str_repeat( 'a', 32 ) === $request['body']['key'], 'Incorrect API key.' );
			$assert( in_array( get_permalink( $posts[ $type ] ), $request['body']['urlList'], true ), 'Missing content URL.' );
			$assert( false === $request['args']['blocking'], 'Default request must be non-blocking.' );
			$assert( ! isset( $request['body']['keyLocation'] ), 'Unchecked key file should omit keyLocation.' );
		} );
	}
	$test( 'Drafts and unsupported post types are skipped', static function () use ( $posts, &$requests, $assert ) {
		$requests = array();
		foreach ( array( 'draft', 'attachment' ) as $type ) {
			nm_indexnow_notify_on_save( $posts[ $type ], get_post( $posts[ $type ] ), true );
		}
		$assert( array() === $requests, 'Skipped content caused a request.' );
	} );
	$test( 'Real publish and update hooks submit content', static function () use ( $posts, &$requests, $assert ) {
		$requests = array();
		wp_update_post( array( 'ID' => $posts['draft'], 'post_status' => 'publish' ) );
		wp_update_post( array( 'ID' => $posts['draft'], 'post_title' => 'IndexNow updated fixture' ) );
		$assert( 2 === count( $requests ), 'Publish/update save_post hooks did not submit once each.' );
	} );
	$test( 'Revision and autosave records are skipped', static function () use ( $posts, &$requests, $assert ) {
		$revision_id = wp_insert_post( array( 'post_type' => 'revision', 'post_status' => 'inherit', 'post_parent' => $posts['post'], 'post_name' => $posts['post'] . '-autosave-v1' ) );
		try {
			$requests = array();
			$revision = get_post( $revision_id );
			// A published status would otherwise qualify: the revision guard must still win.
			$revision->post_status = 'publish';
			nm_indexnow_notify_on_save( $revision_id, $revision, true );
			$assert( array() === $requests, 'A revision or autosave was submitted.' );
		} finally {
			wp_delete_post( $revision_id, true );
		}
	} );
	$test( 'Save veto filter suppresses submissions', static function () use ( $posts, &$requests, $assert ) {
		add_filter( 'nm_indexnow_should_ping_on_save', '__return_false' );
		try {
			$requests = array();
			nm_indexnow_notify_on_save( $posts['post'], get_post( $posts['post'] ), true );
			$assert( array() === $requests, 'Save veto ignored.' );
		} finally {
			remove_filter( 'nm_indexnow_should_ping_on_save', '__return_false' );
		}
	} );
	$test( 'Empty keys and URL lists suppress submissions', static function () use ( &$requests, $assert ) {
		$requests = array();
		update_option( 'nm_indexnow_api_key', '' );
		nm_indexnow_submit_urls( array( 'https://indexnow.example/post/' ) );
		update_option( 'nm_indexnow_api_key', str_repeat( 'a', 32 ) );
		nm_indexnow_submit_urls( array() );
		$assert( array() === $requests, 'Empty key or URLs caused a request.' );
	} );
	$test( 'Key file, URL deduplication, and extension filters work', static function () use ( &$requests, $assert ) {
		update_option( 'nm_indexnow_key_location', '1' );
		$endpoint = static function () { return 'https://indexnow.example/endpoint'; };
		$key_location = static function () { return 'https://indexnow.example/key.txt'; };
		add_filter( 'nm_indexnow_endpoint', $endpoint );
		add_filter( 'nm_indexnow_key_location_url', $key_location );
		try {
			$requests = array();
			nm_indexnow_submit_urls( array( 'https://indexnow.example/post/', '', 'https://indexnow.example/post/' ) );
			$assert( 1 === count( $requests[0]['body']['urlList'] ), 'URLs not deduplicated.' );
			$assert( 'https://indexnow.example/key.txt' === $requests[0]['body']['keyLocation'], 'Key location filter ignored.' );
			$assert( 'https://indexnow.example/endpoint' === $requests[0]['url'], 'Endpoint filter ignored.' );
		} finally {
			update_option( 'nm_indexnow_key_location', '' );
			remove_filter( 'nm_indexnow_endpoint', $endpoint );
			remove_filter( 'nm_indexnow_key_location_url', $key_location );
		}
	} );
	$test( 'Trash action submits and trash veto suppresses HTTP', static function () use ( $posts, &$requests, $assert ) {
		$requests = array();
		wp_trash_post( $posts['page'] );
		$assert( 1 === count( $requests ), 'Trash hook did not submit.' );
		add_filter( 'nm_indexnow_should_ping_on_trashed', '__return_false' );
		try {
			$requests = array();
			nm_indexnow_notify_on_trashed( $posts['post'] );
			$assert( array() === $requests, 'Trash veto ignored.' );
		} finally {
			remove_filter( 'nm_indexnow_should_ping_on_trashed', '__return_false' );
		}
	} );
	$test( 'Before/after actions and request argument filter run', static function () use ( &$requests, $assert ) {
		$events = array();
		$before = static function () use ( &$events ) { $events[] = 'before'; };
		$after = static function () use ( &$events ) { $events[] = 'after'; };
		$args_filter = static function ( $args ) { $args['timeout'] = 10; return $args; };
		add_action( 'nm_indexnow_before_submit', $before );
		add_action( 'nm_indexnow_after_submit', $after );
		add_filter( 'nm_indexnow_request_args', $args_filter );
		try {
			$requests = array();
			nm_indexnow_submit_urls( array( 'https://indexnow.example/post/' ) );
			$assert( array( 'before', 'after' ) === $events, 'Expected before/after actions in order.' );
			$assert( 10 === $requests[0]['args']['timeout'], 'HTTP argument filter ignored.' );
		} finally {
			remove_action( 'nm_indexnow_before_submit', $before );
			remove_action( 'nm_indexnow_after_submit', $after );
			remove_filter( 'nm_indexnow_request_args', $args_filter );
		}
	} );
	$test( 'Transport and HTTP failures invoke the failure action', static function () use ( &$response, $assert ) {
		$failed = 0;
		$callback = static function () use ( &$failed ) { ++$failed; };
		add_action( 'nm_indexnow_submit_failed', $callback );
		try {
			$response = new WP_Error( 'test_error', 'Simulated transport failure' );
			nm_indexnow_submit_urls( array( 'https://indexnow.example/post/' ) );
			$response = array( 'response' => array( 'code' => 403, 'message' => 'Forbidden' ) );
			nm_indexnow_submit_urls( array( 'https://indexnow.example/post/' ) );
			$assert( 2 === $failed, 'Failure action missing.' );
		} finally {
			remove_action( 'nm_indexnow_submit_failed', $callback );
			$response = array( 'response' => array( 'code' => 200, 'message' => 'OK' ) );
		}
	} );
	$test( 'API key field escapes stored HTML', static function () use ( $assert ) {
		// Inject a legacy/externally-written raw value rather than testing the sanitizer again.
		$raw_key = static function () { return '\"><script>alert(1)</script>'; };
		add_filter( 'pre_option_nm_indexnow_api_key', $raw_key );
		try {
			ob_start();
			nm_indexnow_api_key_field_cb();
			$html = ob_get_clean();
			$assert( false === strpos( $html, '<script>' ) && false !== strpos( $html, '&lt;script&gt;' ), 'Unescaped key field.' );
		} finally {
			remove_filter( 'pre_option_nm_indexnow_api_key', $raw_key );
		}
	} );
	$test( 'Error notices require administrator capability and escape output', static function () use ( $assert ) {
		update_option( 'nm_indexnow_last_error', array( 'message' => '<script>alert(1)</script>', 'time' => time() ) );
		wp_set_current_user( 0 );
		ob_start();
		nm_indexnow_admin_notices();
		$assert( '' === ob_get_clean(), 'Unauthenticated user saw the notice.' );
		$assert( false !== get_option( 'nm_indexnow_last_error' ), 'Unauthorized request consumed the notice.' );
		$admins = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
		wp_set_current_user( $admins[0]->ID );
		ob_start();
		nm_indexnow_admin_notices();
		$html = ob_get_clean();
		$assert( false === strpos( $html, '<script>' ) && false !== strpos( $html, '&lt;script&gt;' ), 'Notice output not escaped.' );
		$assert( false === get_option( 'nm_indexnow_last_error' ), 'Notice was not consumed.' );
	} );
} finally {
	// Suppress submission hooks during fixture cleanup and restore original state.
	$home = 'http://localhost:8889';
	foreach ( $posts as $id ) {
		wp_delete_post( $id, true );
	}
	foreach ( $terms as $id ) {
		wp_delete_term( $id, 'category' );
	}
	unregister_post_type( 'product' );
	foreach ( $snapshot as $option => $value ) {
		if ( $value === $missing ) {
			delete_option( $option );
		} else {
			update_option( $option, $value );
		}
	}
	wp_set_current_user( $original_user );
	remove_filter( 'pre_option_home', $home_filter );
	remove_filter( 'pre_http_request', $http_filter, PHP_INT_MAX );
	restore_error_handler();
}
WP_CLI::log( sprintf( '%d checks passed; %d failed.', $passed, count( $failures ) ) );
if ( $failures ) {
	WP_CLI::error( implode( "\n", $failures ) );
}
WP_CLI::success( 'Functional checks passed without outbound HTTP.' );
