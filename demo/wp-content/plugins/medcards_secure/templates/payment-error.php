<?php
/**
 * Payment Error Page Template
 */
if (!defined('ABSPATH')) exit;

$reason = sanitize_text_field($_GET['reason'] ?? 'Payment failed');
$code = sanitize_text_field($_GET['code'] ?? '');
?>
<div class="container text-center py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card border-0 shadow-lg">
                <div class="card-body p-5">
                    <div class="mb-4">
                        <i class="bi bi-x-circle-fill text-danger" style="font-size: 5rem;"></i>
                    </div>
                    
                    <h1 class="display-4 mb-3" style="color: #dc3545;">Payment Failed</h1>
                    <p class="lead mb-4"><?php echo esc_html($reason); ?></p>
                    
                    <?php if ($code): ?>
                    <p class="mb-4"><strong>Error Code:</strong> <?php echo esc_html($code); ?></p>
                    <?php endif; ?>
                    
                    <div class="alert alert-warning">
                        <h5 class="alert-heading">Possible Reasons:</h5>
                        <ul class="text-start mb-0">
                            <li>Insufficient funds</li>
                            <li>Card declined by bank</li>
                            <li>Invalid card details</li>
                            <li>Network connectivity issue</li>
                        </ul>
                    </div>
                    
                    <div class="mt-5">
                        <a href="<?php echo home_url('/'); ?>" class="btn btn-lg btn-outline-secondary me-3" style="padding: 12px 30px;">
                            <i class="bi bi-house me-2"></i>Return to Home
                        </a>
                        <button onclick="window.history.back()" class="btn btn-lg" style="background: #1AA3A9; color: white; padding: 12px 30px;">
                            <i class="bi bi-arrow-left me-2"></i>Try Again
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>