<?php

namespace Advanced_Coupon_For_WooCommerce;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Admin class of the plugin
 */
final class Admin {
	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action('save_post', array($this, 'save_coupon'));
		add_action('add_meta_boxes', array($this, 'add_meta_box'));
		add_action('admin_footer', array($this, 'add_component'));
		add_action('admin_enqueue_scripts', array($this, 'register_scripts'), 1);
		add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));

		add_action('wp_ajax_advanced_coupon_for_woocommerce/import_coupon_data', array($this, 'import_coupon_data'));
		add_action('wp_ajax_advanced_coupon_for_woocommerce/get_dropdown_data', array($this, 'get_dropdown_data'));
	}

	/**
	 * Register styles and scripts
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public function register_scripts() {
		if (defined('CODIEPRESS_DEVELOPMENT')) {
			wp_register_script('advanced-coupon-for-woocommerce-vue', ADVANCED_COUPON_FOR_WOOCOMMERCE_URI . 'assets/vue.js', [], '3.5.12', true);
		} else {
			wp_register_script('advanced-coupon-for-woocommerce-vue', ADVANCED_COUPON_FOR_WOOCOMMERCE_URI . 'assets/vue.min.js', [], '3.5.12', true);
		}
	}

	/**
	 * Enqueue script on backend
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_scripts() {
		if (get_current_screen()->id !== 'shop_coupon') {
			return;
		}

		$wc_countries = new \WC_Countries();

		wp_register_style('select2', ADVANCED_COUPON_FOR_WOOCOMMERCE_URI . 'assets/select2.min.css');
		wp_enqueue_style('advanced-coupon-for-woocommerce', ADVANCED_COUPON_FOR_WOOCOMMERCE_URI . 'assets/admin.min.css', ['select2'], ADVANCED_COUPON_FOR_WOOCOMMERCE_VERSION);

		do_action('advanced_coupon_for_woocommerce/admin_enqueue_scripts');
		wp_enqueue_script('advanced-coupon-for-woocommerce', ADVANCED_COUPON_FOR_WOOCOMMERCE_URI . 'assets/admin.min.js', ['jquery', 'advanced-coupon-for-woocommerce-vue', 'select2'], ADVANCED_COUPON_FOR_WOOCOMMERCE_VERSION, true);
		wp_localize_script('advanced-coupon-for-woocommerce', 'advanced_coupon_for_woocommerce_admin', array(
			'ajax_url' => admin_url('admin-ajax.php'),
			'countries' => $wc_countries->get_countries(),
			'condition_values' => Utils::get_condition_values(),
			'condition_ui_values' => Utils::get_condition_ui_values(),
			'select2_ajax_values_map' => array(
				'model' => 'placeholder',
				'data_type' => 'data_type_placeholder',
				'hold_data' => 'hold_data_placeholder',
			),
			'nonce_get_dropdown_data' => wp_create_nonce('_nonce_advanced_coupon_for_woocommerce/get_dropdown_data'),
			'i10n' => array(
				'copy_text' => __('Copy', 'advanced-coupon-for-woocommerce'),
				'discount_tier' => __('Discount Tier', 'advanced-coupon-for-woocommerce'),
				'delete_discount_tier_warning' => __('Do you want to delete this rule?', 'advanced-coupon-for-woocommerce'),
				'delete_condition_warning' => __('Do you want to delete this condition?', 'advanced-coupon-for-woocommerce')
			)
		));
	}

	/**
	 * Add meta boxes for coupon post type
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public function add_meta_box($post_type) {
		if ('shop_coupon' !== $post_type) {
			return;
		}

		add_meta_box('advanced_coupon_for_woocommerce_metabox', __('Discount Settings', 'advanced-coupon-for-woocommerce'), array($this, 'render_meta_box'), 'shop_coupon', 'advanced', 'high');
	}

	/**
	 * Render meta box
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public function render_meta_box($post) {
		$coupon_data = Utils::get_coupon_data($post->ID); ?>
		<div id="advanced-coupon-for-woocommerce" data-settings="<?php echo esc_attr(wp_json_encode($coupon_data)); ?>">
			<?php wp_nonce_field('_nonce_advanced_coupon_for_woocommerce_meta_box', '_nonce_advanced_coupon_for_woocommerce'); ?>
			<input name="advanced_coupon_for_woocommerce_settings" type="hidden" v-model="get_json_data">

			<div class="rule-empty" v-if="discount_tiers.length === 0">
				<div class="tiered-discount-import-rule">
					<select class="select2-import-tiered-discount" ref="import_tiered_discount_rule" data-placeholder="<?php echo esc_attr_e('Select a coupon', 'advanced-coupon-for-woocommerce'); ?>"></select>
					<button @click.prevent="import_coupon_data()" class="button" :disabled="!has_import_rule_id"><?php esc_html_e('Import', 'advanced-coupon-for-woocommerce'); ?></button>
					<input ref="import_nonce" type="hidden" value="<?php echo esc_attr(wp_create_nonce('_nonce_advanced_coupon_for_woocommerce/import_coupon_data')); ?>">
				</div>
				<div class="tiered-discount-or-hr"><?php esc_html_e('or', 'advanced-coupon-for-woocommerce'); ?></div>
				<a href="#" @click.prevent="add_new_discount_tier()" class="button btn-large-border"><?php esc_html_e('Add a discount tier', 'advanced-coupon-for-woocommerce'); ?></a>
			</div>
			<template v-else>
				<table class="table-discount-rule-of-coupon table-discount-rule-of-coupon-settings">
					<tr>
						<th><?php esc_html_e('Discount tier', 'advanced-coupon-for-woocommerce'); ?></th>
						<td>
							<label>
								<input class="switch-checkbox" type="checkbox" v-model="disabled">
								<?php esc_html_e('Disable', 'advanced-coupon-for-woocommerce'); ?>
							</label>
						</td>
					</tr>

					<tr>
						<th class="vcenter"><?php esc_html_e('Start discount', 'advanced-coupon-for-woocommerce'); ?></th>
						<td>
							<select v-model="start_tiered_discount">
								<option value="immediately"><?php esc_html_e('Immediately', 'advanced-coupon-for-woocommerce'); ?></option>
								<option value="start_after_date"><?php esc_html_e('After', 'advanced-coupon-for-woocommerce'); ?></option>
							</select>

							<input type="datetime-local" v-if="start_tiered_discount == 'start_after_date'" v-model="start_after_date">
						</td>
					</tr>

					<tr>
						<th class="vcenter">
							<?php esc_html_e('Discount rule priority', 'advanced-coupon-for-woocommerce'); ?>

							<div class="field-note">
								<?php esc_html_e('The selected priority will be applied, If match more than one tier.', 'advanced-coupon-for-woocommerce'); ?>
							</div>

						</th>
						<td>
							<select v-model="match_discount_tier_priority">
								<option value="highest_discount"><?php esc_html_e('Highest discount', 'advanced-coupon-for-woocommerce'); ?></option>
								<option value="lowest_discount"><?php esc_html_e('Lowest discount', 'advanced-coupon-for-woocommerce'); ?></option>
							</select>
						</td>
					</tr>
				</table>

				<div :class="['tiered-discount-rule-wrapper', {'tiered-discount-disabled': (disabled || free_shipping)}]">
					<discount-tier-item v-for="(rule, index) in discount_tiers" :key="rule.id" :rule="rule" :rule-no="index"></discount-tier-item>

					<div class="tiered-discount-footer">
						<button href="#" @click.prevent="add_new_discount_tier()" class="button btn-add-new-rule">
							<?php esc_html_e('Add new rule', 'advanced-coupon-for-woocommerce'); ?>
							<span class="dashicons dashicons-lock" v-if="discount_tiers.length >= 3 && !has_pro()"></span>
						</button>
						<button class="button button-primary button-save-tier"><?php esc_html_e('Save Changes', 'advanced-coupon-for-woocommerce'); ?></button>
					</div>
				</div>
			</template>

			<div id="advanced-coupon-locked-modal" v-if="show_locked_modal">
				<div class="modal-body">
					<a @click.prevent="show_locked_modal = false" href="#" class="btn-modal-close dashicons dashicons-no-alt"></a>

					<span class="modal-icon dashicons dashicons-lock"></span>

					<div>
						<?php
						$text = sprintf(
							/* translators: %s for link */
							esc_html__('For adding more discount tire, please get a pro version from %s.', 'advanced-coupon-for-woocommerce'),
							'<a target="_blank" href="https://codiepress.com/plugins/advanced-coupon-for-woocommerce-pro/">' . esc_html__('here', 'advanced-coupon-for-woocommerce') . '</a>'
						);

						echo wp_kses($text, array('a' => array('href' => true, 'target' => true)));
						?>
					</div>


					<div class="modal-footer">
						<a @click.prevent="show_locked_modal = false" class="button" href="#"><?php esc_html_e('Back', 'advanced-coupon-for-woocommerce'); ?></a>
						<a @click="show_locked_modal = false" class="button button-get-pro" href="https://codiepress.com/plugins/advanced-coupon-for-woocommerce-pro/" target="_blank"><?php esc_html_e('Get Pro', 'advanced-coupon-for-woocommerce'); ?></a>
					</div>
				</div>
			</div>
		</div>
<?php
	}

	/**
	 * Save meta box content.
	 *
	 * @since 1.0.0
	 * @param int $post_id Post ID
	 */
	public function save_coupon($post_id) {
		if (!isset($_POST['_nonce_advanced_coupon_for_woocommerce'])) {
			return;
		}

		$nonce = sanitize_text_field($_POST['_nonce_advanced_coupon_for_woocommerce']);
		if (!wp_verify_nonce($nonce, '_nonce_advanced_coupon_for_woocommerce_meta_box')) {
			return;
		}

		if (!isset($_POST['advanced_coupon_for_woocommerce_settings'])) {
			return;
		}

		$tiered_discount = stripslashes(sanitize_text_field($_POST['advanced_coupon_for_woocommerce_settings']));
		update_post_meta($post_id, 'advanced_coupon_for_woocommerce_settings', $tiered_discount);
	}

	/**
	 * Add vuejs component
	 */

	public function add_component() {
		if (get_current_screen()->id !== 'shop_coupon') {
			return;
		}

		echo '<template id="component-tiered-discount">';
		include_once ADVANCED_COUPON_FOR_WOOCOMMERCE_PATH . '/templates/discount-item.php';
		echo '</template>';

		echo '<template id="component-discount-condition">';
		include_once ADVANCED_COUPON_FOR_WOOCOMMERCE_PATH . '/templates/discount-condition.php';
		echo '</template>';
	}

	/**
	 * Import data content by ID
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public function import_coupon_data() {
		check_ajax_referer('_nonce_advanced_coupon_for_woocommerce/import_coupon_data', 'security');

		if (empty($_POST['coupon_id'])) {
			wp_send_json_error(array(
				'error' => __('No coupon ID found.', 'advanced-coupon-for-woocommerce')
			));
		}

		$coupon_data = Utils::get_coupon_data(absint($_POST['coupon_id']));
		wp_send_json_success($coupon_data);
	}

	/**
	 * Get users by search
	 * 
	 * @since 1.0.0
	 * @return void
	 */

	public function get_dropdown_data() {
		check_ajax_referer('_nonce_advanced_coupon_for_woocommerce/get_dropdown_data', 'security');

		$results = $search_args = array();

		$search_term = !empty($_POST['term']) ? sanitize_text_field($_POST['term'])  : '';
		$query_type = !empty($_POST['type']) ? sanitize_text_field($_POST['type']) : '';
		$query_type = explode(':', $query_type);

		$object_type = !empty($query_type[0]) ? $query_type[0] : '';
		$object_slug = !empty($query_type[1]) ? $query_type[1] : '';

		if ('taxonomy' == $object_type && !empty($object_slug)) {
			$search_args = array('hide_empty' => false, 'taxonomy' => $object_slug);

			if (!empty($search_term)) {
				$search_args['search'] = $search_term;
			}

			if (isset($_POST['ids']) && is_array($_POST['ids'])) {
				$search_args['include'] = array_map('absint', $_POST['ids']);
			}

			$terms = get_terms($search_args);

			$results = array_map(function ($term) {
				return array('id' => $term->term_id, 'name' => $term->name);
			}, $terms);
		}

		if ('coupon_rules' == $object_type) {
			$coupons = get_posts(array(
				's' => $search_term,
				'post_type' => 'shop_coupon',
				'posts_per_page' => -1,
				'meta_query' => array(
					array(
						'key' => 'advanced_coupon_for_woocommerce_settings',
						'compare' => 'EXISTS',
					)
				)
			));

			$results = array_map(function ($coupon) {
				$title[] = esc_html__('ID', 'advanced-coupon-for-woocommerce') . ': ' . $coupon->ID;
				return array('id' => $coupon->ID, 'name' => sprintf('%s (%d)', $coupon->post_title, $coupon->ID));
			}, $coupons);
		}

		if ('users' == $object_type) {
			if (!empty($search_term)) {
				$search_args['search'] = $search_term;
			}

			if (isset($_POST['ids']) && is_array($_POST['ids'])) {
				$search_args['include'] = array_map('absint', $_POST['ids']);
			}

			$get_users = get_users($search_args);
			$results = array_map(function ($user) {
				return array('id' => $user->id, 'name' => $user->display_name);
			}, $get_users);
		}

		if ('states' == $object_type) {
			if (empty($_POST['country'])) {
				wp_send_json_error(array(
					'error' => esc_html__('Country Missing', 'advanced-coupon-for-woocommerce')
				));
			}

			$wc_countries = new \WC_Countries();
			$states = $wc_countries->get_states(sanitize_text_field($_POST['country']));

			if (!empty($search_term)) {
				$states = array_filter($states, function ($state) use ($search_term) {
					return stripos($state, $search_term) !== false;
				});
			}

			if (!is_array($states)) {
				$states = [];
			}

			$results = array_map(function ($state, $code) {
				return array('id' => $code, 'name' => html_entity_decode($state));
			}, $states, array_keys($states));
		}

		if ('post_type' == $object_type && !empty($object_slug)) {
			$search_args['post_type'] = $object_slug;
			if (!empty($search_term)) {
				$search_args['s'] = $search_term;
			}

			if (isset($_POST['ids']) && is_array($_POST['ids'])) {
				$search_args['post__in'] = array_map('absint', $_POST['ids']);
			}

			$posts = get_posts($search_args);
			$results = array_map(function ($item) {
				return array('id' => $item->ID, 'name' => $item->post_title);
			}, $posts);
		}

		wp_send_json_success($results);
	}
}
