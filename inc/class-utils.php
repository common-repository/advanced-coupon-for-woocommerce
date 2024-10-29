<?php

namespace Advanced_Coupon_For_WooCommerce;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Utilities class 
 */
class Utils {

	/**
	 * Check if enabled woocommerce coupon
	 * 
	 * @since 1.0.1
	 * @return boolean
	 */
	public static function enabled_woocommerce_coupon() {
		return get_option('woocommerce_enable_coupons') === 'yes';
	}

	/**
	 * Check if pro version installed
	 * 
	 * @since 1.0.0
	 * @return boolean
	 */
	public static function has_pro_installed() {
		return file_exists(WP_PLUGIN_DIR . '/advanced-coupon-for-woocommerce-pro/advanced-coupon-for-woocommerce-pro.php');
	}

	/**
	 * Get condition operators
	 * 
	 * @since 1.0.0
	 * @return array
	 */
	public static function get_operators($operators = array()) {
		$supported_operators = array(
			'equal_to' => __('Equal To ( = )', 'advanced-coupon-for-woocommerce'),
			'less_than' => __('Less than ( < )', 'advanced-coupon-for-woocommerce'),
			'less_than_or_equal' => __('Less than or equal ( <= )', 'advanced-coupon-for-woocommerce'),
			'greater_than_or_equal' => __('Greater than or equal ( >= )', 'advanced-coupon-for-woocommerce'),
			'greater_than' => __('Greater than ( > )', 'advanced-coupon-for-woocommerce'),
			'in_list' => __('In list', 'advanced-coupon-for-woocommerce'),

			'between' => __('Between', 'advanced-coupon-for-woocommerce'),
			'not_between' => __('Not Between', 'advanced-coupon-for-woocommerce'),

			'any_in_list' => __('Any in list', 'advanced-coupon-for-woocommerce'),
			'all_in_list' => __('All in list', 'advanced-coupon-for-woocommerce'),
			'not_in_list' => __('Not in list', 'advanced-coupon-for-woocommerce'),

			'before' => __('Before', 'advanced-coupon-for-woocommerce'),
			'after' => __('After', 'advanced-coupon-for-woocommerce'),
		);

		$return_operators = [];
		while ($key = current($operators)) {
			if (isset($supported_operators[$key])) {
				$return_operators[$key] = $supported_operators[$key];
			}

			next($operators);
		}

		return $return_operators;
	}

	/**
	 * Get condition operators dropdown
	 * 
	 * @since 1.0.0
	 * @return array
	 */
	public static function get_operators_options($args = array()) {
		$operators = self::get_operators($args);

		$options = array_map(function ($label, $key) {
			return sprintf('<option value="%s">%s</option>', $key, $label);
		}, $operators, array_keys($operators));

		echo wp_kses(implode('', $options), array(
			'option' => array(
				'value' => true
			)
		));
	}

	/**
	 * Get coupon data by a coupon id
	 * 
	 * @since 1.0.0
	 * @param int coupon_id
	 */
	public static function get_coupon_data($coupon_id) {
		$coupon_data = wp_parse_args(json_decode(get_post_meta($coupon_id, 'advanced_coupon_for_woocommerce_settings', true), true), array(
			'disabled' => false,
			'discount_tiers' => array(),
			'start_tiered_discount' => 'immediately',
			'start_after_date' => '',
		));

		$discount_tiers = isset($coupon_data['discount_tiers']) && is_array($coupon_data['discount_tiers']) ? $coupon_data['discount_tiers'] : array();

		$discount_tiers = array_map(function ($tier) {
			if (!isset($tier['conditions']) || !is_array($tier['conditions'])) {
				$tier['conditions'] = array();
			}

			$tier['conditions'] = array_map(function ($condition) {
				$condition = wp_parse_args($condition, self::get_condition_values());

				if ('between_values' == $condition['operator']) {
					$condition['operator'] = 'between';
				}

				if ('in_tags' == $condition['cart_value_type']) {
					$condition['cart_value_type'] = 'in_product_tag';
					if (isset($condition['cart_tags']) && is_array($condition['cart_tags'])) {
						$condition['cart_product_tag'] = $condition['cart_tags'];
					}
				}

				if ('in_categories' == $condition['cart_value_type']) {
					$condition['cart_value_type'] = 'in_product_cat';
					if (isset($condition['cart_categories']) && is_array($condition['cart_categories'])) {
						$condition['cart_product_cat'] = $condition['cart_categories'];
					}
				}

				if ('in_shipping_classes' == $condition['cart_value_type']) {
					$condition['cart_value_type'] = 'in_product_shipping_class';
					if (isset($condition['cart_shipping_classes']) && is_array($condition['cart_shipping_classes'])) {
						$condition['cart_product_shipping_class'] = $condition['cart_shipping_classes'];
					}
				}


				if ('cart_products:categories' === $condition['type']) {
					$condition['type'] = 'cart_products:product_cat';
					if (isset($condition['categories']) && is_array($condition['categories'])) {
						$condition['cart_products_product_cat'] = $condition['categories'];
					}
				}

				if ('cart_products:tags' === $condition['type']) {
					$condition['type'] = 'cart_products:product_tag';
					if (isset($condition['tags']) && is_array($condition['tags'])) {
						$condition['cart_products_product_tag'] = $condition['tags'];
					}
				}

				if ('cart_products:shipping_classes' === $condition['type']) {
					$condition['type'] = 'cart_products:product_shipping_class';
					if (isset($condition['shipping_classes']) && is_array($condition['shipping_classes'])) {
						$condition['cart_products_product_shipping_class'] = $condition['shipping_classes'];
					}
				}

				return $condition;
			}, $tier['conditions']);

			return $tier;
		}, $discount_tiers);

		$coupon_data['discount_tiers'] = $discount_tiers;


		return $coupon_data;
	}

	/**
	 * Group of condition of tier discount
	 * 
	 * @since 1.0.0
	 * @return array
	 */
	public static function get_condition_groups() {
		return apply_filters('advanced_coupon_for_woocommerce/condition_groups', array(
			'cart' => __('Cart', 'advanced-coupon-for-woocommerce'),
			'cart_products' => __('Cart Products', 'advanced-coupon-for-woocommerce'),
			'date' => __('Date', 'advanced-coupon-for-woocommerce'),
			'billing' => __('Billing', 'advanced-coupon-for-woocommerce'),
			'shipping' => __('Shipping', 'advanced-coupon-for-woocommerce'),
			'user' => __('User', 'advanced-coupon-for-woocommerce')
		));
	}

	/**
	 * Get condition item of groups
	 * 
	 * @since 1.0.0
	 * @return array
	 */
	public static function get_all_conditions() {
		return apply_filters('advanced_coupon_for_woocommerce/condition_types', array(
			'cart:subtotal' => array(
				'group' => 'cart',
				'priority' => 10,
				'label' => __('Subtotal', 'advanced-coupon-for-woocommerce'),
			),
			'cart:total_quantity' => array(
				'group' => 'cart',
				'priority' => 15,
				'label' => __('Total quantity', 'advanced-coupon-for-woocommerce'),
			),
			'cart:total_weight' => array(
				'group' => 'cart',
				'priority' => 20,
				'label' => __('Total weight', 'advanced-coupon-for-woocommerce'),
			),

			/** Cart products related field types */
			'cart_products:products' => array(
				'group' => 'cart_products',
				'priority' => 5,
				'label' => __('Products', 'advanced-coupon-for-woocommerce'),
			),

			/** Date based condition types */
			'date:time' => array(
				'group' => 'date',
				'priority' => 5,
				'label' => __('Time', 'advanced-coupon-for-woocommerce'),
			),
			'date:date' => array(
				'group' => 'date',
				'priority' => 5,
				'label' => __('Date', 'advanced-coupon-for-woocommerce'),
			),
			'date:weekly_days' => array(
				'group' => 'date',
				'priority' => 10,
				'label' => __('Weekly Days', 'advanced-coupon-for-woocommerce'),
			),

			/** Billing field types */
			'billing:city' => array(
				'group' => 'billing',
				'priority' => 10,
				'label' => __('City', 'advanced-coupon-for-woocommerce'),
			),
			'billing:zipcode' => array(
				'group' => 'billing',
				'priority' => 20,
				'label' => __('Zip code', 'advanced-coupon-for-woocommerce'),
			),
			'billing:state' => array(
				'group' => 'billing',
				'priority' => 25,
				'label' => __('State', 'advanced-coupon-for-woocommerce'),
			),
			'billing:country' => array(
				'group' => 'billing',
				'priority' => 30,
				'label' => __('Country', 'advanced-coupon-for-woocommerce'),
			),

			'shipping:city' => array(
				'group' => 'shipping',
				'priority' => 10,
				'label' => __('City', 'advanced-coupon-for-woocommerce'),
			),
			'shipping:zipcode' => array(
				'group' => 'shipping',
				'priority' => 15,
				'label' => __('Zip code', 'advanced-coupon-for-woocommerce'),
			),
			'shipping:state' => array(
				'group' => 'shipping',
				'priority' => 20,
				'label' => __('State', 'advanced-coupon-for-woocommerce'),
			),
			'shipping:country' => array(
				'group' => 'shipping',
				'priority' => 25,
				'label' => __('Country', 'advanced-coupon-for-woocommerce'),
			),

			'user:users' => array(
				'group' => 'user',
				'priority' => 10,
				'label' => __('Users', 'advanced-coupon-for-woocommerce'),
			),
			'user:roles' => array(
				'group' => 'user',
				'priority' => 15,
				'label' => __('Roles', 'advanced-coupon-for-woocommerce'),
			),
			'user:logged_in' => array(
				'group' => 'user',
				'priority' => 20,
				'label' => __('Logged In', 'advanced-coupon-for-woocommerce'),
			),
		));
	}

	/**
	 * Get conditions of group
	 * 
	 * @since 1.0.0
	 * @return array
	 */
	public static function get_conditions_by_group($group) {
		$all_conditions = self::get_all_conditions();
		$group_conditions = [];
		foreach ($all_conditions as $key => $condition) {
			if ($group !== $condition['group']) {
				continue;
			}

			$group_conditions[$key] = $condition;
		}

		uasort($group_conditions, function ($a, $b) {
			return $a['priority'] > $b['priority'] ? 1 : -1;
		});

		return $group_conditions;
	}

	/**
	 * Get conditions values
	 * 
	 * @since 1.0.0
	 * @return array
	 */
	public static function get_condition_values() {
		$values = apply_filters('advanced_coupon_for_woocommerce/condition_values', array());

		return array_merge($values, array(
			'value' => '',
			'value_two' => '',
			'type' => 'cart:subtotal',
			'operator' => 'less_than',
			'cart_value_type' => 'in_cart',
		));
	}

	/**
	 * Get condition extra values for UI management
	 * 
	 * @since 1.0.0
	 * @return array
	 */
	public static function get_condition_ui_values() {
		return apply_filters('advanced_coupon_for_woocommerce/condition_ui_values', array('loading' => false));
	}

	/**
	 * Select2 ajax values map
	 * 
	 * @since 1.0.0
	 * @return array
	 */
	public static function get_select2_ajax_values_map($args = null) {
		return wp_parse_args($args, array(
			'model' => 'placeholder',
			'data_type' => 'data_type_placeholder',
			'hold_data' => 'hold_data_placeholder',
		));
	}

	/**
	 * Free lock message
	 * 
	 * @since 1.0.0
	 * @return string
	 */
	public static function field_lock_message() {
		if (self::has_pro_installed()) {
			if (class_exists('\Advanced_Coupon_For_WooCommerce_Pro\Upgrade')) {
				if (!\Advanced_Coupon_For_WooCommerce_Pro\Upgrade::license_activated()) {
					echo '<div class="locked-message locked-message-activate-license">';
					$message = sprintf(
						/* translators: %1$s: Link open, %2$s: Link close */
						esc_html__('Please activate your license on the %1$ssettings page%2$s for unlock this feature.', 'advanced-coupon-for-woocommerce'),
						'<a href="' . esc_url(menu_page_url('advanced-coupon-for-woocommerce-settings', false)) . '">',
						'</a>'
					);
					echo wp_kses($message, array('a' => array('href' => true,  'target' => true)));
					echo '</div>';
				}
			} else {
				echo '<div class="locked-message">';
				esc_html_e('Please activate the Advanced Coupon for WooCommerce Pro plugin.', 'advanced-coupon-for-woocommerce');
				echo '</div>';
			}
		} else {
			echo '<div class="locked-message">Get the <a target="_blank" href="https://codiepress.com/plugins/advanced-coupon-for-woocommerce-pro/?utm_campaign=advanced+coupon+for+woocommerce&utm_source=coupon+page&utm_medium=rule+type">pro version</a> for unlock this feature.</div>';
		}
	}

	/**
	 * Get registered taxonomies of product
	 * 
	 * @since 1.0.5
	 * @return array
	 */
	public static function get_product_taxonomies() {
		$taxonomies = get_object_taxonomies('product', 'objects');
		foreach ($taxonomies as $tax_slug => $taxonomy) {
			if (false === $taxonomy->public) {
				unset($taxonomies[$tax_slug]);
			}
		}

		$taxonomies = array_map(function ($taxonomy) {
			return (object) array(
				'slug' => $taxonomy->name,
				'label' => $taxonomy->label,
			);
		}, $taxonomies);

		return $taxonomies;
	}
}
