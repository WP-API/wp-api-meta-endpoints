# WP REST API - Meta Support

[![Build Status](https://travis-ci.org/WP-API/wp-api-meta-endpoints.svg?branch=master)](https://travis-ci.org/WP-API/wp-api-meta-endpoints)

Just like other parts of WordPress, the REST API fully supports custom meta on posts, comments, terms and users. Similar to custom post types and taxonomies, only meta that opts-in to API support is available through the REST API.

This plugin is a feature plugin for the main API plugin, and is expected to be integrated in the near future.


## Meta registration

For your meta fields to be exposed in the REST API, you need to register them. WordPress includes a `register_meta` function which is not usually required to get/set fields, but is required for API support.

To register your field, simply call `register_meta` and set the `show_in_rest` flag to true.

```php
register_meta( 'post', 'field_name', array(
	'show_in_rest' => true,
));
```

Note: `register_meta` must be called separately for each meta key.


### Meta Options

`register_meta` is part of WordPress core, but isn't heavily used in most parts of core, so you may not have seen it before. Here are the options relevant to the REST API:

* `show_in_rest` - Should the field be exposed in the API? `true` for default behaviour, or an array of options to override defaults (see below).
* `description` - Human-readable description of what the field is for. The API exposes this in the schema.
* `single` - Is this field singular? WordPress allows multiple values per key on a single object, but the API needs to know whether you use this functionality.
* `sanitize_callback` - Callback to sanitize the value before it's stored in the database. [See the `"sanitize_{$object_type}_meta_{$meta_key}"` filter.](https://developer.wordpress.org/reference/hooks/sanitize_object_type_meta_meta_key/)
* `auth_callback` - Callback to determine whether the user can **write** to the field. [See the `"auth_post_{$post_type}_meta_{$meta_key}"` filter.](https://developer.wordpress.org/reference/hooks/auth_post_post_type_meta_meta_key/)


### API=Specific Options

The `show_in_rest` parameter defaults to `false`, but can be set to `true` to enable API support. Out of the box, the API makes some assumptions about your meta field, but you can override these by setting `show_in_rest` to an options array instead:

```php
register_meta( 'post', 'field_name', array(
	'show_in_rest' => array(
		'name' => 'fieldname'
	)
));
```

The options you can set are:

* `name` - The key exposed via the REST API. For example, if your meta key starts with an underscore, you may want to remove this for the API.
* `schema` - The JSON Schema options for the field. Exposed via OPTIONS requests to the post. This is generated automatically based on other parameters, but can be specified manually if you'd like to override it.


## Using Meta over the API

Meta is added to existing resources as a new `meta` field. For example, to get the meta for a single post, fetch the post at `/wp/v2/posts/{id}`, then look in the `meta` field. This is a key-value map for the registered fields.

To update a field, simply send back a new value for it.

Setting a field to `null` will cause the value to be removed from the database. If a field has a default value set, this essentially "resets" the value to the default (although no default will be stored in the database).

For meta fields which can hold multiple values, this field will be a JSON list. Adding or removing items from the list will create or remove the corresponding values in the database.
