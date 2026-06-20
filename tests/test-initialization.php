<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class InitializationTest extends TestCase {

	public function test_plugin_constants_are_defined(): void {
		$this->assertTrue( defined( 'TUTORLMS_ANALYTICS_VERSION' ), 'TUTORLMS_ANALYTICS_VERSION is not defined.' );
		$this->assertTrue( defined( 'TUTORLMS_ANALYTICS_DIR' ), 'TUTORLMS_ANALYTICS_DIR is not defined.' );
		$this->assertTrue( defined( 'TUTORLMS_ANALYTICS_URL' ), 'TUTORLMS_ANALYTICS_URL is not defined.' );
	}

	public function test_plugin_classes_exist(): void {
		$this->assertTrue( class_exists( 'TutorLMS_Analytics\Admin_Menu' ), 'Admin_Menu class does not exist.' );
		$this->assertTrue( class_exists( 'TutorLMS_Analytics\REST_API' ), 'REST_API class does not exist.' );
		$this->assertTrue( class_exists( 'TutorLMS_Analytics\Database' ), 'Database class does not exist.' );
		$this->assertTrue( class_exists( 'TutorLMS_Analytics\Export_Handler' ), 'Export_Handler class does not exist.' );
	}
}
