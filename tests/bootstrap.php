<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// Simple WordPress stubs for unit testing without WP stack.
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}

$GLOBALS['tsp_users']          = array();
$GLOBALS['tsp_comments']       = array();
$GLOBALS['tsp_deleted_users']  = array();
$GLOBALS['tsp_mail_log']       = array();
$GLOBALS['tsp_force_mail_fail'] = false;
$GLOBALS['tsp_force_update_option_fail'] = false;
$GLOBALS['tsp_force_delete_fail'] = false;
$GLOBALS['tsp_filters']        = array();
$GLOBALS['tsp_actions']        = array();
$GLOBALS['tsp_admin_settings'] = array(
	'pages'     => array(),
	'registered'=> array(),
	'sections'  => array(),
	'fields'    => array(),
);

/**
 * Reset the in-memory WordPress stubs for a clean test state.
 */
function tsp_reset_state(): void {
	$GLOBALS['tsp_options']       = array(
		'blogname'    => array(
			'value'    => 'Test Blog',
			'autoload' => 'yes',
		),
		'admin_email' => array(
			'value'    => 'admin@example.com',
			'autoload' => 'yes',
		),
	);
	$GLOBALS['tsp_users']         = array();
	$GLOBALS['tsp_comments']      = array();
	$GLOBALS['tsp_deleted_users'] = array();
	$GLOBALS['tsp_mail_log']      = array();
	$GLOBALS['tsp_force_mail_fail'] = false;
	$GLOBALS['tsp_force_update_option_fail'] = false;
	$GLOBALS['tsp_force_delete_fail'] = false;
	$GLOBALS['tsp_filters']       = array();
	$GLOBALS['tsp_actions']       = array();
	$GLOBALS['tsp_admin_settings'] = array(
		'pages'     => array(),
		'registered'=> array(),
		'sections'  => array(),
		'fields'    => array(),
	);
}

tsp_reset_state();

class WP_User {
	public int $ID;
	public string $user_login;
	public string $user_email;
	public string $user_registered;
	public array $roles;

	public function __construct( int|object $data ) {
		if ( is_int( $data ) ) {
			$this->ID             = $data;
			$this->user_login     = 'user_' . $data;
			$this->user_email     = 'user_' . $data . '@example.com';
			$this->user_registered= gmdate( 'Y-m-d H:i:s' );
			$this->roles          = array( 'subscriber' );
			return;
		}

		$this->ID              = (int) ( $data->ID ?? 0 );
		$this->user_login      = (string) ( $data->user_login ?? '' );
		$this->user_email      = (string) ( $data->user_email ?? '' );
		$this->user_registered = (string) ( $data->user_registered ?? gmdate( 'Y-m-d H:i:s' ) );
		$this->roles           = (array) ( $data->roles ?? array() );
	}
}

// Option helpers.
function get_option( string $option, mixed $default = false ): mixed {
	return $GLOBALS['tsp_options'][ $option ]['value'] ?? $default;
}

function add_option( string $option, mixed $value, mixed $deprecated = '', bool|string $autoload = true ): bool {
	if ( isset( $GLOBALS['tsp_options'][ $option ] ) ) {
		return false;
	}
	$GLOBALS['tsp_options'][ $option ] = array(
		'value'    => $value,
		'autoload' => $autoload,
	);
	return true;
}

function update_option( string $option, mixed $value, bool|string $autoload = true ): bool {
	if ( true === $GLOBALS['tsp_force_update_option_fail'] ) {
		return false;
	}

	$GLOBALS['tsp_options'][ $option ] = array(
		'value'    => $value,
		'autoload' => $autoload,
	);
	return true;
}

// Actions/filters stubs.
function add_action( string $hook, callable $callback ): void {
	$GLOBALS['tsp_actions'][ $hook ][] = $callback;
}

function add_filter( string $hook, callable $callback ): void {
	$GLOBALS['tsp_filters'][ $hook ][] = $callback;
}

// Admin/menu stubs.
function add_options_page( string $page_title, string $menu_title, string $capability, string $menu_slug, callable $callback ): void {
	$GLOBALS['tsp_admin_settings']['pages'][] = compact( 'page_title', 'menu_title', 'capability', 'menu_slug', 'callback' );
}

function register_setting( string $option_group, string $option_name, array $args = array() ): void {
	$GLOBALS['tsp_admin_settings']['registered'][] = compact( 'option_group', 'option_name', 'args' );
}

function add_settings_section( string $id, ?string $title, $callback, string $page ): void {
	$GLOBALS['tsp_admin_settings']['sections'][] = compact( 'id', 'title', 'callback', 'page' );
}

function add_settings_field( string $id, string $title, $callback, string $page, string $section ): void {
	$GLOBALS['tsp_admin_settings']['fields'][] = compact( 'id', 'title', 'callback', 'page', 'section' );
}

function settings_fields( string $option_group ): void {
	$GLOBALS['tsp_admin_settings']['settings_fields'][] = $option_group;
}

function do_settings_sections( string $page ): void {
	$GLOBALS['tsp_admin_settings']['rendered_sections'][] = $page;
}

function submit_button(): void {
	echo '<button type="submit">Submit</button>';
}

// User and comment helpers.
function get_users( array $args = array() ): array {
	$users = array_map(
		static fn( $user ) => $user instanceof WP_User ? $user : new WP_User( $user ),
		$GLOBALS['tsp_users']
	);

	if ( isset( $args['role'] ) ) {
		$users = array_values(
			array_filter(
				$users,
				static fn( $user ) => empty( $user->roles ) || in_array( $args['role'], $user->roles, true )
			)
		);
	}

	if ( isset( $args['date_query'][0]['before'] ) ) {
		$before    = strtotime( $args['date_query'][0]['before'] );
		$inclusive = $args['date_query'][0]['inclusive'] ?? false;
		$users     = array_values(
			array_filter(
				$users,
				static function ( $user ) use ( $before, $inclusive ) {
					$registered = strtotime( $user->user_registered );
					return $inclusive ? $registered <= $before : $registered < $before;
				}
			)
		);
	}

	if ( isset( $args['orderby'] ) && 'registered' === $args['orderby'] ) {
		usort(
			$users,
			static fn( $a, $b ) => strtotime( $a->user_registered ) <=> strtotime( $b->user_registered )
		);
	}

	if ( isset( $args['order'] ) && 'DESC' === strtoupper( (string) $args['order'] ) ) {
		$users = array_reverse( $users );
	}

	if ( ! empty( $args['number'] ) && is_int( $args['number'] ) ) {
		$users = array_slice( $users, 0, $args['number'] );
	}

	return $users;
}

function get_comments( array $args = array() ): int|array {
	if ( ! empty( $args['count'] ) && ! empty( $args['user_id'] ) ) {
		return (int) ( $GLOBALS['tsp_comments'][ (int) $args['user_id'] ] ?? 0 );
	}

	return array();
}

function get_user_by( string $field, int $value ): ?WP_User {
	foreach ( $GLOBALS['tsp_users'] as $user ) {
		if ( 'id' === $field && (int) $user->ID === $value ) {
			return $user instanceof WP_User ? $user : new WP_User( $user );
		}
	}
	return null;
}

// Mail/delete stubs.
function wp_mail( string $to, string $subject, string $message ): bool {
	$success = ! $GLOBALS['tsp_force_mail_fail'];

	$GLOBALS['tsp_mail_log'][] = array(
		'to'       => $to,
		'subject'  => $subject,
		'message'  => $message,
		'success'  => $success,
	);

	return $success;
}

function wp_delete_user( int $user_id ): bool {
	if ( true === $GLOBALS['tsp_force_delete_fail'] ) {
		return false;
	}

	$GLOBALS['tsp_deleted_users'][] = $user_id;
	// Mirror core behavior by removing the user from the in-memory store.
	foreach ( $GLOBALS['tsp_users'] as $index => $user ) {
		if ( ( $user instanceof WP_User ? $user->ID : (int) $user->ID ) === $user_id ) {
			unset( $GLOBALS['tsp_users'][ $index ] );
			break;
		}
	}
	return true;
}

// Localization stubs.
function __( string $text, string $domain = 'default' ): string {
	return $text;
}

function esc_html__( string $text, string $domain = 'default' ): string {
	return $text;
}

function esc_html_e( string $text, string $domain = 'default' ): void {
	echo $text;
}

function esc_html( string $text ): string {
	return $text;
}

function esc_attr( string $text ): string {
	return $text;
}

function checked( mixed $checked, mixed $current = true ): void {
	if ( $checked === $current ) {
		echo 'checked="checked"';
	}
}

// Include the plugin code under test.
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/subscriber-purge.php';
require_once __DIR__ . '/../includes/admin-settings.php';
