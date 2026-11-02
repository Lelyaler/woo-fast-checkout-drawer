<?php
/**
 * Модуль выезжающей корзины (Slide Cart Drawer)
 *
 * @package WooFastCheckoutDrawer
 */

namespace WooFastCheckout;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Cart_Drawer {

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

		if ( 'yes' !== $this->plugin->get_setting( 'enable_drawer' ) ) {
			return;
		}

		add_action( 'wp_footer', array( $this, 'render_drawer_container' ) );
		add_filter( 'woocommerce_add_to_cart_fragments', array( $this, 'add_cart_fragments' ) );

		// AJAX обработчики корзины
		add_action( 'wp_ajax_wfcd_update_item_qty', array( $this, 'ajax_update_item_qty' ) );
		add_action( 'wp_ajax_nopriv_wfcd_update_item_qty', array( $this, 'ajax_update_item_qty' ) );

		add_action( 'wp_ajax_wfcd_remove_cart_item', array( $this, 'ajax_remove_cart_item' ) );
		add_action( 'wp_ajax_nopriv_wfcd_remove_cart_item', array( $this, 'ajax_remove_cart_item' ) );
	}

	/**
	 * Отрисовка контейнера дровера и плавающего триггера в футере
	 */
	public function render_drawer_container() {
		$position      = $this->plugin->get_setting( 'drawer_position', 'right' );
		$show_floating = 'yes' === $this->plugin->get_setting( 'floating_cart', 'yes' );
		?>
		<!-- WFCD Slide Cart Overlay -->
		<div id="wfcd-drawer-overlay" class="wfcd-overlay"></div>

		<!-- WFCD Slide Cart Drawer -->
		<aside id="wfcd-cart-drawer" class="wfcd-drawer wfcd-drawer--<?php echo esc_attr( $position ); ?>" aria-hidden="true" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Корзина покупок', 'woo-fast-checkout-drawer' ); ?>">
			<div class="wfcd-drawer__inner">
				<header class="wfcd-drawer__header">
					<div class="wfcd-drawer__title-wrap">
						<h2 class="wfcd-drawer__title"><?php esc_html_e( 'Корзина', 'woo-fast-checkout-drawer' ); ?></h2>
						<span class="wfcd-drawer__count-badge" id="wfcd-header-count">
							<?php echo esc_html( WC()->cart ? WC()->cart->get_cart_contents_count() : 0 ); ?>
						</span>
					</div>
					<button type="button" class="wfcd-drawer__close" aria-label="<?php esc_attr_e( 'Закрыть корзину', 'woo-fast-checkout-drawer' ); ?>">&times;</button>
				</header>

				<!-- Прогресс-бар бесплатной доставки -->
				<?php $this->render_shipping_progress(); ?>

				<!-- Содержимое корзины -->
				<div class="wfcd-drawer__content" id="wfcd-drawer-content">
					<?php $this->render_cart_items(); ?>
				</div>

				<!-- Футер дровера с итогами и кнопками перехода -->
				<footer class="wfcd-drawer__footer" id="wfcd-drawer-footer">
					<?php $this->render_drawer_footer(); ?>
				</footer>
			</div>
		</aside>

		<?php if ( $show_floating ) : ?>
			<!-- WFCD Floating Cart Trigger Button -->
			<button type="button" id="wfcd-floating-cart-btn" class="wfcd-floating-btn" aria-label="<?php esc_attr_e( 'Открыть корзину', 'woo-fast-checkout-drawer' ); ?>">
				<svg class="wfcd-floating-btn__icon" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<circle cx="9" cy="21" r="1"></circle>
					<circle cx="20" cy="21" r="1"></circle>
					<path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
				</svg>
				<span class="wfcd-floating-btn__badge" id="wfcd-floating-badge">
					<?php echo esc_html( WC()->cart ? WC()->cart->get_cart_contents_count() : 0 ); ?>
				</span>
			</button>
		<?php endif; ?>
		<?php
	}

	/**
	 * Отрисовка полосы прогресса до бесплатной доставки
	 */
	public function render_shipping_progress() {
		$free_limit = (float) $this->plugin->get_setting( 'free_shipping_limit', 0 );
		if ( $free_limit <= 0 || ! WC()->cart ) {
			return;
		}

		$current_total = (float) WC()->cart->get_displayed_subtotal();
		$percent       = min( 100, round( ( $current_total / $free_limit ) * 100 ) );
		$difference    = max( 0, $free_limit - $current_total );
		?>
		<div class="wfcd-shipping-progress" id="wfcd-shipping-progress-box">
			<div class="wfcd-shipping-progress__text">
				<?php if ( $difference <= 0 ) : ?>
					<span class="wfcd-shipping-progress__success">
						<?php esc_html_e( '🎉 Поздравляем! Вам доступна бесплатная доставка!', 'woo-fast-checkout-drawer' ); ?>
					</span>
				<?php else : ?>
					<?php
					printf(
						/* translators: %s: Оставшаяся сумма */
						esc_html__( 'Добавьте товаров еще на %s для бесплатной доставки', 'woo-fast-checkout-drawer' ),
						'<strong>' . wc_price( $difference ) . '</strong>'
					);
					?>
				<?php endif; ?>
			</div>
			<div class="wfcd-shipping-progress__bar">
				<div class="wfcd-shipping-progress__fill" style="width: <?php echo esc_attr( $percent ); ?>%;"></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Отрисовка списка товаров в корзине
	 */
	public function render_cart_items() {
		if ( ! WC()->cart || WC()->cart->is_empty() ) {
			?>
			<div class="wfcd-cart-empty">
				<div class="wfcd-cart-empty__icon">🛍️</div>
				<p class="wfcd-cart-empty__text"><?php esc_html_e( 'Ваша корзина пока пуста', 'woo-fast-checkout-drawer' ); ?></p>
				<a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>" class="button wfcd-btn wfcd-btn--primary">
					<?php esc_html_e( 'Перейти в каталог', 'woo-fast-checkout-drawer' ); ?>
				</a>
			</div>
			<?php
			return;
		}

		?>
		<ul class="wfcd-cart-list">
			<?php
			foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) :
				$_product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
				$product_id = apply_filters( 'woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key );

				if ( ! $_product || ! $_product->exists() || $cart_item['quantity'] <= 0 || ! apply_filters( 'woocommerce_cart_item_visible', true, $cart_item, $cart_item_key ) ) {
					continue;
				}

				$product_permalink = apply_filters( 'woocommerce_cart_item_permalink', $_product->is_visible() ? $_product->get_permalink( $cart_item ) : '', $cart_item, $cart_item_key );
				$thumbnail = apply_filters( 'woocommerce_cart_item_thumbnail', $_product->get_image( array( 70, 70 ) ), $cart_item, $cart_item_key );
				?>
				<li class="wfcd-cart-item" data-key="<?php echo esc_attr( $cart_item_key ); ?>">
					<div class="wfcd-cart-item__thumb">
						<?php if ( $product_permalink ) : ?>
							<a href="<?php echo esc_url( $product_permalink ); ?>"><?php echo $thumbnail; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
						<?php else : ?>
							<?php echo $thumbnail; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php endif; ?>
					</div>

					<div class="wfcd-cart-item__details">
						<h3 class="wfcd-cart-item__name">
							<?php if ( $product_permalink ) : ?>
								<a href="<?php echo esc_url( $product_permalink ); ?>"><?php echo esc_html( $_product->get_name() ); ?></a>
							<?php else : ?>
								<?php echo esc_html( $_product->get_name() ); ?>
							<?php endif; ?>
						</h3>

						<?php echo wc_get_formatted_cart_item_data( $cart_item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

						<div class="wfcd-cart-item__price">
							<?php echo apply_filters( 'woocommerce_cart_item_price', WC()->cart->get_product_price( $_product ), $cart_item, $cart_item_key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</div>

						<div class="wfcd-cart-item__actions">
							<div class="wfcd-qty-control" data-key="<?php echo esc_attr( $cart_item_key ); ?>">
								<button type="button" class="wfcd-qty-control__btn wfcd-qty-control__btn--minus" aria-label="<?php esc_attr_e( 'Уменьшить количество', 'woo-fast-checkout-drawer' ); ?>">-</button>
								<input type="number" class="wfcd-qty-control__input" value="<?php echo esc_attr( $cart_item['quantity'] ); ?>" min="1" max="<?php echo esc_attr( $_product->get_max_purchase_quantity() > 0 ? $_product->get_max_purchase_quantity() : 999 ); ?>" step="1" readonly />
								<button type="button" class="wfcd-qty-control__btn wfcd-qty-control__btn--plus" aria-label="<?php esc_attr_e( 'Увеличить количество', 'woo-fast-checkout-drawer' ); ?>">+</button>
							</div>

							<button type="button" class="wfcd-cart-item__remove" data-key="<?php echo esc_attr( $cart_item_key ); ?>" aria-label="<?php esc_attr_e( 'Удалить позицию', 'woo-fast-checkout-drawer' ); ?>">
								<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
									<polyline points="3 6 5 6 21 6"></polyline>
									<path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
								</svg>
							</button>
						</div>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	/**
	 * Отрисовка футера дровера (подытог и кнопки оформления)
	 */
	public function render_drawer_footer() {
		if ( ! WC()->cart || WC()->cart->is_empty() ) {
			return;
		}

		$checkout_url = wc_get_checkout_url();
		$cart_url     = wc_get_cart_url();
		?>
		<div class="wfcd-drawer__summary">
			<div class="wfcd-drawer__summary-row">
				<span class="wfcd-drawer__summary-label"><?php esc_html_e( 'Подытог:', 'woo-fast-checkout-drawer' ); ?></span>
				<span class="wfcd-drawer__summary-val" id="wfcd-subtotal-val"><?php wc_cart_totals_subtotal_html(); ?></span>
			</div>
		</div>

		<div class="wfcd-drawer__checkout-actions">
			<a href="<?php echo esc_url( $checkout_url ); ?>" class="button wfcd-btn wfcd-btn--checkout">
				<?php esc_html_e( 'Перейти к оформлению', 'woo-fast-checkout-drawer' ); ?>
			</a>
			<a href="<?php echo esc_url( $cart_url ); ?>" class="wfcd-btn wfcd-btn--view-cart">
				<?php esc_html_e( 'Открыть корзину', 'woo-fast-checkout-drawer' ); ?>
			</a>
		</div>
		<?php
	}

	/**
	 * Интеграция с WooCommerce Cart Fragments: обновление элементов при стандартном AJAX
	 *
	 * @param array $fragments
	 * @return array
	 */
	public function add_cart_fragments( $fragments ) {
		$count = WC()->cart ? WC()->cart->get_cart_contents_count() : 0;

		ob_start();
		$this->render_cart_items();
		$content_html = ob_get_clean();

		ob_start();
		$this->render_drawer_footer();
		$footer_html = ob_get_clean();

		ob_start();
		$this->render_shipping_progress();
		$progress_html = ob_get_clean();

		$fragments['div#wfcd-drawer-content']      = '<div class="wfcd-drawer__content" id="wfcd-drawer-content">' . $content_html . '</div>';
		$fragments['footer#wfcd-drawer-footer']    = '<footer class="wfcd-drawer__footer" id="wfcd-drawer-footer">' . $footer_html . '</footer>';
		$fragments['div#wfcd-shipping-progress-box'] = $progress_html;
		$fragments['span#wfcd-header-count']       = '<span class="wfcd-drawer__count-badge" id="wfcd-header-count">' . esc_html( $count ) . '</span>';
		$fragments['span#wfcd-floating-badge']     = '<span class="wfcd-floating-btn__badge" id="wfcd-floating-badge">' . esc_html( $count ) . '</span>';

		return $fragments;
	}

	/**
	 * AJAX обновление количества товара в корзине
	 */
	public function ajax_update_item_qty() {
		check_ajax_referer( 'wfcd_frontend_nonce', 'nonce' );

		$cart_item_key = sanitize_text_field( wp_unslash( $_POST['cart_item_key'] ?? '' ) );
		$new_qty       = (int) ( $_POST['quantity'] ?? 1 );

		if ( empty( $cart_item_key ) || $new_qty < 1 || ! WC()->cart ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Некорректные параметры корзины.', 'woo-fast-checkout-drawer' ) ) );
		}

		WC()->cart->set_quantity( $cart_item_key, $new_qty, true );
		WC()->cart->calculate_totals();

		$this->send_refreshed_fragments();
	}

	/**
	 * AJAX удаление товара из корзины
	 */
	public function ajax_remove_cart_item() {
		check_ajax_referer( 'wfcd_frontend_nonce', 'nonce' );

		$cart_item_key = sanitize_text_field( wp_unslash( $_POST['cart_item_key'] ?? '' ) );

		if ( empty( $cart_item_key ) || ! WC()->cart ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Позиция корзины не найдена.', 'woo-fast-checkout-drawer' ) ) );
		}

		WC()->cart->remove_cart_item( $cart_item_key );
		WC()->cart->calculate_totals();

		$this->send_refreshed_fragments();
	}

	/**
	 * Вспомогательный метод отдачи обновлённых фрагментов клиенту
	 */
	private function send_refreshed_fragments() {
		$fragments = $this->add_cart_fragments( array() );

		wp_send_json_success(
			array(
				'fragments' => $fragments,
				'count'     => WC()->cart ? WC()->cart->get_cart_contents_count() : 0,
			)
		);
	}
}
