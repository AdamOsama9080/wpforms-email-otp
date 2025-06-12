jQuery(function($) {
    let otpVerified = false;

    $(document).on("click", ".send-otp-btn", function() {
        const $btn = $(this);
        const $form = $btn.closest("form");
        const email = $form.find("input[type=email]").val();
        if (!email) return alert("Please enter a valid email");

        $btn.prop("disabled", true).text("Sending...");
        $.post(wpformsOtp.ajaxurl, {
            action: "send_wpforms_otp",
            email: email,
            nonce: wpformsOtp.nonce
        }, function(response) {
            if (response.success) {
                alert("OTP sent!");
                $form.find(".otp-field").show();
                $form.find(".otp-field .check-otp-btn").show();
            } else {
                alert(response.data.message);
            }
            $btn.prop("disabled", false).text("Send OTP");
        });
    });

    $(document).on("click", ".check-otp-btn", function() {
        const $btn = $(this);
        const $form = $btn.closest("form");
        const email = $form.find("input[type=email]").val();
        const otp = $form.find(".otp-input").val();

        if (!otp) return alert("Please enter the OTP.");

        $btn.prop("disabled", true).text("Checking...");
        $.post(wpformsOtp.ajaxurl, {
            action: "check_wpforms_otp",
            email: email,
            otp: otp,
            nonce: wpformsOtp.nonce
        }, function(response) {
            if (response.success) {
                alert("OTP verified successfully!");
                otpVerified = true;
                $form.find('button[type="submit"]').prop("disabled", false);
                $btn.text("Verified ✅");
            } else {
                alert("OTP verification failed.");
                otpVerified = false;
                $btn.prop("disabled", false).text("Check OTP");
                $form.find('button[type="submit"]').prop("disabled", true);
            }
        });
    });

    // Disable form submit button until OTP is verified
    $("form").each(function() {
        const $form = $(this);
        $form.find('button[type="submit"]').prop("disabled", true);
    });
});
