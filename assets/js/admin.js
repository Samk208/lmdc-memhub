/**
 * LNMC Member Hub Admin JavaScript
 *
 * @package LNMC_Member_Hub
 * @since 1.0.0
 */

(function($) {
    'use strict';

    // Main admin object
    var LNMCAdmin = {
        
        /**
         * Initialize admin functionality
         */
        init: function() {
            this.bindEvents();
            this.initTooltips();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Webhook URL validation
            $('#lnmc_member_hub_webhook_url').on('blur', this.validateWebhookUrl);
            
            // Membership mode change
            $('#lnmc_member_hub_membership_mode').on('change', this.handleMembershipModeChange);
            
            // Form submission
            $('form').on('submit', this.handleFormSubmit);
        },

        /**
         * Initialize tooltips
         */
        initTooltips: function() {
            // Add tooltips to form fields if needed
            $('.description').each(function() {
                var $this = $(this);
                var $field = $this.prev('input, select, textarea');
                
                if ($field.length) {
                    $field.attr('title', $this.text());
                }
            });
        },

        /**
         * Validate webhook URL
         */
        validateWebhookUrl: function() {
            var $field = $(this);
            var url = $field.val();
            
            if (url && !LNMCAdmin.isValidUrl(url)) {
                LNMCAdmin.showError($field, 'Please enter a valid URL');
                return false;
            } else {
                LNMCAdmin.clearError($field);
                return true;
            }
        },

        /**
         * Handle membership mode change
         */
        handleMembershipModeChange: function() {
            var mode = $(this).val();
            var $webhookField = $('#lnmc_member_hub_webhook_url');
            
            // Show/hide webhook field based on mode
            if (mode === 'enterprise') {
                $webhookField.closest('tr').show();
                $webhookField.attr('required', 'required');
            } else {
                $webhookField.closest('tr').hide();
                $webhookField.removeAttr('required');
            }
        },

        /**
         * Handle form submission
         */
        handleFormSubmit: function(e) {
            var $form = $(this);
            var isValid = true;
            
            // Validate required fields
            $form.find('[required]').each(function() {
                var $field = $(this);
                if (!$field.val()) {
                    LNMCAdmin.showError($field, 'This field is required');
                    isValid = false;
                }
            });
            
            // Validate webhook URL if present
            var $webhookField = $('#lnmc_member_hub_webhook_url');
            if ($webhookField.val() && !LNMCAdmin.isValidUrl($webhookField.val())) {
                LNMCAdmin.showError($webhookField, 'Please enter a valid URL');
                isValid = false;
            }
            
            if (!isValid) {
                e.preventDefault();
                LNMCAdmin.showNotice('Please correct the errors above.', 'error');
            }
        },

        /**
         * Check if URL is valid
         */
        isValidUrl: function(url) {
            try {
                new URL(url);
                return true;
            } catch (e) {
                return false;
            }
        },

        /**
         * Show error message for a field
         */
        showError: function($field, message) {
            var $error = $field.siblings('.error-message');
            
            if (!$error.length) {
                $error = $('<span class="error-message" style="color: #dc3232; font-size: 13px; margin-top: 5px; display: block;"></span>');
                $field.after($error);
            }
            
            $error.text(message);
            $field.addClass('error');
        },

        /**
         * Clear error message for a field
         */
        clearError: function($field) {
            $field.siblings('.error-message').remove();
            $field.removeClass('error');
        },

        /**
         * Show admin notice
         */
        showNotice: function(message, type) {
            type = type || 'info';
            
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            
            // Remove existing notices
            $('.notice').remove();
            
            // Add new notice
            $('.wrap h1').after($notice);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $notice.fadeOut();
            }, 5000);
        },

        /**
         * Test webhook URL
         */
        testWebhook: function() {
            var $button = $('#test-webhook');
            var $field = $('#lnmc_member_hub_webhook_url');
            var url = $field.val();
            
            if (!url) {
                LNMCAdmin.showNotice('Please enter a webhook URL first.', 'warning');
                return;
            }
            
            if (!LNMCAdmin.isValidUrl(url)) {
                LNMCAdmin.showNotice('Please enter a valid URL.', 'error');
                return;
            }
            
            $button.prop('disabled', true).text('Testing...');
            
            // Send test request
            $.ajax({
                url: lnmcMemberHub.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'lnmc_test_webhook',
                    nonce: lnmcMemberHub.nonce,
                    webhook_url: url
                },
                success: function(response) {
                    if (response.success) {
                        LNMCAdmin.showNotice('Webhook test successful!', 'success');
                    } else {
                        LNMCAdmin.showNotice('Webhook test failed: ' + response.data, 'error');
                    }
                },
                error: function() {
                    LNMCAdmin.showNotice('Webhook test failed. Please check the URL and try again.', 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Test Webhook');
                }
            });
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        LNMCAdmin.init();
    });

    // Make LNMCAdmin available globally
    window.LNMCAdmin = LNMCAdmin;

})(jQuery);
