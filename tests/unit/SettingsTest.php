<?php
declare(strict_types=1);

namespace TheSubscriberPurge\Tests;

use PHPUnit\Framework\TestCase;
use TheSubscriberPurge\Settings;

final class SettingsTest extends TestCase {
	protected function setUp(): void {
		tsp_reset_state();
	}

	public function test_get_returns_default_when_missing(): void {
		$result = Settings\get( 'days_inactive', 30 );
		$this->assertSame( 30, $result );
	}

	public function test_update_stores_value_without_autoload(): void {
		Settings\update( 'days_inactive', 45 );

		$value    = Settings\get( 'days_inactive', 0 );
		$autoload = $GLOBALS['tsp_options'][ Settings\OPTION_NAME ]['autoload'] ?? null;

		$this->assertSame( 45, $value );
		$this->assertFalse( $autoload );
	}

	public function test_get_handles_non_array_option_value(): void {
		// Force the stored option into a non-array value to exercise the fallback.
		$GLOBALS['tsp_options'][ Settings\OPTION_NAME ] = array(
			'value'    => 'not-an-array',
			'autoload' => 'no',
		);

		$result_email = Settings\get( 'send_emails', true );
		$result_days  = Settings\get( 'days_inactive', 30 );

		$this->assertTrue( $result_email );
		$this->assertSame( 30, $result_days );
	}

	public function test_update_overwrites_non_array_option_value(): void {
		$GLOBALS['tsp_options'][ Settings\OPTION_NAME ] = array(
			'value'    => 'invalid',
			'autoload' => 'no',
		);

		Settings\update( 'send_emails', false );

		$this->assertSame( false, Settings\get( 'send_emails', true ) );
		$this->assertFalse( $GLOBALS['tsp_options'][ Settings\OPTION_NAME ]['autoload'] );
	}

	public function test_update_preserves_existing_keys(): void {
		Settings\update( 'days_inactive', 10 );
		Settings\update( 'send_emails', true );
		Settings\update( 'days_inactive', 20 );

		$this->assertSame( 20, Settings\get( 'days_inactive', 0 ) );
		$this->assertTrue( Settings\get( 'send_emails', false ) );
		$this->assertFalse( $GLOBALS['tsp_options'][ Settings\OPTION_NAME ]['autoload'] );
	}

	public function test_update_returns_false_when_option_save_fails(): void {
		Settings\update( 'days_inactive', 10 );
		$this->assertSame( 10, Settings\get( 'days_inactive', 0 ) );

		$GLOBALS['tsp_force_update_option_fail'] = true;
		$result = Settings\update( 'days_inactive', 15 );

		$this->assertFalse( $result );
		$this->assertSame( 10, Settings\get( 'days_inactive', 0 ) );
	}
}
