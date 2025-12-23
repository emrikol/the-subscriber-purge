<?php
/**
 * Settings management functions.
 *
 * Handles all plugin settings in a single non-autoloaded option array.
 *
 * @package TheSubscriberPurge
 */

declare(strict_types=1);

namespace TheSubscriberPurge\Settings;

/**
 * Option name for storing all plugin settings.
 */
const OPTION_NAME = 'tsp_settings';

/**
 * Get a setting value.
 *
 * @param string $key           The setting key.
 * @param mixed  $default_value Default value if setting doesn't exist.
 * @return mixed The setting value.
 */
function get( string $key, mixed $default_value = null ): mixed {
	$settings = \get_option( OPTION_NAME, array() );

	if ( ! is_array( $settings ) ) {
		$settings = array();
	}

	return $settings[ $key ] ?? $default_value;
}

/**
 * Update a setting value.
 *
 * @param string $key   The setting key.
 * @param mixed  $value The setting value.
 * @return bool True if setting was updated successfully.
 */
function update( string $key, mixed $value ): bool {
	$settings = \get_option( OPTION_NAME, array() );

	if ( ! is_array( $settings ) ) {
		$settings = array();
	}

	$settings[ $key ] = $value;

	// IMPORTANT: Third parameter false means DO NOT autoload.
	return \update_option( OPTION_NAME, $settings, false );
}
