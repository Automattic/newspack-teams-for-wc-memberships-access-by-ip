<?php
/**
 * Main plugin class.
 *
 * @package Newspack_Teams_For_WC_Memberships_Acces_By_IP
 */

namespace Newspack_Teams_For_WC_Memberships_Access_By_IP;

use \Newspack_Teams_For_WC_Memberships_Access_By_IP\IP_Validator as IP_Validator;
use \SkyVerge\WooCommerce\Memberships\Teams\Team as Team;

/**
 * Newspack Teams for WooCommerce Memberships Access by IP main Plugin class.
 *
 * @package Newspack_Teams_For_WC_Memberships_Access_By_IP
 */
class Plugin {

	/**
	 * The ID of the new WooComm section.
	 */
	const SETTING_ID = 'wc_team_memberships_access_by_ip';

	/**
	 * This plugin.
	 *
	 * @var Plugin|null
	 */
	private static $instance;

	/**
	 * IP_Validator.
	 *
	 * @var IP_Validator|null
	 */
	private $ip_validator;

	/**
	 * Marks whether validation happened when trying to save the settings.
	 *
	 * @var bool
	 */
	private $validation_error_happened;

	/**
	 * Plugin constructor.
	 *
	 * @param IP_Validator $ip_validator IP_Validator.
	 */
	private function __construct( $ip_validator ) {
		$this->ip_validator = $ip_validator;

		// Only register if Teams for WooComm Memberships is active.
		if ( ! is_plugin_active( 'woocommerce-memberships-for-teams/woocommerce-memberships-for-teams.php' ) ) {
			return;
		}

		$this->set_up_interface();
		$this->register_logic();
	}

	/**
	 * Singleton get.
	 *
	 * @param IP_Validator $ip_validator IP_Validator.
	 *
	 * @return Plugin This plugin.
	 */
	public static function get_instance( $ip_validator ) {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self( $ip_validator );
		}

		return self::$instance;
	}

	/**
	 * Registers logic which enables public access to post if the client is accessing it from an IP belonging to a Team Membership
	 * which has access to that particular content.
	 */
	private function register_logic() {
		add_filter( 'the_post', [ $this, 'set_post_public_by_ip' ], -1, 2 );
	}

	/**
	 * Checks whether the client is visiting from an IP associated with a Team Membership, and if that particular Plan can access
	 * the Post, it makes the Post public to the client (no login or registration needed).
	 *
	 * @param \WP_Post $post Post.
	 *
	 * @return $post
	 */
	public function set_post_public_by_ip( $post ) {

		// Early return -- we're only allowing access to public Posts.
		if ( 'post' !== $post->post_type || 'publish' !== $post->post_status ) {
			return $post;
		}

		// Get our list of IPs per Team Memberships.
		$ip_entry_by_teams = get_option( self::SETTING_ID, [] );
		if ( empty( $ip_entry_by_teams ) ) {
			return $post;
		}

		$client_ip = $this->get_client_ip();
		if ( null === $client_ip ) {
			return $post;
		}

		// Check whether client's IP belongs to any of the existing Team Memberships'.
		$team_id = null;
		foreach ( $ip_entry_by_teams as $team_id_with_ips => $ip_entry ) {
			if ( $this->ip_validator->do_ip_fields_overlap( $client_ip, explode( ',', $ip_entry ) ) ) {
				$team_id = $team_id_with_ips;
				break;
			}
		}
		if ( null === $team_id ) {
			return $post;
		}

		// Check whether this Post is accessible by this Team Membership's Plan.
		$team               = new Team( $team_id );
		$plan               = $team->get_plan();
		$restricted_content = $plan->get_restricted_content();
		if ( null === $restricted_content ) {
			return $post;
		}
		$can_plan_access_this_post = in_array(
			$post->ID,
			$restricted_content->query_vars['post__in']
		);

		// Make this Post public.
		if ( $can_plan_access_this_post ) {
			add_filter(
				'wc_memberships_is_post_public',
				function( $is_post_public, $post_id, $post_type ) use ( $post ) {
					if ( $post->ID === $post_id ) {
						return true;
					}

					return $is_post_public;
				},
				10,
				3
			);
		}

		return $post;
	}

	/**
	 * Get's client's IP.
	 *
	 * @return string|null Client IP.
	 */
	private function get_client_ip() {
		$ip = null;

		// phpcs:disable -- ignore -- allow use of $_SERVER.
		// 'REMOTE_ADDR' is safest to use, since these other two params could be set by the client.
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( $_SERVER['REMOTE_ADDR'] );
		} elseif ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = sanitize_text_field( $_SERVER['HTTP_CLIENT_IP'] );
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip = sanitize_text_field( $_SERVER['HTTP_X_FORWARDED_FOR'] );
		}
		// phpcs:enable

		return $ip;
	}

	/**
	 * Defines the interface -- adds a new section under WooCommerce > Settings > Memberships.
	 */
	private function set_up_interface() {
		add_filter( 'woocommerce_get_sections_memberships', [ $this, 'add_section' ] );
		add_filter( 'woocommerce_get_settings_memberships', [ $this, 'get_settings' ], 10, 2 );
		add_filter( 'admin_init', [ $this, 'clean_up_old_ips' ] );
		add_filter(
			'woocommerce_admin_settings_sanitize_option_' . self::SETTING_ID,
			[ $this, 'validate_fields_on_update' ],
			10,
			3
		);
	}

	/**
	 * Before we save or use the IPs, we need to do a clean up and delete unused IPs.
	 *
	 * If a Team gets removed, WooComm does not delete previously saved Setting fields belonging to this same ID-group. Letting
	 * old/unused IPs associated to non-existing Team Memberships linger in the DB would reflect negatively on the access logic.
	 *
	 * @param string $option Actoin param, option.
	 */
	public function clean_up_old_ips( $option ) {
		$current_options = get_option( self::SETTING_ID, [] );
		if ( empty( $current_options ) ) {
			return;
		}

		$teams = $this->get_teams();
		if ( empty( $teams ) ) {
			delete_option( self::SETTING_ID );
			return;
		}

		$team_ids = [];
		foreach ( $this->get_teams() as $team ) {
			$team_ids[] = $team->get_id();
		}

		$updated_options = [];
		foreach ( $current_options as $current_option_team_id => $current_option ) {
			if ( in_array( $current_option_team_id, $team_ids ) ) {
				$updated_options[ $current_option_team_id ] = $current_option;
			}
		}

		if ( count( $updated_options ) != count( $current_options ) ) {
			update_option( self::SETTING_ID, $updated_options, 'no' );
		}
	}

	/**
	 * Sets interface -- creates a section beneath the Memberships tab.
	 *
	 * @param array $sections Action param, sections.
	 *
	 * @return array
	 */
	public function add_section( $sections ) {
		$sections['newspack_teams_for_wc_memberships_access_by_ip'] = __(
			'Team Access by IP',
			'newspack_teams_for_wc_memberships_access_by_ip'
		);
		return $sections;
	}

	/**
	 * Sets interface -- adds settings to the new page.
	 *
	 * @param array  $settings        Action param, settings.
	 * @param string $current_section Action param, current section.
	 *
	 * @return array
	 */
	public function get_settings( $settings, $current_section ) {
		if ( 'newspack_teams_for_wc_memberships_access_by_ip' !== $current_section ) {
			return $settings;
		}

		$msg_description = '<p>' .
			__(
				'Here you may specify IP addresses for Team Memberships. If a User visits the site from those particular IP addresses, they will gain public access (with no registration or login required) to all the Posts available to this particular Team Membership Plan.',
				'newspack-teams-for-wc-memberships-access-by-ip'
			) .
			'</p>' .
			'<p>' .
			__(
				'You may enter Coma Separated Values of IPv4 addresses and/or IPv4 address ranges.',
				'newspack-teams-for-wc-memberships-access-by-ip'
			) .
			' ' .
			__(
				'Here are some examples of valid of inputs:',
				'newspack-teams-for-wc-memberships-access-by-ip'
			) .
			'</p>' .
			'<ul style="list-style-type: disc; padding-left: 2em;">' .
				'<li>' .
					'<code>1.2.3.4,10.10.10.23</code> - ' .
					__(
						'one or more coma separated IPv4 addresses',
						'newspack-teams-for-wc-memberships-access-by-ip'
					) .
				'</li>' .
				'<li>' .
					'<code>10.10.10.100-10.10.10.120</code> - ' .
					__(
						'a range of IPv4 addresses',
						'newspack-teams-for-wc-memberships-access-by-ip'
					) .
				'</li>' .
				'<li>' .
					'<code>1.1.1.1,2.2.2.100-2.2.2.120,3.3.3.3</code> - ' .
					__(
						'combination of IP addresses and ranges',
						'newspack-teams-for-wc-memberships-access-by-ip'
					) .
				'</li>' .
			'</ul>' .
			'<p>' .
			__(
				'No duplicate or overlapping entries are allowed.',
				'newspack-teams-for-wc-memberships-access-by-ip'
			) .
			'</p>';

		$settings_custom   = [];
		$settings_custom[] = [
			'id'   => 'newspack_teams_for_wc_memberships_access_by_ip',
			'name' => __(
				'Enable Public Access to Team Memberhip Content by IP',
				'newspack_teams_for_wc_memberships_access_by_ip'
			),
			'type' => 'title',
			'desc' => $msg_description,
		];

		foreach ( $this->get_teams() as $team ) {
			$settings_custom[] = [
				'type'  => 'text',
				'id'    => sprintf( '%s[%d]', self::SETTING_ID, $team->get_id() ),
				'class' => 'input-text wide-input messages-group-posts',
				'name'  => sprintf( '%s (ID %d)', $team->get_name(), $team->get_id() ),
			];
		}

		$settings_custom[] = [
			'type' => 'sectionend',
			'id'   => 'newspack_teams_for_wc_memberships_access_by_ip',
		];

		return $settings_custom;
	}

	/**
	 * Validation of all the IP settings before they're saved.
	 *
	 * This validation needs to read the $_POST directly, because of two reasons:
	 *  - the actual \WC_Admin_Settings::save_fields works with $_POST, too,
	 *  - and while there is an action is called 'woocommerce_admin_settings_sanitize_option_' . self::SETTING_ID which feeds an
	 *    individual value being saved one by one, there doesn't seem to be a hook which provides access to all the posted values
	 *    at once, before they're saved.
	 *
	 * @param string $value     Action param, value.
	 * @param string $option    Action param, option.
	 * @param string $raw_value Action param, raw value.
	 *
	 * @return null|string The `woocommerce_admin_settings_sanitize_option_[OPTION_NAME]` filter expects $value to be returned
	 *                     if everything's OK, or if null is returned it skips saving this value.
	 */
	public function validate_fields_on_update( $value, $option, $raw_value ) {

		// If validation error happened for a previous field, don't update any of the remaining ones.
		if ( true === $this->validation_error_happened ) {
			return null;
		}

		// Get the Team ID for this $value.
		preg_match( '/.+\[(?<id>\d+)\]/', $option['id'], $matches );
		if ( ! isset( $matches['id'] ) ) {
			return $value;
		}
		$team_id = $matches['id'];

		// Get all other posted values.
		// phpcs:ignore -- we must use $_POST, since that's what \WC_Admin_Settings::save_fields works with directly.
		if ( ! isset( $_POST['wc_team_memberships_access_by_ip'] ) ) {
			return $value;
		}

		// Sanitize $_POST array fields.
		// phpcs:ignore -- we must use $_POST, since that's what \WC_Admin_Settings::save_fields works with directly.
		foreach ( $_POST['wc_team_memberships_access_by_ip'] as $k => $v ) {
			$all_posted_fields[ sanitize_text_field( $k ) ] = sanitize_text_field( $v );
		}
		$all_posted_fields   = $_POST['wc_team_memberships_access_by_ip'];
		$other_posted_fields = $all_posted_fields;
		unset( $other_posted_fields[ $team_id ] );

		// Validate.
		$exploded_values = explode( ',', $value );
		foreach ( $exploded_values as $exploded_value ) {

			// Check if the saved field is of correct type.
			if ( ! $this->is_valid_settings_field_ip( $exploded_value ) ) {
				\WC_Admin_Settings::add_error(
					__(
						'ERROR',
						'newspack_teams_for_wc_memberships_access_by_ip'
					) .
					' ' .
					__(
						'The value you provided is not a valid coma separated IPv4 address or IPv4 address range:',
						'newspack_teams_for_wc_memberships_access_by_ip'
					) .
					': ' . $value
				);

				return null;
			}

			// Check if the IP has been used twice, or that any of them overlap.
			foreach ( $other_posted_fields as $posted_field ) {

				if ( $this->ip_validator->do_ip_fields_overlap( $exploded_value, [ $posted_field ] ) ) {

					$this->validation_error_happened = true;

					\WC_Admin_Settings::add_error(
						__(
							'ERROR',
							'newspack_teams_for_wc_memberships_access_by_ip'
						) .
						' ' .
						__(
							'The following IP values/ranges overlap:',
							'newspack_teams_for_wc_memberships_access_by_ip'
						) .
						sprintf( ": '%s' and '%s'.", $exploded_value, $posted_field )
					);

					return null;
				}
			}
		}

		return $value;
	}

	/**
	 * Validates the User input field for IP. It's a CSV formatted IPv4 address or IPv4 address range.
	 *
	 * @param string $field User made entry to the settings field "IPv4 address or address range".
	 *
	 * @return bool
	 */
	public function is_valid_settings_field_ip( $field ) {
		// Allow empty field as value.
		if ( empty( $field ) ) {
			return true;
		}

		// Explode CSVs.
		$values = explode( ',', $field );
		foreach ( $values as $value ) {
			// Is IPv4.
			if ( $this->ip_validator->is_valid_ip_address( $value ) ) {
				continue;
			}

			// Is IPv4 range.
			if ( $this->ip_validator->is_valid_ip_range( $value ) ) {
				continue;
			}

			return false;
		}

		return true;
	}

	/**
	 * Fetches all WC Team Memberships. Can optionally filter by IDs, too.
	 *
	 * @param array $team_ids Optional, array of team IDs to fetch only.
	 *
	 * @return array Array of \SkyVerge\WooCommerce\Memberships\Teams\Team Objects.
	 */
	private function get_teams( $team_ids = [] ) {
		$teams = array();

		$args = array(
			// phpcs:ignore -- allow fetching all values.
			'numberposts' => -1,
			'post_type'   => 'wc_memberships_team',
		);
		if ( ! empty( $team_ids ) ) {
			$args['post__in'] = $team_ids;
		}
		$query = new \WP_Query( $args );
		if ( ! $query->have_posts() ) {
			return $teams;
		}

		$teams_ids = array();
		while ( $query->have_posts() ) {
			$query->the_post();
			$teams_ids[] = get_the_ID();
		}
		if ( empty( $teams_ids ) ) {
			return $teams;
		}

		foreach ( $teams_ids as $team_id ) {
			$teams[] = wc_memberships_for_teams_get_team( $team_id );
		}

		return $teams;
	}
}
