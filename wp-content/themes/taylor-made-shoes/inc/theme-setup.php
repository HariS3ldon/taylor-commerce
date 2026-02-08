<?php
/**
 * Theme setup and generic hooks.
 *
 * @package TaylorMadeShoes
 */

if (! defined('ABSPATH')) {
    exit;
}

add_action('after_setup_theme', 'tms_theme_setup');
function tms_theme_setup(): void
{
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('woocommerce');

    register_nav_menus(
        [
            'primary' => __('Menu principale', 'taylor-made-shoes'),
        ]
    );
}

add_action('wp_enqueue_scripts', 'tms_enqueue_assets');
function tms_enqueue_assets(): void
{
    wp_enqueue_style('tms-style', get_stylesheet_uri(), [], TMS_THEME_VERSION);
}

/**
 * Rende obbligatoria la registrazione per effettuare acquisti.
 */
add_filter('woocommerce_checkout_registration_required', '__return_true');

/**
 * Disabilita il checkout per utenti non autenticati.
 */
add_action('template_redirect', 'tms_block_guest_checkout');
function tms_block_guest_checkout(): void
{
    if (! function_exists('is_checkout')) {
        return;
    }

    if (is_checkout() && ! is_user_logged_in() && ! is_wc_endpoint_url('order-received')) {
        wc_add_notice(__('Per acquistare devi essere registrato ed effettuare l\'accesso.', 'taylor-made-shoes'), 'error');
        wp_safe_redirect(wc_get_page_permalink('myaccount'));
        exit;
    }
}

/**
 * Mostra una sezione in area account con prodotti acquistati (non raggruppati per ordine).
 */
add_action('init', 'tms_register_account_endpoint');
function tms_register_account_endpoint(): void
{
    add_rewrite_endpoint('prodotti-acquistati', EP_ROOT | EP_PAGES);
}

add_filter('query_vars', 'tms_add_account_query_var');
function tms_add_account_query_var(array $vars): array
{
    $vars[] = 'prodotti-acquistati';

    return $vars;
}

add_filter('woocommerce_account_menu_items', 'tms_add_account_menu_item');
function tms_add_account_menu_item(array $items): array
{
    $logout = $items['customer-logout'] ?? null;
    unset($items['customer-logout']);

    $items['prodotti-acquistati'] = __('Prodotti acquistati', 'taylor-made-shoes');

    if ($logout) {
        $items['customer-logout'] = $logout;
    }

    return $items;
}

add_action('woocommerce_account_prodotti-acquistati_endpoint', 'tms_render_purchased_products_endpoint');
function tms_render_purchased_products_endpoint(): void
{
    $user_id = get_current_user_id();

    if (! $user_id) {
        echo '<p>' . esc_html__('Effettua il login per vedere i tuoi prodotti.', 'taylor-made-shoes') . '</p>';

        return;
    }

    $orders = wc_get_orders(
        [
            'customer_id' => $user_id,
            'limit'       => -1,
            'orderby'     => 'date',
            'order'       => 'DESC',
            'status'      => array_keys(wc_get_order_statuses()),
        ]
    );

    echo '<div class="tms-account-products">';
    echo '<h3>' . esc_html__('Prodotti ordinati', 'taylor-made-shoes') . '</h3>';

    if (empty($orders)) {
        echo '<p>' . esc_html__('Non hai ancora acquistato prodotti.', 'taylor-made-shoes') . '</p>';
        echo '</div>';

        return;
    }

    echo '<ul>';

    foreach ($orders as $order) {
        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            $status  = wc_get_order_item_meta($item_id, '_tms_item_status', true);
            $status  = $status ?: 'in_attesa_presa_in_carico';

            echo '<li>';
            echo '<strong>' . esc_html($item->get_name()) . '</strong>';

            if ($product) {
                echo ' - <a href="' . esc_url($product->get_permalink()) . '">' . esc_html__('Vai al prodotto', 'taylor-made-shoes') . '</a>';
            }

            echo '<br><small>' . esc_html(sprintf(__('Ordine #%d del %s', 'taylor-made-shoes'), $order->get_id(), wc_format_datetime($order->get_date_created()))) . '</small>';
            echo '<br><span class="tms-status-pill">' . esc_html(tms_get_item_status_label($status)) . '</span>';
            echo '</li>';
        }
    }

    echo '</ul>';
    echo '</div>';
}
