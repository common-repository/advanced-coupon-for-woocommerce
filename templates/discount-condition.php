<?php

if (!defined('ABSPATH')) {
	exit;
}

use Advanced_Coupon_For_WooCommerce\Utils;

$condition_groups = Utils::get_condition_groups(); ?>

<table class="advanced-coupon-for-woocommerce-condition-item">
	<tr>
		<td class="condition-type-field-column" style="vertical-align: top;">
			<select v-model="type">
				<?php
				foreach ($condition_groups as $group_key => $group_label) {
					$conditions = Utils::get_conditions_by_group($group_key);
					if (count($conditions) == 0) {
						continue;
					}

					echo '<optgroup label="' . esc_attr($group_label) . '">';
					foreach ($conditions as $key => $condition) {
						echo '<option value="' . esc_attr($key) . '">' . esc_html($condition['label']) . ' </option>';
					}
					echo '</optgroup>';
				}
				?>
			</select>
		</td>

		<td class="condition-type-fields-column">
			<div class="condition-type-fields-wrapper">
				<?php do_action('advanced_coupon_for_woocommerce/condition_templates'); ?>
				<a href="#" class="btn-condition-delete dashicons dashicons-no-alt" @click.prevent="delete_item()"></a>
			</div>
		</td>
	</tr>
</table>