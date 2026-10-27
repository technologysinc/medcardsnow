<?php
/**
 * Thank You Page Template
 */
if (!defined('ABSPATH')) exit;

$txn = sanitize_text_field($_GET['txn'] ?? '');
?>
<div class="container text-center py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card border-0 shadow-lg">
                <div class="card-body p-5">
                    <div class="mb-4">
                        <div class="success-checkmark">
                            <div class="check-icon">
                                <span class="icon-line line-tip"></span>
                                <span class="icon-line line-long"></span>
                                <div class="icon-circle"></div>
                                <div class="icon-fix"></div>
                            </div>
                        </div>
                    </div>
                    
                    <h1 class="display-4 mb-3" style="color: #198754;">Payment Successful!</h1>
                    <p class="lead mb-4">Thank you for your order. Your medical card application is being processed.</p>
                    
                    <?php if ($txn): ?>
                    <p class="mb-4"><strong>Transaction ID:</strong> <code><?php echo esc_html($txn); ?></code></p>
                    <?php endif; ?>
                    
                    <div class="alert alert-info" style="background-color: #E8F6F7; border-color: #1AA3A9;">
                        <h5 class="alert-heading">What happens next?</h5>
                        <ul class="text-start mb-0">
                            <li>You will receive a confirmation email within 24 hours</li>
                            <li>Our medical team will review your application</li>
                            <li>Your medical card will be delivered electronically</li>
                            <li>For physical copies, allow 5-7 business days for delivery</li>
                        </ul>
                    </div>
                    
                    <div class="mt-5">
                        <a href="<?php echo home_url('/'); ?>" class="btn btn-lg" style="background: #1AA3A9; color: white; padding: 12px 30px;">
                            <i class="bi bi-house me-2"></i>Return to Home
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.success-checkmark {
    width: 80px;
    height: 80px;
    margin: 0 auto;
}
.check-icon {
    width: 80px;
    height: 80px;
    position: relative;
    border-radius: 50%;
    box-sizing: content-box;
    border: 4px solid #198754;
}
.icon-line {
    height: 5px;
    background-color: #198754;
    display: block;
    border-radius: 2px;
    position: absolute;
    z-index: 10;
}
.line-tip {
    top: 46px;
    left: 14px;
    width: 25px;
    transform: rotate(45deg);
}
.line-long {
    top: 38px;
    right: 8px;
    width: 47px;
    transform: rotate(-45deg);
}
.icon-circle {
    top: -4px;
    left: -4px;
    z-index: 10;
    width: 80px;
    height: 80px;
    border-radius: 50%;
    position: absolute;
    box-sizing: content-box;
    border: 4px solid rgba(25, 135, 84, 0.1);
}
.icon-fix {
    top: 8px;
    width: 5px;
    left: 26px;
    z-index: 1;
    height: 85px;
    position: absolute;
    transform: rotate(-45deg);
    background-color: #FFFFFF;
}
</style>