# WP MUX Bulk Migration Feature

## Overview

The bulk migration feature allows site administrators to migrate all existing locally hosted videos to MUX in a single batch operation. This is ideal for sites that already have a video library before installing WP MUX.

## Features

### 1. **Automatic Discovery**
- Scans your WordPress media library for locally hosted video files
- Detects all video formats supported by MUX
- Only shows videos that haven't been migrated yet

### 2. **Conditional Menu Item**
- The "Migrate Videos" menu item only appears under "MUX Videos" if local videos are detected
- Uses transient caching for performance
- Automatically hides once all videos are migrated

### 3. **Smart Migration Process**
- Preserves original upload dates for each video
- Processes videos one at a time to avoid server overload
- Shows real-time progress with:
  - Overall progress bar
  - Current video being processed
  - Live migration log
  - Success/failure status for each video

### 4. **Flexible Controls**
- Pause and resume migration at any time
- Review video list before starting
- See estimated time and total size
- Retry failed migrations individually

### 5. **Optional File Deletion**
- Setting in MUX Settings to automatically delete local video files after successful migration
- Helps free up server storage space
- Optional and disabled by default for safety
- Clear warning about permanent deletion

## How to Use

### Step 1: Access the Migration Tool
1. Go to **MUX Videos > Migrate Videos** in your WordPress admin
2. The menu item will only appear if you have local videos that can be migrated

### Step 2: Configure Settings (Optional)
1. Go to **MUX Videos > Settings**
2. Enable **"Delete Local Files After Migration"** if you want to free up server storage
   - ⚠️ Warning: This will permanently delete the original video files
   - Make sure you have backups if needed

### Step 3: Scan for Videos
1. Click the **"Scan for Local Videos"** button
2. The system will search your media library for videos that need migration
3. Review the scan results:
   - Total number of videos found
   - Total size of all videos
   - Estimated migration time
   - List of all videos with details

### Step 4: Start Migration
1. Click **"Start Migration"** to begin
2. Monitor the progress:
   - Watch the overall progress bar
   - See which video is currently being processed
   - Review the migration log for details
3. Use **Pause** if you need to stop temporarily
4. Use **Resume** to continue after pausing

### Step 5: Review Results
- Once complete, you'll see:
  - Number of successfully migrated videos
  - Number of failed migrations (if any)
  - List of failed videos with error messages
- Successfully migrated videos will:
  - Have their original upload date preserved
  - Be accessible via MUX playback
  - Have a MUX video type in the media library
  - Optionally have local files deleted (if enabled in settings)

## Technical Details

### Video Formats Supported
- MP4 (video/mp4)
- MPEG (video/mpeg)
- QuickTime (video/quicktime)
- AVI (video/avi, video/x-msvideo)
- WebM (video/webm)
- OGG (video/ogg)
- WMV (video/wmv, video/x-ms-wmv)
- MKV (video/mkv, video/x-matroska)

### Migration Process
For each video:
1. Verifies the video file exists
2. Creates a MUX direct upload URL
3. Uploads the video to MUX
4. Waits for MUX to process the upload
5. Retrieves the asset ID and playback ID
6. Stores MUX metadata in WordPress
7. Updates the mime type to 'video/mux'
8. Restores the original upload date
9. Optionally deletes the local file

### Performance Considerations
- Videos are processed sequentially (one at a time)
- Default timeout: 5 minutes per video
- Estimated time: ~30 seconds per video
- Uses non-blocking AJAX requests
- Can be paused and resumed safely

### Data Preserved
- Original upload date
- Video title
- Video description
- WordPress attachment metadata
- All post relationships

### What Gets Updated
- Post mime type changes to 'video/mux'
- MUX metadata added:
  - `_mux_upload_id`
  - `_mux_asset_id`
  - `_mux_playback_id`
  - `_mux_thumbnail_url`
  - `_wp_mux_status` (set to 'complete')
  - `_mux_migrated` (set to '1')

## Troubleshooting

### Menu Item Not Showing
- **Cause**: No local videos found that need migration
- **Solution**: Check your media library for video files

### Migration Fails
Possible causes:
1. **MUX API not configured**: Go to Settings and add your API credentials
2. **File not found**: The video file may have been deleted from the server
3. **Timeout**: Large video files may exceed the timeout limit
4. **Network error**: Check your server's internet connection
5. **MUX API error**: Check your MUX account status and API limits

### Local File Not Deleted
- Check that the setting is enabled in MUX Settings
- Verify file permissions on the server
- Check the migration log for specific errors

### Original Date Not Preserved
- This should happen automatically
- If you notice issues, please report them

## Safety Features

1. **No Data Loss**: Original videos remain until migration is confirmed successful
2. **Optional Deletion**: Local file deletion must be explicitly enabled
3. **Pause/Resume**: Stop and restart migration at any time
4. **Error Handling**: Failed migrations are logged and can be retried
5. **Transactional**: Each video is handled independently

## API Endpoints

The feature adds these AJAX endpoints:

- `wp_mux_scan_local_videos`: Scans for videos to migrate
- `wp_mux_migrate_single_video`: Migrates one video
- `wp_mux_get_migration_status`: Gets migration status

All endpoints require:
- Valid nonce (`wp_mux_migration_nonce`)
- `manage_options` capability

## Files Added

### Backend
- `admin/class-wp-mux-admin.php` (modified)
  - Added `display_migration_page()`
  - Added `has_local_videos()`
  - Added `ajax_scan_local_videos()`
  - Added `ajax_migrate_single_video()`
  - Added `ajax_get_migration_status()`
  - Added `delete_local_after_migration_field_callback()`

- `admin/partials/wp-mux-admin-migration.php` (new)
  - Migration page UI

- `includes/class-wp-mux.php` (modified)
  - Registered AJAX actions

### Frontend
- `admin/js/wp-mux-migration.js` (new)
  - JavaScript for migration UI and AJAX handling

- `admin/css/wp-mux-migration.css` (new)
  - Styles for migration page

### Settings
- `wp_mux_delete_local_after_migration` option added
- Displayed in Settings page with warning

## Future Enhancements

Potential improvements for future versions:
- Batch parallel processing for faster migration
- Progress persistence (survive page reload)
- Email notification when complete
- Scheduled migrations (cron job)
- Rollback/undo functionality
- Pre-migration file backup
- Detailed migration reports
- Filter by date range or file size
- Custom file deletion rules

## Version History

- **v1.2.0**: Initial release of bulk migration feature
