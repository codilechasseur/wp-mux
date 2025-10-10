(function($) {
    'use strict';

    // Only run if we're in the media library/upload context
    if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
        return;
    }

    $(document).ready(function() {
        // Enhanced plupload integration for MUX uploads
        if (typeof plupload !== 'undefined') {
            // Hook into the global uploader when it's ready
            $(document).on('ready', function() {
                setTimeout(function() {
                    if (window.wpUploaderInit && window.wpUploaderInit.uploader) {
                        var uploader = window.wpUploaderInit.uploader;

                        uploader.bind('FileUploaded', function(up, file, response) {
                            try {
                                var resp = JSON.parse(response.response);

                                // Check if this is a MUX upload success
                                if (resp.success && resp.data && resp.data.subtype === 'mux') {
                                    // The attachment is already created by our PHP code
                                    // Just refresh the media library to show it
                                    setTimeout(function() {
                                        refreshMediaLibrary();
                                    }, 500);
                                }
                            } catch (e) {
                                // Silent error handling
                            }
                        });

                        // Suppress error messages for MUX uploads that were successful
                        uploader.bind('Error', function(up, error) {
                            if (error.file && error.file.name && error.message) {
                                // Check if this might be a MUX file that was already processed
                                var filename = error.file.name.toLowerCase();
                                if (filename.match(/\.(mp4|mov|avi|wmv|flv|webm|mkv)$/)) {
                                    // Don't show the error - it was likely handled by our MUX upload
                                    return false;
                                }
                            }
                        });
                    }
                }, 1000);
            });
        }

        // Function to refresh media library
        function refreshMediaLibrary() {
            // Method 1: Refresh via wp.media if available
            if (wp.media.frame && wp.media.frame.content) {
                var contentView = wp.media.frame.content.get();
                if (contentView && contentView.collection) {
                    contentView.collection.fetch({reset: true});
                }
            }

            // Method 2: Refresh via media library browser if available
            if (wp.media.frame && wp.media.frame.library) {
                wp.media.frame.library.fetch({reset: true});
            }

            // Method 3: If we're in the media library page, trigger a soft refresh
            if (window.location.href.indexOf('upload.php') !== -1) {
                // Don't reload the whole page, just refresh the content
                if (window.wp && window.wp.media && window.wp.media.model && window.wp.media.model.Query) {
                    // Clear the query cache to force a refresh
                    window.wp.media.model.Query.cleanCache();
                }
            }
        }
    });
})(jQuery);
