<?php

/**
 * Manage a WordPress site's settings.
 */

abstract class WP_REST_Meta_Fields {
	/**
	 * Get the object type for meta.
	 *
	 * @return string One of 'post', 'comment', 'term', 'user', or anything else supported by `_get_meta_table()`
	 */
	abstract protected function get_meta_type();

	/**
	 * Get the object type for `register_rest_field`.
	 *
	 * @return string Custom post type, 'taxonomy', 'comment', or `user`
	 */
	abstract protected function get_rest_field_type();

	/**
	 * Register the meta field.
	 */
	public function register_field() {
		register_rest_field( $this->get_rest_field_type(), 'meta', array(
			'get_callback' => array( $this, 'get_value' ),
			'update_callback' => array( $this, 'update_value' ),
			'schema' => $this->get_field_schema(),
		));
	}

	/**
	 * Get the settings.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Error|array
	 */
	public function get_value( $data, $field_name, $request, $type ) {
		$options  = $this->get_registered_fields();
		$response = array();

		foreach ( $options as $name => $args ) {
			$all_values = get_metadata( $this->get_meta_type(), $data['id'], $name, false );
			if ( $args['single'] ) {
				if ( empty( $all_values ) ) {
					$value = $args['schema']['default'];
				} else {
					$value = $all_values[0];
				}
				$value = $this->prepare_value_for_response( $value, $name, $args );
			} else {
				$value = array();
				foreach ( $all_values as $row ) {
					$value[] = $this->prepare_value_for_response( $row, $name, $args );
				}
			}

			$response[ $name ] = $value;
		}

		return (object) $response;
	}

	/**
	 * Prepare value for response.
	 *
	 * This is required because some native types cannot be stored correctly in
	 * the database, such as booleans. We need to cast back to the relevant type
	 * before passing back to JSON.
	 *
	 * @param mixed $value Value to prepare.
	 * @param string $name Meta key.
	 * @param array $args Options for the field.
	 * @return mixed Prepared value.
	 */
	protected function prepare_value_for_response( $value, $name, $args ) {
		switch ( $args['schema']['type'] ) {
			case 'string':
				$value = strval( $value );
				break;
			case 'number':
				$value = floatval( $value );
				break;
			case 'boolean':
				$value = (bool) $value;
				break;
		}

		return $value;
	}

	/**
	 * Update settings for the settings object.
	 *
	 * @param  WP_REST_Request $request Full detail about the request.
	 * @return WP_Error|array
	 */
	public function update_value( $params, $data, $field_name, $request ) {
		$options = $this->get_registered_fields();

		foreach ( $options as $name => $args ) {
			if ( ! array_key_exists( $name, $params ) ) {
				continue;
			}

			// a null value means reset the option, which is essentially deleting it
			// from the database and then relying on the default value.
			if ( is_null( $params[ $name ] ) ) {
				$result = $this->delete_meta_value( $request['id'], $name );
			} elseif ( $args['single'] ) {
				$result = $this->update_meta_value( $request['id'], $name, $params[ $name ] );
			} else {
				$result = $this->update_multi_meta_value( $request['id'], $name, $params[ $name ] );
			}

			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		return null;
	}

	/**
	 * Delete meta value for an object.
	 *
	 * @param int $object Object ID the field belongs to.
	 * @param string $name Key for the field.
	 * @return bool|WP_Error True if meta field is deleted, error otherwise.
	 */
	protected function delete_meta_value( $object, $name ) {
		if ( ! current_user_can( 'delete_post_meta', $object, $name ) ) {
			return new WP_Error(
				'rest_cannot_delete',
				sprintf( __( 'You do not have permission to edit the %s custom field.' ), $name ),
				array( 'key' => $name, 'status' => rest_authorization_required_code() )
			);
		}

		if ( ! delete_metadata( $this->get_meta_type(), $object, wp_slash( $name ) ) ) {
			return new WP_Error(
				'rest_meta_database_error',
				__( 'Could not delete meta value from database.' ),
				array( 'key' => $name, 'status' => WP_HTTP::INTERNAL_SERVER_ERROR )
			);
		}

		return true;
	}

	/**
	 * Update multiple meta values for an object.
	 *
	 * Alters the list of values in the database to match the list of provided values.
	 *
	 * @param int $object Object ID.
	 * @param string $name Key for the custom field.
	 * @param array $values List of values to update to.
	 * @return bool|WP_Error True if meta fields are updated, error otherwise.
	 */
	protected function update_multi_meta_value( $object, $name, $values ) {
		if ( ! current_user_can( 'edit_post_meta', $object, $name ) ) {
			return new WP_Error(
				'rest_cannot_update',
				sprintf( __( 'You do not have permission to edit the %s custom field.' ), $name ),
				array( 'key' => $name, 'status' => rest_authorization_required_code() )
			);
		}

		$current = get_metadata( $this->get_meta_type(), $object, $name, false );
		$to_add = array_diff( $values, $current );
		$to_remove = array_diff( $current, $values );

		foreach ( $to_add as $value ) {
			if ( ! add_metadata( $this->get_meta_type(), $object, wp_slash( $name ), wp_slash( $value ) ) ) {
				return new WP_Error(
					'rest_meta_database_error',
					__( 'Could not update meta value in database.' ),
					array( 'key' => $name, 'status' => WP_HTTP::INTERNAL_SERVER_ERROR )
				);
			}
		}
		foreach ( $to_remove as $value ) {
			if ( ! delete_metadata( $this->get_meta_type(), $object, wp_slash( $name ), wp_slash( $value ) ) ) {
				return new WP_Error(
					'rest_meta_database_error',
					__( 'Could not update meta value in database.' ),
					array( 'key' => $name, 'status' => WP_HTTP::INTERNAL_SERVER_ERROR )
				);
			}
		}

		return true;
	}

	/**
	 * Update meta value for an object.
	 *
	 * @param int $object Object ID.
	 * @param string $name Key for the custom field.
	 * @return bool|WP_Error True if meta field is deleted, error otherwise.
	 */
	protected function update_meta_value( $object, $name, $value ) {
		if ( ! current_user_can( 'edit_post_meta', $object, $name ) ) {
			return new WP_Error(
				'rest_cannot_update',
				sprintf( __( 'You do not have permission to edit the %s custom field.' ), $name ),
				array( 'key' => $name, 'status' => rest_authorization_required_code() )
			);
		}

		if ( ! update_metadata( $this->get_meta_type(), $object, wp_slash( $name ), wp_slash( $value ) ) ) {
			return new WP_Error(
				'rest_meta_database_error',
				__( 'Could not update meta value in database.' ),
				array( 'key' => $name, 'status' => WP_HTTP::INTERNAL_SERVER_ERROR )
			);
		}

		return true;
	}

	/**
	 * Get all the registered options for the Settings API
	 *
	 * @return array
	 */
	protected function get_registered_fields() {
		$rest_options = array();

		foreach ( get_registered_meta_keys( $this->get_meta_type() ) as $name => $args ) {
			if ( empty( $args['show_in_rest'] ) ) {
				continue;
			}

			$rest_args = array();
			if ( is_array( $args['show_in_rest'] ) ) {
				$rest_args = $args['show_in_rest'];
			}

			$default_args = array(
				'name'   => $name,
				'single' => $args['single'],
				'schema' => array(),
			);
			$default_schema = array(
				'type'        => empty( $args['type'] ) ? null : $args['type'],
				'description' => empty( $args['description'] ) ? '' : $args['description'],
				'default'     => isset( $args['default'] ) ? $args['default'] : null,
			);
			$rest_args = array_merge( $default_args, $rest_args );
			$rest_args['schema'] = array_merge( $default_schema, $rest_args['schema'] );

			// skip over settings that don't have a defined type in the schema
			if ( empty( $rest_args['schema']['type'] ) ) {
				continue;
			}

			$rest_options[ $rest_args['name'] ] = $rest_args;
		}

		return $rest_options;
	}

	/**
	 * Get the site setting schema, conforming to JSON Schema.
	 *
	 * @return array
	 */
	public function get_field_schema() {
		$fields = $this->get_registered_fields();

		$schema = array(
			'description' => __( 'Post meta fields.' ),
			'type'        => 'object',
			'context'     => array( 'view', 'edit' ),
			'properties'  => array(),
		);

		foreach ( $fields as $key => $args ) {
			$schema['properties'][ $key ] = $args['schema'];
		}

		return $schema;
	}
}
