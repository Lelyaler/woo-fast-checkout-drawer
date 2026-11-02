/**
 * Скрипты фронтенда WooCommerce Fast Checkout & Slide Cart Drawer
 */
(function ($) {
  'use strict';

  const WFCD = {
    init: function () {
      this.cacheDOM();
      this.bindEvents();
    },

    cacheDOM: function () {
      this.$drawer = $('#wfcd-cart-drawer');
      this.$overlay = $('#wfcd-drawer-overlay');
      this.$floatingBtn = $('#wfcd-floating-cart-btn');
      this.$closeBtn = $('.wfcd-drawer__close');
      this.$modalOverlay = $('#wfcd-modal-overlay');
      this.$modalClose = $('.wfcd-modal__close');
      this.$quickOrderForm = $('#wfcd-quick-order-form');
    },

    bindEvents: function () {
      const self = this;

      // Открытие дровера по плавающей кнопке или внешним ссылкам с классом .wfcd-open-cart
      $(document).on('click', '#wfcd-floating-cart-btn, .wfcd-open-cart', function (e) {
        e.preventDefault();
        self.openDrawer();
      });

      // Закрытие дровера
      $(document).on('click', '.wfcd-drawer__close, #wfcd-drawer-overlay', function () {
        self.closeDrawer();
      });

      // Закрытие по клавише Escape
      $(document).on('keydown', function (e) {
        if (e.key === 'Escape' || e.keyCode === 27) {
          self.closeDrawer();
          self.closeModal();
        }
      });

      // Автоматическое открытие дровера при добавлении товара в корзину через AJAX
      if (typeof wfcd_params !== 'undefined' && wfcd_params.auto_open_on_add) {
        $(document.body).on('added_to_cart', function () {
          self.openDrawer();
        });
      }

      // Изменение количества товара (+ / -)
      $(document).on('click', '.wfcd-qty-control__btn', function (e) {
        e.preventDefault();
        const $btn = $(this);
        const $input = $btn.siblings('.wfcd-qty-control__input');
        const key = $btn.closest('.wfcd-qty-control').data('key');
        let currentVal = parseInt($input.val(), 10) || 1;
        const maxVal = parseInt($input.attr('max'), 10) || 999;
        const minVal = parseInt($input.attr('min'), 10) || 1;

        if ($btn.hasClass('wfcd-qty-control__btn--plus')) {
          if (currentVal < maxVal) {
            currentVal += 1;
            $input.val(currentVal);
            self.updateItemQty(key, currentVal);
          }
        } else if ($btn.hasClass('wfcd-qty-control__btn--minus')) {
          if (currentVal > minVal) {
            currentVal -= 1;
            $input.val(currentVal);
            self.updateItemQty(key, currentVal);
          }
        }
      });

      // Удаление товара из корзины
      $(document).on('click', '.wfcd-cart-item__remove', function (e) {
        e.preventDefault();
        const key = $(this).data('key');
        if (confirm(wfcd_params.i18n.remove_confirm)) {
          self.removeCartItem(key);
        }
      });

      // Клик по кнопке «Купить в 1 клик»
      $(document).on('click', '.wfcd-quick-buy-btn', function (e) {
        e.preventDefault();
        const $btn = $(this);
        self.openQuickBuyModal($btn);
      });

      // Закрытие модального окна
      $(document).on('click', '.wfcd-modal__close, #wfcd-modal-overlay', function (e) {
        if ($(e.target).is('#wfcd-modal-overlay') || $(e.target).closest('.wfcd-modal__close').length) {
          self.closeModal();
        }
      });

      // Отправка формы быстрого заказа
      if (this.$quickOrderForm.length) {
        this.$quickOrderForm.on('submit', function (e) {
          e.preventDefault();
          self.submitQuickOrder($(this));
        });
      }
    },

    openDrawer: function () {
      this.$drawer.addClass('is-open').attr('aria-hidden', 'false');
      this.$overlay.addClass('is-active');
      $('body').addClass('wfcd-drawer-opened');
    },

    closeDrawer: function () {
      this.$drawer.removeClass('is-open').attr('aria-hidden', 'true');
      this.$overlay.removeClass('is-active');
      $('body').removeClass('wfcd-drawer-opened');
    },

    openQuickBuyModal: function ($btn) {
      const productId = $btn.data('product-id');
      const productName = $btn.data('product-name');
      const productPrice = $btn.data('product-price');
      const productThumb = $btn.data('product-thumb');

      // Проверка на вариативный товар: если есть выбранная вариация в форме
      const $form = $btn.closest('form.cart');
      let variationId = 0;
      let qty = 1;

      if ($form.length) {
        const varInput = $form.find('input[name="variation_id"]');
        if (varInput.length && parseInt(varInput.val(), 10) > 0) {
          variationId = parseInt(varInput.val(), 10);
        }
        const qtyInput = $form.find('input[name="quantity"]');
        if (qtyInput.length) {
          qty = parseInt(qtyInput.val(), 10) || 1;
        }
      }

      $('#wfcd-input-product-id').val(productId);
      $('#wfcd-input-variation-id').val(variationId);
      $('#wfcd-input-qty').val(qty);
      $('#wfcd-modal-name').text(productName);
      $('#wfcd-modal-price').text(productPrice);
      $('#wfcd-modal-thumb').html(productThumb);
      $('#wfcd-modal-feedback').hide().empty();

      this.$modalOverlay.addClass('is-active').attr('aria-hidden', 'false');
      $('body').addClass('wfcd-modal-opened');
    },

    closeModal: function () {
      this.$modalOverlay.removeClass('is-active').attr('aria-hidden', 'true');
      $('body').removeClass('wfcd-modal-opened');
    },

    updateItemQty: function (cartItemKey, qty) {
      const self = this;
      $('#wfcd-drawer-content').css('opacity', '0.6');

      $.ajax({
        url: wfcd_params.ajax_url,
        type: 'POST',
        data: {
          action: 'wfcd_update_item_qty',
          nonce: wfcd_params.nonce,
          cart_item_key: cartItemKey,
          quantity: qty,
        },
        success: function (res) {
          if (res.success && res.data.fragments) {
            self.replaceFragments(res.data.fragments);
            $(document.body).trigger('wc_fragments_refreshed');
          }
        },
        complete: function () {
          $('#wfcd-drawer-content').css('opacity', '1');
        },
      });
    },

    removeCartItem: function (cartItemKey) {
      const self = this;
      const $item = $(`.wfcd-cart-item[data-key="${cartItemKey}"]`);
      $item.css('opacity', '0.4');

      $.ajax({
        url: wfcd_params.ajax_url,
        type: 'POST',
        data: {
          action: 'wfcd_remove_cart_item',
          nonce: wfcd_params.nonce,
          cart_item_key: cartItemKey,
        },
        success: function (res) {
          if (res.success && res.data.fragments) {
            self.replaceFragments(res.data.fragments);
            $(document.body).trigger('wc_fragments_refreshed');
          }
        },
      });
    },

    replaceFragments: function (fragments) {
      $.each(fragments, function (key, value) {
        $(key).replaceWith(value);
      });
    },

    submitQuickOrder: function ($form) {
      const self = this;
      const $submitBtn = $('#wfcd-submit-btn');
      const $spinner = $submitBtn.find('.wfcd-btn__spinner');
      const $btnText = $submitBtn.find('.wfcd-btn__text');
      const $feedback = $('#wfcd-modal-feedback');

      $submitBtn.prop('disabled', true);
      $spinner.show();
      $btnText.text(wfcd_params.i18n.submitting);
      $feedback.hide().removeClass('is-error is-success').empty();

      const formData = $form.serializeArray();
      formData.push({ name: 'action', value: 'wfcd_submit_quick_order' });
      formData.push({ name: 'nonce', value: wfcd_params.nonce });

      $.ajax({
        url: wfcd_params.ajax_url,
        type: 'POST',
        data: $.param(formData),
        success: function (res) {
          if (res.success) {
            $feedback
              .addClass('is-success')
              .text(res.data.message || wfcd_params.i18n.order_success)
              .show();

            $form.find('input[type="text"], input[type="tel"], input[type="email"], textarea').val('');

            setTimeout(function () {
              if (res.data.redirect_url) {
                window.location.href = res.data.redirect_url;
              } else {
                self.closeModal();
              }
            }, 1200);
          } else {
            $feedback
              .addClass('is-error')
              .text(res.data.message || wfcd_params.i18n.error_general)
              .show();
          }
        },
        error: function () {
          $feedback
            .addClass('is-error')
            .text(wfcd_params.i18n.error_general)
            .show();
        },
        complete: function () {
          $submitBtn.prop('disabled', false);
          $spinner.hide();
          $btnText.text('Подтвердить заказ');
        },
      });
    },
  };

  $(document).ready(function () {
    WFCD.init();
  });
})(jQuery);
