=== WP Mux ===
Contributors: codilechasseur
Tags: video, mux, streaming, media, video hosting, hls, video player
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.3.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Seamlessly integrate Mux video streaming platform with your WordPress media library for professional video hosting and playback.

== Description ==

WP Mux brings professional video streaming to WordPress by integrating with Mux, the powerful video hosting and streaming platform. Upload videos directly through your WordPress media library and deliver them via Mux's lightning-fast CDN with adaptive bitrate streaming.

= Key Features =

* **Direct Media Library Integration** - Upload videos through WordPress and automatically send them to Mux
* **Professional Video Player** - Beautiful, customizable Mux Player with full controls
* **Adaptive Streaming** - Automatic quality adjustment based on viewer's connection speed
* **Video Analytics** - Track views, watch time, and engagement metrics
* **Custom Player Themes** - Create and manage multiple player themes
* **Gutenberg Support** - Native video block integration
* **Signed URLs** - Protect your videos with automatic signing key management
* **Bulk Migration** - Migrate existing WordPress videos to Mux
* **Multiple Quality Tiers** - Choose from baseline, smart, or premium encoding

= Perfect For =

* Content creators and video publishers
* Membership sites with video courses
* Marketing teams sharing product demos
* Anyone who needs reliable, professional video delivery

= What is Mux? =

Mux is a video hosting platform that handles encoding, storage, and delivery of your videos. It provides:

* Global CDN for fast video delivery
* Automatic transcoding to multiple formats
* Adaptive bitrate streaming (HLS)
* Real-time analytics and quality monitoring
* 99.9% uptime SLA

You'll need a Mux account to use this plugin. Sign up at [mux.com](https://mux.com).

= Documentation =

For detailed setup instructions and feature guides, visit the plugin documentation.

== Installation ==

= Composer =

Add the GitHub repository and require the plugin from your site's `composer.json`:

`composer config repositories.wp-mux vcs https://github.com/codilechasseur/wp-mux`
`composer require codilechasseur/wp-mux:^1.2`

Tagged releases (`v1.2.0`, `v1.2.1`, ...) are picked up by `composer update codilechasseur/wp-mux`.

= Automatic Installation =

1. Log in to your WordPress admin panel
2. Go to Plugins > Add New
3. Search for "WP Mux"
4. Click "Install Now" and then "Activate"

= Manual Installation =

1. Download the plugin zip file
2. Go to Plugins > Add New > Upload Plugin
3. Choose the zip file and click "Install Now"
4. Activate the plugin

= Setup =

1. Go to MUX Videos > Settings
2. Enter your Mux API credentials (Access Token and Secret Key)
3. Get your credentials from [dashboard.mux.com](https://dashboard.mux.com) under Settings > Access Tokens
4. Configure your default upload settings
5. Start uploading videos!

= Quick Start =

After setup, you can:

* Upload videos through Media > Add New
* Enable "Override Video Uploads" in settings to automatically send all video uploads to Mux
* Use videos in posts/pages via the Gutenberg video block
* View video analytics in the media library
* Manage all Mux videos from MUX Videos > All Videos

== For Theme Developers ==

= Template functions =

`wp_mux_player( $attachment_id, $args )` returns a video attachment as a player: the Mux Player when the video is on Mux, a plain `<video>` for a local file (for example one Mux hasn't finished processing), and an empty string when the attachment isn't a video. The Mux Player script is loaded only on pages that use it.

`wp_mux_is_mux_video( $attachment_id )` and `wp_mux_get_playback_id( $attachment_id )` tell you whether a video plays from Mux.

    // A hero background: muted loop, cropped to fill, your image as the poster.
    echo wp_mux_player( $video_id, [
        'background' => true,
        'poster'     => $image_id,
        'class'      => 'hero__media',
    ] );

Arguments:

* `controls`, `autoplay`, `muted`, `loop` (booleans; controls default on)
* `preload`: `auto`, `metadata` or `none`
* `poster`: an image attachment ID (shown responsively) or a URL; Mux's thumbnail by default
* `fit`: `cover` or `contain`, how the video fills its box
* `class`, `style`, `width`, `height`
* `background`: a muted, looping video without controls that plays only while it's on screen, starts paused for visitors who prefer reduced motion, and has a pause button. The poster shows until it plays. It's wrapped in `.wp-mux-background` (which gets `[data-paused]` while paused), and `class` and `style` go on that wrapper.
* `pause_button`: set to false to leave the pause button out. Only do this if you provide your own way to pause, because moving video needs one (WCAG 2.2.2).
* `pause_button_class`: extra classes for the pause button, for example your theme's button classes

The plugin's background styles have zero specificity, so your own classes always win.

= Shortcode =

`[video]` and `wp_video_shortcode()` play Mux stream URLs (`wp_get_attachment_url()` of a Mux video) in the Mux Player. They take the same arguments as attributes, with booleans as `on` / `off`, for example `[video src="…" background="on"]`.

= Filters =

* `wp_mux_player_script_url`: the Mux Player script URL (pinned to a tested release), for example to host it yourself.

== Frequently Asked Questions ==

= Do I need a Mux account? =

Yes, you need a Mux account to use this plugin. Sign up at [mux.com](https://mux.com). They offer a free trial with generous limits.

= What happens to my videos if I uninstall the plugin? =

By default, all your data is preserved when you uninstall. If you enable "full cleanup" in settings, your WordPress attachments are converted to disconnected placeholders (preserving attachment IDs so Gutenberg blocks don't break), but videos remain on Mux. You can reinstall and reconnect them anytime.

= Will my Gutenberg video blocks break if I uninstall? =

No! We preserve attachment IDs specifically to prevent this. Videos won't play while the plugin is uninstalled, but the blocks remain intact and will work again when you reinstall.

= Are videos automatically deleted from Mux when I uninstall? =

No. Videos on Mux are NEVER automatically deleted on uninstall. You must manually delete them from your Mux dashboard at [dashboard.mux.com](https://dashboard.mux.com) to stop billing charges.

= Can I migrate my existing WordPress videos to Mux? =

Yes! Go to MUX Videos > Migrate Videos to bulk migrate your existing video files to Mux.

= Do I need to enable MP4 support? =

MP4 support is optional. It allows users to download videos directly but increases storage costs on Mux. Most users only need HLS streaming which is enabled by default.

= Can I protect my videos? =

Yes! Enable "Signed URLs" in upload settings. The plugin automatically manages signing keys for you, creating secure tokens for each viewer.

= What video formats are supported? =

Mux accepts virtually any video format (MP4, MOV, AVI, MKV, WebM, etc.). It automatically transcodes them to HLS for streaming.

= How do I customize the video player? =

Go to MUX Videos > Themes to create custom player themes with different colors, controls, and behaviors.

= Can I see video analytics? =

Yes! Click on any video in your media library to view detailed analytics including views, watch time, quality scores, and more.

= What's the difference between encoding tiers? =

* **Baseline**: Standard quality, lowest cost
* **Smart**: Optimized encoding with better quality-to-size ratio
* **Plus**: Premium quality with advanced encoding

Note: Encoding tier cannot be changed after upload.

= Can I use this with Gutenberg? =

Yes! Full Gutenberg support. Simply use the video block and select your Mux video from the media library.

= Does this work with page builders? =

The plugin works with any WordPress site. For page builders, insert videos using their media/video modules and select from your media library.

= What happens if Mux is down? =

Mux has 99.9% uptime. In the rare case of an outage, videos will display a loading state until service is restored. Your WordPress site remains fully functional.

== Screenshots ==

1. Plugin settings page with API credentials and upload defaults
2. Video management interface showing all Mux videos
3. Media library with Mux videos and analytics
4. Video analytics dashboard with engagement metrics
5. Player theme editor for customization
6. Bulk video migration interface

== Changelog ==
= 1.3.1 =
* Fix: Critical error after saving API keys on accounts with existing videos (Mux timestamps crashed the media library sync)

= 1.3.0 =
* New: `wp_mux_player()`, `wp_mux_is_mux_video()` and `wp_mux_get_playback_id()` for themes. `wp_mux_player()` renders any video attachment, from Mux or local
* New: Background videos (`background` argument, `[video background="on"]`). They're muted and looping without controls, and only play while on screen. They start paused for reduced motion, have an accessible pause button, and show the poster until they play
* New: `poster` takes an image attachment ID; `fit` (cover / contain) for how a video fills its box
* Fix: Converting a Mux video in post content no longer breaks the other videos in the same post
* Removed the public script's two-second polling for videos. New content is still picked up as it's added
= 1.2.1 =
* Fix: Mux videos in [video] shortcodes and wp_video_shortcode() now render the Mux Player instead of a plain link, honouring loop, muted, autoplay, preload, poster, class and style; new controls="off" for background video
* Fix: PHP warning (an exception on Acorn sites) for every Mux video in the media modal
* Mux Player is pinned to a fixed release (filter: wp_mux_player_script_url) and only loaded on pages with a player
* Composer installs no longer include development files

= 1.2.0 =
* All features are now free: removed Freemius licensing, bulk migration and upload routing are no longer gated
* New: Cookieless mode for the player
* New: Option to disable Mux Data analytics tracking
* Installable via Composer from GitHub

= 1.1.1 =
* Initial public release
* Smart uninstall system that preserves attachment IDs
* Automatic signing key management for private videos
* Media library integration with analytics
* Custom player themes
* Bulk video migration tool
* Gutenberg block support
* Player options for quality control
* Account statistics overview

== Upgrade Notice ==

= 1.2.0 =
Every feature is now free. Adds cookieless and no-analytics player options.

= 1.1.1 =
First stable release! Professional video streaming for WordPress with Mux integration.
