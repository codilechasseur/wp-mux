# MUX Options Testing Checklist

## Pre-Testing Setup
- [ ] WordPress site running with DDEV
- [ ] WP Mux plugin activated
- [ ] Mux API credentials configured
- [ ] Test video file ready (< 50MB recommended)
- [ ] Browser developer tools open (console tab)

## Phase 1: Global Settings UI

### Settings Page Access
- [ ] Navigate to WP Admin → MUX Videos → Settings
- [ ] Page loads without errors
- [ ] "Upload Default Settings" section visible
- [ ] All fields render properly

### Playback Policy Field
- [ ] Dropdown shows two options: Public, Signed URLs
- [ ] Default value is "Public"
- [ ] Help text displayed below field
- [ ] Can select "Signed URLs"
- [ ] Selection saves on "Save Settings"

### MP4 Support Field
- [ ] Dropdown shows three options: None, Standard, Audio Only
- [ ] Default value is "None"
- [ ] Help text mentions storage costs
- [ ] All options selectable
- [ ] Selection saves properly

### Encoding Tier Field
- [ ] Dropdown shows three options: Baseline, Smart, Plus
- [ ] Default value is "Baseline"
- [ ] Help text mentions processing costs
- [ ] Help text notes "cannot be changed after upload"
- [ ] Selection saves properly

### Auto-Captions Field
- [ ] Checkbox is unchecked by default
- [ ] Label text: "Automatically generate captions on upload"
- [ ] Language dropdown appears
- [ ] 8 languages available (EN, ES, FR, DE, IT, PT, JA, ZH)
- [ ] Default language is English
- [ ] Help text mentions Mux account requirement
- [ ] Both fields save properly

### Signing Keys Field
- [ ] Two text inputs visible
- [ ] Signing Key ID field (text input)
- [ ] Private Key field (textarea)
- [ ] Help text with link to Mux docs
- [ ] Fields save properly
- [ ] Private key obfuscated after save

### Settings Validation
- [ ] Invalid playback policy rejected
- [ ] Invalid MP4 support rejected
- [ ] Invalid encoding tier rejected
- [ ] Settings persist after page reload
- [ ] Success notice shown after save

## Phase 2: Video Upload with Defaults

### Test with Default Settings (Public, None, Baseline)
- [ ] Go to Media → Add New
- [ ] Upload test video file
- [ ] Video appears in media library
- [ ] Processing status shows
- [ ] Video completes processing
- [ ] Check Mux dashboard: video has public policy
- [ ] Check Mux dashboard: MP4 support is none
- [ ] Check Mux dashboard: encoding tier is baseline

### Test with Modified Settings (Signed, Standard, Smart)
- [ ] Change settings: Signed URLs, Standard MP4, Smart tier
- [ ] Save settings
- [ ] Upload another test video
- [ ] Video completes processing
- [ ] Check Mux dashboard: video has signed policy
- [ ] Check Mux dashboard: MP4 support is standard
- [ ] Check Mux dashboard: encoding tier is smart

### Test with Auto-Captions Enabled
- [ ] Enable "Auto-Generate Captions"
- [ ] Select "Spanish" as language
- [ ] Save settings
- [ ] Upload test video (with spoken audio)
- [ ] Video completes processing
- [ ] Wait 2-3 minutes for caption processing
- [ ] Check video attachment meta: `_mux_has_captions` = true
- [ ] Check video attachment meta: `_mux_caption_track_ids` has value
- [ ] Check Mux dashboard: caption track exists

## Phase 3: Per-Asset Settings UI

### Meta Box Display
- [ ] Navigate to Media → Library
- [ ] Click on a completed MUX video
- [ ] "MUX Settings" meta box visible
- [ ] Meta box in "normal" position (below title)
- [ ] All fields render properly

### Playback Policy Control
- [ ] Current policy displayed in dropdown
- [ ] Can select different policy
- [ ] Change from Public to Signed
- [ ] Confirmation dialog appears
- [ ] Click confirm
- [ ] Success message shown
- [ ] No page reload required (AJAX)
- [ ] Check Mux dashboard: policy updated

### MP4 Support Control
- [ ] Current setting displayed
- [ ] Can select different option
- [ ] Change selection
- [ ] Save via form submit (Update button)
- [ ] Success notice appears
- [ ] Check Mux dashboard: setting updated

### Encoding Tier Display
- [ ] Shows current tier (e.g., "Baseline")
- [ ] Displayed as bold text (not editable)
- [ ] Help text: "cannot be changed after upload"
- [ ] No dropdown or input field

### Caption Generation
- [ ] For video WITHOUT captions:
  - [ ] Shows "No captions" with gray icon
  - [ ] "Generate Captions" button visible
  - [ ] Click button
  - [ ] Language selector appears
  - [ ] Select language
  - [ ] Click "Confirm"
  - [ ] Button shows "Generating..." (disabled)
  - [ ] Success message after ~30 seconds
  - [ ] Page reloads showing "Captions available"

- [ ] For video WITH captions:
  - [ ] Shows "Captions available" with green checkmark
  - [ ] "Manage Captions" button visible

## Phase 4: AJAX Functionality

### Caption Generation AJAX
- [ ] Open browser console
- [ ] Click "Generate Captions"
- [ ] Select language
- [ ] Click "Confirm"
- [ ] Check console: no JavaScript errors
- [ ] Check network tab: POST to admin-ajax.php
- [ ] Check response: action = wp_mux_generate_captions
- [ ] Verify nonce in request
- [ ] Verify response includes success: true
- [ ] Verify response includes message

### Playback Policy Update AJAX
- [ ] Open browser console
- [ ] Change playback policy dropdown
- [ ] Confirmation dialog appears
- [ ] Click "OK"
- [ ] Check console: no errors
- [ ] Check network tab: POST to admin-ajax.php
- [ ] Check response: action = wp_mux_update_playback_policy
- [ ] Verify nonce in request
- [ ] Verify response includes success: true
- [ ] Alert shown with success message

## Phase 5: Metadata Verification

### WordPress Options Check
Run in WordPress SQL or use plugin like Query Monitor:
```sql
SELECT option_name, option_value 
FROM wp_options 
WHERE option_name LIKE 'wp_mux_%';
```

- [ ] `wp_mux_default_playback_policy` exists
- [ ] `wp_mux_default_mp4_support` exists
- [ ] `wp_mux_default_encoding_tier` exists
- [ ] `wp_mux_enable_auto_captions` exists
- [ ] `wp_mux_default_caption_language` exists
- [ ] `wp_mux_signing_key_id` exists (if configured)
- [ ] `wp_mux_signing_key_private` exists (if configured)

### Post Meta Check
For a completed MUX video attachment:
```sql
SELECT meta_key, meta_value 
FROM wp_postmeta 
WHERE post_id = [VIDEO_ATTACHMENT_ID] 
AND meta_key LIKE '_mux_%';
```

- [ ] `_mux_asset_id` exists
- [ ] `_mux_playback_id` exists
- [ ] `_mux_playback_policy` exists
- [ ] `_mux_mp4_support` exists
- [ ] `_mux_encoding_tier` exists
- [ ] `_mux_requires_signed_url` exists
- [ ] `_mux_has_captions` exists (if captions generated)
- [ ] `_mux_caption_track_ids` exists (if captions generated)

## Phase 6: Error Handling

### Invalid Inputs
- [ ] Try to save invalid playback policy (manual form manipulation)
- [ ] Verify sanitization returns default value
- [ ] Try to generate captions without attachment ID
- [ ] Verify error message returned
- [ ] Try to update policy without asset ID
- [ ] Verify error message returned

### Permission Checks
- [ ] Log in as non-admin user (Subscriber role)
- [ ] Try to access settings page
- [ ] Should be denied access
- [ ] Try to edit MUX video attachment
- [ ] Should be denied or limited
- [ ] Try AJAX requests with subscriber account
- [ ] Should return permission error

### Network Errors
- [ ] Temporarily disable Mux API credentials
- [ ] Try to generate captions
- [ ] Should show error message
- [ ] Check error logged
- [ ] Re-enable credentials
- [ ] Verify functionality restored

## Phase 7: Cross-Browser Testing

Test in multiple browsers:
- [ ] Chrome/Edge (latest)
- [ ] Firefox (latest)
- [ ] Safari (if on Mac)

For each browser:
- [ ] Settings page loads
- [ ] All dropdowns work
- [ ] AJAX requests complete
- [ ] JavaScript console clean
- [ ] Styling looks correct

## Phase 8: WordPress Compatibility

- [ ] Test with WordPress 6.4+
- [ ] Test with Classic Editor plugin
- [ ] Test with Gutenberg editor
- [ ] Test with different admin themes
- [ ] Verify no conflicts with other plugins

## Phase 9: Performance Testing

- [ ] Measure settings page load time (< 2 seconds)
- [ ] Measure AJAX request time (< 5 seconds)
- [ ] Test with 10+ videos in library
- [ ] Check for database query optimization
- [ ] Verify caching works properly

## Phase 10: Security Testing

- [ ] Verify nonce on all AJAX requests
- [ ] Attempt CSRF attack (should fail)
- [ ] Check SQL injection prevention
- [ ] Verify XSS protection in outputs
- [ ] Test capability checks enforced
- [ ] Verify signing keys not exposed in HTML

## Known Limitations (Expected)

- [ ] Encoding tier cannot be changed after upload (Mux limitation)
- [ ] Caption generation takes time (1-3 minutes typically)
- [ ] Signed URL playback requires JWT implementation (placeholder exists)
- [ ] Bulk operations not available (future feature)

## Bug Reporting Template

If you find issues, report with:
- [ ] Steps to reproduce
- [ ] Expected behavior
- [ ] Actual behavior
- [ ] Browser and version
- [ ] WordPress version
- [ ] PHP version
- [ ] Error messages (console + PHP logs)
- [ ] Screenshots if applicable

---

## Test Summary Report Template

**Date Tested**: ___________  
**Tester**: ___________  
**Environment**: ___________

**Tests Passed**: ___ / ___  
**Tests Failed**: ___ / ___  
**Blockers**: ___ (critical issues)  
**Minor Issues**: ___ (non-critical)

**Overall Status**: ☐ Pass  ☐ Pass with issues  ☐ Fail

**Notes**:
