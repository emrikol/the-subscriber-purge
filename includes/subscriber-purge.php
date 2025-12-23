<?php
/**
 * Subscriber purge functionality.
 *
 * @package TheSubscriberPurge
 */

declare(strict_types=1);

namespace TheSubscriberPurge\Purge;

/**
 * Get inactive subscribers with no comments ordered oldest-first.
 *
 * @param int $days_inactive Number of days of inactivity required.
 * @param int $limit         Optional maximum number of users to return (0 for no limit).
 * @return array<int, \WP_User> List of inactive subscribers.
 */
function get_inactive_subscribers( int $days_inactive, int $limit = 0 ): array {
	$cutoff_time = time() - ( $days_inactive * DAY_IN_SECONDS );

	$args = array(
		'role'       => 'subscriber',
		'orderby'    => 'registered',
		'order'      => 'ASC',
		'date_query' => array(
			array(
				'column'    => 'user_registered',
				'before'    => gmdate( 'Y-m-d H:i:s', $cutoff_time ),
				'inclusive' => true,
			),
		),
	);

	if ( 0 !== $limit ) {
		$args['number'] = $limit;
	}

	$users    = get_users( $args );
	$inactive = array();

	foreach ( $users as $user ) {
		$comment_count = (int) get_comments(
			array(
				'user_id' => $user->ID,
				'count'   => true,
			)
		);

		if ( 0 !== $comment_count ) {
			continue;
		}

		$inactive[] = $user;

		if ( 0 !== $limit && count( $inactive ) >= $limit ) {
			break;
		}
	}

	return $inactive;
}

/**
 * Purge inactive subscribers.
 */
function run_purge(): void {
	// Get plugin options from unified settings.
	$days_inactive = (int) \TheSubscriberPurge\Settings\get( 'days_inactive', 30 );
	$send_emails   = (bool) \TheSubscriberPurge\Settings\get( 'send_emails', true );
	$notify_admin  = (bool) \TheSubscriberPurge\Settings\get( 'notify_admin', true );

	$users = get_inactive_subscribers( $days_inactive, 1 );

	if ( empty( $users ) ) {
		return;
	}

	foreach ( $users as $user ) {
		if ( true === $send_emails ) {
			send_deletion_email( $user );
		}

		if ( true === $notify_admin ) {
			send_admin_notification( $user );
		}

		wp_delete_user( $user->ID );
		break;
	}
}

/**
 * Send deletion notification email to user.
 *
 * @param \WP_User $user The user object.
 */
function send_deletion_email( \WP_User $user ): void {
	$blog_name = get_option( 'blogname' );
	$to        = $user->user_email;
	$subject   = sprintf(
		/* translators: %s: blog name */
		__( 'Your account on %s has been deleted', 'the-subscriber-purge' ),
		$blog_name
	);

	$days_inactive = (int) \TheSubscriberPurge\Settings\get( 'days_inactive', 30 );

	$message = sprintf(
		/* translators: 1: blog name, 2: days inactive */
		__( 'Hello %1$s,\n\nYour account on %2$s has been deleted because it had not been active for %3$d days and had no comments.\n\nThis helps us maintain the quality of our community.\n\nBest regards,\n%2$s', 'the-subscriber-purge' ),
		$user->user_login,
		$blog_name,
		$days_inactive
	);

	wp_mail( $to, $subject, $message ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_mail_wp_mail
}

/**
 * Send admin notification email when a user is purged.
 *
 * @param \WP_User $user The user object that was purged.
 */
function send_admin_notification( \WP_User $user ): void {
	$admin_email = get_option( 'admin_email' );
	$blog_name   = get_option( 'blogname' );
	$subject     = sprintf(
		/* translators: %s: blog name */
		__( '[%s] User Account Purged', 'the-subscriber-purge' ),
		$blog_name
	);

	$days_inactive     = (int) \TheSubscriberPurge\Settings\get( 'days_inactive', 30 );
	$registration_time = strtotime( $user->user_registered );
	$registered_date   = false !== $registration_time ? gmdate( 'Y-m-d H:i:s', $registration_time ) : 'Unknown';
	$days_registered   = false !== $registration_time ? (int) floor( ( time() - $registration_time ) / DAY_IN_SECONDS ) : 0;

	$message = sprintf(
		/* translators: 1: username, 2: user email, 3: user ID, 4: registration date, 5: days registered, 6: days inactive threshold, 7: blog name */
		__(
			'A subscriber account has been purged from %7$s.

User Details:
- Username: %1$s
- Email: %2$s
- User ID: %3$d
- Registered: %4$s
- Days Since Registration: %5$d
- Inactivity Threshold: %6$d days
- Comment Count: 0

This account was automatically deleted because it had no comments and exceeded the inactivity threshold.

---
This is an automated notification from The Subscriber Purge plugin.',
			'the-subscriber-purge'
		),
		$user->user_login,
		$user->user_email,
		$user->ID,
		$registered_date,
		$days_registered,
		$days_inactive,
		$blog_name
	);

	wp_mail( $admin_email, $subject, $message ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_mail_wp_mail
}
