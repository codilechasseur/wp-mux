# Troubleshooting Automatic Signing Key Setup

## Common Error: "Unknown API error"

If you're seeing this error when the plugin tries to automatically create signing keys, here's how to diagnose and fix it.

## Quick Diagnosis

### Step 1: Check Your Error

The error notice will show:
```
⚠️ WP Mux Activated
Note: Signing keys could not be automatically configured.

Unknown API error (or other error message)
```

### Step 2: Test Your Connection

Click the **"Test API Connection"** button in the error notice. This will:
1. Verify your API credentials
2. Test basic API access
3. Check signing keys endpoint specifically

## Common Causes & Solutions

### Cause 1: API Credentials Incorrect

**Symptoms**:
- "API credentials are not configured"
- "Authentication failed"
- 401 or 403 errors

**Solution**:
1. Go to **WP Admin → MUX Videos → Settings**
2. Double-check your Mux Access Token
3. Double-check your Mux Secret Key
4. Get fresh credentials from [dashboard.mux.com](https://dashboard.mux.com)
5. Save settings and try again

### Cause 2: Mux Account Permissions

**Symptoms**:
- "Signing keys endpoint failed"
- "Permission denied" errors
- 403 Forbidden errors

**Solution**:
Some Mux accounts may not have access to the Signing Keys API. This typically requires:
- A paid Mux plan
- Specific API permissions enabled
- Account administrator access

**Workaround**: Use manual signing key configuration:
1. Log into [dashboard.mux.com](https://dashboard.mux.com)
2. Go to Settings → Signing Keys
3. Click "Create New Signing Key"
4. Copy the Key ID and Private Key
5. Paste them in WP Admin → MUX Videos → Settings
6. Save

### Cause 3: Network/Firewall Issues

**Symptoms**:
- "Network error"
- Request timeouts
- Cannot connect to mux.com

**Solution**:
1. Verify your server can reach `https://api.mux.com`
2. Check firewall rules
3. Check if your host blocks outbound connections
4. Try from command line:
   ```bash
   curl -i https://api.mux.com/system/v1/signing-keys \
     -u YOUR_TOKEN:YOUR_SECRET \
     -H "Content-Type: application/json"
   ```

### Cause 4: API Version Issues

**Symptoms**:
- "Endpoint not found"
- 404 errors
- "Unknown API error"

**Solution**:
The signing keys endpoint should be `/system/v1/signing-keys`. If Mux has changed their API:
1. Check [Mux API Documentation](https://docs.mux.com/api-reference/system)
2. Report the issue to plugin support
3. Use manual configuration as temporary workaround

## Debugging Steps

### Enable WordPress Debug Logging

1. Edit `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

2. Reproduce the error (deactivate and reactivate plugin)

3. Check `wp-content/debug.log` for detailed error messages:
```
[WP MUX] API Error - Code: 403
[WP MUX] API Error - Message: Permission denied
[WP MUX] API Error - Full response: {...}
```

### Check Error Details

The plugin now logs:
- HTTP status code
- Full error response from Mux
- API endpoint being called
- Method used (POST, GET, etc.)

Look for lines starting with `[WP MUX]` in your error log.

### Manual API Test

Test the Mux API directly to isolate the issue:

**Test 1: Basic Auth**
```bash
curl -u YOUR_TOKEN:YOUR_SECRET https://api.mux.com/video/v1/assets?limit=1
```
Should return a list of videos (or empty array if none).

**Test 2: Signing Keys Endpoint**
```bash
curl -X POST https://api.mux.com/system/v1/signing-keys \
  -u YOUR_TOKEN:YOUR_SECRET \
  -H "Content-Type: application/json" \
  -d '{}'
```
Should return a new signing key with ID and private key.

**Test 3: List Existing Keys**
```bash
curl https://api.mux.com/system/v1/signing-keys \
  -u YOUR_TOKEN:YOUR_SECRET
```
Should return list of existing signing keys.

## Workarounds

### Option 1: Manual Configuration

If automatic setup fails:

1. Go to [dashboard.mux.com](https://dashboard.mux.com)
2. Settings → Signing Keys → Create New Signing Key
3. Copy Key ID and Private Key
4. In WordPress: Settings → Enter keys manually
5. Click "Auto-Configure" button (will use existing Mux key)

### Option 2: Use Public Videos

If you don't need private videos:

1. Go to **WP Admin → MUX Videos → Settings**
2. Find **"Default Playback Policy"**
3. Change from **"Signed"** to **"Public"**
4. Save settings
5. Your videos will be publicly accessible (no signing keys needed)

### Option 3: Contact Support

If nothing works:

**Mux Support**:
- Email: support@mux.com
- Docs: https://docs.mux.com
- Check if your account has signing keys feature

**Plugin Support**:
- Provide: Error message from error_log
- Provide: Result of curl tests above
- Provide: Mux account type (free/paid)

## What Changed

### New in Version 1.3.0

The plugin now:
- **Sends `{}`** instead of empty body for POST requests
- **Better error detection** - checks multiple error response formats
- **Detailed logging** - logs full API responses
- **Graceful fallback** - defaults to public if key creation fails
- **Test button** - helps diagnose connection issues

### Previous Behavior

- Sent empty array `[]` which some endpoints reject
- Less detailed error messages
- Would block activation on errors
- No way to test connection

## Success Indicators

When it works, you'll see:

```
✅ WP Mux Activated!

Your videos are automatically protected with signed URLs.
Signing keys have been configured and stored securely.

Key ID: key_abc123xyz
```

And in `wp-content/debug.log`:
```
[WP MUX] Signing keys auto-configured on activation: key_abc123xyz
```

## Still Having Issues?

### Quick Checklist

- [ ] API credentials are correct (copy-paste from Mux dashboard)
- [ ] Account has signing keys feature (check Mux plan)
- [ ] Server can reach api.mux.com (test with curl)
- [ ] No firewall blocking outbound HTTPS
- [ ] WordPress debug log shows detailed error
- [ ] Tried manual configuration workaround
- [ ] Tested with curl commands above

### Report an Issue

If you've tried everything, report with:

1. **Error message** from admin notice
2. **Error log** entries from debug.log
3. **Curl test results** (remove your actual tokens!)
4. **Mux account type** (free/paid/enterprise)
5. **WordPress version**
6. **PHP version**
7. **Hosting provider** (some block API calls)

---

**Last Updated**: November 2025  
**Plugin Version**: 1.3.0
