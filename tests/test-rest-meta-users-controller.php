<?php

/**
 * Unit tests covering WP_REST_Users meta functionality.
 *
 * @package WordPress
 * @subpackage JSON API
 */
class WP_Test_REST_Meta_Users_Controller extends WP_Test_REST_Controller_Testcase {
	public function setUp() {
		parent::setUp();

		$this->user = $this->factory->user->create( array(
			'role' => 'administrator',
		) );
	}

	public function test_register_routes() {
		$routes = $this->server->get_routes();

		$this->assertArrayHasKey( '/wp/v2/users/(?P<parent_id>[\d]+)/meta', $routes );
		$this->assertCount( 2, $routes['/wp/v2/users/(?P<parent_id>[\d]+)/meta'] );
		$this->assertArrayHasKey( '/wp/v2/users/(?P<parent_id>[\d]+)/meta/(?P<id>[\d]+)', $routes );
		$this->assertCount( 3, $routes['/wp/v2/users/(?P<parent_id>[\d]+)/meta/(?P<id>[\d]+)'] );
	}

	public function test_context_param() {
		$this->user = $this->factory->user->create();
		// Collection
		$request = new WP_REST_Request( 'OPTIONS', '/wp/v2/users/' . $this->user . '/meta' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertEquals( 'edit', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertEquals( array( 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
		// Single
		$meta_id_basic = add_user_meta( $this->user, 'testkey', 'testvalue' );
		$request = new WP_REST_Request( 'OPTIONS', '/wp/v2/users/' . $this->user . '/meta/' . $meta_id_basic );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertEquals( 'edit', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertEquals( array( 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
	}

	public function test_get_item() {
		wp_set_current_user( $this->user );
		$this->allow_user_to_manage_multisite();

		$meta_id = add_user_meta( $this->user, 'testkey', 'testvalue' );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/users/%d/meta/%d', $this->user, $meta_id ) );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertCount( 3, $data );

		if ( $data['id'] === $meta_id ) {
			$this->assertEquals( 'testkey', $data['key'] );
			$this->assertEquals( 'testvalue', $data['value'] );
		} else {
			$this->fail();
		}
	}

	public function test_get_items() {
		wp_set_current_user( $this->user );
		$this->allow_user_to_manage_multisite();

		$meta_id_serialized         = add_user_meta( $this->user, 'testkey_serialized', array( 'testvalue1', 'testvalue2' ) );
		$meta_id_serialized_object  = add_user_meta( $this->user, 'testkey_serialized_object', (object) array( 'testvalue' => 'test' ) );
		$meta_id_serialized_array   = add_user_meta( $this->user, 'testkey_serialized_array', serialize( array( 'testkey1' => 'testvalue1', 'testkey2' => 'testvalue2' ) ) );
		$meta_id_protected          = add_user_meta( $this->user, '_testkey', 'testvalue' );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/users/%d/meta', $this->user ) );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();

		foreach ( $data as $row ) {
			$row = (array) $row;
			$this->assertArrayHasKey( 'id', $row );
			$this->assertArrayHasKey( 'key', $row );
			$this->assertArrayHasKey( 'value', $row );

			if ( $row['id'] === $meta_id_serialized ) {
				$this->assertEquals( 'testkey_serialized', $row['key'] );
				$this->assertEquals( array( 'testvalue1', 'testvalue2' ), $row['value'] );
			}

			if ( $row['id'] === $meta_id_serialized_object ) {
				$this->assertEquals( 'testkey_serialized_object', $row['key'] );
				$this->assertEquals( (object) array( 'testvalue' => 'test' ), $row['value'] );
			}

			if ( $row['id'] === $meta_id_serialized_array ) {
				$this->assertEquals( 'testkey_serialized_array', $row['key'] );
				$this->assertEquals( serialize( array( 'testkey1' => 'testvalue1', 'testkey2' => 'testvalue2' ) ), $row['value'] );
			}
		}
	}

	public function test_get_item_no_user_id() {
		wp_set_current_user( $this->user );
		$this->allow_user_to_manage_multisite();
		$meta_id = add_user_meta( $this->user, 'testkey', 'testvalue' );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/users/%d/meta/%d', $this->user, $meta_id ) );
		$request['parent_id'] = 0;

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_user_invalid_id', $response, 404 );
	}

	public function test_get_item_invalid_user_id() {
		wp_set_current_user( $this->user );
		$this->allow_user_to_manage_multisite();
		$meta_id = add_user_meta( $this->user, 'testkey', 'testvalue' );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/users/%d/meta/%d', $this->user, $meta_id ) );
		$request['parent_id'] = -1;

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_user_invalid_id', $response, 404 );
	}

	public function test_get_item_no_meta_id() {
		wp_set_current_user( $this->user );
		$this->allow_user_to_manage_multisite();
		$meta_id = add_user_meta( $this->user, 'testkey', 'testvalue' );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/users/%d/meta/%d', 0, $meta_id ) );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_user_invalid_id', $response, 404 );
	}

	public function test_get_item_invalid_meta_id() {
		wp_set_current_user( $this->user );
		$this->allow_user_to_manage_multisite();
		$meta_id = add_user_meta( $this->user, 'testkey', 'testvalue' );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/users/%d/meta/%d', $this->user, $meta_id ) );
		$request['id'] = 'a';

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_invalid_id', $response, 404 );
	}

	public function test_get_item_protected() {
		wp_set_current_user( $this->user );
		$this->allow_user_to_manage_multisite();
		$meta_id = add_user_meta( $this->user, '_testkey', 'testvalue' );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/users/%d/meta/%d', $this->user, $meta_id ) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_protected', $response, 403 );
	}

	public function test_get_item_serialized_array() {
		wp_set_current_user( $this->user );
		$this->allow_user_to_manage_multisite();
		$meta_id = add_user_meta( $this->user, 'testkey', array( 'testvalue' => 'test' ) );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/users/%d/meta/%d', $this->user, $meta_id ) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_protected', $response, 403 );
	}

	public function test_get_item_serialized_object() {
		wp_set_current_user( $this->user );
		$this->allow_user_to_manage_multisite();
		$meta_id = add_user_meta( $this->user, 'testkey', (object) array( 'testvalue' => 'test' ) );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/users/%d/meta/%d', $this->user, $meta_id ) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_protected', $response, 403 );
	}

	public function test_get_item_unauthenticated() {
		wp_set_current_user( $this->user );
		$this->allow_user_to_manage_multisite();
		$meta_id = add_user_meta( $this->user, 'testkey', 'testvalue' );

		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/users/%d/meta/%d', $this->user, $meta_id ) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_forbidden', $response, 401 );
	}

	public function test_get_item_wrong_user() {
		wp_set_current_user( $this->user );
		$this->allow_user_to_manage_multisite();
		$meta_id = add_user_meta( $this->user, 'testkey', 'testvalue' );

		$this->user_two = $this->factory->user->create();
		$meta_id_two = add_user_meta( $this->user_two, 'testkey', 'testvalue' );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/users/%d/meta/%d', $this->user_two, $meta_id ) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_user_mismatch', $response, 400 );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/users/%d/meta/%d', $this->user, $meta_id_two ) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_user_mismatch', $response, 400 );
	}

	public function test_get_items_no_user_id() {
		wp_set_current_user( $this->user );
		$this->allow_user_to_manage_multisite();
		add_user_meta( $this->user, 'testkey', 'testvalue' );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/users/%d/meta', $this->user ) );
		$request['parent_id'] = 0;
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_user_invalid_id', $response );
	}

	public function test_get_items_invalid_user_id() {
		wp_set_current_user( $this->user );
		$this->allow_user_to_manage_multisite();
		add_user_meta( $this->user, 'testkey', 'testvalue' );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/users/%d/meta', $this->user ) );
		$request['parent_id'] = -1;
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_user_invalid_id', $response );
	}

	public function test_get_items_unauthenticated() {
		wp_set_current_user( $this->user );
		$this->allow_user_to_manage_multisite();
		add_user_meta( $this->user, 'testkey', 'testvalue' );

		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/users/%d/meta', $this->user ) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_forbidden', $response );
	}

	public function test_create_item() {
		wp_set_current_user( $this->user );
		$this->allow_user_to_manage_multisite();

		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/users/%d/meta', $this->user ) );
		$request->set_body_params( array(
			'key'   => 'testkey',
			'value' => 'testvalue',
		) );
		$response = $this->server->dispatch( $request );

		$meta = get_user_meta( $this->user, 'testkey', false );
		$this->assertNotEmpty( $meta );
		$this->assertCount( 1, $meta );
		$this->assertEquals( 'testvalue', $meta[0] );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'id', $data );
		$this->assertEquals( 'testkey', $data['key'] );
		$this->assertEquals( 'testvalue', $data['value'] );
	}

	public function test_create_item_no_user_id() {
		wp_set_current_user( $this->user );
		$this->allow_user_to_manage_multisite();
		$data = array(
			'key' => 'testkey',
			'value' => 'testvalue',
		);

		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/users/%d/meta', $this->user ) );
		$request->set_body_params( $data );

		$request['parent_id'] = 0;

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_user_invalid_id', $response, 404 );
	}

	public function test_create_item_invalid_user_id() {
		wp_set_current_user( $this->user );
		$this->allow_user_to_manage_multisite();
		$data = array(
			'key' => 'testkey',
			'value' => 'testvalue',
		);

		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/users/%d/meta', $this->user ) );
		$request->set_body_params( $data );

		$request['parent_id'] = -1;

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_user_invalid_id', $response, 404 );
	}

	public function test_create_item_no_value() {
		wp_set_current_user( $this->user );
		$this->allow_user_to_manage_multisite();
		$data = array(
			'key' => 'testkey',
		);
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/users/%d/meta', $this->user ) );
		$request->set_body_params( $data );

		$response = $this->server->dispatch( $request );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'id', $data );
		$this->assertEquals( 'testkey', $data['key'] );
		$this->assertEquals( '', $data['value'] );
	}

	public function test_create_item_no_key() {
		wp_set_current_user( $this->user );
		$this->allow_user_to_manage_multisite();
		$data = array(
			'value' => 'testvalue',
		);
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/users/%d/meta', $this->user ) );
		$request->set_body_params( $data );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_missing_callback_param', $response, 400 );
	}

	public function test_create_item_empty_string_key() {
		wp_set_current_user( $this->user );
		$this->allow_user_to_manage_multisite();
		$data = array(
			'key' => '',
			'value' => 'testvalue',
		);
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/users/%d/meta', $this->user ) );
		$request->set_body_params( $data );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_invalid_key', $response, 400 );
	}

	public function test_create_item_invalid_key() {
		wp_set_current_user( $this->user );
		$this->allow_user_to_manage_multisite();
		$data = array(
			'key' => false,
			'value' => 'testvalue',
		);
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/users/%d/meta', $this->user ) );
		$request->set_body_params( $data );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_invalid_key', $response, 400 );
	}

	public function test_create_item_unauthenticated() {
		wp_set_current_user( $this->user );
		$this->allow_user_to_manage_multisite();
		$data = array(
			'key' => 'testkey',
			'value' => 'testvalue',
		);

		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/users/%d/meta', $this->user ) );
		$request->set_body_params( $data );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_forbidden', $response, 401 );
		$this->assertEmpty( get_user_meta( $this->user, 'testkey' ) );
	}

	public function test_create_item_serialized_array() {
		wp_set_current_user( $this->user );
		$this->allow_user_to_manage_multisite();
		$data = array(
			'key' => 'testkey',
			'value' => array( 'testvalue1', 'testvalue2' ),
		);

		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/users/%d/meta', $this->user ) );
		$request->set_body_params( $data );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
		$this->assertEmpty( get_user_meta( $this->user, 'testkey' ) );
	}

	public function test_create_item_serialized_object() {
		wp_set_current_user( $this->user );
		$this->allow_user_to_manage_multisite();
		$data = array(
			'key' => 'testkey',
			'value' => (object) array( 'testkey1' => 'testvalue1', 'testkey2' => 'testvalue2' ),
		);

		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/users/%d/meta', $this->user ) );
		$request->set_body_params( $data );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
		$this->assertEmpty( get_user_meta( $this->user, 'testkey' ) );
	}

	public function test_create_item_serialized_string() {
		wp_set_current_user( $this->user );
		$this->allow_user_to_manage_multisite();
		$data = array(
			'key' => 'testkey',
			'value' => serialize( array( 'testkey1' => 'testvalue1', 'testkey2' => 'testvalue2' ) ),
		);

		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/users/%d/meta', $this->user ) );
		$request->set_body_params( $data );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_invalid_action', $response, 400 );
		$this->assertEmpty( get_user_meta( $this->user, 'testkey' ) );
	}

	public function test_create_item_failed_get() {
		$this->markTestSkipped();

		$this->endpoint = $this->getMock( 'WP_REST_Meta_Posts', array( 'get_meta' ), array( $this->fake_server ) );

		$test_error = new WP_Error( 'rest_test_error', 'Test error' );
		$this->endpoint->expects( $this->any() )->method( 'get_meta' )->will( $this->returnValue( $test_error ) );

		wp_set_current_user( $this->user );
		$this->allow_user_to_manage_multisite();
		$data = array(
			'key' => 'testkey',
			'value' => 'testvalue',
		);

		$response = $this->endpoint->add_meta( $this->user, $data );
		$this->assertErrorResponse( 'rest_test_error', $response );
	}

	public function test_create_item_protected() {
		wp_set_current_user( $this->user );
		$this->allow_user_to_manage_multisite();
		$data = array(
			'key' => '_testkey',
			'value' => 'testvalue',
		);

		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/users/%d/meta', $this->user ) );
		$request->set_body_params( $data );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_protected', $response, 403 );
		$this->assertEmpty( get_user_meta( $this->user, '_testkey' ) );
	}

	/**
	 * Ensure slashes aren't added
	 */
	public function test_create_item_unslashed() {
		wp_set_current_user( $this->user );
		$this->allow_user_to_manage_multisite();
		$data = array(
			'key' => 'testkey',
			'value' => "test unslashed ' value",
		);
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/users/%d/meta', $this->user ) );
		$request->set_body_params( $data );

		$this->server->dispatch( $request );

		$meta = get_user_meta( $this->user, 'testkey', false );
		$this->assertNotEmpty( $meta );
		$this->assertCount( 1, $meta );
		$this->assertEquals( "test unslashed ' value", $meta[0] );
	}

	/**
	 * Ensure slashes aren't touched in data
	 */
	public function test_create_item_slashed() {
		wp_set_current_user( $this->user );
		$this->allow_user_to_manage_multisite();
		$data = array(
			'key' => 'testkey',
			'value' => "test slashed \\' value",
		);
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/users/%d/meta', $this->user ) );
		$request->set_body_params( $data );

		$this->server->dispatch( $request );

		$meta = get_user_meta( $this->user, 'testkey', false );
		$this->assertNotEmpty( $meta );
		$this->assertCount( 1, $meta );
		$this->assertEquals( "test slashed \\' value", $meta[0] );
	}

	public function test_update_item() {
		wp_set_current_user( $this->user );
		$this->allow_user_to_manage_multisite();
		$meta_id = add_user_meta( $this->user, 'testkey', 'testvalue' );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/users/%d/meta/%d', $this->user, $meta_id ) );
		$request->set_body_params( array(
			'value' => 'testnewvalue',
		) );

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertEquals( $meta_id, $data['id'] );
		$this->assertEquals( 'testkey', $data['key'] );
		$this->assertEquals( 'testnewvalue', $data['value'] );

		$meta = get_user_meta( $this->user, 'testkey', false );
		$this->assertNotEmpty( $meta );
		$this->assertCount( 1, $meta );
		$this->assertEquals( 'testnewvalue', $meta[0] );
	}

	public function test_update_meta_key() {
		wp_set_current_user( $this->user );
		$this->allow_user_to_manage_multisite();
		$meta_id = add_user_meta( $this->user, 'testkey', 'testvalue' );

		$data = array(
			'key' => 'testnewkey',
		);
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/users/%d/meta/%d', $this->user, $meta_id ) );
		$request->set_body_params( $data );

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertEquals( $meta_id, $data['id'] );
		$this->assertEquals( 'testnewkey', $data['key'] );
		$this->assertEquals( 'testvalue', $data['value'] );

		$meta = get_user_meta( $this->user, 'testnewkey', false );
		$this->assertNotEmpty( $meta );
		$this->assertCount( 1, $meta );
		$this->assertEquals( 'testvalue', $meta[0] );

		// Ensure it was actually renamed, not created
		$meta = get_user_meta( $this->user, 'testkey', false );
		$this->assertEmpty( $meta );
	}

	public function test_update_meta_key_and_value() {
		wp_set_current_user( $this->user );
		$this->allow_user_to_manage_multisite();
		$meta_id = add_user_meta( $this->user, 'testkey', 'testvalue' );

		$data = array(
			'key' => 'testnewkey',
			'value' => 'testnewvalue',
		);
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/users/%d/meta/%d', $this->user, $meta_id ) );
		$request->set_body_params( $data );

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertEquals( $meta_id, $data['id'] );
		$this->assertEquals( 'testnewkey', $data['key'] );
		$this->assertEquals( 'testnewvalue', $data['value'] );

		$meta = get_user_meta( $this->user, 'testnewkey', false );
		$this->assertNotEmpty( $meta );
		$this->assertCount( 1, $meta );
		$this->assertEquals( 'testnewvalue', $meta[0] );

		// Ensure it was actually renamed, not created
		$meta = get_user_meta( $this->user, 'testkey', false );
		$this->assertEmpty( $meta );
	}

	public function test_update_meta_empty() {
		wp_set_current_user( $this->user );
		$this->allow_user_to_manage_multisite();
		$meta_id = add_user_meta( $this->user, 'testkey', 'testvalue' );

		$data = array();
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/users/%d/meta/%d', $this->user, $meta_id ) );
		$request->set_body_params( $data );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_data_invalid', $response, 400 );
	}

	public function test_update_meta_no_user_id() {
		wp_set_current_user( $this->user );
		$this->allow_user_to_manage_multisite();
		$meta_id = add_user_meta( $this->user, 'testkey', 'testvalue' );

		$data = array(
			'key' => 'testnewkey',
			'value' => 'testnewvalue',
		);
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/users/%d/meta/%d', 0, $meta_id ) );
		$request->set_body_params( $data );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_user_invalid_id', $response, 404 );
	}

	public function test_update_meta_invalid_user_id() {
		wp_set_current_user( $this->user );
		$this->allow_user_to_manage_multisite();
		$meta_id = add_user_meta( $this->user, 'testkey', 'testvalue' );

		$data = array(
			'key' => 'testnewkey',
			'value' => 'testnewvalue',
		);
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/users/%d/meta/%d', REST_TESTS_IMPOSSIBLY_HIGH_NUMBER, $meta_id ) );
		$request->set_body_params( $data );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_user_invalid_id', $response, 404 );
	}

	public function test_update_meta_no_meta_id() {
		wp_set_current_user( $this->user );
		$this->allow_user_to_manage_multisite();
		add_user_meta( $this->user, 'testkey', 'testvalue' );

		$data = array(
			'key' => 'testnewkey',
			'value' => 'testnewvalue',
		);
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/users/%d/meta/%d', $this->user, 0 ) );
		$request->set_body_params( $data );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_invalid_id', $response, 404 );
		$this->assertEquals( array( 'testvalue' ), get_user_meta( $this->user, 'testkey' ) );
	}

	public function test_update_meta_invalid_meta_id() {
		wp_set_current_user( $this->user );
		$this->allow_user_to_manage_multisite();
		$meta_id = add_user_meta( $this->user, 'testkey', 'testvalue' );

		$data = array(
			'key' => 'testnewkey',
			'value' => 'testnewvalue',
		);
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/users/%d/meta/%d', $this->user, $meta_id ) );
		$request['id'] = 'a';
		$request->set_body_params( $data );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_invalid_id', $response, 404 );
		$this->assertEquals( array( 'testvalue' ), get_user_meta( $this->user, 'testkey' ) );
	}

	public function test_update_meta_unauthenticated() {
		wp_set_current_user( $this->user );
		$this->allow_user_to_manage_multisite();
		$meta_id = add_user_meta( $this->user, 'testkey', 'testvalue' );

		wp_set_current_user( 0 );

		$data = array(
			'key' => 'testnewkey',
			'value' => 'testnewvalue',
		);
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/users/%d/meta/%d', $this->user, $meta_id ) );
		$request->set_body_params( $data );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_forbidden', $response, 401 );
		$this->assertEquals( array( 'testvalue' ), get_user_meta( $this->user, 'testkey' ) );
	}

	public function test_update_meta_wrong_user() {
		wp_set_current_user( $this->user );
		$this->allow_user_to_manage_multisite();
		$meta_id = add_user_meta( $this->user, 'testkey', 'testvalue' );

		$this->user_two = $this->factory->user->create();
		$meta_id_two = add_user_meta( $this->user_two, 'testkey', 'testvalue' );

		$data = array(
			'key' => 'testnewkey',
			'value' => 'testnewvalue',
		);
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/users/%d/meta/%d', $this->user_two, $meta_id ) );
		$request->set_body_params( $data );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_user_mismatch', $response, 400 );
		$this->assertEquals( array( 'testvalue' ), get_user_meta( $this->user_two, 'testkey' ) );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/users/%d/meta/%d', $this->user, $meta_id_two ) );
		$request->set_body_params( $data );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_user_mismatch', $response, 400 );
		$this->assertEquals( array( 'testvalue' ), get_user_meta( $this->user, 'testkey' ) );
	}

	public function test_update_meta_serialized_array() {
		wp_set_current_user( $this->user );
		$this->allow_user_to_manage_multisite();
		$meta_id = add_user_meta( $this->user, 'testkey', 'testvalue' );

		$data = array(
			'value' => array( 'testvalue1', 'testvalue2' ),
		);
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/users/%d/meta/%d', $this->user, $meta_id ) );
		$request->set_body_params( $data );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
		$this->assertEquals( array( 'testvalue' ), get_user_meta( $this->user, 'testkey' ) );
	}

	public function test_update_meta_serialized_object() {
		wp_set_current_user( $this->user );
		$this->allow_user_to_manage_multisite();
		$meta_id = add_user_meta( $this->user, 'testkey', 'testvalue' );

		$data = array(
			'value' => (object) array( 'testkey1' => 'testvalue1', 'testkey2' => 'testvalue2' ),
		);
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/users/%d/meta/%d', $this->user, $meta_id ) );
		$request->set_body_params( $data );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
		$this->assertEquals( array( 'testvalue' ), get_user_meta( $this->user, 'testkey' ) );
	}

	public function test_update_meta_serialized_string() {
		wp_set_current_user( $this->user );
		$this->allow_user_to_manage_multisite();
		$meta_id = add_user_meta( $this->user, 'testkey', 'testvalue' );

		$data = array(
			'value' => serialize( array( 'testkey1' => 'testvalue1', 'testkey2' => 'testvalue2' ) ),
		);
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/users/%d/meta/%d', $this->user, $meta_id ) );
		$request->set_body_params( $data );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_invalid_action', $response, 400 );
		$this->assertEquals( array( 'testvalue' ), get_user_meta( $this->user, 'testkey' ) );
	}

	public function test_update_meta_existing_serialized() {
		wp_set_current_user( $this->user );
		$this->allow_user_to_manage_multisite();
		$meta_id = add_user_meta( $this->user, 'testkey', array( 'testvalue1', 'testvalue2' ) );

		$data = array(
			'value' => 'testnewvalue',
		);
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/users/%d/meta/%d', $this->user, $meta_id ) );
		$request->set_body_params( $data );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_invalid_action', $response, 400 );
		$this->assertEquals( array( array( 'testvalue1', 'testvalue2' ) ), get_user_meta( $this->user, 'testkey' ) );
	}

	public function test_update_meta_protected() {
		wp_set_current_user( $this->user );
		$this->allow_user_to_manage_multisite();
		$meta_id = add_user_meta( $this->user, '_testkey', 'testvalue' );

		$data = array(
			'value' => 'testnewvalue',
		);
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/users/%d/meta/%d', $this->user, $meta_id ) );
		$request->set_body_params( $data );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_protected', $response, 403 );
		$this->assertEquals( array( 'testvalue' ), get_user_meta( $this->user, '_testkey' ) );
	}

	public function test_update_meta_protected_new() {
		wp_set_current_user( $this->user );
		$this->allow_user_to_manage_multisite();
		$meta_id = add_user_meta( $this->user, 'testkey', 'testvalue' );

		$data = array(
			'key' => '_testnewkey',
			'value' => 'testnewvalue',
		);
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/users/%d/meta/%d', $this->user, $meta_id ) );
		$request->set_body_params( $data );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_protected', $response, 403 );
		$this->assertEquals( array( 'testvalue' ), get_user_meta( $this->user, 'testkey' ) );
		$this->assertEmpty( get_user_meta( $this->user, '_testnewkey' ) );
	}

	public function test_update_meta_invalid_key() {
		wp_set_current_user( $this->user );
		$this->allow_user_to_manage_multisite();
		$meta_id = add_user_meta( $this->user, 'testkey', 'testvalue' );

		$data = array(
			'key' => false,
			'value' => 'testnewvalue',
		);
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/users/%d/meta/%d', $this->user, $meta_id ) );
		$request->set_body_params( $data );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_invalid_key', $response, 400 );
		$this->assertEquals( array( 'testvalue' ), get_user_meta( $this->user, 'testkey' ) );
	}

	/**
	 * Ensure slashes aren't added
	 */
	public function test_update_meta_unslashed() {
		wp_set_current_user( $this->user );
		$this->allow_user_to_manage_multisite();
		$meta_id = add_user_meta( $this->user, 'testkey', 'testvalue' );

		$data = array(
			'key' => 'testkey',
			'value' => "test unslashed ' value",
		);
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/users/%d/meta/%d', $this->user, $meta_id ) );
		$request->set_body_params( $data );

		$this->server->dispatch( $request );

		$meta = get_user_meta( $this->user, 'testkey', false );
		$this->assertNotEmpty( $meta );
		$this->assertCount( 1, $meta );
		$this->assertEquals( "test unslashed ' value", $meta[0] );
	}

	/**
	 * Ensure slashes aren't touched in data
	 */
	public function test_update_meta_slashed() {
		wp_set_current_user( $this->user );
		$this->allow_user_to_manage_multisite();
		$meta_id = add_user_meta( $this->user, 'testkey', 'testvalue' );

		$data = array(
			'key' => 'testkey',
			'value' => "test slashed \\' value",
		);
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/users/%d/meta/%d', $this->user, $meta_id ) );
		$request->set_body_params( $data );

		$this->server->dispatch( $request );

		$meta = get_user_meta( $this->user, 'testkey', false );
		$this->assertNotEmpty( $meta );
		$this->assertCount( 1, $meta );
		$this->assertEquals( "test slashed \\' value", $meta[0] );
	}

	public function test_delete_item() {
		wp_set_current_user( $this->user );
		$this->allow_user_to_manage_multisite();
		$meta_id = add_user_meta( $this->user, 'testkey', 'testvalue' );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/users/%d/meta/%d', $this->user, $meta_id ) );
		$request['force'] = true;
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'message', $data );
		$this->assertNotEmpty( $data['message'] );

		$meta = get_user_meta( $this->user, 'testkey', false );
		$this->assertEmpty( $meta );
	}

	public function test_delete_item_no_trash() {
		wp_set_current_user( $this->user );
		$this->allow_user_to_manage_multisite();
		$meta_id = add_user_meta( $this->user, 'testkey', 'testvalue' );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/users/%d/meta/%d', $this->user, $meta_id ) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_trash_not_supported', $response, 501 );

		// Ensure the meta still exists
		$meta = get_metadata_by_mid( 'user', $meta_id );
		$this->assertNotEmpty( $meta );
	}

	public function test_delete_item_no_user_id() {
		wp_set_current_user( $this->user );
		$this->allow_user_to_manage_multisite();
		$meta_id = add_user_meta( $this->user, 'testkey', 'testvalue' );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/users/%d/meta/%d', $this->user, $meta_id ) );
		$request['force'] = true;
		$request['parent_id'] = 0;

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_user_invalid_id', $response, 404 );

		$this->assertEquals( array( 'testvalue' ), get_user_meta( $this->user, 'testkey', false ) );
	}

	public function test_delete_item_invalid_user_id() {
		wp_set_current_user( $this->user );
		$this->allow_user_to_manage_multisite();
		$meta_id = add_user_meta( $this->user, 'testkey', 'testvalue' );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/users/%d/meta/%d', $this->user, $meta_id ) );
		$request['force'] = true;
		$request['parent_id'] = -1;

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_user_invalid_id', $response, 404 );

		$this->assertEquals( array( 'testvalue' ), get_user_meta( $this->user, 'testkey', false ) );
	}

	public function test_delete_item_no_meta_id() {
		wp_set_current_user( $this->user );
		$this->allow_user_to_manage_multisite();
		$meta_id = add_user_meta( $this->user, 'testkey', 'testvalue' );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/users/%d/meta/%d', $this->user, $meta_id ) );
		$request['force'] = true;
		$request['id'] = 0;

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_invalid_id', $response, 404 );

		$this->assertEquals( array( 'testvalue' ), get_user_meta( $this->user, 'testkey', false ) );
	}

	public function test_delete_item_invalid_meta_id() {
		wp_set_current_user( $this->user );
		$this->allow_user_to_manage_multisite();
		$meta_id = add_user_meta( $this->user, 'testkey', 'testvalue' );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/users/%d/meta/%d', $this->user, $meta_id ) );
		$request['force'] = true;
		$request['id'] = 'a';

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_invalid_id', $response, 404 );

		$this->assertEquals( array( 'testvalue' ), get_user_meta( $this->user, 'testkey', false ) );
	}

	public function test_delete_item_unauthenticated() {
		wp_set_current_user( $this->user );
		$this->allow_user_to_manage_multisite();
		$meta_id = add_user_meta( $this->user, 'testkey', 'testvalue' );

		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/users/%d/meta/%d', $this->user, $meta_id ) );
		$request['force'] = true;

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_forbidden', $response, 401 );

		$this->assertEquals( array( 'testvalue' ), get_user_meta( $this->user, 'testkey', false ) );
	}

	public function test_delete_item_wrong_user() {
		wp_set_current_user( $this->user );
		$this->allow_user_to_manage_multisite();
		$meta_id = add_user_meta( $this->user, 'testkey', 'testvalue' );

		$this->user_two = $this->factory->user->create();
		$meta_id_two = add_user_meta( $this->user_two, 'testkey', 'testvalue' );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/users/%d/meta/%d', $this->user_two, $meta_id ) );
		$request['force'] = true;

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_user_mismatch', $response, 400 );
		$this->assertEquals( array( 'testvalue' ), get_user_meta( $this->user_two, 'testkey' ) );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/users/%d/meta/%d', $this->user, $meta_id_two ) );
		$request['force'] = true;

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_user_mismatch', $response, 400 );
		$this->assertEquals( array( 'testvalue' ), get_user_meta( $this->user, 'testkey' ) );
	}

	public function test_delete_item_serialized_array() {
		wp_set_current_user( $this->user );
		$this->allow_user_to_manage_multisite();
		$value = array( 'testvalue1', 'testvalue2' );
		$meta_id = add_user_meta( $this->user, 'testkey', $value );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/users/%d/meta/%d', $this->user, $meta_id ) );
		$request['force'] = true;

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_invalid_action', $response, 400 );
		$this->assertEquals( array( $value ), get_user_meta( $this->user, 'testkey' ) );
	}

	public function test_delete_item_serialized_object() {
		wp_set_current_user( $this->user );
		$this->allow_user_to_manage_multisite();
		$value = (object) array( 'testkey1' => 'testvalue1', 'testkey2' => 'testvalue2' );
		$meta_id = add_user_meta( $this->user, 'testkey', $value );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/users/%d/meta/%d', $this->user, $meta_id ) );
		$request['force'] = true;

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_invalid_action', $response, 400 );
		$this->assertEquals( array( $value ), get_user_meta( $this->user, 'testkey' ) );
	}

	public function test_delete_item_serialized_string() {
		wp_set_current_user( $this->user );
		$this->allow_user_to_manage_multisite();
		$value = serialize( array( 'testkey1' => 'testvalue1', 'testkey2' => 'testvalue2' ) );
		$meta_id = add_user_meta( $this->user, 'testkey', $value );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/users/%d/meta/%d', $this->user, $meta_id ) );
		$request['force'] = true;

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_invalid_action', $response, 400 );
		$this->assertEquals( array( $value ), get_user_meta( $this->user, 'testkey' ) );
	}

	public function test_delete_item_protected() {
		wp_set_current_user( $this->user );
		$this->allow_user_to_manage_multisite();
		$meta_id = add_user_meta( $this->user, '_testkey', 'testvalue' );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/users/%d/meta/%d', $this->user, $meta_id ) );
		$request['force'] = true;

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_protected', $response, 403 );
		$this->assertEquals( array( 'testvalue' ), get_user_meta( $this->user, '_testkey' ) );
	}

	public function test_prepare_item() {
		wp_set_current_user( $this->user );
		$this->allow_user_to_manage_multisite();

		$meta_id = add_user_meta( $this->user, 'testkey', 'testvalue' );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/users/%d/meta/%d', $this->user, $meta_id ) );
		$response = $this->server->dispatch( $request );

		$data = $response->get_data();
		$this->assertEquals( $meta_id, $data['id'] );
		$this->assertEquals( 'testkey', $data['key'] );
		$this->assertEquals( 'testvalue', $data['value'] );
	}

	public function test_get_item_schema() {
		// No op
	}

	protected function allow_user_to_manage_multisite() {
		wp_set_current_user( $this->user );
		$user = wp_get_current_user();
		if ( is_multisite() ) {
			update_site_option( 'site_admins', array( $user->user_login ) );
		}
		return;
	}
}
