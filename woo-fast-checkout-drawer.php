<?php
/**
 * Plugin Name:       WooCommerce Fast Checkout & Slide Cart Drawer
 * Plugin URI:        https://github.com/Lelyaler/woo-fast-checkout-drawer
 * Description:       Профессиональный модуль выезжающей корзины (Slide Cart Drawer) и мгновенного оформления заказа в 1 клик для WooCommerce с поддержкой HPOS и AJAX-обновлением.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Valeria Chernyavskaya
 * Author URI:        https://lelyaler.github.io/Portfolio/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       woo-fast-checkout-drawer
 * Domain Path:       /languages
 * WC requires at least: 5.0
 * WC tested up to:   8.8
 *
 * @package WooFastCheckoutDrawer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Определение ключевых констант
define( 'WFCD_VERSION', '1.0.0' );
define( 'WFCD_PLUGIN_FILE', __FILE__ );
define( 'WFCD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WFCD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WFCD_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Объявление совместимости с High-Performance Order Storage (HPOS) WooCommerce
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

/**
 * Проверка активации WooCommerce и загрузка ядра
 */
function wfcd_init_plugin() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'wfcd_woocommerce_missing_notice' );
		return;
	}

	require_once WFCD_PLUGIN_DIR . 'includes/class-plugin.php';
	\WooFastCheckout\Plugin::get_instance();
}
add_action( 'plugins_loaded', 'wfcd_init_plugin' );

/**
 * Уведомление в админке, если WooCommerce не активен
 */
function wfcd_woocommerce_missing_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: %s: Название плагина */
				esc_html__( 'Для работы плагина "%s" требуется установленный и активный WooCommerce.', 'woo-fast-checkout-drawer' ),
				'<strong>WooCommerce Fast Checkout & Slide Cart Drawer</strong>'
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Хук активации: создание опций по умолчанию
 */
register_activation_hook(
	__FILE__,
	function () {
		if ( ! get_option( 'wfcd_settings' ) ) {
			$defaults = array(
				'enable_drawer'       => 'yes',
				'enable_quick_buy'    => 'yes',
				'drawer_position'     => 'right',
				'button_color'        => '#2271b1',
				'button_text_color'   => '#ffffff',
				'floating_cart'       => 'yes',
				'require_phone'       => 'yes',
				'require_email'       => 'no',
				'default_payment'     => 'cod',
				'auto_open_on_add'    => 'yes',
				'free_shipping_limit' => 5000,
			);
			update_option( 'wfcd_settings', $defaults );
		}
	}
);
