<?php
declare(strict_types=1);

namespace TheSubscriberPurge\Tests;

use PHPUnit\Framework\TestCase;
use TheSubscriberPurge\Purge;
use TheSubscriberPurge\Settings;

final class SubscriberPurgeTest extends TestCase {
	protected function setUp(): void {
		tsp_reset_state();
	}

	public function test_get_inactive_subscribers_filters_by_age_comments_and_order(): void {
		$now = time();

		$GLOBALS['tsp_users'] = array(
			(object) array(
				'ID'             => 1,
				'user_login'     => 'oldest',
				'user_email'     => 'oldest@example.com',
				'user_registered'=> gmdate( 'Y-m-d H:i:s', $now - ( 40 * DAY_IN_SECONDS ) ),
				'roles'          => array( 'subscriber' ),
			),
			(object) array(
				'ID'             => 2,
				'user_login'     => 'newer',
				'user_email'     => 'newer@example.com',
				'user_registered'=> gmdate( 'Y-m-d H:i:s', $now - ( 10 * DAY_IN_SECONDS ) ),
				'roles'          => array( 'subscriber' ),
			),
			(object) array(
				'ID'             => 3,
				'user_login'     => 'with-comments',
				'user_email'     => 'with-comments@example.com',
				'user_registered'=> gmdate( 'Y-m-d H:i:s', $now - ( 50 * DAY_IN_SECONDS ) ),
				'roles'          => array( 'subscriber' ),
			),
		);

		$GLOBALS['tsp_comments'] = array(
			3 => 5, // Exclude due to comments.
		);

		$results = Purge\get_inactive_subscribers( 30, 2 );

		$this->assertCount( 1, $results );
		$this->assertSame( 1, $results[0]->ID );
	}

	public function test_get_inactive_subscribers_ignores_non_subscribers(): void {
		$now = time();

		$GLOBALS['tsp_users'] = array(
			(object) array(
				'ID'             => 101,
				'user_login'     => 'old-subscriber',
				'user_email'     => 'sub@example.com',
				'user_registered'=> gmdate( 'Y-m-d H:i:s', $now - ( 40 * DAY_IN_SECONDS ) ),
				'roles'          => array( 'subscriber' ),
			),
			(object) array(
				'ID'             => 102,
				'user_login'     => 'old-editor',
				'user_email'     => 'editor@example.com',
				'user_registered'=> gmdate( 'Y-m-d H:i:s', $now - ( 50 * DAY_IN_SECONDS ) ),
				'roles'          => array( 'editor' ),
			),
		);

		$GLOBALS['tsp_comments'] = array();

		$results = Purge\get_inactive_subscribers( 30, 0 );

		$this->assertCount( 1, $results );
		$this->assertSame( 101, $results[0]->ID );
		$this->assertSame( 'old-subscriber', $results[0]->user_login );
	}

	public function test_get_inactive_subscribers_respects_limit_and_order(): void {
		$now = time();

		$GLOBALS['tsp_users'] = array(
			(object) array(
				'ID'             => 5,
				'user_login'     => 'oldest',
				'user_email'     => 'oldest@example.com',
				'user_registered'=> gmdate( 'Y-m-d H:i:s', $now - ( 60 * DAY_IN_SECONDS ) ),
				'roles'          => array( 'subscriber' ),
			),
			(object) array(
				'ID'             => 6,
				'user_login'     => 'middle',
				'user_email'     => 'middle@example.com',
				'user_registered'=> gmdate( 'Y-m-d H:i:s', $now - ( 45 * DAY_IN_SECONDS ) ),
				'roles'          => array( 'subscriber' ),
			),
			(object) array(
				'ID'             => 7,
				'user_login'     => 'newest',
				'user_email'     => 'newest@example.com',
				'user_registered'=> gmdate( 'Y-m-d H:i:s', $now - ( 20 * DAY_IN_SECONDS ) ),
				'roles'          => array( 'subscriber' ),
			),
		);

		$GLOBALS['tsp_comments'] = array();

		$results = Purge\get_inactive_subscribers( 15, 2 );

		$this->assertCount( 2, $results );
		$this->assertSame( 5, $results[0]->ID );
		$this->assertSame( 6, $results[1]->ID );
	}

	public function test_run_purge_deletes_only_one_oldest_and_sends_email(): void {
		$now = time();

		$GLOBALS['tsp_users'] = array(
			(object) array(
				'ID'             => 10,
				'user_login'     => 'older',
				'user_email'     => 'older@example.com',
				'user_registered'=> gmdate( 'Y-m-d H:i:s', $now - ( 45 * DAY_IN_SECONDS ) ),
				'roles'          => array( 'subscriber' ),
			),
			(object) array(
				'ID'             => 11,
				'user_login'     => 'also-old',
				'user_email'     => 'also-old@example.com',
				'user_registered'=> gmdate( 'Y-m-d H:i:s', $now - ( 40 * DAY_IN_SECONDS ) ),
				'roles'          => array( 'subscriber' ),
			),
		);

		Settings\update( 'send_emails', true );
		Settings\update( 'days_inactive', 30 );

		Purge\run_purge();

		$this->assertSame( array( 10 ), $GLOBALS['tsp_deleted_users'] );
		$this->assertCount( 2, $GLOBALS['tsp_mail_log'] ); // User email + admin email.
		$this->assertSame( 'older@example.com', $GLOBALS['tsp_mail_log'][0]['to'] );
		$this->assertSame( 'admin@example.com', $GLOBALS['tsp_mail_log'][1]['to'] );
	}

	public function test_run_purge_no_users_exits_early(): void {
		// No users meet criteria; should not attempt deletion or email.
		Purge\run_purge();

		$this->assertSame( array(), $GLOBALS['tsp_deleted_users'] );
		$this->assertSame( array(), $GLOBALS['tsp_mail_log'] );
	}


	public function test_run_purge_deletes_at_most_one_per_run_when_multiple_eligible(): void {
		$now = time();

		$GLOBALS['tsp_users'] = array(
			(object) array(
				'ID'             => 41,
				'user_login'     => 'older-one',
				'user_email'     => 'older-one@example.com',
				'user_registered'=> gmdate( 'Y-m-d H:i:s', $now - ( 50 * DAY_IN_SECONDS ) ),
				'roles'          => array( 'subscriber' ),
			),
			(object) array(
				'ID'             => 42,
				'user_login'     => 'older-two',
				'user_email'     => 'older-two@example.com',
				'user_registered'=> gmdate( 'Y-m-d H:i:s', $now - ( 45 * DAY_IN_SECONDS ) ),
				'roles'          => array( 'subscriber' ),
			),
		);

		Settings\update( 'days_inactive', 30 );
		Settings\update( 'send_emails', true );

		Purge\run_purge();

		$this->assertCount( 1, $GLOBALS['tsp_deleted_users'] );
		$this->assertCount( 2, $GLOBALS['tsp_mail_log'] ); // User email + admin email.
		$this->assertSame( array( 41 ), $GLOBALS['tsp_deleted_users'] );
	}

	public function test_run_purge_skips_users_with_comments(): void {
		$now = time();

		$GLOBALS['tsp_users'] = array(
			(object) array(
				'ID'             => 30,
				'user_login'     => 'has-comments',
				'user_email'     => 'comments@example.com',
				'user_registered'=> gmdate( 'Y-m-d H:i:s', $now - ( 50 * DAY_IN_SECONDS ) ),
				'roles'          => array( 'subscriber' ),
			),
		);

		$GLOBALS['tsp_comments'] = array( 30 => 3 );
		Settings\update( 'days_inactive', 30 );
		Settings\update( 'send_emails', true );

		Purge\run_purge();

		$this->assertSame( array(), $GLOBALS['tsp_deleted_users'] );
		$this->assertSame( array(), $GLOBALS['tsp_mail_log'] );
	}

	public function test_send_deletion_email_includes_blogname_and_days(): void {
		$now = time();
		$GLOBALS['tsp_users'] = array();
		Settings\update( 'days_inactive', 45 );

		$user = new \WP_User(
			(object) array(
				'ID'             => 99,
				'user_login'     => 'to-email',
				'user_email'     => 'to@example.com',
				'user_registered'=> gmdate( 'Y-m-d H:i:s', $now - ( 60 * DAY_IN_SECONDS ) ),
				'roles'          => array( 'subscriber' ),
			)
		);

		Purge\send_deletion_email( $user );

		$this->assertCount( 1, $GLOBALS['tsp_mail_log'] );
		$this->assertSame( 'to@example.com', $GLOBALS['tsp_mail_log'][0]['to'] );
		$this->assertStringContainsString( 'Test Blog', $GLOBALS['tsp_mail_log'][0]['subject'] );
		$this->assertStringContainsString( '45', $GLOBALS['tsp_mail_log'][0]['message'] );
	}

	public function test_run_purge_deletes_without_email_when_disabled(): void {
		$now = time();

		$GLOBALS['tsp_users'] = array(
			(object) array(
				'ID'             => 20,
				'user_login'     => 'old',
				'user_email'     => 'old@example.com',
				'user_registered'=> gmdate( 'Y-m-d H:i:s', $now - ( 50 * DAY_IN_SECONDS ) ),
				'roles'          => array( 'subscriber' ),
			),
		);

		Settings\update( 'send_emails', false );
		Settings\update( 'notify_admin', false );
		Settings\update( 'days_inactive', 30 );

		Purge\run_purge();

		$this->assertSame( array( 20 ), $GLOBALS['tsp_deleted_users'] );
		$this->assertSame( array(), $GLOBALS['tsp_mail_log'] );
	}

	public function test_run_purge_respects_one_user_per_call_across_multiple_invocations(): void {
		$now = time();

		$GLOBALS['tsp_users'] = array(
			(object) array(
				'ID'             => 201,
				'user_login'     => 'first-oldest',
				'user_email'     => 'first@example.com',
				'user_registered'=> gmdate( 'Y-m-d H:i:s', $now - ( 70 * DAY_IN_SECONDS ) ),
				'roles'          => array( 'subscriber' ),
			),
			(object) array(
				'ID'             => 202,
				'user_login'     => 'second-oldest',
				'user_email'     => 'second@example.com',
				'user_registered'=> gmdate( 'Y-m-d H:i:s', $now - ( 60 * DAY_IN_SECONDS ) ),
				'roles'          => array( 'subscriber' ),
			),
			(object) array(
				'ID'             => 203,
				'user_login'     => 'third-oldest',
				'user_email'     => 'third@example.com',
				'user_registered'=> gmdate( 'Y-m-d H:i:s', $now - ( 50 * DAY_IN_SECONDS ) ),
				'roles'          => array( 'subscriber' ),
			),
		);

		Settings\update( 'send_emails', false );
		Settings\update( 'notify_admin', false );
		Settings\update( 'days_inactive', 30 );

		Purge\run_purge();
		$this->assertCount( 1, $GLOBALS['tsp_deleted_users'] );
		$this->assertSame( array( 201 ), $GLOBALS['tsp_deleted_users'] );

		Purge\run_purge();
		$this->assertCount( 2, $GLOBALS['tsp_deleted_users'] );
		$this->assertSame( array( 201, 202 ), $GLOBALS['tsp_deleted_users'] );

		Purge\run_purge();
		$this->assertCount( 3, $GLOBALS['tsp_deleted_users'] );
		$this->assertSame( array( 201, 202, 203 ), $GLOBALS['tsp_deleted_users'] );
	}

	public function test_get_inactive_subscribers_returns_all_when_limit_zero(): void {
		$now = time();

		$GLOBALS['tsp_users'] = array(
			(object) array(
				'ID'             => 301,
				'user_login'     => 'older',
				'user_email'     => 'older@example.com',
				'user_registered'=> gmdate( 'Y-m-d H:i:s', $now - ( 70 * DAY_IN_SECONDS ) ),
				'roles'          => array( 'subscriber' ),
			),
			(object) array(
				'ID'             => 302,
				'user_login'     => 'middle',
				'user_email'     => 'middle@example.com',
				'user_registered'=> gmdate( 'Y-m-d H:i:s', $now - ( 60 * DAY_IN_SECONDS ) ),
				'roles'          => array( 'subscriber' ),
			),
			(object) array(
				'ID'             => 303,
				'user_login'     => 'newest',
				'user_email'     => 'newest@example.com',
				'user_registered'=> gmdate( 'Y-m-d H:i:s', $now - ( 40 * DAY_IN_SECONDS ) ),
				'roles'          => array( 'subscriber' ),
			),
		);

		$GLOBALS['tsp_comments'] = array();

		$results = Purge\get_inactive_subscribers( 30, 0 );

		$this->assertCount( 3, $results );
		$this->assertSame( 301, $results[0]->ID );
		$this->assertSame( 302, $results[1]->ID );
		$this->assertSame( 303, $results[2]->ID );
	}

	public function test_send_deletion_email_logs_failure_without_throwing(): void {
		$now = time();
		$GLOBALS['tsp_force_mail_fail'] = true;

		$user = new \WP_User(
			(object) array(
				'ID'             => 401,
				'user_login'     => 'failing-mail',
				'user_email'     => 'fail@example.com',
				'user_registered'=> gmdate( 'Y-m-d H:i:s', $now - ( 80 * DAY_IN_SECONDS ) ),
				'roles'          => array( 'subscriber' ),
			)
		);

		Purge\send_deletion_email( $user );

		$this->assertCount( 1, $GLOBALS['tsp_mail_log'] );
		$this->assertFalse( $GLOBALS['tsp_mail_log'][0]['success'] );
		$this->assertSame( 'fail@example.com', $GLOBALS['tsp_mail_log'][0]['to'] );
	}

	public function test_run_purge_handles_delete_failure_without_removing_user(): void {
		$now = time();

		$GLOBALS['tsp_users'] = array(
			(object) array(
				'ID'             => 501,
				'user_login'     => 'cannot-delete',
				'user_email'     => 'cannot@example.com',
				'user_registered'=> gmdate( 'Y-m-d H:i:s', $now - ( 90 * DAY_IN_SECONDS ) ),
				'roles'          => array( 'subscriber' ),
			),
		);

		$GLOBALS['tsp_force_delete_fail'] = true;
		Settings\update( 'days_inactive', 30 );
		Settings\update( 'send_emails', true );

		Purge\run_purge();

		$this->assertSame( array(), $GLOBALS['tsp_deleted_users'] );
		$this->assertCount( 1, $GLOBALS['tsp_users'] ); // User remains because delete failed.
		$this->assertCount( 2, $GLOBALS['tsp_mail_log'] ); // User email + admin email were still attempted.
	}

	public function test_send_admin_notification_includes_user_details(): void {
		$now = time();
		$registration_ts = $now - ( 60 * DAY_IN_SECONDS );

		$user = new \WP_User(
			(object) array(
				'ID'             => 600,
				'user_login'     => 'purged-user',
				'user_email'     => 'purged@example.com',
				'user_registered'=> gmdate( 'Y-m-d H:i:s', $registration_ts ),
				'roles'          => array( 'subscriber' ),
			)
		);

		Settings\update( 'days_inactive', 45 );

		Purge\send_admin_notification( $user );

		$this->assertCount( 1, $GLOBALS['tsp_mail_log'] );
		$this->assertSame( 'admin@example.com', $GLOBALS['tsp_mail_log'][0]['to'] );
		$this->assertStringContainsString( 'purged-user', $GLOBALS['tsp_mail_log'][0]['message'] );
		$this->assertStringContainsString( 'purged@example.com', $GLOBALS['tsp_mail_log'][0]['message'] );
		$this->assertStringContainsString( '600', $GLOBALS['tsp_mail_log'][0]['message'] );
		$this->assertStringContainsString( '45', $GLOBALS['tsp_mail_log'][0]['message'] );
		$this->assertStringContainsString( 'Test Blog', $GLOBALS['tsp_mail_log'][0]['subject'] );
	}

	public function test_run_purge_skips_admin_notification_when_disabled(): void {
		$now = time();

		$GLOBALS['tsp_users'] = array(
			(object) array(
				'ID'             => 601,
				'user_login'     => 'old-user',
				'user_email'     => 'old@example.com',
				'user_registered'=> gmdate( 'Y-m-d H:i:s', $now - ( 45 * DAY_IN_SECONDS ) ),
				'roles'          => array( 'subscriber' ),
			),
		);

		Settings\update( 'send_emails', false );
		Settings\update( 'notify_admin', false );
		Settings\update( 'days_inactive', 30 );

		Purge\run_purge();

		$this->assertSame( array( 601 ), $GLOBALS['tsp_deleted_users'] );
		$this->assertSame( array(), $GLOBALS['tsp_mail_log'] ); // No emails sent at all.
	}

	public function test_run_purge_sends_only_admin_notification_when_user_email_disabled(): void {
		$now = time();

		$GLOBALS['tsp_users'] = array(
			(object) array(
				'ID'             => 602,
				'user_login'     => 'admin-only',
				'user_email'     => 'adminonly@example.com',
				'user_registered'=> gmdate( 'Y-m-d H:i:s', $now - ( 45 * DAY_IN_SECONDS ) ),
				'roles'          => array( 'subscriber' ),
			),
		);

		Settings\update( 'send_emails', false );
		Settings\update( 'notify_admin', true );
		Settings\update( 'days_inactive', 30 );

		Purge\run_purge();

		$this->assertSame( array( 602 ), $GLOBALS['tsp_deleted_users'] );
		$this->assertCount( 1, $GLOBALS['tsp_mail_log'] ); // Only admin email.
		$this->assertSame( 'admin@example.com', $GLOBALS['tsp_mail_log'][0]['to'] );
	}

	public function test_send_admin_notification_handles_invalid_registration_date(): void {
		$user = new \WP_User(
			(object) array(
				'ID'             => 603,
				'user_login'     => 'bad-date',
				'user_email'     => 'baddate@example.com',
				'user_registered'=> 'not-a-date',
				'roles'          => array( 'subscriber' ),
			)
		);

		Settings\update( 'days_inactive', 30 );

		Purge\send_admin_notification( $user );

		$this->assertCount( 1, $GLOBALS['tsp_mail_log'] );
		$this->assertStringContainsString( 'Unknown', $GLOBALS['tsp_mail_log'][0]['message'] );
		$this->assertStringContainsString( '- Days Since Registration: 0', $GLOBALS['tsp_mail_log'][0]['message'] );
	}
}
