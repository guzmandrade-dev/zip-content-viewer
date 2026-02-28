
=== ZIP Content Viewer ===

Contributors:      WordPress Telex
Tags:              block, zip, iframe, file upload
Tested up to:      6.8
Stable tag:        0.1.0
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html
A block that extracts ZIP files and displays HTML content in an iframe.

== Description ==

The ZIP Content Viewer block allows you to upload ZIP files containing HTML projects, extract them to your WordPress uploads directory, and display the selected HTML file in an iframe on your site.

Features:
* Upload ZIP files directly in the block editor
* Automatic extraction to wp-content/uploads
* Browse extracted files and select an HTML file to display
* Responsive iframe rendering on the frontend
* Clean file management interface

Perfect for embedding HTML5 games, interactive presentations, documentation sites, or any self-contained HTML projects.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/zip-content-viewer` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Add the ZIP Content Viewer block to any post or page
4. Upload your ZIP file and select an HTML file to display

== Frequently Asked Questions ==

= What file types are supported? =

The block accepts ZIP files containing HTML, CSS, JavaScript, images, and other web assets. You must select an HTML file as the entry point for display.

= Where are the files stored? =

Extracted files are stored in wp-content/uploads/zip-extracts/ with a folder name based on your ZIP file.

= Can I change the displayed HTML file? =

Yes, you can select a different HTML file from the extracted contents at any time in the block editor.

= What happens if I upload a new ZIP file? =

The previous extraction will be replaced with the new ZIP file's contents.

== Screenshots ==

1. Block interface showing ZIP upload and file selection
2. Frontend display with iframe rendering HTML content

== Changelog ==

= 0.1.0 =
* Initial release
* ZIP file upload and extraction
* HTML file selection interface
* Iframe rendering on frontend

== Support ==

For issues or feature requests, please contact WordPress Telex support.
