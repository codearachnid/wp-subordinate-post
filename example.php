<?php

/*
Plugin Name: Subordinate Post Type
Plugin URI: 
Description: This is a demo setup of using subordinate post types
Version: 1.0
Author: Timothy Wood (@codearachnid)
Author URI: http://www.codearachnid.com	
Author Email: tim@imaginesimplicity.com
License: GPLv2 or later

Notes:

License:

  Copyright 2011 Imagine Simplicity (tim@imaginesimplicity.com)

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License, version 2, as 
  published by the Free Software Foundation.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
  
*/

if ( !defined('ABSPATH') )
  die('-1');

require_once 'wp-subordinate-post.php';

function codex_custom_init() {
  $labels = array(
    'name' => _x('Books', 'post type general name'),
    'singular_name' => _x('Book', 'post type singular name'),
    'add_new' => _x('Add New', 'book'),
    'add_new_item' => __('Add New Book'),
    'edit_item' => __('Edit Book'),
    'new_item' => __('New Book'),
    'all_items' => __('All Books'),
    'view_item' => __('View Book'),
    'search_items' => __('Search Books'),
    'not_found' =>  __('No books found'),
    'not_found_in_trash' => __('No books found in Trash'), 
    'parent_item_colon' => '',
    'menu_name' => __('Books')

  );
  $args = array(
    'labels' => $labels,
    'public' => true,
    'publicly_queryable' => true,
    'show_ui' => true, 
    'show_in_menu' => true, 
    'query_var' => true,
    'rewrite' => true,
    'capability_type' => 'post',
    'has_archive' => true, 
    'hierarchical' => true,
    'menu_position' => null,
    'parent' => 'page',
    'supports' => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments', 'page-attributes' )
  ); 
  register_post_type('book',$args);

  $labels = array(
    'name' => _x('Parent Posts', 'post type general name'),
    'singular_name' => _x('Parent Post', 'post type singular name'),
    'add_new' => _x('Add New', 'parent_type'),
    'add_new_item' => __('Add New Book'),
    'edit_item' => __('Edit Parent Post'),
    'new_item' => __('New Parent Post'),
    'all_items' => __('All Parent Post'),
    'view_item' => __('View Parent Post'),
    'search_items' => __('Search Parent Posts'),
    'not_found' =>  __('No parent posts found'),
    'not_found_in_trash' => __('No parent posts found in Trash'), 
    'parent_item_colon' => '',
    'menu_name' => __('Parent Post')

  );
  $args = array(
    'labels' => $labels,
    'public' => true,
    'publicly_queryable' => true,
    'show_ui' => true, 
    'show_in_menu' => true, 
    'query_var' => true,
    'rewrite' => true,
    'capability_type' => 'post',
    'has_archive' => true, 
    'hierarchical' => true,
    'menu_position' => null,
    'children' => 'test_child_type', // can also take array array('test_child_type')
    'supports' => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments', 'page-attributes' )
  ); 
  register_post_type('test_parent_type',$args);


  $labels = array(
    'name' => _x('Child Posts', 'post type general name'),
    'singular_name' => _x('Child Post', 'post type singular name'),
    'add_new' => _x('Add New', 'child_post'),
    'add_new_item' => __('Add New Child Post'),
    'edit_item' => __('Edit Child Post'),
    'new_item' => __('New Child Post'),
    'all_items' => __('All Child Post'),
    'view_item' => __('View Child Post'),
    'search_items' => __('Search Child Posts'),
    'not_found' =>  __('No child posts found'),
    'not_found_in_trash' => __('No child posts found in Trash'), 
    'parent_item_colon' => '',
    'menu_name' => __('Child Post')

  );
  $args = array(
    'labels' => $labels,
    'public' => true,
    'publicly_queryable' => true,
    'show_ui' => true, 
    'show_in_menu' => true, 
    'query_var' => true,
    'rewrite' => true,
    'capability_type' => 'post',
    'has_archive' => true, 
    'hierarchical' => true,
    'menu_position' => null,
    'supports' => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments', 'page-attributes' )
  ); 
  register_post_type('test_child_type',$args);
}
add_action( 'init', 'codex_custom_init' );