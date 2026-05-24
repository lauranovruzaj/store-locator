<?php
if (! defined('ABSPATH')) {
	exit;
}

class SL_CPT
{

	const POST_TYPE = 'store';
	const TAXONOMY  = 'store_category';

	/** @var array<string, array{type:string,default:mixed}> */
	private static $meta_fields = [
		'_sl_external_id'    => ['type' => 'string',  'default' => ''],
		'_sl_address'        => ['type' => 'string',  'default' => ''],
		'_sl_cap'            => ['type' => 'string',  'default' => ''],
		'_sl_city'           => ['type' => 'string',  'default' => ''],
		'_sl_province'       => ['type' => 'string',  'default' => ''],
		'_sl_country'        => ['type' => 'string',  'default' => 'IT'],
		'_sl_phone'          => ['type' => 'string',  'default' => ''],
		'_sl_phone_raw'      => ['type' => 'string',  'default' => ''],
		'_sl_phone_tel'      => ['type' => 'string',  'default' => ''],
		'_sl_email'          => ['type' => 'string',  'default' => ''],
		'_sl_website'        => ['type' => 'string',  'default' => ''],
		'_sl_hours'          => ['type' => 'string',  'default' => ''],
		'_sl_lat'            => ['type' => 'number',  'default' => 0],
		'_sl_lng'            => ['type' => 'number',  'default' => 0],
		'_sl_category'       => ['type' => 'string',  'default' => ''],
		'_sl_category_code'  => ['type' => 'string',  'default' => ''],
		'_sl_address_hash'   => ['type' => 'string',  'default' => ''],
	];

	public static function init(): void
	{
		add_action('init', [__CLASS__, 'register']);
		add_action('add_meta_boxes', [__CLASS__, 'add_meta_box']);
		add_action('save_post_' . self::POST_TYPE, [__CLASS__, 'save_meta'], 10, 2);
	}

	public static function register(): void
	{
		register_post_type(self::POST_TYPE, [
			'labels' => [
				'name'               => __('Stores', 'store-locator'),
				'singular_name'      => __('Store', 'store-locator'),
				'add_new'            => __('Add New', 'store-locator'),
				'add_new_item'       => __('Add Store', 'store-locator'),
				'edit_item'          => __('Modifica Store', 'store-locator'),
				'all_items'          => __('All Stores', 'store-locator'),
				'menu_name'          => __('Store Locator', 'store-locator'),
				'search_items'       => __('Search Stores', 'store-locator'),
			],
			'public'        => false,
			'show_ui'       => true,
			'show_in_menu'  => true,
			'show_in_rest'  => true,
			'menu_icon'     => 'dashicons-store',
			'menu_position' => 25,
			'supports'      => ['title'],
			'capability_type' => 'post',
			'map_meta_cap'  => true,
		]);

		register_taxonomy(self::TAXONOMY, self::POST_TYPE, [
			'labels' => [
				'name'          => __('Categories', 'store-locator'),
				'singular_name' => __('Category', 'store-locator'),
			],
			'public'            => false,
			'show_ui'           => true,
			'show_in_rest'      => true,
			'hierarchical'      => true,
			'show_admin_column' => true,
		]);

		foreach (self::$meta_fields as $key => $config) {
			register_post_meta(self::POST_TYPE, $key, [
				'type'              => $config['type'],
				'default'           => $config['default'],
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => self::sanitizer_for($key, $config['type']),
				'auth_callback'     => static fn() => current_user_can('edit_posts'),
			]);
		}
	}

	private static function sanitizer_for(string $key, string $type): callable
	{
		if ($type === 'number') {
			return static fn($v) => is_numeric($v) ? (float) $v : 0.0;
		}
		if ($key === '_sl_email') {
			return 'sanitize_email';
		}
		if ($key === '_sl_website') {
			return 'esc_url_raw';
		}
		if ($key === '_sl_hours') {
			return 'sanitize_textarea_field';
		}
		return 'sanitize_text_field';
	}

	public static function meta_keys(): array
	{
		return array_keys(self::$meta_fields);
	}

	public static function add_meta_box(): void
	{
		add_meta_box(
			'sl_store_details',
			__('Store Details', 'store-locator'),
			[__CLASS__, 'render_meta_box'],
			self::POST_TYPE,
			'normal',
			'high'
		);
	}

	public static function render_meta_box(WP_Post $post): void
	{
		wp_nonce_field('sl_save_meta', 'sl_meta_nonce');
		$get = static function ($key) use ($post) {
			return get_post_meta($post->ID, $key, true);
		};
?>
		<style>
			.sl-grid {
				display: grid;
				grid-template-columns: repeat(2, 1fr);
				gap: 12px 20px;
			}

			.sl-grid .sl-full {
				grid-column: 1 / -1;
			}

			.sl-grid label {
				display: block;
				font-weight: 600;
				margin-bottom: 4px;
			}

			.sl-grid input[type=text],
			.sl-grid input[type=email],
			.sl-grid input[type=url],
			.sl-grid textarea {
				width: 100%;
				box-sizing: border-box;
			}

			.sl-grid textarea {
				min-height: 90px;
			}

			.sl-hint {
				color: #666;
				font-size: 12px;
				margin-top: 8px;
			}
		</style>
		<div class="sl-grid">
			<div class="sl-full">
				<label for="sl_address"><?php esc_html_e('Address', 'store-locator'); ?></label>
				<input type="text" id="sl_address" name="_sl_address" value="<?php echo esc_attr($get('_sl_address')); ?>">
			</div>
			<div>
				<label for="sl_cap"><?php esc_html_e('ZIP Code', 'store-locator'); ?></label>
				<input type="text" id="sl_cap" name="_sl_cap" value="<?php echo esc_attr($get('_sl_cap')); ?>">
			</div>
			<div>
				<label for="sl_city"><?php esc_html_e('City', 'store-locator'); ?></label>
				<input type="text" id="sl_city" name="_sl_city" value="<?php echo esc_attr($get('_sl_city')); ?>">
			</div>
			<div>
				<label for="sl_province"><?php esc_html_e('Province (abbreviation)', 'store-locator'); ?></label>
				<input type="text" id="sl_province" maxlength="2" name="_sl_province" value="<?php echo esc_attr($get('_sl_province')); ?>">
			</div>
			<div>
				<label for="sl_country"><?php esc_html_e('Country (ISO 2)', 'store-locator'); ?></label>
				<input type="text" id="sl_country" maxlength="2" name="_sl_country" value="<?php echo esc_attr($get('_sl_country') ?: 'IT'); ?>">
			</div>
			<div>
				<label for="sl_phone"><?php esc_html_e('Phone (displayed)', 'store-locator'); ?></label>
				<input type="text" id="sl_phone" name="_sl_phone_raw" value="<?php echo esc_attr($get('_sl_phone_raw') ?: $get('_sl_phone')); ?>">
			</div>
			<div>
				<label for="sl_phone_tel"><?php esc_html_e('Phone (phone format:)', 'store-locator'); ?></label>
				<input type="text" id="sl_phone_tel" name="_sl_phone_tel" value="<?php echo esc_attr($get('_sl_phone_tel')); ?>" placeholder="+39…">
			</div>
			<div>
				<label for="sl_email"><?php esc_html_e('Email', 'store-locator'); ?></label>
				<input type="email" id="sl_email" name="_sl_email" value="<?php echo esc_attr($get('_sl_email')); ?>">
			</div>
			<div>
				<label for="sl_website"><?php esc_html_e('Website', 'store-locator'); ?></label>
				<input type="url" id="sl_website" name="_sl_website" value="<?php echo esc_attr($get('_sl_website')); ?>">
			</div>
			<div class="sl-full">
				<label for="sl_hours"><?php esc_html_e('Hours', 'store-locator'); ?></label>
				<textarea id="sl_hours" name="_sl_hours"><?php echo esc_textarea($get('_sl_hours')); ?></textarea>
			</div>
			<div>
				<label for="sl_lat"><?php esc_html_e('Latitude', 'store-locator'); ?></label>
				<input type="text" id="sl_lat" name="_sl_lat" value="<?php echo esc_attr($get('_sl_lat')); ?>">
			</div>
			<div>
				<label for="sl_lng"><?php esc_html_e('Longitude', 'store-locator'); ?></label>
				<input type="text" id="sl_lng" name="_sl_lng" value="<?php echo esc_attr($get('_sl_lng')); ?>">
			</div>
			<div>
				<label for="sl_external_id"><?php esc_html_e('External ID (Customer Code)', 'store-locator'); ?></label>
				<input type="text" id="sl_external_id" name="_sl_external_id" value="<?php echo esc_attr($get('_sl_external_id')); ?>">
			</div>
			<div>
				<label for="sl_category"><?php esc_html_e('Category', 'store-locator'); ?></label>
				<input type="text" id="sl_category" name="_sl_category" value="<?php echo esc_attr($get('_sl_category')); ?>">
			</div>
		</div>
		<p class="sl-hint"><?php esc_html_e('If lat/lng are empty on save, the address will be geocoded automatically via Google.', 'store-locator'); ?></p>
<?php
	}

	public static function save_meta(int $post_id, WP_Post $post): void
	{
		if (
			! isset($_POST['sl_meta_nonce']) ||
			! wp_verify_nonce(wp_unslash($_POST['sl_meta_nonce']), 'sl_save_meta')
		) {
			return;
		}
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}
		if (! current_user_can('edit_post', $post_id)) {
			return;
		}

		$keys_text   = ['_sl_external_id', '_sl_address', '_sl_cap', '_sl_city', '_sl_province', '_sl_country', '_sl_phone_raw', '_sl_phone_tel', '_sl_category'];
		$keys_num    = ['_sl_lat', '_sl_lng'];

		foreach ($keys_text as $k) {
			if (isset($_POST[$k])) {
				update_post_meta($post_id, $k, sanitize_text_field(wp_unslash($_POST[$k])));
			}
		}
		if (isset($_POST['_sl_email'])) {
			update_post_meta($post_id, '_sl_email', sanitize_email(wp_unslash($_POST['_sl_email'])));
		}
		if (isset($_POST['_sl_website'])) {
			update_post_meta($post_id, '_sl_website', esc_url_raw(wp_unslash($_POST['_sl_website'])));
		}
		if (isset($_POST['_sl_hours'])) {
			update_post_meta($post_id, '_sl_hours', sanitize_textarea_field(wp_unslash($_POST['_sl_hours'])));
		}
		foreach ($keys_num as $k) {
			if (isset($_POST[$k]) && $_POST[$k] !== '') {
				update_post_meta($post_id, $k, (float) str_replace(',', '.', wp_unslash($_POST[$k])));
			}
		}

		// Geocode if coords missing.
		$lat = (float) get_post_meta($post_id, '_sl_lat', true);
		$lng = (float) get_post_meta($post_id, '_sl_lng', true);
		if (! $lat && ! $lng) {
			$query = SL_Geocoder::build_query(
				get_post_meta($post_id, '_sl_address', true),
				get_post_meta($post_id, '_sl_cap', true),
				get_post_meta($post_id, '_sl_city', true),
				get_post_meta($post_id, '_sl_province', true),
				get_post_meta($post_id, '_sl_country', true) ?: 'IT'
			);
			if ($query) {
				$res = SL_Geocoder::lookup($query);
				if (! is_wp_error($res)) {
					update_post_meta($post_id, '_sl_lat', $res['lat']);
					update_post_meta($post_id, '_sl_lng', $res['lng']);
					update_post_meta($post_id, '_sl_address_hash', SL_Geocoder::address_hash($query));
				}
			}
		}
	}
}
