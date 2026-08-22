<?php
/**
 * Submission endpoint and server side validation.
 *
 * @package Roga
 */

defined( 'ABSPATH' ) || exit;

class ROGA_Rest {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Declares the public submission route.
	 */
	public static function register_routes() {
		register_rest_route(
			'roga/v1',
			'/submit',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'form_id' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * REST handler.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle( $request ) {
		$form_id = (int) $request->get_param( 'form_id' );
		$answers = $request->get_param( 'answers' );
		$hp      = $request->get_param( 'website' );

		if ( ! empty( $hp ) ) {
			// Silently accept: a bot should not learn it was caught.
			return new WP_REST_Response( array( 'ok' => true ), 200 );
		}

		$result = self::process(
			$form_id,
			is_array( $answers ) ? $answers : array(),
			array(
				'source' => 'js',
				'page'   => esc_url_raw( (string) $request->get_param( 'page' ) ),
			)
		);

		if ( is_wp_error( $result ) ) {
			return new WP_Error(
				$result->get_error_code(),
				$result->get_error_message(),
				array(
					'status' => 400,
					'fields' => $result->get_error_data(),
				)
			);
		}

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Validates, stores and notifies. Shared by the REST route and the no-JS fallback.
	 *
	 * @param int   $form_id Form post ID.
	 * @param array $raw     Raw answers keyed by field id.
	 * @param array $context Extra context.
	 * @return array|WP_Error
	 */
	public static function process( $form_id, $raw, $context = array() ) {
		$config = ROGA_Forms::get_config( $form_id );

		if ( ! $config ) {
			return new WP_Error( 'roga_unknown_form', __( 'Ce formulaire n\'existe plus.', 'roga' ) );
		}

		if ( ! self::rate_limit_ok() ) {
			return new WP_Error( 'roga_rate_limited', __( 'Trop de demandes envoyées coup sur coup. Merci de patienter une minute.', 'roga' ) );
		}

		$answers = array();
		$byid    = array();

		foreach ( $config['fields'] as $field ) {
			$byid[ $field['id'] ] = $field;
		}

		// First pass: sanitise every submitted value against its declared type.
		foreach ( $byid as $id => $field ) {
			if ( 'statement' === $field['type'] ) {
				continue;
			}
			$value         = isset( $raw[ $id ] ) ? $raw[ $id ] : '';
			$answers[ $id ] = self::sanitize_answer( $field, $value );
		}

		// Second pass: only fields actually visible may be required.
		$errors = array();

		foreach ( $byid as $id => $field ) {
			if ( 'statement' === $field['type'] ) {
				continue;
			}
			if ( ! ROGA_Logic::is_visible( $field, $answers ) ) {
				$answers[ $id ] = is_array( $answers[ $id ] ) ? array() : '';
				continue;
			}

			$value = $answers[ $id ];
			$empty = is_array( $value ) ? empty( $value ) : ( '' === trim( (string) $value ) );

			if ( $field['required'] && $empty ) {
				$errors[ $id ] = __( 'Cette réponse est nécessaire pour continuer.', 'roga' );
				continue;
			}

			if ( 'email' === $field['type'] && ! $empty && ! is_email( $value ) ) {
				$errors[ $id ] = __( 'Cette adresse e-mail ne semble pas valide.', 'roga' );
			}
		}

		if ( $errors ) {
			return new WP_Error( 'roga_invalid', __( 'Certaines réponses sont à corriger.', 'roga' ), $errors );
		}

		/**
		 * Fires before an entry is stored, letting integrations veto it.
		 *
		 * @param array $answers Sanitised answers.
		 * @param array $config  Form configuration.
		 * @param int   $form_id Form post ID.
		 */
		$blocked = apply_filters( 'roga_pre_submit', false, $answers, $config, $form_id );
		if ( is_wp_error( $blocked ) ) {
			return $blocked;
		}

		$entry_id = 0;

		if ( ! empty( $config['settings']['store_entries'] ) ) {
			$labels = array();
			foreach ( $config['fields'] as $field ) {
				$labels[ $field['id'] ] = $field['label'];
			}

			$entry_id = ROGA_Entries::insert(
				$form_id,
				$answers,
				array_merge( $context, array( 'labels' => $labels ) ),
				! empty( $config['settings']['store_ip'] )
			);
		}

		ROGA_Mailer::notify(
			$config,
			$answers,
			array(
				'entry_url' => $entry_id ? admin_url( 'admin.php?page=roga-entries&entry=' . $entry_id ) : '',
			)
		);

		ROGA_Mailer::acknowledge( $config, $answers );

		/**
		 * Fires after a submission has been fully handled.
		 *
		 * @param array $answers  Sanitised answers.
		 * @param int   $form_id  Form post ID.
		 * @param int   $entry_id Stored entry ID, 0 when storage is off.
		 */
		do_action( 'roga_after_submit', $answers, $form_id, $entry_id );

		return array(
			'ok'       => true,
			'entry_id' => $entry_id,
			'thankyou' => $config['thankyou'],
		);
	}

	/**
	 * Sanitises one answer according to its field type.
	 *
	 * @param array $field Field definition.
	 * @param mixed $value Raw value.
	 * @return string|array
	 */
	public static function sanitize_answer( $field, $value ) {
		switch ( $field['type'] ) {
			case 'checkbox':
				$value  = is_array( $value ) ? $value : ( '' === $value ? array() : array( $value ) );
				$out    = array();
				$allowed = isset( $field['options'] ) ? $field['options'] : array();
				foreach ( $value as $v ) {
					$v = sanitize_text_field( is_scalar( $v ) ? $v : '' );
					if ( '' === $v ) {
						continue;
					}
					if ( in_array( $v, $allowed, true ) || ! empty( $field['allow_other'] ) ) {
						$out[] = $v;
					}
				}
				return array_values( array_unique( $out ) );

			case 'radio':
			case 'select':
				$v       = sanitize_text_field( is_scalar( $value ) ? $value : '' );
				$allowed = isset( $field['options'] ) ? $field['options'] : array();
				if ( '' === $v ) {
					return '';
				}
				return ( in_array( $v, $allowed, true ) || ! empty( $field['allow_other'] ) ) ? $v : '';

			case 'email':
				return sanitize_email( is_scalar( $value ) ? $value : '' );

			case 'textarea':
				return sanitize_textarea_field( is_scalar( $value ) ? $value : '' );

			case 'legal':
				return empty( $value ) ? '' : __( 'Oui', 'roga' );

			case 'number':
				$v = sanitize_text_field( is_scalar( $value ) ? $value : '' );
				return is_numeric( $v ) ? $v : '';

			default:
				return sanitize_text_field( is_scalar( $value ) ? $value : '' );
		}
	}

	/**
	 * Simple per IP throttle: at most 5 submissions a minute.
	 *
	 * @return bool
	 */
	protected static function rate_limit_ok() {
		$ip = ROGA_Entries::client_ip();

		if ( '' === $ip ) {
			return true;
		}

		$key   = 'roga_rl_' . md5( $ip );
		$count = (int) get_transient( $key );

		if ( $count >= 5 ) {
			return false;
		}

		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );

		return true;
	}
}
