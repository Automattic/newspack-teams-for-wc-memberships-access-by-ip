<?php
/**
 * Plugin Name: Newspack Teams for WooCommerce Memberships Access by IP
 * Description: Allows public access to Team Subscription content by IP.
 * Version: 0.1
 * Text Domain: newspack-teams-for-wc-memberships-access-by-ip
 * Author: Automattic
 * Author URI: https://newspack.blog/
 * License: GPL2
 *
 * @package Newspack_Plugin
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'NEWSPACK_TEAMS_FOR_WC_MEMBERSHIPS_ACCESS_BY_IP_FILE' ) ) {
	define( 'NEWSPACK_TEAMS_FOR_WC_MEMBERSHIPS_ACCESS_BY_IP_FILE', __FILE__ );
}

require_once dirname( NEWSPACK_TEAMS_FOR_WC_MEMBERSHIPS_ACCESS_BY_IP_FILE ) . '/plugin/class-plugin.php';
require_once dirname( NEWSPACK_TEAMS_FOR_WC_MEMBERSHIPS_ACCESS_BY_IP_FILE ) . '/plugin/class-ip-validator.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

\Newspack_Teams_For_WC_Memberships_Access_By_IP\Plugin::get_instance(
	new \Newspack_Teams_For_WC_Memberships_Access_By_IP\IP_Validator()
);
