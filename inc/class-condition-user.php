<?php

namespace Advanced_Coupon_For_WooCommerce\Condition;

use Advanced_Coupon_For_WooCommerce\Utils;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * User Condition class
 */
final class User {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter('advanced_coupon_for_woocommerce/condition_values', array($this, 'condition_values'));
		add_filter('advanced_coupon_for_woocommerce/condition_ui_values', array($this, 'ui_values'));
		add_filter('advanced_coupon_for_woocommerce/condition_matched', array($this, 'filters'), 100, 2);

		add_action('advanced_coupon_for_woocommerce/condition_templates', array($this, 'users'));
		add_action('advanced_coupon_for_woocommerce/condition_templates', array($this, 'user_roles'));
		add_action('advanced_coupon_for_woocommerce/condition_templates', array($this, 'logged_in'));
	}

	/**
	 * Condition values
	 * 
	 * @since 1.0.0
	 * @return array
	 */
	public function condition_values($values) {
		return array_merge($values, array(
			'users' => [],
			'logged_in' => 'yes',
		));
	}

	/**
	 * UI values
	 * 
	 * @since 1.0.0
	 * @return array
	 */
	public function ui_values($values) {
		return array_merge($values, array(
			'hold_users' => [],
			'loading_users' => false,
		));
	}


	/**
	 * Condition filters
	 * 
	 * @since 1.0.0
	 * @return boolean
	 */
	public function filters($matched, $condition) {
		$operator = $condition['operator'];

		if ('user:users' === $condition['type']) {
			$users = isset($condition['users']) && is_array($condition['users']) ? $condition['users'] : array();
			if ('any_in_list' === $operator && in_array(get_current_user_id(), $users)) {
				return true;
			}

			if ('not_in_list' === $operator && !in_array(get_current_user_id(), $users)) {
				return true;
			}
		}

		if ('user:logged_in' === $condition['type'] && 'yes' == $condition['logged_in']) {
			return is_user_logged_in();
		}

		if ('user:logged_in' === $condition['type'] && 'no' == $condition['logged_in']) {
			return !is_user_logged_in();
		}

		return $matched;
	}

	/**
	 * Add users template
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public function users() {
		$values_map = array(
			'model' => 'users',
			'data_type' => 'users',
			'hold_data' => 'hold_users',
		); ?>

		<template v-if="type == 'user:users'">
			<select v-model="operator">
				<?php Utils::get_operators_options(array('any_in_list', 'not_in_list')); ?>
			</select>

			<div class="input-field-loading" v-if="loading_users"></div>
			<select ref="select2_ajax" data-values-map="<?php echo esc_attr(wp_json_encode($values_map)) ?>" multiple v-else data-placeholder="<?php esc_html_e('Select users', 'advanced-coupon-for-woocommerce'); ?>">
				<option v-for="user in get_ui_data_items('hold_users')" :value="user.id" :selected="users.includes(user.id.toString())">{{user.name}}</option>
			</select>
		</template>
	<?php
	}

	/**
	 * Add user roles template
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public function user_roles() { ?>
		<div class="advanced-coupon-for-woocommerce-locked-fields" v-if="type == 'user:roles'">
			<select>
				<?php Utils::get_operators_options(array('any_in_list', 'not_in_list')); ?>
			</select>

			<select>
				<option value="test">Administrator</option>
			</select>

			<?php Utils::field_lock_message(); ?>
		</div>
	<?php
	}

	/**
	 * Add logged in template
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public function logged_in() { ?>
		<template v-if="type == 'user:logged_in'">
			<select v-model="logged_in">
				<option value="yes"><?php esc_html_e('Yes', 'advanced-coupon-for-woocommerce'); ?></option>
				<option value="no"><?php esc_html_e('No', 'advanced-coupon-for-woocommerce'); ?></option>
			</select>
		</template>
<?php
	}
}
