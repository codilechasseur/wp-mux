
(function($){
  // Add MUX badge to media grid thumbnails after render
  function addMuxBadgeToGrid() {
    $('.attachment-preview.type-video.subtype-mux').each(function(){
      var $preview = $(this);
      if (!$preview.find('.mux-badge').length) {
        $preview.prepend('<span class="mux-badge">MUX</span>');
      }
    });
  }

  // Use multiple event listeners to ensure badge appears
  $(document).ready(addMuxBadgeToGrid);
  $(document).on('DOMNodeInserted', '.attachments', addMuxBadgeToGrid);
  $(document).on('ready ajaxComplete', addMuxBadgeToGrid);

  // Removed aggressive 1-second interval - badges are now added via event listeners only

  // Patch details modal for preview and details
  wp.media.view.Attachment.Details.prototype.render = _.wrap(
    wp.media.view.Attachment.Details.prototype.render,
    function(render) {
      render.apply(this, Array.prototype.slice.call(arguments, 1));
      var model = this.model.toJSON();
      if (model.subtype === 'mux' || model.mime === 'video/mux' || model.mux) {
        var $el = this.$el;
        // 1. Add preview player using playback_id - FULL WIDTH
        var playbackId = model.playback_id;
        if (playbackId && !$el.find('.mux-player').length) {
          $el.find('.attachment-media-view').html('<mux-player class="mux-player" style="width:100%;max-width:100%;min-height:300px;" stream-type="on-demand" playback-id="'+playbackId+'" controls></mux-player>');
        }
        // Hide filename and filesize
        $el.find('.filename').hide();
        $el.find('.filesize').hide();
        $el.find('.file-size').hide();

        // Update uploaded date to MUX creation date
        if (model.created_at) {
          try {
            // MUX returns Unix timestamp in seconds, convert to milliseconds
            var timestamp = parseInt(model.created_at) * 1000;
            var createdDate = new Date(timestamp);
            if (!isNaN(createdDate.getTime()) && createdDate.getFullYear() > 1970) {
              var formatOptions = { year: 'numeric', month: 'long', day: 'numeric' };
              var formattedDate = createdDate.toLocaleDateString('en-US', formatOptions);
              $el.find('.uploaded').text('Created on ' + formattedDate);
            }
          } catch(e) {
            // Error formatting date - silently fail
          }
        }

        // Remove 'View media file' link since local files don't exist
        // Try multiple selectors to catch all possible link formats
        var $viewLink = $el.find('a:contains("View media file")');
        if ($viewLink.length === 0) {
          $viewLink = $el.find('a[href*="?attachment_id="]');
        }
        if ($viewLink.length === 0) {
          $viewLink = $el.find('.actions a:contains("View")');
        }
        if ($viewLink.length > 0) {
          $viewLink.remove(); // Remove the link entirely
        }

        // Remove any orphaned/empty separator pipes
        $el.find('.actions .links-separator').each(function() {
          var $separator = $(this);
          // Check if this separator has no adjacent link (orphaned)
          var $prev = $separator.prev('a');
          var $next = $separator.next('a');

          // If no link before or after, or it's the first element, remove it
          if (($prev.length === 0 && $next.length === 0) ||
              $separator.prevAll('a, button').length === 0) {
            $separator.remove();
          }
        });
      }
      return this;
    }
  );

  // Hook into post edit page to show MUX player for video attachments
  if ($('body').hasClass('post-type-attachment')) {
    // Use a longer delay to ensure WordPress has fully loaded
    $(window).on('load', function() {
      setTimeout(function() {
        // Check if MUX data was injected
        if (typeof window.wpMuxAttachment !== 'undefined' && window.wpMuxAttachment.playback_id) {
          var playbackId = window.wpMuxAttachment.playback_id;

          // Try multiple selectors to find the preview area
          var $mediaPreview = $('.wp_attachment_holder');
          if ($mediaPreview.length === 0) {
            $mediaPreview = $('#attachment-preview');
          }
          if ($mediaPreview.length === 0) {
            $mediaPreview = $('.attachment-media-view');
          }

          // Replace/set the content with MUX player
          if ($mediaPreview.length > 0) {
            $mediaPreview.html(
              '<mux-player class="mux-player" style="width:100%;max-width:800px;min-height:450px;margin:20px auto;display:block;" ' +
              'stream-type="on-demand" playback-id="' + playbackId + '" controls></mux-player>'
            );
          }

          // Also hide any URL text that might be showing
          $('#post-body-content').find('a[href*="stream.mux.com"]').parent().hide();
        }
      }, 100);
    });
  }
})(jQuery);
