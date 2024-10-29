<?php

namespace Advanced_Coupon_For_WooCommerce;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Main class plugin
 */
final class Main {

	/**
	 * Hold the current instance of plugin
	 * 
	 * @since 1.0.0
	 * @var Main
	 */
	private static $instance = null;

	/**
	 * Get instance of current class
	 * 
	 * @since 1.0.0
	 * @return Main
	 */
	public static function get_instance() {
		if (is_null(self::$instance)) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Hold admin class
	 * 
	 * @since 1.0.0
	 * @var Admin
	 */
	public $admin = null;

	/**
	 * Conditions template class
	 * 
	 * @since 1.0.0
	 * @var array
	 */
	public $conditions = [];

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->include_files();
		$this->init();
		$this->hooks();
	}

	/**
	 * Load plugin files
	 * 
	 * @version 1.0.0
	 * @return void
	 */
	public function include_files() {
		require_once ADVANCED_COUPON_FOR_WOOCOMMERCE_PATH . 'inc/class-utils.php';
		require_once ADVANCED_COUPON_FOR_WOOCOMMERCE_PATH . 'inc/class-admin.php';
		require_once ADVANCED_COUPON_FOR_WOOCOMMERCE_PATH . 'inc/class-condition-cart.php';
		require_once ADVANCED_COUPON_FOR_WOOCOMMERCE_PATH . 'inc/class-condition-date.php';
		require_once ADVANCED_COUPON_FOR_WOOCOMMERCE_PATH . 'inc/class-condition-user.php';
		require_once ADVANCED_COUPON_FOR_WOOCOMMERCE_PATH . 'inc/class-condition-cart-products.php';
		require_once ADVANCED_COUPON_FOR_WOOCOMMERCE_PATH . 'inc/class-condition-billing-shipping.php';
	}

	/**
	 * Initialize classes
	 * 
	 * @since 1.0.0
	 */
	public function init() {
		$this->admin = new Admin();
		$this->conditions['cart'] = new Condition\Cart();
		$this->conditions['date'] = new Condition\Date();
		$this->conditions['user'] = new Condition\User();
		$this->conditions['cart_products'] = new Condition\Cart_Products();
		$this->conditions['billing_shipping'] = new Condition\Billing_Shipping();
	}

	/**
	 * Add hooks of plugin
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public function hooks() {
		add_action('admin_notices', array($this, 'disabled_coupon_feature'));
		add_filter('plugin_action_links', array($this, 'add_plugin_links'), 10, 2);
		add_filter('woocommerce_coupon_get_amount', array($this, 'woocommerce_get_shop_coupon_data'), 200, 2);
	}

	/**
	 * Show notice for not enabled coupon feature
	 * 
	 * @since 1.0.1
	 */
	public function disabled_coupon_feature() {
		if (Utils::enabled_woocommerce_coupon()) {
			return;
		}

		$notice = sprintf(
			/* translators: 1 for plugin name, 2 for link */
			esc_html__('%1$s need to enable WooCommerce coupon. Please %2$s and enable coupons.', 'advanced-coupon-for-woocommerce'),
			'<strong>Advanced Coupon for WooCommerce</strong>',
			'<a href="' . esc_url(admin_url('admin.php?page=wc-settings')) . '">' . __('go here', 'advanced-coupon-for-woocommerce') . '</a>'
		);

		printf('<div class="notice notice-warning"><p>%1$s</p></div>', wp_kses_post($notice));
	}

	/**
	 * Add add coupon link in plugin links
	 * 
	 * @since 1.0.1
	 * @return array
	 */
	public function add_plugin_links($actions, $plugin_file) {
		if (ADVANCED_COUPON_FOR_WOOCOMMERCE_BASENAME == $plugin_file) {
			$new_links = array();
			if (Utils::enabled_woocommerce_coupon()) {
				$new_links[] = sprintf('<a href="%s">%s</a>', admin_url('edit.php?post_type=shop_coupon'), __('Add Coupon', 'advanced-coupon-for-woocommerce'));
			}

			$actions = array_merge($new_links, $actions);
		}

		return $actions;
	}

	/**
	 * Update coupon amount
	 * 
	 * @since 1.0.0
	 * @return float
	 */
	public function woocommerce_get_shop_coupon_data($amount, $coupon) {
		if (is_admin()) {
			return $amount;
		}

		$tiered_data = Utils::get_coupon_data($coupon->get_id());
		if (true === $tiered_data['disabled']) {
			return $amount;
		}

		if (isset($tiered_data['start_tiered_discount']) && 'start_after_date' === $tiered_data['start_tiered_discount']) {
			$start_time = strtotime($tiered_data['start_after_date']);
			if (false === $start_time || $start_time > current_time('timestamp')) {
				return $amount;
			}
		}

		if (!isset($tiered_data['discount_tiers']) || !is_array($tiered_data['discount_tiers']) || count($tiered_data['discount_tiers']) == 0) {
			return $amount;
		}

		$discount_tiers = array_map(function ($tier) {
			if (!isset($tier['conditions']) || !is_array($tier['conditions'])) {
				$tier['conditions'] = array();
			}

			$match_conditions = array_filter($tier['conditions'], function ($condition) {
				return apply_filters('advanced_coupon_for_woocommerce/condition_matched', false, wp_parse_args($condition, Utils::get_condition_values()));
			});

			$tier['match_conditions'] = count($match_conditions);

			return $tier;
		}, $tiered_data['discount_tiers']);

		$matched_tiers = array_filter($discount_tiers, function ($current_tier) {
			if (0 === count($current_tier['conditions'])) {
				return false;
			}

			if ('match_any' == $current_tier['condition_relationship'] && $current_tier['match_conditions'] > 0) {
				return true;
			}

			if ('match_all' == $current_tier['condition_relationship'] && $current_tier['match_conditions'] === count($current_tier['conditions'])) {
				return true;
			}

			return false;
		});

		if (count($matched_tiers) === 0) {
			return $amount;
		}

		$current_tier = current($matched_tiers);
		if (count($matched_tiers) > 1) {
			$tier_priority = !empty($tiered_data['match_discount_tier_priority']) ? $tiered_data['match_discount_tier_priority'] : 'highest_discount';

			usort($matched_tiers, function ($a, $b) {
				return $a['discount'] > $b['discount'] ? 1 : -1;
			});

			if ('lowest_discount' === $tier_priority) {
				$current_tier = reset($matched_tiers);
			} else {
				$current_tier = end($matched_tiers);
			}
		}

		$discount = floatval($current_tier['discount']);
		if ('fixed_discount' === $current_tier['discount_type']) {
			return $discount;
		}

		if ('percentage_discount' === $current_tier['discount_type']) {
			if ($discount <= 0) {
				return 0;
			}

			$subtotal = (float) WC()->cart->get_subtotal();
			return floatval($subtotal * $discount / 100);
		}

		return $amount;
	}
}

Main::get_instance();
