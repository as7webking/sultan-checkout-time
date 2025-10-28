<?php
/*
Plugin Name: Sultan Checkout Time
Description: Add pickup time selection to WooCommerce checkout with admin settings.
Version: 1.0
Author: Ahmed Sultanline
Author URI: https://ahmedsultanline.com
Text Domain: sultan-checkout-time
Domain Path: /languages
*/

if (!defined('ABSPATH'))
    exit;

// Different languages
add_action('plugins_loaded', function () {
    load_plugin_textdomain('sultan-checkout-time', false, dirname(plugin_basename(__FILE__)) . '/languages/');
});

// === Enqueue CSS on frontend ===
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style(
        'sultan-checkout-time-style',
        plugin_dir_url(__FILE__) . 'assets/style.css',
        [],
        '1.0'
    );
});

// Add settings link on plugin page
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
    $settings_link = '<a href="options-general.php?page=sultan-pickup-time">' . __('Settings', 'notifier-wptg') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
});

// === 1. Add admin menu page ===
add_action('admin_menu', function () {
    add_options_page(
        __('Pickup Time Settings', 'sultan-checkout-time'),
        __('Pickup Time', 'sultan-checkout-time'),
        'manage_options',
        'sultan-pickup-time',
        'sultan_pickup_settings_page'
    );
});

// === 2. Register settings ===
add_action('admin_init', function () {

    register_setting('sultan_pickup_settings', 'sultan_pickup_start_hour', ['type' => 'integer']);
    register_setting('sultan_pickup_settings', 'sultan_pickup_end_hour', ['type' => 'integer']);
    register_setting('sultan_pickup_settings', 'sultan_pickup_days', ['type' => 'array']);
    // === Additional settings: enable/disable orders ===
    register_setting('sultan_pickup_settings', 'sultan_pickup_disable_orders', ['type' => 'boolean']);
    register_setting('sultan_pickup_settings', 'sultan_pickup_order_start', ['type' => 'string']);
    register_setting('sultan_pickup_settings', 'sultan_pickup_order_end', ['type' => 'string']);
    register_setting('sultan_pickup_settings', 'sultan_pickup_interval', ['type' => 'integer']);

    // Switch: Disable Orders
    add_settings_field(
        'sultan_pickup_disable_orders',
        __('Disable Orders', 'sultan-checkout-time'),
        function () {
            $value = get_option('sultan_pickup_disable_orders', false);
            echo '<label><input type="checkbox" name="sultan_pickup_disable_orders" value="1" ' . checked(1, $value, false) . '> ';
            echo __('Temporarily disable checkout', 'sultan-checkout-time') . '</label>';
        },
        'sultan_pickup_settings',
        'sultan_pickup_section'
    );

    // Optional: specific checkout open/close time
    add_settings_field(
        'sultan_pickup_order_start',
        __('Orders Open From (HH:MM)', 'sultan-checkout-time'),
        function () {
            $value = get_option('sultan_pickup_order_start', '');
            echo '<input type="time" name="sultan_pickup_order_start" value="' . esc_attr($value) . '" />';
        },
        'sultan_pickup_settings',
        'sultan_pickup_section'
    );

    add_settings_field(
        'sultan_pickup_order_end',
        __('Orders Close At (HH:MM)', 'sultan-checkout-time'),
        function () {
            $value = get_option('sultan_pickup_order_end', '');
            echo '<input type="time" name="sultan_pickup_order_end" value="' . esc_attr($value) . '" />';
        },
        'sultan_pickup_settings',
        'sultan_pickup_section'
    );


    add_settings_section('sultan_pickup_section', '', '__return_false', 'sultan_pickup_settings');

    // Start hour
    add_settings_field(
        'sultan_pickup_start_hour',
        __('Pickup Start Hour (24h)', 'sultan-checkout-time'),
        function () {
            $value = get_option('sultan_pickup_start_hour', 16);
            echo '<input type="number" min="0" max="23" name="sultan_pickup_start_hour" value="' . esc_attr($value) . '" />';
        },
        'sultan_pickup_settings',
        'sultan_pickup_section'
    );

    // End hour
    add_settings_field(
        'sultan_pickup_end_hour',
        __('Pickup End Hour (24h)', 'sultan-checkout-time'),
        function () {
            $value = get_option('sultan_pickup_end_hour', 21);
            echo '<input type="number" min="0" max="23" name="sultan_pickup_end_hour" value="' . esc_attr($value) . '" />';
        },
        'sultan_pickup_settings',
        'sultan_pickup_section'
    );

    // Working days
    add_settings_field(
        'sultan_pickup_days',
        __('Working Days', 'sultan-checkout-time'),
        function () {
            $saved = (array) get_option('sultan_pickup_days', ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun']);
            $days = [
                'mon' => 'Monday',
                'tue' => 'Tuesday',
                'wed' => 'Wednesday',
                'thu' => 'Thursday',
                'fri' => 'Friday',
                'sat' => 'Saturday',
                'sun' => 'Sunday'
            ];
            foreach ($days as $key => $label) {
                $checked = in_array($key, $saved) ? 'checked' : '';
                echo '<label style="margin-right:10px;"><input type="checkbox" name="sultan_pickup_days[]" value="' . $key . '" ' . $checked . '> ' . $label . '</label>';
            }
        },
        'sultan_pickup_settings',
        'sultan_pickup_section'
    );

    // Interval between time slots (minutes)
    add_settings_field(
        'sultan_pickup_interval',
        __('Time Slot Interval (minutes)', 'sultan-checkout-time'),
        function () {
            $value = get_option('sultan_pickup_interval', 5);
            echo '<input type="number" min="1" step="1" name="sultan_pickup_interval" value="' . esc_attr($value) . '" />';
        },
        'sultan_pickup_settings',
        'sultan_pickup_section'
    );

});

// === 3. Render settings page ===
function sultan_pickup_settings_page()
{ ?>
    <div class="wrap">
        <h1><?php esc_html_e('Pickup Time Settings', 'sultan-checkout-time'); ?></h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('sultan_pickup_settings');
            do_settings_sections('sultan_pickup_settings');
            submit_button();
            ?>
        </form>
    </div>
<?php }

// === 4. Add pickup time select to checkout ===
add_filter('woocommerce_checkout_fields', function ($fields) {
    $options = sultan_get_pickup_time_options();

    $fields['billing']['billing_time_'] = [
        'type' => 'select',
        'label' => __('Pickup Time', 'sultan-checkout-time'),
        'required' => true,
        'options' => array_merge(
            ['' => __('— Select time —', 'sultan-checkout-time')],
            sultan_get_pickup_time_options()
        ),
        'priority' => 120,
        'class' => ['form-row-wide', 'address-field'], // address-field важно!
        'input_class' => ['state_select'], // заставляет Woo применить select2
        'custom_attributes' => [
            'data-placeholder' => __('— Select time —', 'sultan-checkout-time'),
            'autocomplete' => 'off'
        ]
    ];

    return $fields;
});

// === 5. Generate available time slots ===
function sultan_get_pickup_time_options()
{
    $options = [];
    $current_time = current_time('timestamp');
    $cutoff_time = $current_time + 30 * 60;

    $start_hour = (int) get_option('sultan_pickup_start_hour', 16);
    $end_hour = (int) get_option('sultan_pickup_end_hour', 21);
    $interval = (int) get_option('sultan_pickup_interval', 5);
    $days = (array) get_option('sultan_pickup_days', ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun']);

    $day_key = strtolower(date('D', $current_time)); // e.g. "mon"
    $today = strtotime(date('Y-m-d', $current_time));

    $start_today = $today + $start_hour * 3600;
    $end_today = $today + $end_hour * 3600;

    // If closed today or after hours — switch to tomorrow
    if (!in_array($day_key, $days) || $current_time > $end_today) {
        $pickup_date = strtotime(date('Y-m-d', strtotime('+1 day', $current_time)));
        $day_key = strtolower(date('D', $pickup_date));
        if (!in_array($day_key, $days))
            return ['no_slots' => __('Closed today and tomorrow', 'sultan-checkout-time')];
        $start_time = $pickup_date + $start_hour * 3600;
        $end_time = $pickup_date + $end_hour * 3600;
    } else {
        $start_time = $start_today;
        $end_time = $end_today;
    }

    $interval = (int) get_option('sultan_pickup_interval', 5);
    // Generate slots based on admin interval
    for ($time = $start_time; $time <= $end_time; $time += $interval * 60) {
        if ($time >= $cutoff_time) {
            $formatted = date('H:i', $time);
            $options[$formatted] = $formatted;
        }
    }

    if (empty($options)) {
        $options['no_slots'] = __('No pickup slots available today — please choose tomorrow', 'sultan-checkout-time');
    }

    return $options;
}

// === 6. Validate pickup time ===
add_action('woocommerce_checkout_process', function () {
    if (isset($_POST['billing_time_']) && $_POST['billing_time_'] !== 'no_slots') {
        $selected = sanitize_text_field($_POST['billing_time_']);
        $now = current_time('timestamp');
        $start_hour = (int) get_option('sultan_pickup_start_hour', 16);
        $end_hour = (int) get_option('sultan_pickup_end_hour', 21);

        $slot_today = strtotime(date('Y-m-d', $now) . ' ' . $selected);
        $slot_tomorrow = strtotime(date('Y-m-d', strtotime('+1 day', $now)) . ' ' . $selected);

        $valid = false;
        if ($slot_today >= $now + 30 * 60 && (int) date('H', $slot_today) >= $start_hour && (int) date('H', $slot_today) <= $end_hour) {
            $valid = true;
        } elseif ($slot_tomorrow >= $now + 30 * 60 && (int) date('H', $slot_tomorrow) >= $start_hour && (int) date('H', $slot_tomorrow) <= $end_hour) {
            $valid = true;
        }

        if (!$valid) {
            wc_add_notice(__('The selected pickup time is invalid. Please choose another slot.', 'sultan-checkout-time'), 'error');
        }
    } elseif (isset($_POST['billing_time_']) && $_POST['billing_time_'] === 'no_slots') {
        wc_add_notice(__('No pickup slots are available. Please try again tomorrow.', 'sultan-checkout-time'), 'error');
    }
});

// === Disable checkout if "disable orders" is checked or outside working hours ===
add_action('woocommerce_checkout_process', function () {
    // 1) Manual switch
    if (get_option('sultan_pickup_disable_orders')) {
        wc_add_notice(__('Orders are temporarily disabled. Please try again later.', 'sultan-checkout-time'), 'error');
        return;
    }

    // 2) Time-based control
    $start = get_option('sultan_pickup_order_start', '');
    $end = get_option('sultan_pickup_order_end', '');

    if ($start && $end) {
        $now = current_time('H:i');

        if ($now < $start || $now > $end) {
            wc_add_notice(__('We are currently closed. Please place your order during working hours.', 'sultan-checkout-time'), 'error');
        }
    }
});

// Adding JS script

add_action('wp_footer', function () {
    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const select = document.querySelector('select[name="billing_time_"]');
            const orderButton = document.querySelector('#place_order');
            if (!orderButton) return;

            <?php
            $disable_orders = get_option('sultan_pickup_disable_orders');
            $order_start = get_option('sultan_pickup_order_start', '');
            $order_end = get_option('sultan_pickup_order_end', '');
            $now = current_time('H:i');
            $closed = false;

            if ($disable_orders) {
                $closed = true;
            } elseif ($order_start && $order_end && ($now < $order_start || $now > $order_end)) {
                $closed = true;
            }

            if ($closed) {
                echo "if (select) select.disabled = true;";
                echo "orderButton.disabled = true;";
            }
            ?>

            const checkoutForm = document.querySelector('form.checkout');
            if (checkoutForm) {
                checkoutForm.addEventListener('input', () => {
                    const allRequired = checkoutForm.querySelectorAll('[required]');
                    let allFilled = true;
                    allRequired.forEach(field => {
                        if (!field.value.trim()) allFilled = false;
                    });
                    orderButton.disabled = !allFilled;
                });
            }

            const field = document.querySelector('select[name="billing_time_"]');
            if (field && window.jQuery && jQuery.fn.select2) {
                jQuery(field).select2({
                    placeholder: field.dataset.placeholder || '— Select time —',
                    minimumResultsForSearch: Infinity, // 👈 Отключает строку поиска
                    width: '100%'
                });
            }
        });
    </script>
    <?php
});