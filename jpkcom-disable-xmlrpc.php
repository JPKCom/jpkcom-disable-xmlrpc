<?php
/*
Plugin Name: JPKCom Disable XML-RPC
Plugin URI: https://github.com/JPKCom/jpkcom-disable-xmlrpc
Description: Globally disable XML-RPC.
Version: 1.0.0
Author: Jean Pierre Kolb <jpk@jpkc.com>
Author URI: https://www.jpkc.com
Contributors: JPKCom
Tags: Security, XML, RPC, API, Plugin
Requires at least: 6.8
Tested up to: 6.8
Requires PHP: 8.3
Stable tag: 1.0.0
License: GPL-2.0+
License URI: http://www.gnu.org/licenses/gpl-2.0.txt
GitHub Plugin URI: JPKCom/jpkcom-disable-xmlrpc
Primary Branch: main
*/

if ( ! defined( constant_name: 'WPINC' ) ) {
  die;
}

add_filter( 'xmlrpc_enabled', '__return_false', 1 );

add_action( 'init', function(): void {
    if ( strpos( haystack: $_SERVER['REQUEST_URI'], needle: 'xmlrpc.php' ) !== false ) {
        wp_die( 'XML-RPC is disabled.', 'Error', array( 'response' => 403 ) );
    }
});
