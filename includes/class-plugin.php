<?php
/**
 * Главный класс ядра плагина
 *
 * @package WooFastCheckoutDrawer
 */

namespace WooFastCheckout;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Plugin {

	/**
	 * Единственный экземпляр класса (Singleton)
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Настройки плагина
	 *
	 * @var array
	 */
	private $settings = array();

	/**
	 * Модуль выезжающей корзины
	 *
	 * @var Cart_Drawer
	 */
	public $cart_drawer;

	/**
	 * Модуль кнопки «Купить в 1 клик»
	 *
	 * @var Quick_Buy
	 */
	public $quick_buy;

	/**
	 * Модуль оформления и обработки заказов
	 *
	 * @var Order_Handler
	 */
	public $order_handler;

	/**
	 * Модуль REST API
	 *
	 * @var Rest_Api
	 */
	public $rest_api;

	/**
	 * Модуль административных настроек
	 *
	 * @var Admin_Settings
	 */
	public $admin_settings;

	/**
	 * Получение инстанса
	 *
	 * @return Plugin
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Конструктор
	 */
	private function __construct() {
		$this->load_settings();
		$this->include_dependencies();
		$this->init_modules();
		$this->register_hooks();
	}

	/**
	 * Загрузка опций плагина
	 */
	private function load_settings() {
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

		$this->settings = wp_parse_args( get_option( 'wfcd_settings', array() ), $defaults );
	}

	/**
	 * Подключение файлов классов
	 */
	private function include_dependencies() {
		require_once WFCD_PLUGIN_DIR . 'includes/class-cart-drawer.php';
		require_once WFCD_PLUGIN_DIR . 'includes/class-quick-buy.php';
		require_once WFCD_PLUGIN_DIR . 'includes/class-order-handler.php';
		require_once WFCD_PLUGIN_DIR . 'includes/class-rest-api.php';
		require_once WFCD_PLUGIN_DIR . 'includes/class-admin-settings.php';
	}

	/**
	 * Инициализация модулей
	 */
	private function init_modules() {
		$this->cart_drawer    = new Cart_Drawer( $this );
		$this->quick_buy      = new Quick_Buy( $this );
		$this->order_handler  = new Order_Handler( $this );
		$this->rest_api       = new Rest_Api( $this );
		$this->admin_settings = new Admin_Settings( $this );
	}

	/**
	 * Регистрация глобальных хуков
	 */
	private function register_hooks() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Локализация
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'woo-fast-checkout-drawer', false, dirname( WFCD_PLUGIN_BASENAME ) . '/languages' );
	}

	/**
	 * Подключение скриптов и стилей фронтенда
	 */
	public function enqueue_frontend_assets() {
		// Подключаем только если включен дровер или быстрый заказ
		if ( 'yes' !== $this->get_setting( 'enable_drawer' ) && 'yes' !== $this->get_setting( 'enable_quick_buy' ) ) {
			return;
		}

		wp_enqueue_style(
			'wfcd-frontend',
			WFCD_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			WFCD_VERSION
		);

		// Передача динамических CSS-переменных
		$btn_color      = sanitize_hex_color( $this->get_setting( 'button_color', '#2271b1' ) );
		$btn_text_color = sanitize_hex_color( $this->get_setting( 'button_text_color', '#ffffff' ) );
		$custom_css     = ":root { --wfcd-primary-color: {$btn_color}; --wfcd-text-color: {$btn_text_color}; }";
		wp_add_inline_style( 'wfcd-frontend', $custom_css );

		wp_enqueue_script(
			'wfcd-frontend',
			WFCD_PLUGIN_URL . 'assets/js/frontend.js',
			array( 'jquery' ),
			WFCD_VERSION,
			true
		);

		wp_localize_script(
			'wfcd-frontend',
			'wfcd_params',
			array(
				'ajax_url'            => admin_url( 'admin-ajax.php' ),
				'nonce'               => wp_create_nonce( 'wfcd_frontend_nonce' ),
				'wc_ajax_url'         => \WC_AJAX::get_endpoint( '%%endpoint%%' ),
				'currency_symbol'     => get_woocommerce_currency_symbol(),
				'auto_open_on_add'    => 'yes' === $this->get_setting( 'auto_open_on_add' ),
				'free_shipping_limit' => (float) $this->get_setting( 'free_shipping_limit', 0 ),
				'i18n'                => array(
					'submitting'      => esc_html__( 'Оформление заказа...', 'woo-fast-checkout-drawer' ),
					'order_success'   => esc_html__( 'Заказ успешно оформлен!', 'woo-fast-checkout-drawer' ),
					'cart_empty'      => esc_html__( 'Ваша корзина пуста', 'woo-fast-checkout-drawer' ),
					'remove_confirm'  => esc_html__( 'Удалить товар из корзины?', 'woo-fast-checkout-drawer' ),
					'error_general'   => esc_html__( 'Произошла ошибка. Попробуйте еще раз.', 'woo-fast-checkout-drawer' ),
					'free_shipping'   => esc_html__( 'Поздравляем! Вам доступна бесплатная доставка.', 'woo-fast-checkout-drawer' ),
					'away_from_free'  => esc_html__( 'До бесплатной доставки осталось %s', 'woo-fast-checkout-drawer' ),
				),
			)
		);
	}

	/**
	 * Получение значения настройки
	 *
	 * @param string $key
	 * @param mixed  $default
	 * @return mixed
	 */
	public function get_setting( $key, $default = '' ) {
		return $this->settings[ $key ] ?? $default;
	}

	/**
	 * Получение всех настроек
	 *
	 * @return array
	 */
	public function get_all_settings() {
		return $this->settings;
	}
}
