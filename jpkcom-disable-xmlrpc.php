<?php
/*
Plugin Name: JPKCom Disable XML-RPC
Plugin URI: https://github.com/JPKCom/jpkcom-disable-xmlrpc
Description: Globally disable XML-RPC.
Version: 1.0.2
Author: Jean Pierre Kolb <jpk@jpkc.com>
Author URI: https://www.jpkc.com
Contributors: JPKCom
Tags: Security, XML, RPC, API, Plugin
Requires at least: 6.9
Tested up to: 7.0
Requires PHP: 8.3
Stable tag: 1.0.2
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

if ( ! defined( constant_name: 'WPINC' ) ) {
  die;
}


/**
 * Plugin Constants
 */
if ( ! defined( 'JPKCOM_DISABLE_XMLRPC_VERSION' ) ) {
    define( 'JPKCOM_DISABLE_XMLRPC_VERSION', '1.0.2' );
}


/**
 * Initialize Plugin Updater
 *
 * Loads and initializes the GitHub-based plugin updater with SHA256 checksum verification.
 */
add_action( 'init', static function (): void {
    $updater_file = plugin_dir_path( __FILE__ ) . 'includes/class-plugin-updater.php';

    if ( file_exists( $updater_file ) ) {
        require_once $updater_file;

        if ( class_exists( 'JPKComDisableXmlrpcGitUpdate\\JPKComGitPluginUpdater' ) ) {
            new \JPKComDisableXmlrpcGitUpdate\JPKComGitPluginUpdater(
                plugin_file: __FILE__,
                current_version: JPKCOM_DISABLE_XMLRPC_VERSION,
                manifest_url: 'https://jpkcom.github.io/jpkcom-disable-xmlrpc/plugin_jpkcom-disable-xmlrpc.json'
            );
        }
    }
}, 5 );

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
