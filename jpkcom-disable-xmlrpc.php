<?php
/*
Plugin Name: JPKCom Disable XML-RPC
Plugin URI: https://github.com/JPKCom/jpkcom-disable-xmlrpc
Description: Globally disable XML-RPC.
Version: 1.0.1
Author: Jean Pierre Kolb <jpk@jpkc.com>
Author URI: https://www.jpkc.com
Contributors: JPKCom
Tags: Security, XML, RPC, API, Plugin
Requires at least: 6.8
Tested up to: 6.8
Requires PHP: 8.3
Stable tag: 1.0.1
License: GPL-2.0+
License URI: http://www.gnu.org/licenses/gpl-2.0.txt
GitHub Plugin URI: JPKCom/jpkcom-disable-xmlrpc
Primary Branch: main
*/

if ( ! defined( constant_name: 'WPINC' ) ) {
  die;
}

add_filter( 'xmlrpc_enabled', '__return_false', 1 );
add_filter( 'xmlrpc_methods', '__return_empty_array', 1 );

add_filter( 'wp_xmlrpc_server_class', function( $class ): void {
    wp_die( 'XML-RPC is disabled.', 'Error', array( 'response' => 403 ) );
});

add_action( 'init', function(): void {
    if ( isset( $_SERVER['SCRIPT_FILENAME'] ) && basename( path: $_SERVER['SCRIPT_FILENAME'] ) === 'xmlrpc.php' ) {
        wp_die( 'XML-RPC is disabled.', 'Error', array( 'response' => 403 ) );
    }
});
