<?php
/**
 * Plugin Name:       ZIP Content Viewer
 * Description:       Extracts ZIP files and displays HTML content in an iframe.
 * Version:           0.1.0
 * Requires at least: 6.1
 * Requires PHP:      7.4
 * Author:            h4l9k
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       zip-content-viewer
 *
 * @package TelexZipContentViewer
 */

if (! defined('ABSPATH') ) {
    exit; // Exit if accessed directly.
}

/**
 * Registers the block using the metadata loaded from the `block.json` file.
 *
 * @return void
 */
function telex_zip_content_viewer_block_init()
{
    register_block_type(__DIR__ . '/build/');
}
add_action('init', 'telex_zip_content_viewer_block_init');

/**
 * Handle ZIP file upload via AJAX
 */
function telex_zip_content_viewer_upload_zip()
{
    check_ajax_referer('zip_upload_nonce', 'nonce');

    if (! current_user_can('upload_files') ) {
        wp_send_json_error(array( 'message' => 'Insufficient permissions' ));
    }

    if (empty($_FILES['zip_file']) ) {
        wp_send_json_error(array( 'message' => 'No file uploaded' ));
    }

    $file = $_FILES['zip_file'];

    // Validate file type
    $file_type = wp_check_filetype($file['name']);
    if ($file_type['ext'] !== 'zip' ) {
        wp_send_json_error(array( 'message' => 'Only ZIP files are allowed' ));
    }

    // Create upload directory
    $upload_dir = wp_upload_dir();
    $extract_base = $upload_dir['basedir'] . '/zip-extracts/';

    if (! file_exists($extract_base) ) {
        wp_mkdir_p($extract_base);
    }

    // Generate folder name from ZIP file
    $folder_name = sanitize_file_name(pathinfo($file['name'], PATHINFO_FILENAME));
    $extract_path = $extract_base . $folder_name;

    // Remove existing folder if it exists
    if (file_exists($extract_path) ) {
        telex_zip_content_viewer_delete_directory($extract_path);
    }

    wp_mkdir_p($extract_path);

    // Extract ZIP file
    $zip = new ZipArchive();
    if ($zip->open($file['tmp_name']) === true ) {
        $zip->extractTo($extract_path);
        $zip->close();

        // Find HTML files
        $html_files = telex_zip_content_viewer_find_html_files($extract_path);

        if (empty($html_files) ) {
            telex_zip_content_viewer_delete_directory($extract_path);
            wp_send_json_error(array( 'message' => 'No HTML files found in ZIP' ));
        }

        $relative_url = $upload_dir['baseurl'] . '/zip-extracts/' . $folder_name;

        wp_send_json_success(
            array(
            'folder' => $folder_name,
            'path' => $extract_path,
            'url' => $relative_url,
            'files' => $html_files
            )
        );
    } else {
        wp_send_json_error(array( 'message' => 'Failed to extract ZIP file' ));
    }
}
add_action('wp_ajax_telex_zip_upload', 'telex_zip_content_viewer_upload_zip');

/**
 * Find HTML files in directory
 */
function telex_zip_content_viewer_find_html_files( $dir )
{
    $html_files = array();
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ( $iterator as $file ) {
        if ($file->isFile() && preg_match('/\.html?$/i', $file->getFilename()) ) {
            $relative_path = str_replace($dir . '/', '', $file->getPathname());
            $html_files[] = $relative_path;
        }
    }

    return $html_files;
}

/**
 * Delete directory recursively
 */
function telex_zip_content_viewer_delete_directory( $dir )
{
    if (! file_exists($dir) ) {
        return;
    }

    $files = array_diff(scandir($dir), array( '.', '..' ));
    foreach ( $files as $file ) {
        $path = $dir . '/' . $file;
        is_dir($path) ? telex_zip_content_viewer_delete_directory($path) : unlink($path);
    }
    rmdir($dir);
}

/**
 * Enqueue block editor assets
 */
function telex_zip_content_viewer_enqueue_editor_assets()
{
    wp_localize_script(
        'telex-zip-content-viewer-editor-script',
        'telexZipViewer',
        array(
        'nonce' => wp_create_nonce('zip_upload_nonce'),
        'ajaxUrl' => admin_url('admin-ajax.php')
        )
    );
}
add_action('enqueue_block_editor_assets', 'telex_zip_content_viewer_enqueue_editor_assets');
