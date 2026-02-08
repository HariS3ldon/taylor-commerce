<?php
/**
 * Appointments workflow at checkout.
 *
 * @package TaylorMadeShoes
 */

if (! defined('ABSPATH')) {
    exit;
}

add_action('init', 'tms_register_appointment_post_type');
function tms_register_appointment_post_type(): void
{
    register_post_type(
        'tms_appointment',
        [
            'label'               => __('Appuntamenti prova scarpe', 'taylor-made-shoes'),
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => 'woocommerce',
            'supports'            => ['title'],
            'capability_type'     => 'post',
            'map_meta_cap'        => true,
            'menu_icon'           => 'dashicons-calendar-alt',
            'labels'              => [
                'name'          => __('Appuntamenti', 'taylor-made-shoes'),
                'singular_name' => __('Appuntamento', 'taylor-made-shoes'),
                'add_new_item'  => __('Nuovo appuntamento', 'taylor-made-shoes'),
                'edit_item'     => __('Modifica appuntamento', 'taylor-made-shoes'),
            ],
        ]
    );
}

function tms_get_daily_slots(): array
{
    $slots = [];

    for ($hour = 9; $hour <= 18; $hour++) {
        $slots[] = sprintf('%02d:00', $hour);
    }

    return $slots;
}

function tms_get_booked_slots(string $date): array
{
    $appointments = get_posts(
        [
            'post_type'      => 'tms_appointment',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'   => '_tms_appointment_date',
                    'value' => $date,
                ],
            ],
        ]
    );

    if (empty($appointments)) {
        return [];
    }

    $slots = [];
    foreach ($appointments as $appointment_id) {
        $slot = get_post_meta($appointment_id, '_tms_appointment_slot', true);
        if ($slot) {
            $slots[] = $slot;
        }
    }

    return array_unique($slots);
}

function tms_get_available_slots(string $date): array
{
    $all_slots    = tms_get_daily_slots();
    $booked_slots = tms_get_booked_slots($date);

    return array_values(array_diff($all_slots, $booked_slots));
}

add_action('wp_ajax_tms_get_slots', 'tms_ajax_get_slots');
add_action('wp_ajax_nopriv_tms_get_slots', 'tms_ajax_get_slots');
function tms_ajax_get_slots(): void
{
    $date = sanitize_text_field($_GET['date'] ?? '');

    if (! $date || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        wp_send_json_error(['message' => __('Data non valida.', 'taylor-made-shoes')], 400);
    }

    wp_send_json_success(
        [
            'slots' => tms_get_available_slots($date),
        ]
    );
}

add_action('woocommerce_after_order_notes', 'tms_add_checkout_appointment_fields');
function tms_add_checkout_appointment_fields($checkout): void
{
    echo '<div id="tms_appointment_checkout" class="tms-appointment-summary"><h3>' . esc_html__('Appuntamento prima prova', 'taylor-made-shoes') . '</h3>';

    woocommerce_form_field(
        'tms_appointment_date',
        [
            'type'        => 'date',
            'class'       => ['form-row-wide'],
            'label'       => __('Data appuntamento', 'taylor-made-shoes'),
            'required'    => true,
            'input_class' => ['tms-appointment-date'],
            'custom_attributes' => [
                'min' => gmdate('Y-m-d'),
            ],
        ],
        $checkout->get_value('tms_appointment_date')
    );

    woocommerce_form_field(
        'tms_appointment_slot',
        [
            'type'     => 'select',
            'class'    => ['form-row-wide'],
            'label'    => __('Orario appuntamento', 'taylor-made-shoes'),
            'required' => true,
            'options'  => [
                '' => __('Seleziona prima una data', 'taylor-made-shoes'),
            ],
        ],
        $checkout->get_value('tms_appointment_slot')
    );

    echo '<p class="tms-slot-error" style="display:none;"></p>';
    echo '</div>';
}

add_action('wp_enqueue_scripts', 'tms_enqueue_checkout_script');
function tms_enqueue_checkout_script(): void
{
    if (! function_exists('is_checkout') || ! is_checkout()) {
        return;
    }

    wp_register_script('tms-checkout-appointments', '', ['jquery'], TMS_THEME_VERSION, true);
    wp_enqueue_script('tms-checkout-appointments');

    wp_localize_script(
        'tms-checkout-appointments',
        'tmsAppointments',
        [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'i18n'    => [
                'pickSlot'   => __('Seleziona uno slot orario', 'taylor-made-shoes'),
                'noSlots'    => __('Nessuno slot disponibile per questa data.', 'taylor-made-shoes'),
                'loadError'  => __('Errore nel caricamento degli slot. Riprova.', 'taylor-made-shoes'),
            ],
        ]
    );

    wp_add_inline_script(
        'tms-checkout-appointments',
        "jQuery(function($){
            var dateField = $('#tms_appointment_date');
            var slotField = $('#tms_appointment_slot');
            var errorBox = $('.tms-slot-error');

            function setOptions(slots) {
                slotField.empty();
                slotField.append($('<option>').val('').text(tmsAppointments.i18n.pickSlot));

                if (!slots.length) {
                    slotField.append($('<option>').val('').text(tmsAppointments.i18n.noSlots));
                    return;
                }

                slots.forEach(function(slot){
                    slotField.append($('<option>').val(slot).text(slot));
                });
            }

            dateField.on('change', function(){
                var date = $(this).val();
                errorBox.hide().text('');
                setOptions([]);

                if (!date) {
                    return;
                }

                $.getJSON(tmsAppointments.ajaxUrl, { action: 'tms_get_slots', date: date })
                    .done(function(res){
                        if (!res.success) {
                            errorBox.text((res.data && res.data.message) || tmsAppointments.i18n.loadError).show();
                            return;
                        }
                        setOptions(res.data.slots || []);
                    })
                    .fail(function(){
                        errorBox.text(tmsAppointments.i18n.loadError).show();
                    });
            });
        });"
    );
}

add_action('woocommerce_checkout_process', 'tms_validate_appointment_slot');
function tms_validate_appointment_slot(): void
{
    $date = sanitize_text_field($_POST['tms_appointment_date'] ?? '');
    $slot = sanitize_text_field($_POST['tms_appointment_slot'] ?? '');

    if (! $date) {
        wc_add_notice(__('Seleziona una data per l\'appuntamento.', 'taylor-made-shoes'), 'error');

        return;
    }

    if (! $slot) {
        wc_add_notice(__('Seleziona un orario per l\'appuntamento.', 'taylor-made-shoes'), 'error');

        return;
    }

    $available_slots = tms_get_available_slots($date);
    if (! in_array($slot, $available_slots, true)) {
        wc_add_notice(__('Lo slot selezionato non è più disponibile. Scegli un altro orario.', 'taylor-made-shoes'), 'error');
    }
}

add_action('woocommerce_checkout_create_order', 'tms_save_appointment_in_order', 20, 2);
function tms_save_appointment_in_order(WC_Order $order, array $data): void
{
    $date = sanitize_text_field($_POST['tms_appointment_date'] ?? '');
    $slot = sanitize_text_field($_POST['tms_appointment_slot'] ?? '');

    if (! $date || ! $slot) {
        return;
    }

    $order->update_meta_data('_tms_appointment_date', $date);
    $order->update_meta_data('_tms_appointment_slot', $slot);
}

add_action('woocommerce_checkout_order_processed', 'tms_create_appointment_post', 10, 3);
function tms_create_appointment_post(int $order_id, array $posted_data, WC_Order $order): void
{
    $date = $order->get_meta('_tms_appointment_date');
    $slot = $order->get_meta('_tms_appointment_slot');

    if (! $date || ! $slot) {
        return;
    }

    $appointment_id = wp_insert_post(
        [
            'post_type'   => 'tms_appointment',
            'post_status' => 'publish',
            'post_title'  => sprintf(__('Appuntamento ordine #%d', 'taylor-made-shoes'), $order_id),
        ]
    );

    if (is_wp_error($appointment_id)) {
        return;
    }

    update_post_meta($appointment_id, '_tms_order_id', $order_id);
    update_post_meta($appointment_id, '_tms_user_id', $order->get_user_id());
    update_post_meta($appointment_id, '_tms_appointment_date', $date);
    update_post_meta($appointment_id, '_tms_appointment_slot', $slot);
}

add_action('woocommerce_admin_order_data_after_billing_address', 'tms_show_appointment_on_admin_order');
function tms_show_appointment_on_admin_order(WC_Order $order): void
{
    $date = $order->get_meta('_tms_appointment_date');
    $slot = $order->get_meta('_tms_appointment_slot');

    if (! $date || ! $slot) {
        return;
    }

    echo '<p><strong>' . esc_html__('Appuntamento prova:', 'taylor-made-shoes') . '</strong> ' . esc_html($date . ' ' . $slot) . '</p>';
}

add_filter('manage_tms_appointment_posts_columns', 'tms_appointment_columns');
function tms_appointment_columns(array $columns): array
{
    $columns['appointment_date'] = __('Data', 'taylor-made-shoes');
    $columns['appointment_slot'] = __('Orario', 'taylor-made-shoes');
    $columns['appointment_order'] = __('Ordine', 'taylor-made-shoes');

    return $columns;
}

add_action('manage_tms_appointment_posts_custom_column', 'tms_appointment_custom_column', 10, 2);
function tms_appointment_custom_column(string $column, int $post_id): void
{
    if ('appointment_date' === $column) {
        echo esc_html(get_post_meta($post_id, '_tms_appointment_date', true));
    }

    if ('appointment_slot' === $column) {
        echo esc_html(get_post_meta($post_id, '_tms_appointment_slot', true));
    }

    if ('appointment_order' === $column) {
        $order_id = (int) get_post_meta($post_id, '_tms_order_id', true);
        if ($order_id > 0) {
            echo '<a href="' . esc_url(admin_url('post.php?post=' . $order_id . '&action=edit')) . '">#' . esc_html((string) $order_id) . '</a>';
        }
    }
}
