<?php
/**
 * Модуль обработки и создания заказов в 1 клик через WooCommerce API
 *
 * @package WooFastCheckoutDrawer
 */

namespace WooFastCheckout;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Order_Handler {

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

		// Обработка AJAX оформления быстрого заказа
		add_action( 'wp_ajax_wfcd_submit_quick_order', array( $this, 'ajax_handle_quick_order' ) );
		add_action( 'wp_ajax_nopriv_wfcd_submit_quick_order', array( $this, 'ajax_handle_quick_order' ) );

		// Демонстрация глубокого хука расчета корзины: woocommerce_before_calculate_totals
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'hook_before_calculate_totals' ), 10, 1 );
	}

	/**
	 * Хук woocommerce_before_calculate_totals
	 * Позволяет применять динамическую ценовую логику или промо-скидки
	 *
	 * @param \WC_Cart $cart
	 */
	public function hook_before_calculate_totals( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		if ( did_action( 'woocommerce_before_calculate_totals' ) >= 2 ) {
			return;
		}

		// Фильтр для сторонних расширений или кастомных правил расчета
		do_action( 'wfcd_before_cart_calculate_totals', $cart );
	}

	/**
	 * AJAX обработчик мгновенного создания заказа
	 */
	public function ajax_handle_quick_order() {
		check_ajax_referer( 'wfcd_frontend_nonce', 'nonce' );

		$product_id   = (int) ( $_POST['product_id'] ?? 0 );
		$variation_id = (int) ( $_POST['variation_id'] ?? 0 );
		$qty          = max( 1, (int) ( $_POST['quantity'] ?? 1 ) );
		$name         = sanitize_text_field( wp_unslash( $_POST['customer_name'] ?? '' ) );
		$phone        = sanitize_text_field( wp_unslash( $_POST['customer_phone'] ?? '' ) );
		$email        = sanitize_email( wp_unslash( $_POST['customer_email'] ?? '' ) );
		$comment      = sanitize_textarea_field( wp_unslash( $_POST['customer_comment'] ?? '' ) );

		// Валидация входных данных
		if ( empty( $name ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Пожалуйста, укажите ваше имя.', 'woo-fast-checkout-drawer' ) ) );
		}

		if ( empty( $phone ) || strlen( preg_replace( '/[^0-9]/', '', $phone ) ) < 7 ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Пожалуйста, введите корректный номер телефона.', 'woo-fast-checkout-drawer' ) ) );
		}

		$require_email = 'yes' === $this->plugin->get_setting( 'require_email', 'no' );
		if ( $require_email && ( empty( $email ) || ! is_email( $email ) ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Пожалуйста, укажите корректный адрес эл. почты.', 'woo-fast-checkout-drawer' ) ) );
		}

		// Если email не передан, формируем валидный внутренний адрес для WooCommerce
		if ( empty( $email ) ) {
			$clean_phone = preg_replace( '/[^0-9]/', '', $phone );
			$host        = (string) wp_parse_url( home_url(), PHP_URL_HOST );
			if ( empty( $host ) || strpos( $host, '.' ) === false ) {
				$host = 'store.local';
			}
			$email = 'guest_' . $clean_phone . '@' . $host;
		}

		// Получение объекта товара
		$target_product_id = $variation_id > 0 ? $variation_id : $product_id;
		$product           = wc_get_product( $target_product_id );

		if ( ! $product || ! $product->is_purchasable() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Выбранный товар недоступен для покупки.', 'woo-fast-checkout-drawer' ) ) );
		}

		if ( ! $product->is_in_stock() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'К сожалению, данного товара нет в наличии.', 'woo-fast-checkout-drawer' ) ) );
		}

		try {
			// Создание заказа через родное WooCommerce API
			$order = wc_create_order(
				array(
					'status'        => 'processing',
					'customer_note' => $comment ? sprintf( esc_html__( '[1-Click Express] %s', 'woo-fast-checkout-drawer' ), $comment ) : esc_html__( '[1-Click Express Order]', 'woo-fast-checkout-drawer' ),
				)
			);

			if ( is_wp_error( $order ) || ! $order ) {
				throw new \Exception( esc_html__( 'Не удалось инициализировать заказ.', 'woo-fast-checkout-drawer' ) );
			}

			// Добавление товара в заказ
			$item_id = $order->add_product( $product, $qty );
			if ( ! $item_id ) {
				throw new \Exception( esc_html__( 'Ошибка добавления товара в заказ.', 'woo-fast-checkout-drawer' ) );
			}

			// Разделение имени на First Name / Last Name
			$name_parts = explode( ' ', trim( $name ), 2 );
			$first_name = $name_parts[0];
			$last_name  = $name_parts[1] ?? '';

			$address = array(
				'first_name' => $first_name,
				'last_name'  => $last_name,
				'email'      => $email,
				'phone'      => $phone,
			);

			$order->set_address( $address, 'billing' );
			$order->set_address( $address, 'shipping' );

			// Способ оплаты по умолчанию
			$payment_method = sanitize_text_field( $this->plugin->get_setting( 'default_payment', 'cod' ) );
			$available_gateways = WC()->payment_gateways->get_available_payment_gateways();

			if ( isset( $available_gateways[ $payment_method ] ) ) {
				$order->set_payment_method( $available_gateways[ $payment_method ] );
			} else {
				$order->set_payment_method( 'cod' );
				$order->set_payment_method_title( esc_html__( 'Оплата при получении (Express)', 'woo-fast-checkout-drawer' ) );
			}

			// Метаданные заказа
			$order->update_meta_data( '_wfcd_express_order', 'yes' );
			$order->update_meta_data( '_wfcd_customer_raw_phone', $phone );

			// Расчет итогов заказа
			$order->calculate_totals();

			// Хук завершения оформления заказа для совместимости с аналитикой и плагинами
			do_action( 'woocommerce_checkout_order_processed', $order->get_id(), $_POST, $order );
			do_action( 'woocommerce_new_order', $order->get_id(), $order );

			// Отправка стандартного уведомления админу WooCommerce
			$mailer = WC()->mailer();
			$emails = $mailer->get_emails();
			if ( ! empty( $emails['WC_Email_New_Order'] ) ) {
				$emails['WC_Email_New_Order']->trigger( $order->get_id() );
			}

			// Формирование ссылки на страницу «Спасибо за заказ»
			$redirect_url = $order->get_checkout_order_received_url();

			wp_send_json_success(
				array(
					'order_id'     => $order->get_id(),
					'order_key'    => $order->get_order_key(),
					'redirect_url' => $redirect_url,
					'message'      => sprintf(
						/* translators: %s: Номер заказа */
						esc_html__( 'Спасибо! Ваш заказ #%s успешно принят. Наш менеджер скоро свяжется с вами.', 'woo-fast-checkout-drawer' ),
						$order->get_order_number()
					),
				)
			);
		} catch ( \Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}
}
