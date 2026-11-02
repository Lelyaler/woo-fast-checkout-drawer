<?php
/**
 * Модуль административных настроек плагина
 *
 * @package WooFastCheckoutDrawer
 */

namespace WooFastCheckout;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_Settings {

	/**
	 * Экземпляр ядра
	 *
	 * @var Plugin
	 */
	private $plugin;

	/**
	 * Конструктор
	 *
	 * @param Plugin $plugin
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;

		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Регистрация страницы в меню WooCommerce
	 */
	public function register_admin_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Express Checkout & Drawer', 'woo-fast-checkout-drawer' ),
			__( 'Express Checkout', 'woo-fast-checkout-drawer' ),
			'manage_woocommerce',
			'wfcd-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Подключение стилей и скриптов админки
	 *
	 * @param string $hook
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'woocommerce_page_wfcd-settings' !== $hook ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );

		wp_enqueue_style(
			'wfcd-admin-style',
			WFCD_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			WFCD_VERSION
		);

		wp_enqueue_script(
			'wfcd-admin-script',
			WFCD_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery', 'wp-color-picker' ),
			WFCD_VERSION,
			true
		);
	}

	/**
	 * Отрисовка страницы настроек
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Недостаточно прав для доступа.', 'woo-fast-checkout-drawer' ) );
		}

		$notice = '';

		// Сохранение настроек
		if ( isset( $_POST['wfcd_save_settings'] ) ) {
			check_admin_referer( 'wfcd_settings_nonce_action', 'wfcd_settings_nonce' );

			$new_settings = array(
				'enable_drawer'       => ! empty( $_POST['enable_drawer'] ) ? 'yes' : 'no',
				'enable_quick_buy'    => ! empty( $_POST['enable_quick_buy'] ) ? 'yes' : 'no',
				'drawer_position'     => sanitize_text_field( wp_unslash( $_POST['drawer_position'] ?? 'right' ) ),
				'button_color'        => sanitize_hex_color( wp_unslash( $_POST['button_color'] ?? '#2271b1' ) ),
				'button_text_color'   => sanitize_hex_color( wp_unslash( $_POST['button_text_color'] ?? '#ffffff' ) ),
				'floating_cart'       => ! empty( $_POST['floating_cart'] ) ? 'yes' : 'no',
				'require_phone'       => 'yes',
				'require_email'       => ! empty( $_POST['require_email'] ) ? 'yes' : 'no',
				'default_payment'     => sanitize_text_field( wp_unslash( $_POST['default_payment'] ?? 'cod' ) ),
				'auto_open_on_add'    => ! empty( $_POST['auto_open_on_add'] ) ? 'yes' : 'no',
				'free_shipping_limit' => max( 0, (float) ( $_POST['free_shipping_limit'] ?? 0 ) ),
			);

			update_option( 'wfcd_settings', $new_settings );
			$notice = esc_html__( 'Настройки успешно сохранены!', 'woo-fast-checkout-drawer' );
		}

		$settings = $this->plugin->get_all_settings();
		$available_gateways = WC()->payment_gateways ? WC()->payment_gateways->get_available_payment_gateways() : array();
		?>
		<div class="wrap wfcd-admin-wrap">
			<header class="wfcd-admin-header">
				<h1>
					<?php esc_html_e( 'WooCommerce Fast Checkout & Slide Cart', 'woo-fast-checkout-drawer' ); ?>
					<span class="wfcd-admin-badge"><?php echo esc_html( WFCD_VERSION ); ?></span>
				</h1>
				<p class="description">
					<?php esc_html_e( 'Управление выезжающей корзиной, плавающим триггером и быстрым оформлением заказов в 1 клик.', 'woo-fast-checkout-drawer' ); ?>
				</p>
			</header>

			<?php if ( $notice ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php echo esc_html( $notice ); ?></p>
				</div>
			<?php endif; ?>

			<form method="post" action="" class="wfcd-admin-form">
				<?php wp_nonce_field( 'wfcd_settings_nonce_action', 'wfcd_settings_nonce' ); ?>

				<div class="wfcd-admin-grid">
					<!-- Секция 1: Выезжающая корзина -->
					<div class="wfcd-admin-card">
						<div class="wfcd-admin-card__header">
							<h2><?php esc_html_e( 'Выезжающая корзина (Slide Cart Drawer)', 'woo-fast-checkout-drawer' ); ?></h2>
						</div>
						<div class="wfcd-admin-card__body">
							<div class="wfcd-admin-field">
								<label class="wfcd-admin-toggle">
									<input type="checkbox" name="enable_drawer" value="yes" <?php checked( $settings['enable_drawer'], 'yes' ); ?> />
									<span class="wfcd-admin-toggle__slider"></span>
									<span class="wfcd-admin-toggle__label"><?php esc_html_e( 'Активировать Slide Cart Drawer', 'woo-fast-checkout-drawer' ); ?></span>
								</label>
							</div>

							<div class="wfcd-admin-field">
								<label class="wfcd-admin-toggle">
									<input type="checkbox" name="floating_cart" value="yes" <?php checked( $settings['floating_cart'], 'yes' ); ?> />
									<span class="wfcd-admin-toggle__slider"></span>
									<span class="wfcd-admin-toggle__label"><?php esc_html_e( 'Отображать плавающую иконку корзины на страницах', 'woo-fast-checkout-drawer' ); ?></span>
								</label>
							</div>

							<div class="wfcd-admin-field">
								<label class="wfcd-admin-toggle">
									<input type="checkbox" name="auto_open_on_add" value="yes" <?php checked( $settings['auto_open_on_add'], 'yes' ); ?> />
									<span class="wfcd-admin-toggle__slider"></span>
									<span class="wfcd-admin-toggle__label"><?php esc_html_e( 'Автоматически открывать корзину при добавлении товара', 'woo-fast-checkout-drawer' ); ?></span>
								</label>
							</div>

							<div class="wfcd-admin-field">
								<label for="drawer_position" class="wfcd-admin-label"><?php esc_html_e( 'Расположение дровера', 'woo-fast-checkout-drawer' ); ?></label>
								<select name="drawer_position" id="drawer_position" class="wfcd-admin-select">
									<option value="right" <?php selected( $settings['drawer_position'], 'right' ); ?>><?php esc_html_e( 'Справа', 'woo-fast-checkout-drawer' ); ?></option>
									<option value="left" <?php selected( $settings['drawer_position'], 'left' ); ?>><?php esc_html_e( 'Слева', 'woo-fast-checkout-drawer' ); ?></option>
								</select>
							</div>

							<div class="wfcd-admin-field">
								<label for="free_shipping_limit" class="wfcd-admin-label"><?php esc_html_e( 'Порог суммы для бесплатной доставки (0 для выключения)', 'woo-fast-checkout-drawer' ); ?></label>
								<input type="number" name="free_shipping_limit" id="free_shipping_limit" class="regular-text" value="<?php echo esc_attr( $settings['free_shipping_limit'] ); ?>" min="0" step="100" />
							</div>
						</div>
					</div>

					<!-- Секция 2: Заказ в 1 клик -->
					<div class="wfcd-admin-card">
						<div class="wfcd-admin-card__header">
							<h2><?php esc_html_e( 'Быстрый заказ в 1 клик (Express Checkout)', 'woo-fast-checkout-drawer' ); ?></h2>
						</div>
						<div class="wfcd-admin-card__body">
							<div class="wfcd-admin-field">
								<label class="wfcd-admin-toggle">
									<input type="checkbox" name="enable_quick_buy" value="yes" <?php checked( $settings['enable_quick_buy'], 'yes' ); ?> />
									<span class="wfcd-admin-toggle__slider"></span>
									<span class="wfcd-admin-toggle__label"><?php esc_html_e( 'Включить кнопку «Купить в 1 клик» на страницах товаров', 'woo-fast-checkout-drawer' ); ?></span>
								</label>
							</div>

							<div class="wfcd-admin-field">
								<label class="wfcd-admin-toggle">
									<input type="checkbox" name="require_email" value="yes" <?php checked( $settings['require_email'], 'yes' ); ?> />
									<span class="wfcd-admin-toggle__slider"></span>
									<span class="wfcd-admin-toggle__label"><?php esc_html_e( 'Требовать обязательный ввод Email', 'woo-fast-checkout-drawer' ); ?></span>
								</label>
							</div>

							<div class="wfcd-admin-field">
								<label for="default_payment" class="wfcd-admin-label"><?php esc_html_e( 'Способ оплаты для быстрых заказов', 'woo-fast-checkout-drawer' ); ?></label>
								<select name="default_payment" id="default_payment" class="wfcd-admin-select">
									<option value="cod" <?php selected( $settings['default_payment'], 'cod' ); ?>><?php esc_html_e( 'Оплата при доставке (COD)', 'woo-fast-checkout-drawer' ); ?></option>
									<?php foreach ( $available_gateways as $gateway_id => $gateway ) : ?>
										<?php if ( 'cod' !== $gateway_id ) : ?>
											<option value="<?php echo esc_attr( $gateway_id ); ?>" <?php selected( $settings['default_payment'], $gateway_id ); ?>>
												<?php echo esc_html( $gateway->get_title() ); ?>
											</option>
										<?php endif; ?>
									<?php endforeach; ?>
								</select>
							</div>

							<div class="wfcd-admin-field">
								<label for="button_color" class="wfcd-admin-label"><?php esc_html_e( 'Основной цвет кнопок', 'woo-fast-checkout-drawer' ); ?></label>
								<input type="text" name="button_color" id="button_color" class="wfcd-color-field" value="<?php echo esc_attr( $settings['button_color'] ); ?>" />
							</div>

							<div class="wfcd-admin-field">
								<label for="button_text_color" class="wfcd-admin-label"><?php esc_html_e( 'Цвет текста кнопок', 'woo-fast-checkout-drawer' ); ?></label>
								<input type="text" name="button_text_color" id="button_text_color" class="wfcd-color-field" value="<?php echo esc_attr( $settings['button_text_color'] ); ?>" />
							</div>
						</div>
					</div>
				</div>

				<div class="wfcd-admin-actions">
					<button type="submit" name="wfcd_save_settings" class="button button-primary button-hero">
						<?php esc_html_e( 'Сохранить настройки', 'woo-fast-checkout-drawer' ); ?>
					</button>
				</div>
			</form>
		</div>
		<?php
	}
}
