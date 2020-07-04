<?php
/**
 * Test class for the \Newspack_Teams_For_WC_Memberships_Access_By_IP\IP_Validator.
 *
 * @package Newspack
 */

use \Newspack_Teams_For_WC_Memberships_Access_By_IP\IP_Validator as IP_Validator;

/**
 * Class TestCaptionImgPatcher
 */
class TestIPValidator extends WP_UnitTestCase {

	/**
	 * IP_Validator.
	 *
	 * @var IP_Validator|null
	 */
	private $ip_validator;

	/**
	 * Override setUp.
	 */
	public function setUp() {
		$this->ip_validator = new IP_Validator();
	}

	/**
	 * Test validation whether a string is a valid IPv4 address.
	 *
	 * @dataProvider ip_address_validation_provider
	 *
	 * @param string $field    IP input field.
	 * @param bool   $expected Expected validation result.
	 */
	public function test_is_valid_ip_address( $field, $expected ) {
		$actual = $this->ip_validator->is_valid_ip_address( $field );
		$this->assertSame( $expected, $actual );
	}

	/**
	 * Test validation whether a string is a valid IPv4 address range.
	 *
	 * @dataProvider ip_range_validation_provider
	 *
	 * @param string $field    IP input field.
	 * @param bool   $expected Expected validation result.
	 */
	public function test_is_valid_ip_range( $field, $expected ) {
		$actual = $this->ip_validator->is_valid_ip_range( $field );
		$this->assertSame( $expected, $actual );
	}

	/**
	 * Validates is IP address is within IP range.
	 *
	 * @dataProvider ip_address_within_range_provider
	 *
	 * @param string $ip_field User entered setting value for CSV IP address or range.
	 * @param array  $ip_range An IPv4 range.
	 * @param bool   $expected Test assertion expected.
	 */
	public function test_is_ip_address_within_range( $ip_field, $ip_range, $expected ) {
		$ip_ranges = explode( '-', $ip_range );
		$actual    = $this->ip_validator->is_ip_address_within_range( $ip_field, $ip_ranges[0], $ip_ranges[1] );
		$this->assertSame( $expected, $actual );
	}

	/**
	 * Validates if IP ranges overlap.
	 *
	 * @dataProvider do_ip_ranges_overlap_provider
	 *
	 * @param array $ip_range_a An IPv4 range.
	 * @param array $ip_range_b An IPv4 range.
	 * @param bool  $expected   Test assertion expected.
	 */
	public function test_do_ip_ranges_overlap( $ip_range_a, $ip_range_b, $expected ) {
		$ip_ranges_a = explode( '-', $ip_range_a );
		$ip_ranges_b = explode( '-', $ip_range_b );
		$actual      = $this->ip_validator->do_ip_ranges_overlap( $ip_ranges_a[0], $ip_ranges_a[1], $ip_ranges_b[0], $ip_ranges_b[1] );
		$this->assertSame( $expected, $actual );
	}

	/**
	 * Validates whether different values for the IP input field overlap with an array of other IP fields.
	 *
	 * @dataProvider field_overlapping_ips_validation_provider
	 *
	 * @param string $ip_field    IPv4 value or range.
	 * @param array  $ip_haystack An array of IPv4 values or ranges, to perform overlapping check against.
	 * @param bool   $expected    Test assertion expected.
	 */
	public function test_do_various_ip_fields_overlap( $ip_field, $ip_haystack, $expected ) {
		$actual = $this->ip_validator->do_ip_fields_overlap( $ip_field, $ip_haystack );
		$this->assertSame( $expected, $actual );
	}

	/**
	 * Different sets of user inputs to test the IP_Validator::is_valid_field_ip method.
	 *
	 * @return array[]
	 */
	public function ip_address_validation_provider() {
		return [
			[ '', false ],
			[ ' ', false ],
			[ '2.1.1.1', true ],
			[ '2.1.1.254', true ],
			[ '2.1.1.354', false ],
			[ '2.1.1.1-2.1.1.5', false ],
			[ '2.1.1.5,2.1.1.1', false ],
			[ '1.1.1.1,2.1.1.1', false ],
			[ '1.1.1.1,2.1.1.1-2.1.1.5', false ],
		];
	}

	/**
	 * Different sets of user inputs to test the IP_Validator::is_valid_ip_range method.
	 *
	 * @return array[]
	 */
	public function ip_range_validation_provider() {
		return [
			[ '', false ],
			[ '-', false ],
			[ ' ', false ],
			[ ' -', false ],
			[ '1.1.1.100', false ],
			[ '2.1.1.1-', false ],
			[ '-2.1.1.1', false ],
			[ '--2.1.1.1', false ],
			[ '2.1.1.1-2.1.1.5', true ],
			[ '2.1.1.5-2.1.1.1', false ],
			[ '2.1.1.1-d.1.1.5', false ],
			[ '2.1.1.5,2.1.1.1', false ],
		];
	}

	/**
	 * Different sets of user inputs to test the IP_Validator::is_ip_address_within_range method.
	 *
	 * @return array[]
	 */
	public function ip_address_within_range_provider() {
		return [
			[ '2.2.2.100', '1.1.1.100-1.1.1.150', false ],
			[ '2.2.2.100', '2.2.2.50-2.2.2.100', true ],
			[ '2.2.2.100', '2.2.2.50-2.2.2.150', true ],
			[ '2.2.2.100', '2.2.2.100-2.2.2.150', true ],
			[ '2.2.2.100', '2.2.2.150-2.2.2.200', false ],
			[ '2.2.2.100', '3.3.3.50-3.3.3.100', false ],
		];
	}

	/**
	 * Different sets of user inputs to test the IP_Validator::do_ip_ranges_overlap method.
	 *
	 * @return array[]
	 */
	public function do_ip_ranges_overlap_provider() {
		return [
			// Needle lower boundary: below haystack range.
			// Needle upper boundary: below haystack range.
			[ '10.1.1.90-10.1.1.95', '10.1.1.100-10.1.1.115', false ],

			// Needle lower boundary: below haystack range.
			// Needle upper boundary: below haystack range, on haystack range lower boundary, inside haystack range, on haystack range upper boundary, above haystack range.
			[ '10.1.1.90-10.1.1.100', '10.1.1.100-10.1.1.115', true ],
			[ '10.1.1.90-10.1.1.110', '10.1.1.100-10.1.1.115', true ],
			[ '10.1.1.90-10.1.1.115', '10.1.1.100-10.1.1.115', true ],
			[ '10.1.1.90-10.1.1.120', '10.1.1.100-10.1.1.115', true ],

			// Needle lower boundary: on haystack range lower boundary.
			// Needle upper boundary: inside haystack range, on haystack range upper boundary, above haystack range.
			[ '10.1.1.100-10.1.1.105', '10.1.1.100-10.1.1.115', true ],
			[ '10.1.1.100-10.1.1.115', '10.1.1.100-10.1.1.115', true ],
			[ '10.1.1.100-10.1.1.120', '10.1.1.100-10.1.1.115', true ],

			// Needle lower boundary: inside haystack range.
			// Needle upper boundary: inside haystack range, on haystack range upper boundary, above haystack range.
			[ '10.1.1.101-10.1.1.105', '10.1.1.100-10.1.1.115', true ],
			[ '10.1.1.101-10.1.1.115', '10.1.1.100-10.1.1.115', true ],
			[ '10.1.1.101-10.1.1.120', '10.1.1.100-10.1.1.115', true ],

			// Needle lower boundary: on haystack range upper boundary.
			// Needle upper boundary: above haystack range.
			[ '10.1.1.115-10.1.1.120', '10.1.1.100-10.1.1.115', true ],

			// Needle lower boundary: above haystack range.
			// Needle upper boundary: above haystack range.
			[ '10.1.1.116-10.1.1.120', '10.1.1.100-10.1.1.115', false ],
			[ '10.1.1.120-10.1.1.125', '10.1.1.100-10.1.1.115', false ],
		];
	}

	/**
	 * Different sets of user inputs to test the IP_Validator::do_ip_fields_overlap method.
	 *
	 * @return array[]
	 */
	public function field_overlapping_ips_validation_provider() {
		$haystack = [
			'1.1.1.10',
			'10.1.1.100-10.1.1.115',
		];

		return [
			// 1/4 -- Testing Needle is IP address VS Haystack is IP address.
			[ '1.1.1.9', $haystack, false ],
			[ '1.1.1.10', $haystack, true ],
			[ '1.1.1.21', $haystack, false ],

			// 2/4 -- Testing Needle is IP address VS Haystack is IP range.
			[ '10.1.1.99', $haystack, false ],
			[ '10.1.1.110', $haystack, true ],
			[ '10.1.1.113', $haystack, true ],
			[ '10.1.1.115', $haystack, true ],
			[ '10.1.1.116', $haystack, false ],

			// 3/4 -- Testing Needle is IP range VS Haystack is IP address.
			[ '1.1.1.1-1.1.1.9', $haystack, false ],
			[ '1.1.1.1-1.1.1.10', $haystack, true ],
			[ '1.1.1.1-1.1.1.20', $haystack, true ],
			[ '1.1.1.9-1.1.1.20', $haystack, true ],
			[ '1.1.1.10-1.1.1.20', $haystack, true ],
			[ '1.1.1.11-1.1.1.20', $haystack, false ],

			// 4/4 -- Testing Needle is IP range VS Haystack is IP range.
			// - needle lower boundary is below haystack range.
			// - needle upper boundary is below haystack range.
			[ '10.1.1.90-10.1.1.95', $haystack, false ],
			// - needle lower boundary is below haystack range.
			// - needle upper boundary is below haystack range, on haystack range lower boundary, inside haystack range, on haystack range upper boundary, above haystack range.
			[ '10.1.1.90-10.1.1.100', $haystack, true ],
			[ '10.1.1.90-10.1.1.110', $haystack, true ],
			[ '10.1.1.90-10.1.1.115', $haystack, true ],
			[ '10.1.1.90-10.1.1.120', $haystack, true ],
			// - needle lower boundary is on haystack range lower boundary.
			// - needle upper boundary is inside haystack range, on haystack range upper boundary, above haystack range.
			[ '10.1.1.100-10.1.1.105', $haystack, true ],
			[ '10.1.1.100-10.1.1.115', $haystack, true ],
			[ '10.1.1.100-10.1.1.120', $haystack, true ],
			// - needle lower boundary is inside haystack range.
			// - needle upper boundary is inside haystack range, on haystack range upper boundary, above haystack range.
			[ '10.1.1.101-10.1.1.105', $haystack, true ],
			[ '10.1.1.101-10.1.1.115', $haystack, true ],
			[ '10.1.1.101-10.1.1.120', $haystack, true ],
			// - needle lower boundary is on haystack range upper boundary.
			// - needle upper boundary is above haystack range.
			[ '10.1.1.115-10.1.1.120', $haystack, true ],
			// - needle lower boundary is above haystack range.
			// - needle upper boundary is above haystack range.
			[ '10.1.1.116-10.1.1.120', $haystack, false ],
			[ '10.1.1.120-10.1.1.125', $haystack, false ],
		];
	}
}
