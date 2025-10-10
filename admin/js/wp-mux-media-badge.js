
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
        // Remove 'View media file' link
        $el.find('.view-attachment').remove();
      }
      return this;
    }
  );
})(jQuery);
