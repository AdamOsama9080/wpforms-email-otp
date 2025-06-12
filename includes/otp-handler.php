<?php
class WPForms_OTP_Handler {
    public static function generate_otp() {
        return str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    public static function store_otp($email, $otp) {
        set_transient('otp_' . md5($email), $otp, 5 * MINUTE_IN_SECONDS);
    }

    public static function verify_otp($email, $otp) {
        return get_transient('otp_' . md5($email)) === $otp;
    }
}
