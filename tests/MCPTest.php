<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use TutorLMS_Analytics\MCP;

if ( ! function_exists( 'wp_register_ability_category' ) ) {
	function wp_register_ability_category( $name, $args ) {
		$GLOBALS['mock_ability_categories'][ $name ] = $args;
	}
}

if ( ! function_exists( 'wp_register_ability' ) ) {
	function wp_register_ability( $name, $args ) {
		$GLOBALS['mock_abilities'][ $name ] = $args;
	}
}

final class MCPTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['mock_ability_categories'] = array();
		$GLOBALS['mock_abilities']          = array();
	}

	public function test_registers_analytics_ability(): void {
		MCP::register_category();
		MCP::register_abilities();

		$this->assertArrayHasKey( 'tutorlms-analytics', $GLOBALS['mock_ability_categories'] );
		$this->assertArrayHasKey( 'tutorlms-analytics/get-section', $GLOBALS['mock_abilities'] );
		$this->assertTrue( $GLOBALS['mock_abilities']['tutorlms-analytics/get-section']['meta']['annotations']['readonly'] );
	}

	public function test_permission_matches_analytics_permissions(): void {
		$GLOBALS['mock_user_can'] = array(
			'manage_tutor'     => false,
			'manage_options'   => false,
			'tutor_instructor' => false,
		);
		$this->assertFalse( MCP::can_view() );

		$GLOBALS['mock_user_can']['tutor_instructor'] = true;
		$this->assertTrue( MCP::can_view() );
	}
}
