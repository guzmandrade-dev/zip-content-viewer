
/**
 * Frontend JavaScript for ZIP Content Viewer
 */

document.addEventListener( 'DOMContentLoaded', function() {
	const iframes = document.querySelectorAll( '.wp-block-telex-zip-content-viewer iframe' );
	
	iframes.forEach( function( iframe ) {
		// Add responsive handling if needed
		iframe.addEventListener( 'load', function() {
			// Iframe loaded successfully
		} );
	} );
} );
	