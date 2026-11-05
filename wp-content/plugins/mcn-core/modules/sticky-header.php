<?php
/**
 * MCN Core module: Sticky header
 * Header behaviour script.
 *
 * Moved unchanged from the child theme's functions.php on 2026-10-30.
 * Loaded by MCN Core; switch it on or off under Tools > MCN Modules.
 */
if (!defined('ABSPATH')) exit;


/* =====================================================
   END FINAL CALIFORNIA FULL TOTAL + PLAN META OVERRIDE
   ===================================================== */


/*
 * LEGAL LETTERS MULTIPAGE BACKUP FIX:
 * Legal letters are now persisted across Gravity Forms pages through:
 * - sessionStorage key: medcards_ca_legal_letters_selected
 * - hidden input: medcards_legal_letters_backup
 *
 * The final California override reads this backup field and includes
 * legal_total inside payment_amount and payment_plan_.
 */


function func_sticky_header() {
    ?>
    <div class="nav-item phnumber d-none d-md-block" aria-label="Call us">
        <a class="nav-link" href="tel:+16263232358" aria-label="Call (626) 323-2358">
            (626) 323-2358
        </a>
    </div>
	
	<script>
        document.addEventListener('DOMContentLoaded', function () {
            const phoneNumber = document.querySelector('.phnumber');

            if (!phoneNumber) {
                return;
            }

            const scrollOffset = 50;

            function handlePhoneScroll() {
                if (window.scrollY > scrollOffset) {
                    phoneNumber.classList.add('sticky-phone');
                } else {
                    phoneNumber.classList.remove('sticky-phone');
                }
            }

            // Initial state
            handlePhoneScroll();

            // Handle scroll
            window.addEventListener('scroll', handlePhoneScroll, {
                passive: true
            });
        });
    </script>
    <?php
}

add_action('wp_footer', 'func_sticky_header');
