# WP MUX Theme System

The WP MUX plugin includes a comprehensive theme system that allows you to control the appearance and behavior of MUX players throughout your WordPress site.

## Overview

The theme system allows you to:
- Create custom themes with specific control configurations
- Control which player controls are visible (play button, volume, progress bar, etc.)
- Set a default theme for all new players
- Apply themes globally or to specific players

## Available Controls

The theme system supports customization of the following MUX player elements:

### Global Controls
- **Show All Controls**: Toggle all player controls at once
- **Error Dialog**: Show/hide error messages when playback issues occur
- **Loading Indicator**: Show/hide loading spinner during video loading

### Buttons
- **Play Button**: Central play/pause button
- **Live Button**: Live indicator for live streams
- **Seek Backward Button**: Button to seek backward in the video
- **Seek Forward Button**: Button to seek forward in the video
- **Mute Button**: Volume mute/unmute button
- **Captions Button**: Closed captions toggle button
- **AirPlay Button**: Apple AirPlay casting button
- **Picture-in-Picture Button**: PiP mode toggle button
- **Fullscreen Button**: Fullscreen mode toggle button
- **Cast Button**: Google Cast button
- **Playback Rate Button**: Playback speed control button
- **Quality Menu Button**: Video quality selection menu

### Controls
- **Volume Slider**: Volume control slider
- **Progress Bar**: Video progress/seek bar
- **Current Time Display**: Current playback time display
- **Duration Display**: Total video duration display

### Sections
- **Center Controls Section**: Controls displayed in the center of the player
- **Bottom Play Button**: Play button in the bottom control bar

## Creating Themes

### Through the Admin Interface

1. Navigate to **MUX Videos > Themes** in your WordPress admin
2. Use the form on the left to create a new theme:
   - Enter a **Theme Name** (required)
   - Add an optional **Description**
   - Configure which controls should be visible using the checkboxes
3. Click **Create Theme** to save

### Editing Existing Themes

1. Go to **MUX Videos > Themes**
2. Click **Edit** on any existing theme
3. Modify the settings as needed
4. Click **Update Theme** to save changes

### Setting Default Theme

1. On the themes page, find the theme you want to set as default
2. Click **Set Default** (only visible for non-default themes)
3. This theme will now be applied to all new MUX players automatically

## Using Themes

### Automatic Application

The default theme is automatically applied to all MUX players created through:
- WordPress video blocks using MUX URLs
- Direct `<mux-player>` elements in content
- Video shortcodes

### Manual Theme Application

You can apply a specific theme to individual players by adding a CSS class:

```html
<mux-player playback-id="your-playback-id" class="wp-mux-theme-theme-id">
</mux-player>
```

Replace `theme-id` with the actual ID of your theme (shown in the admin interface).

### CSS Customization

Each theme generates CSS custom properties (CSS variables) that control the MUX player appearance. For example, a theme that hides the volume controls would generate:

```css
mux-player {
  --volume-range: none;
  --mute-button: none;
}
```

### Programmatic Usage

You can also work with themes programmatically:

```php
// Get the theme manager
$theme_manager = new Wp_Mux_Theme_Manager();

// Get all themes
$themes = $theme_manager->get_themes();

// Get a specific theme
$theme = $theme_manager->get_theme('theme-id');

// Create a new theme
$result = $theme_manager->save_theme(
    'my-theme',                    // Theme ID
    'My Custom Theme',             // Theme name
    'A theme for video galleries', // Description
    array(                         // Controls configuration
        'controls' => true,
        'play-button' => true,
        'volume-range' => false,
        'time-display' => false,
    )
);

// Generate CSS for a theme
$css = $theme_manager->generate_theme_css('theme-id');

// Apply theme to player HTML
$themed_player = $wp_mux_public->apply_theme_to_player($player_html, 'theme-id');
```

## Best Practices

### Theme Naming
- Use descriptive names that indicate the theme's purpose
- Examples: "Minimal Player", "Full Controls", "Gallery View", "Mobile Optimized"

### Control Selection
- Consider your audience and use case when selecting controls
- For background videos, consider hiding all controls
- For educational content, keep time displays and progress bars
- For mobile experiences, consider hiding less essential controls

### Testing
- Preview themes using the built-in preview feature
- Test themes on different devices and screen sizes
- Verify accessibility with screen readers when hiding controls

### Performance
- Themes use CSS custom properties, which are very efficient
- Each theme adds minimal CSS overhead
- Consider creating focused themes rather than many similar ones

## Troubleshooting

### Theme Not Applying
1. Check that the theme exists in **MUX Videos > Themes**
2. Verify the theme ID in your CSS class is correct
3. Ensure the MUX player element is properly formed
4. Check for CSS conflicts in your theme

### Controls Still Showing
1. Verify the control is unchecked in your theme settings
2. Check for CSS specificity issues
3. Ensure the MUX player library is loaded
4. Look for JavaScript errors in the browser console

### Default Theme Not Working
1. Confirm the default theme is set in **MUX Videos > Themes**
2. Clear any caching plugins
3. Check that the public styles are being enqueued

## Advanced Customization

### Custom CSS
You can add additional styling beyond the control visibility:

```css
.wp-mux-theme-my-theme {
  border-radius: 8px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.wp-mux-theme-my-theme::part(play-button) {
  background-color: #ff6b6b;
}
```

### JavaScript Integration
Access theme information in JavaScript:

```javascript
// Get theme data via WordPress localization
if (typeof wpMuxData !== 'undefined') {
  // Theme data would be available here
}

// Apply theme dynamically
const player = document.querySelector('mux-player');
player.classList.add('wp-mux-theme-my-theme');
```

## Reference

### Theme Data Structure
```php
array(
  'name' => 'Theme Name',
  'description' => 'Theme description',
  'controls' => array(
    'play-button' => true,
    'volume-range' => false,
    // ... other controls
  ),
  'created' => '2024-01-01 12:00:00',
  'modified' => '2024-01-01 12:00:00',
  'is_default' => false
)
```

### Available Hooks
The theme system provides several WordPress hooks for customization:

- `wp_mux_theme_created` - Fired when a new theme is created
- `wp_mux_theme_updated` - Fired when a theme is updated
- `wp_mux_theme_deleted` - Fired when a theme is deleted
- `wp_mux_default_theme_changed` - Fired when the default theme changes

For more information, visit the [MUX Player documentation](https://www.mux.com/docs/guides/player-customize-look-and-feel).
