# Quick Start Guide: MUX Video Analytics

## Installation

The analytics feature is automatically enabled with the WP MUX plugin. No additional setup required beyond having valid MUX API credentials.

## Viewing Analytics

### In Media Library

1. Go to **Media** → **Library** in WordPress admin
2. Click on any MUX video thumbnail
3. In the attachment details sidepanel (right side), scroll down
4. Look for the **"Video Analytics"** section

### Metrics Displayed

- **Total Views** - How many times the video has been played
- **Unique Viewers** - Number of different people who watched
- **Total Watch Time** - Cumulative viewing time (hours/minutes)
- **Avg. View Duration** - Average time per viewing session
- **Quality Score** - Video playback quality rating (if available)

### Refresh Button

Click the 🔄 refresh icon in the analytics section header to fetch the latest stats from MUX.

## What to Expect

### When You First Upload a Video

- Analytics section will NOT appear immediately
- Video must be **fully processed** by MUX
- Video must have **at least 1 view** before stats appear

### After Video Has Views

- Stats appear automatically when viewing attachment details
- Data is cached for **1 hour** to improve performance
- Manual refresh available anytime via the refresh button
- Stats auto-update hourly in the background

### Typical Timeline

1. **Upload**: Video uploaded to MUX (0-5 minutes)
2. **Processing**: MUX processes video (5-30 minutes depending on length)
3. **First View**: Someone watches the video
4. **Analytics Available**: Stats appear within 1-5 minutes after first view
5. **Updates**: Stats refresh automatically every hour

## API Requirements

### Required MUX API Permissions

Your MUX API credentials must have access to:
- **MUX Video API** (for video management) ✓ Already configured
- **MUX Data API** (for analytics) ← New requirement

### Checking Permissions

1. Log into your MUX dashboard
2. Go to **Settings** → **Access Tokens**
3. Find your token used for WP MUX
4. Verify it has **"Read" permissions for "Data"**

If not, you may need to:
- Create a new access token with Data API access
- Update your WP MUX plugin settings with the new token

## Troubleshooting

### "No analytics available yet"

**Possible causes:**
- Video hasn't been viewed yet → Share the video and get some views
- MUX Data API access not enabled → Check token permissions
- Cache issue → Click the refresh button

### Stats not updating

**Solutions:**
1. Click the manual refresh button (🔄)
2. Wait 5-10 minutes for MUX to process new views
3. Check if WordPress cron is running (wp-cron.php)

### Refresh button doesn't work

**Check:**
- Browser console for JavaScript errors (F12)
- WordPress admin AJAX functionality
- Network tab shows successful AJAX call
- MUX API credentials are valid

### Stats show "0" for everything

**This is normal if:**
- Video just got its first view (wait 5 minutes)
- MUX hasn't processed the view data yet
- Video is brand new with no views

## Performance Notes

### Caching Behavior

- Analytics are cached for **1 hour**
- Reduces API calls to MUX
- Improves media library load times
- Background refresh every hour via cron

### Manual Refresh

- Use sparingly to avoid rate limits
- Best practice: Check stats once per hour
- Automatic updates handle most use cases

### System Load

- Minimal impact on WordPress
- API calls are throttled
- Only processes 50 videos per cron run
- JavaScript only loads on media library pages

## Understanding the Metrics

### Total Views
Number of times someone clicked play on your video. Each play session counts as one view.

### Unique Viewers
Number of different people who watched. Uses IP address and device fingerprinting to identify unique viewers.

### Total Watch Time
Sum of all viewing time across all viewers. Example: If 10 people each watch for 5 minutes, total is 50 minutes.

### Average View Duration
Total watch time divided by total views. Indicates how engaging your video is. Compare to video length to see completion rate.

### Quality Score
MUX's proprietary score indicating playback quality. Factors include:
- Startup time
- Rebuffering rate
- Video resolution
- Playback failures

Higher is better (aim for 80%+).

## Tips for Best Results

### Getting Accurate Analytics

1. **Share your videos** - Analytics only update when videos are actually viewed
2. **Wait for processing** - Fresh uploads need time to process
3. **Check hourly** - Don't spam the refresh button
4. **Monitor trends** - Look at changes over time, not just absolute numbers

### Maximizing View Metrics

1. **Embed videos** prominently on your site
2. **Share links** on social media
3. **Add to email** newsletters
4. **Optimize thumbnails** to increase click-through
5. **Keep videos** engaging to increase watch time

## Advanced Usage

### For Developers

See `MUX_ANALYTICS_FEATURE.md` for:
- API method documentation
- Code examples
- Integration patterns
- Customization options

### Scheduled Reports

Currently, stats are view-on-demand. Future versions may include:
- Email reports
- Scheduled exports
- Dashboard widgets
- Performance comparisons

## Support

### Common Questions

**Q: Why don't I see analytics for my video?**
A: The video needs to be viewed at least once after being fully processed by MUX.

**Q: How often do stats update?**
A: Cached stats refresh every hour. Use the manual refresh button for immediate updates.

**Q: Do analytics affect site performance?**
A: No, analytics are cached and loaded only when viewing attachment details.

**Q: Can I export analytics data?**
A: Not yet, but this is planned for a future update.

**Q: What if I hit API rate limits?**
A: The caching system prevents this. Manual refreshes are user-controlled.

### Need Help?

- Check WordPress admin → MUX Videos → Settings
- Verify API credentials are correct
- Review browser console for errors
- Check PHP error logs for server-side issues

---

**Happy analyzing!** 📊

Your video stats are now just a click away in the WordPress media library.
