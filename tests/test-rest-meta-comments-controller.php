<?php
/**
 * Unit tests covering WP_REST_Comments meta functionality.
 *
 * @package WordPress
 * @subpackage JSON API
 */
class WP_Test_REST_Meta_Comments_Controller extends WP_Test_REST_Controller_Testcase {
	protected $admin_id;
	protected $subscriber_id;
	protected $post_id;
	protected $approved_id;
	protected $hold_id;
	public function setUp() {
		parent::setUp();
		$this->admin_id = $this->factory->user->create( array(
			'role' => 'administrator',
		));
		$this->subscriber_id = $this->factory->user->create( array(
			'role' => 'subscriber',
		));
		$this->author_id = $this->factory->user->create( array(
			'role' => 'author',
		));
		$this->post_id = $this->factory->post->create();
		$this->approved_id = $this->factory->comment->create( array(
			'comment_approved' => 1,
			'comment_post_ID'  => $this->post_id,
			'user_id'          => 0,
		));
		$this->hold_id = $this->factory->comment->create( array(
			'comment_approved' => 0,
			'comment_post_ID'  => $this->post_id,
			'user_id'          => $this->subscriber_id,
		));
	}
	public function test_register_routes() {
		$routes = $this->server->get_routes();
		$this->assertArrayHasKey( '/wp/v2/comments/(?P<parent_id>[\d]+)/meta', $routes );
		$this->assertCount( 2, $routes['/wp/v2/comments/(?P<parent_id>[\d]+)/meta'] );
		$this->assertArrayHasKey( '/wp/v2/comments/(?P<parent_id>[\d]+)/meta/(?P<id>[\d]+)', $routes );
		$this->assertCount( 3, $routes['/wp/v2/comments/(?P<parent_id>[\d]+)/meta/(?P<id>[\d]+)'] );
	}
	public function test_context_param() {
		// Collection
		$request = new WP_REST_Request( 'OPTIONS', '/wp/v2/comments/' . $this->approved_id . '/meta' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertEquals( 'edit', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertEquals( array( 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
		// Single
		$meta_id_basic = add_comment_meta( $this->approved_id, 'testkey', 'testvalue' );
		$request = new WP_REST_Request( 'OPTIONS', '/wp/v2/comments/' . $this->approved_id . '/meta/' . $meta_id_basic );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertEquals( 'edit', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertEquals( array( 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
	}
	public function test_get_item() {
		wp_set_current_user( $this->admin_id );
		$meta_id = add_comment_meta( $this->approved_id, 'testkey', 'testvalue' );
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/comments/%d/meta/%d', $this->approved_id, $meta_id ) );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertEquals( $meta_id, $data['id'] );
		$this->assertEquals( 'testkey', $data['key'] );
		$this->assertEquals( 'testvalue', $data['value'] );
	}
	public function test_get_items() {
		wp_set_current_user( $this->admin_id );
		$meta_id = add_comment_meta( $this->approved_id, 'testkey', 'testvalue' );
		$meta_id_basic = add_comment_meta( $this->approved_id, 'testkey', 'testvalue' );
		$meta_id_other1 = add_comment_meta( $this->approved_id, 'testotherkey', 'testvalue1' );
		$meta_id_other2 = add_comment_meta( $this->approved_id, 'testotherkey', 'testvalue2' );
		$value = array( 'testvalue1', 'testvalue2' );
		// serialized
		add_comment_meta( $this->approved_id, 'testkey', $value );
		$value = (object) array( 'testvalue' => 'test' );
		// serialized object
		add_comment_meta( $this->approved_id, 'testkey', $value );
		$value = serialize( array( 'testkey1' => 'testvalue1', 'testkey2' => 'testvalue2' ) );
		// serialized string
		add_comment_meta( $this->approved_id, 'testkey', $value );
		// protected
		add_comment_meta( $this->approved_id, '_testkey', 'testvalue' );
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/comments/%d/meta', $this->approved_id ) );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		foreach ( $data as $row ) {
			$row = (array) $row;
			$this->assertArrayHasKey( 'id', $row );
			$this->assertArrayHasKey( 'key', $row );
			$this->assertArrayHasKey( 'value', $row );
			if ( $row['id'] === $meta_id_basic ) {
				$this->assertEquals( 'testkey', $row['key'] );
				$this->assertEquals( 'testvalue', $row['value'] );
			}
			if ( $row['id'] === $meta_id_other1 ) {
				$this->assertEquals( 'testotherkey', $row['key'] );
				$this->assertEquals( 'testvalue1', $row['value'] );
			}
			if ( $row['id'] === $meta_id_other2 ) {
				$this->assertEquals( 'testotherkey', $row['key'] );
				$this->assertEquals( 'testvalue2', $row['value'] );
			}
		}
	}
	public function test_get_item_no_comment_id() {
		wp_set_current_user( $this->admin_id );
		$meta_id = add_comment_meta( $this->approved_id, 'testkey', 'testvalue' );
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/comments/%d/meta/%d', $this->approved_id, $meta_id ) );
		$request['parent_id'] = 0;
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_comment_invalid_id', $response, 404 );
	}
	public function test_get_item_invalid_comment_id() {
		wp_set_current_user( $this->admin_id );
		$meta_id = add_comment_meta( $this->approved_id, 'testkey', 'testvalue' );
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/comments/%d/meta/%d', $this->approved_id, $meta_id ) );
		$request['parent_id'] = -1;
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_comment_invalid_id', $response, 404 );
	}
	public function test_get_item_no_meta_id() {
		wp_set_current_user( $this->admin_id );
		$meta_id = add_comment_meta( $this->approved_id, 'testkey', 'testvalue' );
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/comments/%d/meta/%d', 0, $meta_id ) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_comment_invalid_id', $response, 404 );
	}
	public function test_get_item_invalid_meta_id() {
		wp_set_current_user( $this->admin_id );
		$meta_id = add_comment_meta( $this->approved_id, 'testkey', 'testvalue' );
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/comments/%d/meta/%d', $this->approved_id, $meta_id ) );
		$request['id'] = 'a';
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_invalid_id', $response, 404 );
	}
	public function test_get_item_protected() {
		wp_set_current_user( $this->admin_id );
		$meta_id = add_comment_meta( $this->approved_id, '_testkey', 'testvalue' );
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/comments/%d/meta/%d', $this->approved_id, $meta_id ) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_protected', $response, 403 );
	}
	public function test_get_item_serialized_array() {
		wp_set_current_user( $this->admin_id );
		$meta_id = add_comment_meta( $this->approved_id, 'testkey', array( 'testvalue' => 'test' ) );
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/comments/%d/meta/%d', $this->approved_id, $meta_id ) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_protected', $response, 403 );
	}
	public function test_get_item_serialized_object() {
		wp_set_current_user( $this->admin_id );
		$meta_id = add_comment_meta( $this->approved_id, 'testkey', (object) array( 'testvalue' => 'test' ) );
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/comments/%d/meta/%d', $this->approved_id, $meta_id ) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_protected', $response, 403 );
	}
	public function test_get_item_unauthenticated() {
		$meta_id = add_comment_meta( $this->approved_id, 'testkey', 'testvalue' );
		wp_set_current_user( 0 );
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/comments/%d/meta/%d', $this->approved_id, $meta_id ) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_forbidden', $response, 401 );
	}
	public function test_get_item_wrong_comment() {
		wp_set_current_user( $this->admin_id );
		$meta_id = add_comment_meta( $this->approved_id, 'testkey', 'testvalue' );
		$meta_id_two = add_comment_meta( $this->hold_id, 'testkey', 'testvalue' );
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/comments/%d/meta/%d', $this->hold_id, $meta_id ) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_comment_mismatch', $response, 400 );
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/comments/%d/meta/%d', $this->approved_id, $meta_id_two ) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_comment_mismatch', $response, 400 );
	}
	public function test_get_items_no_comment_id() {
		wp_set_current_user( $this->admin_id );
		add_comment_meta( $this->approved_id, 'testkey', 'testvalue' );
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/comments/%d/meta', $this->approved_id ) );
		$request['parent_id'] = 0;
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_comment_invalid_id', $response );
	}
	public function test_get_items_invalid_comment_id() {
		wp_set_current_user( $this->admin_id );
		add_comment_meta( $this->approved_id, 'testkey', 'testvalue' );
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/comments/%d/meta', $this->approved_id ) );
		$request['parent_id'] = -1;
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_comment_invalid_id', $response );
	}
	public function test_get_items_unauthenticated() {
		add_comment_meta( $this->approved_id, 'testkey', 'testvalue' );
		wp_set_current_user( 0 );
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/comments/%d/meta', $this->approved_id ) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_forbidden', $response );
	}
	public function test_create_item() {
		wp_set_current_user( $this->admin_id );
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/comments/%d/meta', $this->approved_id ) );
		$request->set_body_params( array(
			'key'   => 'testkey',
			'value' => 'testvalue',
		) );
		$response = $this->server->dispatch( $request );
		$meta = get_comment_meta( $this->approved_id, 'testkey', false );
		$this->assertNotEmpty( $meta );
		$this->assertCount( 1, $meta );
		$this->assertEquals( 'testvalue', $meta[0] );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'id', $data );
		$this->assertEquals( 'testkey', $data['key'] );
		$this->assertEquals( 'testvalue', $data['value'] );
	}
	public function test_create_item_no_comment_id() {
		wp_set_current_user( $this->admin_id );
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/comments/%d/meta', $this->approved_id ) );
		$request->set_body_params( array(
			'key'   => 'testkey',
			'value' => 'testvalue',
		) );
		$request['parent_id'] = 0;
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_comment_invalid_id', $response, 404 );
	}
	public function test_create_item_invalid_comment_id() {
		wp_set_current_user( $this->admin_id );
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/comments/%d/meta', $this->approved_id ) );
		$request->set_body_params( array(
			'key'   => 'testkey',
			'value' => 'testvalue',
		) );
		$request['parent_id'] = -1;
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_comment_invalid_id', $response, 404 );
	}
	public function test_create_item_no_value() {
		wp_set_current_user( $this->admin_id );
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/comments/%d/meta', $this->approved_id ) );
		$request->set_body_params( array(
			'key' => 'testkey',
		) );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'id', $data );
		$this->assertEquals( 'testkey', $data['key'] );
		$this->assertEquals( '', $data['value'] );
	}
	public function test_create_item_no_key() {
		wp_set_current_user( $this->admin_id );
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/comments/%d/meta', $this->approved_id ) );
		$request->set_body_params( array(
			'value' => 'testvalue',
		) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_missing_callback_param', $response, 400 );
	}
	public function test_create_item_empty_string_key() {
		wp_set_current_user( $this->admin_id );
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/comments/%d/meta', $this->approved_id ) );
		$request->set_body_params( array(
			'key'   => '',
			'value' => 'testvalue',
		) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_invalid_key', $response, 400 );
	}
	public function test_create_item_invalid_key() {
		wp_set_current_user( $this->admin_id );
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/comments/%d/meta', $this->approved_id ) );
		$request->set_body_params( array(
			'key' => false,
			'value' => 'testvalue',
		) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_invalid_key', $response, 400 );
	}
	public function test_create_item_unauthenticated() {
		wp_set_current_user( 0 );
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/comments/%d/meta', $this->approved_id ) );
		$request->set_body_params( array(
			'key'   => 'testkey',
			'value' => 'testvalue',
		) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_forbidden', $response, 401 );
		$this->assertEmpty( get_comment_meta( $this->approved_id, 'testkey' ) );
	}
	public function test_create_item_serialized_array() {
		wp_set_current_user( $this->admin_id );
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/comments/%d/meta', $this->approved_id ) );
		$request->set_body_params( array(
			'key'   => 'testkey',
			'value' => array( 'testvalue1', 'testvalue2' ),
		) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
		$this->assertEmpty( get_comment_meta( $this->approved_id, 'testkey' ) );
	}
	public function test_create_item_serialized_object() {
		wp_set_current_user( $this->admin_id );
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/comments/%d/meta', $this->approved_id ) );
		$request->set_body_params( array(
			'key'   => 'testkey',
			'value' => (object) array( 'testkey1' => 'testvalue1', 'testkey2' => 'testvalue2' ),
		) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
		$this->assertEmpty( get_comment_meta( $this->approved_id, 'testkey' ) );
	}
	public function test_create_item_serialized_string() {
		wp_set_current_user( $this->admin_id );
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/comments/%d/meta', $this->approved_id ) );
		$request->set_body_params( array(
			'key'   => 'testkey',
			'value' => serialize( array( 'testkey1' => 'testvalue1', 'testkey2' => 'testvalue2' ) ),
		) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_invalid_action', $response, 400 );
		$this->assertEmpty( get_comment_meta( $this->approved_id, 'testkey' ) );
	}
	public function test_create_item_failed_get() {
		$this->markTestSkipped();
		$this->endpoint = $this->getMock( 'WP_REST_Meta_Comments', array( 'get_meta' ), array( $this->fake_server ) );
		$test_error = new WP_Error( 'rest_test_error', 'Test error' );
		$this->endpoint->expects( $this->any() )->method( 'get_meta' )->will( $this->returnValue( $test_error ) );
		wp_set_current_user( $this->admin_id );
		$response = $this->endpoint->add_meta( $this->approved_id, array(
			'key'   => 'testkey',
			'value' => 'testvalue',
		) );
		$this->assertErrorResponse( 'rest_test_error', $response );
	}
	public function test_create_item_protected() {
		wp_set_current_user( $this->admin_id );
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/comments/%d/meta', $this->approved_id ) );
		$request->set_body_params( array(
			'key'   => '_testkey',
			'value' => 'testvalue',
		) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_protected', $response, 403 );
		$this->assertEmpty( get_comment_meta( $this->approved_id, '_testkey' ) );
	}
	/**
	 * Ensure slashes aren't added
	 */
	public function test_create_item_unslashed() {
		wp_set_current_user( $this->admin_id );
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/comments/%d/meta', $this->approved_id ) );
		$request->set_body_params( array(
			'key'   => 'testkey',
			'value' => "test unslashed ' value",
		) );
		$this->server->dispatch( $request );
		$meta = get_comment_meta( $this->approved_id, 'testkey', false );
		$this->assertNotEmpty( $meta );
		$this->assertCount( 1, $meta );
		$this->assertEquals( "test unslashed ' value", $meta[0] );
	}
	/**
	 * Ensure slashes aren't touched in data
	 */
	public function test_create_item_slashed() {
		wp_set_current_user( $this->admin_id );
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/comments/%d/meta', $this->approved_id ) );
		$request->set_body_params( array(
			'key'   => 'testkey',
			'value' => "test slashed \\' value",
		) );
		$this->server->dispatch( $request );
		$meta = get_comment_meta( $this->approved_id, 'testkey', false );
		$this->assertNotEmpty( $meta );
		$this->assertCount( 1, $meta );
		$this->assertEquals( "test slashed \\' value", $meta[0] );
	}
	public function test_update_item() {
		wp_set_current_user( $this->admin_id );
		$meta_id = add_comment_meta( $this->approved_id, 'testkey', 'testvalue' );
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/comments/%d/meta/%d', $this->approved_id, $meta_id ) );
		$request->set_body_params( array(
			'value' => 'testnewvalue',
		) );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertEquals( $meta_id, $data['id'] );
		$this->assertEquals( 'testkey', $data['key'] );
		$this->assertEquals( 'testnewvalue', $data['value'] );
		$meta = get_comment_meta( $this->approved_id, 'testkey', false );
		$this->assertNotEmpty( $meta );
		$this->assertCount( 1, $meta );
		$this->assertEquals( 'testnewvalue', $meta[0] );
	}
	public function test_update_meta_key() {
		wp_set_current_user( $this->admin_id );
		$meta_id = add_comment_meta( $this->approved_id, 'testkey', 'testvalue' );
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/comments/%d/meta/%d', $this->approved_id, $meta_id ) );
		$request->set_body_params( array(
			'key' => 'testnewkey',
		) );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertEquals( $meta_id, $data['id'] );
		$this->assertEquals( 'testnewkey', $data['key'] );
		$this->assertEquals( 'testvalue', $data['value'] );
		$meta = get_comment_meta( $this->approved_id, 'testnewkey', false );
		$this->assertNotEmpty( $meta );
		$this->assertCount( 1, $meta );
		$this->assertEquals( 'testvalue', $meta[0] );
		// Ensure it was actually renamed, not created
		$meta = get_comment_meta( $this->approved_id, 'testkey', false );
		$this->assertEmpty( $meta );
	}
	public function test_update_meta_key_and_value() {
		wp_set_current_user( $this->admin_id );
		$meta_id = add_comment_meta( $this->approved_id, 'testkey', 'testvalue' );
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/comments/%d/meta/%d', $this->approved_id, $meta_id ) );
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
		$meta = get_comment_meta( $this->approved_id, 'testnewkey', false );
		$this->assertNotEmpty( $meta );
		$this->assertCount( 1, $meta );
		$this->assertEquals( 'testnewvalue', $meta[0] );
		// Ensure it was actually renamed, not created
		$meta = get_comment_meta( $this->approved_id, 'testkey', false );
		$this->assertEmpty( $meta );
	}
	public function test_update_meta_empty() {
		wp_set_current_user( $this->admin_id );
		$meta_id = add_comment_meta( $this->approved_id, 'testkey', 'testvalue' );
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/comments/%d/meta/%d', $this->approved_id, $meta_id ) );
		$request->set_body_params( array() );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_data_invalid', $response, 400 );
	}
	public function test_update_meta_no_comment_id() {
		wp_set_current_user( $this->admin_id );
		$meta_id = add_comment_meta( $this->approved_id, 'testkey', 'testvalue' );
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/comments/%d/meta/%d', 0, $meta_id ) );
		$request->set_body_params( array(
			'key'   => 'testnewkey',
			'value' => 'testnewvalue',
		) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_comment_invalid_id', $response, 404 );
	}
	public function test_update_meta_invalid_comment_id() {
		wp_set_current_user( $this->admin_id );
		$meta_id = add_comment_meta( $this->approved_id, 'testkey', 'testvalue' );
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/comments/%d/meta/%d', REST_TESTS_IMPOSSIBLY_HIGH_NUMBER, $meta_id ) );
		$request->set_body_params( array(
			'key'   => 'testnewkey',
			'value' => 'testnewvalue',
		) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_comment_invalid_id', $response, 404 );
	}
	public function test_update_meta_no_meta_id() {
		wp_set_current_user( $this->admin_id );
		add_comment_meta( $this->approved_id, 'testkey', 'testvalue' );
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/comments/%d/meta/%d', $this->approved_id, 0 ) );
		$request->set_body_params( array(
			'key'   => 'testnewkey',
			'value' => 'testnewvalue',
		) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_invalid_id', $response, 404 );
		$this->assertEquals( array( 'testvalue' ), get_comment_meta( $this->approved_id, 'testkey' ) );
	}
	public function test_update_meta_invalid_meta_id() {
		wp_set_current_user( $this->admin_id );
		$meta_id = add_comment_meta( $this->approved_id, 'testkey', 'testvalue' );
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/comments/%d/meta/%d', $this->approved_id, $meta_id ) );
		$request['id'] = 'a';
		$request->set_body_params( array(
			'key'   => 'testnewkey',
			'value' => 'testnewvalue',
		) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_invalid_id', $response, 404 );
		$this->assertEquals( array( 'testvalue' ), get_comment_meta( $this->approved_id, 'testkey' ) );
	}
	public function test_update_meta_unauthenticated() {
		$meta_id = add_comment_meta( $this->approved_id, 'testkey', 'testvalue' );
		wp_set_current_user( 0 );
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/comments/%d/meta/%d', $this->approved_id, $meta_id ) );
		$request->set_body_params( array(
			'key'   => 'testnewkey',
			'value' => 'testnewvalue',
		) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_forbidden', $response, 401 );
		$this->assertEquals( array( 'testvalue' ), get_comment_meta( $this->approved_id, 'testkey' ) );
	}
	public function test_update_meta_wrong_comment() {
		wp_set_current_user( $this->admin_id );
		$meta_id = add_comment_meta( $this->approved_id, 'testkey', 'testvalue' );
		$meta_id_two = add_comment_meta( $this->hold_id, 'testkey', 'testvalue' );
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/comments/%d/meta/%d', $this->hold_id, $meta_id ) );
		$request->set_body_params( array(
			'key'   => 'testnewkey',
			'value' => 'testnewvalue',
		) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_comment_mismatch', $response, 400 );
		$this->assertEquals( array( 'testvalue' ), get_comment_meta( $this->hold_id, 'testkey' ) );
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/comments/%d/meta/%d', $this->approved_id, $meta_id_two ) );
		$request->set_body_params( array(
			'key'   => 'testnewkey',
			'value' => 'testnewvalue',
		) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_comment_mismatch', $response, 400 );
		$this->assertEquals( array( 'testvalue' ), get_comment_meta( $this->approved_id, 'testkey' ) );
	}
	public function test_update_meta_serialized_array() {
		wp_set_current_user( $this->admin_id );
		$meta_id = add_comment_meta( $this->approved_id, 'testkey', 'testvalue' );
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/comments/%d/meta/%d', $this->approved_id, $meta_id ) );
		$request->set_body_params( array(
			'value' => array( 'testvalue1', 'testvalue2' ),
		) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
		$this->assertEquals( array( 'testvalue' ), get_comment_meta( $this->approved_id, 'testkey' ) );
	}
	public function test_update_meta_serialized_object() {
		wp_set_current_user( $this->admin_id );
		$meta_id = add_comment_meta( $this->approved_id, 'testkey', 'testvalue' );
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/comments/%d/meta/%d', $this->approved_id, $meta_id ) );
		$request->set_body_params( array(
			'value' => (object) array( 'testkey1' => 'testvalue1', 'testkey2' => 'testvalue2' ),
		) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
		$this->assertEquals( array( 'testvalue' ), get_comment_meta( $this->approved_id, 'testkey' ) );
	}
	public function test_update_meta_serialized_string() {
		wp_set_current_user( $this->admin_id );
		$meta_id = add_comment_meta( $this->approved_id, 'testkey', 'testvalue' );
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/comments/%d/meta/%d', $this->approved_id, $meta_id ) );
		$request->set_body_params( array(
			'value' => serialize( array( 'testkey1' => 'testvalue1', 'testkey2' => 'testvalue2' ) ),
		) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_invalid_action', $response, 400 );
		$this->assertEquals( array( 'testvalue' ), get_comment_meta( $this->approved_id, 'testkey' ) );
	}
	public function test_update_meta_existing_serialized() {
		wp_set_current_user( $this->admin_id );
		$meta_id = add_comment_meta( $this->approved_id, 'testkey', array( 'testvalue1', 'testvalue2' ) );
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/comments/%d/meta/%d', $this->approved_id, $meta_id ) );
		$request->set_body_params( array(
			'value' => 'testnewvalue',
		) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_invalid_action', $response, 400 );
		$this->assertEquals( array( array( 'testvalue1', 'testvalue2' ) ), get_comment_meta( $this->approved_id, 'testkey' ) );
	}
	public function test_update_meta_protected() {
		wp_set_current_user( $this->admin_id );
		$meta_id = add_comment_meta( $this->approved_id, '_testkey', 'testvalue' );
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/comments/%d/meta/%d', $this->approved_id, $meta_id ) );
		$request->set_body_params( array(
			'value' => 'testnewvalue',
		) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_protected', $response, 403 );
		$this->assertEquals( array( 'testvalue' ), get_comment_meta( $this->approved_id, '_testkey' ) );
	}
	public function test_update_meta_protected_new() {
		wp_set_current_user( $this->admin_id );
		$meta_id = add_comment_meta( $this->approved_id, 'testkey', 'testvalue' );
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/comments/%d/meta/%d', $this->approved_id, $meta_id ) );
		$request->set_body_params( array(
			'key'   => '_testnewkey',
			'value' => 'testnewvalue',
		) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_protected', $response, 403 );
		$this->assertEquals( array( 'testvalue' ), get_comment_meta( $this->approved_id, 'testkey' ) );
		$this->assertEmpty( get_comment_meta( $this->approved_id, '_testnewkey' ) );
	}
	public function test_update_meta_invalid_key() {
		wp_set_current_user( $this->admin_id );
		$meta_id = add_comment_meta( $this->approved_id, 'testkey', 'testvalue' );
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/comments/%d/meta/%d', $this->approved_id, $meta_id ) );
		$request->set_body_params( array(
			'key'   => false,
			'value' => 'testnewvalue',
		) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_invalid_key', $response, 400 );
		$this->assertEquals( array( 'testvalue' ), get_comment_meta( $this->approved_id, 'testkey' ) );
	}
	/**
	 * Ensure slashes aren't added
	 */
	public function test_update_meta_unslashed() {
		wp_set_current_user( $this->admin_id );
		$meta_id = add_comment_meta( $this->approved_id, 'testkey', 'testvalue' );
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/comments/%d/meta/%d', $this->approved_id, $meta_id ) );
		$request->set_body_params( array(
			'key'   => 'testkey',
			'value' => "test unslashed ' value",
		) );
		$this->server->dispatch( $request );
		$meta = get_comment_meta( $this->approved_id, 'testkey', false );
		$this->assertNotEmpty( $meta );
		$this->assertCount( 1, $meta );
		$this->assertEquals( "test unslashed ' value", $meta[0] );
	}
	/**
	 * Ensure slashes aren't touched in data
	 */
	public function test_update_meta_slashed() {
		wp_set_current_user( $this->admin_id );
		$meta_id = add_comment_meta( $this->approved_id, 'testkey', 'testvalue' );
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/comments/%d/meta/%d', $this->approved_id, $meta_id ) );
		$request->set_body_params( array(
			'key'   => 'testkey',
			'value' => "test slashed \\' value",
		) );
		$this->server->dispatch( $request );
		$meta = get_comment_meta( $this->approved_id, 'testkey', false );
		$this->assertNotEmpty( $meta );
		$this->assertCount( 1, $meta );
		$this->assertEquals( "test slashed \\' value", $meta[0] );
	}
	public function test_delete_item() {
		wp_set_current_user( $this->admin_id );
		$meta_id = add_comment_meta( $this->approved_id, 'testkey', 'testvalue' );
		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/comments/%d/meta/%d', $this->approved_id, $meta_id ) );
		$request['force'] = true;
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'message', $data );
		$this->assertNotEmpty( $data['message'] );
		$meta = get_comment_meta( $this->approved_id, 'testkey', false );
		$this->assertEmpty( $meta );
	}
	public function test_delete_item_no_trash() {
		wp_set_current_user( $this->admin_id );
		$meta_id = add_comment_meta( $this->approved_id, 'testkey', 'testvalue' );
		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/comments/%d/meta/%d', $this->approved_id, $meta_id ) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_trash_not_supported', $response, 501 );
		// Ensure the meta still exists
		$meta = get_metadata_by_mid( 'comment', $meta_id );
		$this->assertNotEmpty( $meta );
	}
	public function test_delete_item_no_comment_id() {
		wp_set_current_user( $this->admin_id );
		$meta_id = add_comment_meta( $this->approved_id, 'testkey', 'testvalue' );
		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/comments/%d/meta/%d', $this->approved_id, $meta_id ) );
		$request['force'] = true;
		$request['parent_id'] = 0;
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_comment_invalid_id', $response, 404 );
		$this->assertEquals( array( 'testvalue' ), get_comment_meta( $this->approved_id, 'testkey', false ) );
	}
	public function test_delete_item_invalid_comment_id() {
		wp_set_current_user( $this->admin_id );
		$meta_id = add_comment_meta( $this->approved_id, 'testkey', 'testvalue' );
		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/comments/%d/meta/%d', $this->approved_id, $meta_id ) );
		$request['force'] = true;
		$request['parent_id'] = -1;
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_comment_invalid_id', $response, 404 );
		$this->assertEquals( array( 'testvalue' ), get_comment_meta( $this->approved_id, 'testkey', false ) );
	}
	public function test_delete_item_no_meta_id() {
		wp_set_current_user( $this->admin_id );
		$meta_id = add_comment_meta( $this->approved_id, 'testkey', 'testvalue' );
		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/comments/%d/meta/%d', $this->approved_id, $meta_id ) );
		$request['force'] = true;
		$request['id'] = 0;
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_invalid_id', $response, 404 );
		$this->assertEquals( array( 'testvalue' ), get_comment_meta( $this->approved_id, 'testkey', false ) );
	}
	public function test_delete_item_invalid_meta_id() {
		wp_set_current_user( $this->admin_id );
		$meta_id = add_comment_meta( $this->approved_id, 'testkey', 'testvalue' );
		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/comments/%d/meta/%d', $this->approved_id, $meta_id ) );
		$request['force'] = true;
		$request['id'] = -1;
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_invalid_id', $response, 404 );
		$this->assertEquals( array( 'testvalue' ), get_comment_meta( $this->approved_id, 'testkey', false ) );
	}
	public function test_delete_item_unauthenticated() {
		$meta_id = add_comment_meta( $this->approved_id, 'testkey', 'testvalue' );
		wp_set_current_user( 0 );
		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/comments/%d/meta/%d', $this->approved_id, $meta_id ) );
		$request['force'] = true;
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_forbidden', $response, 401 );
		$this->assertEquals( array( 'testvalue' ), get_comment_meta( $this->approved_id, 'testkey', false ) );
	}
	public function test_delete_item_wrong_comment() {
		wp_set_current_user( $this->admin_id );
		$meta_id = add_comment_meta( $this->approved_id, 'testkey', 'testvalue' );
		$meta_id_two = add_comment_meta( $this->hold_id, 'testkey', 'testvalue' );
		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/comments/%d/meta/%d', $this->hold_id, $meta_id ) );
		$request['force'] = true;
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_comment_mismatch', $response, 400 );
		$this->assertEquals( array( 'testvalue' ), get_comment_meta( $this->hold_id, 'testkey' ) );
		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/comments/%d/meta/%d', $this->approved_id, $meta_id_two ) );
		$request['force'] = true;
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_comment_mismatch', $response, 400 );
		$this->assertEquals( array( 'testvalue' ), get_comment_meta( $this->approved_id, 'testkey' ) );
	}
	public function test_delete_item_serialized_array() {
		wp_set_current_user( $this->admin_id );
		$value = array( 'testvalue1', 'testvalue2' );
		$meta_id = add_comment_meta( $this->approved_id, 'testkey', $value );
		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/comments/%d/meta/%d', $this->approved_id, $meta_id ) );
		$request['force'] = true;
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_invalid_action', $response, 400 );
		$this->assertEquals( array( $value ), get_comment_meta( $this->approved_id, 'testkey' ) );
	}
	public function test_delete_item_serialized_object() {
		wp_set_current_user( $this->admin_id );
		$value = (object) array( 'testkey1' => 'testvalue1', 'testkey2' => 'testvalue2' );
		$meta_id = add_comment_meta( $this->approved_id, 'testkey', $value );
		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/comments/%d/meta/%d', $this->approved_id, $meta_id ) );
		$request['force'] = true;
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_invalid_action', $response, 400 );
		$this->assertEquals( array( $value ), get_comment_meta( $this->approved_id, 'testkey' ) );
	}
	public function test_delete_item_serialized_string() {
		wp_set_current_user( $this->admin_id );
		$value = serialize( array( 'testkey1' => 'testvalue1', 'testkey2' => 'testvalue2' ) );
		$meta_id = add_comment_meta( $this->approved_id, 'testkey', $value );
		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/comments/%d/meta/%d', $this->approved_id, $meta_id ) );
		$request['force'] = true;
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_invalid_action', $response, 400 );
		$this->assertEquals( array( $value ), get_comment_meta( $this->approved_id, 'testkey' ) );
	}
	public function test_delete_item_protected() {
		wp_set_current_user( $this->admin_id );
		$meta_id = add_comment_meta( $this->approved_id, '_testkey', 'testvalue' );
		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/comments/%d/meta/%d', $this->approved_id, $meta_id ) );
		$request['force'] = true;
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_protected', $response, 403 );
		$this->assertEquals( array( 'testvalue' ), get_comment_meta( $this->approved_id, '_testkey' ) );
	}
	public function test_prepare_item() {
		wp_set_current_user( $this->admin_id );
		$meta_id = add_comment_meta( $this->approved_id, 'testkey', 'testvalue' );
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/comments/%d/meta/%d', $this->approved_id, $meta_id ) );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertEquals( $meta_id, $data['id'] );
		$this->assertEquals( 'testkey', $data['key'] );
		$this->assertEquals( 'testvalue', $data['value'] );
	}
	public function test_get_item_schema() {
		// No op
	}
}
