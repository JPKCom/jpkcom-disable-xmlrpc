<?php
/*
Plugin Name: JPKCom Disable XML-RPC
Plugin URI: https://github.com/JPKCom/jpkcom-disable-xmlrpc
Description: Globally disable XML-RPC.
Version: 1.0.9
Author: Jean Pierre Kolb <jpk@jpkc.com>
Author URI: https://www.jpkc.com
Contributors: JPKCom
Tags: Security, XML, RPC, API, Plugin
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 8.3
Stable tag: 1.0.9
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
    define( 'JPKCOM_DISABLE_XMLRPC_VERSION', '1.0.9' );
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

if ( ! function_exists( function: 'jpkcom_disable_xmlrpc_is_xmlrpc_request' ) ) {
    /**
     * Whether the current request is being served by xmlrpc.php.
     *
     * `XMLRPC_REQUEST` is defined at the top of `xmlrpc.php`, before
     * `wp-load.php` runs, so it is set for every request that reaches the
     * endpoint. Up to 1.0.8 this was derived from
     * `basename( $_SERVER['SCRIPT_FILENAME'] )` instead, which depends on how a
     * given SAPI populates that variable, and the value was pushed through
     * `sanitize_text_field()` first - a function that strips tags and `%xx`
     * sequences and therefore alters a filesystem path rather than securing it.
     *
     * @since 1.0.9
     *
     * @return bool True when serving an XML-RPC request.
     */
    function jpkcom_disable_xmlrpc_is_xmlrpc_request(): bool {
        return defined( constant_name: 'XMLRPC_REQUEST' ) && (bool) constant( 'XMLRPC_REQUEST' );
    }
}

if ( ! function_exists( function: 'jpkcom_disable_xmlrpc_fault_xml' ) ) {
    /**
     * Build the XML-RPC fault sent in place of a served request.
     *
     * A fault response keeps the refusal machine-readable for XML-RPC clients,
     * which cannot parse the HTML error page `wp_die()` would normally produce.
     *
     * @since 1.0.9
     *
     * @return string A complete XML-RPC `methodResponse` fault document.
     */
    function jpkcom_disable_xmlrpc_fault_xml(): string {
        return '<?xml version="1.0"?>'
            . '<methodResponse><fault><value><struct>'
            . '<member><name>faultCode</name><value><int>403</int></value></member>'
            . '<member><name>faultString</name><value><string>'
            . esc_html__( 'XML-RPC is disabled.', 'jpkcom-disable-xmlrpc' )
            . '</string></value></member>'
            . '</struct></value></fault></methodResponse>';
    }
}

/**
 * Disable the XML-RPC interface and strip all of its methods.
 *
 * PHP_INT_MAX so that a theme or plugin hooking in later cannot re-enable them.
 *
 * @since 1.0.0
 */
add_filter( 'xmlrpc_enabled', '__return_false', PHP_INT_MAX );
add_filter( 'xmlrpc_methods', '__return_empty_array', PHP_INT_MAX );

/**
 * Stop advertising an endpoint that is refused.
 *
 * WordPress sends `X-Pingback: <site>/xmlrpc.php` on singular views
 * (`WP::send_headers()`) and themes emit `<link rel="pingback">` in the head;
 * both are gated on `pings_open()`. Without this filter the site kept
 * publishing the address of an endpoint it will not serve. Pingbacks are
 * delivered through XML-RPC's `pingback.ping`, so with XML-RPC disabled they
 * cannot be received anyway.
 *
 * Side effect worth knowing: `pings_open()` also governs `wp-trackback.php`, so
 * trackbacks are refused as well. Trackbacks are a separate mechanism from
 * XML-RPC; this is a deliberate choice, on the assumption that a site turning
 * off XML-RPC does not want trackbacks either.
 *
 * @since 1.0.9
 */
add_filter( 'pings_open', '__return_false', PHP_INT_MAX, 2 );

/**
 * Reject XML-RPC requests with a 403 and an XML-RPC fault.
 *
 * The response is written here instead of being left to `wp_die()`. With
 * `XMLRPC_REQUEST` defined, `wp_die()` dispatches to
 * `_xmlrpc_wp_die_handler()`, which only emits anything when the global
 * `$wp_xmlrpc_server` already exists - and `xmlrpc.php` creates that object
 * *after* `init`. That handler also never calls `status_header()`. Up to 1.0.8
 * the endpoint therefore answered with a bare HTTP 200 and an empty body while
 * the documentation promised a 403.
 *
 * Runs at priority 0, before the updater bootstrap above, so a refused request
 * does no further work.
 *
 * @since 1.0.0
 * @since 1.0.9 Sends an explicit 403 status and an XML-RPC fault body.
 *
 * @return void
 */
add_action( 'init', static function (): void {
    if ( ! jpkcom_disable_xmlrpc_is_xmlrpc_request() ) {
        return;
    }

    nocache_headers();
    status_header( 403 );

    if ( ! headers_sent() ) {
        header( 'Content-Type: text/xml; charset=' . get_option( 'blog_charset' ) );
    }

    echo jpkcom_disable_xmlrpc_fault_xml(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from literals and one esc_html__() string.

    exit;
}, 0 );

/**
 * Last resort: block instantiation of the XML-RPC server class.
 *
 * Unreachable while the guard above is in place, and kept so that removing or
 * short-circuiting it cannot silently re-enable the endpoint. `status_header()`
 * is called explicitly here for the same reason as above.
 *
 * @since 1.0.0
 *
 * @param string $class The XML-RPC server class name.
 * @return never
 */
add_filter( 'wp_xmlrpc_server_class', static function ( string $class ): never {
    status_header( 403 );

    wp_die(
        esc_html__( 'XML-RPC is disabled.', 'jpkcom-disable-xmlrpc' ),
        esc_html__( 'Error', 'jpkcom-disable-xmlrpc' ),
        array( 'response' => 403 )
    );
} );
