# Automated Signing Key Configuration

## Overview
WP Mux now supports **automatic signing key setup** with a single click. No more manual key management from the Mux dashboard!

## How It Works

### The Problem
Previously, setting up signed URLs required:
1. Logging into Mux Dashboard
2. Navigating to Settings → Signing Keys
3. Creating a new key
4. Copying the Key ID
5. Copying the Private Key
6. Pasting both into WordPress settings

This was error-prone and time-consuming.

### The Solution
Now you can simply click **"Auto-Configure Signing Keys"** and WP Mux will:
1. Connect to Mux API
2. Create a new signing key pair
3. Securely store the keys in WordPress
4. Enable signed URL functionality instantly

## Using Auto-Configuration

### Step 1: Navigate to Settings
Go to: **WP Admin → MUX Videos → Settings**

Scroll down to the **"Signing Key Configuration"** section.

### Step 2: Click Auto-Configure
In the "Quick Setup" box, click the button:
```
🌐 Auto-Configure Signing Keys
```

### Step 3: Wait for Completion
The button will show "Configuring..." while it:
- Creates keys in Mux
- Stores them securely
- Validates the setup

### Step 4: Success!
You'll see:
- ✅ Success message
- Key ID populated automatically
- Private key stored (hidden for security)
- Page refreshes to show configured state

## Features

### Smart Detection
- **Already Configured**: If keys exist and are valid, shows status
- **Auto-Validation**: Verifies existing keys before creating new ones
- **Error Handling**: Clear messages if something goes wrong

### Security
- **Base64 Encoding**: Private keys are encoded before storage
- **No Transmission**: Keys never leave your server after creation
- **Read-Only Fields**: Can't accidentally edit configured keys
- **Secure Storage**: Uses WordPress options with proper escaping

### User-Friendly
- **One-Click Setup**: No technical knowledge required
- **Visual Feedback**: Real-time status updates
- **Clear Instructions**: Helpful text at every step
- **Confirmation**: Success/error messages

## API Implementation

### New API Methods

#### `create_signing_key()`
```php
$api = new Wp_Mux_Api();
$result = $api->create_signing_key();

// Returns:
// array(
//     'data' => array(
//         'id' => 'key_abc123',
//         'private_key' => '-----BEGIN PRIVATE KEY-----...',
//         'created_at' => '2025-11-11T17:00:00Z'
//     )
// )
```

#### `list_signing_keys()`
```php
$api = new Wp_Mux_Api();
$keys = $api->list_signing_keys();

// Returns array of all signing keys
```

#### `get_signing_key($key_id)`
```php
$api = new Wp_Mux_Api();
$key = $api->get_signing_key('key_abc123');

// Returns details of specific key
```

#### `delete_signing_key($key_id)`
```php
$api = new Wp_Mux_Api();
$result = $api->delete_signing_key('key_abc123');

// Deletes the signing key from Mux
```

#### `auto_configure_signing_keys()`
```php
$api = new Wp_Mux_Api();
$result = $api->auto_configure_signing_keys();

// Handles the entire configuration process
// Returns:
// array(
//     'status' => 'created', // or 'existing'
//     'message' => 'Success message',
//     'key_id' => 'key_abc123',
//     'created_at' => '2025-11-11T17:00:00Z'
// )
```

### AJAX Handler

#### `wp_ajax_wp_mux_auto_configure_keys`
**Action**: `wp_mux_auto_configure_keys`  
**Nonce**: `wp_mux_auto_configure_keys`  
**Permission**: `manage_options`

**Request**:
```javascript
{
    action: 'wp_mux_auto_configure_keys',
    nonce: '...'
}
```

**Response (Success)**:
```javascript
{
    success: true,
    data: {
        status: 'created',
        key_id: 'key_abc123',
        created_at: '2025-11-11T17:00:00Z',
        message: 'New signing key created and configured successfully.'
    }
}
```

**Response (Error)**:
```javascript
{
    success: false,
    data: {
        message: 'Error description',
        code: 'error_code'
    }
}
```

## UI Components

### Quick Setup Section
Located at the top of Signing Key Configuration:

**Features**:
- Gray background box for visibility
- Large button with icon
- Status indicator when configured
- Reconfigure option for existing setups

**States**:
1. **Not Configured**: Button enabled, no status
2. **Configuring**: Button disabled, "Configuring..." text
3. **Configured**: Button disabled, green checkmark with reconfigure link
4. **Error**: Red error message, button re-enabled

### Manual Configuration Section
Appears below Quick Setup:

**Fields**:
- **Key ID**: Read-only text input (auto-populated)
- **Private Key**: Read-only textarea (obfuscated)

**Purpose**: Shows configured keys but prevents accidental edits

### Status Messages
Real-time feedback div shows:
- Processing status
- Success notifications
- Error messages
- Next steps

## Workflow Diagrams

### First-Time Configuration
```
User clicks "Auto-Configure"
    ↓
AJAX request to WordPress
    ↓
API call to Mux /system/v1/signing-keys
    ↓
Mux creates new key pair
    ↓
API returns key_id + private_key
    ↓
WordPress stores in wp_options (base64 encoded)
    ↓
Success message + page reload
    ↓
UI shows configured state
```

### Reconfiguration
```
User clicks "Reconfigure"
    ↓
Confirmation dialog
    ↓
User confirms
    ↓
Same flow as first-time
    ↓
Old keys remain in Mux (can be deleted manually)
    ↓
New keys become active
```

### Validation Check
```
Auto-configure called
    ↓
Check wp_options for existing keys
    ↓
Keys found?
    ├─ Yes → Validate with Mux API
    │         ├─ Valid → Return "already configured"
    │         └─ Invalid → Create new keys
    └─ No → Create new keys
```

## Error Handling

### Common Errors

**Permission Denied**
```
Error: Insufficient permissions
Solution: Must have 'manage_options' capability
```

**API Credentials Missing**
```
Error: MUX API credentials are not configured
Solution: Configure Access Token and Secret Key first
```

**Network Timeout**
```
Error: Request to Mux API timed out
Solution: Check internet connection, try again
```

**Invalid API Response**
```
Error: Invalid response from Mux API when creating signing key
Solution: Check API credentials, verify Mux account status
```

**Rate Limiting**
```
Error: Too many requests
Solution: Wait a moment and try again
```

## Security Considerations

### Key Storage
- **Base64 Encoded**: Not encryption, but prevents casual viewing
- **Database Storage**: WordPress wp_options table
- **No Plain Text**: Never stored in plain text
- **Access Control**: Only admin users can configure

### Transmission
- **HTTPS Only**: API calls use TLS
- **One-Time Retrieval**: Private key only returned on creation
- **No Logging**: Private keys not logged to error_log

### Rotation
- **Create New Keys**: Reconfigure button creates new pair
- **Old Keys Persist**: Must be manually deleted from Mux dashboard
- **Instant Switch**: New keys activate immediately

## Best Practices

### When to Auto-Configure
✅ **Good Times**:
- First-time setup
- After key compromise
- Testing signed URL functionality
- Moving to new environment

❌ **Avoid When**:
- Multiple sites using same Mux account (coordinate keys)
- Production site during high traffic
- Without Mux account access

### Key Management
1. **Document Key IDs**: Keep record of which keys are for which site
2. **Regular Rotation**: Consider rotating keys every 6-12 months
3. **Delete Old Keys**: Clean up unused keys in Mux dashboard
4. **Monitor Usage**: Check Mux dashboard for key activity

### Troubleshooting Steps
1. Verify Mux API credentials are correct
2. Check WordPress has internet access
3. Review PHP error log for details
4. Test API connection manually
5. Contact Mux support if persistent issues

## Comparison: Manual vs Automatic

### Manual Configuration
**Pros**:
- Full control over key creation
- Can use existing keys
- No API permissions needed beyond video management

**Cons**:
- 6+ steps required
- Error-prone copying
- Time-consuming
- Requires Mux dashboard access

### Automatic Configuration
**Pros**:
- One-click setup
- No copying errors
- Instant validation
- Beginner-friendly

**Cons**:
- Creates new keys each time
- Requires API permissions for signing keys
- Less granular control

## Future Enhancements

### Planned Features
- [ ] **Key Rotation Scheduler**: Auto-rotate keys on schedule
- [ ] **Multi-Environment Support**: Different keys for dev/staging/prod
- [ ] **Key Health Monitoring**: Alert when keys are about to expire
- [ ] **Bulk Key Management**: Manage multiple keys from one interface
- [ ] **Key Export**: Download keys for backup

### Under Consideration
- [ ] **JWT Token Generation**: Full signed URL implementation
- [ ] **Key Usage Analytics**: Track which keys are being used
- [ ] **Emergency Revocation**: Quick disable without deletion
- [ ] **Key Sharing**: Share keys across multisite installations

## Testing

### Test Cases

#### TC1: First-Time Configuration
1. Fresh WordPress install with WP Mux
2. Configure Mux API credentials
3. Click "Auto-Configure Signing Keys"
4. **Expected**: Keys created and stored, success message shown

#### TC2: Existing Keys
1. Keys already configured
2. Navigate to settings
3. **Expected**: "Already configured" message, button disabled

#### TC3: Reconfiguration
1. Keys already configured
2. Click "Reconfigure"
3. Confirm dialog
4. **Expected**: New keys created, old keys still in Mux

#### TC4: Missing API Credentials
1. No Mux credentials configured
2. Click "Auto-Configure"
3. **Expected**: Error message about missing credentials

#### TC5: Network Error
1. Simulate network failure
2. Click "Auto-Configure"
3. **Expected**: Clear error message, button re-enabled

## Support

### Getting Help
- **Documentation**: This file + quick start guide
- **Error Messages**: Always check browser console + PHP logs
- **Mux Support**: Contact if API issues persist
- **Plugin Support**: GitHub issues or support channel

### Reporting Issues
Include:
- Browser + version
- WordPress version
- PHP version
- Error messages (JS console + PHP log)
- Steps to reproduce
- Screenshot if applicable

---

**Feature Version**: 1.3.0  
**Status**: ✅ Implemented & Tested  
**Complexity**: Low (user perspective), Medium (technical)  
**Impact**: High (greatly improves UX)
