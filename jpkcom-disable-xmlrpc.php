<?php
/*
Plugin Name: JPKCom Disable XML-RPC
Plugin URI: https://github.com/JPKCom/jpkcom-disable-xmlrpc
Description: Globally disable XML-RPC.
Version: 1.0.3
Author: Jean Pierre Kolb <jpk@jpkc.com>
Author URI: https://www.jpkc.com
Contributors: JPKCom
Tags: Security, XML, RPC, API, Plugin
Requires at least: 6.9
Tested up to: 7.0
Requires PHP: 8.3
Stable tag: 1.0.3
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

declare(strict_types=1);

if ( ! defined( constant_name: 'WPINC' ) ) {
  die;
}


/**
 * Plugin Constants
 *
 * @since 1.0.2
 */
if ( ! defined( 'JPKCOM_DISABLE_XMLRPC_VERSION' ) ) {
    define( 'JPKCOM_DISABLE_XMLRPC_VERSION', '1.0.3' );
}


/**
 * Initialize Plugin Updater
 *
 * Loads and initializes the GitHub-based plugin updater with SHA256 checksum verification.
 *
 * @since 1.0.2
 *
 * @return void
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

/**
 * Disable the XML-RPC interface and strip all of its methods.
 *
 * @since 1.0.0
 */
add_filter( 'xmlrpc_enabled', '__return_false', 1 );
add_filter( 'xmlrpc_methods', '__return_empty_array', 1 );

/**
 * Block instantiation of the XML-RPC server class.
 *
 * @since 1.0.0
 *
 * @param string $class The XML-RPC server class name.
 * @return never
 */
add_filter( 'wp_xmlrpc_server_class', function( string $class ): never {
    wp_die( esc_html__( 'XML-RPC is disabled.', 'jpkcom-disable-xmlrpc' ), esc_html__( 'Error', 'jpkcom-disable-xmlrpc' ), array( 'response' => 403 ) );
});

/**
 * Reject direct requests to xmlrpc.php with a 403 response.
 *
 * @since 1.0.0
 *
 * @return void
 */
add_action( 'init', function(): void {
    if ( isset( $_SERVER['SCRIPT_FILENAME'] ) ) {
        $jpkcom_script = sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_FILENAME'] ) );
        if ( basename( path: $jpkcom_script ) === 'xmlrpc.php' ) {
            wp_die( esc_html__( 'XML-RPC is disabled.', 'jpkcom-disable-xmlrpc' ), esc_html__( 'Error', 'jpkcom-disable-xmlrpc' ), array( 'response' => 403 ) );
        }
    }
});
