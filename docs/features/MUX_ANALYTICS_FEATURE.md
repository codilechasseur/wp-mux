# MUX Video Analytics Integration

## Overview

This feature adds comprehensive video analytics from the MUX Data API to the WordPress media library. Video stats are displayed in the attachment details sidepanel when viewing MUX videos.

## What Was Added

### 1. MUX Data API Integration (`includes/class-wp-mux-api.php`)

Added methods to fetch video analytics from MUX:

- `get_video_views()` - Fetches view count data
- `get_video_overall_metrics()` - Gets overall metrics (views, watch time, unique viewers)
- `get_video_engagement_metrics()` - Gets quality metrics (quality score, rebuffer rate, startup time)
- `get_video_analytics()` - Combines all analytics into a single response
- `format_watch_time()` - Helper to format watch time for display

### 2. Caching System (`admin/class-wp-mux-admin.php`)

Added methods to cache analytics data:

- `get_video_analytics()` - Gets cached analytics or fetches fresh data (1-hour cache)
- `clear_analytics_cache()` - Clears cached analytics for a specific video
- `format_analytics_for_display()` - Formats raw analytics for human-readable display

### 3. AJAX Refresh Handler (`admin/class-wp-mux-admin.php`)

- `ajax_refresh_video_analytics()` - AJAX endpoint to manually refresh stats
- Includes nonce verification and permission checks
- Returns formatted analytics data

### 4. Attachment Data Enhancement (`admin/class-wp-mux-admin.php`)

Modified `prepare_mux_attachment_for_js()` to include:
- Analytics data in the attachment response
- `mux_has_analytics` flag to indicate if stats are available
- Only adds analytics for videos with `complete` status

### 5. Frontend Display (`admin/js/wp-mux-media-stats.js`)

New JavaScript file that:
- Hooks into WordPress media library events
- Dynamically adds stats section to attachment details sidepanel
- Displays metrics in an organized grid layout
- Provides refresh button to update stats on demand
- Shows loading states and success/error messages
- Uses MutationObserver to handle dynamically loaded content

### 6. Styling (`admin/css/wp-mux-media-stats.css`)

Professional CSS styling with:
- Card-based layout for metrics
- Primary stats (views, unique viewers) highlighted in blue
- Quality score highlighted in green
- Responsive grid layout (2 columns → 1 column on mobile)
- Animated refresh button
- Success/error message styling
- Hover effects and transitions

### 7. Automated Refresh (`includes/class-wp-mux.php`)

Cron job implementation:
- Scheduled hourly refresh of analytics
- Processes 50 videos per run
- Only refreshes cached data older than 1 hour
- Includes rate limiting (0.1s delay between API calls)
- Cleanup on plugin deactivation

## Analytics Metrics Displayed

### Primary Metrics
- **Total Views** - Total number of video plays
- **Unique Viewers** - Number of unique viewers

### Engagement Metrics
- **Total Watch Time** - Cumulative time watched (formatted as hours/minutes)
- **Average View Duration** - Average time per view

### Quality Metrics (when available)
- **Quality Score** - MUX video quality score (0-100%)
- **Rebuffer Percentage** - How often video rebuffering occurs
- **Video Startup Time** - Average time to start playback

### Metadata
- **Last Updated** - Timestamp of last analytics refresh

## How It Works

1. **Data Fetching**: When a MUX video attachment is viewed in the media library:
   - Analytics are fetched from MUX Data API
   - Results are cached in post meta for 1 hour
   - Formatted for display

2. **Display**: The JavaScript detects MUX videos in the attachment details panel and:
   - Inserts a stats section after the attachment info
   - Shows metrics in an organized grid layout
   - Adds a refresh button

3. **Refresh**: Users can manually refresh stats:
   - Click the refresh button
   - AJAX call fetches fresh data from MUX API
   - Cache is updated
   - Display is updated with new values

4. **Automatic Updates**: Cron job runs hourly:
   - Finds all MUX videos with stale cache (>1 hour old)
   - Refreshes analytics in background
   - Processes up to 50 videos per run

## Files Modified

- `includes/class-wp-mux-api.php` - Added Data API methods
- `admin/class-wp-mux-admin.php` - Added caching, AJAX handlers, and cron
- `includes/class-wp-mux.php` - Registered AJAX actions and cron
- `includes/class-wp-mux-deactivator.php` - Added cron cleanup

## Files Created

- `admin/js/wp-mux-media-stats.js` - Frontend stats display logic
- `admin/css/wp-mux-media-stats.css` - Stats styling

## Usage

### For Users

1. Upload a video to MUX through the WordPress media library
2. Once the video is processed and has views, open it in the media library
3. Click on the video to view attachment details
4. Scroll down to see the "Video Analytics" section
5. Click the refresh button (🔄) to update stats manually

### For Developers

```php
// Get analytics for a specific attachment
$admin = new Wp_Mux_Admin( 'wp-mux', '1.0.0' );
$analytics = $admin->get_video_analytics( $attachment_id, $force_refresh );

// Format analytics for display
$formatted = $admin->format_analytics_for_display( $analytics );

// Clear cache
$admin->clear_analytics_cache( $attachment_id );
```

## MUX Data API Endpoints Used

- `/data/v1/metrics/video-views` - View counts
- `/data/v1/metrics/overall` - Overall engagement metrics
- `/data/v1/metrics/video-quality-score` - Quality and performance metrics

## Caching Strategy

- **Cache Duration**: 1 hour
- **Storage**: WordPress post meta
  - `_mux_analytics_cache` - Cached analytics data
  - `_mux_analytics_timestamp` - Cache timestamp
- **Refresh Triggers**:
  - Manual refresh via button
  - Hourly cron job
  - Opening attachment details (if cache expired)

## Rate Limiting

To avoid MUX API rate limits:
- Manual refreshes are user-initiated (not automatic on every view)
- Cron job includes 0.1s delay between requests
- Cron processes max 50 videos per run
- Cache prevents excessive API calls

## Future Enhancements

Potential improvements:
- Add time-series graphs for views over time
- Show geographic breakdown of viewers
- Display device/browser statistics
- Add export functionality for analytics data
- Create dedicated analytics dashboard page
- Add email notifications for view milestones

## Testing

To test the feature:

1. **Setup**: Ensure MUX API credentials are configured
2. **Upload**: Upload a video through WordPress
3. **Wait**: Let the video be viewed (or simulate views via MUX dashboard)
4. **View**: Open the video in media library
5. **Verify**: Check that stats appear in the sidepanel
6. **Refresh**: Click refresh button to update stats
7. **Cache**: Check that subsequent views use cached data

## Troubleshooting

### Stats Not Showing
- Verify MUX API credentials are configured correctly
- Check that video status is 'complete'
- Confirm the video has been viewed at least once
- Check browser console for JavaScript errors

### Stats Not Updating
- Click the manual refresh button
- Clear the cache manually via code
- Check MUX Data API access permissions
- Verify cron jobs are running (WP-Cron or server cron)

### JavaScript Not Loading
- Check that scripts are enqueued on media library pages
- Verify `wpMuxAnalytics` object is available in console
- Clear browser cache
- Check for JavaScript conflicts with other plugins

## Security

- AJAX requests use WordPress nonces for verification
- Permission checks ensure only users with `upload_files` capability can refresh stats
- User input is sanitized before API calls
- API credentials stored securely in WordPress options

## Performance

- Analytics are cached to minimize API calls
- Cron job processes videos in batches
- Rate limiting prevents API throttling
- Minimal JavaScript load (only on media library pages)
- CSS is lightweight and optimized

## Browser Compatibility

- Modern browsers (Chrome, Firefox, Safari, Edge)
- Uses standard WordPress media library APIs
- Graceful degradation if JavaScript disabled
- Responsive design for mobile devices

---

**Version**: 1.1.0
**Author**: Codi Lechasseur
**Date**: October 10, 2025
