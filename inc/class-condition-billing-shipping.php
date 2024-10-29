<?php

namespace Advanced_Coupon_For_WooCommerce\Condition;

use Advanced_Coupon_For_WooCommerce\Utils;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Billing & Shipping condition class
 */
final class Billing_Shipping {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter('advanced_coupon_for_woocommerce/condition_matched', array($this, 'filters'), 10, 2);
		add_filter('advanced_coupon_for_woocommerce/condition_values', array($this, 'condition_values'));

		add_action('advanced_coupon_for_woocommerce/condition_templates', array($this, 'billing_city'));
		add_action('advanced_coupon_for_woocommerce/condition_templates', array($this, 'billing_zipcode'));
		add_action('advanced_coupon_for_woocommerce/condition_templates', array($this, 'billing_state'));

		add_action('advanced_coupon_for_woocommerce/condition_templates', array($this, 'shipping_city'));
		add_action('advanced_coupon_for_woocommerce/condition_templates', array($this, 'shipping_zipcode'));
		add_action('advanced_coupon_for_woocommerce/condition_templates', array($this, 'shipping_state'));

		add_action('advanced_coupon_for_woocommerce/condition_templates', array($this, 'billing_country'));
		add_action('advanced_coupon_for_woocommerce/condition_templates', array($this, 'shipping_country'));
	}

	/**
	 * Billing & Shipping condition filter
	 * 
	 * @since 1.0.0
	 * @return boolean
	 */
	public function filters($matched, $condition) {
		$operator = $condition['operator'];

		if ('billing:city' === $condition['type']) {
			$cities = $condition['billing_cities'] ?? '';
			$cities = array_filter(array_map('trim', explode(',', strtolower($cities))));

			$customer_city = strtolower(WC()->customer->get_billing_city());
			if ('any_in_list' === $operator && in_array($customer_city, $cities)) {
				return true;
			}

			if ('not_in_list' === $operator && !in_array($customer_city, $cities)) {
				return true;
			}
		}

		if ('shipping:city' === $condition['type']) {
			$cities = $condition['shipping_cities'] ?? '';
			$cities = array_filter(array_map('trim', explode(',', strtolower($cities))));

			$customer_city = strtolower(WC()->customer->get_shipping_city());

			if ('any_in_list' === $operator && in_array($customer_city, $cities)) {
				return true;
			}

			if ('not_in_list' === $operator && !in_array($customer_city, $cities)) {
				return true;
			}
		}

		if ('billing:country' === $condition['type'] || 'shipping:country' === $condition['type']) {
			$countries = isset($condition['shipping_countries']) && is_array($condition['shipping_countries']) ? $condition['shipping_countries'] : array();

			$customer_country = WC()->customer->get_shipping_country();
			if ('billing:country' === $condition['type']) {
				$countries = isset($condition['billing_countries']) && is_array($condition['billing_countries']) ? $condition['billing_countries'] : array();
				$customer_country = WC()->customer->get_billing_country();
			}

			if ('any_in_list' === $operator && in_array($customer_country, $countries)) {
				return true;
			}

			if ('not_in_list' === $operator && !in_array($customer_country, $countries)) {
				return true;
			}
		}

		return $matched;
	}

	/**
	 * Condition values
	 * 
	 * @since 1.0.0
	 * @return array
	 */
	public function condition_values($values) {
		return array_merge($values, array(
			'billing_cities' => '',
			'shipping_cities' => '',
			'billing_countries' => [],
			'shipping_countries' => [],
		));
	}

	/**
	 * Add city template of condition
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public function billing_city() { ?>
		<template v-if="type == 'billing:city'">
			<select v-model="operator">
				<?php Utils::get_operators_options(array('any_in_list', 'not_in_list')); ?>
			</select>

			<?php $placeholder = __('Example: Chicago, New York', 'advanced-coupon-for-woocommerce'); ?>
			<input style="width: 400px;" type="text" v-model="billing_cities" placeholder="<?php echo esc_attr($placeholder); ?>" title="<?php echo esc_attr($placeholder); ?>">
		</template>
	<?php
	}

	/**
	 * Add zipcode template of billing
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public function billing_zipcode() { ?>
		<div class="advanced-coupon-for-woocommerce-locked-fields" v-if="type == 'billing:zipcode'">
			<select>
				<?php Utils::get_operators_options(array('any_in_list', 'not_in_list')); ?>
			</select>

			<input style="width: 400px;" type="text" placeholder="<?php esc_html_e('Example: 38632, 21710, 38686', 'advanced-coupon-for-woocommerce'); ?>">

			<?php Utils::field_lock_message(); ?>
		</div>
	<?php
	}

	/**
	 * Add state of billing template of condition
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public function billing_state() { ?>
		<div class="advanced-coupon-for-woocommerce-locked-fields" v-if="type == 'billing:state'">
			<select v-model="operator">
				<?php Utils::get_operators_options(array('any_in_list', 'not_in_list')); ?>
			</select>

			<select ref="select2_dropdown">
				<option value=""><?php esc_html_e('Select a country', 'advanced-coupon-for-woocommerce'); ?></option>
				<option v-for="(country, country_code) in get_countries()" :value="country_code">{{country}}</option>
			</select>

			<select ref="select2_ajax" multiple data-placeholder="<?php esc_html_e('Select states', 'advanced-coupon-for-woocommerce'); ?>">
				<option value="state1">State one</option>
				<option value="state1">State two</option>
			</select>
			<?php Utils::field_lock_message(); ?>
		</div>
	<?php
	}

	/**
	 * Add city template of condition
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public function shipping_city() { ?>
		<template v-if="type == 'shipping:city'">
			<select v-model="operator">
				<?php Utils::get_operators_options(array('any_in_list', 'not_in_list')); ?>
			</select>

			<?php $placeholder = __('Example: Chicago, New York', 'advanced-coupon-for-woocommerce'); ?>
			<input style="width: 400px;" type="text" v-model="shipping_cities" placeholder="<?php echo esc_attr($placeholder); ?>" title="<?php echo esc_attr($placeholder); ?>">
		</template>
	<?php
	}

	/**
	 * Add state template of condition
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public function shipping_zipcode() { ?>
		<div class="advanced-coupon-for-woocommerce-locked-fields" v-if="type == 'shipping:zipcode'">
			<select v-model="operator">
				<?php Utils::get_operators_options(array('any_in_list', 'not_in_list')); ?>
			</select>

			<input style="width: 400px;" type="text" v-model="zipcodes" placeholder="<?php esc_html_e('Example: 38632, 21710, 38686', 'advanced-coupon-for-woocommerce'); ?>">

			<?php Utils::field_lock_message(); ?>
		</div>
	<?php
	}

	/**
	 * Add state of shipping template of condition
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public function shipping_state() { ?>
		<div class="advanced-coupon-for-woocommerce-locked-fields" v-if="type == 'shipping:state'">
			<select v-model="operator">
				<?php Utils::get_operators_options(array('any_in_list', 'not_in_list')); ?>
			</select>

			<select ref="select2_dropdown">
				<option value=""><?php esc_html_e('Select a country', 'advanced-coupon-for-woocommerce'); ?></option>
				<option v-for="(country, country_code) in get_countries()" :value="country_code">{{country}}</option>
			</select>

			<select ref="select2_ajax" multiple data-placeholder="<?php esc_html_e('Select states', 'advanced-coupon-for-woocommerce'); ?>">
				<option value="state1">State one</option>
				<option value="state1">State two</option>
			</select>
			<?php Utils::field_lock_message(); ?>
		</div>
	<?php
	}

	/**
	 * Add country template
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public function billing_country() { ?>
		<template v-if="type == 'billing:country'">
			<select v-model="operator">
				<?php Utils::get_operators_options(array('any_in_list', 'not_in_list')); ?>
			</select>

			<select v-model="billing_countries" ref="select2_dropdown" multiple data-model="billing_countries" data-placeholder="<?php esc_attr_e('Select country', 'advanced-coupon-for-woocommerce'); ?>">
				<option v-for="(country, country_code) in get_countries()" :value="country_code">{{country}}</option>
			</select>
		</template>
	<?php
	}

	/**
	 * Add country template
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public function shipping_country() { ?>
		<template v-if="type == 'shipping:country'">
			<select v-model="operator">
				<?php Utils::get_operators_options(array('any_in_list', 'not_in_list')); ?>
			</select>

			<select v-model="shipping_countries" ref="select2_dropdown" multiple data-model="shipping_countries" data-placeholder="<?php esc_attr_e('Select country', 'advanced-coupon-for-woocommerce'); ?>">
				<option v-for="(country, country_code) in get_countries()" :value="country_code">{{country}}</option>
			</select>
		</template>
<?php
	}
}
