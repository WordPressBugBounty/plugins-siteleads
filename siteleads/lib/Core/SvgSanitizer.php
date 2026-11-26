<?php

namespace SiteLeads\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sanitizes uploaded SVG files: keeps only known SVG shape / paint elements, removes scripts,
 * event handler attributes, external references and anything that can run code.
 */
class SvgSanitizer {

	private static $allowed_elements = array(
		'svg',
		'g',
		'defs',
		'title',
		'desc',
		'metadata',
		'symbol',
		'use',
		'switch',
		'path',
		'circle',
		'ellipse',
		'line',
		'polyline',
		'polygon',
		'rect',
		'text',
		'tspan',
		'textpath',
		'lineargradient',
		'radialgradient',
		'stop',
		'clippath',
		'mask',
		'pattern',
		'marker',
		'style',
		'filter',
		'feblend',
		'fecolormatrix',
		'fecomponenttransfer',
		'fecomposite',
		'feconvolvematrix',
		'fediffuselighting',
		'fedisplacementmap',
		'fedistantlight',
		'fedropshadow',
		'feflood',
		'fefunca',
		'fefuncb',
		'fefuncg',
		'fefuncr',
		'fegaussianblur',
		'femerge',
		'femergenode',
		'femorphology',
		'feoffset',
		'fepointlight',
		'fespecularlighting',
		'fespotlight',
		'fetile',
		'feturbulence',
	);

	/**
	 * @param string $svg SVG markup.
	 *
	 * @return string|false The sanitized markup, or false when the file is not a valid SVG.
	 */
	public static function sanitize( $svg ) {
		if ( ! is_string( $svg ) || '' === trim( $svg ) || ! class_exists( '\DOMDocument' ) ) {
			return false;
		}

		// No DTDs / entities: blocks XXE and entity expansion attacks.
		if ( preg_match( '/<!(DOCTYPE|ENTITY)/i', $svg ) ) {
			return false;
		}

		$internal_errors = libxml_use_internal_errors( true );
		$dom             = new \DOMDocument();
		$loaded          = $dom->loadXML( $svg, LIBXML_NONET );
		libxml_clear_errors();
		libxml_use_internal_errors( $internal_errors );

		if ( ! $loaded || ! $dom->documentElement || 'svg' !== strtolower( $dom->documentElement->localName ) ) {
			return false;
		}

		self::sanitizeNode( $dom->documentElement );

		return $dom->saveXML( $dom->documentElement );
	}

	private static function sanitizeNode( \DOMElement $element ) {
		// Attributes.
		$attributes = array();
		foreach ( $element->attributes as $attribute ) {
			$attributes[] = $attribute;
		}

		foreach ( $attributes as $attribute ) {
			if ( ! self::isAllowedAttribute( $attribute ) ) {
				$element->removeAttributeNode( $attribute );
			}
		}

		if ( 'style' === strtolower( $element->localName ) && self::isUnsafeCss( $element->textContent ) ) {
			$element->textContent = '';
		}

		// Children (copy the list first, it changes while removing).
		$children = array();
		foreach ( $element->childNodes as $child ) {
			$children[] = $child;
		}

		foreach ( $children as $child ) {
			if ( $child instanceof \DOMElement ) {
				if ( in_array( strtolower( $child->localName ), self::$allowed_elements, true ) ) {
					self::sanitizeNode( $child );
				} else {
					$element->removeChild( $child );
				}
				continue;
			}

			// Keep text and CDATA (style content, text labels); drop comments, processing instructions, etc.
			if ( ! ( $child instanceof \DOMText ) ) {
				$element->removeChild( $child );
			}
		}
	}

	private static function isAllowedAttribute( \DOMAttr $attribute ) {
		$name  = strtolower( $attribute->localName );
		$value = $attribute->value;

		// Event handlers (onload, onclick, ...).
		if ( 0 === strpos( $name, 'on' ) ) {
			return false;
		}

		// Links / references (href, xlink:href) may only point inside the document.
		if ( 'href' === $name ) {
			return 0 === strpos( trim( $value ), '#' );
		}

		$normalized = strtolower( preg_replace( '/[\s\x00-\x1f]+/', '', $value ) );
		if ( preg_match( '/(javascript|vbscript|data):/', $normalized ) ) {
			return false;
		}

		if ( 'style' === $name && self::isUnsafeCss( $value ) ) {
			return false;
		}

		return true;
	}

	private static function isUnsafeCss( $css ) {
		$normalized = strtolower( preg_replace( '/[\s\x00-\x1f]+/', '', $css ) );

		if ( preg_match( '/@import|expression\(|javascript:|vbscript:|-moz-binding|behavior:/', $normalized ) ) {
			return true;
		}

		// url() may only reference elements inside the document: url(#id).
		if ( preg_match_all( '/url\(([^)]*)\)/', $normalized, $matches ) ) {
			foreach ( $matches[1] as $target ) {
				if ( 0 !== strpos( trim( $target, '\'"' ), '#' ) ) {
					return true;
				}
			}
		}

		return false;
	}
}
