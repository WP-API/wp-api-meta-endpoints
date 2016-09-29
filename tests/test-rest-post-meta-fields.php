<?php

/**
 * Unit tests covering WP_REST_Posts meta functionality.
 *
 * @package WordPress
 * @subpackage JSON API
 */
class WP_Test_REST_Post_Meta_Fields extends WP_Test_REST_TestCase {
	public function setUp() {
		parent::setUp();

		/** @var WP_REST_Server $wp_rest_server */
		global $wp_rest_server;
		$this->server = $wp_rest_server = new WP_Test_Spy_REST_Server;
		do_action( 'rest_api_init' );

		register_meta( 'post', 'test_single', array(
			'show_in_rest' => true,
			'single' => true,
		));
		register_meta( 'post', 'test_multi', array(
			'show_in_rest' => true,
			'single' => false,
		));

		$this->post_id = $this->factory->post->create();
	}

	protected function grant_write_permission() {
		// Ensure we have write permission.
		$user = $this->factory->user->create( array(
			'role' => 'editor',
		));
		wp_set_current_user( $user );
	}

	public function test_get_value() {
		add_post_meta( $this->post_id, 'test_single', 'testvalue' );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d', $this->post_id ) );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'meta', $data );

		$meta = (array) $data['meta'];
		$this->assertArrayHasKey( 'test_single', $meta );
		$this->assertEquals( 'testvalue', $meta['test_single'] );
	}

	/**
	 * @depends test_get_value
	 */
	public function test_get_multi_value() {
		add_post_meta( $this->post_id, 'test_multi', 'value1' );
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d', $this->post_id ) );

		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$meta = (array) $data['meta'];
		$this->assertArrayHasKey( 'test_multi', $meta );
		$this->assertInternalType( 'array', $meta['test_multi'] );
		$this->assertContains( 'value1', $meta['test_multi'] );

		// Check after an update.
		add_post_meta( $this->post_id, 'test_multi', 'value2' );

		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$meta = (array) $data['meta'];
		$this->assertContains( 'value1', $meta['test_multi'] );
		$this->assertContains( 'value2', $meta['test_multi'] );
	}

	/**
	 * @depends test_get_value
	 */
	public function test_get_unregistered() {
		add_post_meta( $this->post_id, 'test_unregistered', 'value1' );
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d', $this->post_id ) );

		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$meta = (array) $data['meta'];
		$this->assertArrayNotHasKey( 'test_unregistered', $meta );
	}

	/**
	 * @depends test_get_value
	 */
	public function test_set_value() {
		// Ensure no data exists currently.
		$values = get_post_meta( $this->post_id, 'test_single', false );
		$this->assertEmpty( $values );

		$this->grant_write_permission();

		$data = array(
			'meta' => array(
				'test_single' => 'test_value',
			),
		);
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/posts/%d', $this->post_id ) );
		$request->set_body_params( $data );

		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$meta = get_post_meta( $this->post_id, 'test_single', false );
		$this->assertNotEmpty( $meta );
		$this->assertCount( 1, $meta );
		$this->assertEquals( 'test_value', $meta[0] );

		$data = $response->get_data();
		$meta = (array) $data['meta'];
		$this->assertArrayHasKey( 'test_single', $meta );
		$this->assertEquals( 'test_value', $meta['test_single'] );
	}

	/**
	 * @depends test_set_value
	 */
	public function test_set_value_unauthenticated() {
		$data = array(
			'meta' => array(
				'test_single' => 'test_value',
			),
		);

		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/posts/%d', $this->post_id ) );
		$request->set_body_params( $data );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_edit', $response, 401 );

		// Check that the value wasn't actually updated.
		$this->assertEmpty( get_post_meta( $this->post_id, 'test_single', false ) );
	}

	public function test_set_value_multiple() {
		// Ensure no data exists currently.
		$values = get_post_meta( $this->post_id, 'test_multi', false );
		$this->assertEmpty( $values );

		$this->grant_write_permission();

		$data = array(
			'meta' => array(
				'test_multi' => array( 'val1' ),
			),
		);
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/posts/%d', $this->post_id ) );
		$request->set_body_params( $data );

		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$meta = get_post_meta( $this->post_id, 'test_multi', false );
		$this->assertNotEmpty( $meta );
		$this->assertCount( 1, $meta );
		$this->assertEquals( 'val1', $meta[0] );

		// Add another value.
		$data = array(
			'meta' => array(
				'test_multi' => array( 'val1', 'val2' ),
			),
		);
		$request->set_body_params( $data );

		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$meta = get_post_meta( $this->post_id, 'test_multi', false );
		$this->assertNotEmpty( $meta );
		$this->assertCount( 2, $meta );
		$this->assertContains( 'val1', $meta );
		$this->assertContains( 'val2', $meta );
	}

	public function test_delete_value() {
		add_post_meta( $this->post_id, 'test_single', 'val1' );
		$current = get_post_meta( $this->post_id, 'test_single', true );
		$this->assertEquals( 'val1', $current );

		$this->grant_write_permission();

		$data = array(
			'meta' => array(
				'test_single' => null,
			),
		);
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/posts/%d', $this->post_id ) );
		$request->set_body_params( $data );

		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$meta = get_post_meta( $this->post_id, 'test_single', false );
		$this->assertEmpty( $meta );
	}
}
