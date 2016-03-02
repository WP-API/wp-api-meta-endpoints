<?php

/**
 * Unit tests covering WP_REST_Terms meta functionality.
 *
 * @package WordPress
 * @subpackage JSON API
 */
class WP_Test_REST_Meta_Categories_Controller extends WP_Test_REST_Controller_Testcase {
	public function setUp() {
		parent::setUp();

		$this->administrator = $this->factory->user->create( array(
			'role' => 'administrator',
		) );
		$this->subscriber = $this->factory->user->create( array(
			'role' => 'subscriber',
		) );

		wp_set_current_user( $this->administrator );

		$this->category_id = $this->factory->category->create( array( 'name' => 'Test Category' ) );
		$this->category_2_id = $this->factory->category->create( array( 'name' => 'Test Category 2' ) );
	}

	public function test_register_routes() {
		$routes = $this->server->get_routes();

		$this->assertArrayHasKey( '/wp/v2/categories/(?P<parent_id>[\d]+)/meta', $routes );
		$this->assertCount( 2, $routes['/wp/v2/categories/(?P<parent_id>[\d]+)/meta'] );
		$this->assertArrayHasKey( '/wp/v2/categories/(?P<parent_id>[\d]+)/meta/(?P<id>[\d]+)', $routes );
		$this->assertCount( 3, $routes['/wp/v2/categories/(?P<parent_id>[\d]+)/meta/(?P<id>[\d]+)'] );
	}

	public function test_context_param() {
		// Collection
		$request = new WP_REST_Request( 'OPTIONS', '/wp/v2/categories/' . $this->category_id . '/meta' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertEquals( 'edit', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertEquals( array( 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
		// Single
		$meta_id = add_term_meta( $this->category_id, 'testkey', 'testvalue' );
		$request = new WP_REST_Request( 'OPTIONS', '/wp/v2/categories/' . $this->category_id . '/meta/' . $meta_id );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertEquals( 'edit', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertEquals( array( 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
	}

	public function test_prepare_item() {
		$meta_id = add_term_meta( $this->category_id, 'testkey', 'testvalue' );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/categories/%d/meta/%d', $this->category_id, $meta_id ) );
		$response = $this->server->dispatch( $request );

		$data = $response->get_data();
		$this->assertEquals( $meta_id, $data['id'] );
		$this->assertEquals( 'testkey', $data['key'] );
		$this->assertEquals( 'testvalue', $data['value'] );
	}

	public function test_get_items() {
		$meta_id_basic = add_term_meta( $this->category_id, 'testkey', 'testvalue' );
		$meta_id_other1 = add_term_meta( $this->category_id, 'testotherkey', 'testvalue1' );
		$meta_id_other2 = add_term_meta( $this->category_id, 'testotherkey', 'testvalue2' );
		$value = array( 'testvalue1', 'testvalue2' );
		// serialized
		add_term_meta( $this->category_id, 'testkey', $value );
		$value = (object) array( 'testvalue' => 'test' );
		// serialized object
		add_term_meta( $this->category_id, 'testkey', $value );
		$value = serialize( array( 'testkey1' => 'testvalue1', 'testkey2' => 'testvalue2' ) );
		// serialized string
		add_term_meta( $this->category_id, 'testkey', $value );
		// protected
		add_term_meta( $this->category_id, '_testkey', 'testvalue' );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/categories/%d/meta', $this->category_id ) );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertCount( 3, $data );

		foreach ( $data as $row ) {
			$row = (array) $row;
			$this->assertArrayHasKey( 'id', $row );
			$this->assertArrayHasKey( 'key', $row );
			$this->assertArrayHasKey( 'value', $row );

			$this->assertTrue( in_array( $row['id'], array( $meta_id_basic, $meta_id_other1, $meta_id_other2 ) ) );

			if ( $row['id'] === $meta_id_basic ) {
				$this->assertEquals( 'testkey', $row['key'] );
				$this->assertEquals( 'testvalue', $row['value'] );
			} elseif ( $row['id'] === $meta_id_other1 ) {
				$this->assertEquals( 'testotherkey', $row['key'] );
				$this->assertEquals( 'testvalue1', $row['value'] );
			} elseif ( $row['id'] === $meta_id_other2 ) {
				$this->assertEquals( 'testotherkey', $row['key'] );
				$this->assertEquals( 'testvalue2', $row['value'] );
			} else {
				$this->fail();
			}
		}
	}

	public function test_get_item() {
		$meta_id = add_term_meta( $this->category_id, 'testkey', 'testvalue' );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/categories/%d/meta/%d', $this->category_id, $meta_id ) );

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertEquals( $meta_id, $data['id'] );
		$this->assertEquals( 'testkey', $data['key'] );
		$this->assertEquals( 'testvalue', $data['value'] );
	}

	public function test_get_item_no_term_id() {
		$meta_id = add_term_meta( $this->category_id, 'testkey', 'testvalue' );

		// Use the real URL to ensure routing succeeds
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/categories/%d/meta/%d', $this->category_id, $meta_id ) );
		// Override the id parameter to ensure meta is checking it
		$request['parent_id'] = 0;

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_term_invalid_id', $response, 404 );
	}

	public function test_get_item_invalid_term_id() {
		$meta_id = add_term_meta( $this->category_id, 'testkey', 'testvalue' );

		// Use the real URL to ensure routing succeeds
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/categories/%d/meta/%d', $this->category_id, $meta_id ) );
		// Override the id parameter to ensure meta is checking it
		$request['parent_id'] = -1;

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_term_invalid_id', $response, 404 );
	}

	public function test_get_item_no_meta_id() {
		$meta_id = add_term_meta( $this->category_id, 'testkey', 'testvalue' );

		// Override the mid parameter to ensure meta is checking it
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/categories/%d/meta/%d', 0, $meta_id ) );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_term_invalid_id', $response, 404 );
	}

	public function test_get_item_invalid_meta_id() {
		$meta_id = add_term_meta( $this->category_id, 'testkey', 'testvalue' );

		// Use the real URL to ensure routing succeeds
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/categories/%d/meta/%d', $this->category_id, $meta_id ) );
		// Override the mid parameter to ensure meta is checking it
		$request['id'] = -1;

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_invalid_id', $response, 404 );
	}

	public function test_get_item_protected() {
		$meta_id = add_term_meta( $this->category_id, '_testkey', 'testvalue' );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/categories/%d/meta/%d', $this->category_id, $meta_id ) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_protected', $response, 403 );
	}

	public function test_get_item_serialized_array() {
		$meta_id = add_term_meta( $this->category_id, 'testkey', array( 'testvalue' => 'test' ) );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/categories/%d/meta/%d', $this->category_id, $meta_id ) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_protected', $response, 403 );
	}

	public function test_get_item_serialized_object() {
		$meta_id = add_term_meta( $this->category_id, 'testkey', (object) array( 'testvalue' => 'test' ) );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/categories/%d/meta/%d', $this->category_id, $meta_id ) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_protected', $response, 403 );
	}

	public function test_get_item_unauthenticated() {
		$meta_id = add_term_meta( $this->category_id, 'testkey', 'testvalue' );

		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/categories/%d/meta/%d', $this->category_id, $meta_id ) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_forbidden', $response, 401 );
	}

	public function test_get_item_wrong_term() {
		$meta_id = add_term_meta( $this->category_id, 'testkey', 'testvalue' );
		$meta_id_two = add_term_meta( $this->category_2_id, 'testkey', 'testvalue' );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/categories/%d/meta/%d', $this->category_2_id, $meta_id ) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_term_mismatch', $response, 400 );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/categories/%d/meta/%d', $this->category_id, $meta_id_two ) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_term_mismatch', $response, 400 );
	}

	public function test_get_items_no_term_id() {
		add_term_meta( $this->category_id, 'testkey', 'testvalue' );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/categories/%d/meta', $this->category_id ) );
		$request['parent_id'] = 0;
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_term_invalid_id', $response );
	}

	public function test_get_items_invalid_term_id() {
		add_term_meta( $this->category_id, 'testkey', 'testvalue' );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/categories/%d/meta', $this->category_id ) );
		$request['parent_id'] = -1;
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_term_invalid_id', $response );
	}

	public function test_get_items_unauthenticated() {
		add_term_meta( $this->category_id, 'testkey', 'testvalue' );

		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/categories/%d/meta', $this->category_id ) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_forbidden', $response );
	}

	public function test_create_item() {
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/categories/%d/meta', $this->category_id ) );
		$request->set_body_params( array(
			'key'   => 'testkey',
			'value' => 'testvalue',
		)  );

		$response = $this->server->dispatch( $request );

		$meta = get_term_meta( $this->category_id, 'testkey', false );
		$this->assertNotEmpty( $meta );
		$this->assertCount( 1, $meta );
		$this->assertEquals( 'testvalue', $meta[0] );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'id', $data );
		$this->assertEquals( 'testkey', $data['key'] );
		$this->assertEquals( 'testvalue', $data['value'] );
	}

	public function test_update_item() {
		$meta_id = add_term_meta( $this->category_id, 'testkey', 'testvalue' );

		$data = array(
			'value' => 'testnewvalue',
		);
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/categories/%d/meta/%d', $this->category_id, $meta_id ) );
		$request->set_body_params( $data );

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertEquals( $meta_id, $data['id'] );
		$this->assertEquals( 'testkey', $data['key'] );
		$this->assertEquals( 'testnewvalue', $data['value'] );

		$meta = get_term_meta( $this->category_id, 'testkey', false );
		$this->assertNotEmpty( $meta );
		$this->assertCount( 1, $meta );
		$this->assertEquals( 'testnewvalue', $meta[0] );
	}

	public function test_update_meta_key() {
		$meta_id = add_term_meta( $this->category_id, 'testkey', 'testvalue' );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/categories/%d/meta/%d', $this->category_id, $meta_id ) );
		$request->set_body_params( array(
			'key' => 'testnewkey',
		) );

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertEquals( $meta_id, $data['id'] );
		$this->assertEquals( 'testnewkey', $data['key'] );
		$this->assertEquals( 'testvalue', $data['value'] );

		$meta = get_term_meta( $this->category_id, 'testnewkey', false );
		$this->assertNotEmpty( $meta );
		$this->assertCount( 1, $meta );
		$this->assertEquals( 'testvalue', $meta[0] );

		// Ensure it was actually renamed, not created
		$meta = get_term_meta( $this->category_id, 'testkey', false );
		$this->assertEmpty( $meta );
	}

	public function test_update_meta_key_and_value() {
		$meta_id = add_term_meta( $this->category_id, 'testkey', 'testvalue' );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/categories/%d/meta/%d', $this->category_id, $meta_id ) );
		$request->set_body_params( array(
			'key'   => 'testnewkey',
			'value' => 'testnewvalue',
		) );

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertEquals( $meta_id, $data['id'] );
		$this->assertEquals( 'testnewkey', $data['key'] );
		$this->assertEquals( 'testnewvalue', $data['value'] );

		$meta = get_term_meta( $this->category_id, 'testnewkey', false );
		$this->assertNotEmpty( $meta );
		$this->assertCount( 1, $meta );
		$this->assertEquals( 'testnewvalue', $meta[0] );

		// Ensure it was actually renamed, not created
		$meta = get_term_meta( $this->category_id, 'testkey', false );
		$this->assertEmpty( $meta );
	}

	public function test_update_meta_empty() {
		$meta_id = add_term_meta( $this->category_id, 'testkey', 'testvalue' );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/categories/%d/meta/%d', $this->category_id, $meta_id ) );
		$request->set_body_params( array() );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_data_invalid', $response, 400 );
	}

	public function test_update_meta_no_term_id() {
		$meta_id = add_term_meta( $this->category_id, 'testkey', 'testvalue' );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/categories/%d/meta/%d', 0, $meta_id ) );
		$request->set_body_params( array(
			'key'   => 'testnewkey',
			'value' => 'testnewvalue',
		) );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_term_invalid_id', $response, 404 );
	}

	public function test_update_meta_invalid_term_id() {
		$meta_id = add_term_meta( $this->category_id, 'testkey', 'testvalue' );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/categories/%d/meta/%d', REST_TESTS_IMPOSSIBLY_HIGH_NUMBER, $meta_id ) );
		$request->set_body_params( array(
			'key'   => 'testnewkey',
			'value' => 'testnewvalue',
		) );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_term_invalid_id', $response, 404 );
	}

	public function test_update_meta_no_meta_id() {
		add_term_meta( $this->category_id, 'testkey', 'testvalue' );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/categories/%d/meta/%d', $this->category_id, 0 ) );
		$request->set_body_params( array(
			'key'   => 'testnewkey',
			'value' => 'testnewvalue',
		) );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_invalid_id', $response, 404 );
		$this->assertEquals( array( 'testvalue' ), get_term_meta( $this->category_id, 'testkey' ) );
	}

	public function test_update_meta_invalid_meta_id() {
		$meta_id = add_term_meta( $this->category_id, 'testkey', 'testvalue' );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/categories/%d/meta/%d', $this->category_id, $meta_id ) );
		$request['id'] = 'a';
		$request->set_body_params( array(
			'key'   => 'testnewkey',
			'value' => 'testnewvalue',
		) );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_invalid_id', $response, 404 );
		$this->assertEquals( array( 'testvalue' ), get_term_meta( $this->category_id, 'testkey' ) );
	}

	public function test_update_meta_unauthenticated() {
		$meta_id = add_term_meta( $this->category_id, 'testkey', 'testvalue' );

		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/categories/%d/meta/%d', $this->category_id, $meta_id ) );
		$request->set_body_params( array(
			'key'   => 'testnewkey',
			'value' => 'testnewvalue',
		) );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_forbidden', $response, 401 );
		$this->assertEquals( array( 'testvalue' ), get_term_meta( $this->category_id, 'testkey' ) );
	}

	public function test_update_meta_wrong_term() {
		$meta_id = add_term_meta( $this->category_id, 'testkey', 'testvalue' );
		$meta_id_two = add_term_meta( $this->category_2_id, 'testkey', 'testvalue' );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/categories/%d/meta/%d', $this->category_2_id, $meta_id ) );
		$request->set_body_params( array(
			'key'   => 'testnewkey',
			'value' => 'testnewvalue',
		) );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_term_mismatch', $response, 400 );
		$this->assertEquals( array( 'testvalue' ), get_term_meta( $this->category_2_id, 'testkey' ) );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/categories/%d/meta/%d', $this->category_id, $meta_id_two ) );
		$request->set_body_params( array(
			'key'   => 'testnewkey',
			'value' => 'testnewvalue',
		) );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_term_mismatch', $response, 400 );
		$this->assertEquals( array( 'testvalue' ), get_term_meta( $this->category_id, 'testkey' ) );
	}

	public function test_update_meta_serialized_array() {
		$meta_id = add_term_meta( $this->category_id, 'testkey', 'testvalue' );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/categories/%d/meta/%d', $this->category_id, $meta_id ) );
		$request->set_body_params( array(
			'value' => array( 'testvalue1', 'testvalue2' ),
		) );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
		$this->assertEquals( array( 'testvalue' ), get_term_meta( $this->category_id, 'testkey' ) );
	}

	public function test_update_meta_serialized_object() {
		$meta_id = add_term_meta( $this->category_id, 'testkey', 'testvalue' );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/categories/%d/meta/%d', $this->category_id, $meta_id ) );
		$request->set_body_params( array(
			'value' => (object) array( 'testkey1' => 'testvalue1', 'testkey2' => 'testvalue2' ),
		) );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
		$this->assertEquals( array( 'testvalue' ), get_term_meta( $this->category_id, 'testkey' ) );
	}

	public function test_update_meta_serialized_string() {
		$meta_id = add_term_meta( $this->category_id, 'testkey', 'testvalue' );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/categories/%d/meta/%d', $this->category_id, $meta_id ) );
		$request->set_body_params( array(
			'value' => serialize( array( 'testkey1' => 'testvalue1', 'testkey2' => 'testvalue2' ) ),
		) );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_invalid_action', $response, 400 );
		$this->assertEquals( array( 'testvalue' ), get_term_meta( $this->category_id, 'testkey' ) );
	}

	public function test_update_meta_existing_serialized() {
		$meta_id = add_term_meta( $this->category_id, 'testkey', array( 'testvalue1', 'testvalue2' ) );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/categories/%d/meta/%d', $this->category_id, $meta_id ) );
		$request->set_body_params( array(
			'value' => 'testnewvalue',
		) );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_invalid_action', $response, 400 );
		$this->assertEquals( array( array( 'testvalue1', 'testvalue2' ) ), get_term_meta( $this->category_id, 'testkey' ) );
	}

	public function test_update_meta_protected() {
		$meta_id = add_term_meta( $this->category_id, '_testkey', 'testvalue' );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/categories/%d/meta/%d', $this->category_id, $meta_id ) );
		$request->set_body_params( array(
			'value' => 'testnewvalue',
		) );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_protected', $response, 403 );
		$this->assertEquals( array( 'testvalue' ), get_term_meta( $this->category_id, '_testkey' ) );
	}

	public function test_update_meta_protected_new() {
		$meta_id = add_term_meta( $this->category_id, 'testkey', 'testvalue' );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/categories/%d/meta/%d', $this->category_id, $meta_id ) );
		$request->set_body_params( array(
			'key'   => '_testnewkey',
			'value' => 'testnewvalue',
		) );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_protected', $response, 403 );
		$this->assertEquals( array( 'testvalue' ), get_term_meta( $this->category_id, 'testkey' ) );
		$this->assertEmpty( get_term_meta( $this->category_id, '_testnewkey' ) );
	}

	public function test_update_meta_invalid_key() {
		$meta_id = add_term_meta( $this->category_id, 'testkey', 'testvalue' );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/categories/%d/meta/%d', $this->category_id, $meta_id ) );
		$request->set_body_params( array(
			'key'   => false,
			'value' => 'testnewvalue',
		) );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_invalid_key', $response, 400 );
		$this->assertEquals( array( 'testvalue' ), get_term_meta( $this->category_id, 'testkey' ) );
	}

	/**
	 * Ensure slashes aren't added
	 */
	public function test_update_meta_unslashed() {
		$meta_id = add_term_meta( $this->category_id, 'testkey', 'testvalue' );

		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/categories/%d/meta/%d', $this->category_id, $meta_id ) );
		$request->set_body_params( array(
			'key'   => 'testkey',
			'value' => "test unslashed ' value",
		) );

		$this->server->dispatch( $request );

		$meta = get_term_meta( $this->category_id, 'testkey', false );
		$this->assertNotEmpty( $meta );
		$this->assertCount( 1, $meta );
		$this->assertEquals( "test unslashed ' value", $meta[0] );
	}

	/**
	 * Ensure slashes aren't touched in data
	 */
	public function test_update_meta_slashed() {
		$meta_id = add_term_meta( $this->category_id, 'testkey', 'testvalue' );

		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/categories/%d/meta/%d', $this->category_id, $meta_id ) );
		$request->set_body_params( array(
			'key'   => 'testkey',
			'value' => "test slashed \\' value",
		) );

		$this->server->dispatch( $request );

		$meta = get_term_meta( $this->category_id, 'testkey', false );
		$this->assertNotEmpty( $meta );
		$this->assertCount( 1, $meta );
		$this->assertEquals( "test slashed \\' value", $meta[0] );
	}

	public function test_delete_item() {
		$meta_id = add_term_meta( $this->category_id, 'testkey', 'testvalue' );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/categories/%d/meta/%d', $this->category_id, $meta_id ) );
		$request['force'] = true;
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'message', $data );
		$this->assertNotEmpty( $data['message'] );

		$meta = get_term_meta( $this->category_id, 'testkey', false );
		$this->assertEmpty( $meta );
	}

	public function test_delete_item_no_trash() {
		$meta_id = add_term_meta( $this->category_id, 'testkey', 'testvalue' );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/categories/%d/meta/%d', $this->category_id, $meta_id ) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_trash_not_supported', $response, 501 );

		// Ensure the meta still exists
		$meta = get_metadata_by_mid( 'term', $meta_id );
		$this->assertNotEmpty( $meta );
	}

	public function test_delete_item_no_term_id() {
		$meta_id = add_term_meta( $this->category_id, 'testkey', 'testvalue' );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/categories/%d/meta/%d', $this->category_id, $meta_id ) );
		$request['force'] = true;
		$request['parent_id'] = 0;

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_term_invalid_id', $response, 404 );

		$this->assertEquals( array( 'testvalue' ), get_term_meta( $this->category_id, 'testkey', false ) );
	}

	public function test_delete_item_invalid_term_id() {
		$meta_id = add_term_meta( $this->category_id, 'testkey', 'testvalue' );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/categories/%d/meta/%d', $this->category_id, $meta_id ) );
		$request['force'] = true;
		$request['parent_id'] = -1;

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_term_invalid_id', $response, 404 );

		$this->assertEquals( array( 'testvalue' ), get_term_meta( $this->category_id, 'testkey', false ) );
	}

	public function test_delete_item_no_meta_id() {
		$meta_id = add_term_meta( $this->category_id, 'testkey', 'testvalue' );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/categories/%d/meta/%d', $this->category_id, $meta_id ) );
		$request['force'] = true;
		$request['id'] = 0;

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_invalid_id', $response, 404 );

		$this->assertEquals( array( 'testvalue' ), get_term_meta( $this->category_id, 'testkey', false ) );
	}

	public function test_delete_item_invalid_meta_id() {
		$meta_id = add_term_meta( $this->category_id, 'testkey', 'testvalue' );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/categories/%d/meta/%d', $this->category_id, $meta_id ) );
		$request['force'] = true;
		$request['id'] = -1;

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_invalid_id', $response, 404 );

		$this->assertEquals( array( 'testvalue' ), get_term_meta( $this->category_id, 'testkey', false ) );
	}

	public function test_delete_item_unauthenticated() {
		$meta_id = add_term_meta( $this->category_id, 'testkey', 'testvalue' );

		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/categories/%d/meta/%d', $this->category_id, $meta_id ) );
		$request['force'] = true;

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_forbidden', $response, 401 );

		$this->assertEquals( array( 'testvalue' ), get_term_meta( $this->category_id, 'testkey', false ) );
	}

	public function test_delete_item_wrong_term() {
		$meta_id = add_term_meta( $this->category_id, 'testkey', 'testvalue' );
		$meta_id_two = add_term_meta( $this->category_2_id, 'testkey', 'testvalue' );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/categories/%d/meta/%d', $this->category_2_id, $meta_id ) );
		$request['force'] = true;

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_term_mismatch', $response, 400 );
		$this->assertEquals( array( 'testvalue' ), get_term_meta( $this->category_2_id, 'testkey' ) );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/categories/%d/meta/%d', $this->category_id, $meta_id_two ) );
		$request['force'] = true;

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_term_mismatch', $response, 400 );
		$this->assertEquals( array( 'testvalue' ), get_term_meta( $this->category_id, 'testkey' ) );
	}

	public function test_delete_item_serialized_array() {
		$value = array( 'testvalue1', 'testvalue2' );
		$meta_id = add_term_meta( $this->category_id, 'testkey', $value );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/categories/%d/meta/%d', $this->category_id, $meta_id ) );
		$request['force'] = true;

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_invalid_action', $response, 400 );
		$this->assertEquals( array( $value ), get_term_meta( $this->category_id, 'testkey' ) );
	}

	public function test_delete_item_serialized_object() {
		$value = (object) array( 'testkey1' => 'testvalue1', 'testkey2' => 'testvalue2' );
		$meta_id = add_term_meta( $this->category_id, 'testkey', $value );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/categories/%d/meta/%d', $this->category_id, $meta_id ) );
		$request['force'] = true;

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_invalid_action', $response, 400 );
		$this->assertEquals( array( $value ), get_term_meta( $this->category_id, 'testkey' ) );
	}

	public function test_delete_item_serialized_string() {
		$value = serialize( array( 'testkey1' => 'testvalue1', 'testkey2' => 'testvalue2' ) );
		$meta_id = add_term_meta( $this->category_id, 'testkey', $value );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/categories/%d/meta/%d', $this->category_id, $meta_id ) );
		$request['force'] = true;

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_invalid_action', $response, 400 );
		$this->assertEquals( array( $value ), get_term_meta( $this->category_id, 'testkey' ) );
	}

	public function test_delete_item_protected() {
		$meta_id = add_term_meta( $this->category_id, '_testkey', 'testvalue' );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/categories/%d/meta/%d', $this->category_id, $meta_id ) );
		$request['force'] = true;

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_protected', $response, 403 );
		$this->assertEquals( array( 'testvalue' ), get_term_meta( $this->category_id, '_testkey' ) );
	}

	public function test_get_item_schema() {
		// No-op
	}
}
