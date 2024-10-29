<?php

namespace Advanced_Coupon_For_WooCommerce\Condition;

use Advanced_Coupon_For_WooCommerce\Utils;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Cart products rule class
 */
final class Cart_Products {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter('advanced_coupon_for_woocommerce/condition_values', array($this, 'condition_values'));
		add_filter('advanced_coupon_for_woocommerce/condition_ui_values', array($this, 'condition_ui_values'));
		add_filter('advanced_coupon_for_woocommerce/condition_matched', array($this, 'rule_filters'), 10, 2);
		add_filter('advanced_coupon_for_woocommerce/condition_types', array($this, 'add_condition_types'));
		add_action('advanced_coupon_for_woocommerce/condition_templates', array($this, 'products_template'));
		add_action('advanced_coupon_for_woocommerce/condition_templates', array($this, 'add_taxonomy_templates'));
	}

	/**
	 * Rule values
	 * 
	 * @since 1.0.0
	 * @return array
	 */
	public function condition_values($values) {
		$values['products'] = [];
		foreach (array_keys(Utils::get_product_taxonomies()) as $tax_key) {
			$values['cart_products_' . $tax_key] = [];
		}
		return $values;
	}

	/**
	 * Rule UI values
	 * 
	 * @since 1.0.0
	 * @return array
	 */
	public function condition_ui_values($values) {
		$values['hold_products'] = [];
		foreach (array_keys(Utils::get_product_taxonomies()) as $tax_key) {
			$values['hold_cart_products_' . $tax_key] = [];
		}
		return $values;
	}

	/**
	 * Rule filters
	 * 
	 * @since 1.0.0
	 * @return boolean
	 */
	public function rule_filters($matched, $rule) {
		if ('cart_products:products' === $rule['type']) {
			$rule_products = isset($rule['products']) && is_array($rule['products']) ? $rule['products'] : array();

			$cart_products = [];
			foreach (WC()->cart->get_cart() as $item) {
				$cart_products[] = $item['product_id'];
			}

			$cart_products = array_unique(array_filter($cart_products));

			$matched_items = array_intersect($rule_products, $cart_products);
			if ('any_in_list' == $rule['operator'] && count($matched_items) > 0) {
				return true;
			}

			if ('all_in_list' == $rule['operator'] && count($matched_items) === count($rule_products)) {
				return true;
			}

			if ('not_in_list' == $rule['operator'] && 0 === count($matched_items)) {
				return true;
			}
		}

		foreach (array_keys(Utils::get_product_taxonomies()) as $tax_key) {
			if ('product_shipping_class' === $tax_key) {
				continue;
			}

			if ('cart_products:' . $tax_key === $rule['type']) {
				$model_name = 'cart_products_' . $tax_key;
				$rule_terms = isset($rule[$model_name]) && is_array($rule[$model_name]) ? $rule[$model_name] : array();

				$cart_terms = [];
				foreach (WC()->cart->get_cart() as $item) {
					$cart_terms = array_merge($cart_terms, wc_get_product_term_ids($item['product_id'], $tax_key));
				}

				$matched_items = array_intersect($cart_terms, $rule_terms);
				if ('any_in_list' == $rule['operator'] && count($matched_items) > 0) {
					return true;
				}

				if ('all_in_list' == $rule['operator'] && count($rule_terms) === count($matched_items)) {
					return true;
				}

				if ('not_in_list' == $rule['operator'] && 0 === count($matched_items)) {
					return true;
				}
			}
		}

		if ('cart_products:product_shipping_class' === $rule['type']) {
			$shipping_classes = isset($rule['cart_products_product_shipping_class']) && is_array($rule['cart_products_product_shipping_class']) ? $rule['cart_products_product_shipping_class'] : array();

			$product_shipping_classes = [];
			foreach (WC()->cart->get_cart() as $item) {
				$product_shipping_classes[] = $item['data']->get_shipping_class_id();
			}

			$product_shipping_classes = array_unique(array_filter($product_shipping_classes));

			$matched_items = array_intersect($shipping_classes, $product_shipping_classes);
			if ('any_in_list' == $rule['operator'] && count($matched_items) > 0) {
				return true;
			}

			if ('all_in_list' == $rule['operator'] && count($shipping_classes) === count($matched_items)) {
				return true;
			}

			if ('not_in_list' == $rule['operator'] && 0 === count($matched_items)) {
				return true;
			}
		}

		return $matched;
	}

	/**
	 * Add condition types of cart products
	 * 
	 * @since 1.0.6
	 * @return array
	 */
	public function add_condition_types($types) {
		$priority = 20;

		foreach (Utils::get_product_taxonomies() as $tax_key => $taxonomy) {
			$priority++;
			$types['cart_products:' . $tax_key] = array(
				'group' => 'cart_products',
				'priority' => $priority,
				'label' => $taxonomy->label,
			);
		}

		return $types;
	}

	/**
	 * Products template
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public function products_template() {
		$values_map = array(
			'model' => 'products',
			'hold_data' => 'hold_products',
			'data_type' => 'post_type:product',
		); ?>

		<template v-if="type == 'cart_products:products'">
			<select v-model="operator">
				<?php Utils::get_operators_options(array('any_in_list', 'all_in_list', 'not_in_list')); ?>
			</select>

			<div class="input-field-loading" v-if="loading"></div>
			<select class="select2-flex1" ref="select2_ajax" data-values-map="<?php echo esc_attr(wp_json_encode($values_map)) ?>" multiple v-else data-placeholder="<?php esc_html_e('Products', 'advanced-coupon-for-woocommerce'); ?>">
				<option v-for="product in get_ui_data_items('hold_products')" :value="product.id" :selected="products.includes(product.id.toString())">{{product.name}}</option>
			</select>
		</template>
	<?php
	}

	/**
	 * Add templates for taxonomy
	 * 
	 * @since 1.0.6
	 * @return void
	 */
	public function add_taxonomy_templates() { ?>
		<?php foreach (Utils::get_product_taxonomies() as $tax_key => $taxonomy) :
			$data_map_values = array(
				'model' => 'cart_products_' . $tax_key,
				'hold_data' => 'hold_cart_products_' . $tax_key,
				'data_type' => 'taxonomy:' . $tax_key,
			); ?>

			<template v-if="type == 'cart_products:<?php echo esc_attr($tax_key) ?>'">
				<select v-model="operator">
					<?php Utils::get_operators_options(array('any_in_list', 'all_in_list', 'not_in_list')); ?>
				</select>

				<div class="input-field-loading" v-if="loading"></div>
				<select class="select2-flex1" v-model="cart_products_<?php echo esc_attr($tax_key); ?>" ref="select2_ajax" data-values-map="<?php echo esc_attr(wp_json_encode($data_map_values)) ?>" multiple v-else data-placeholder="<?php echo esc_attr($taxonomy->label); ?>">
					<option v-for="tag in get_ui_data_items('hold_cart_products_<?php echo esc_attr($tax_key) ?>')" :value="tag.id">{{tag.name}}</option>
				</select>
			</template>

		<?php endforeach; ?>
<?php
	}
}
