<?php
/**
 * Модуль REST API для Headless и внешних интеграций
 *
 * @package WooFastCheckoutDrawer
 */

namespace WooFastCheckout;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rest_Api {

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

		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Регистрация эндпоинтов REST API
	 */
	public function register_routes() {
		// Получение текущего состояния корзины
		register_rest_route(
			'wfcd/v1',
			'/cart-state',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_cart_state' ),
				'permission_callback' => '__return_true',
			)
		);

		// Создание быстрого заказа через REST API
		register_rest_route(
			'wfcd/v1',
			'/quick-order',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_quick_order' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'product_id'     => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'customer_name'  => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'customer_phone' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'quantity'       => array(
						'default'           => 1,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Получение данных корзины
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response
	 */
	public function get_cart_state( \WP_REST_Request $request ) {
		if ( ! WC()->cart ) {
			return new \WP_REST_Response(
				array(
					'item_count' => 0,
					'total'      => 0,
					'items'      => array(),
				),
				200
			);
		}

		$items = array();
		foreach ( WC()->cart->get_cart() as $key => $cart_item ) {
			$_product = $cart_item['data'];
			if ( ! $_product ) {
				continue;
			}

			$items[] = array(
				'key'        => $key,
				'product_id' => $cart_item['product_id'],
				'name'       => $_product->get_name(),
				'price'      => (float) $_product->get_price(),
				'quantity'   => $cart_item['quantity'],
				'subtotal'   => (float) $cart_item['line_subtotal'],
			);
		}

		return new \WP_REST_Response(
			array(
				'item_count' => WC()->cart->get_cart_contents_count(),
				'subtotal'   => (float) WC()->cart->get_subtotal(),
				'total'      => (float) WC()->cart->get_total( 'edit' ),
				'items'      => $items,
			),
			200
		);
	}

	/**
	 * Создание быстрого заказа через REST
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_quick_order( \WP_REST_Request $request ) {
		$product_id = (int) $request->get_param( 'product_id' );
		$qty        = max( 1, (int) $request->get_param( 'quantity' ) );
		$name       = (string) $request->get_param( 'customer_name' );
		$phone      = (string) $request->get_param( 'customer_phone' );

		$product = wc_get_product( $product_id );
		if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
			return new \WP_Error( 'invalid_product', esc_html__( 'Товар недоступен для покупки', 'woo-fast-checkout-drawer' ), array( 'status' => 400 ) );
		}

		try {
			$order = wc_create_order( array( 'status' => 'processing' ) );
			$order->add_product( $product, $qty );

			$name_parts = explode( ' ', trim( $name ), 2 );
			$clean_phone = preg_replace( '/[^0-9]/', '', $phone );
			$host        = (string) wp_parse_url( home_url(), PHP_URL_HOST );
			if ( empty( $host ) || strpos( $host, '.' ) === false ) {
				$host = 'store.local';
			}
			$guest_email = 'api_guest_' . $clean_phone . '@' . $host;

			$order->set_address(
				array(
					'first_name' => $name_parts[0],
					'last_name'  => $name_parts[1] ?? '',
					'phone'      => $phone,
					'email'      => $guest_email,
				),
				'billing'
			);

			$order->set_payment_method( 'cod' );
			$order->update_meta_data( '_wfcd_express_order', 'yes' );
			$order->calculate_totals();

			return new \WP_REST_Response(
				array(
					'success'      => true,
					'order_id'     => $order->get_id(),
					'order_number' => $order->get_order_number(),
					'total'        => (float) $order->get_total(),
				),
				201
			);
		} catch ( \Exception $e ) {
			return new \WP_Error( 'order_creation_failed', $e->getMessage(), array( 'status' => 500 ) );
		}
	}
}
