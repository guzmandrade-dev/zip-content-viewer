/**
 * Retrieves the translation of text.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-i18n/
 */
import { __ } from '@wordpress/i18n';

/**
 * React hook that is used to mark the block wrapper element.
 * It provides all the necessary props like the class name.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-block-editor/#useblockprops
 */
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';

import { 
	PanelBody, 
	Button, 
	SelectControl,
	Placeholder,
	Spinner,
	Notice,
	TextControl
} from '@wordpress/components';

import { useState, useRef } from '@wordpress/element';
import { upload } from '@wordpress/icons';

/**
 * Lets webpack process CSS, SASS or SCSS files referenced in JavaScript files.
 * Those files can contain any CSS code that gets applied to the editor.
 *
 * @see https://www.npmjs.com/package/@wordpress/scripts#using-css
 */
import './editor.scss';

/**
 * The edit function describes the structure of your block in the context of the
 * editor. This represents what the editor will render when the block is used.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/#edit
 *
 * @param {Object} props - Block props
 * @return {Element} Element to render.
 */
export default function Edit( { attributes, setAttributes } ) {
	const { folderName, baseUrl, selectedFile, htmlFiles, iframeHeight } = attributes;
	const [ isUploading, setIsUploading ] = useState( false );
	const [ error, setError ] = useState( '' );
	const fileInputRef = useRef( null );

	const handleFileUpload = ( event ) => {
		const file = event.target.files[0];
		if ( ! file ) {
			return;
		}

		setIsUploading( true );
		setError( '' );

		const formData = new FormData();
		formData.append( 'action', 'telex_zip_upload' );
		formData.append( 'nonce', window.telexZipViewer.nonce );
		formData.append( 'zip_file', file );

		fetch( window.telexZipViewer.ajaxUrl, {
			method: 'POST',
			body: formData,
		} )
			.then( ( response ) => response.json() )
			.then( ( data ) => {
				setIsUploading( false );
				if ( data.success ) {
					setAttributes( {
						folderName: data.data.folder,
						baseUrl: data.data.url,
						htmlFiles: data.data.files,
						selectedFile: data.data.files[0] || '',
					} );
				} else {
					setError( data.data.message || __( 'Upload failed', 'zip-content-viewer' ) );
				}
			} )
			.catch( () => {
				setIsUploading( false );
				setError( __( 'Upload failed', 'zip-content-viewer' ) );
			} );
	};

	const blockProps = useBlockProps( {
		className: 'wp-block-telex-zip-content-viewer',
	} );

	if ( ! folderName || ! selectedFile ) {
		return (
			<div { ...blockProps }>
				<Placeholder
					icon={ upload }
					label={ __( 'ZIP Content Viewer', 'zip-content-viewer' ) }
					instructions={ __( 'Upload a ZIP file containing HTML content', 'zip-content-viewer' ) }
				>
					{ error && (
						<Notice status="error" isDismissible={ false }>
							{ error }
						</Notice>
					) }
					{ isUploading ? (
						<Spinner />
					) : (
						<div className="zip-upload-container">
							<Button
								variant="primary"
								onClick={ () => fileInputRef.current?.click() }
							>
								{ __( 'Upload ZIP File', 'zip-content-viewer' ) }
							</Button>
							<input
								ref={ fileInputRef }
								type="file"
								accept=".zip"
								style={ { display: 'none' } }
								onChange={ handleFileUpload }
							/>
						</div>
					) }
				</Placeholder>
			</div>
		);
	}

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'ZIP Content Settings', 'zip-content-viewer' ) }>
					<SelectControl
						label={ __( 'Select HTML File', 'zip-content-viewer' ) }
						value={ selectedFile }
						options={ htmlFiles.map( ( file ) => ( {
							label: file,
							value: file,
						} ) ) }
						onChange={ ( value ) => setAttributes( { selectedFile: value } ) }
					/>
					<TextControl
						label={ __( 'Iframe Height', 'zip-content-viewer' ) }
						value={ iframeHeight }
						onChange={ ( value ) => setAttributes( { iframeHeight: value } ) }
						help={ __( 'e.g., 600px or 100vh', 'zip-content-viewer' ) }
					/>
					<Button
						variant="secondary"
						isDestructive
						onClick={ () => {
							setAttributes( {
								folderName: '',
								baseUrl: '',
								selectedFile: '',
								htmlFiles: [],
							} );
						} }
					>
						{ __( 'Upload New ZIP', 'zip-content-viewer' ) }
					</Button>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<div className="zip-viewer-editor-notice">
					<p className="zip-viewer-file-info">
						<strong>{ __( 'ZIP Content Viewer', 'zip-content-viewer' ) }</strong>
					</p>
					<p className="zip-viewer-selected-file">
						{ __( 'Selected file:', 'zip-content-viewer' ) } <code>{ selectedFile }</code>
					</p>
					<p className="zip-viewer-preview-note">
						{ __( 'The iframe will be displayed on the frontend', 'zip-content-viewer' ) }
					</p>
				</div>
			</div>
		</>
	);
}