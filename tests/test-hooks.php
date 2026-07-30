<?php
/**
 * Regression tests for the hook surface of jpkcom-disable-xmlrpc.
 *
 * Runs standalone (no WordPress): the WordPress functions the plugin file
 * touches at load time are stubbed, the plugin file is required, and the
 * decision helpers are then called directly.
 *
 * The `init` callback itself is deliberately not invoked - it terminates the
 * request. Its two decisions are covered through the helpers it calls.
 *
 * Every case below is red against 1.0.8.
 *
 * @package JPKCom_Disable_Xmlrpc
 * @since 1.0.9
 */

declare(strict_types=1);

if ( ! defined( constant_name: 'WPINC' ) ) {
    define( constant_name: 'WPINC', value: true );
}

/** Recorded hook registrations: $GLOBALS['jpkcom_hooks'][type][hook][] = [cb, priority]. */
$GLOBALS['jpkcom_hooks'] = array();

if ( ! function_exists( function: 'add_action' ) ) {
    function add_action( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): void {
        $GLOBALS['jpkcom_hooks']['action'][ $hook ][] = array( $callback, $priority );
    }
}

if ( ! function_exists( function: 'add_filter' ) ) {
    function add_filter( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): void {
        $GLOBALS['jpkcom_hooks']['filter'][ $hook ][] = array( $callback, $priority );
    }
}

if ( ! function_exists( function: 'plugin_dir_path' ) ) {
    function plugin_dir_path( string $file ): string {
        return dirname( path: $file ) . DIRECTORY_SEPARATOR;
    }
}

if ( ! function_exists( function: 'esc_html__' ) ) {
    function esc_html__( string $text, string $domain = 'default' ): string {
        return htmlspecialchars( string: $text, flags: ENT_QUOTES | ENT_SUBSTITUTE, encoding: 'UTF-8' );
    }
}

if ( ! function_exists( function: '__return_false' ) ) {
    function __return_false(): bool {
        return false;
    }
}

if ( ! function_exists( function: '__return_empty_array' ) ) {
    function __return_empty_array(): array {
        return array();
    }
}

require_once dirname( path: __DIR__ ) . '/jpkcom-disable-xmlrpc.php';

$failed = 0;
$passed = 0;

/**
 * Assert a condition and report it.
 *
 * @param string $name Case name.
 * @param bool   $ok   Whether the case passed.
 * @param string $note Extra detail printed on failure.
 * @return void
 */
function jpkcom_check( string $name, bool $ok, string $note = '' ): void {
    global $failed, $passed;

    if ( $ok ) {
        ++$passed;
        printf( "  ok   %s\n", $name );
        return;
    }

    ++$failed;
    printf( "  FAIL %s%s\n", $name, $note !== '' ? ' -- ' . $note : '' );
}

/**
 * Fetch the registered callbacks for a hook.
 *
 * @param string $type action|filter
 * @param string $hook Hook name.
 * @return array<int,array{0:callable,1:int}>
 */
function jpkcom_hooked( string $type, string $hook ): array {
    return $GLOBALS['jpkcom_hooks'][ $type ][ $hook ] ?? array();
}

echo "jpkcom-disable-xmlrpc: hook regressions\n";

/* 1.0.8 used priority 1, which a later filter could override. */
foreach ( array( 'xmlrpc_enabled', 'xmlrpc_methods' ) as $hook ) {
    $entries = jpkcom_hooked( 'filter', $hook );
    jpkcom_check(
        sprintf( '%s is filtered at PHP_INT_MAX', $hook ),
        $entries !== array() && $entries[0][1] === PHP_INT_MAX,
        $entries === array() ? 'not registered' : sprintf( 'priority %d', $entries[0][1] )
    );
}

/*
 * 1.0.8 did not touch pings_open, so core kept sending
 * X-Pingback: <site>/xmlrpc.php on singular views and themes kept emitting
 * <link rel="pingback"> - the site advertised an endpoint it refuses to serve.
 */
$pings = jpkcom_hooked( 'filter', 'pings_open' );
jpkcom_check(
    'pings_open is filtered so the endpoint is no longer advertised',
    $pings !== array() && $pings[0][1] === PHP_INT_MAX,
    $pings === array() ? 'not registered - X-Pingback would still be sent' : sprintf( 'priority %d', $pings[0][1] )
);

if ( $pings !== array() ) {
    jpkcom_check( 'pings_open returns false', $pings[0][0]( true, 1 ) === false );
}

/*
 * 1.0.8 ran the guard at the default priority 10, after the updater bootstrap
 * at 5. A refused request should not boot the updater first.
 */
$init = jpkcom_hooked( 'action', 'init' );
$priorities = array_map( static fn( array $e ): int => $e[1], $init );
jpkcom_check(
    'the reject guard runs at priority 0, ahead of the updater bootstrap',
    in_array( 0, $priorities, true ),
    'init priorities: ' . implode( ', ', $priorities )
);

/*
 * The decision no longer derives from $_SERVER['SCRIPT_FILENAME'] - that
 * depends on the SAPI, and 1.0.8 additionally pushed the path through
 * sanitize_text_field(), which strips tags and %xx sequences from it.
 */
jpkcom_check(
    'a testable request-detection helper exists',
    function_exists( function: 'jpkcom_disable_xmlrpc_is_xmlrpc_request' ),
    'the decision is not reachable without terminating the request'
);

if ( function_exists( function: 'jpkcom_disable_xmlrpc_is_xmlrpc_request' ) ) {
    $_SERVER['SCRIPT_FILENAME'] = '/var/www/html/xmlrpc.php';

    jpkcom_check(
        'SCRIPT_FILENAME alone does not mark a request as XML-RPC',
        jpkcom_disable_xmlrpc_is_xmlrpc_request() === false,
        'still keyed off $_SERVER'
    );

    $_SERVER['SCRIPT_FILENAME'] = '/var/www/html/index.php';
    define( constant_name: 'XMLRPC_REQUEST', value: true );

    jpkcom_check(
        'XMLRPC_REQUEST marks a request as XML-RPC regardless of SCRIPT_FILENAME',
        jpkcom_disable_xmlrpc_is_xmlrpc_request() === true
    );
}

/*
 * 1.0.8 left the response to wp_die(). With XMLRPC_REQUEST defined that
 * dispatches to _xmlrpc_wp_die_handler(), which writes nothing unless the global
 * $wp_xmlrpc_server already exists - xmlrpc.php creates it after init - and
 * never calls status_header(). The endpoint answered HTTP 200 with an empty body
 * while the documentation promised 403.
 */
jpkcom_check(
    'a fault body builder exists',
    function_exists( function: 'jpkcom_disable_xmlrpc_fault_xml' ),
    'no body would be emitted'
);

if ( function_exists( function: 'jpkcom_disable_xmlrpc_fault_xml' ) ) {
    $xml = jpkcom_disable_xmlrpc_fault_xml();

    jpkcom_check( 'the fault body is not empty', $xml !== '' );

    $previous = libxml_use_internal_errors( true );
    $parsed   = simplexml_load_string( $xml );
    libxml_clear_errors();
    libxml_use_internal_errors( $previous );

    jpkcom_check( 'the fault body is well-formed XML', $parsed !== false );

    if ( $parsed !== false ) {
        $members = array();

        foreach ( $parsed->fault->value->struct->member as $member ) {
            $members[ (string) $member->name ] = trim( (string) $member->value->children()[0] );
        }

        jpkcom_check(
            'it is an XML-RPC methodResponse fault',
            $parsed->getName() === 'methodResponse' && isset( $parsed->fault )
        );
        jpkcom_check(
            'faultCode is 403',
            ( $members['faultCode'] ?? '' ) === '403',
            'faultCode: ' . ( $members['faultCode'] ?? 'missing' )
        );
        jpkcom_check(
            'faultString explains the refusal',
            str_contains( haystack: $members['faultString'] ?? '', needle: 'XML-RPC is disabled' )
        );
    }
}

/* Belt-and-braces guard that must stay registered. */
jpkcom_check(
    'wp_xmlrpc_server_class is still blocked',
    jpkcom_hooked( 'filter', 'wp_xmlrpc_server_class' ) !== array()
);

printf( "\n  %d passed, %d failed\n", $passed, $failed );

exit( $failed > 0 ? 1 : 0 );
