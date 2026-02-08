<?php
/**
 * Theme bootstrap.
 *
 * @package TaylorMadeShoes
 */

if (! defined('ABSPATH')) {
    exit;
}

define('TMS_THEME_VERSION', '1.0.0');
define('TMS_THEME_PATH', get_template_directory());
define('TMS_THEME_URL', get_template_directory_uri());

require_once TMS_THEME_PATH . '/inc/theme-setup.php';
require_once TMS_THEME_PATH . '/inc/appointments.php';
require_once TMS_THEME_PATH . '/inc/product-status.php';
