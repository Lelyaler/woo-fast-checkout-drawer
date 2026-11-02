<?php
/**
 * Модуль кнопки «Купить в 1 клик» и модального окна быстрого заказа
 *
 * @package WooFastCheckoutDrawer
 */

namespace WooFastCheckout;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Quick_Buy {

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

		if ( 'yes' !== $this->plugin->get_setting( 'enable_quick_buy' ) ) {
			return;
		}

		// Вывод кнопки в карточке товара рядом с кнопкой «В корзину»
		add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'render_single_quick_buy_button' ), 20 );

		// Модальное окно быстрого оформления в футере
		add_action( 'wp_footer', array( $this, 'render_modal_container' ) );

		// Шорткод кнопки быстрого заказа [wfcd_quick_buy product_id="123"]
		add_shortcode( 'wfcd_quick_buy', array( $this, 'shortcode_quick_buy' ) );
	}

	/**
	 * Отрисовка кнопки быстрого заказа на странице товара
	 */
	public function render_single_quick_buy_button() {
		global $product;
		if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
			return;
		}

		$product_id = $product->get_id();
		$this->render_button_html( $product_id, $product );
	}

	/**
	 * Шорткод [wfcd_quick_buy id="..."]
	 *
	 * @param array $atts
	 * @return string
	 */
	public function shortcode_quick_buy( $atts ) {
		$atts = shortcode_atts(
			array(
				'id' => 0,
			),
			$atts,
			'wfcd_quick_buy'
		);

		$product_id = (int) $atts['id'];
		if ( ! $product_id ) {
			global $product;
			if ( $product ) {
				$product_id = $product->get_id();
			}
		}

		if ( ! $product_id ) {
			return '';
		}

		$product = wc_get_product( $product_id );
		if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
			return '';
		}

		ob_start();
		$this->render_button_html( $product_id, $product );
		return ob_get_clean();
	}

	/**
	 * Вывод HTML разметки кнопки быстрого заказа
	 *
	 * @param int         $product_id
	 * @param \WC_Product $product
	 */
	private function render_button_html( $product_id, $product ) {
		$thumb = $product->get_image( array( 64, 64 ) );
		?>
		<button type="button"
			class="button wfcd-quick-buy-btn"
			data-product-id="<?php echo esc_attr( $product_id ); ?>"
			data-product-name="<?php echo esc_attr( $product->get_name() ); ?>"
			data-product-price="<?php echo esc_attr( wp_strip_all_tags( wc_price( $product->get_price() ) ) ); ?>"
			data-product-thumb="<?php echo esc_attr( $thumb ); ?>"
			data-is-variable="<?php echo esc_attr( $product->is_type( 'variable' ) ? 'yes' : 'no' ); ?>">
			<svg class="wfcd-quick-buy-btn__icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
				<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
			</svg>
			<span><?php esc_html_e( 'Купить в 1 клик', 'woo-fast-checkout-drawer' ); ?></span>
		</button>
		<?php
	}

	/**
	 * Отрисовка модального окна быстрого заказа
	 */
	public function render_modal_container() {
		$require_email = 'yes' === $this->plugin->get_setting( 'require_email', 'no' );
		?>
		<!-- WFCD Quick Buy Modal Container -->
		<div id="wfcd-modal-overlay" class="wfcd-modal-overlay" aria-hidden="true">
			<div class="wfcd-modal" role="dialog" aria-modal="true" aria-labelledby="wfcd-modal-title">
				<header class="wfcd-modal__header">
					<h2 id="wfcd-modal-title" class="wfcd-modal__title"><?php esc_html_e( 'Быстрый заказ в 1 клик', 'woo-fast-checkout-drawer' ); ?></h2>
					<button type="button" class="wfcd-modal__close" aria-label="<?php esc_attr_e( 'Закрыть окно', 'woo-fast-checkout-drawer' ); ?>">&times;</button>
				</header>

				<form id="wfcd-quick-order-form" class="wfcd-modal__form">
					<!-- Карточка выбранного товара -->
					<div class="wfcd-modal__product-preview" id="wfcd-modal-product-box">
						<div class="wfcd-modal__product-thumb" id="wfcd-modal-thumb"></div>
						<div class="wfcd-modal__product-info">
							<h3 class="wfcd-modal__product-name" id="wfcd-modal-name"></h3>
							<div class="wfcd-modal__product-price" id="wfcd-modal-price"></div>
						</div>
					</div>

					<input type="hidden" name="product_id" id="wfcd-input-product-id" value="" />
					<input type="hidden" name="variation_id" id="wfcd-input-variation-id" value="0" />
					<input type="hidden" name="quantity" id="wfcd-input-qty" value="1" />

					<!-- Поля данных покупателя -->
					<div class="wfcd-form-group">
						<label for="wfcd-customer-name" class="wfcd-label">
							<?php esc_html_e( 'Ваше имя', 'woo-fast-checkout-drawer' ); ?> <span class="required">*</span>
						</label>
						<input type="text" name="customer_name" id="wfcd-customer-name" class="wfcd-input" placeholder="<?php esc_attr_e( 'Иван Иванов', 'woo-fast-checkout-drawer' ); ?>" required />
					</div>

					<div class="wfcd-form-group">
						<label for="wfcd-customer-phone" class="wfcd-label">
							<?php esc_html_e( 'Телефон', 'woo-fast-checkout-drawer' ); ?> <span class="required">*</span>
						</label>
						<input type="tel" name="customer_phone" id="wfcd-customer-phone" class="wfcd-input" placeholder="+7 (999) 000-00-00" required />
					</div>

					<?php if ( $require_email ) : ?>
						<div class="wfcd-form-group">
							<label for="wfcd-customer-email" class="wfcd-label">
								<?php esc_html_e( 'Email', 'woo-fast-checkout-drawer' ); ?> <span class="required">*</span>
							</label>
							<input type="email" name="customer_email" id="wfcd-customer-email" class="wfcd-input" placeholder="example@mail.com" required />
						</div>
					<?php endif; ?>

					<div class="wfcd-form-group">
						<label for="wfcd-customer-comment" class="wfcd-label">
							<?php esc_html_e( 'Комментарий к заказу (необязательно)', 'woo-fast-checkout-drawer' ); ?>
						</label>
						<textarea name="customer_comment" id="wfcd-customer-comment" class="wfcd-textarea" rows="2" placeholder="<?php esc_attr_e( 'Уточнения по доставке, размеру...', 'woo-fast-checkout-drawer' ); ?>"></textarea>
					</div>

					<div class="wfcd-modal__feedback" id="wfcd-modal-feedback" style="display:none;"></div>

					<footer class="wfcd-modal__footer">
						<button type="submit" class="button wfcd-btn wfcd-btn--submit" id="wfcd-submit-btn">
							<span class="wfcd-btn__text"><?php esc_html_e( 'Подтвердить заказ', 'woo-fast-checkout-drawer' ); ?></span>
							<span class="wfcd-btn__spinner" style="display:none;">⏳</span>
						</button>
					</footer>
				</form>
			</div>
		</div>
		<?php
	}
}
