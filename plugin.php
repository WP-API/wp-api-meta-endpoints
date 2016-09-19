<?php
/**
 * Plugin Name: WP REST API - Meta Endpoints
 * Description: Meta endpoints for the WP REST API
 * Author: WP REST API Team
 * Author URI: http://wp-api.org/
 * Version: 0.2.0
 * Plugin URI: https://github.com/WP-API/wp-api-meta-endpoints
 * License: GPL2+
 */

function meta_rest_api_init() {
	require_once dirname( __FILE__ ) . '/lib/class-wp-rest-meta-fields.php';
	require_once dirname( __FILE__ ) . '/lib/class-wp-rest-post-meta-fields.php';
	require_once dirname( __FILE__ ) . '/lib/class-wp-rest-comment-meta-fields.php';
	require_once dirname( __FILE__ ) . '/lib/class-wp-rest-term-meta-fields.php';
	require_once dirname( __FILE__ ) . '/lib/class-wp-rest-user-meta-fields.php';


	foreach ( get_post_types( array( 'show_in_rest' => true ), 'objects' ) as $post_type ) {
		if ( post_type_supports( $post_type->name, 'custom-fields' ) ) {
			$post_meta = new WP_REST_Post_Meta_Fields( $post_type->name );
			$post_meta->register_field();
		}
	}

	foreach ( get_taxonomies( array( 'show_in_rest' => true ), 'objects' ) as $taxonomy ) {
		$terms_meta = new WP_REST_Term_Meta_Fields( $taxonomy->name );
		$terms_meta->register_field();
	}

	$user_meta = new WP_REST_User_Meta_Fields();
	$user_meta->register_field();

	$comment_meta = new WP_REST_Comment_Meta_Fields();
	$comment_meta->register_field();
}

add_action( 'rest_api_init', 'meta_rest_api_init', 11 );
