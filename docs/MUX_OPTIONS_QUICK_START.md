# MUX Options Quick Start Guide

## Overview
WP Mux now supports comprehensive video configuration options including playback policies, MP4 support, encoding tiers, and automatic captions.

## Getting Started

### 1. Configure Global Defaults

Navigate to: **WP Admin → MUX Videos → Settings**

Scroll down to the **"Upload Default Settings"** section.

#### Available Settings:

**Playback Policy**
- **Public**: Anyone can play your videos (default)
- **Signed URLs**: Requires token for playback (secure content)

**MP4 Support**
- **None**: Streaming only, lowest cost (default)
- **Standard**: Enable downloadable MP4 files
- **Audio Only**: MP4 audio track only

**Encoding Tier**
- **Baseline**: Standard quality (default)
- **Smart**: Optimized encoding
- **Plus**: Premium quality
- ⚠️ Cannot be changed after upload

**Auto-Generate Captions**
- Check the box to automatically generate captions on upload
- Select default language
- 💡 Requires captions enabled on your Mux account

**Signing Key Configuration**
- Required only if using "Signed URLs" policy
- Get keys from Mux Dashboard → Settings → Signing Keys
- Enter Signing Key ID and Private Key (Base64)

### 2. Upload Videos with Your Settings

Once configured, any new videos uploaded to the Media Library will automatically use your default settings.

1. Go to **Media → Add New**
2. Upload video file
3. WP Mux applies your defaults
4. Video processes with selected options

### 3. Customize Individual Videos

You can override settings for specific videos after upload.

#### Steps:
1. Go to **Media → Library**
2. Click on any MUX video
3. Find the **"MUX Settings"** meta box
4. Adjust settings:
   - Change playback policy
   - Enable/disable MP4 support
   - Generate captions (if not auto-generated)
5. Click **"Update MUX Settings"**

Changes are sent to Mux immediately via AJAX.

## Common Use Cases

### Secure Premium Content
1. Set **Playback Policy** to "Signed URLs"
2. Configure signing keys
3. Upload videos
4. Only users with valid tokens can play

### Enable Downloads
1. Set **MP4 Support** to "Standard"
2. Upload videos
3. MP4 files become available for download

### High-Quality Videos
1. Set **Encoding Tier** to "Plus"
2. Upload videos
3. Videos encoded at premium quality
4. ⚠️ Higher processing costs

### Accessibility Compliance
1. Enable **Auto-Generate Captions**
2. Select primary language
3. Upload videos
4. Captions automatically generated
5. 💡 Can also generate manually per-video

## Generating Captions Manually

For videos uploaded before enabling auto-captions:

1. Edit the video attachment
2. Find **"MUX Settings"** meta box
3. Click **"Generate Captions"**
4. Select language
5. Click **"Confirm"**

Captions will be processed by Mux.

## Cost Considerations

### Settings That Affect Costs:

**MP4 Support**
- Increases storage costs
- Standard = full video file stored
- Consider carefully for large libraries

**Encoding Tiers**
- Baseline: Standard pricing
- Smart: Moderate increase
- Plus: Premium pricing
- Review Mux pricing before changing

**Auto-Captions**
- Per-minute fees may apply
- Check your Mux plan details
- Can be expensive at scale

**Signed URLs**
- No additional Mux costs
- Just requires key management

## Troubleshooting

### Captions Not Generating
- Verify captions are enabled in Mux dashboard
- Check Mux account has caption permissions
- Review error logs in WP Debug

### Signed URLs Not Working
- Verify Signing Key ID is correct
- Ensure Private Key is properly formatted (Base64)
- Check key has not expired in Mux dashboard

### Settings Not Saving
- Check user has appropriate permissions
- Verify Mux API credentials are valid
- Look for JavaScript errors in browser console

### Videos Not Using Defaults
- Ensure settings were saved before upload
- Check video was uploaded AFTER configuring defaults
- Existing videos retain their original settings

## Best Practices

### For Most Users
- **Playback Policy**: Public
- **MP4 Support**: None (unless downloads needed)
- **Encoding Tier**: Baseline
- **Auto-Captions**: Enabled (if budget allows)

### For Premium Content
- **Playback Policy**: Signed URLs
- **MP4 Support**: None (prevent downloads)
- **Encoding Tier**: Smart or Plus
- **Signing Keys**: Configured and secured

### For High Volume Sites
- **Playback Policy**: Public
- **MP4 Support**: None (reduce storage)
- **Encoding Tier**: Baseline (control costs)
- **Auto-Captions**: Consider manual generation for important videos only

### For Educational/Accessibility Sites
- **Playback Policy**: Public
- **MP4 Support**: Standard (allow downloads)
- **Encoding Tier**: Smart
- **Auto-Captions**: Enabled (compliance)

## FAQ

**Q: Can I change encoding tier after upload?**  
A: No, encoding tier is set at upload and cannot be changed. This is a Mux limitation.

**Q: Do existing videos get updated when I change defaults?**  
A: No, defaults only apply to NEW uploads. Edit individual videos to update.

**Q: How do I know if captions were generated?**  
A: Edit the video attachment and check the MUX Settings meta box. It will show "Captions available" with a green checkmark.

**Q: What happens if I don't configure signing keys?**  
A: Signed URLs won't work. Keep "Public" policy if not using signing keys.

**Q: Can I bulk update settings for multiple videos?**  
A: Not yet. This is a planned future enhancement. Currently, edit each video individually.

**Q: Are settings synced back from Mux?**  
A: Yes, when videos complete processing, settings are stored locally as metadata.

**Q: What languages are supported for captions?**  
A: Currently: English, Spanish, French, German, Italian, Portuguese, Japanese, Chinese. More can be added.

## Support

For issues or questions:
1. Check WordPress debug log
2. Review Mux dashboard for API errors
3. Consult Mux documentation: https://docs.mux.com
4. Contact plugin support with error details

## Related Documentation

- [Mux Encoding Tiers](https://docs.mux.com/guides/video-encoding-tiers)
- [Mux Secure Playback](https://docs.mux.com/guides/secure-video-playback)
- [Mux Captions](https://docs.mux.com/guides/add-subtitles-and-captions)
- [Mux MP4 Support](https://docs.mux.com/guides/enable-static-mp4-renditions)

---

**Version**: 1.3.0  
**Last Updated**: November 2025
