# WP REST API Meta Endpoints #

## Do not use this plugin :slightly_smiling_face:

These endpoints were created during the initial development of the WordPress REST API, and has subsequently been superseded by core support for registering meta on all object types (posts, terms, etc) using [`register_term_meta`](https://developer.wordpress.org/reference/functions/register_term_meta/) and [`register_post_meta`](https://developer.wordpress.org/reference/functions/register_post_meta/) (or for other object types like Users, the lower-level [`register_term_meta`](https://developer.wordpress.org/reference/functions/register_meta/)) functions in core.

All meta registered with the argument `"show_in_rest" => true` will display within the `meta` key of the resource to which it is registered. Once registered, you may pass updated values as a part of your POST or PUT responses to set those values on the API resources.

**Contributors:** rmccue, rachelbaker, danielbachhuber, joehoyle  
**Tags:** json, rest, api, rest-api  
**Requires at least:** 4.4  
**Tested up to:** 4.5-alpha  
**Stable tag:** 0.1.0  
**License:** GPLv2 or later  
**License URI:** http://www.gnu.org/licenses/gpl-2.0.html  

WP REST API companion plugin for post meta endpoints.

## Description ##

[![Build Status](https://travis-ci.org/WP-API/wp-api-meta-endpoints.svg?branch=master)](https://travis-ci.org/WP-API/wp-api-meta-endpoints)

WP REST API companion plugin for post meta endpoints.

## Changelog ##

### 0.1.0 (February 9, 2016) ###
* Initial release.
