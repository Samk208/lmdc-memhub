# LNMC Member Hub

A comprehensive member management system for LNMC with REST API endpoints and admin settings.

## Requirements

- **PHP**: 8.1 or higher
- **WordPress**: 6.5 or higher
- **Composer**: For dependency management

## Installation

### 1. Download and Extract

Download the plugin and extract it to your WordPress plugins directory:

```bash
cd wp-content/plugins/
git clone <repository-url> lnmc-member-hub
cd lnmc-member-hub
```

### 2. Install Dependencies

Install PHP dependencies using Composer:

```bash
composer install
```

### 3. Activate the Plugin

1. Go to your WordPress admin dashboard
2. Navigate to **Plugins** > **Installed Plugins**
3. Find "LNMC Member Hub" and click **Activate**

### 4. Configure Settings

1. Go to **Settings** > **LNMC Member Hub**
2. Configure the following settings:
   - **Membership Mode**: Choose between Off (disabled), Test (testing mode), or Live (production mode)
   - **Webhook URL**: Enter a webhook URL for external integrations (HTTPS required, optional)

## Usage

### Divi Shortcodes

The plugin provides two shortcodes designed specifically for Divi compatibility:

#### Member Badge Shortcode

**Shortcode**: `[lnmc_member_badge]`

Displays a small inline badge showing the current user's membership status.

**Attributes**:

- `class` (optional): Additional CSS classes to apply to the badge

**Examples**:

```html
<!-- Basic usage -->
[lnmc_member_badge]

<!-- With custom styling -->
[lnmc_member_badge class="custom-badge"]
```

**Output**:

- For active members: `<span class="lnmc-member-badge lnmc-member-active">Member Active</span>`
- For inactive members: `<span class="lnmc-member-badge lnmc-member-inactive">Member Inactive</span>`
- For non-logged-in users: No output (empty string)

#### Member Dashboard Shortcode

**Shortcode**: `[lnmc_member_dashboard]`

Displays a dashboard card with user information for logged-in users.

**Attributes**:

- `class` (optional): Additional CSS classes to apply to the dashboard

**Examples**:

```html
<!-- Basic usage -->
[lnmc_member_dashboard]

<!-- With custom styling -->
[lnmc_member_dashboard class="custom-dashboard"]
```

**Output**:

- For logged-in users: A styled dashboard card with username, obfuscated email, and last login (if available)
- For non-logged-in users: A message asking them to log in

#### Member Profile Shortcode

**Shortcode**: `[lnmc_member_profile]`

Displays the current member's profile information.

**Attributes**:

- `class` (optional): Additional CSS classes to apply to the profile

**Examples**:

```html
<!-- Basic usage -->
[lnmc_member_profile]

<!-- With custom styling -->
[lnmc_member_profile class="custom-profile"]
```

**Output**:

- For logged-in members: A styled profile card with user information
- For non-logged-in users: No output (empty string)

#### Member Resources Shortcode

**Shortcode**: `[lnmc_member_resources]`

Displays a grid of member resources available to logged-in members.

**Attributes**:

- `class` (optional): Additional CSS classes to apply to the resources section
- `title` (optional): Section title
- `description` (optional): Section description

**Examples**:

```html
<!-- Basic usage -->
[lnmc_member_resources]

<!-- With custom title and styling -->
[lnmc_member_resources title="Member Resources" description="Access exclusive
content and tools." class="custom-resources"]
```

**Output**:

- For logged-in members: A grid of resource items
- For non-logged-in users: No output (empty string)

#### Membership Hub Shortcode

**Shortcode**: `[lnmc_membership_hub]`

Renders a complete membership landing page with hero, benefits, pricing, and signup sections. Divi-optimized to avoid "Save Failed" errors.

**Attributes**:

- `title` (optional): Hero section title (default: "Welcome to LNMC Member Hub")
- `subtitle` (optional): Hero section subtitle/description
- `pricing_sc` (optional): Pricing shortcode to render (default: [lnmc_pricing_table])
- `form_sc` (optional): Form shortcode to render (default: [lnmc_membership_form])
- `dashboard_url` (optional): URL for "Already a Member" link (default: /member-dashboard/)
- `cta_text` (optional): Call-to-action button text (default: "Get Started")
- `anchor_pricing` (optional): ID for pricing section anchor link (default: "plans")
- `class` (optional): Additional CSS classes to apply to the wrapper

**Examples**:

```html
<!-- Basic usage -->
[lnmc_membership_hub]

<!-- With custom title and CTA -->
[lnmc_membership_hub title="Join LNMC" subtitle="Unlock exclusive benefits"
cta_text="Join Now"]
```

**Output**:
A complete membership landing page with all sections.

### Using Shortcodes in Divi

#### In Divi Text Modules

1. **Add a Text Module** to your Divi page
2. **Enter the shortcode** in the text editor:

   ```
   Welcome back! Your status: [lnmc_member_badge]

   [lnmc_member_dashboard]
   ```

3. **Save and preview** the page

#### In Divi Code Modules

1. **Add a Code Module** to your Divi page
2. **Enter the shortcode** in the code editor:
   ```html
   <div class="member-section">
     <h2>Member Information</h2>
     [lnmc_member_dashboard class="divi-styled-dashboard"]
   </div>
   ```
3. **Save and preview** the page

#### Frontend JavaScript

The plugin automatically enqueues a frontend JavaScript file (`frontend.js`) only when one of the shortcodes is present on the page. This script provides:

- **Conditional Loading**: Scripts are only loaded on singular pages containing the shortcodes
- **REST API Integration**: Built-in methods for making AJAX requests to the plugin's REST API
- **Global Object**: `LNMC_Hub` object with REST URL and nonce for API requests

**Available JavaScript Methods**:

```javascript
// Test the ping endpoint
LNMCFrontend.testPing();

// Make a custom API request
LNMCFrontend.apiRequest(
  "/lnmc/v1/ping",
  { nonce: LNMC_Hub.nonce },
  function (response) {
    console.log("API Response:", response);
  }
);
```

**Global Object Properties**:

- `LNMC_Hub.restUrl`: WordPress REST API base URL
- `LNMC_Hub.nonce`: Valid nonce for REST API requests

#### Customizing with CSS

```css
/* Custom badge styling */
.lnmc-member-badge.custom-badge {
  background: linear-gradient(45deg, #667eea 0%, #764ba2 100%);
  border-radius: 20px;
  padding: 6px 12px;
  font-size: 0.8em;
}

/* Custom dashboard styling */
.lnmc-member-dashboard.custom-dashboard .lnmc-dashboard-card {
  background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
  border: none;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
}
```

#### Membership Status Filter

To control membership status, use the `lnmc/is_member` filter:

```php
// In your theme's functions.php or a custom plugin
add_filter( 'lnmc/is_member', function( $is_member, $user_id ) {
    // Your custom logic here
    $user = get_user_by( 'id', $user_id );

    // Example: Check if user has a specific role
    if ( in_array( 'premium_member', $user->roles ) ) {
        return true;
    }

    // Example: Check if user has a specific meta field
    if ( get_user_meta( $user_id, 'membership_status', true ) === 'active' ) {
        return true;
    }

    return $is_member;
}, 10, 2 );
```

### REST API Endpoints

The plugin provides a REST API with the following endpoints:

#### Ping Endpoint

**URL**: `/wp-json/lnmc/v1/ping`

**Method**: `GET`

**Authentication**: Requires user authentication and a valid nonce

**Parameters**:

- `nonce` (required): A valid nonce for security verification

**Example Request**:

```bash
curl -X GET "https://your-site.com/wp-json/lnmc/v1/ping?nonce=YOUR_NONCE" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

**Example Response**:

```json
{
  "status": "success",
  "message": "Pong! LNMC Member Hub is working correctly.",
  "data": {
    "timestamp": "2024-01-15 10:30:00",
    "user_id": 1,
    "user_name": "John Doe",
    "plugin_version": "1.0.0",
    "site_url": "https://your-site.com"
  }
}
```

### Nonce Generation

To generate a nonce for API requests:

```php
// Get the plugin instance
$plugin = \LNMC_Member_Hub\Plugin::get_instance();

// Get the nonce helper
$nonce_helper = $plugin->get_nonce_helper();

// Create a nonce for the ping endpoint
$nonce = $nonce_helper->create_nonce('ping');
```

### Settings API

Access plugin settings programmatically:

```php
// Get the plugin instance
$plugin = \LNMC_Member_Hub\Plugin::get_instance();

// Get the settings instance
$settings = $plugin->get_settings();

// Get membership mode
$membership_mode = $settings->get_membership_mode();

// Get webhook URL
$webhook_url = $settings->get_webhook_url();
```

## Development

### Project Structure

```
lnmc-member-hub/
├── src/
│   ├── Admin/
│   │   └── Settings.php
│   ├── Frontend/
│   │   └── Assets.php
│   ├── REST/
│   │   └── API.php
│   ├── Shortcodes/
│   │   └── Shortcodes.php
│   ├── Utils/
│   │   └── Nonce_Helper.php
│   └── Plugin.php
├── tests/
│   ├── Admin/
│   │   └── SettingsTest.php
│   ├── Frontend/
│   │   └── AssetsTest.php
│   ├── REST/
│   │   └── APITest.php
│   ├── Shortcodes/
│   │   └── ShortcodesTest.php
│   ├── Utils/
│   │   └── NonceHelperTest.php
│   └── PluginTest.php
├── assets/
│   ├── css/
│   │   └── admin.css
│   └── js/
│       ├── admin.js
│       └── frontend.js
├── composer.json
├── phpunit.xml
├── phpcs.xml
├── lnmc-member-hub.php
└── README.md
```

### Running Tests

1. **Install WordPress Test Environment**:

   ```bash
   # Create a script to install WordPress test environment
   bin/install-wp-tests.sh wordpress_test root '' localhost latest
   ```

2. **Run PHPUnit Tests**:

   ```bash
   composer test
   ```

3. **Run Code Style Checks**:

   ```bash
   composer phpcs
   ```

4. **Fix Code Style Issues**:
   ```bash
   composer phpcbf
   ```

### Adding New Features

1. **Create New Classes**: Follow the PSR-4 autoloading structure
2. **Add Tests**: Create corresponding test files in the `tests/` directory
3. **Update Documentation**: Update this README and inline documentation
4. **Run Tests**: Ensure all tests pass before committing

## Security

### Shortcode Security Features

The shortcodes implement several security measures to ensure compatibility with WordPress security plugins:

#### Output Escaping

- **All output is escaped** using `esc_html()`, `esc_attr()`, and `esc_url()`
- **No raw HTML** is output without proper escaping
- **XSS prevention** through comprehensive input sanitization

#### Email Obfuscation

- **Email addresses are obfuscated** to prevent harvesting
- **Format**: `first***last@domain***tld` (e.g., `j***n@example***com`)
- **No plain email addresses** are ever displayed

#### State-Changing Prevention

- **No database writes** from shortcodes
- **No form submissions** or AJAX calls
- **Read-only operations** only

#### Input Sanitization

- **Shortcode attributes** are sanitized using `sanitize_html_class()`
- **User data** is validated before display
- **Malicious input** is filtered out

#### Accessibility Compliance

- **ARIA labels** for screen readers
- **Semantic HTML** structure
- **Role attributes** for proper accessibility

### Compatibility with Security Plugins

The plugin is designed to work seamlessly with popular WordPress security plugins:

#### Wordfence Security

- **No false positives** from shortcode output
- **Clean HTML structure** that passes security scans
- **No external links** or suspicious content

#### Sucuri Security

- **No inline scripts** or potentially malicious code
- **Sanitized output** that passes Sucuri scans
- **Proper escaping** prevents XSS vulnerabilities

#### iThemes Security

- **No database queries** that could trigger security alerts
- **Clean user data handling** that complies with security policies
- **No file operations** that could be flagged

#### All In One WP Security

- **No user enumeration** through shortcode output
- **Secure user data display** that respects privacy settings
- **No information disclosure** vulnerabilities

### Nonce Verification

All REST API endpoints require nonce verification to prevent CSRF attacks:

```php
// Verify nonce in your code
$nonce = $_GET['nonce'] ?? '';
if (!$nonce_helper->verify_nonce($nonce, 'ping')) {
    wp_die('Security check failed');
}
```

### Input Sanitization

All user inputs are properly sanitized:

- **Settings**: Use `register_setting()` with sanitize callbacks
- **REST API**: Use `sanitize_callback` in endpoint arguments
- **Database**: Use `$wpdb->prepare()` for all SQL queries

### Capability Checks

The plugin implements proper capability checks:

- **Admin Settings**: Requires `manage_options` capability
- **REST API**: Requires `read` capability for basic access
- **Admin Functions**: Check capabilities before performing actions

### Security Best Practices

1. **Never Trust User Input**: Always sanitize and validate
2. **Use Nonces**: For all forms and AJAX requests
3. **Check Capabilities**: Before performing privileged operations
4. **Escape Output**: Use `esc_html()`, `esc_attr()`, etc.
5. **Use Prepared Statements**: For all database queries

## Database

The plugin uses WordPress options API for data storage:

- `lnmc_member_hub_membership_mode`: Stores the membership mode (enum: 'off', 'test', 'live', default: 'off')
- `lnmc_member_hub_webhook_url`: Stores the webhook URL (HTTPS required, optional)

### Example Database Query

```php
// Safe database query using $wpdb->prepare()
global $wpdb;
$results = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM {$wpdb->users} WHERE user_status = %d",
        0
    )
);
```

## Troubleshooting

### Common Issues

1. **Plugin Not Loading**: Check PHP version (requires 8.1+)
2. **Settings Not Saving**: Verify user has `manage_options` capability
3. **REST API Errors**: Check nonce validity and user authentication
4. **Tests Failing**: Ensure WordPress test environment is properly set up

### Debug Mode

Enable WordPress debug mode to see detailed error messages:

```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests for new functionality
5. Run the test suite
6. Submit a pull request

## License

This plugin is licensed under the GPL v2 or later.

## Support

For support and questions:

- **Documentation**: Check this README and inline code documentation
- **Issues**: Report bugs and feature requests through the issue tracker
- **Security**: Report security issues privately to the development team

## Changelog

### Version 1.1.0

- Added new Divi-compatible shortcodes: `[lnmc_member_profile]`, `[lnmc_member_resources]`, and `[lnmc_membership_hub]`
- Enhanced membership hub pages with complete landing page solution
- Improved Divi Builder integration with optimized HTML structure
- Added CSS styling for new shortcodes with responsive design
- Updated documentation with comprehensive shortcode usage examples

### Version 1.0.0

- Initial release
- Admin settings page with membership mode and webhook URL
- REST API with ping endpoint
- Nonce helper utilities
- **Divi-compatible shortcodes**: `[lnmc_member_badge]` and `[lnmc_member_dashboard]`
- **Conditional frontend assets**: Scripts loaded only when shortcodes are present
- **Frontend JavaScript API**: `LNMC_Hub` object with REST integration
- **Security-focused design** with output escaping and email obfuscation
- **Accessibility compliance** with ARIA labels and semantic HTML
- **WordPress security plugin compatibility**
- Comprehensive test suite
- WordPress coding standards compliance
