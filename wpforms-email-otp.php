<?php
/*
Plugin Name: WPForms Email OTP Verification
Description: Adds email OTP verification to WPForms forms using secure Gmail SMTP through PHPMailer.
Version: 1.4    // bumped for SMTP feature
Author: Adam Osama
*/

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

//----------------------------------
// 🔐 SMTP CREDENTIALS
// It’s safer to move these to wp-config.php or environment variables.
// Kept inline here only because you asked to "edit here" directly.
//----------------------------------
if ( ! defined( 'WPFORMS_EMAIL_OTP_SMTP_USER' ) ) {
    define( 'WPFORMS_EMAIL_OTP_SMTP_USER', 'zN5tL@example.com' );
}
if ( ! defined( 'WPFORMS_EMAIL_OTP_SMTP_PASS' ) ) {
    define( 'WPFORMS_EMAIL_OTP_SMTP_PASS', 'your_gmail_app_password' ); // 👉 Gmail *App Password* (NOT your main password)
}

//----------------------------------
// 🔧 PLUGIN CONSTANTS
//----------------------------------

define( 'WPFORMS_EMAIL_OTP_VERSION', '1.1' );
define( 'WPFORMS_EMAIL_OTP_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPFORMS_EMAIL_OTP_URL', plugin_dir_url( __FILE__ ) );

//----------------------------------
// 📤 Configure PHPMailer to use Gmail SMTP for *every* wp_mail() call
//----------------------------------
add_action( 'phpmailer_init', 'wpforms_email_otp_configure_phpmailer' );
function wpforms_email_otp_configure_phpmailer( $phpmailer ) {
    // Only touch if SMTP credentials exist
    if ( empty( WPFORMS_EMAIL_OTP_SMTP_USER ) || empty( WPFORMS_EMAIL_OTP_SMTP_PASS ) ) {
        return;
    }

    $phpmailer->isSMTP();
    $phpmailer->Host       = 'smtp.gmail.com';
    $phpmailer->SMTPAuth   = true;
    $phpmailer->Username   = WPFORMS_EMAIL_OTP_SMTP_USER;
    $phpmailer->Password   = WPFORMS_EMAIL_OTP_SMTP_PASS;
    $phpmailer->SMTPSecure = 'tls'; // use 'ssl' for port 465
    $phpmailer->Port       = 587;

    // From/Reply‑To headers
    $phpmailer->setFrom( WPFORMS_EMAIL_OTP_SMTP_USER, get_bloginfo( 'name' ) );
    if ( empty( $phpmailer->FromName ) ) {
        $phpmailer->FromName = get_bloginfo( 'name' );
    }
}

//----------------------------------
// 🛠️  Include core plugin files
//----------------------------------
require_once WPFORMS_EMAIL_OTP_DIR . 'includes/otp-handler.php';
require_once WPFORMS_EMAIL_OTP_DIR . 'includes/email-sender.php';

//----------------------------------
// 🗂️  Enqueue assets
//----------------------------------
add_action( 'wp_enqueue_scripts', function () {
    wp_enqueue_script( 'wpforms-otp-js', WPFORMS_EMAIL_OTP_URL . 'assets/js/otp.js', [ 'jquery' ], WPFORMS_EMAIL_OTP_VERSION, true );
    wp_enqueue_style( 'wpforms-otp-css', WPFORMS_EMAIL_OTP_URL . 'assets/css/otp.css', [], WPFORMS_EMAIL_OTP_VERSION );

    wp_localize_script( 'wpforms-otp-js', 'wpformsOtp', [
        'ajaxurl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'wpforms_email_otp_nonce' ),
    ] );
} );

//----------------------------------
// 🚀 AJAX handler to send OTP
//----------------------------------
add_action( 'wp_ajax_send_wpforms_otp',       'wpforms_send_otp_ajax' );
add_action( 'wp_ajax_nopriv_send_wpforms_otp','wpforms_send_otp_ajax' );
function wpforms_send_otp_ajax() {
    check_ajax_referer( 'wpforms_email_otp_nonce', 'nonce' );

    $email = sanitize_email( $_POST['email'] ?? '' );
    if ( ! is_email( $email ) ) {
        wp_send_json_error( [ 'message' => 'Invalid email address.' ] );
    }

    $otp = WPForms_OTP_Handler::generate_otp();
    WPForms_OTP_Handler::store_otp( $email, $otp );

    $sent = WPForms_Email_Sender::send_otp( $email, $otp );

    if ( $sent ) {
        wp_send_json_success( [ 'message' => 'OTP sent successfully.' ] );
    } else {
        wp_send_json_error( [ 'message' => 'Failed to send OTP.' ] );
    }
}

//----------------------------------
// ✅  Validate OTP during WPForms submission
//----------------------------------
add_filter( 'wpforms_process_validate', 'wpforms_email_otp_validate', 10, 3 );
function wpforms_email_otp_validate( $fields, $entry, $form_data ) {
    foreach ( $fields as $id => $field ) {
        if ( 'email' === $field['type'] ) {
            $email = sanitize_email( $field['value'] );
            $otp   = sanitize_text_field( wp_unslash( $_POST['wpforms']['otp'] ?? '' ) );

            if ( ! WPForms_OTP_Handler::verify_otp( $email, $otp ) ) {
                wpforms()->process->errors[ $form_data['id'] ][ $id ] = __( 'Invalid or missing OTP code.', 'wpforms-email-otp' );
            }
        }
    }
    return $fields;
}
