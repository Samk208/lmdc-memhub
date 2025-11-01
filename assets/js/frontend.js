/**
 * LNMC Member Hub - Frontend JavaScript
 *
 * @package LNMC_Member_Hub
 * @since 1.0.0
 */

(function($) {
    'use strict';

    // Main LNMC Member Hub object
    window.LNMCMemberHub = {
        
        // Configuration
        config: {
            ajaxUrl: '',
            nonce: '',
            stripePublishableKey: '',
            currency: 'usd',
            locale: 'auto'
        },

        // Initialize the plugin
        init: function() {
            this.bindEvents();
            this.initStripe();
            this.initAnimations();
            this.initAccessibility();
        },

        // Bind event listeners
        bindEvents: function() {
            // Membership form submission
            $(document).on('submit', '.lnmc-membership-form-inner', this.handleMembershipFormSubmit);
            
            // Subscribe button clicks
            $(document).on('click', '.lnmc-subscribe-button', this.handleSubscribeClick);
            
            // Member badge interactions
            $(document).on('click', '.lnmc-member-badge', this.handleBadgeClick);
            
            // Form validation
            $(document).on('blur', '.lnmc-form-input', this.validateField);
            $(document).on('input', '.lnmc-form-input', this.clearFieldError);
            
            // Smooth scrolling for anchor links
            $(document).on('click', 'a[href^="#"]', this.handleSmoothScroll);
            
            // Loading states
            $(document).on('click', '.lnmc-submit-button', function() {
                LNMCMemberHub.showLoadingState($(this));
            });
        },

        // Initialize Stripe
        initStripe: function() {
            if (typeof Stripe !== 'undefined' && this.config.stripePublishableKey) {
                this.stripe = Stripe(this.config.stripePublishableKey, {
                    locale: this.config.locale
                });
            }
        },

        // Initialize animations
        initAnimations: function() {
            // Intersection Observer for fade-in animations
            if ('IntersectionObserver' in window) {
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            entry.target.classList.add('lnmc-animate-in');
                        }
                    });
                }, {
                    threshold: 0.1,
                    rootMargin: '0px 0px -50px 0px'
                });

                // Observe elements for animation
                document.querySelectorAll('.lnmc-member-hub').forEach(el => {
                    observer.observe(el);
                });
            }
        },

        // Initialize accessibility features
        initAccessibility: function() {
            // Add ARIA labels and roles
            $('.lnmc-member-badge').attr('role', 'status');
            $('.lnmc-membership-form').attr('role', 'form');
            $('.lnmc-submit-button').attr('aria-describedby', 'payment-info');
            
            // Keyboard navigation
            $('.lnmc-submit-button, .lnmc-subscribe-button').on('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    $(this).click();
                }
            });
        },

        // Handle membership form submission
        handleMembershipFormSubmit: function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const $submitButton = $form.find('.lnmc-submit-button');
            const $errorContainer = $form.find('.lnmc-form-error');
            
            // Clear previous errors
            $errorContainer.empty().hide();
            
            // Validate form
            if (!LNMCMemberHub.validateForm($form)) {
                return false;
            }
            
            // Show loading state
            LNMCMemberHub.showLoadingState($submitButton);
            
            // Get form data
            const formData = {
                action: 'create_stripe_checkout_session',
                email: $form.find('#lnmc-email').val(),
                amount: $form.find('#lnmc-amount').val(),
                currency: LNMCMemberHub.config.currency,
                nonce: LNMCMemberHub.config.nonce
            };
            
            // Create Stripe checkout session
            $.ajax({
                url: LNMCMemberHub.config.ajaxUrl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success && response.data.session_id) {
                        LNMCMemberHub.redirectToStripeCheckout(response.data.session_id);
                    } else {
                        LNMCMemberHub.showError($errorContainer, response.data.message || 'Payment setup failed. Please try again.');
                    }
                },
                error: function(xhr, status, error) {
                    LNMCMemberHub.showError($errorContainer, 'Network error. Please check your connection and try again.');
                },
                complete: function() {
                    LNMCMemberHub.hideLoadingState($submitButton);
                }
            });
        },

        // Validate form fields
        validateForm: function($form) {
            let isValid = true;
            const $errorContainer = $form.find('.lnmc-form-error');
            
            // Validate email
            const email = $form.find('#lnmc-email').val();
            if (!email || !LNMCMemberHub.isValidEmail(email)) {
                LNMCMemberHub.showFieldError($form.find('#lnmc-email'), 'Please enter a valid email address.');
                isValid = false;
            }
            
            // Validate amount
            const amount = $form.find('#lnmc-amount').val();
            if (!amount || isNaN(amount) || amount <= 0) {
                LNMCMemberHub.showFieldError($form.find('#lnmc-amount'), 'Please enter a valid amount.');
                isValid = false;
            }
            
            return isValid;
        },

        // Validate individual field
        validateField: function() {
            const $field = $(this);
            const fieldType = $field.attr('type');
            const value = $field.val();
            
            // Clear previous error
            LNMCMemberHub.clearFieldError($field);
            
            // Validate based on field type
            if (fieldType === 'email' && value && !LNMCMemberHub.isValidEmail(value)) {
                LNMCMemberHub.showFieldError($field, 'Please enter a valid email address.');
            }
        },

        // Clear field error
        clearFieldError: function() {
            const $field = $(this);
            $field.removeClass('lnmc-field-error');
            $field.siblings('.lnmc-field-error-message').remove();
        },

        // Show field error
        showFieldError: function($field, message) {
            $field.addClass('lnmc-field-error');
            $field.after('<div class="lnmc-field-error-message">' + message + '</div>');
        },

        // Show general error
        showError: function($container, message) {
            $container.html('<div class="lnmc-error-message">' + message + '</div>').show();
        },

        // Show loading state
        showLoadingState: function($button) {
            $button.prop('disabled', true)
                   .html('<span class="lnmc-loading"></span> Processing...')
                   .addClass('lnmc-loading');
        },

        // Hide loading state
        hideLoadingState: function($button) {
            $button.prop('disabled', false)
                   .html('Pay with Card')
                   .removeClass('lnmc-loading');
        },

        // Redirect to Stripe checkout
        redirectToStripeCheckout: function(sessionId) {
            if (this.stripe) {
                this.stripe.redirectToCheckout({
                    sessionId: sessionId
                }).then(function(result) {
                    if (result.error) {
                        console.error('Stripe checkout error:', result.error);
                        alert('Payment setup failed. Please try again.');
                    }
                });
            } else {
                // Fallback redirect
                window.location.href = '/wp-admin/admin-ajax.php?action=stripe_checkout&session_id=' + sessionId;
            }
        },

        // Handle subscribe button click
        handleSubscribeClick: function(e) {
            const $button = $(this);
            const isLoggedIn = $button.hasClass('lnmc-manage-billing');
            
            if (!isLoggedIn) {
                // Track analytics event
                if (typeof gtag !== 'undefined') {
                    gtag('event', 'click', {
                        'event_category': 'Membership',
                        'event_label': 'Subscribe Button'
                    });
                }
            }
        },

        // Handle badge click
        handleBadgeClick: function(e) {
            e.preventDefault();
            
            const $badge = $(this);
            const status = $badge.text().trim();
            
            // Show tooltip or modal with status information
            LNMCMemberHub.showStatusTooltip($badge, status);
        },

        // Show status tooltip
        showStatusTooltip: function($element, status) {
            const tooltip = $('<div class="lnmc-tooltip">' + status + '</div>');
            
            $element.append(tooltip);
            
            // Position tooltip
            const elementRect = $element[0].getBoundingClientRect();
            tooltip.css({
                position: 'absolute',
                top: elementRect.bottom + 5,
                left: elementRect.left + (elementRect.width / 2) - (tooltip.width() / 2),
                zIndex: 1000
            });
            
            // Remove tooltip after 3 seconds
            setTimeout(() => {
                tooltip.remove();
            }, 3000);
        },

        // Handle smooth scrolling
        handleSmoothScroll: function(e) {
            const href = $(this).attr('href');
            
            if (href.length > 1) {
                e.preventDefault();
                
                const target = $(href);
                if (target.length) {
                    $('html, body').animate({
                        scrollTop: target.offset().top - 100
                    }, 800);
                }
            }
        },

        // Email validation
        isValidEmail: function(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        },

        // Format currency
        formatCurrency: function(amount, currency = 'USD') {
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: currency
            }).format(amount / 100);
        },

        // Show notification
        showNotification: function(message, type = 'info') {
            const notification = $('<div class="lnmc-notification lnmc-notification-' + type + '">' + message + '</div>');
            
            $('body').append(notification);
            
            // Animate in
            setTimeout(() => {
                notification.addClass('lnmc-notification-show');
            }, 100);
            
            // Remove after 5 seconds
            setTimeout(() => {
                notification.removeClass('lnmc-notification-show');
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 5000);
        },

        // Track analytics event
        trackEvent: function(category, action, label = null) {
            if (typeof gtag !== 'undefined') {
                gtag('event', action, {
                    'event_category': category,
                    'event_label': label
                });
            }
            
            // Facebook Pixel
            if (typeof fbq !== 'undefined') {
                fbq('track', action, {
                    content_category: category,
                    content_name: label
                });
            }
        },

        // Update member dashboard
        updateDashboard: function() {
            const $dashboard = $('.lnmc-member-dashboard');
            
            if ($dashboard.length) {
                $.ajax({
                    url: LNMCMemberHub.config.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'get_member_dashboard_data',
                        nonce: LNMCMemberHub.config.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            LNMCMemberHub.renderDashboardData(response.data);
                        }
                    }
                });
            }
        },

        // Render dashboard data
        renderDashboardData: function(data) {
            // Update membership status
            if (data.membership_status) {
                $('.lnmc-membership-status').text(data.membership_status);
            }
            
            // Update next payment date
            if (data.next_payment_date) {
                $('.lnmc-next-payment').text(data.next_payment_date);
            }
            
            // Update recent activity
            if (data.recent_activity) {
                const $activityList = $('.lnmc-activity-list');
                $activityList.empty();
                
                data.recent_activity.forEach(activity => {
                    $activityList.append(`
                        <div class="lnmc-activity-item">
                            <span class="lnmc-activity-icon">${activity.icon}</span>
                            <div class="lnmc-activity-content">
                                <div class="lnmc-activity-text">${activity.text}</div>
                                <div class="lnmc-activity-time">${activity.time}</div>
                            </div>
                        </div>
                    `);
                });
            }
        }
    };

    // Initialize when DOM is ready
    $(document).ready(function() {
        LNMCMemberHub.init();
    });

    // Expose to global scope
    window.LNMCMemberHub = LNMCMemberHub;

})(jQuery);

// Additional CSS for JavaScript functionality
const additionalStyles = `
    .lnmc-field-error {
        border-color: #e74c3c !important;
        box-shadow: 0 0 0 3px rgba(231, 76, 60, 0.1) !important;
    }
    
    .lnmc-field-error-message {
        color: #e74c3c;
        font-size: 0.85rem;
        margin-top: 0.25rem;
        font-weight: 500;
    }
    
    .lnmc-error-message {
        background: #f8d7da;
        border: 1px solid #f5c6cb;
        border-radius: 6px;
        padding: 1rem;
        color: #721c24;
        margin: 1rem 0;
        font-weight: 500;
    }
    
    .lnmc-loading {
        display: inline-block;
        width: 16px;
        height: 16px;
        border: 2px solid #f3f3f3;
        border-top: 2px solid #3498db;
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin-right: 8px;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    .lnmc-tooltip {
        background: #2c3e50;
        color: white;
        padding: 8px 12px;
        border-radius: 6px;
        font-size: 0.85rem;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        pointer-events: none;
    }
    
    .lnmc-notification {
        position: fixed;
        top: 20px;
        right: 20px;
        background: #fff;
        border: 1px solid #e1e8ed;
        border-radius: 8px;
        padding: 1rem 1.5rem;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
        z-index: 10000;
        transform: translateX(100%);
        transition: transform 0.3s ease;
        max-width: 300px;
    }
    
    .lnmc-notification-show {
        transform: translateX(0);
    }
    
    .lnmc-notification-success {
        border-left: 4px solid #27ae60;
    }
    
    .lnmc-notification-error {
        border-left: 4px solid #e74c3c;
    }
    
    .lnmc-notification-warning {
        border-left: 4px solid #f39c12;
    }
    
    .lnmc-notification-info {
        border-left: 4px solid #3498db;
    }
    
    .lnmc-animate-in {
        animation: fadeInUp 0.6s ease-out;
    }
    
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
`;

// Inject additional styles
const styleSheet = document.createElement('style');
styleSheet.textContent = additionalStyles;
document.head.appendChild(styleSheet);
