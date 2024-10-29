<?php

namespace Advanced_Coupon_For_WooCommerce\Condition;

use Advanced_Coupon_For_WooCommerce\Utils;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Cart Condition class
 */
final class Cart {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter('advanced_coupon_for_woocommerce/condition_matched', array($this, 'filters'), 10, 2);
		add_action('advanced_coupon_for_woocommerce/condition_templates', array($this, 'common_templates'));
		add_action('advanced_coupon_for_woocommerce/cart_common_fields', array($this, 'cart_common_fields'));
	}

	/**
	 * Cart related condition filters
	 * 
	 * @since 1.0.0
	 * @return boolean
	 */
	public function filters($matched, $condition) {
		if (!in_array($condition['type'], array('cart:subtotal', 'cart:total_quantity', 'cart:total_weight'))) {
			return $matched;
		}

		$operator = $condition['operator'];
		$value_one = floatval($condition['value']);
		$value_two = isset($condition['value_two']) ? floatval($condition['value_two']) : 0.00;


		$compare_value = 0.00;
		if ('cart:subtotal' === $condition['type']) {
			$compare_value = (float) WC()->cart->get_subtotal();
		}

		if ('cart:total_quantity' === $condition['type']) {
			$compare_value = WC()->cart->get_cart_contents_count();
		}

		if ('cart:total_weight' === $condition['type']) {
			$compare_value = WC()->cart->cart_contents_weight;
		}

		$compare_value = apply_filters('advanced_coupon_for_woocommerce/cart_compare_value', $compare_value, $condition);

		if ('equal_to' === $operator && $compare_value == $value_one) {
			return true;
		}

		if ('less_than' === $operator && $compare_value < $value_one) {
			return true;
		}

		if ('less_than_or_equal' === $operator && $compare_value <= $value_one) {
			return true;
		}

		if ('greater_than_or_equal' === $operator && $compare_value >= $value_one) {
			return true;
		}

		if ('greater_than' === $operator && $compare_value > $value_one) {
			return true;
		}

		if ('between' === $operator && $compare_value >= $value_one && $compare_value <= $value_two) {
			return true;
		}

		return $matched;
	}

	/**
	 * Add common template of cart
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public function common_templates() { ?>
		<template v-if="['cart:subtotal', 'cart:total_quantity', 'cart:total_weight'].includes(type)">
			<select v-model="operator">
				<?php Utils::get_operators_options(array('equal_to', 'less_than', 'less_than_or_equal', 'greater_than_or_equal', 'greater_than', 'between')); ?>
			</select>

			<input type="number" step="0.001" v-model="value" placeholder="<?php echo '0.00'; ?>">
			<input type="number" step="0.001" v-model="value_two" placeholder="<?php echo '0.00'; ?>" v-if="'between' == operator">
			<?php do_action('advanced_coupon_for_woocommerce/cart_common_fields') ?>
		</template>
	<?php
	}

	/**
	 * Cart common fields
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public function cart_common_fields() { ?>
		<select v-model="cart_value_type">
			<option value="in_cart"><?php esc_html_e('In Cart', 'advanced-coupon-for-woocommerce'); ?></option>
			<?php foreach (Utils::get_product_taxonomies() as $taxonomy_data) : ?>
				<option disabled><?php echo esc_html($taxonomy_data->label); ?> (<?php echo esc_html('pro', 'advanced-coupon-for-woocommerce') ?>)</option>
			<?php endforeach; ?>
		</select>
<?php
	}
}
