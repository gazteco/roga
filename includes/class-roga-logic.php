<?php
/**
 * Conditional logic evaluation. Mirrors the JavaScript implementation in
 * assets/form.js so the server reaches the same conclusion as the browser.
 *
 * @package Roga
 */

defined( 'ABSPATH' ) || exit;

class ROGA_Logic {

	/**
	 * Decides whether a field should be shown given the answers collected so far.
	 *
	 * @param array $field   Field definition.
	 * @param array $answers Map of field id => answer.
	 * @return bool
	 */
	public static function is_visible( $field, $answers ) {
		if ( empty( $field['logic'] ) || empty( $field['logic']['rules'] ) ) {
			return true;
		}

		$mode    = ( isset( $field['logic']['mode'] ) && 'any' === $field['logic']['mode'] ) ? 'any' : 'all';
		$results = array();

		foreach ( $field['logic']['rules'] as $rule ) {
			$results[] = self::test( $rule, $answers );
		}

		if ( empty( $results ) ) {
			return true;
		}

		return 'any' === $mode ? in_array( true, $results, true ) : ! in_array( false, $results, true );
	}

	/**
	 * Evaluates a single rule.
	 *
	 * @param array $rule    Rule definition.
	 * @param array $answers Collected answers.
	 * @return bool
	 */
	protected static function test( $rule, $answers ) {
		$key    = isset( $rule['field'] ) ? $rule['field'] : '';
		$actual = isset( $answers[ $key ] ) ? $answers[ $key ] : '';
		$value  = isset( $rule['value'] ) ? $rule['value'] : '';
		$op     = isset( $rule['op'] ) ? $rule['op'] : 'is';

		$list = is_array( $actual ) ? array_map( 'strval', $actual ) : array( (string) $actual );
		$flat = implode( ' ', $list );

		switch ( $op ) {
			case 'is':
				return in_array( (string) $value, $list, true );
			case 'is_not':
				return ! in_array( (string) $value, $list, true );
			case 'contains':
				return '' !== $value && false !== mb_stripos( $flat, (string) $value );
			case 'not_contains':
				return '' === $value || false === mb_stripos( $flat, (string) $value );
			case 'gt':
				return is_numeric( $flat ) && is_numeric( $value ) && (float) $flat > (float) $value;
			case 'lt':
				return is_numeric( $flat ) && is_numeric( $value ) && (float) $flat < (float) $value;
			case 'empty':
				return '' === trim( $flat );
			case 'not_empty':
				return '' !== trim( $flat );
		}

		return true;
	}

	/**
	 * Filters a field list down to those visible for a given answer set.
	 *
	 * @param array $fields  Field definitions.
	 * @param array $answers Collected answers.
	 * @return array
	 */
	public static function visible_fields( $fields, $answers ) {
		$out = array();

		foreach ( $fields as $field ) {
			if ( self::is_visible( $field, $answers ) ) {
				$out[] = $field;
			}
		}

		return $out;
	}
}
