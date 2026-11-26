<?php

namespace SiteLeads\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ArrayAccess;
use Closure;

/**
 * The array helpers SiteLeads uses from Laravel's Illuminate\Support\Arr, copied from pragmarx/ia-arr 7.3.3 ( MIT,
 * abandoned; its implicitly nullable parameters are deprecated in PHP 8.4+ ). Same names and the same behavior:
 * keys are dot paths ( 'a.b.c' ), an exact key wins over the path, a Closure default is called.
 */
class Arr {

	public static function accessible( $value ) {
		return is_array( $value ) || $value instanceof ArrayAccess;
	}

	public static function exists( $array, $key ) {
		if ( $array instanceof ArrayAccess ) {
			return $array->offsetExists( $key );
		}

		return array_key_exists( $key, $array );
	}

	public static function get( $array, $key, $default = null ) {
		if ( ! static::accessible( $array ) ) {
			return static::value( $default );
		}

		if ( is_null( $key ) ) {
			return $array;
		}

		if ( static::exists( $array, $key ) ) {
			return $array[ $key ];
		}

		if ( strpos( $key, '.' ) === false ) {
			return $array[ $key ] ?? static::value( $default );
		}

		foreach ( explode( '.', $key ) as $segment ) {
			if ( static::accessible( $array ) && static::exists( $array, $segment ) ) {
				$array = $array[ $segment ];
			} else {
				return static::value( $default );
			}
		}

		return $array;
	}

	public static function set( &$array, $key, $value ) {
		if ( is_null( $key ) ) {
			$array = $value;

			return $array;
		}

		$keys = explode( '.', $key );

		while ( count( $keys ) > 1 ) {
			$key = array_shift( $keys );

			// A missing or non-array level becomes an empty array, to hold the next one.
			if ( ! isset( $array[ $key ] ) || ! is_array( $array[ $key ] ) ) {
				$array[ $key ] = array();
			}

			$array = &$array[ $key ];
		}

		$array[ array_shift( $keys ) ] = $value;

		return $array;
	}

	public static function forget( &$array, $keys ) {
		$original = &$array;

		$keys = (array) $keys;

		if ( count( $keys ) === 0 ) {
			return;
		}

		foreach ( $keys as $key ) {
			// The exact key at the top level.
			if ( static::exists( $array, $key ) ) {
				unset( $array[ $key ] );

				continue;
			}

			$parts = explode( '.', $key );

			// Start each path from the top.
			$array = &$original;

			while ( count( $parts ) > 1 ) {
				$part = array_shift( $parts );

				if ( isset( $array[ $part ] ) && is_array( $array[ $part ] ) ) {
					$array = &$array[ $part ];
				} else {
					continue 2;
				}
			}

			unset( $array[ array_shift( $parts ) ] );
		}
	}

	public static function has( $array, $keys ) {
		$keys = (array) $keys;

		if ( ! $array || $keys === array() ) {
			return false;
		}

		foreach ( $keys as $key ) {
			$sub_key_array = $array;

			if ( static::exists( $array, $key ) ) {
				continue;
			}

			foreach ( explode( '.', $key ) as $segment ) {
				if ( static::accessible( $sub_key_array ) && static::exists( $sub_key_array, $segment ) ) {
					$sub_key_array = $sub_key_array[ $segment ];
				} else {
					return false;
				}
			}
		}

		return true;
	}

	public static function wrap( $value ) {
		if ( is_null( $value ) ) {
			return array();
		}

		return is_array( $value ) ? $value : array( $value );
	}

	public static function isAssoc( array $array ) {
		$keys = array_keys( $array );

		return array_keys( $keys ) !== $keys;
	}

	public static function where( $array, callable $callback ) {
		return array_filter( $array, $callback, ARRAY_FILTER_USE_BOTH );
	}

	public static function first( $array, ?callable $callback = null, $default = null ) {
		if ( is_null( $callback ) ) {
			if ( empty( $array ) ) {
				return static::value( $default );
			}

			foreach ( $array as $item ) {
				return $item;
			}
		}

		foreach ( $array as $key => $value ) {
			if ( $callback( $value, $key ) ) {
				return $value;
			}
		}

		return static::value( $default );
	}

	public static function last( $array, ?callable $callback = null, $default = null ) {
		if ( is_null( $callback ) ) {
			return empty( $array ) ? static::value( $default ) : end( $array );
		}

		return static::first( array_reverse( $array, true ), $callback, $default );
	}

	public static function flatten( $array, $depth = INF ) {
		$result = array();

		foreach ( $array as $item ) {
			if ( ! is_array( $item ) ) {
				$result[] = $item;
			} else {
				$values = $depth === 1
					? array_values( $item )
					: static::flatten( $item, $depth - 1 );

				foreach ( $values as $value ) {
					$result[] = $value;
				}
			}
		}

		return $result;
	}

	public static function except( $array, $keys ) {
		static::forget( $array, $keys );

		return $array;
	}

	/** A Closure default is called, any other value returned as it is ( Laravel's value() helper ). */
	private static function value( $value ) {
		return $value instanceof Closure ? $value() : $value;
	}
}
