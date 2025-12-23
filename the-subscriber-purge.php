<?php
/**
 * Plugin Name: The Subscriber Purge
 * Plugin URI: https://github.com/emrikol/the-subscriber-purge
 * Description: Automatically purge inactive subscriber accounts to help keep user spam down.
 * Version: 1.0.0
 * Author: Derrick Tennant
 * Author URI: https://derrick.blog/
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: the-subscriber-purge
 * Domain Path: /languages
 * Requires PHP: 8.4
 */

declare(strict_types=1);

namespace TheSubscriberPurge;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Include required files.
require_once plugin_dir_path( __FILE__ ) . 'includes/settings.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/subscriber-purge.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/admin-settings.php';

// Register custom cron schedule and ensure the purge event is set to every 15 minutes.
\add_filter( 'cron_schedules', __NAMESPACE__ . '\add_fifteen_minute_schedule' ); // phpcs:ignore WordPress.WP.CronInterval.ChangeDetected
\add_action( 'init', __NAMESPACE__ . '\schedule_purge_event' );

// Register cron action.
\add_action( 'tsp_purge_subscribers', 'TheSubscriberPurge\Purge\run_purge' );

/**
 * Add a 15-minute cron interval.
 *
 * @param array<string, array<string, int|string>> $schedules Existing schedules.
 * @return array<string, array<string, int|string>> Updated schedules.
 */
function add_fifteen_minute_schedule( array $schedules ): array {
	$schedules['tsp_every_fifteen_minutes'] = array(
		// 15 minutes to rate-limit deletions and emails.
		'interval' => 15 * MINUTE_IN_SECONDS,
		'display'  => __( 'Every 15 Minutes', 'the-subscriber-purge' ),
	);

	return $schedules;
}

/**
 * Ensure the purge event is scheduled for the 15-minute interval.
 */
function schedule_purge_event(): void {
	$event = \wp_get_scheduled_event( 'tsp_purge_subscribers' );

	if ( false === $event ) {
		\wp_schedule_event( time(), 'tsp_every_fifteen_minutes', 'tsp_purge_subscribers' );
		return;
	}

	if ( 'tsp_every_fifteen_minutes' !== $event->schedule ) {
		\wp_unschedule_event( (int) $event->timestamp, 'tsp_purge_subscribers', $event->args );
		\wp_schedule_event( time(), 'tsp_every_fifteen_minutes', 'tsp_purge_subscribers' );
	}
}
