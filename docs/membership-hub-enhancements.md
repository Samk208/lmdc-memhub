# LNMC Member Hub - Enhanced Membership Pages

## Overview

The LNMC Member Hub plugin includes enhanced membership hub pages with Divi Builder compatibility. This document explains how to use the available shortcodes and features to create comprehensive membership pages.

## Available Shortcodes

### 1. [lnmc_membership_hub]

A complete membership landing page with hero, benefits, pricing, and signup sections. Divi-optimized to avoid "Save Failed" errors.

**Attributes:**

- `title` - Hero section title (default: "Welcome to LNMC Member Hub")
- `subtitle` - Hero section subtitle/description
- `pricing_sc` - Pricing shortcode to render (default: [lnmc_pricing_table])
- `form_sc` - Form shortcode to render (default: [lnmc_membership_form])
- `dashboard_url` - URL for "Already a Member" link (default: /member-dashboard/)
- `cta_text` - Call-to-action button text (default: "Get Started")
- `anchor_pricing` - ID for pricing section anchor link (default: "plans")
- `class` - Additional CSS classes to apply to the wrapper

**Example:**

```
[lnmc_membership_hub title="Join LNMC" subtitle="Unlock exclusive benefits" cta_text="Join Now"]
```

### 2. [lnmc_member_profile]

Displays the current member's profile information.

**Attributes:**

- `class` - Additional CSS classes to apply to the profile

**Example:**

```
[lnmc_member_profile class="custom-profile"]
```

### 3. [lnmc_member_resources]

Displays a grid of member resources available to logged-in members.

**Attributes:**

- `class` - Additional CSS classes to apply to the resources section
- `title` - Section title
- `description` - Section description

**Example:**

```
[lnmc_member_resources title="Member Resources" description="Access exclusive content and tools."]
```

### 4. Existing Shortcodes (Enhanced for Divi)

- `[lnmc_member_badge]` - Displays membership status badge
- `[lnmc_member_dashboard]` - Shows user dashboard with account info
- `[lnmc_membership_form]` - Membership payment form with Stripe integration
- `[lnmc_pricing_table]` - Displays membership pricing options
- `[lnmc_subscribe_button]` - Subscribe/manage billing button
- `[lnmc_members_only]` - Content restriction for members only

## Divi Builder Integration

The plugin is specifically designed to work with Divi Builder:

1. **Optimized HTML Structure** - Compact markup to avoid Divi "Save Failed" errors
2. **CSS Handling** - Uses `wp_add_inline_style` instead of inline styles
3. **Namespace Safety** - CSS class names avoid conflicts with Divi's `et_pb_*` classes
4. **Responsive Design** - Mobile-friendly layouts that work with Divi's responsive features

## Creating Membership Pages

### 1. Membership Hub Landing Page

Create a new page and add the `[lnmc_membership_hub]` shortcode:

```
[lnmc_membership_hub]
```

### 2. Member Dashboard Page

Create a new page and add the `[lnmc_member_dashboard]` shortcode:

```
[lnmc_member_dashboard]
```

### 3. Custom Membership Page with Individual Components

You can also create custom pages using individual shortcodes:

```
[lnmc_member_badge]

[lnmc_member_profile]

[lnmc_member_resources]

[lnmc_pricing_table]

[lnmc_membership_form]
```

## Styling and Customization

### Custom CSS Classes

All shortcodes support custom CSS classes:

```
[lnmc_membership_hub class="my-custom-class"]
```

### Overriding Styles

Add custom CSS to your theme's stylesheet or Divi's Custom CSS section:

```css
/* Custom styling for membership hub */
.my-custom-class .lnmc-hub-title {
  color: #your-brand-color;
}

/* Custom styling for member profile */
.my-custom-class .lnmc-profile-avatar {
  background: #your-brand-color;
}
```

## Best Practices for Divi Integration

1. **Use the Complete Membership Hub Shortcode** - For best results, use `[lnmc_membership_hub]` on your main membership page
2. **Avoid Complex Layouts** - Keep Divi layouts simple around the shortcodes to prevent save errors
3. **Test Responsiveness** - Always check how pages look on mobile devices
4. **Use Divi's Built-in Styling** - Apply Divi's text and background modules around shortcodes for additional styling

## Troubleshooting

### "Save Failed" Errors in Divi

1. Use the `[lnmc_membership_hub]` shortcode instead of individual components
2. Simplify the Divi layout around the shortcode
3. Clear Divi's cache in Divi > Theme Options > Performance

### Shortcodes Not Rendering

1. Ensure the LNMC Member Hub plugin is activated
2. Check that you're using the correct shortcode syntax
3. Verify user permissions for member-only content

### Payment Form Issues

1. Confirm Stripe keys are properly configured in plugin settings
2. Ensure HTTPS is enabled on your site
3. Check browser console for JavaScript errors

## Developer Notes

### Divi Optimization Features

- Minimal HTML markup to prevent save failures
- CSS enqueued via `wp_add_inline_style`
- Namespaced CSS classes to avoid conflicts
- Responsive design with media queries

### Extending Functionality

To add new shortcodes:

1. Add the method in `src/Shortcodes/Shortcodes.php`
2. Register the shortcode in the `register_shortcodes()` method
3. Add documentation to the `get_shortcode_docs()` method
