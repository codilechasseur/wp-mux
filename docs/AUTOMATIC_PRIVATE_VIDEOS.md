# Automatic Private Video Protection

## Overview
WP Mux now automatically secures ALL your videos by default. No configuration needed!

## How It Works

### 🔒 Privacy by Default

When you activate WP Mux, it automatically:

1. **Creates Signing Keys** - Secure cryptographic keys via Mux API
2. **Stores Keys Securely** - Base64 encoded in WordPress database
3. **Sets Default to "Signed"** - All new videos require authentication
4. **Works Silently** - No user interaction required

### The Magic Happens On...

#### Plugin Activation
```
You click "Activate Plugin"
    ↓
WP Mux checks for API credentials
    ↓
If found → Creates signing keys automatically
    ↓
Sets default playback policy to "signed"
    ↓
Shows success notice with key ID
    ↓
All future videos are automatically private!
```

#### First API Configuration
```
You save Mux API credentials
    ↓
WP Mux detects first-time setup
    ↓
Automatically creates signing keys
    ↓
Sets default to "signed"
    ↓
Shows success notice
    ↓
All videos now private by default!
```

## User Experience

### For New Installations

**What the admin sees:**

1. Install & activate plugin
2. Go to settings
3. Enter Mux API credentials
4. Save settings
5. See success notice: "Your videos are automatically protected with signed URLs"
6. Upload videos → they're automatically private!

**What they DON'T see:**
- Complex signing key concepts
- Base64 encoded strings
- Manual key creation steps
- Technical configuration

### For Existing Installations

**When activating plugin:**
- If API credentials already exist → Keys created immediately
- If no credentials → Notice: "Configure API to enable security"
- Once credentials saved → Keys auto-created, videos become private

### Success Notice

After automatic configuration, admin sees:

```
✅ WP Mux Activated!

Your videos are automatically protected with signed URLs. 
Signing keys have been configured and stored securely.

Key ID: key_abc123xyz
```

## Settings Page

The signing key section shows:

### Quick Setup Box
- Green checkmark: "✓ Signing keys are already configured"
- No action needed by user
- Optional "Reconfigure" link if keys need rotation

### Manual Section
- Read-only fields show configured keys
- Prevents accidental changes
- Professional, clean appearance

## For Developers

### Activation Hook

```php
// includes/class-wp-mux-activator.php

public static function activate() {
    // Set secure defaults
    self::set_default_options();
    
    // Auto-create signing keys
    self::auto_setup_signing_keys();
}

private static function set_default_options() {
    // DEFAULT IS SIGNED (PRIVATE)
    add_option('wp_mux_default_playback_policy', 'signed');
    
    add_option('wp_mux_default_mp4_support', 'none');
    add_option('wp_mux_default_encoding_tier', 'baseline');
}

private static function auto_setup_signing_keys() {
    $api = new Wp_Mux_Api();
    
    if ($api->is_configured()) {
        $result = $api->auto_configure_signing_keys();
        
        if (!is_wp_error($result)) {
            set_transient('wp_mux_signing_key_success', $result['key_id'], WEEK_IN_SECONDS);
        }
    } else {
        set_transient('wp_mux_needs_api_config', true, DAY_IN_SECONDS);
    }
}
```

### Deferred Key Creation

```php
// admin/class-wp-mux-admin.php

public function sanitize_api_credentials($value) {
    $sanitized = sanitize_text_field($value);
    $secret_key = $_POST['wp_mux_secret_key'] ?? '';
    
    if (!empty($sanitized) && !empty($secret_key)) {
        $has_keys = !empty(get_option('wp_mux_signing_key_id'));
        
        if (!$has_keys) {
            // Schedule for next page load
            set_transient('wp_mux_needs_key_creation', true, HOUR_IN_SECONDS);
        }
    }
    
    return $sanitized;
}

public function maybe_create_signing_keys() {
    if (!get_transient('wp_mux_needs_key_creation')) {
        return;
    }
    
    delete_transient('wp_mux_needs_key_creation');
    
    $api = new Wp_Mux_Api();
    $result = $api->auto_configure_signing_keys();
    
    if (!is_wp_error($result)) {
        // Success!
        set_transient('wp_mux_signing_key_success', $result['key_id'], WEEK_IN_SECONDS);
        update_option('wp_mux_default_playback_policy', 'signed');
    }
}
```

## Security Benefits

### Automatic Protection
- **No Configuration Required** - Works out of the box
- **Secure by Default** - Videos can't be accessed without tokens
- **Professional Grade** - Uses industry-standard RSA keys
- **WordPress Integration** - Seamless with existing security

### What's Protected
- **Video Playback** - Can't be embedded on unauthorized sites
- **Direct URLs** - Can't be shared and played elsewhere
- **API Access** - Requires valid authentication
- **Thumbnail Access** - Also requires signed URLs

## Opting Out

### To Make Videos Public

If you want public videos instead:

1. Go to **WP Admin → MUX Videos → Settings**
2. Find **"Default Playback Policy"**
3. Change from **"Signed URLs"** to **"Public"**
4. Save settings
5. Future videos will be public

Existing private videos remain private unless changed individually.

### Per-Video Control

Individual videos can be changed:

1. Edit video in Media Library
2. Find **"MUX Settings"** meta box
3. Change **Playback Policy** dropdown
4. Update immediately via AJAX

## Comparison: Before vs After

### Before This Update
```
✗ Videos public by default
✗ Manual key configuration required
✗ 7+ steps to enable security
✗ Technical knowledge needed
✗ Easy to forget or skip
```

### After This Update
```
✓ Videos private by default
✓ Automatic key creation
✓ Zero configuration steps
✓ No technical knowledge needed
✓ Impossible to forget
```

## Troubleshooting

### Keys Not Created on Activation

**Cause**: API credentials not configured yet

**Solution**: 
1. Go to Settings
2. Enter Mux Access Token & Secret Key
3. Save settings
4. Keys created automatically on next page load

### Still Seeing Manual Fields

**Cause**: This is normal! Shows configured keys

**What to do**: Nothing! The read-only fields show your keys are configured

### Want to Change Keys

**How**: Click the "Reconfigure" link in Quick Setup box

**Result**: New keys created, old ones remain in Mux (can delete manually)

## Admin Notices Explained

### Success Notice
```
✅ WP Mux Activated!
Your videos are automatically protected with signed URLs.
Signing keys have been configured and stored securely.
Key ID: key_abc123
```
**Meaning**: Everything worked! Your videos are secure.

### Info Notice
```
ℹ️ WP Mux: Setup Required
Please configure your Mux API credentials. Once configured,
signing keys will be automatically created to secure your videos.
[Configure API Credentials]
```
**Meaning**: Enter API credentials to enable auto-security.

### Warning Notice
```
⚠️ WP Mux Activated
Note: Signing keys could not be automatically configured.
[Error message]
[Configure Manually]
```
**Meaning**: Something went wrong. Use manual configuration or check API credentials.

## Testing

### Test Case 1: Fresh Installation
1. Install plugin
2. Activate plugin
3. Enter API credentials
4. Save settings
5. **Expected**: Success notice with key ID
6. **Verify**: `wp_mux_default_playback_policy` = 'signed'
7. **Verify**: `wp_mux_signing_key_id` has value

### Test Case 2: Activation With Pre-Configured API
1. Plugin installed but not activated
2. Manually set API credentials in database
3. Activate plugin
4. **Expected**: Immediate key creation
5. **Expected**: Success notice on next admin page

### Test Case 3: Existing Installation Upgrade
1. Plugin already active (old version)
2. Update to new version
3. **Expected**: Keys NOT created (already public videos)
4. **Optional**: Admin can manually click "Auto-Configure"

## Best Practices

### For Plugin Developers
✓ Use transients for deferred operations
✓ Show clear success/error notices
✓ Log operations for debugging
✓ Fail gracefully if API unavailable
✓ Don't block plugin activation on API errors

### For Site Administrators
✓ Review security notice when activating
✓ Test with one video before mass upload
✓ Understand signed vs public difference
✓ Keep plugin updated for security fixes
✓ Consider key rotation every 6-12 months

### For End Users
✓ Nothing! It just works automatically
✓ Videos are secure by default
✓ No manual configuration needed

## Future Enhancements

### Planned
- [ ] **Deactivation Handler** - Clean up keys when plugin removed
- [ ] **Multisite Support** - Different keys per site
- [ ] **Key Health Monitoring** - Alert when keys need rotation
- [ ] **Migration Tool** - Convert existing public videos to private
- [ ] **WP-CLI Commands** - Manage keys from command line

### Under Consideration
- [ ] **Key Rotation Scheduler** - Auto-rotate every X months
- [ ] **Emergency Disable** - Quick toggle to make all videos public
- [ ] **Audit Log** - Track when videos accessed with signed URLs
- [ ] **Geofencing** - Restrict playback to specific countries

## FAQ

**Q: What if I don't want private videos?**  
A: Change default playback policy to "Public" in settings. Simple!

**Q: Will this break my existing videos?**  
A: No. Existing videos keep their current settings. Only affects NEW uploads.

**Q: Can I change individual videos to public?**  
A: Yes! Edit the video and change its playback policy.

**Q: What if signing key creation fails?**  
A: You'll see a notice. Click "Configure Manually" or try again later.

**Q: Does this work with multisite?**  
A: Not yet. Each site would need separate keys. Planned for future.

**Q: What's the performance impact?**  
A: Minimal. Key creation happens once. Playback is same speed.

**Q: Are the signing keys secure?**  
A: Yes. Base64 encoded, stored in database, only accessible to admins.

**Q: Can I export/backup the keys?**  
A: They're in wp_options table. Use standard WordPress backup tools.

---

**Feature Version**: 1.3.0  
**Status**: ✅ Implemented  
**Complexity**: Low (user), Medium (technical)  
**Impact**: 🔥 HUGE - Complete game changer for video security
