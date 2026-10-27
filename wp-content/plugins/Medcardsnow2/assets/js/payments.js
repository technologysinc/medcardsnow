// (function($) {
//     'use strict';

//     class OMC_Payments {
//         constructor() {
//             this.settings = window.OMC?.settings || {};
//             this.ajaxURL = window.OMC?.ajax_url || '';
//             this.nonce = window.OMC?.nonce || '';
//             this.initialized = false;
//             this.init();
//         }

//         init() {
//             if (this.initialized) return;
            
//             if (document.readyState === 'loading') {
//                 document.addEventListener('DOMContentLoaded', () => this.setup());
//             } else {
//                 this.setup();
//             }
//         }

//         setup() {
//             console.log('OMC Payments initialized - Accept.js Payment Method');
//             this.setupEventListeners();
//             this.setupCardFormatting();
//             this.initialized = true;
//         }

//         setupEventListeners() {
//             // Listen for Accept.js payment events (triggered from omc.js)
//             $(document).on('omc_process_token_payment', (e, tokenData, amount) => {
//                 this.processTokenizedPayment(tokenData, amount);
//             });

//             // Listen for emergency fallback (admin only)
//             $(document).on('omc_emergency_payment', (e, paymentData, amount) => {
//                 this.processEmergencyPayment(paymentData, amount);
//             });

//             // Coupon application
//             $(document).on('click', '#applyCoupon', (e) => {
//                 e.preventDefault();
//                 this.applyCoupon();
//             });

//             // Initialize Accept.js if not loaded
//             this.checkAcceptJS();
//         }

//         setupCardFormatting() {
//             // Formatting for card number display only (not for processing)
//             $('#dc_number').on('input', function() {
//                 let value = $(this).val().replace(/\D/g, '');
//                 if (value.length > 16) value = value.substring(0, 16);
//                 let formatted = value.replace(/(\d{4})/g, '$1 ').trim();
//                 $(this).val(formatted);
//             });

//             $('#dc_exp').on('input', function() {
//                 let value = $(this).val().replace(/\D/g, '');
//                 if (value.length >= 2) {
//                     value = value.substring(0, 2) + '/' + value.substring(2, 4);
//                 }
//                 $(this).val(value.substring(0, 5));
//             });

//             $('#dc_cvv').on('input', function() {
//                 let value = $(this).val().replace(/\D/g, '');
//                 $(this).val(value.substring(0, 4));
//             });
//         }

//         checkAcceptJS() {
//             // Check if Accept.js is properly loaded
//             if (!window.Accept || typeof window.Accept.dispatchData !== 'function') {
//                 console.warn('Accept.js not loaded properly');
                
//                 // Try to reload Accept.js
//                 setTimeout(() => {
//                     if (!window.Accept) {
//                         console.error('Accept.js failed to load. Payments may not work.');
//                         this.showToast('Payment system loading... Please refresh if issues persist.', 'warning');
//                     }
//                 }, 3000);
//             } else {
//                 console.log('Accept.js loaded successfully');
//             }
//         }

//         async processTokenizedPayment(tokenData, amount) {
//             const $btn = $('#omcPayNow');
//             const originalText = $btn.text();
            
//             try {
//                 // Validate token data
//                 if (!tokenData || !tokenData.opaque || !tokenData.descriptor) {
//                     throw new Error('Invalid payment token data');
//                 }

//                 if (amount <= 0) {
//                     throw new Error('Invalid payment amount');
//                 }

//                 console.log('Processing tokenized payment...', { tokenData, amount });

//                 $btn.prop('disabled', true).text('Processing...');

//                 // Process tokenized payment through WordPress
//                 const result = await this.sendTokenizedPayment(tokenData, amount);
                
//                 if (result.success) {
//                     this.showToast('Payment successful! Transaction ID: ' + result.transaction_id, 'success');
                    
//                     // Get order data
//                     const orderData = this.getOrderData();
                    
//                     // Notify admin
//                     await this.notifyAdmin(orderData, result.transaction_id);
                    
//                     // Optionally redirect
//                     // this.redirectToSuccess();
//                 } else {
//                     throw new Error(result.message || 'Payment failed');
//                 }

//             } catch (error) {
//                 console.error('Payment Error:', error);
//                 this.showToast(error.message, 'error');
                
//                 // Check if it's a network error and show retry option
//                 if (error.message.includes('Network error') || error.message.includes('Gateway error')) {
//                     this.showRetryOption();
//                 }
//             } finally {
//                 $btn.prop('disabled', false).text('Proceed to Payment');
//             }
//         }

//         sendTokenizedPayment(tokenData, amount) {
//             return new Promise((resolve, reject) => {
//                 console.log('Sending tokenized payment to server...');

//                 $.ajax({
//                     url: this.ajaxURL,
//                     type: 'POST',
//                     data: {
//                         action: 'omc_process_payment',
//                         nonce: this.nonce,
//                         opaque: tokenData.opaque,
//                         descriptor: tokenData.descriptor,
//                         amount: amount
//                     },
//                     success: (response) => {
//                         console.log('Server response:', response);
//                         if (response.success) {
//                             resolve(response.data);
//                         } else {
//                             // Format detailed error message
//                             let errorMsg = response.data?.message || 'Payment failed';
                            
//                             // Add error codes if available
//                             if (response.data?.debug_info) {
//                                 const debug = response.data.debug_info;
//                                 if (debug.messages?.message?.[0]?.text) {
//                                     errorMsg = debug.messages.message[0].text;
//                                 }
//                             }
                            
//                             reject(new Error(errorMsg));
//                         }
//                     },
//                     error: (xhr, status, error) => {
//                         // Handle specific HTTP status codes
//                         if (xhr.status === 402) {
//                             reject(new Error('Payment was declined by the bank. Please check your card details.'));
//                         } else if (xhr.status === 500) {
//                             reject(new Error('Server error. Please try again or contact support.'));
//                         } else if (xhr.status === 403) {
//                             reject(new Error('Payment processing is temporarily unavailable.'));
//                         } else {
//                             reject(new Error('Network error: ' + error));
//                         }
//                     }
//                 });
//             });
//         }

//         // EMERGENCY PAYMENT METHOD (Admin only - PCI violation warning!)
//         async processEmergencyPayment(paymentData, amount) {
//             // SECURITY WARNING: This method violates PCI compliance
//             // Only use for emergency debugging by administrators
            
//             if (!this.isAdminUser()) {
//                 this.showToast('Unauthorized. Emergency payments require administrator access.', 'error');
//                 return;
//             }
            
//             const $btn = $('#omcPayNow');
//             const originalText = $btn.text();
            
//             try {
//                 console.warn('WARNING: Using emergency direct payment (PCI violation)');
                
//                 $btn.prop('disabled', true).text('Processing Emergency Payment...');

//                 // Process emergency payment
//                 const result = await this.sendEmergencyPayment(paymentData, amount);
                
//                 if (result.success) {
//                     this.showToast('EMERGENCY PAYMENT: Transaction ID: ' + result.transaction_id, 'warning');
                    
//                     // Log this security violation
//                     console.error('SECURITY WARNING: Direct card processing used!', paymentData);
//                 } else {
//                     throw new Error(result.message || 'Emergency payment failed');
//                 }

//             } catch (error) {
//                 console.error('Emergency Payment Error:', error);
//                 this.showToast('Emergency payment failed: ' + error.message, 'error');
//             } finally {
//                 $btn.prop('disabled', false).text('Proceed to Payment');
//             }
//         }

//         sendEmergencyPayment(paymentData, amount) {
//             return new Promise((resolve, reject) => {
//                 console.warn('Sending emergency payment (PCI VIOLATION)');

//                 $.ajax({
//                     url: this.ajaxURL,
//                     type: 'POST',
//                     data: {
//                         action: 'omc_process_direct_payment',
//                         nonce: this.nonce,
//                         card_data: JSON.stringify(paymentData),
//                         amount: amount
//                     },
//                     success: (response) => {
//                         console.log('Emergency response:', response);
//                         if (response.success) {
//                             resolve(response.data);
//                         } else {
//                             const errorMsg = response.data?.debug_info ? 
//                                 JSON.stringify(response.data.debug_info) : 
//                                 response.data?.message || 'Emergency payment failed';
//                             reject(new Error(errorMsg));
//                         }
//                     },
//                     error: (xhr, status, error) => {
//                         reject(new Error('Emergency network error: ' + error));
//                     }
//                 });
//             });
//         }

//         isAdminUser() {
//             // Check if current user is admin
//             return window.OMC?.current_user?.is_logged_in && 
//                    (window.OMC.current_user.roles?.includes('administrator') || 
//                     window.OMC.current_user.caps?.administrator);
//         }

//         validatePaymentForm() {
//             // This validation is for display purposes only
//             // Real validation happens in omc.js before tokenization
            
//             const name = $('#dc_name').val().trim();
//             const number = $('#dc_number').val().replace(/\s/g, '');
//             const exp = $('#dc_exp').val().trim();
//             const cvv = $('#dc_cvv').val().trim();
//             const zip = $('#billing_zip').val().trim();

//             // Clear previous errors
//             $('.payment-error').remove();
//             $('#dc_num_err').addClass('d-none');

//             let isValid = true;

//             if (!name) {
//                 this.showFieldError('#dc_name', 'Name on card is required');
//                 isValid = false;
//             }

//             if (!this.validateCardNumber(number)) {
//                 $('#dc_num_err').removeClass('d-none');
//                 isValid = false;
//             }

//             if (!this.validateExpiration(exp)) {
//                 this.showFieldError('#dc_exp', 'Valid expiration date required (MM/YY)');
//                 isValid = false;
//             }

//             if (!this.validateCVV(cvv)) {
//                 this.showFieldError('#dc_cvv', 'Valid CVV required');
//                 isValid = false;
//             }

//             if (!zip || zip.length < 3) {
//                 this.showFieldError('#billing_zip', 'Valid zip code required');
//                 isValid = false;
//             }

//             return isValid;
//         }

//         validateCardNumber(number) {
//             const cleanNumber = number.replace(/\s/g, '');
//             if (cleanNumber.length < 13 || cleanNumber.length > 19) {
//                 return false;
//             }
//             return this.luhnCheck(cleanNumber);
//         }

//         validateExpiration(exp) {
//             if (!/^\d{2}\/\d{2}$/.test(exp)) return false;
//             const [month, year] = exp.split('/');
//             const expMonth = parseInt(month, 10);
//             const expYear = parseInt('20' + year, 10);
            
//             if (expMonth < 1 || expMonth > 12) return false;

//             const now = new Date();
//             const currentYear = now.getFullYear();
//             const currentMonth = now.getMonth() + 1;

//             if (expYear < currentYear) return false;
//             if (expYear === currentYear && expMonth < currentMonth) return false;

//             return true;
//         }

//         validateCVV(cvv) {
//             return /^\d{3,4}$/.test(cvv);
//         }

//         showFieldError(selector, message) {
//             const $field = $(selector);
//             $field.addClass('is-invalid');
            
//             if (!$field.next('.payment-error').length) {
//                 $field.after(`<div class="payment-error invalid-feedback">${message}</div>`);
//             }
//         }

//         getPaymentFormData() {
//             // WARNING: This collects raw card data - ONLY for emergency/admin use
//             return {
//                 name: $('#dc_name').val().trim(),
//                 number: $('#dc_number').val().replace(/\s/g, ''),
//                 exp: $('#dc_exp').val().trim(),
//                 cvv: $('#dc_cvv').val().trim(),
//                 zip: $('#billing_zip').val().trim()
//             };
//         }

//         getOrderData() {
//             try {
//                 // Try to get data from global app state (from omc.js)
//                 if (window.app) {
//                     return {
//                         total: window.app.getTotal ? window.app.getTotal() : this.getTotalAmount(),
//                         state: window.app.state || '',
//                         plan: window.app.plan || null,
//                         duration: window.app.duration || null,
//                         addHardCopy: window.app.addHardCopy || false,
//                         addIdCard: window.app.addIdCard || false,
//                         user: window.OMC?.current_user || {}
//                     };
//                 }
                
//                 // Fallback to DOM elements
//                 return {
//                     total: this.getTotalAmount(),
//                     state: $('#stateBadge').text().replace('—', '').trim() || '',
//                     plan: this.getSelectedPlan(),
//                     duration: this.getSelectedDuration(),
//                     addHardCopy: $('#addIdYes').is(':checked'),
//                     addIdCard: $('#wantsIdCard').is(':checked'),
//                     user: window.OMC?.current_user || {}
//                 };
//             } catch (error) {
//                 console.error('Error getting order data:', error);
//                 return {
//                     total: this.getTotalAmount(),
//                     user: window.OMC?.current_user || {}
//                 };
//             }
//         }

//         getTotalAmount() {
//             const totalText = $('#orderTotal').text() || '$0';
//             return parseFloat(totalText.replace('$', '')) || 0;
//         }

//         getSelectedPlan() {
//             const activePlan = $('#plansGrid .option-card.active');
//             return activePlan.length ? {
//                 name: activePlan.data('plan'),
//                 price: parseFloat(activePlan.data('price')) || 0
//             } : null;
//         }

//         getSelectedDuration() {
//             const activeDuration = $('#durationGrid .option-card.active');
//             return activeDuration.length ? {
//                 name: activeDuration.data('plan'),
//                 price: parseFloat(activeDuration.data('price')) || 0,
//                 years: parseInt(activeDuration.data('years')) || 1
//             } : null;
//         }

//         applyCoupon() {
//             const code = $('#couponCode').val().trim().toUpperCase();
//             const $msg = $('#couponMsg');

//             if (code === 'SAVE10') {
//                 this.applyDiscount(100);
//                 this.showCouponMessage('Coupon applied: 100% OFF', 'success', $msg);
//             } else if (code) {
//                 this.showCouponMessage('Invalid coupon code', 'error', $msg);
//             } else {
//                 this.showCouponMessage('Please enter a coupon code', 'error', $msg);
//             }
//         }

//         applyDiscount(percent) {
//             const currentTotal = this.getTotalAmount();
//             const discount = (currentTotal * percent) / 100;
//             const newTotal = Math.max(0, currentTotal - discount);
//             $('#orderTotal').text('$' + newTotal.toFixed(2));
            
//             // Update any global state if available
//             if (window.app && window.app.computeOrder) {
//                 window.app.computeOrder();
//             }
//         }

//         async notifyAdmin(orderData, transactionId) {
//             try {
//                 await $.post(this.ajaxURL, {
//                     action: 'omc_notify_admin',
//                     nonce: this.nonce,
//                     payload: JSON.stringify({
//                         ...orderData,
//                         transaction_id: transactionId,
//                         timestamp: new Date().toISOString(),
//                         payment_method: 'Authorize.Net (Tokenized)'
//                     })
//                 });
//                 console.log('Admin notification sent');
//             } catch (error) {
//                 console.error('Failed to notify admin:', error);
//                 // Don't show error to user - admin notification failure shouldn't block user
//             }
//         }

//         redirectToSuccess() {
//             // Optional: Redirect to thank you page
//             setTimeout(() => {
//                 window.location.href = '/thank-you';
//             }, 2000);
//         }

//         showRetryOption() {
//             // Show retry option for network errors
//             this.showToast(
//                 '<div>Network error. <button class="btn btn-sm btn-light ms-2" id="retryPayment">Retry</button></div>',
//                 'warning'
//             );
            
//             $('#retryPayment').on('click', () => {
//                 window.location.reload();
//             });
//         }

//         luhnCheck(cardNumber) {
//             let sum = 0;
//             let isEven = false;
//             for (let i = cardNumber.length - 1; i >= 0; i--) {
//                 let digit = parseInt(cardNumber.charAt(i), 10);
//                 if (isEven) {
//                     digit *= 2;
//                     if (digit > 9) digit -= 9;
//                 }
//                 sum += digit;
//                 isEven = !isEven;
//             }
//             return sum % 10 === 0;
//         }

//         showToast(message, type = 'success') {
//             // Remove any existing toasts
//             $('.omc-toast').remove();
            
//             const toast = $(`
//                 <div class="omc-toast position-fixed top-0 end-0 m-3" style="z-index: 99999;">
//                     <div class="alert alert-${type === 'success' ? 'success' : (type === 'error' ? 'danger' : 'warning')} alert-dismissible fade show shadow">
//                         ${message}
//                         <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
//                     </div>
//                 </div>
//             `);
            
//             $('body').append(toast);
            
//             // Auto-remove after 5 seconds
//             setTimeout(() => toast.remove(), 5000);
//         }

//         showCouponMessage(message, type, $element) {
//             if ($element) {
//                 $element.text(message)
//                     .removeClass('text-success text-danger')
//                     .addClass(type === 'success' ? 'text-success' : 'text-danger')
//                     .removeClass('d-none');
                    
//                 // Auto-hide after 5 seconds
//                 setTimeout(() => {
//                     $element.addClass('d-none');
//                 }, 5000);
//             }
//         }
//     }

//     // Initialize only if we're on the payment page
//     function shouldInitializePayments() {
//         // Check if we're on a page with payment elements
//         return $('#omcPayNow').length > 0 || $('#step-9').length > 0;
//     }

//     // Initialize
//     if (shouldInitializePayments()) {
//         if (document.readyState === 'loading') {
//             document.addEventListener('DOMContentLoaded', () => {
//                 window.OMC_Payments = new OMC_Payments();
//             });
//         } else {
//             window.OMC_Payments = new OMC_Payments();
//         }
//     }

// })(jQuery);