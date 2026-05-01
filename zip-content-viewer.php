<?php
/**
 * Plugin Name:       ZIP Content Viewer
 * Description:       A block that extracts ZIP files and displays HTML content in an iframe.
 * Version:           0.1.0
 * Requires at least: 6.1
 * Requires PHP:      7.4
 * Author:            WordPress Telex
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       zip-content-viewer
 *
 * @package TelexZipContentViewer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Registers the block using the metadata loaded from the `block.json` file.
 */
function telex_zip_content_viewer_block_init() {
	register_block_type( __DIR__ . '/build/' );
}
add_action( 'init', 'telex_zip_content_viewer_block_init' );

/**
 * Validate ZIP entries for security (prevent ZIP Slip and symlink attacks)
 *
 * @param ZipArchive $zip         The ZIP archive object.
 * @param string     $extract_path The target extraction directory.
 * @return true|WP_Error True on success, WP_Error on failure.
 */
function telex_zip_content_viewer_validate_zip_entries( $zip, $extract_path ) {
	$max_file_size  = 50 * MB_IN_BYTES; // 50MB limit per file
	$max_total_size = 200 * MB_IN_BYTES; // 200MB total limit
	$max_file_count = 500;
	$total_size     = 0;

	if ( $zip->numFiles > $max_file_count ) {
		return new WP_Error( 'zip_too_many_files', sprintf( 'ZIP contains too many files (max: %d)', $max_file_count ) );
	}
	
	for ( $i = 0; $i < $zip->numFiles; $i++ ) {
		$entry      = $zip->statIndex( $i );
		$entry_name = $entry['name'];

		// Skip empty names
		if ( empty( $entry_name ) ) {
			continue;
		}
		
		// Block absolute paths
		if ( strpos( $entry_name, '/' ) === 0 || strpos( $entry_name, '\\' ) === 0 ) {
			return new WP_Error( 'zip_invalid_path', 'ZIP contains absolute paths' );
		}
		
		// Block path traversal attempts
		if ( strpos( $entry_name, '..' ) !== false ) {
			return new WP_Error( 'zip_path_traversal', 'ZIP contains path traversal attempts' );
		}
		
		// Block null bytes
		if ( strpos( $entry_name, "\0" ) !== false ) {
			return new WP_Error( 'zip_null_byte', 'ZIP contains null bytes in paths' );
		}
		
		// Check file size
		if ( $entry['size'] > $max_file_size ) {
			return new WP_Error( 'zip_file_too_large', sprintf( 'File %s exceeds size limit', $entry_name ) );
		}
		
		$total_size += $entry['size'];
		if ( $total_size > $max_total_size ) {
			return new WP_Error( 'zip_too_large', 'Total ZIP size exceeds limit' );
		}
		
		// Validate final path stays within extraction directory
		$safe_path = telex_zip_content_viewer_sanitize_zip_path( $entry_name, $extract_path );
		if ( false === $safe_path ) {
			return new WP_Error( 'zip_invalid_path', 'Invalid file path in ZIP' );
		}
	}
	
	return true;
}

/**
 * Sanitize ZIP entry path and ensure it stays within target directory
 *
 * @param string $entry_name   The ZIP entry name.
 * @param string $extract_path The target extraction directory.
 * @return string|false Safe absolute path or false if invalid.
 */
function telex_zip_content_viewer_sanitize_zip_path( $entry_name, $extract_path ) {
	// Normalize path separators
	$entry_name = str_replace( '\\', '/', $entry_name );

	// Remove leading slashes
	$entry_name = ltrim( $entry_name, '/' );

	// Build full path
	$full_path = $extract_path . '/' . $entry_name;

	// Normalize and resolve real path
	$normalized_path       = wp_normalize_path( $full_path );
	$extract_normalized    = wp_normalize_path( $extract_path );

	// Ensure path is within extraction directory
	if ( strpos( $normalized_path, $extract_normalized . '/' ) !== 0 && $normalized_path !== $extract_normalized ) {
		return false;
	}
	
	return $normalized_path;
}

/**
 * Handle ZIP file upload via AJAX
 */
function telex_zip_content_viewer_upload_zip() {
	check_ajax_referer( 'zip_upload_nonce', 'nonce' );
	
	if ( ! current_user_can( 'upload_files' ) ) {
		wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
	}
	
	if ( empty( $_FILES['zip_file'] ) ) {
		wp_send_json_error( array( 'message' => 'No file uploaded' ) );
	}
	
	$file = $_FILES['zip_file'];

	// Check for upload errors
	if ( UPLOAD_ERR_OK !== $file['error'] ) {
		wp_send_json_error( array( 'message' => 'File upload error occurred' ) );
	}

	// Validate file size (WordPress max_upload_size)
	$max_upload_size = wp_max_upload_size();
	if ( $file['size'] > $max_upload_size ) {
		wp_send_json_error( array( 'message' => 'File exceeds maximum upload size' ) );
	}

	// Validate file type by extension and MIME
	$file_type = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], $file['type'] );
	if ( 'zip' !== $file_type['ext'] ) {
		wp_send_json_error( array( 'message' => 'Only ZIP files are allowed' ) );
	}
	
	// Additional MIME type validation
	$allowed_mime_types = array( 'application/zip', 'application/x-zip-compressed' );
	if ( ! in_array( $file_type['type'], $allowed_mime_types, true ) ) {
		wp_send_json_error( array( 'message' => 'Invalid file type' ) );
	}
	
	// Create upload directory
	$upload_dir     = wp_upload_dir();
	$extract_base   = $upload_dir['basedir'] . '/zip-extracts/';

	if ( ! file_exists( $extract_base ) ) {
		wp_mkdir_p( $extract_base );
	}

	// Generate unique folder name from ZIP file with timestamp and random suffix
	$base_folder_name = sanitize_file_name( pathinfo( $file['name'], PATHINFO_FILENAME ) );
	$unique_suffix    = wp_generate_password( 6, false );
	$folder_name      = $base_folder_name . '_' . time() . '_' . $unique_suffix;
	$extract_path     = $extract_base . $folder_name;

	wp_mkdir_p( $extract_path );

	// Extract ZIP file with security validation
	$zip               = new ZipArchive();
	$zip_open_result   = $zip->open( $file['tmp_name'] );

	if ( true !== $zip_open_result ) {
		wp_send_json_error( array( 'message' => 'Failed to open ZIP file' ) );
	}
	
	// Validate ZIP entries before extraction (prevent ZIP Slip)
	$validation_result = telex_zip_content_viewer_validate_zip_entries( $zip, $extract_path );
	if ( is_wp_error( $validation_result ) ) {
		$zip->close();
		wp_send_json_error( array( 'message' => $validation_result->get_error_message() ) );
	}
	
	// Safe extraction with validated entries
	$extraction_success = true;
	for ( $i = 0; $i < $zip->numFiles; $i++ ) {
		$entry      = $zip->statIndex( $i );
		$entry_name = $entry['name'];

		// Skip directories
		if ( '/' === substr( $entry_name, -1 ) ) {
			continue;
		}

		// Sanitize path and extract
		$safe_path = telex_zip_content_viewer_sanitize_zip_path( $entry_name, $extract_path );
		if ( false === $safe_path ) {
			$extraction_success = false;
			break;
		}
		
		// Create directory if needed
		$entry_dir = dirname( $safe_path );
		if ( ! file_exists( $entry_dir ) ) {
			wp_mkdir_p( $entry_dir );
		}
		
		// Extract file
		$content = $zip->getFromIndex( $i );
		if ( $content === false ) {
			$extraction_success = false;
			break;
		}
		
		file_put_contents( $safe_path, $content );
	}
	
	$zip->close();
	
	if ( ! $extraction_success ) {
		telex_zip_content_viewer_delete_directory( $extract_path );
		wp_send_json_error( array( 'message' => 'Failed to extract ZIP file securely' ) );
	}
	
	// Find HTML files
	$html_files = telex_zip_content_viewer_find_html_files( $extract_path );
	
	if ( empty( $html_files ) ) {
		telex_zip_content_viewer_delete_directory( $extract_path );
		wp_send_json_error( array( 'message' => 'No HTML files found in ZIP' ) );
	}
	
	$relative_url = $upload_dir['baseurl'] . '/zip-extracts/' . $folder_name;
	
	wp_send_json_success( array(
		'folder' => $folder_name,
		'url' => $relative_url,
		'files' => $html_files
	) );
}
add_action( 'wp_ajax_telex_zip_upload', 'telex_zip_content_viewer_upload_zip' );

/**
 * Find HTML files in directory
 */
function telex_zip_content_viewer_find_html_files( $dir ) {
	$html_files = array();
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::SELF_FIRST
	);
	
	foreach ( $iterator as $file ) {
		if ( $file->isFile() && preg_match( '/\.html?$/i', $file->getFilename() ) ) {
			$relative_path = str_replace( $dir . '/', '', $file->getPathname() );
			$html_files[] = $relative_path;
		}
	}
	
	return $html_files;
}

/**
 * Delete directory recursively with safety checks
 *
 * @param string $dir Directory path to delete.
 * @return bool True on success, false on failure.
 */
function telex_zip_content_viewer_delete_directory( $dir ) {
	if ( ! file_exists( $dir ) || ! is_dir( $dir ) ) {
		return false;
	}
	
	// Safety check: ensure path is within wp-content/uploads
	$upload_dir = wp_upload_dir();
	$normalized_dir = wp_normalize_path( $dir );
	$normalized_upload_dir = wp_normalize_path( $upload_dir['basedir'] );
	
	if ( strpos( $normalized_dir, $normalized_upload_dir . '/zip-extracts/' ) !== 0 ) {
		error_log( 'ZIP Content Viewer: Attempted to delete directory outside allowed path: ' . $dir );
		return false;
	}
	
	// Safety check: prevent deletion of root or important directories
	$dir_basename = basename( $normalized_dir );
	if ( in_array( $dir_basename, array( 'uploads', 'wp-content', 'wordpress' ), true ) ) {
		error_log( 'ZIP Content Viewer: Attempted to delete protected directory: ' . $dir );
		return false;
	}
	
	$files = array_diff( scandir( $dir ), array( '.', '..' ) );
	foreach ( $files as $file ) {
		$path = $dir . '/' . $file;
		if ( is_dir( $path ) ) {
			telex_zip_content_viewer_delete_directory( $path );
		} else {
			unlink( $path );
		}
	}
	
	return rmdir( $dir );
}

/**
 * Enqueue block editor assets
 */
function telex_zip_content_viewer_enqueue_editor_assets() {
	wp_localize_script(
		'telex-zip-content-viewer-editor-script',
		'telexZipViewer',
		array(
			'nonce' => wp_create_nonce( 'zip_upload_nonce' ),
			'ajaxUrl' => admin_url( 'admin-ajax.php' )
		)
	);
}
add_action( 'enqueue_block_editor_assets', 'telex_zip_content_viewer_enqueue_editor_assets' );
