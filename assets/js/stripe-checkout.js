/**
 * Enhanced Stripe Checkout JavaScript - FIXED VERSION
 *
 * @package LNMC Member Hub
 * @since 1.0.0
 */

(function($) {
    'use strict';

    // Debug logging function
    function debugLog(message, data = null) {
        if (window.lnmcDebug && window.console) {
            console.log('[LNMC Debug] ' + message, data);
        }
    }

    // Check if required libraries are loaded
    if (typeof $ === 'undefined') {
        console.error('jQuery is not loaded');
        return;
    }

    if (typeof Stripe === 'undefined') {
        console.error('Stripe.js is not loaded');
        return;
    }

    if (!lnmcStripe || !lnmcStripe.publishableKey) {
        console.error('Stripe configuration is missing');
        return;
    }

    debugLog('Stripe checkout initializing...', lnmcStripe);

    // Initialize Stripe
    const stripe = Stripe(lnmcStripe.publishableKey);

    /**
     * Show loading state for buttons
     */
    function showLoadingState(element, text) {
        if (element.length) {
            element.prop('disabled', true);
            if (text) {
                element.text(text);
            }
            element.addClass('lnmc-loading');
        }
    }

    /**
     * Hide loading state for buttons
     */
    function hideLoadingState(element, originalText) {
        if (element.length) {
            element.prop('disabled', false);
            if (originalText) {
                element.text(originalText);
            }
            element.removeClass('lnmc-loading');
        }
    }

    /**
     * Initialize Stripe checkout functionality
     */
    function initStripeCheckout() {
        debugLog('Initializing Stripe checkout functionality');

        // Handle membership form submission
        $(document).on('submit', '.lnmc-membership-form', function(e) {
            e.preventDefault();
            debugLog('Membership form submitted');
            
            const form = $(this);
            const submitButton = form.find('button[type="submit"], .lnmc-stripe-checkout-button').first();
            const originalText = submitButton.text() || 'Pay Now';
            
            // Validate form
            const email = form.find('[name="email"]').val();
            const amount = form.find('[name="amount"]').val();
            
            if (!email || !isValidEmail(email)) {
                showError('Please enter a valid email address');
                return;
            }
            
            if (!amount || amount <= 0) {
                showError('Please enter a valid payment amount');
                return;
            }

            showLoadingState(submitButton, 'Processing...');
            
            // Get form data
            const formData = {
                action: 'create_stripe_checkout_session',
                nonce: lnmcStripe.nonce,
                amount: parseInt(amount),
                email: email.trim(),
                plan_id: form.find('[name="plan_id"]').val() || ''
            };
            
            debugLog('Creating checkout session with data:', formData);
            
            // Create checkout session
            $.ajax({
                url: lnmcStripe.ajaxUrl,
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    debugLog('Checkout session response:', response);
                    
                    if (response.success && response.data.session_id) {
                        debugLog('Redirecting to Stripe checkout...');
                        // Redirect to Stripe Checkout
                        stripe.redirectToCheckout({
                            sessionId: response.data.session_id
                        }).then(function(result) {
                            if (result.error) {
                                debugLog('Stripe redirect error:', result.error);
                                showError('Payment error: ' + result.error.message);
                                hideLoadingState(submitButton, originalText);
                            }
                        });
                    } else {
                        debugLog('Checkout session failed:', response.data);
                        showError(response.data || 'Failed to create checkout session');
                        hideLoadingState(submitButton, originalText);
                    }
                },
                error: function(xhr, status, error) {
                    debugLog('AJAX error:', {status, error, response: xhr.responseText});
                    showError('Network error: ' + (error || 'Unable to process payment'));
                    hideLoadingState(submitButton, originalText);
                }
            });
        });
        
        // Handle direct checkout buttons
        $(document).on('click', '.lnmc-stripe-checkout-button', function(e) {
            e.preventDefault();
            
            const button = $(this);
            const amount = button.data('amount');
            const email = button.data('email') || '';
            const planId = button.data('plan-id') || '';
            const originalText = button.text() || 'Pay Now';
            
            debugLog('Direct checkout button clicked', {amount, email, planId});
            
            if (!amount || amount <= 0) {
                showError('Invalid payment amount');
                return;
            }
            
            // If email is not provided, prompt user
            let userEmail = email;
            if (!userEmail) {
                userEmail = prompt('Please enter your email address:');
                if (!userEmail) {
                    debugLog('User cancelled email prompt');
                    return;
                }
            }
            
            // Validate email
            if (!isValidEmail(userEmail)) {
                showError('Please enter a valid email address');
                return;
            }
            
            showLoadingState(button, 'Processing...');
            
            // Create checkout session
            $.ajax({
                url: lnmcStripe.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'create_stripe_checkout_session',
                    nonce: lnmcStripe.nonce,
                    amount: parseInt(amount),
                    email: userEmail.trim(),
                    plan_id: planId
                },
                dataType: 'json',
                success: function(response) {
                    debugLog('Direct checkout response:', response);
                    
                    if (response.success && response.data.session_id) {
                        debugLog('Redirecting to Stripe checkout...');
                        stripe.redirectToCheckout({
                            sessionId: response.data.session_id
                        }).then(function(result) {
                            if (result.error) {
                                debugLog('Stripe redirect error:', result.error);
                                showError('Payment error: ' + result.error.message);
                                hideLoadingState(button, originalText);
                            }
                        });
                    } else {
                        debugLog('Direct checkout failed:', response.data);
                        showError(response.data || 'Failed to create checkout session');
                        hideLoadingState(button, originalText);
                    }
                },
                error: function(xhr, status, error) {
                    debugLog('Direct checkout AJAX error:', {status, error, response: xhr.responseText});
                    showError('Network error: ' + (error || 'Unable to process payment'));
                    hideLoadingState(button, originalText);
                }
            });
        });

        // Handle customer portal access
        $(document).on('click', '.lnmc-customer-portal-button', function(e) {
            e.preventDefault();
            
            const button = $(this);
            const originalText = button.text() || 'Manage Subscription';
            
            debugLog('Customer portal button clicked');
            
            if (!lnmcStripe.customerPortalEnabled) {
                showError('Customer portal is not available');
                return;
            }
            
            showLoadingState(button, 'Loading...');
            
            // Create customer portal session
            $.ajax({
                url: lnmcStripe.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'create_stripe_customer_portal_session',
                    nonce: lnmcStripe.nonce
                },
                dataType: 'json',
                success: function(response) {
                    debugLog('Customer portal response:', response);
                    
                    if (response.success && response.data.url) {
                        debugLog('Redirecting to customer portal...');
                        window.location.href = response.data.url;
                    } else {
                        debugLog('Customer portal failed:', response.data);
                        showError(response.data || 'Failed to access customer portal');
                        hideLoadingState(button, originalText);
                    }
                },
                error: function(xhr, status, error) {
                    debugLog('Customer portal AJAX error:', {status, error, response: xhr.responseText});
                    showError('Network error: ' + (error || 'Unable to access portal'));
                    hideLoadingState(button, originalText);
                }
            });
        });
    }

    /**
     * Show error message
     */
    function showError(message) {
        debugLog('Showing error:', message);
        
        // Remove existing error messages
        $('.lnmc-error-message').remove();
        
        // Create error message element
        const errorDiv = $('<div class="lnmc-error-message">' + 
            '<div class="lnmc-notice lnmc-notice-error">' + 
                '<p>' + message + '</p>' + 
                '<button type="button" class="lnmc-notice-dismiss">&times;</button>' + 
            '</div>' + 
        '</div>');
        
        // Insert error message
        $('body').prepend(errorDiv);
        
        // Auto-remove after 10 seconds
        setTimeout(function() {
            errorDiv.fadeOut(function() {
                $(this).remove();
            });
        }, 10000);
        
        // Dismiss button
        errorDiv.find('.lnmc-notice-dismiss').on('click', function() {
            errorDiv.remove();
        });
    }

    /**
     * Show success message
     */
    function showSuccess(message) {
        debugLog('Showing success:', message);
        
        // Remove existing success messages
        $('.lnmc-success-message').remove();
        
        // Create success message element
        const successDiv = $('<div class="lnmc-success-message">' + 
            '<div class="lnmc-notice lnmc-notice-success">' + 
                '<p>' + message + '</p>' + 
                '<button type="button" class="lnmc-notice-dismiss">&times;</button>' + 
            '</div>' + 
        '</div>');
        
        // Insert success message
        $('body').prepend(successDiv);
        
        // Auto-remove after 5 seconds
        setTimeout(function() {
            successDiv.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
        
        // Dismiss button
        successDiv.find('.lnmc-notice-dismiss').on('click', function() {
            successDiv.remove();
        });
    }

    /**
     * Validate email address
     */
    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        debugLog('Document ready, initializing Stripe checkout');
        
        // Enable debug mode for development
        window.lnmcDebug = true;
        
        // Initialize Stripe checkout
        initStripeCheckout();
        
        // Handle success page
        if (window.location.search.includes('session_id=')) {
            const urlParams = new URLSearchParams(window.location.search);
            const sessionId = urlParams.get('session_id');
            
            if (sessionId) {
                showSuccess('Payment successful! Welcome to LNMC membership.');
                
                // Store session ID
                if (typeof sessionStorage !== 'undefined') {
                    sessionStorage.setItem('lnmc_last_session_id', sessionId);
                }
            }
        }
        
        // Handle cancel page
        if (window.location.pathname.includes('membership-cancel')) {
            showError('Payment was cancelled. Please try again.');
        }

        // Handle customer portal return
        if (window.location.search.includes('portal_return=')) {
            showSuccess('Your subscription has been updated successfully.');
        }
    });
    
})(jQuery);
