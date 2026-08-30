<?php
/*
Plugin Name: Sultan Checkout Time
Description: Add pickup time selection to WooCommerce checkout with admin settings.
Version: 1.3.0
Author: Ahmed Sultanline
Author URI: https://ahmedsultanline.com
Text Domain: sultan-checkout-time
Domain Path: /languages
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load translations.
add_action( 'plugins_loaded', function () {
	load_plugin_textdomain( 'sultan-checkout-time', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
} );

// Enqueue CSS on frontend.
add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style(
		'sultan-checkout-time-style',
		plugin_dir_url( __FILE__ ) . 'style.css',
		[],
		'1.3.0'
	);

	wp_enqueue_script(
		'sultan-checkout-time-frontend',
		plugin_dir_url( __FILE__ ) . 'assets/frontend.js',
		[],
		'1.3.0',
		true
	);
} );

// Settings link on plugins page.
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function ( $links ) {
	$settings_link = '<a href="options-general.php?page=sultan-pickup-time">' . esc_html__( 'Settings', 'sultan-checkout-time' ) . '</a>';
	array_unshift( $links, $settings_link );
	return $links;
} );

// Admin menu.
add_action( 'admin_menu', function () {
	add_options_page(
		__( 'Pickup Time Settings', 'sultan-checkout-time' ),
		__( 'Pickup Time', 'sultan-checkout-time' ),
		'manage_options',
		'sultan-pickup-time',
		'sultan_pickup_settings_page'
	);
} );

// Register settings.
add_action( 'admin_init', function () {
	register_setting( 'sultan_pickup_settings', 'sultan_pickup_start_hour', [ 'type' => 'integer' ] );
	register_setting( 'sultan_pickup_settings', 'sultan_pickup_end_hour', [ 'type' => 'integer' ] );
	register_setting( 'sultan_pickup_settings', 'sultan_pickup_days', [ 'type' => 'array' ] );
	register_setting( 'sultan_pickup_settings', 'sultan_pickup_disable_orders', [ 'type' => 'boolean' ] );
	register_setting( 'sultan_pickup_settings', 'sultan_pickup_order_start', [ 'type' => 'string' ] );
	register_setting( 'sultan_pickup_settings', 'sultan_pickup_order_end', [ 'type' => 'string' ] );
	register_setting( 'sultan_pickup_settings', 'sultan_pickup_interval', [ 'type' => 'integer' ] );
	register_setting(
		'sultan_pickup_settings',
		'sultan_distance_shipping_settings',
		[
			'type'              => 'array',
			'sanitize_callback' => 'sultan_sanitize_distance_shipping_settings',
			'default'           => [],
		]
	);

	add_settings_section( 'sultan_pickup_section', '', '__return_false', 'sultan_pickup_settings' );

	add_settings_field(
		'sultan_pickup_disable_orders',
		__( 'Disable Orders', 'sultan-checkout-time' ),
		function () {
			$value = get_option( 'sultan_pickup_disable_orders', false );
			echo '<label><input type="checkbox" name="sultan_pickup_disable_orders" value="1" ' . checked( 1, $value, false ) . '> ';
			echo esc_html__( 'Temporarily disable checkout', 'sultan-checkout-time' ) . '</label>';
		},
		'sultan_pickup_settings',
		'sultan_pickup_section'
	);

	add_settings_field(
		'sultan_pickup_order_start',
		__( 'Orders Open From (HH:MM)', 'sultan-checkout-time' ),
		function () {
			$value = get_option( 'sultan_pickup_order_start', '' );
			echo '<input type="time" name="sultan_pickup_order_start" value="' . esc_attr( $value ) . '" />';
		},
		'sultan_pickup_settings',
		'sultan_pickup_section'
	);

	add_settings_field(
		'sultan_pickup_order_end',
		__( 'Orders Close At (HH:MM)', 'sultan-checkout-time' ),
		function () {
			$value = get_option( 'sultan_pickup_order_end', '' );
			echo '<input type="time" name="sultan_pickup_order_end" value="' . esc_attr( $value ) . '" />';
		},
		'sultan_pickup_settings',
		'sultan_pickup_section'
	);

	add_settings_field(
		'sultan_pickup_start_hour',
		__( 'Pickup Start Hour (24h)', 'sultan-checkout-time' ),
		function () {
			$value = get_option( 'sultan_pickup_start_hour', 16 );
			echo '<input type="number" min="0" max="23" name="sultan_pickup_start_hour" value="' . esc_attr( $value ) . '" />';
		},
		'sultan_pickup_settings',
		'sultan_pickup_section'
	);

	add_settings_field(
		'sultan_pickup_end_hour',
		__( 'Pickup End Hour (24h)', 'sultan-checkout-time' ),
		function () {
			$value = get_option( 'sultan_pickup_end_hour', 21 );
			echo '<input type="number" min="0" max="23" name="sultan_pickup_end_hour" value="' . esc_attr( $value ) . '" />';
		},
		'sultan_pickup_settings',
		'sultan_pickup_section'
	);

	add_settings_field(
		'sultan_pickup_days',
		__( 'Working Days', 'sultan-checkout-time' ),
		function () {
			$saved = (array) get_option( 'sultan_pickup_days', [ 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun' ] );
			$days  = [
				'mon' => __( 'Monday', 'sultan-checkout-time' ),
				'tue' => __( 'Tuesday', 'sultan-checkout-time' ),
				'wed' => __( 'Wednesday', 'sultan-checkout-time' ),
				'thu' => __( 'Thursday', 'sultan-checkout-time' ),
				'fri' => __( 'Friday', 'sultan-checkout-time' ),
				'sat' => __( 'Saturday', 'sultan-checkout-time' ),
				'sun' => __( 'Sunday', 'sultan-checkout-time' ),
			];

			foreach ( $days as $key => $label ) {
				$checked = in_array( $key, $saved, true ) ? 'checked' : '';
				echo '<label style="margin-right:10px;"><input type="checkbox" name="sultan_pickup_days[]" value="' . esc_attr( $key ) . '" ' . $checked . '> ' . esc_html( $label ) . '</label>';
			}
		},
		'sultan_pickup_settings',
		'sultan_pickup_section'
	);

	add_settings_field(
		'sultan_pickup_interval',
		__( 'Time Slot Interval (minutes)', 'sultan-checkout-time' ),
		function () {
			$value = get_option( 'sultan_pickup_interval', 5 );
			echo '<input type="number" min="1" step="1" name="sultan_pickup_interval" value="' . esc_attr( $value ) . '" />';
		},
		'sultan_pickup_settings',
		'sultan_pickup_section'
	);

	add_settings_section(
		'sultan_distance_shipping_section',
		__( 'Distance Shipping and Address Autocomplete', 'sultan-checkout-time' ),
		function () {
			echo '<p>' . esc_html__( 'Configure Google API access, store location, closed days, delivery radius, fees, and caps.', 'sultan-checkout-time' ) . '</p>';
		},
		'sultan_pickup_settings'
	);

	add_settings_field(
		'sultan_google_maps_server_api_key',
		__( 'Google server API key', 'sultan-checkout-time' ),
		function () {
			$settings = sultan_get_distance_shipping_settings();
			echo '<input class="regular-text" type="password" autocomplete="new-password" name="sultan_distance_shipping_settings[server_api_key]" value="' . esc_attr( $settings['server_api_key'] ) . '" />';
			echo '<p class="description">' . esc_html__( 'Used on the server for Distance Matrix API requests.', 'sultan-checkout-time' ) . '</p>';
		},
		'sultan_pickup_settings',
		'sultan_distance_shipping_section'
	);

	add_settings_field(
		'sultan_google_maps_browser_api_key',
		__( 'Google browser API key', 'sultan-checkout-time' ),
		function () {
			$settings = sultan_get_distance_shipping_settings();
			echo '<input class="regular-text" type="password" autocomplete="new-password" name="sultan_distance_shipping_settings[browser_api_key]" value="' . esc_attr( $settings['browser_api_key'] ) . '" />';
			echo '<p class="description">' . esc_html__( 'Used in the checkout browser for Maps JavaScript API and Places API address suggestions.', 'sultan-checkout-time' ) . '</p>';
		},
		'sultan_pickup_settings',
		'sultan_distance_shipping_section'
	);

	add_settings_field(
		'sultan_store_address',
		__( 'Store address', 'sultan-checkout-time' ),
		function () {
			$settings = sultan_get_distance_shipping_settings();
			echo '<input class="regular-text" type="text" name="sultan_distance_shipping_settings[store_address]" value="' . esc_attr( $settings['store_address'] ) . '" placeholder="Street, number, postcode, city, country" />';
			echo '<p class="description">' . esc_html__( 'Enter the complete address used as the delivery origin.', 'sultan-checkout-time' ) . '</p>';
		},
		'sultan_pickup_settings',
		'sultan_distance_shipping_section'
	);

	add_settings_field(
		'sultan_store_weekly_schedule',
		__( 'Weekly store schedule', 'sultan-checkout-time' ),
		'sultan_render_weekly_schedule_field',
		'sultan_pickup_settings',
		'sultan_distance_shipping_section'
	);

	add_settings_field(
		'sultan_enable_local_pickup',
		__( 'Local pickup', 'sultan-checkout-time' ),
		function () {
			$settings = sultan_get_distance_shipping_settings();
			echo '<label><input type="checkbox" name="sultan_distance_shipping_settings[enable_pickup]" value="1" ' . checked( ! empty( $settings['enable_pickup'] ), true, false ) . '> ' . esc_html__( 'Offer free local pickup at checkout', 'sultan-checkout-time' ) . '</label>';
		},
		'sultan_pickup_settings',
		'sultan_distance_shipping_section'
	);

	add_settings_field(
		'sultan_local_pickup_label',
		__( 'Local pickup label', 'sultan-checkout-time' ),
		function () {
			$settings = sultan_get_distance_shipping_settings();
			echo '<input class="regular-text" type="text" name="sultan_distance_shipping_settings[pickup_label]" value="' . esc_attr( $settings['pickup_label'] ) . '" />';
		},
		'sultan_pickup_settings',
		'sultan_distance_shipping_section'
	);

	add_settings_field(
		'sultan_max_delivery_distance',
		__( 'Maximum delivery distance', 'sultan-checkout-time' ),
		function () {
			$settings = sultan_get_distance_shipping_settings();
			echo '<input type="number" min="0.1" step="0.1" name="sultan_distance_shipping_settings[max_distance]" value="' . esc_attr( $settings['max_distance'] ) . '" /> km';
		},
		'sultan_pickup_settings',
		'sultan_distance_shipping_section'
	);

	add_settings_field(
		'sultan_distance_shipping_tiers',
		__( 'Distance pricing tiers', 'sultan-checkout-time' ),
		'sultan_render_distance_tiers_field',
		'sultan_pickup_settings',
		'sultan_distance_shipping_section'
	);
} );

// Settings page.
function sultan_pickup_settings_page() {
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Pickup Time Settings', 'sultan-checkout-time' ); ?></h1>
		<form method="post" action="options.php">
			<?php
			settings_fields( 'sultan_pickup_settings' );
			do_settings_sections( 'sultan_pickup_settings' );
			submit_button();
			?>
		</form>
	</div>
	<?php
}

// Check if ordering is currently allowed.
function sultan_pickup_orders_are_closed() {
	if ( sultan_store_is_closed_today() ) {
		return true;
	}

	if ( get_option( 'sultan_pickup_disable_orders' ) ) {
		return true;
	}

	return false;
}

/**
 * Return the configured days on which checkout is closed.
 *
 * Use full English weekday names as returned by DateTime::format( 'l' ).
 *
 * @return string[]
 */
function sultan_get_store_closed_days() {
	$settings = sultan_get_distance_shipping_settings();
	$closed   = [];

	foreach ( $settings['weekly_schedule'] as $day => $schedule ) {
		if ( empty( $schedule['enabled'] ) ) {
			$closed[] = $day;
		}
	}

	return $closed;
}

/**
 * Determine whether the store is closed at the current WordPress-local time.
 */
function sultan_store_is_closed_today() {
	$settings = sultan_get_distance_shipping_settings();
	$now      = current_datetime();
	$day      = $now->format( 'l' );
	$schedule = $settings['weekly_schedule'][ $day ] ?? [];

	if ( empty( $schedule['enabled'] ) ) {
		return true;
	}

	$open_time  = $schedule['open_time'] ?? '';
	$close_time = $schedule['close_time'] ?? '';

	if ( '' === $open_time || '' === $close_time ) {
		return false;
	}

	$current_minutes = ( (int) $now->format( 'H' ) * 60 ) + (int) $now->format( 'i' );
	[ $open_hour, $open_minute ]   = array_map( 'intval', explode( ':', $open_time ) );
	[ $close_hour, $close_minute ] = array_map( 'intval', explode( ':', $close_time ) );
	$open_minutes  = ( $open_hour * 60 ) + $open_minute;
	$close_minutes = ( $close_hour * 60 ) + $close_minute;

	if ( $open_minutes < $close_minutes ) {
		return $current_minutes < $open_minutes || $current_minutes > $close_minutes;
	}

	if ( $open_minutes > $close_minutes ) {
		return ! ( $current_minutes >= $open_minutes || $current_minutes <= $close_minutes );
	}

	return false;
}

/**
 * Display the closed-day message on the cart page.
 */
add_action( 'woocommerce_before_cart', function () {
	if ( sultan_store_is_closed_today() ) {
		wc_print_notice(
			__( 'The store is closed today. Checkout is currently unavailable.', 'sultan-checkout-time' ),
			'error'
		);
	}
} );

// Build slots for one concrete day window.
function sultan_build_pickup_slots_for_window( $window_start, $window_end, $interval, $cutoff_time ) {
	$options = [];

	for ( $time = $window_start; $time <= $window_end; $time += $interval * 60 ) {
		if ( $time >= $cutoff_time ) {
			$formatted              = date_i18n( 'H:i', $time );
			$options[ $formatted ] = $formatted;
		}
	}

	return $options;
}

// Generate pickup time options.
function sultan_get_pickup_time_options() {
	$settings       = sultan_get_distance_shipping_settings();
	$timezone       = wp_timezone();
	$now            = current_datetime();
	$cutoff         = $now->modify( '+30 minutes' );
	$interval       = max( 1, (int) get_option( 'sultan_pickup_interval', 5 ) );
	$fallback_start = sprintf( '%02d:00', min( 23, max( 0, (int) get_option( 'sultan_pickup_start_hour', 16 ) ) ) );
	$fallback_end   = sprintf( '%02d:00', min( 23, max( 0, (int) get_option( 'sultan_pickup_end_hour', 21 ) ) ) );
	$options        = [];

	for ( $offset = 0; $offset < 7; $offset++ ) {
		$date     = $now->setTime( 0, 0 )->modify( '+' . $offset . ' days' );
		$day_name = $date->format( 'l' );
		$schedule = $settings['weekly_schedule'][ $day_name ] ?? [];

		if ( empty( $schedule['enabled'] ) ) {
			continue;
		}

		$open_time  = ! empty( $schedule['open_time'] ) ? $schedule['open_time'] : $fallback_start;
		$close_time = ! empty( $schedule['close_time'] ) ? $schedule['close_time'] : $fallback_end;
		$start      = new DateTimeImmutable( $date->format( 'Y-m-d' ) . ' ' . $open_time, $timezone );
		$end        = new DateTimeImmutable( $date->format( 'Y-m-d' ) . ' ' . $close_time, $timezone );

		if ( $end <= $start ) {
			$end = $end->modify( '+1 day' );
		}

		for ( $slot = $start; $slot <= $end; $slot = $slot->modify( '+' . $interval . ' minutes' ) ) {
			if ( $slot < $cutoff ) {
				continue;
			}

			$value             = $slot->format( 'Y-m-d H:i' );
			$options[ $value ] = wp_date( 'D, d.m. H:i', $slot->getTimestamp(), $timezone );
		}
	}

	return $options ?: [ 'no_slots' => __( 'No pickup slots available', 'sultan-checkout-time' ) ];
}

// Generate options for Checkout Block.
function sultan_get_pickup_time_options_for_blocks() {
	$options = [];

	foreach ( sultan_get_pickup_time_options() as $value => $label ) {
		$options[] = [
			'value' => $value,
			'label' => $label,
		];
	}

	return $options;
}

// Shared validator.
function sultan_pickup_time_is_valid( $selected ) {
	$selected = sanitize_text_field( $selected );

	if ( '' === $selected || 'no_slots' === $selected ) {
		return false;
	}

	$allowed = sultan_get_pickup_time_options();

	return isset( $allowed[ $selected ] );
}

function sultan_get_pickup_time_checkout_field() {
	return [
		'type'        => 'select',
		'label'       => __( 'Pickup Time', 'sultan-checkout-time' ),
		'required'    => true,
		'options'     => array_merge(
			[ '' => __( '— Select time —', 'sultan-checkout-time' ) ],
			sultan_get_pickup_time_options()
		),
		'priority'    => 120,
		'class'       => [ 'form-row-wide', 'sultan-checkout-time-field' ],
		'input_class' => [ 'sultan-checkout-time-select' ],
		'clear'       => true,
	];
}

function sultan_get_datenschutz_checkout_field() {
	return [
		'type'        => 'checkbox',
		'label'       => __( 'I agree to the privacy policy.', 'sultan-checkout-time' ),
		'required'    => true,
		'priority'    => 130,
		'class'       => [ 'form-row-wide', 'sultan-datenschutz-field' ],
		'label_class' => [ 'sultan-datenschutz-label' ],
		'clear'       => true,
	];
}

// Classic checkout field.
add_filter( 'woocommerce_checkout_fields', function ( $fields ) {
	$fields['order']['sultan_pickup_time'] = sultan_get_pickup_time_checkout_field();
	$fields['order']['sultan_datenschutz'] = sultan_get_datenschutz_checkout_field();

	return $fields;
} );

add_action( 'wp_head', function () {
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
		return;
	}
	?>
	<style>
		.woocommerce-checkout .sultan-checkout-time-field {
			margin-top: 12px;
		}

		.woocommerce-checkout .sultan-checkout-time-select {
			display: block;
			width: 100%;
			height: 48px;
			padding: 0 44px 0 14px;
			border: 1px solid rgba(15, 23, 42, 0.18);
			border-radius: 12px;
			background-color: #ffffff;
			background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='18' height='18' viewBox='0 0 20 20' fill='none'%3E%3Cpath d='M5 7.5L10 12.5L15 7.5' stroke='%235f6f7f' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
			background-repeat: no-repeat;
			background-position: right 14px center;
			background-size: 18px 18px;
			font-size: 15px;
			line-height: 1.4;
			color: #1f2937;
			box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
			appearance: none;
			-webkit-appearance: none;
			-moz-appearance: none;
			transition: border-color 0.2s ease, box-shadow 0.2s ease;
		}

		.woocommerce-checkout .sultan-checkout-time-select:focus {
			border-color: rgba(37, 99, 235, 0.7);
			box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12);
			outline: none;
		}

		.woocommerce-checkout .sultan-datenschutz-field {
			margin-top: 12px;
		}

		.woocommerce-checkout .sultan-datenschutz-field label {
			display: flex;
			align-items: flex-start;
			gap: 10px;
			font-size: 14px;
			line-height: 1.5;
			color: #374151;
		}

		.woocommerce-checkout .sultan-datenschutz-field .input-checkbox {
			width: 18px;
			height: 18px;
			margin-top: 2px;
			flex-shrink: 0;
			accent-color: #2563eb;
		}
	</style>
	<?php
} );

// Checkout Block field.
add_action( 'woocommerce_init', function () {
	if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
		return;
	}

	woocommerce_register_additional_checkout_field(
		[
			'id'                => 'sultan-checkout-time/sultan_pickup_time',
			'label'             => __( 'Pickup Time', 'sultan-checkout-time' ),
			'location'          => 'order',
			'type'              => 'select',
			'required'          => true,
			'placeholder'       => __( '— Select time —', 'sultan-checkout-time' ),
			'options'           => sultan_get_pickup_time_options_for_blocks(),
			'sanitize_callback' => function ( $value ) {
				return sanitize_text_field( $value );
			},
			'validate_callback' => function ( $value ) {
				if ( sultan_pickup_orders_are_closed() ) {
					return new WP_Error(
						'sultan_pickup_closed',
						__( 'We are currently closed. Please place your order during working hours.', 'sultan-checkout-time' )
					);
				}

				if ( ! sultan_pickup_time_is_valid( $value ) ) {
					return new WP_Error(
						'sultan_pickup_invalid',
						__( 'The selected pickup time is invalid. Please choose another slot.', 'sultan-checkout-time' )
					);
				}

				return null;
			},
		]
	);
} );

// Classic checkout validation for time field.
add_action( 'woocommerce_checkout_process', function () {
	if ( isset( $_POST['sultan_pickup_time'] ) ) {
		$selected = sanitize_text_field( wp_unslash( $_POST['sultan_pickup_time'] ) );

		if ( ! sultan_pickup_time_is_valid( $selected ) ) {
			wc_add_notice( __( 'The selected pickup time is invalid. Please choose another slot.', 'sultan-checkout-time' ), 'error' );
		}
	}

	if ( empty( $_POST['sultan_datenschutz'] ) ) {
		wc_add_notice( __( 'Please confirm the privacy policy before placing your order.', 'sultan-checkout-time' ), 'error' );
	}
} );

// Classic checkout validation for opening hours / disable switch.
add_action( 'woocommerce_checkout_process', function () {
	if ( sultan_pickup_orders_are_closed() ) {
		wc_add_notice( __( 'We are currently closed. Please place your order during working hours.', 'sultan-checkout-time' ), 'error' );
	}
} );

// Save classic checkout value to order meta.
add_action( 'woocommerce_checkout_create_order', function ( $order ) {
	if ( isset( $_POST['sultan_pickup_time'] ) ) {
		$order->update_meta_data(
			'_sultan_pickup_time',
			sanitize_text_field( wp_unslash( $_POST['sultan_pickup_time'] ) )
		);
	}

	if ( isset( $_POST['sultan_datenschutz'] ) ) {
		$order->update_meta_data( '_sultan_datenschutz_accepted', 'yes' );
	}
}, 10, 1 );

// Show in admin order page.
add_action( 'woocommerce_admin_order_data_after_billing_address', function ( $order ) {
	$pickup_time = $order->get_meta( '_sultan_pickup_time' );

	if ( $pickup_time ) {
		echo '<p><strong>' . esc_html__( 'Pickup Time:', 'sultan-checkout-time' ) . '</strong> ' . esc_html( $pickup_time ) . '</p>';
	}
} );

/**
 * ============================================================
 * RESTAURANT DELIVERY & CHECKOUT
 * ============================================================
 */

/**
 * Make billing phone required.
 */
add_filter( 'woocommerce_billing_fields', 'sultan_customize_billing_phone', 9999 );

/**
 * Force the core phone field to be required in Checkout Blocks.
 */
add_filter( 'option_woocommerce_checkout_phone_field', function () {
	return 'required';
} );

function sultan_customize_billing_phone( $fields ) {

	if ( isset( $fields['billing_phone'] ) ) {
		$fields['billing_phone']['required']    = true;
		$fields['billing_phone']['label']       = __( 'Phone number', 'sultan-checkout-time' );
		$fields['billing_phone']['placeholder'] = __( 'e.g. +49 170 1234567', 'sultan-checkout-time' );
	}

	return $fields;
}

/**
 * Validate the billing phone for the classic checkout.
 */
add_action( 'woocommerce_checkout_process', function () {
	$phone = isset( $_POST['billing_phone'] )
		? sanitize_text_field( wp_unslash( $_POST['billing_phone'] ) )
		: '';

	if ( '' === trim( $phone ) ) {
		wc_add_notice(
			__( 'Please enter a billing phone number.', 'sultan-checkout-time' ),
			'error'
		);
	}
} );


/**
 * Distance shipping configuration.
 */
define( 'SULTAN_GOOGLE_MAPS_API_KEY', 'YOUR_GOOGLE_MAPS_API_KEY' );
define( 'SULTAN_GOOGLE_MAPS_BROWSER_API_KEY', 'YOUR_GOOGLE_MAPS_BROWSER_API_KEY' );
define( 'SULTAN_STORE_ADDRESS', 'YOUR_STORE_ADDRESS' );
define( 'SULTAN_MAX_DELIVERY_DISTANCE_KM', 30.0 );

/**
 * Return all supported weekday names.
 */
function sultan_get_weekday_choices() {
	return [
		'Monday',
		'Tuesday',
		'Wednesday',
		'Thursday',
		'Friday',
		'Saturday',
		'Sunday',
	];
}

/**
 * Return a localized weekday label while keeping English names as stable keys.
 */
function sultan_get_weekday_label( $day ) {
	$labels = [
		'Monday'    => __( 'Monday', 'sultan-checkout-time' ),
		'Tuesday'   => __( 'Tuesday', 'sultan-checkout-time' ),
		'Wednesday' => __( 'Wednesday', 'sultan-checkout-time' ),
		'Thursday'  => __( 'Thursday', 'sultan-checkout-time' ),
		'Friday'    => __( 'Friday', 'sultan-checkout-time' ),
		'Saturday'  => __( 'Saturday', 'sultan-checkout-time' ),
		'Sunday'    => __( 'Sunday', 'sultan-checkout-time' ),
	];

	return $labels[ $day ] ?? $day;
}

/**
 * Return default distance shipping settings.
 */
function sultan_get_default_distance_shipping_settings() {
	$default_open_time  = sanitize_text_field( get_option( 'sultan_pickup_order_start', '' ) );
	$default_close_time = sanitize_text_field( get_option( 'sultan_pickup_order_end', '' ) );
	$weekly_schedule    = [];

	foreach ( sultan_get_weekday_choices() as $day ) {
		$weekly_schedule[ $day ] = [
			'enabled'    => 'Sunday' !== $day,
			'open_time'  => $default_open_time,
			'close_time' => $default_close_time,
		];
	}

	return [
		'server_api_key'  => SULTAN_GOOGLE_MAPS_API_KEY,
		'browser_api_key' => SULTAN_GOOGLE_MAPS_BROWSER_API_KEY,
		'store_address'   => SULTAN_STORE_ADDRESS,
		'enable_pickup'   => true,
		'pickup_label'    => __( 'Local pickup', 'sultan-checkout-time' ),
		'closed_days'     => [ 'Sunday' ],
		'weekly_schedule' => $weekly_schedule,
		'max_distance'    => SULTAN_MAX_DELIVERY_DISTANCE_KM,
		'tiers'           => [
			[ 'maximum' => 5.0,  'fee' => 0.00, 'cap' => 0.00 ],
			[ 'maximum' => 10.0, 'fee' => 2.00, 'cap' => 12.50 ],
			[ 'maximum' => 15.0, 'fee' => 2.50, 'cap' => 17.50 ],
			[ 'maximum' => 20.0, 'fee' => 3.00, 'cap' => 20.00 ],
			[ 'maximum' => 25.0, 'fee' => 3.50, 'cap' => 25.00 ],
			[ 'maximum' => 30.0, 'fee' => 4.50, 'cap' => 40.00 ],
		],
	];
}

/**
 * Return saved distance shipping settings merged with defaults.
 */
function sultan_get_distance_shipping_settings() {
	$defaults = sultan_get_default_distance_shipping_settings();
	$saved    = get_option( 'sultan_distance_shipping_settings', [] );

	if ( ! is_array( $saved ) ) {
		return $defaults;
	}

	$settings          = wp_parse_args( $saved, $defaults );
	$settings['tiers'] = isset( $saved['tiers'] ) && is_array( $saved['tiers'] )
		? array_replace( $defaults['tiers'], $saved['tiers'] )
		: $defaults['tiers'];
	$settings['weekly_schedule'] = isset( $saved['weekly_schedule'] ) && is_array( $saved['weekly_schedule'] )
		? array_replace( $defaults['weekly_schedule'], $saved['weekly_schedule'] )
		: $defaults['weekly_schedule'];

	return $settings;
}

/**
 * Sanitize distance shipping settings before saving them.
 */
function sultan_sanitize_distance_shipping_settings( $input ) {
	$defaults = sultan_get_default_distance_shipping_settings();
	$input    = is_array( $input ) ? $input : [];

	$output = [
		'server_api_key'  => sanitize_text_field( $input['server_api_key'] ?? '' ),
		'browser_api_key' => sanitize_text_field( $input['browser_api_key'] ?? '' ),
		'store_address'   => sanitize_text_field( $input['store_address'] ?? '' ),
		'enable_pickup'   => ! empty( $input['enable_pickup'] ),
		'pickup_label'    => sanitize_text_field( $input['pickup_label'] ?? $defaults['pickup_label'] ),
		'closed_days'     => [],
		'weekly_schedule' => [],
		'max_distance'    => max( 0.1, (float) ( $input['max_distance'] ?? $defaults['max_distance'] ) ),
		'tiers'           => [],
	];

	$closed_days = isset( $input['closed_days'] ) && is_array( $input['closed_days'] )
		? array_map( 'sanitize_text_field', $input['closed_days'] )
		: [];

	$output['closed_days'] = array_values(
		array_intersect( sultan_get_weekday_choices(), $closed_days )
	);

	foreach ( sultan_get_weekday_choices() as $day ) {
		$day_input = isset( $input['weekly_schedule'][ $day ] ) && is_array( $input['weekly_schedule'][ $day ] )
			? $input['weekly_schedule'][ $day ]
			: [];

		$open_time  = sanitize_text_field( $day_input['open_time'] ?? '' );
		$close_time = sanitize_text_field( $day_input['close_time'] ?? '' );

		if ( ! preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $open_time ) ) {
			$open_time = '';
		}

		if ( ! preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $close_time ) ) {
			$close_time = '';
		}

		$output['weekly_schedule'][ $day ] = [
			'enabled'    => ! empty( $day_input['enabled'] ),
			'open_time'  => $open_time,
			'close_time' => $close_time,
		];
	}

	foreach ( $defaults['tiers'] as $index => $default_tier ) {
		$tier = isset( $input['tiers'][ $index ] ) && is_array( $input['tiers'][ $index ] )
			? $input['tiers'][ $index ]
			: [];

		$output['tiers'][ $index ] = [
			'maximum' => max( 0.0, (float) ( $tier['maximum'] ?? $default_tier['maximum'] ) ),
			'fee'     => max( 0.0, (float) ( $tier['fee'] ?? $default_tier['fee'] ) ),
			'cap'     => max( 0.0, (float) ( $tier['cap'] ?? $default_tier['cap'] ) ),
		];
	}

	usort( $output['tiers'], function ( $first, $second ) {
		return $first['maximum'] <=> $second['maximum'];
	} );

	return $output;
}

/**
 * Render the weekly store schedule.
 */
function sultan_render_weekly_schedule_field() {
	$settings = sultan_get_distance_shipping_settings();

	echo '<table class="widefat striped" style="max-width:700px;">';
	echo '<thead><tr><th>' . esc_html__( 'Day', 'sultan-checkout-time' ) . '</th><th>' . esc_html__( 'Open', 'sultan-checkout-time' ) . '</th><th>' . esc_html__( 'Opening time', 'sultan-checkout-time' ) . '</th><th>' . esc_html__( 'Closing time', 'sultan-checkout-time' ) . '</th></tr></thead><tbody>';

	foreach ( sultan_get_weekday_choices() as $day ) {
		$day_settings = $settings['weekly_schedule'][ $day ];
		$field_base   = 'sultan_distance_shipping_settings[weekly_schedule][' . $day . ']';

		echo '<tr>';
		echo '<td><strong>' . esc_html( sultan_get_weekday_label( $day ) ) . '</strong></td>';
		echo '<td><label><input type="checkbox" name="' . esc_attr( $field_base . '[enabled]' ) . '" value="1" ' . checked( ! empty( $day_settings['enabled'] ), true, false ) . '> ' . esc_html__( 'Working day', 'sultan-checkout-time' ) . '</label></td>';
		echo '<td><input type="time" name="' . esc_attr( $field_base . '[open_time]' ) . '" value="' . esc_attr( $day_settings['open_time'] ) . '"></td>';
		echo '<td><input type="time" name="' . esc_attr( $field_base . '[close_time]' ) . '" value="' . esc_attr( $day_settings['close_time'] ) . '"></td>';
		echo '</tr>';
	}

	echo '</tbody></table>';
	echo '<p class="description">' . esc_html__( 'Clear both time fields to keep an enabled day open for the full day. Overnight schedules are supported.', 'sultan-checkout-time' ) . '</p>';
}

/**
 * Render editable distance pricing tiers.
 */
function sultan_render_distance_tiers_field() {
	$settings = sultan_get_distance_shipping_settings();
	$minimum  = 0.0;

	echo '<table class="widefat striped" style="max-width:700px;">';
	echo '<thead><tr><th>' . esc_html__( 'Distance range', 'sultan-checkout-time' ) . '</th><th>' . esc_html__( 'Fee in EUR', 'sultan-checkout-time' ) . '</th><th>' . esc_html__( 'Maximum cap in EUR', 'sultan-checkout-time' ) . '</th></tr></thead><tbody>';

	foreach ( $settings['tiers'] as $index => $tier ) {
		echo '<tr>';
		echo '<td><span>' . esc_html( number_format_i18n( $minimum, 2 ) ) . ' - </span><input type="number" min="0" step="0.01" name="sultan_distance_shipping_settings[tiers][' . esc_attr( $index ) . '][maximum]" value="' . esc_attr( $tier['maximum'] ) . '" /> km</td>';
		echo '<td><input type="number" min="0" step="0.01" name="sultan_distance_shipping_settings[tiers][' . esc_attr( $index ) . '][fee]" value="' . esc_attr( $tier['fee'] ) . '" /></td>';
		echo '<td><input type="number" min="0" step="0.01" name="sultan_distance_shipping_settings[tiers][' . esc_attr( $index ) . '][cap]" value="' . esc_attr( $tier['cap'] ) . '" /></td>';
		echo '</tr>';
		$minimum = (float) $tier['maximum'] + 0.01;
	}

	echo '</tbody></table>';
}

/**
 * Load Google Places address autocomplete on cart and checkout pages.
 */
add_action( 'wp_enqueue_scripts', 'sultan_enqueue_address_autocomplete', 20 );

function sultan_enqueue_address_autocomplete() {
	if ( ! function_exists( 'is_checkout' ) || ( ! is_checkout() && ! is_cart() ) ) {
		return;
	}

	$settings        = sultan_get_distance_shipping_settings();
	$browser_api_key = trim( $settings['browser_api_key'] );
	$maps_language   = strtolower( substr( determine_locale(), 0, 2 ) );

	if (
		'' === $browser_api_key ||
		'YOUR_GOOGLE_MAPS_BROWSER_API_KEY' === $browser_api_key
	) {
		return;
	}

	$script_url = add_query_arg(
		[
			'key'       => $browser_api_key,
			'libraries' => 'places',
			'language'  => $maps_language,
			'callback'  => 'sultanInitAddressAutocomplete',
		],
		'https://maps.googleapis.com/maps/api/js'
	);

	wp_enqueue_script(
		'sultan-google-places-autocomplete',
		$script_url,
		[],
		null,
		true
	);

	wp_add_inline_script(
		'sultan-google-places-autocomplete',
		sultan_get_address_autocomplete_script(),
		'before'
	);

	wp_add_inline_style(
		'sultan-checkout-time-style',
		'.pac-container{z-index:999999!important;}'
	);
}

/**
 * Return the browser-side address autocomplete integration.
 */
function sultan_get_address_autocomplete_script() {
	return <<<'JS'
(function () {
	'use strict';

	var observerStarted = false;

	function findComponent(components, type, shortName) {
		var component = components.find(function (item) {
			return item.types.indexOf(type) !== -1;
		});

		if (!component) {
			return '';
		}

		return shortName ? component.short_name : component.long_name;
	}

	function setField(prefix, field, value) {
		var selectors = [
			'#' + prefix + '_' + field,
			'[name="' + prefix + '_' + field + '"]',
			'#' + prefix + '-' + field.replace(/_/g, '-'),
			'[name="' + prefix + '-' + field.replace(/_/g, '-') + '"]'
		];
		var element = document.querySelector(selectors.join(','));

		if (!element) {
			return;
		}

		var prototype = element.tagName === 'SELECT'
			? window.HTMLSelectElement.prototype
			: window.HTMLInputElement.prototype;
		var descriptor = Object.getOwnPropertyDescriptor(prototype, 'value');

		if (descriptor && descriptor.set) {
			descriptor.set.call(element, value);
		} else {
			element.value = value;
		}

		element.dispatchEvent(new Event('input', { bubbles: true }));
		element.dispatchEvent(new Event('change', { bubbles: true }));
	}

	function fillAddress(input, place) {
		var components = place.address_components || [];
		var prefix = input.getAttribute('data-sultan-address-prefix');
		var streetNumber = findComponent(components, 'street_number', false);
		var route = findComponent(components, 'route', false);
		var city = findComponent(components, 'locality', false) ||
			findComponent(components, 'postal_town', false) ||
			findComponent(components, 'sublocality_level_1', false) ||
			findComponent(components, 'administrative_area_level_2', false);
		var postcode = findComponent(components, 'postal_code', false);
		var state = findComponent(components, 'administrative_area_level_1', true);
		var country = findComponent(components, 'country', true);
		var street = [route, streetNumber].filter(Boolean).join(' ');

		setField(prefix, 'country', country);

		window.setTimeout(function () {
			setField(prefix, 'address_1', street);
			setField(prefix, 'city', city);
			setField(prefix, 'postcode', postcode);
			setField(prefix, 'state', state);

			if (window.jQuery && window.jQuery(document.body).trigger) {
				window.jQuery(document.body).trigger('update_checkout');
			}
		}, 150);
	}

	function attachAutocomplete(input, prefix) {
		if (input.getAttribute('data-sultan-autocomplete') === '1') {
			return;
		}

		input.setAttribute('data-sultan-autocomplete', '1');
		input.setAttribute('data-sultan-address-prefix', prefix);
		input.setAttribute('autocomplete', 'new-password');

		var autocomplete = new google.maps.places.Autocomplete(input, {
			fields: ['address_components', 'formatted_address'],
			types: ['address']
		});

		autocomplete.addListener('place_changed', function () {
			fillAddress(input, autocomplete.getPlace());
		});
	}

	function scanAddressFields() {
		if (!window.google || !google.maps || !google.maps.places) {
			return;
		}

		[
			{ prefix: 'shipping', selectors: '#shipping_address_1,[name="shipping_address_1"],#shipping-address-1,[name="shipping-address-1"]' },
			{ prefix: 'billing', selectors: '#billing_address_1,[name="billing_address_1"],#billing-address-1,[name="billing-address-1"]' }
		].forEach(function (config) {
			document.querySelectorAll(config.selectors).forEach(function (input) {
				attachAutocomplete(input, config.prefix);
			});
		});
	}

	window.sultanInitAddressAutocomplete = function () {
		scanAddressFields();

		if (!observerStarted && document.body) {
			observerStarted = true;
			new MutationObserver(scanAddressFields).observe(document.body, {
				childList: true,
				subtree: true
			});
		}
	};
}());
JS;
}

/**
 * Build a normalized destination address from a shipping package.
 */
function sultan_get_package_destination_address( $package ) {
	$destination = isset( $package['destination'] ) && is_array( $package['destination'] )
		? $package['destination']
		: [];

	if (
		empty( $destination['address'] ) ||
		empty( $destination['city'] ) ||
		empty( $destination['country'] )
	) {
		return '';
	}

	$parts = [
		$destination['address'] ?? '',
		$destination['address_2'] ?? '',
		$destination['postcode'] ?? '',
		$destination['city'] ?? '',
		$destination['state'] ?? '',
		$destination['country'] ?? '',
	];

	$parts = array_map( 'sanitize_text_field', $parts );
	$parts = array_filter( array_map( 'trim', $parts ) );

	return implode( ', ', $parts );
}

/**
 * Build the current checkout shipping destination.
 */
function sultan_get_checkout_destination_address() {
	if ( ! WC()->customer ) {
		return '';
	}

	if (
		! WC()->customer->get_shipping_address_1() ||
		! WC()->customer->get_shipping_city() ||
		! WC()->customer->get_shipping_country()
	) {
		return '';
	}

	$parts = [
		WC()->customer->get_shipping_address_1(),
		WC()->customer->get_shipping_address_2(),
		WC()->customer->get_shipping_postcode(),
		WC()->customer->get_shipping_city(),
		WC()->customer->get_shipping_state(),
		WC()->customer->get_shipping_country(),
	];

	$parts = array_map( 'sanitize_text_field', $parts );
	$parts = array_filter( array_map( 'trim', $parts ) );

	return implode( ', ', $parts );
}

/**
 * Get the exact driving distance in kilometers and cache it for 24 hours.
 *
 * @return float|WP_Error
 */
function sultan_get_driving_distance_km( $destination_address ) {
	$settings            = sultan_get_distance_shipping_settings();
	$destination_address = trim( sanitize_text_field( $destination_address ) );
	$origin_address      = trim( $settings['store_address'] );
	$api_key             = trim( $settings['server_api_key'] );

	if ( '' === $destination_address ) {
		return new WP_Error( 'sultan_missing_destination', __( 'Please enter a complete shipping address.', 'sultan-checkout-time' ) );
	}

	if (
		'' === $origin_address ||
		'YOUR_STORE_ADDRESS' === $origin_address ||
		'' === $api_key ||
		'YOUR_GOOGLE_MAPS_API_KEY' === $api_key
	) {
		return new WP_Error( 'sultan_maps_not_configured', __( 'Delivery distance calculation is not configured. Please contact the store.', 'sultan-checkout-time' ) );
	}

	$cache_key = 'sultan_distance_' . md5( strtolower( $origin_address . '|' . $destination_address ) );
	$cached    = get_transient( $cache_key );

	if ( false !== $cached && is_numeric( $cached ) ) {
		return (float) $cached;
	}

	$url = add_query_arg(
		[
			'origins'      => $origin_address,
			'destinations' => $destination_address,
			'mode'         => 'driving',
			'units'        => 'metric',
			'key'          => $api_key,
		],
		'https://maps.googleapis.com/maps/api/distancematrix/json'
	);

	$response = wp_safe_remote_get(
		$url,
		[
			'timeout'     => 15,
			'redirection' => 2,
			'headers'     => [ 'Accept' => 'application/json' ],
		]
	);

	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		return new WP_Error( 'sultan_maps_request_failed', __( 'The delivery distance service is temporarily unavailable. Please try again.', 'sultan-checkout-time' ) );
	}

	$data    = json_decode( wp_remote_retrieve_body( $response ), true );
	$element = $data['rows'][0]['elements'][0] ?? [];

	if (
		! is_array( $data ) ||
		'OK' !== ( $data['status'] ?? '' ) ||
		'OK' !== ( $element['status'] ?? '' ) ||
		! isset( $element['distance']['value'] ) ||
		! is_numeric( $element['distance']['value'] )
	) {
		return new WP_Error( 'sultan_distance_unavailable', __( 'The shipping address could not be verified. Please check the address and try again.', 'sultan-checkout-time' ) );
	}

	$distance_km = round( (float) $element['distance']['value'] / 1000, 2 );

	set_transient( $cache_key, $distance_km, DAY_IN_SECONDS );

	return $distance_km;
}

/**
 * Return the fixed delivery fee for a road-distance tier.
 * Each cap is enforced as an upper bound on its tier fee.
 *
 * @return float|null
 */
function sultan_get_distance_shipping_cost( $distance_km ) {
	$settings = sultan_get_distance_shipping_settings();
	$tiers    = $settings['tiers'];

	foreach ( $tiers as $tier ) {
		if ( $distance_km <= $tier['maximum'] ) {
			return min( $tier['fee'], $tier['cap'] );
		}
	}

	return null;
}

/**
 * Store the latest delivery availability result in the WooCommerce session.
 */
function sultan_set_delivery_status( $status, $distance_km = 0, $message = '' ) {
	if ( ! function_exists( 'WC' ) || ! WC()->session ) {
		return;
	}

	WC()->session->set(
		'sultan_delivery_status',
		[
			'status'      => sanitize_key( $status ),
			'distance_km' => (float) $distance_km,
			'message'     => sanitize_text_field( $message ),
		]
	);
}

/**
 * Return a clear no-delivery message for the active customer address.
 */
function sultan_get_no_delivery_message() {
	$settings = sultan_get_distance_shipping_settings();
	$status   = function_exists( 'WC' ) && WC()->session
		? WC()->session->get( 'sultan_delivery_status', [] )
		: [];

	if ( 'over_limit' === ( $status['status'] ?? '' ) ) {
		return sprintf(
			/* translators: %s: maximum delivery distance in kilometers */
			__( 'Delivery is not available. This address is farther than our %s km delivery limit.', 'sultan-checkout-time' ),
			number_format_i18n( $settings['max_distance'], 1 )
		);
	}

	if ( 'error' === ( $status['status'] ?? '' ) ) {
		return $status['message'] ?: __( 'Delivery availability could not be verified. Please check the address and try again.', 'sultan-checkout-time' );
	}

	return __( 'No delivery methods are available for this address.', 'sultan-checkout-time' );
}

add_filter( 'woocommerce_cart_no_shipping_available_html', 'sultan_get_no_delivery_message' );
add_filter( 'woocommerce_no_shipping_available_html', 'sultan_get_no_delivery_message' );

/**
 * Add the plugin-managed free local pickup choice.
 */
function sultan_add_local_pickup_rate( $rates, $unavailable_delivery = false ) {
	$settings = sultan_get_distance_shipping_settings();

	if ( empty( $settings['enable_pickup'] ) || ! class_exists( 'WC_Shipping_Rate' ) ) {
		return $rates;
	}

	foreach ( $rates as $rate ) {
		if ( 'local_pickup' === $rate->get_method_id() ) {
			if ( $unavailable_delivery ) {
				$rate->set_label( $rate->get_label() . ' — ' . __( 'delivery is not available for this address', 'sultan-checkout-time' ) );
			}

			return $rates;
		}
	}

	$label = $settings['pickup_label'] ?: __( 'Local pickup', 'sultan-checkout-time' );

	if ( $unavailable_delivery ) {
		$label .= ' — ' . __( 'delivery is not available for this address', 'sultan-checkout-time' );
	}

	$rates['sultan_local_pickup'] = new WC_Shipping_Rate(
		'sultan_local_pickup',
		$label,
		0,
		[],
		'local_pickup',
		0
	);

	return $rates;
}

/**
 * Keep only local-pickup rates when courier delivery is unavailable.
 */
function sultan_only_local_pickup_rates( $rates, $unavailable_delivery = true ) {
	$pickup_rates = [];

	foreach ( $rates as $rate_id => $rate ) {
		if ( 'local_pickup' === $rate->get_method_id() ) {
			$pickup_rates[ $rate_id ] = $rate;
		}
	}

	return sultan_add_local_pickup_rate( $pickup_rates, $unavailable_delivery );
}

/**
 * Determine whether the customer selected local pickup in classic checkout.
 */
function sultan_customer_selected_local_pickup() {
	if ( ! function_exists( 'WC' ) || ! WC()->session ) {
		return false;
	}

	$chosen_methods = (array) WC()->session->get( 'chosen_shipping_methods', [] );

	foreach ( $chosen_methods as $chosen_method ) {
		if ( false !== strpos( (string) $chosen_method, 'local_pickup' ) ) {
			return true;
		}

		if ( 'sultan_local_pickup' === $chosen_method ) {
			return true;
		}
	}

	return false;
}

/**
 * Apply distance pricing to every configured WooCommerce flat-rate method.
 */
add_filter( 'woocommerce_package_rates', function ( $rates, $package ) {
	$settings            = sultan_get_distance_shipping_settings();
	$destination_address = sultan_get_package_destination_address( $package );
	$rates               = sultan_add_local_pickup_rate( $rates );

	if ( '' === $destination_address ) {
		sultan_set_delivery_status( 'incomplete' );
		return $rates;
	}

	$distance_km = sultan_get_driving_distance_km( $destination_address );

	if ( is_wp_error( $distance_km ) ) {
		sultan_set_delivery_status( 'error', 0, $distance_km->get_error_message() );
		return sultan_only_local_pickup_rates( $rates );
	}

	if ( $distance_km > (float) $settings['max_distance'] ) {
		sultan_set_delivery_status( 'over_limit', $distance_km );
		return sultan_only_local_pickup_rates( $rates );
	}

	$cost = sultan_get_distance_shipping_cost( $distance_km );

	if ( null === $cost ) {
		sultan_set_delivery_status( 'over_limit', $distance_km );
		return sultan_only_local_pickup_rates( $rates );
	}

	sultan_set_delivery_status( 'available', $distance_km );
	$flat_rate_found = false;

	foreach ( $rates as $rate_id => $rate ) {
		if ( 'flat_rate' !== $rate->get_method_id() ) {
			continue;
		}

		$flat_rate_found = true;

		$rate->set_cost( $cost );
		$rate->set_label(
			sprintf(
				/* translators: %s: driving distance in kilometers */
				__( 'Delivery Fee (%s km)', 'sultan-checkout-time' ),
				number_format_i18n( $distance_km, 1 )
			)
		);

		if ( $rate->get_taxes() ) {
			$rate->set_taxes( WC_Tax::calc_shipping_tax( $cost, WC_Tax::get_shipping_tax_rates() ) );
		}

		$rates[ $rate_id ] = $rate;
	}

	if ( ! $flat_rate_found && class_exists( 'WC_Shipping_Rate' ) ) {
		$rates['sultan_distance_delivery'] = new WC_Shipping_Rate(
			'sultan_distance_delivery',
			sprintf(
				/* translators: %s: driving distance in kilometers */
				__( 'Delivery Fee (%s km)', 'sultan-checkout-time' ),
				number_format_i18n( $distance_km, 1 )
			),
			$cost,
			WC_Tax::calc_shipping_tax( $cost, WC_Tax::get_shipping_tax_rates() ),
			'flat_rate',
			0
		);
	}

	return $rates;
}, 100, 2 );

/**
 * Block checkout when the address cannot be verified or exceeds 30 km.
 */
add_action( 'woocommerce_checkout_process', function () {
	if ( sultan_customer_selected_local_pickup() ) {
		return;
	}

	$settings            = sultan_get_distance_shipping_settings();
	$destination_address = sultan_get_checkout_destination_address();

	if ( '' === $destination_address ) {
		return;
	}

	$distance_km = sultan_get_driving_distance_km( $destination_address );

	if ( is_wp_error( $distance_km ) ) {
		wc_add_notice( $distance_km->get_error_message(), 'error' );
		return;
	}

	if ( $distance_km > (float) $settings['max_distance'] ) {
		wc_add_notice(
			sprintf(
				/* translators: %s: maximum delivery distance in kilometers */
				__( 'Delivery is not available for distances over %s km.', 'sultan-checkout-time' ),
				number_format_i18n( $settings['max_distance'], 1 )
			),
			'error'
		);
	}
} );

/**
 * Validate phone and delivery radius for WooCommerce Checkout Blocks.
 */
add_action(
	'woocommerce_store_api_checkout_update_order_from_request',
	function ( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		if ( '' === trim( (string) $order->get_billing_phone() ) ) {
			throw new Exception(
				__( 'Please enter a billing phone number.', 'sultan-checkout-time' )
			);
		}

		foreach ( $order->get_items( 'shipping' ) as $shipping_item ) {
			if ( 'local_pickup' === $shipping_item->get_method_id() || 'sultan_local_pickup' === $shipping_item->get_method_id() ) {
				return;
			}
		}

		$address_1 = $order->get_shipping_address_1();
		$address_2 = $order->get_shipping_address_2();
		$postcode  = $order->get_shipping_postcode();
		$city      = $order->get_shipping_city();
		$state     = $order->get_shipping_state();
		$country   = $order->get_shipping_country();

		if ( '' === trim( (string) $address_1 ) ) {
			$address_1 = $order->get_billing_address_1();
			$address_2 = $order->get_billing_address_2();
			$postcode  = $order->get_billing_postcode();
			$city      = $order->get_billing_city();
			$state     = $order->get_billing_state();
			$country   = $order->get_billing_country();
		}

		$parts = array_filter(
			array_map(
				'sanitize_text_field',
				[ $address_1, $address_2, $postcode, $city, $state, $country ]
			)
		);

		if ( empty( $address_1 ) || empty( $city ) || empty( $country ) ) {
			throw new Exception(
				__( 'Please enter a complete delivery address.', 'sultan-checkout-time' )
			);
		}

		$distance_km = sultan_get_driving_distance_km( implode( ', ', $parts ) );

		if ( is_wp_error( $distance_km ) ) {
			throw new Exception( $distance_km->get_error_message() );
		}

		$settings = sultan_get_distance_shipping_settings();

		if ( $distance_km > (float) $settings['max_distance'] ) {
			throw new Exception(
				sprintf(
					/* translators: %s: maximum delivery distance in kilometers */
					__( 'Delivery is not available. This address is farther than our %s km delivery limit.', 'sultan-checkout-time' ),
					number_format_i18n( $settings['max_distance'], 1 )
				)
			);
		}
	},
	20,
	1
);

/**
 * Invalidate WooCommerce shipping caches after saving these settings.
 */
add_action( 'update_option_sultan_distance_shipping_settings', function () {
	if ( class_exists( 'WC_Cache_Helper' ) ) {
		WC_Cache_Helper::get_transient_version( 'shipping', true );
	}
} );
