<?php

/**
 * @see https://github.com/WordPress/gutenberg/blob/trunk/docs/reference-guides/block-api/block-metadata.md#render
 */

if (empty($attributes['folderName']) || empty($attributes['selectedFile'])) {
    return '';
}

$base_url = esc_url($attributes['baseUrl']);
$selected_file = esc_attr($attributes['selectedFile']);
$iframe_src = $base_url . '/' . $selected_file;
$iframe_height = ! empty($attributes['iframeHeight']) ? esc_attr($attributes['iframeHeight']) : '600px';

$wrapper_attributes = get_block_wrapper_attributes();
?>
<div <?php echo $wrapper_attributes; ?>>
    <iframe 
        class="zip-viewer-iframe" 
        src="<?php echo $iframe_src; ?>" 
        style="height: <?php echo $iframe_height; ?>;"
        title="<?php esc_attr_e('ZIP Content', 'zip-content-viewer'); ?>"
        loading="lazy"
    ></iframe>
</div>
