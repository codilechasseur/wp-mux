# 🎬 Mux Poster/Thumbnail Integration - Quick Start Guide

## What's New?

Your WP-MUX plugin now integrates Mux's powerful thumbnail generation API with WordPress Gutenberg's native video controls!

**You can now:**
- ✨ Capture any frame from your Mux videos as a poster
- 🎞️ Create animated GIF previews
- 📐 Customize thumbnail dimensions
- 🔄 Toggle between custom uploads and Mux generation
- 👀 See live URL previews in the editor

## Quick Start (30 seconds)

### 1. Add a Mux Video
- Create/edit a post
- Add a Video block
- Select your Mux video

### 2. Open Video Settings
- Look in the right sidebar
- The standard **"Video Settings"** panel now includes Mux controls
- All settings are in one place - seamless!

### 3. Configure Your Poster
**Choose Your Source:**
- Toggle **"Use Mux-generated poster"** on/off
- When ON: Mux will generate your poster
- When OFF: Use the "Poster image" button to upload custom image

**Customize Mux Poster (when enabled):**
- **Capture time**: Pick which moment from the video (slider)
- **Animated GIF**: Toggle for animated preview
- **Advanced dimensions**: Expand to customize width/height

### 4. Preview & Save
- See generated URL at bottom of Mux controls
- Save your post
- View on frontend - your poster is live! 🎉

## Common Use Cases

### Scenario 1: Pick the Best Thumbnail
```
Problem: Your video starts with a black screen
Solution: Set "Thumbnail time" to 5 seconds (or when action starts)
```

### Scenario 2: High-Quality Thumbnail for Hero Section
```
Problem: Default thumbnail is too small/blurry
Solution: Set width to 1920px, height to 1080px
```

### Scenario 3: Engaging Animated Preview
```
Problem: Static thumbnail doesn't show what video is about
Solution: Toggle "Use animated GIF" ON, set start time
```

### Scenario 4: Branded Poster with Logo
```
Problem: Need specific branding on thumbnail
Solution: Toggle "Use custom uploaded poster image", upload branded image
```

## Shortcode Users

Add parameters to your `[mux]` shortcode:

```
[mux playback_id="abc123" poster_time="5" poster_width="1280" poster_height="720"]
```

Available parameters:
- `poster_time` - Frame time in seconds
- `poster_width` - Width in pixels
- `poster_height` - Height in pixels
- `animated_poster` - Set to "true" for GIF
- `poster` - Custom URL (overrides Mux)

## Tips & Best Practices

### 📸 Choosing Thumbnail Time
- **Tutorials**: Pick a moment showing the key technique
- **Vlogs**: Choose when speaker is engaged/smiling
- **Action videos**: Select high-energy moment
- **Interviews**: Capture an expressive moment

### 📏 Dimension Guidelines
- **16:9 aspect ratio**: Width:Height = 1.77:1
  - Example: 1280x720, 1920x1080, 640x360
- **4:3 aspect ratio**: Width:Height = 1.33:1
  - Example: 640x480, 1024x768
- Match your player's display size for best results

### ⚡ Performance Tips
- Static thumbnails load faster than animated GIFs
- Use smaller dimensions for video grids/lists
- Larger dimensions for featured/hero videos
- Animated GIFs: Great for engagement, use sparingly

### 🎨 When to Use Custom Posters
- Need text overlays or graphics
- Specific branding requirements
- Video doesn't provide good frame options
- Want consistent style across all videos

## Troubleshooting

**Thumbnail not showing?**
- Check video has finished processing in Mux
- Try a different time offset
- Verify playback ID is correct
- Clear browser cache

**Settings panel not appearing?**
- Make sure video is a Mux video (not regular upload)
- Check if video is still processing
- Refresh the editor

**Animated GIF not working?**
- Animated GIFs are large files - check Network tab
- Try smaller dimensions
- Ensure video is longer than the start time

## Learn More

- 📖 [Full Documentation](./MUX_POSTER_INTEGRATION.md)
- 🧪 [Testing Examples](./TESTING_EXAMPLES.html)
- 📝 [Implementation Summary](../../../MUX_POSTER_FEATURE_SUMMARY.md)
- 🔗 [Mux Image API Docs](https://www.mux.com/docs/guides/get-images-from-a-video)

## What's Under the Hood?

### Mux Image API
The plugin uses Mux's Image API to generate thumbnails on-the-fly:

**Static:**
```
https://image.mux.com/{PLAYBACK_ID}/thumbnail.jpg?time=5&width=640&height=360&fit_mode=smartcrop
```

**Animated:**
```
https://image.mux.com/{PLAYBACK_ID}/animated.gif?start=5&width=640&height=360&fps=15
```

### Block Attributes
Settings are stored in your post content as block attributes:
```json
{
  "mux_poster_time": 5,
  "mux_poster_width": 1280,
  "mux_poster_height": 720,
  "mux_use_animated_gif": false,
  "mux_use_custom_poster": false
}
```

## Support

Having issues?
1. Check the [Full Documentation](./MUX_POSTER_INTEGRATION.md)
2. Review [Testing Examples](./TESTING_EXAMPLES.html)
3. Check browser console for errors
4. Verify Mux video is fully processed

## That's It! 🚀

You're ready to create stunning video posters with Mux! The feature is designed to be intuitive and seamless with WordPress's native controls.

**Remember**: You always have full control - use Mux generation or custom uploads, it's your choice!
