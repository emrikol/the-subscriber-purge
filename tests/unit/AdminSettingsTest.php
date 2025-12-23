<?php
declare(strict_types=1);

namespace TheSubscriberPurge\Tests;

use PHPUnit\Framework\TestCase;
use TheSubscriberPurge\Admin;
use TheSubscriberPurge\Settings;

final class AdminSettingsTest extends TestCase {
	protected function setUp(): void {
		tsp_reset_state();
	}

	public function test_register_settings_registers_all_fields(): void {
		Admin\register_settings();

		$this->assertNotEmpty( $GLOBALS['tsp_admin_settings']['registered'] );
		$this->assertCount( 3, $GLOBALS['tsp_admin_settings']['fields'] );
		$this->assertSame( 'tsp_settings', $GLOBALS['tsp_admin_settings']['registered'][0]['option_name'] );
		$this->assertSame( 'tsp_settings_group', $GLOBALS['tsp_admin_settings']['registered'][0]['option_group'] );
	}

	public function test_add_menu_registers_admin_page(): void {
		Admin\add_menu();

		$this->assertCount( 1, $GLOBALS['tsp_admin_settings']['pages'] );
		$this->assertSame( 'tsp-settings', $GLOBALS['tsp_admin_settings']['pages'][0]['menu_slug'] );
	}

	public function test_sanitize_settings_clamps_and_casts(): void {
		$result = Admin\sanitize_settings(
			array(
				'days_inactive' => 0,
				'send_emails'   => '',
				'notify_admin'  => '',
			)
		);

		$this->assertSame( 1, $result['days_inactive'] ); // Min clamp.
		$this->assertFalse( $result['send_emails'] );
		$this->assertFalse( $result['notify_admin'] );

		$result = Admin\sanitize_settings(
			array(
				'days_inactive' => 999,
				'send_emails'   => 1,
				'notify_admin'  => 1,
			)
		);

		$this->assertSame( 365, $result['days_inactive'] ); // Max clamp.
		$this->assertTrue( $result['send_emails'] );
		$this->assertTrue( $result['notify_admin'] );
	}

	public function test_sanitize_settings_defaults_when_missing_or_invalid(): void {
		// Missing keys should fall back to defaults.
		$result = Admin\sanitize_settings( array() );
		$this->assertSame( 30, $result['days_inactive'] );
		$this->assertFalse( $result['send_emails'] );
		$this->assertFalse( $result['notify_admin'] );

		// Non-array input should also fall back to defaults.
		$result = Admin\sanitize_settings( 'not-an-array' );
		$this->assertSame( 30, $result['days_inactive'] );
		$this->assertFalse( $result['send_emails'] );
		$this->assertFalse( $result['notify_admin'] );

		// Unexpected keys should be ignored.
		$result = Admin\sanitize_settings(
			array(
				'unknown'       => 'value',
				'days_inactive' => 15,
			)
		);
		$this->assertSame( 15, $result['days_inactive'] );
		$this->assertArrayNotHasKey( 'unknown', $result );
	}

	public function test_render_page_outputs_form(): void {
		ob_start();
		Admin\render_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Subscriber Purge Settings', $output );
		$this->assertStringContainsString( '<form method="post"', $output );
		// Ensure settings_fields was called with the expected group (stub logs calls).
		$this->assertContains( 'tsp_settings_group', $GLOBALS['tsp_admin_settings']['settings_fields'] ?? array() );
	}

	public function test_render_upcoming_purges_renders_rows(): void {
		$now                = time();
		$GLOBALS['tsp_users'] = array(
			(object) array(
				'ID'             => 50,
				'user_login'     => 'soon-to-purge',
				'user_email'     => 'soon@example.com',
				'user_registered'=> gmdate( 'Y-m-d H:i:s', $now - ( 40 * DAY_IN_SECONDS ) ),
				'roles'          => array( 'subscriber' ),
			),
		);

		Settings\update( 'days_inactive', 30 );
		$GLOBALS['tsp_comments'] = array(); // No comments so it is eligible.

		ob_start();
		Admin\render_upcoming_purges();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Users Scheduled for Purge', $output );
		$this->assertStringContainsString( 'soon@example.com', $output );
	}

	public function test_render_upcoming_purges_orders_oldest_first(): void {
		$now = time();
		$GLOBALS['tsp_users'] = array(
			(object) array(
				'ID'             => 71,
				'user_login'     => 'older-user',
				'user_email'     => 'older@example.com',
				'user_registered'=> gmdate( 'Y-m-d H:i:s', $now - ( 60 * DAY_IN_SECONDS ) ),
				'roles'          => array( 'subscriber' ),
			),
			(object) array(
				'ID'             => 72,
				'user_login'     => 'newer-user',
				'user_email'     => 'newer@example.com',
				'user_registered'=> gmdate( 'Y-m-d H:i:s', $now - ( 10 * DAY_IN_SECONDS ) ),
				'roles'          => array( 'subscriber' ),
			),
		);

		Settings\update( 'days_inactive', 5 );
		$GLOBALS['tsp_comments'] = array();

		ob_start();
		Admin\render_upcoming_purges();
		$output = ob_get_clean();

		$older_pos = strpos( $output, 'older@example.com' );
		$newer_pos = strpos( $output, 'newer@example.com' );

		$this->assertIsInt( $older_pos );
		$this->assertIsInt( $newer_pos );
		$this->assertLessThan( $newer_pos, $older_pos );
	}

	public function test_render_upcoming_purges_respects_days_inactive(): void {
		$now = time();
		$GLOBALS['tsp_users'] = array(
			(object) array(
				'ID'             => 60,
				'user_login'     => 'old-enough',
				'user_email'     => 'old@example.com',
				'user_registered'=> gmdate( 'Y-m-d H:i:s', $now - ( 7 * DAY_IN_SECONDS ) ),
				'roles'          => array( 'subscriber' ),
			),
			(object) array(
				'ID'             => 61,
				'user_login'     => 'too-new',
				'user_email'     => 'new@example.com',
				'user_registered'=> gmdate( 'Y-m-d H:i:s', $now - ( 2 * DAY_IN_SECONDS ) ),
				'roles'          => array( 'subscriber' ),
			),
		);

		Settings\update( 'days_inactive', 5 );
		$GLOBALS['tsp_comments'] = array();

		ob_start();
		Admin\render_upcoming_purges();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'old@example.com', $output );
		$this->assertStringNotContainsString( 'new@example.com', $output );
	}

	public function test_render_upcoming_purges_shows_empty_state(): void {
		// No eligible users.
		ob_start();
		Admin\render_upcoming_purges();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'No subscriber accounts are currently scheduled for purge.', $output );
	}

	public function test_render_upcoming_purges_shows_empty_state_when_only_commenters(): void {
		$now = time();
		$GLOBALS['tsp_users'] = array(
			(object) array(
				'ID'             => 90,
				'user_login'     => 'commenter',
				'user_email'     => 'commenter@example.com',
				'user_registered'=> gmdate( 'Y-m-d H:i:s', $now - ( 40 * DAY_IN_SECONDS ) ),
				'roles'          => array( 'subscriber' ),
			),
		);
		$GLOBALS['tsp_comments'] = array( 90 => 4 );

		ob_start();
		Admin\render_upcoming_purges();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'No subscriber accounts are currently scheduled for purge.', $output );
	}

	public function test_render_upcoming_purges_handles_missing_user_fields(): void {
		$now = time();
		$GLOBALS['tsp_users'] = array(
			(object) array(
				'ID'             => 91,
				'user_login'     => '',
				'user_email'     => '',
				'user_registered'=> '',
				'roles'          => array( 'subscriber' ),
			),
		);

		Settings\update( 'days_inactive', 30 );
		$GLOBALS['tsp_comments'] = array();

		ob_start();
		Admin\render_upcoming_purges();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Users Scheduled for Purge', $output );
		$this->assertStringContainsString( '<td style="padding: 10px;"></td>', $output );
	}

	public function test_render_form_fields_output_values(): void {
		Settings\update( 'days_inactive', 42 );
		Settings\update( 'send_emails', true );
		Settings\update( 'notify_admin', true );

		ob_start();
		Admin\render_days_field();
		$days_output = ob_get_clean();

		ob_start();
		Admin\render_email_field();
		$email_output = ob_get_clean();

		ob_start();
		Admin\render_notify_admin_field();
		$admin_output = ob_get_clean();

		$this->assertStringContainsString( 'value="42"', $days_output );
		$this->assertStringContainsString( 'checked', $email_output );
		$this->assertStringContainsString( 'checked', $admin_output );
	}

	public function test_get_days_until_purge_is_never_negative(): void {
		$now = time();
		$GLOBALS['tsp_users'] = array(
			(object) array(
				'ID'             => 77,
				'user_login'     => 'future',
				'user_email'     => 'future@example.com',
				'user_registered'=> gmdate( 'Y-m-d H:i:s', $now ),
				'roles'          => array( 'subscriber' ),
			),
		);

		Settings\update( 'days_inactive', 1 );

		$days_until = Admin\get_days_until_purge( 77 );
		$this->assertGreaterThanOrEqual( 0, $days_until );

		// If already beyond the purge threshold, it should clamp to zero.
		$GLOBALS['tsp_users'][0]->user_registered = gmdate( 'Y-m-d H:i:s', $now - ( 90 * DAY_IN_SECONDS ) );
		Settings\update( 'days_inactive', 30 );
		$days_until = Admin\get_days_until_purge( 77 );
		$this->assertSame( 0, $days_until );
	}

}
