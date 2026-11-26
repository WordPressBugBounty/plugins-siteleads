<?php

namespace SiteLeads\Core;

use function array_map;
use function array_merge;
use function count;
use function is_array;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function array_get_value( &$array, $parents, $default = null, $glue = '.' ) {
	if ( ! $array || ! is_array( $array ) ) {
		return $default;
	}

	if ( ! is_array( $parents ) ) {
		$parents = explode( $glue, $parents );
	}

	$ref = &$array;

	foreach ( (array) $parents as $parent ) {
		if ( is_array( $ref ) && array_key_exists( $parent, $ref ) ) {
			$ref = &$ref[ $parent ];
			// walk inside object
		} elseif ( is_object( $ref ) && property_exists( $ref, $parent ) ) {
			$ref = &$ref->$parent;
		} else {
			return $default;
		}
	}

	return $ref;
}

function array_set_value( array &$array, $parents, $value, $glue = '.' ) {
	if ( ! is_array( $parents ) ) {
		$parents = explode( $glue, (string) $parents );
	}

	$ref = &$array;

	foreach ( $parents as $parent ) {
		if ( isset( $ref ) && ! is_array( $ref ) ) {
			$ref = array();
		}

		$ref = &$ref[ $parent ];
	}

	$ref = $value;
}

function array_unset_value( &$array, $parents, $glue = '.' ) {

	if ( ! is_array( $array ) ) {
		return;
	}

	if ( ! is_array( $parents ) ) {
		$parents = explode( $glue, $parents );
	}

	$key = array_shift( $parents );

	if ( empty( $parents ) ) {
		unset( $array[ $key ] );
	} else {
		array_unset_value( $array[ $key ], $parents );
	}
}

function array_map_by_key( $array, $key ) {
	$result = array();
	array_walk(
		$array,
		function ( $partial ) use ( $result, $key ) {
			$id = array_get_value( $partial, $key, null );
			if ( $id !== null ) {
				$result[ $id ] = $partial;
			}
		}
	);

	return $result;
}

class LodashBasic {
	static function array_get_value( &$array, $parents, $default = null, $glue = '.' ) {
		return array_get_value( $array, $parents, $default, $glue );
	}

	static function has( $array, $path ) {
		return Arr::has( $array, $path );
	}

	static function set( array &$array, $parents, $value ) {
		array_set_value( $array, $parents, $value );
	}

	static function unsetValue( &$array, $parents ) {
		array_unset_value( $array, $parents );
	}

	/** Calls $iterateFn( $value, $key, $collection ) for each entry ( an object's public properties ), until it returns false. */
	static function each( $collection, $iterateFn ) {
		foreach ( is_object( $collection ) ? get_object_vars( $collection ) : $collection as $key => $value ) {
			if ( false === $iterateFn( $value, $key, $collection ) ) {
				break;
			}
		}
	}

	/** The entries keyed by $iteratee( $value ) ( a callable ) or by their $iteratee path ( a string ); the last one wins. */
	static function keyBy( $collection, $iteratee ) {
		$result = array();
		foreach ( $collection as $value ) {
			$key            = is_callable( $iteratee ) ? $iteratee( $value ) : LodashBasic::get( $value, $iteratee );
			$result[ $key ] = $value;
		}

		return $result;
	}

	/** A list of $iteratee( $value, $key, $collection ), or of the $iteratee path ( a string ) of each value. */
	static function map( $collection, $iteratee ) {
		$values = is_object( $collection ) ? get_object_vars( $collection ) : $collection;
		$result = array();
		foreach ( $values as $key => $value ) {
			$result[] = is_callable( $iteratee ) ? $iteratee( $value, $key, $collection ) : LodashBasic::get( $value, $iteratee );
		}

		return $result;
	}

	static function mapValues( $array, $mapper ) {
		$closure = $mapper;
		if ( is_string( $mapper ) ) {
			$closure = function ( $value ) use ( $mapper ) {
				return LodashBasic::get( $value, $mapper );
			};
		}

		return array_map( $closure, $array );
	}

	static function get( $array, $parents, $default = null ) {
		if ( $array ) {
			return array_get_value( $array, $parents, $default );
		}

		return $default;
	}

	static function find( $array, $closure ) {
		return Arr::first( $array, $closure );
	}

	/** The values $closure( $value, $key ) keeps, reindexed. */
	static function filter( $array, $closure ) {
		return array_values( Arr::where( $array, $closure ) );
	}

	static function compactWithExceptions( $array, $exceptions = array() ) {
		return \array_values(
			\array_filter(
				$array,
				function ( $input ) use ( $exceptions ) {
					$isException = in_array( $input, $exceptions, true );

					return ! ! $input || $isException;
				}
			)
		);
	}

	static function isString( $value ) {
		return is_string( $value );
	}

	static function concat( $array, ...$values ) {
		$check = function ( $value ) {
			return is_array( $value ) ? $value : array( $value );
		};

		return array_merge( $check( $array ), ...array_map( $check, $values ) );
	}

	static function merge( ...$values ) {
		$not_null_values = LodashBasic::compact( $values );
		if ( count( $not_null_values ) > 0 ) {
			return array_replace_recursive( ...$not_null_values );
		}

		return array();
	}

	static function compact( $array ) {
		return array_values( array_filter( null === $array ? array() : $array ) );
	}

	static function mergeSkipSeqArray( ...$values ) {
		$not_null_values = LodashBasic::compact( $values );
		if ( count( $not_null_values ) > 0 ) {
			if ( count( $not_null_values ) === 1 ) {
				return $not_null_values[0];
			} else {

				// get the first 2 arrays to merge from parameters
				$next_arr   = array_shift( $not_null_values );
				$second_arr = array_shift( $not_null_values );

				// if arrays are not assoc use the second array
				if (
					is_array( $next_arr ) && count( $next_arr ) && ! Arr::isAssoc( $second_arr ) &&
					is_array( $second_arr ) && count( $second_arr ) && ! Arr::isAssoc( $next_arr )
				) {
					return $second_arr;
				}

				foreach ( $second_arr as $key => $second_value ) {

					$first_value = Arr::get( $next_arr, $key, null );
					if ( is_array( $second_value ) && count( $second_value ) ) {
						if ( ! is_array( $first_value ) ) {
							$next_arr[ $key ] = $second_value;
						} else {
							$next_arr[ $key ] = LodashBasic::mergeSkipSeqArray( $first_value, $second_value );
						}
					} else {
						if ( is_array( $first_value ) && is_array( $second_value ) && empty( $second_value ) ) {
							$next_arr[ $key ] = $first_value;
						} else {
							$next_arr[ $key ] = $second_value;
						}
					}
				}

				return $next_arr;
			}
		}

		return array();
	}

	static function omit( $object, $property ) {
		return Arr::except( $object, $property );
	}

	static function pick( $array, $paths ) {
		$paths_by_name = array_fill_keys( (array) $paths, true );

		return array_filter(
			$array,
			function ( $key ) use ( $paths_by_name ) {
				return isset( $paths_by_name[ $key ] );
			},
			ARRAY_FILTER_USE_KEY
		);
	}

	/**
	 * Laravel's Str::kebab ( snake() with "-" ), copied from pragmarx/ia-str 7.3.1: a "-" before each capital, then
	 * lowercase; a string that is all lowercase letters is returned as it is.
	 */
	static function kebabCase( $string ) {
		static $cache = array();

		$value = strval( $string );
		if ( isset( $cache[ $value ] ) ) {
			return $cache[ $value ];
		}

		$kebab = $value;
		if ( ! preg_match( '/^[a-z]+$/', $kebab ) ) {
			$kebab = preg_replace( '/\s+/u', '', ucwords( $kebab ) );
			$kebab = preg_replace( '/(.)(?=[A-Z])/u', '$1-', $kebab );
			$kebab = function_exists( 'mb_strtolower' ) ? mb_strtolower( $kebab, 'UTF-8' ) : strtolower( $kebab );
		}

		return $cache[ $value ] = $kebab;
	}

	/**
	 * This method returns the first argument it receives.
	 *
	 * @param mixed $value Any value.
	 *
	 * @return mixed Returns `value`.
	 * @category Util
	 *
	 * @example
	 * <code>
	 * $object = ['a' => 1];
	 *
	 * identity($object) === $object;
	 * // => true
	 * </code>
	 */
	static function identity( $value ) {
		return $value;
	}

	static function uniq( $values ) {
		return array_unique( $values );
	}

	static function diff( $a1, $a2 ) {
		$r = array();
		foreach ( $a1 as $k => $v ) {
			if ( array_key_exists( $k, (array) $a2 ) ) {
				if ( is_array( $v ) ) {
					$rad = self::diff( $v, $a2[ $k ] );
					if ( count( $rad ) ) {
						$r[ $k ] = $rad;
					}
				} else {
					if ( $v != $a2[ $k ] ) {
						$r[ $k ] = $v;
					}
				}
			} else {
				$r[ $k ] = $v;
			}
		}

		return $r;
	}

}
