<?php
/**
 * Plugin Name: WP REST API - Meta Endpoints
 * Description: Meta endpoints for the WP REST API
 * Author: WP REST API Team
 * Author URI: http://wp-api.org
 * Version: 0.1.0
 * Plugin URI: https://github.com/WP-API/wp-api-meta-endpoints
 * License: GPL2+
 */

function meta_rest_api_init() {

	if ( class_exists( 'WP_REST_Controller' )
		&& ! class_exists( 'WP_REST_Meta_Controller' ) ) {
		require_once dirname( __FILE__ ) . '/lib/class-wp-rest-meta-controller.php';
	}

	if ( class_exists( 'WP_REST_Controller' )
		&& ! class_exists( 'WP_REST_Meta_Posts_Controller' ) ) {
		require_once dirname( __FILE__ ) . '/lib/class-wp-rest-meta-posts-controller.php';
	}

	if ( class_exists( 'WP_REST_Controller' )
		&& ! class_exists( 'WP_REST_Meta_Users_Controller' ) ) {
		require_once dirname( __FILE__ ) . '/lib/class-wp-rest-meta-users-controller.php';
	}

	if ( class_exists( 'WP_REST_Controller' )
		&& ! class_exists( 'WP_REST_Meta_Comments_Controller' ) ) {
		require_once dirname( __FILE__ ) . '/lib/class-wp-rest-meta-comments-controller.php';
	}

	if ( class_exists( 'WP_REST_Controller' )
		&& ! class_exists( 'WP_REST_Meta_Terms_Controller' ) ) {
		require_once dirname( __FILE__ ) . '/lib/class-wp-rest-meta-terms-controller.php';
	}

	foreach ( get_post_types( array( 'show_in_rest' => true ), 'objects' ) as $post_type ) {
		if ( post_type_supports( $post_type->name, 'custom-fields' ) ) {
			$meta_controller = new WP_REST_Meta_Posts_Controller( $post_type->name );
			$meta_controller->register_routes();
		}
	}

	foreach ( get_taxonomies( array( 'show_in_rest' => true ), 'objects' ) as $taxonomy ) {
		$terms_meta_controller = new WP_REST_Meta_Terms_Controller( $taxonomy->name );
		$terms_meta_controller->register_routes();
	}

	$user_meta_controller = new WP_REST_Meta_Users_Controller();
	$user_meta_controller->register_routes();

	$comment_meta_controller = new WP_REST_Meta_Comments_Controller();
	$comment_meta_controller->register_routes();

}

add_action( 'rest_api_init', 'meta_rest_api_init', 11 );
