<?php
/**
 * Plugin Name: Alkamy Blocks for Classic Editor
 * Description: Edit post and page content with WordPress blocks inside the classic editing screen.
 * Version: 1.1.6
 * Requires at least: 7.0
 * Requires PHP: 7.4
 * Author: Alkamy
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: alkamy-classic-editor-blocks
 *
 * @package Alkamy_Classic_Editor_Blocks
 */

defined( 'ABSPATH' ) || exit;

/** Embeds a block editor in the classic post form. */
final class Alkamy_Classic_Editor_Blocks {

	const BLOCK_TYPES = array(
		'core/paragraph',
		'core/heading',
		'core/list',
		'core/list-item',
		'core/quote',
		'core/pullquote',
		'core/image',
		'core/gallery',
		'core/video',
		'core/audio',
		'core/file',
		'core/media-text',
		'core/cover',
		'core/embed',
		'core/table',
		'core/buttons',
		'core/button',
		'core/columns',
		'core/column',
		'core/group',
		'core/details',
		'core/separator',
		'core/spacer',
		'core/code',
		'core/preformatted',
		'core/verse',
		'core/html',
		'core/shortcode',
	);


	const EDITOR_SETTINGS = array(
		'alignWide',
		'codeEditingEnabled',
		'allowedMimeTypes',
		'imageSizes',
		'imageDimensions',
		'imageDefaultSize',
		'maxUploadFileSize',
		'disableCustomColors',
		'disableCustomFontSizes',
		'disableCustomGradients',
		'disableCustomSpacingSizes',
		'enableCustomLineHeight',
		'enableCustomSpacing',
		'enableCustomUnits',
		'disableLayoutStyles',
		'__experimentalFeatures',
		'colors',
		'gradients',
		'fontSizes',
		'spacingSizes',
		'isRTL',
		'__unstableIsBlockBasedTheme',
	);


	/** @var string Post type whose editor support must be restored after the core editor slot. */
	private static $temporarily_disabled = '';

	/** Register the classic edit-form hooks. */
	public static function init() {
		add_action( 'load-post.php', array( __CLASS__, 'watch_form_save' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_filter( 'wp_editor_expand', array( __CLASS__, 'editor_expand' ), 10, 2 );
		add_action( 'edit_form_after_title', array( __CLASS__, 'render' ), PHP_INT_MAX );
		add_action( 'edit_form_after_editor', array( __CLASS__, 'restore_support' ), 0 );
	}

	/** Whether this post type has an eligible content editor. */
	private static function supports_editor( $post_type ) {
		return in_array( $post_type, array( 'post', 'page' ), true )
			&& post_type_supports( $post_type, 'editor' ) && user_can_richedit();
	}

	/** Compare content only in the classic form's normal save request. */
	public static function watch_form_save() {
		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'post' === sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) {
			add_filter( 'wp_insert_post_data', array( __CLASS__, 'preserve_line_endings' ), 10, 2 );
		}
	}

	/** Browsers submit textarea line breaks as CRLF, even when the author only edits the title. */
	public static function preserve_line_endings( $data, $postarr ) {
		if ( empty( $postarr['ID'] ) || ! self::supports_editor( $data['post_type'] ) ) {
			return $data;
		}

		$original = get_post_field( 'post_content', $postarr['ID'], 'raw' );
		$incoming = wp_unslash( $data['post_content'] );
		if ( $incoming !== $original && preg_replace( '/\r\n?|\n/', "\r\n", $original ) === $incoming ) {
			$data['post_content'] = wp_slash( $original );
		}
		return $data;
	}

	/** Disable the TinyMCE-specific expanding toolbar when replacing the editor. */
	public static function editor_expand( $expand, $post_type ) {
		$screen = get_current_screen();
		if ( $screen && 'post' === $screen->base && $post_type === $screen->post_type && ! $screen->is_block_editor() && self::supports_editor( $post_type ) ) {
			return false;
		}
		return $expand;
	}

	/** Render the normal content field on the classic post form. */
	public static function render( $post ) {
		if ( ! self::supports_editor( $post->post_type ) ) {
			return;
		}

		self::$temporarily_disabled = $post->post_type;
		remove_post_type_support( $post->post_type, 'editor' );
		?>
		<div id="postdivrich" class="postarea">
			<div class="akceb-field" data-akceb-field="content">
				<div class="akceb-field__editor"></div>
				<textarea id="content" name="content" class="akceb-field__content" rows="15" spellcheck="false" aria-label="<?php esc_attr_e( 'Content', 'alkamy-classic-editor-blocks' ); ?>"><?php
					// Browsers strip the first newline immediately after an opening textarea tag.
					echo "\n";
					echo esc_textarea( get_post_field( 'post_content', $post->ID, 'raw' ) );
				?></textarea>
			</div>
		</div>
		<?php
	}

	/** Restore support before other after-editor callbacks run. */
	public static function restore_support() {
		if ( self::$temporarily_disabled ) {
			add_post_type_support( self::$temporarily_disabled, 'editor' );
			self::$temporarily_disabled = '';
		}
	}

	/** Load core block components only on an eligible classic screen. */
	public static function enqueue_assets() {
		global $post;

		$screen = get_current_screen();

		if ( ! $screen || 'post' !== $screen->base || $screen->is_block_editor() || ! $post instanceof WP_Post || ! self::supports_editor( $post->post_type ) ) {
			return;
		}

		foreach ( array( 'wp-block-library', 'wp-block-library-theme', 'classic-theme-styles', 'wp-edit-blocks', 'wp-block-editor', 'wp-components', 'wp-format-library' ) as $style ) {
			wp_enqueue_style( $style );
		}
		wp_enqueue_style(
			'akceb-editor',
			plugins_url( 'assets/css/editor.css', __FILE__ ),
			array( 'wp-block-editor', 'wp-components', 'wp-edit-blocks' ),
			'1.1.6'
		);

		wp_enqueue_media( array( 'post' => $post->ID ) );
		wp_enqueue_script(
			'akceb-editor',
			plugins_url( 'assets/js/editor.js', __FILE__ ),
			array( 'wp-autop', 'wp-block-editor', 'wp-block-library', 'wp-blocks', 'wp-components', 'wp-compose', 'wp-core-data', 'wp-data', 'wp-element', 'wp-format-library', 'wp-hooks', 'wp-i18n', 'wp-keyboard-shortcuts', 'wp-keycodes', 'wp-media-utils' ),
			'1.1.6',
			true
		);
		wp_set_script_translations( 'akceb-editor', 'alkamy-classic-editor-blocks' );

		$settings = get_block_editor_settings( array(), new WP_Block_Editor_Context( array( 'post' => $post ) ) );

		$allowed = $settings['allowedBlockTypes'] ?? true;
		$blocks  = true === $allowed ? self::BLOCK_TYPES : array_values( array_intersect( self::BLOCK_TYPES, (array) $allowed ) );

		wp_localize_script(
			'akceb-editor',
			'akcebSettings',
			array(
				'editor'    => array_intersect_key( $settings, array_flip( self::EDITOR_SETTINGS ) ),
				'canUpload' => current_user_can( 'upload_files' ),
				'fields'    => array(
					'content' => array(
						'allowedBlockTypes' => $blocks,
						'placeholder'       => __( 'Write your content, or type / to choose a block', 'alkamy-classic-editor-blocks' ),
					),
				),
			)
		);
	}
}

Alkamy_Classic_Editor_Blocks::init();
