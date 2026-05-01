<?php
/**
 * @see https://github.com/WordPress/gutenberg/blob/trunk/docs/reference-guides/block-api/block-metadata.md#render
 */

if ( empty( $attributes['folderName'] ) || empty( $attributes['selectedFile'] ) ) {
	return '';
}

// Validate folder and file paths
$upload_dir = wp_upload_dir();
$extract_base = $upload_dir['basedir'] . '/zip-extracts/';
$folder_name = sanitize_file_name( $attributes['folderName'] );
$selected_file = sanitize_file_name( $attributes['selectedFile'] );

// Ensure paths are within allowed directory
$full_path = $extract_base . $folder_name . '/' . $selected_file;
$normalized_path = wp_normalize_path( $full_path );
$normalized_extract_base = wp_normalize_path( $extract_base );

if ( strpos( $normalized_path, $normalized_extract_base ) !== 0 || ! file_exists( $full_path ) ) {
	return '';
}

// Verify file is an HTML file
if ( ! preg_match( '/\.html?$/i', $selected_file ) ) {
	return '';
}

$base_url = esc_url( $attributes['baseUrl'] );
$iframe_src = $base_url . '/' . $selected_file;
$iframe_height = ! empty( $attributes['iframeHeight'] ) ? esc_attr( $attributes['iframeHeight'] ) : '600px';

$wrapper_attributes = get_block_wrapper_attributes();
?>
<div <?php echo $wrapper_attributes; ?>>
	<iframe 
		class="zip-viewer-iframe" 
		src="<?php echo $iframe_src; ?>" 
		style="height: <?php echo $iframe_height; ?>;"
		title="<?php esc_attr_e( 'ZIP Content', 'zip-content-viewer' ); ?>"
		loading="lazy"
	></iframe>
</div>
