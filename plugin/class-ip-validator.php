<?php
/**
 * IP validator class.
 *
 * @package Newspack_Teams_For_WC_Memberships_Acces_By_IP
 */

namespace Newspack_Teams_For_WC_Memberships_Access_By_IP;

/**
 * Class IP_Validator
 *
 * @package Newspack_Teams_For_WC_Memberships_Access_By_IP
 */
class IP_Validator {

	/**
	 * Is string a valid IPv4 address.
	 *
	 * @param string $ip IPv4 address.
	 *
	 * @return bool
	 */
	public function is_valid_ip_address( $ip ) {
		return (bool) filter_var( $ip, FILTER_VALIDATE_IP );
	}

	/**
	 * Is string a valid IPv4 address range.
	 *
	 * @param string $value IPv4 address range.
	 *
	 * @return bool
	 */
	public function is_valid_ip_range( $value ) {
		$ips = explode( '-', $value );
		if ( 2 != count( $ips ) ) {
			return false;
		}

		if ( ! $this->is_valid_ip_address( $ips[0] ) || ! $this->is_valid_ip_address( $ips[1] ) ) {
			return false;
		}

		if ( ip2long( $ips[0] ) >= ip2long( $ips[1] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Checks wheather two IP ranges overlap.
	 *
	 * @param string $range_a_lower IP address range A lower value.
	 * @param string $range_a_upper IP address range A upper value.
	 * @param string $range_b_lower IP address range B lower value.
	 * @param string $range_b_upper IP address range B upper value.
	 *
	 * @return bool|null True or false whether they overlap, null on invalid values.
	 */
	public function do_ip_ranges_overlap( $range_a_lower, $range_a_upper, $range_b_lower, $range_b_upper ) {
		if (
			! $this->is_valid_ip_address( $range_a_lower )
			|| ! $this->is_valid_ip_address( $range_a_upper )
			|| ! $this->is_valid_ip_address( $range_b_lower )
			|| ! $this->is_valid_ip_address( $range_b_upper )
		) {
			return null;
		}

		// Check needle lower boundary.
		if (
			ip2long( $range_a_lower ) >= ip2long( $range_b_lower ) &&
			ip2long( $range_a_lower ) <= ip2long( $range_b_upper )
		) {
			return true;
		}

		// Check needle upper boundary.
		if (
			ip2long( $range_a_upper ) >= ip2long( $range_b_lower ) &&
			ip2long( $range_a_upper ) <= ip2long( $range_b_upper )
		) {
			return true;
		}

		// Check haystack lower boundary.
		if (
			ip2long( $range_b_lower ) >= ip2long( $range_a_lower ) &&
			ip2long( $range_b_lower ) <= ip2long( $range_a_upper )
		) {
			return true;
		}

		// Check haystack upper boundary.
		if (
			ip2long( $range_b_upper ) >= ip2long( $range_a_lower ) &&
			ip2long( $range_b_upper ) <= ip2long( $range_a_upper )
		) {
			return true;
		}

		return false;
	}

	/**
	 * Checks whether an IP is inside an IP range.
	 *
	 * @param string $ip             IPv4 address.
	 * @param string $ip_range_lower IPv4 range lower boundary.
	 * @param string $ip_range_upper IPv4 range upper boundary.
	 *
	 * @return bool|null null if values are invalid, true and false as 'is valid' result.
	 */
	public function is_ip_address_within_range( $ip, $ip_range_lower, $ip_range_upper ) {
		if ( ! $this->is_valid_ip_address( $ip ) || ! $this->is_valid_ip_address( $ip_range_lower ) || ! $this->is_valid_ip_address( $ip_range_upper ) ) {
			null;
		}

		if (
			ip2long( $ip ) >= ip2long( $ip_range_lower )
			&& ip2long( $ip ) <= ip2long( $ip_range_upper )
		) {
			return true;
		}

		return false;
	}

	/**
	 * Checks whether a needle IPv4 value or range overlaps with any of the IPv4 values or ranges in the haystack.
	 *
	 * @param string $needle   IPv4 address or range.
	 * @param array  $haystack An array of IPv4 addresses or ranges.
	 * @return bool|null null if values are invalid, true and false as 'is valid' result.
	 */
	public function do_ip_fields_overlap( $needle, $haystack ) {
		if ( ! $this->is_valid_ip_address( $needle ) && ! $this->is_valid_ip_range( $needle ) ) {
			return null;
		}

		// Run through both needle and haystack as either IP values, or IP ranges.
		foreach ( $haystack as $haystack_value ) {

			if ( $this->is_valid_ip_address( $needle ) ) {

				// Needle is IP address, Haystack is IP address.
				if ( $this->is_valid_ip_address( $haystack_value ) ) {

					if ( ip2long( $needle ) == ip2long( $haystack_value ) ) {
						return true;
					}

					// Needle is IP address, Haystack is IP range.
				} elseif ( $this->is_valid_ip_range( $haystack_value ) ) {

					$haystack_ranges = explode( '-', $haystack_value );
					if ( $this->is_ip_address_within_range( $needle, $haystack_ranges[0], $haystack_ranges[1] ) ) {
						return true;
					}
				} else {
					return null;
				}
			} elseif ( $this->is_valid_ip_range( $needle ) ) {

				$needle_ranges = explode( '-', $needle );

				// Needle is IP range, Haystack is IP address.
				if ( $this->is_valid_ip_address( $haystack_value ) ) {

					if ( $this->is_ip_address_within_range( $haystack_value, $needle_ranges[0], $needle_ranges[1] ) ) {
						return true;
					}

					// Needle is IP range, Haystack is IP range.
				} elseif ( $this->is_valid_ip_range( $haystack_value ) ) {

					$haystack_ranges = explode( '-', $haystack_value );
					if ( $this->do_ip_ranges_overlap( $needle_ranges[0], $needle_ranges[1], $haystack_ranges[0], $haystack_ranges[1] ) ) {
						return true;
					}
				} else {
					return null;
				}
			} else {
				return null;
			}
		}

		return false;
	}
}
