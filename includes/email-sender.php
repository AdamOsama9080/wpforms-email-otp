<?php
class WPForms_Email_Sender {
    public static function send_otp($email, $otp) {
        $subject = 'Your OTP Code';
        $message = 'Your verification code is: ' . $otp;
        return wp_mail($email, $subject, $message);
    }
}
