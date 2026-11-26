<?php

namespace SiteLeads\Features;

use SiteLeads\Core\Singleton;
use SiteLeads\Core\SvgSanitizer;
use SiteLeads\Features\Widgets\FCWidgets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Features {

	use Singleton;

	protected function __construct() {
		if ( is_admin() ) {
			add_filter( 'upload_mimes', array( $this, 'allow_svg_uploads' ), 10, 2 );
		}

		add_filter( 'wp_handle_upload_prefilter', array( $this, 'sanitize_svg_upload' ) );
		add_filter( 'wp_handle_sideload_prefilter', array( $this, 'sanitize_svg_upload' ) );

		if ( ! is_admin() ) {
			$isAjaxOrRest = defined( 'XMLRPC_REQUEST' ) || defined( 'REST_REQUEST' ) || ( defined( 'DOING_AJAX' ) && DOING_AJAX );
			if ( ! $isAjaxOrRest ) {
				FCWidgets::load()->addHooks();
			}
		}

		FCAnalytics::load();
		FCPreviewWidget::load();
		Rest::load();
		new FrontendWidgetLoadActions();
	}

	/**
	 * SVG uploads are needed for the widget icons, so they are only enabled for the users that can
	 * manage SiteLeads (the SiteLeads admin pages require manage_options).
	 */
	public function allow_svg_uploads( $mimes, $user = null ) {
		$can_upload_svg = $user ? user_can( $user, 'manage_options' ) : current_user_can( 'manage_options' );

		if ( $can_upload_svg ) {
			$mimes['svg'] = 'image/svg+xml';
		}

		return $mimes;
	}

	/**
	 * Removes scripts, event handlers and external references from uploaded SVG files.
	 */
	public function sanitize_svg_upload( $file ) {
		$name = isset( $file['name'] ) ? $file['name'] : '';
		$type = isset( $file['type'] ) ? $file['type'] : '';

		$is_svg = 'svg' === strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ) || 'image/svg+xml' === $type;

		if ( ! $is_svg || ! empty( $file['error'] ) || empty( $file['tmp_name'] ) ) {
			return $file;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local temporary upload file
		$content   = file_get_contents( $file['tmp_name'] );
		$sanitized = SvgSanitizer::sanitize( $content );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- local temporary upload file
		if ( false === $sanitized || false === file_put_contents( $file['tmp_name'], $sanitized ) ) {
			$file['error'] = __( 'This SVG file could not be sanitized and was not uploaded.', 'siteleads' );
		}

		return $file;
	}
}
