# Mux Poster/Thumbnail Integration with Gutenberg

## Overview

This plugin integrates Mux's powerful thumbnail and poster image generation capabilities with WordPress Gutenberg's native video block controls. You can use either Mux-generated thumbnails or upload custom poster images, giving you maximum flexibility.

## Features

### 1. **Seamless Native Gutenberg Integration**
- Works with the native WordPress video block's poster image control
- Option to use custom uploaded posters or Mux-generated thumbnails
- Preserves all standard Gutenberg video block functionality

### 2. **Mux Thumbnail Customization**
When using Mux-generated thumbnails, you have full control over:
- **Time offset**: Capture a frame at any point in the video (0-120 seconds)
- **Dimensions**: Custom width and height (320-1920px width, 180-1080px height)
- **Animated GIFs**: Option to use an animated GIF instead of a static image
- **Smart cropping**: Automatic intelligent cropping with `smartcrop` fit mode

### 3. **Live Preview**
- See the generated thumbnail URL in the editor
- URL updates in real-time as you adjust settings
- Easy to copy for external use

## Using the Feature

### In Gutenberg Editor

1. **Add a video block** and select a Mux video from your media library

2. **Open Video Settings** in the right sidebar (the standard panel)

3. **Configure poster options** (all integrated in one place):

   **Choose your poster source:**
   - Toggle **"Use Mux-generated poster"**
     - ON = Mux generates the poster (default)
     - OFF = Upload custom poster via "Poster image" button

   **When using Mux poster:**
   - **Capture time**: Slider to pick which moment (0-120 seconds)
   - **Animated GIF**: Toggle for animated vs static
   - **Advanced dimensions** (expand for width/height control)
   - **Generated URL**: Preview shown at bottom

4. **Save your post** - Settings are preserved in block attributes

### Mux Thumbnail Settings

When using Mux-generated thumbnails, configure:

#### **Thumbnail Time (seconds)**
- Range: 0-120 seconds
- Default: 0 (start of video)
- For animated GIFs, this is the start time of the animation
- Example: Set to 5.0 to capture a frame 5 seconds into the video

#### **Thumbnail Width (px)**
- Range: 320-1920 pixels
- Default: 640
- Recommended: Match your video player dimensions

#### **Thumbnail Height (px)**
- Range: 180-1080 pixels
- Default: 360
- Recommended: Match your video player aspect ratio

#### **Use Animated GIF**
- Default: Off (static image)
- When enabled: Creates an animated GIF preview instead of a static thumbnail
- Note: Animated GIFs have larger file sizes

## API Reference

### Mux Image API

The plugin uses Mux's Image API to generate thumbnails:

**Static Thumbnails:**
```
https://image.mux.com/{PLAYBACK_ID}/thumbnail.jpg?time={time}&width={width}&height={height}&fit_mode=smartcrop
```

**Animated GIFs:**
```
https://image.mux.com/{PLAYBACK_ID}/animated.gif?start={time}&width={width}&height={height}&fps=15
```

### Block Attributes

The following custom attributes are added to the `core/video` block:

```javascript
{
  mux_use_custom_poster: boolean,  // Use custom uploaded poster vs Mux thumbnail
  mux_poster_time: number,          // Time in seconds (0-120)
  mux_poster_width: number,         // Width in pixels (320-1920)
  mux_poster_height: number,        // Height in pixels (180-1080)
  mux_use_animated_gif: boolean,    // Use animated GIF vs static image
  mux_theme: string                 // Player theme (existing)
}
```

### Shortcode Support

When using the `[mux]` shortcode, you can also customize poster images:

```
[mux playback_id="YOUR_PLAYBACK_ID" poster_time="5.0" poster_width="1280" poster_height="720"]
```

Available shortcode parameters:
- `poster`: Custom poster image URL (overrides Mux generation)
- `poster_time`: Time in seconds for thumbnail capture
- `poster_width`: Width in pixels
- `poster_height`: Height in pixels
- `poster_fit`: Fit mode (smartcrop, crop, preserve)
- `animated_poster`: Set to "true" for animated GIF

## PHP API

### Helper Function

The plugin provides a helper function to generate Mux thumbnail URLs:

```php
// In Wp_Mux_Public class
private function generate_mux_thumbnail_url( $playback_id, $options = array() )
```

**Parameters:**
- `$playback_id` (string): The Mux playback ID
- `$options` (array): Optional parameters
  - `time` (float): Time in seconds (default: 0)
  - `width` (int): Width in pixels (default: 640)
  - `height` (int): Height in pixels (default: 360)
  - `fit_mode` (string): Fit mode (default: 'smartcrop')
  - `animated` (bool): Generate animated GIF (default: false)

**Example:**
```php
$thumbnail_url = $this->generate_mux_thumbnail_url( 'abc123', array(
    'time'     => 5.5,
    'width'    => 1280,
    'height'   => 720,
    'animated' => false
) );
```

## Best Practices

### 1. **Choosing the Right Time Offset**
- For action videos: Pick a moment with high visual interest
- For talking head videos: Choose when the speaker is engaged
- Test different times to find the most appealing thumbnail

### 2. **Dimension Guidelines**
- Use dimensions that match your player's display size
- Maintain your video's aspect ratio (16:9, 4:3, etc.)
- Higher resolutions look better but increase load time

### 3. **Static vs Animated**
- **Static thumbnails**:
  - Smaller file size
  - Better for most use cases
  - Faster page loads
- **Animated GIFs**:
  - More engaging
  - Better for previewing content
  - Larger file sizes (consider performance)

### 4. **Custom Posters**
Use custom uploaded posters when:
- You need a specific branded image
- You want text overlays or graphics
- The video content doesn't provide good thumbnail options

### 5. **Performance Considerations**
- Mux serves images from a CDN (fast loading)
- Consider using smaller dimensions for grid/list views
- Animated GIFs should be used sparingly on pages with many videos

## Troubleshooting

### Thumbnail Not Showing
1. Verify the Mux video has completed processing
2. Check browser console for image loading errors
3. Ensure playback ID is correct
4. Try a different time offset (video might be shorter than expected)

### Custom Poster Not Appearing
1. Ensure "Use custom uploaded poster image" is checked
2. Verify you've uploaded an image using the native poster control
3. Clear browser cache
4. Check if the uploaded image URL is accessible

### Animated GIF Not Playing
1. Animated GIFs may be large - check network tab for loading status
2. Ensure browser supports animated GIFs (all modern browsers do)
3. Try reducing dimensions to decrease file size
4. Check if video is long enough for the start time

## Technical Details

### How It Works

1. **Editor Side**: The block editor JavaScript extends the native video block's "Video Settings" panel with Mux-specific controls
2. **Seamless Integration**: Mux controls appear right alongside native WordPress controls (no separate panels)
3. **Attribute Storage**: Settings are stored as block attributes in the post content
4. **Frontend Rendering**: The PHP `process_video_blocks()` method reads block attributes and generates appropriate poster URLs
5. **Mux Player**: The generated poster URL is set as the `poster` attribute on the `mux-player` element

### Filter Integration

The poster URL generation respects WordPress's native poster attribute:
- If user sets custom poster: Uses that URL
- If `mux_use_custom_poster` is true: Uses native WordPress poster
- Otherwise: Generates Mux thumbnail with custom parameters

## Related Mux Documentation

- [Mux Image API - Get Thumbnail Images](https://www.mux.com/docs/guides/get-images-from-a-video)
- [Mux Image API - Animated GIFs](https://www.mux.com/docs/guides/get-images-from-a-video#get-an-animated-gif-from-a-video)
- [Mux Image API - Reference](https://docs.mux.com/api-reference/video#operation/get-asset-input-info)

## Version History

- **1.0.0** - Initial poster integration implementation
  - Added Mux thumbnail customization options
  - Added animated GIF support
  - Integrated with native Gutenberg poster controls
  - Added shortcode parameter support
