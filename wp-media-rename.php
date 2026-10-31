<?php
/**
 * Plugin Name:       Media Rename
 * Description:       Auto-renames uploaded media files from a configurable pattern ({date}, {time}, {post_title}, {original}) for SEO-friendly filenames.
 * Version:           1.0.0
 * Author:            Azan Umer
 * License:           GPL-2.0+
 * Text Domain:       wmr
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'WMR_OPTION', 'wmr_settings' );

/**
 * Default settings.
 *
 * @return array
 */
function wmr_defaults() {
    return array(
        'pattern'        => '{date}-{original}',
        'lowercase'      => 1,
        'replace_spaces' => 1,
    );
}

/**
 * Get merged settings.
 *
 * @return array
 */
function wmr_settings() {
    return wp_parse_args( get_option( WMR_OPTION, array() ), wmr_defaults() );
}

/**
 * Build a new filename from the pattern.
 *
 * Supported tokens: {date} (Y-m-d), {time} (His), {original} (sanitized
 * original name without extension), {post_title} (title of the post the
 * upload is attached to, empty when uploading from the Media Library).
 *
 * @param string $filename Original filename, e.g. "IMG_1234.JPG".
 * @return string New filename with the original extension preserved.
 */
function wmr_build_filename( $filename ) {
    $settings = wmr_settings();
    $info     = pathinfo( $filename );
    $ext      = isset( $info['extension'] ) ? strtolower( $info['extension'] ) : '';
    $original = sanitize_file_name( $info['filename'] );

    $post_title = '';
    $post_id    = isset( $_REQUEST['post_id'] ) ? absint( $_REQUEST['post_id'] ) : 0;
    if ( $post_id > 0 ) {
        $post_title = sanitize_title( get_the_title( $post_id ) );
    }

    $replacements = array(
        '{date}'       => current_time( 'Y-m-d' ),
        '{time}'       => current_time( 'His' ),
        '{original}'   => $original,
        '{post_title}' => $post_title !== '' ? $post_title : $original,
    );

    $name = str_replace( array_keys( $replacements ), array_values( $replacements ), $settings['pattern'] );

    // Re-sanitize after token substitution, then tidy separators.
    $name = sanitize_file_name( $name );
    if ( ! empty( $settings['replace_spaces'] ) ) {
        $name = str_replace( ' ', '-', $name );
    }
    if ( ! empty( $settings['lowercase'] ) ) {
        $name = strtolower( $name );
    }
    $name = preg_replace( '/-+/', '-', trim( $name, '-' ) );

    // Fall back to the original name if the pattern produced nothing usable.
    if ( $name === '' ) {
        $name = $original;
    }

    $new = $ext !== '' ? $name . '.' . $ext : $name;

    // Themes/plugins can override the final name.
    return apply_filters( 'wmr_filename', $new, $filename );
}

/**
 * Rename the file before WordPress moves it into the uploads directory.
 * wp_unique_filename() later guarantees uniqueness, so collisions are safe.
 *
 * @param array $file Upload data from wp_handle_upload_prefilter.
 * @return array
 */
function wmr_prefilter( $file ) {
    if ( ! empty( $file['name'] ) ) {
        $file['name'] = wmr_build_filename( $file['name'] );
    }
    return $file;
}
add_filter( 'wp_handle_upload_prefilter', 'wmr_prefilter' );

/**
 * Settings page under Settings -> Media Rename.
 */
function wmr_register_settings() {
    register_setting( 'wmr_settings_group', WMR_OPTION, array(
        'sanitize_callback' => 'wmr_sanitize_settings',
    ) );

    add_settings_section( 'wmr_main', __( 'Media Rename', 'wmr' ), '__return_false', 'wmr-settings' );
    add_settings_field( 'wmr_pattern', __( 'Filename pattern', 'wmr' ), 'wmr_field_text', 'wmr-settings', 'wmr_main', array( 'key' => 'pattern' ) );
    add_settings_field( 'wmr_lowercase', __( 'Force lowercase', 'wmr' ), 'wmr_field_checkbox', 'wmr-settings', 'wmr_main', array( 'key' => 'lowercase' ) );
    add_settings_field( 'wmr_replace_spaces', __( 'Replace spaces with dashes', 'wmr' ), 'wmr_field_checkbox', 'wmr-settings', 'wmr_main', array( 'key' => 'replace_spaces' ) );
}
add_action( 'admin_init', 'wmr_register_settings' );

/**
 * Sanitize settings input. The pattern may only contain safe characters
 * plus the known tokens; anything else is stripped.
 *
 * @param array $input Raw input.
 * @return array
 */
function wmr_sanitize_settings( $input ) {
    $out = wmr_defaults();
    $pattern = isset( $input['pattern'] ) ? sanitize_text_field( wp_unslash( $input['pattern'] ) ) : '';
    $pattern = preg_replace( '/[^A-Za-z0-9\-_{} ]/', '', $pattern );
    $out['pattern']        = $pattern !== '' ? $pattern : $out['pattern'];
    $out['lowercase']      = ! empty( $input['lowercase'] ) ? 1 : 0;
    $out['replace_spaces'] = ! empty( $input['replace_spaces'] ) ? 1 : 0;
    return $out;
}

/**
 * Text field callback.
 *
 * @param array $args Field args.
 */
function wmr_field_text( $args ) {
    $settings = wmr_settings();
    printf(
        '<input type="text" class="regular-text" name="%s[%s]" value="%s" /><p class="description">%s</p>',
        esc_attr( WMR_OPTION ),
        esc_attr( $args['key'] ),
        esc_attr( $settings[ $args['key'] ] ),
        esc_html__( 'Tokens: {date} {time} {original} {post_title}. Extension is always preserved.', 'wmr' )
    );
}

/**
 * Checkbox field callback.
 *
 * @param array $args Field args.
 */
function wmr_field_checkbox( $args ) {
    $settings = wmr_settings();
    printf(
        '<input type="checkbox" name="%s[%s]" value="1" %s />',
        esc_attr( WMR_OPTION ),
        esc_attr( $args['key'] ),
        checked( ! empty( $settings[ $args['key'] ] ), true, false )
    );
}

/**
 * Render the settings page.
 */
function wmr_settings_page() {
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Media Rename', 'wmr' ); ?></h1>
        <p><?php esc_html_e( 'Files already in the Media Library are not renamed — only new uploads. Uniqueness is handled by WordPress (a -1, -2 suffix is added on collision).', 'wmr' ); ?></p>
        <form method="post" action="options.php">
            <?php
            settings_fields( 'wmr_settings_group' );
            do_settings_sections( 'wmr-settings' );
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

/**
 * Add the menu item.
 */
function wmr_add_menu() {
    add_options_page(
        __( 'Media Rename', 'wmr' ),
        __( 'Media Rename', 'wmr' ),
        'manage_options',
        'wmr-settings',
        'wmr_settings_page'
    );
}
add_action( 'admin_menu', 'wmr_add_menu' );
