<?php
/**
 * Per-product workflow status.
 *
 * @package TaylorMadeShoes
 */

if (! defined('ABSPATH')) {
    exit;
}

function tms_get_item_statuses(): array
{
    return [
        'in_attesa_presa_in_carico' => __('In attesa presa in carico', 'taylor-made-shoes'),
        'analisi_misure'            => __('Analisi misure piede', 'taylor-made-shoes'),
        'prototipo_in_lavorazione'  => __('Prototipo in lavorazione', 'taylor-made-shoes'),
        'in_prova_cliente'          => __('In prova cliente', 'taylor-made-shoes'),
        'finitura_finale'           => __('Finitura finale', 'taylor-made-shoes'),
        'pronto_ritiro_spedizione'  => __('Pronto per ritiro/spedizione', 'taylor-made-shoes'),
        'consegnato'                => __('Consegnato', 'taylor-made-shoes'),
    ];
}

function tms_get_item_status_label(string $status): string
{
    $statuses = tms_get_item_statuses();

    return $statuses[$status] ?? $statuses['in_attesa_presa_in_carico'];
}

add_action('woocommerce_checkout_create_order_line_item', 'tms_set_default_item_status', 20, 4);
function tms_set_default_item_status(WC_Order_Item_Product $item, string $cart_item_key, array $values, WC_Order $order): void
{
    $item->add_meta_data('_tms_item_status', 'in_attesa_presa_in_carico', true);
}

add_action('woocommerce_after_order_itemmeta', 'tms_render_admin_item_status_field', 10, 3);
function tms_render_admin_item_status_field(int $item_id, WC_Order_Item $item, WC_Product $product = null): void
{
    if (! is_admin()) {
        return;
    }

    if (! $item instanceof WC_Order_Item_Product) {
        return;
    }

    $statuses = tms_get_item_statuses();
    $current  = wc_get_order_item_meta($item_id, '_tms_item_status', true) ?: 'in_attesa_presa_in_carico';

    echo '<div class="view"><small><strong>' . esc_html__('Stato lavorazione prodotto', 'taylor-made-shoes') . ':</strong> ' . esc_html(tms_get_item_status_label($current)) . '</small></div>';
    echo '<div class="edit" style="margin-top: 4px;">';
    echo '<select name="tms_item_status[' . esc_attr((string) $item_id) . ']">';

    foreach ($statuses as $key => $label) {
        echo '<option value="' . esc_attr($key) . '" ' . selected($current, $key, false) . '>' . esc_html($label) . '</option>';
    }

    echo '</select>';
    echo '</div>';
}

add_action('woocommerce_saved_order_items', 'tms_save_admin_item_statuses', 10, 2);
function tms_save_admin_item_statuses(int $order_id, array $items): void
{
    if (! isset($_POST['tms_item_status']) || ! is_array($_POST['tms_item_status'])) {
        return;
    }

    $statuses = tms_get_item_statuses();

    foreach ($_POST['tms_item_status'] as $item_id => $status) {
        $item_id = (int) $item_id;
        $status  = sanitize_text_field($status);

        if (! isset($statuses[$status])) {
            continue;
        }

        wc_update_order_item_meta($item_id, '_tms_item_status', $status);
    }
}
