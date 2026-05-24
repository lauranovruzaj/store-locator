<?php
if (! defined('ABSPATH')) {
	exit;
}

class SL_Settings
{

	const OPTION = 'sl_settings';

	public static function init(): void
	{
		add_action('admin_menu', [__CLASS__, 'add_menu']);
		add_action('admin_init', [__CLASS__, 'register_settings']);
		add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
	}

	public static function defaults(): array
	{
		return [
			'maps_key'             => '',
			'geocode_key'          => '',
			'center_lat'           => 41.9028,
			'center_lng'           => 12.4964,
			'zoom'                 => 6,
			'suggerimenti_title'   => 'Suggerimenti',
			'suggerimenti'         => "Chiamare il numero di telefono del punto vendita selezionato e richiedere il prodotto a cui si è interessati.",
			'suggerimenti_icon'    => '',
		];
	}

	public static function enqueue_assets(string $hook): void
	{
		if ($hook !== SL_CPT::POST_TYPE . '_page_sl-settings') {
			return;
		}
		wp_enqueue_media();
	}

	public static function get(string $key, $default = null)
	{
		$opts = wp_parse_args(get_option(self::OPTION, []), self::defaults());
		return $opts[$key] ?? $default;
	}

	public static function add_menu(): void
	{
		add_submenu_page(
			'edit.php?post_type=' . SL_CPT::POST_TYPE,
			__('Store Locator – Impostazioni', 'store-locator'),
			__('Impostazioni', 'store-locator'),
			'manage_options',
			'sl-settings',
			[__CLASS__, 'render']
		);
	}

	public static function register_settings(): void
	{
		register_setting('sl_settings_group', self::OPTION, [
			'type'              => 'array',
			'sanitize_callback' => [__CLASS__, 'sanitize'],
			'default'           => self::defaults(),
		]);
	}

	public static function sanitize($input): array
	{
		$defaults = self::defaults();
		$out = [];
		$out['maps_key']           = sanitize_text_field($input['maps_key'] ?? '');
		$out['geocode_key']        = sanitize_text_field($input['geocode_key'] ?? '');
		$out['center_lat']         = isset($input['center_lat']) && $input['center_lat'] !== '' ? (float) str_replace(',', '.', $input['center_lat']) : $defaults['center_lat'];
		$out['center_lng']         = isset($input['center_lng']) && $input['center_lng'] !== '' ? (float) str_replace(',', '.', $input['center_lng']) : $defaults['center_lng'];
		$out['zoom']               = isset($input['zoom']) ? max(1, min(20, (int) $input['zoom'])) : $defaults['zoom'];
		$out['suggerimenti_title'] = sanitize_text_field($input['suggerimenti_title'] ?? $defaults['suggerimenti_title']);
		$out['suggerimenti']       = sanitize_textarea_field($input['suggerimenti'] ?? $defaults['suggerimenti']);
		$out['suggerimenti_icon']  = esc_url_raw($input['suggerimenti_icon'] ?? '');
		return $out;
	}

	public static function render(): void
	{
		if (! current_user_can('manage_options')) {
			return;
		}
		$opts = wp_parse_args(get_option(self::OPTION, []), self::defaults());
?>
		<div class="wrap">
			<h1><?php esc_html_e('Store Locator – Impostazioni', 'store-locator'); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields('sl_settings_group'); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="sl_maps_key"><?php esc_html_e('Google Maps JavaScript API key', 'store-locator'); ?></label></th>
						<td><input type="text" id="sl_maps_key" class="regular-text" name="<?php echo esc_attr(self::OPTION); ?>[maps_key]" value="<?php echo esc_attr($opts['maps_key']); ?>"></td>
					</tr>
					<tr>
						<th><label for="sl_geocode_key"><?php esc_html_e('Google Geocoding API key', 'store-locator'); ?></label></th>
						<td>
							<input type="text" id="sl_geocode_key" class="regular-text" name="<?php echo esc_attr(self::OPTION); ?>[geocode_key]" value="<?php echo esc_attr($opts['geocode_key']); ?>">
							<p class="description"><?php esc_html_e('Può essere la stessa chiave dei Maps.', 'store-locator'); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e('Centro mappa di default', 'store-locator'); ?></th>
						<td>
							<label>Lat <input type="text" name="<?php echo esc_attr(self::OPTION); ?>[center_lat]" value="<?php echo esc_attr($opts['center_lat']); ?>"></label>
							&nbsp;
							<label>Lng <input type="text" name="<?php echo esc_attr(self::OPTION); ?>[center_lng]" value="<?php echo esc_attr($opts['center_lng']); ?>"></label>
						</td>
					</tr>
					<tr>
						<th><label for="sl_zoom"><?php esc_html_e('Zoom di default', 'store-locator'); ?></label></th>
						<td><input type="number" min="1" max="20" id="sl_zoom" name="<?php echo esc_attr(self::OPTION); ?>[zoom]" value="<?php echo esc_attr($opts['zoom']); ?>"></td>
					</tr>
					<tr>
						<th><label for="sl_suggerimenti_title"><?php esc_html_e('Title "Carousel"', 'store-locator'); ?></label></th>
						<td><input type="text" id="sl_suggerimenti_title" class="regular-text" name="<?php echo esc_attr(self::OPTION); ?>[suggerimenti_title]" value="<?php echo esc_attr($opts['suggerimenti_title']); ?>"></td>
					</tr>
					<tr>
						<th><label for="sl_suggerimenti_icon"><?php esc_html_e('Icon "Carousel"', 'store-locator'); ?></label></th>
						<td>
							<div class="sl-media-field">
								<input type="url" id="sl_suggerimenti_icon" class="regular-text" name="<?php echo esc_attr(self::OPTION); ?>[suggerimenti_icon]" value="<?php echo esc_attr($opts['suggerimenti_icon']); ?>" placeholder="https://…">
								<button type="button" class="button sl-media-btn" data-target="sl_suggerimenti_icon"><?php esc_html_e('Scegli immagine', 'store-locator'); ?></button>
								<button type="button" class="button sl-media-clear" data-target="sl_suggerimenti_icon"><?php esc_html_e('Rimuovi', 'store-locator'); ?></button>
							</div>
							<p class="description"><?php esc_html_e('Immagine mostrata accanto al titolo "Suggerimenti" (consigliato: PNG/SVG quadrato, max 64×64).', 'store-locator'); ?></p>
							<div class="sl-media-preview" style="margin-top:8px;">
								<?php if ($opts['suggerimenti_icon']) : ?>
									<img src="<?php echo esc_url($opts['suggerimenti_icon']); ?>" alt="" style="max-height:48px;max-width:48px;">
								<?php endif; ?>
							</div>
						</td>
					</tr>
					<tr>
						<th><label for="sl_suggerimenti"><?php esc_html_e('Description "Carousel"', 'store-locator'); ?></label></th>
						<td><textarea id="sl_suggerimenti" rows="4" class="large-text" name="<?php echo esc_attr(self::OPTION); ?>[suggerimenti]"><?php echo esc_textarea($opts['suggerimenti']); ?></textarea></td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<script>
			(function($) {
				$(document).on('click', '.sl-media-btn', function(e) {
					e.preventDefault();
					var $btn = $(this);
					var $input = $('#' + $btn.data('target'));
					var $preview = $btn.closest('td').find('.sl-media-preview');
					var frame = wp.media({
						title: '<?php echo esc_js(__('Scegli un\'immagine', 'store-locator')); ?>',
						button: {
							text: '<?php echo esc_js(__('Usa questa immagine', 'store-locator')); ?>'
						},
						library: {
							type: 'image'
						},
						multiple: false
					});
					frame.on('select', function() {
						var att = frame.state().get('selection').first().toJSON();
						$input.val(att.url);
						$preview.html('<img src="' + att.url + '" alt="" style="max-height:48px;max-width:48px;">');
					});
					frame.open();
				});
				$(document).on('click', '.sl-media-clear', function(e) {
					e.preventDefault();
					var $btn = $(this);
					$('#' + $btn.data('target')).val('');
					$btn.closest('td').find('.sl-media-preview').empty();
				});
			})(jQuery);
		</script>
<?php
	}
}
