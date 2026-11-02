<?php
/**
 * Очистка данных при удалении плагина
 *
 * @package WooFastCheckoutDrawer
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Удаление сохранённых опций плагина
delete_option( 'wfcd_settings' );
