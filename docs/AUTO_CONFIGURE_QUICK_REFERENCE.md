# Auto-Configure Signing Keys - Quick Reference

## For End Users

### Setup in 2 Steps

1. **Go to Settings**
   ```
   WordPress Admin → MUX Videos → Settings
   ```

2. **Click the Button**
   ```
   Click: "Auto-Configure Signing Keys"
   Wait: 2-3 seconds
   Done: ✅ Keys configured!
   ```

That's it! Signed URLs are now ready to use.

## For Developers

### API Usage

```php
// Auto-configure (creates or validates keys)
$api = new Wp_Mux_Api();
$result = $api->auto_configure_signing_keys();

if ( ! is_wp_error( $result ) ) {
    echo $result['message'];
    echo 'Key ID: ' . $result['key_id'];
}
```

### Manual Key Management

```php
$api = new Wp_Mux_Api();

// Create new key
$new_key = $api->create_signing_key();
$key_id = $new_key['data']['id'];
$private_key = $new_key['data']['private_key'];

// List all keys
$keys = $api->list_signing_keys();

// Get specific key
$key = $api->get_signing_key( 'key_abc123' );

// Delete key
$api->delete_signing_key( 'key_abc123' );
```

### AJAX Implementation

```javascript
jQuery.ajax({
    url: ajaxurl,
    method: 'POST',
    data: {
        action: 'wp_mux_auto_configure_keys',
        nonce: wpMuxData.nonce
    },
    success: function(response) {
        if (response.success) {
            console.log('Configured:', response.data.key_id);
        }
    }
});
```

## Troubleshooting

### Common Issues

| Issue | Solution |
|-------|----------|
| Button does nothing | Check browser console for JS errors |
| "Permission denied" | Must be admin user |
| "API credentials missing" | Configure Access Token & Secret Key first |
| "Network error" | Check internet connection |
| "Invalid response" | Verify Mux API credentials are correct |

### Debug Mode

Enable WordPress debug to see detailed logs:

```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Check logs at: `wp-content/debug.log`

## Security Notes

- **Keys are encrypted** with base64 encoding
- **Admin-only access** to configuration
- **Nonce protection** on all requests
- **Read-only display** after configuration
- **HTTPS required** for API communication

## Best Practices

✅ **Do:**
- Use auto-configure for quick setup
- Reconfigure if keys are compromised
- Keep WordPress & plugin updated
- Test in staging before production

❌ **Don't:**
- Share signing keys publicly
- Use same keys across multiple sites
- Manually edit stored keys
- Disable auto-reload after configuration

## Quick Commands

### WP-CLI (Future Enhancement)
```bash
# Auto-configure keys
wp mux keys auto-configure

# List keys
wp mux keys list

# Delete key
wp mux keys delete key_abc123
```

## Related Documentation

- Full Guide: `AUTO_CONFIGURE_SIGNING_KEYS.md`
- Mux Options: `MUX_OPTIONS_QUICK_START.md`
- Testing: `TESTING_CHECKLIST.md`

---

**Version**: 1.3.0  
**Last Updated**: November 2025
