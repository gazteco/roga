<?php
/**
 * Form storage: custom post type plus a sanitised JSON configuration.
 *
 * @package Roga
 */

defined( 'ABSPATH' ) || exit;

class ROGA_Forms {

	const POST_TYPE = 'gzf_form'; // Historique : conservé pour ne pas perdre les formulaires existants.
	const META_KEY  = '_gzf_config'; // Historique, idem.

	/**
	 * Field types the editor and renderer both understand.
	 *
	 * @return array<string,string>
	 */
	public static function field_types() {
		return array(
			'statement' => __( 'Message (sans réponse)', 'roga' ),
			'text'      => __( 'Texte court', 'roga' ),
			'textarea'  => __( 'Texte long', 'roga' ),
			'email'     => __( 'E-mail', 'roga' ),
			'tel'       => __( 'Téléphone', 'roga' ),
			'number'    => __( 'Nombre', 'roga' ),
			'date'      => __( 'Date', 'roga' ),
			'radio'     => __( 'Choix unique', 'roga' ),
			'checkbox'  => __( 'Choix multiple', 'roga' ),
			'select'    => __( 'Liste déroulante', 'roga' ),
			'legal'     => __( 'Case de consentement', 'roga' ),
		);
	}

	/**
	 * Types that carry a list of options.
	 *
	 * @return string[]
	 */
	public static function choice_types() {
		return array( 'radio', 'checkbox', 'select' );
	}

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
	}

	/**
	 * Registers the (non public) form post type.
	 */
	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'          => array(
					'name'          => __( 'Formulaires', 'roga' ),
					'singular_name' => __( 'Formulaire', 'roga' ),
				),
				'public'          => false,
				'show_ui'         => false,
				'show_in_rest'    => false,
				'supports'        => array( 'title' ),
				'capability_type' => 'post',
				'map_meta_cap'    => true,
			)
		);
	}

	/**
	 * Returns the default configuration for a brand new form.
	 *
	 * @return array
	 */
	public static function default_config() {
		return array(
			'welcome'  => array(
				'enabled'     => true,
				'title'       => __( 'Bonjour !', 'roga' ),
				'description' => __( 'Ce court formulaire prend moins de deux minutes.', 'roga' ),
				'button'      => __( 'Commencer', 'roga' ),
			),
			'thankyou' => array(
				'title'       => __( 'Merci de votre réponse !', 'roga' ),
				'description' => __( 'Nous revenons vers vous dès que possible.', 'roga' ),
			),
			'fields'   => array(),
			'settings' => array(
				'submit_label'  => __( 'Envoyer', 'roga' ),
				'colors'        => array(
					'bg'      => '#ffffff',
					'text'    => '#1d1d1f',
					'accent'  => '#0d493b',
					'onaccent' => '#ffffff',
					'muted'   => '#6b7280',
				),
				'notify_enabled' => true,
				'notify_to'      => get_option( 'admin_email' ),
				'notify_subject' => __( 'Nouvelle demande depuis le site', 'roga' ),
				'from_name'      => get_bloginfo( 'name' ),
				'from_email'     => '',
				'ack_enabled'    => false,
				'ack_field'      => '',
				'ack_subject'    => __( 'Nous avons bien reçu votre demande', 'roga' ),
				'ack_intro'      => __( "Bonjour,\n\nNous avons bien reçu votre demande et revenons vers vous rapidement.\n\nVoici le récapitulatif de vos réponses :", 'roga' ),
				'ack_outro'      => __( 'À très vite,', 'roga' ),
				'store_entries'  => true,
				'store_ip'       => false,
				'rgpd_notice'    => '',
			),
		);
	}

	/**
	 * Fetches every form, newest first.
	 *
	 * @return WP_Post[]
	 */
	public static function all() {
		return get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => array( 'publish', 'draft' ),
				'numberposts'    => 200,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'suppress_filters' => false,
			)
		);
	}

	/**
	 * Reads a form configuration.
	 *
	 * @param int $form_id Form post ID.
	 * @return array|null
	 */
	public static function get_config( $form_id ) {
		$post = get_post( $form_id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return null;
		}

		$raw = get_post_meta( $form_id, self::META_KEY, true );
		$cfg = is_string( $raw ) ? json_decode( $raw, true ) : $raw;

		if ( ! is_array( $cfg ) ) {
			$cfg = self::default_config();
		}

		return self::merge_defaults( $cfg );
	}

	/**
	 * Fills in any key missing from a stored configuration.
	 *
	 * @param array $cfg Stored configuration.
	 * @return array
	 */
	public static function merge_defaults( $cfg ) {
		$defaults = self::default_config();

		$cfg             = is_array( $cfg ) ? $cfg : array();
		$cfg['welcome']  = array_merge( $defaults['welcome'], isset( $cfg['welcome'] ) && is_array( $cfg['welcome'] ) ? $cfg['welcome'] : array() );
		$cfg['thankyou'] = array_merge( $defaults['thankyou'], isset( $cfg['thankyou'] ) && is_array( $cfg['thankyou'] ) ? $cfg['thankyou'] : array() );
		$cfg['fields']   = isset( $cfg['fields'] ) && is_array( $cfg['fields'] ) ? array_values( $cfg['fields'] ) : array();
		$cfg['settings'] = array_merge( $defaults['settings'], isset( $cfg['settings'] ) && is_array( $cfg['settings'] ) ? $cfg['settings'] : array() );
		$cfg['settings']['colors'] = array_merge( $defaults['settings']['colors'], isset( $cfg['settings']['colors'] ) && is_array( $cfg['settings']['colors'] ) ? $cfg['settings']['colors'] : array() );

		return $cfg;
	}

	/**
	 * Creates a form.
	 *
	 * @param array $config Form configuration, including a `title` key.
	 * @return int|false Post ID on success.
	 */
	public static function create( $config ) {
		$title = isset( $config['title'] ) ? $config['title'] : __( 'Nouveau formulaire', 'roga' );
		unset( $config['title'] );

		$post_id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_title'  => sanitize_text_field( $title ),
				'post_status' => 'publish',
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return false;
		}

		self::save_config( $post_id, $config );

		return $post_id;
	}

	/**
	 * Sanitises and stores a configuration.
	 *
	 * @param int   $form_id Form post ID.
	 * @param array $config  Raw configuration.
	 * @return array The sanitised configuration that was stored.
	 */
	public static function save_config( $form_id, $config ) {
		$clean = self::sanitize_config( $config );
		update_post_meta( $form_id, self::META_KEY, wp_slash( wp_json_encode( $clean ) ) );
		return $clean;
	}

	/**
	 * Sanitises a whole configuration tree.
	 *
	 * @param array $config Raw configuration.
	 * @return array
	 */
	public static function sanitize_config( $config ) {
		$config = self::merge_defaults( is_array( $config ) ? $config : array() );
		$out    = self::default_config();

		$out['welcome'] = array(
			'enabled'     => ! empty( $config['welcome']['enabled'] ),
			'title'       => sanitize_text_field( $config['welcome']['title'] ),
			'description' => sanitize_textarea_field( $config['welcome']['description'] ),
			'button'      => sanitize_text_field( $config['welcome']['button'] ),
		);

		$out['thankyou'] = array(
			'title'       => sanitize_text_field( $config['thankyou']['title'] ),
			'description' => sanitize_textarea_field( $config['thankyou']['description'] ),
		);

		$types    = array_keys( self::field_types() );
		$seen_ids = array();
		$fields   = array();

		foreach ( $config['fields'] as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			$type = isset( $field['type'] ) && in_array( $field['type'], $types, true ) ? $field['type'] : 'text';
			$id   = isset( $field['id'] ) ? sanitize_key( $field['id'] ) : '';

			if ( '' === $id ) {
				$id = 'champ_' . ( count( $fields ) + 1 );
			}
			// Guarantee uniqueness so answers never collide.
			$base = $id;
			$n    = 2;
			while ( in_array( $id, $seen_ids, true ) ) {
				$id = $base . '_' . $n;
				$n++;
			}
			$seen_ids[] = $id;

			$clean = array(
				'id'          => $id,
				'type'        => $type,
				'label'       => sanitize_text_field( isset( $field['label'] ) ? $field['label'] : '' ),
				'description' => sanitize_textarea_field( isset( $field['description'] ) ? $field['description'] : '' ),
				'placeholder' => sanitize_text_field( isset( $field['placeholder'] ) ? $field['placeholder'] : '' ),
				'required'    => ! empty( $field['required'] ) && 'statement' !== $type,
			);

			if ( in_array( $type, self::choice_types(), true ) ) {
				$options = array();
				if ( isset( $field['options'] ) && is_array( $field['options'] ) ) {
					foreach ( $field['options'] as $opt ) {
						$opt = sanitize_text_field( is_scalar( $opt ) ? $opt : '' );
						if ( '' !== $opt ) {
							$options[] = $opt;
						}
					}
				}
				$clean['options']     = array_values( array_unique( $options ) );
				$clean['allow_other'] = ! empty( $field['allow_other'] );
			}

			$clean['logic'] = self::sanitize_logic( isset( $field['logic'] ) ? $field['logic'] : null );

			$fields[] = $clean;
		}

		$out['fields'] = $fields;

		$s   = $config['settings'];
		$def = $out['settings'];

		$out['settings'] = array(
			'submit_label'   => sanitize_text_field( $s['submit_label'] ),
			'colors'         => array(
				'bg'       => self::sanitize_color( $s['colors']['bg'], $def['colors']['bg'] ),
				'text'     => self::sanitize_color( $s['colors']['text'], $def['colors']['text'] ),
				'accent'   => self::sanitize_color( $s['colors']['accent'], $def['colors']['accent'] ),
				'onaccent' => self::sanitize_color( $s['colors']['onaccent'], $def['colors']['onaccent'] ),
				'muted'    => self::sanitize_color( $s['colors']['muted'], $def['colors']['muted'] ),
			),
			'notify_enabled' => ! empty( $s['notify_enabled'] ),
			'notify_to'      => self::sanitize_email_list( $s['notify_to'] ),
			'notify_subject' => sanitize_text_field( $s['notify_subject'] ),
			'from_name'      => sanitize_text_field( $s['from_name'] ),
			'from_email'     => is_email( $s['from_email'] ) ? sanitize_email( $s['from_email'] ) : '',
			'ack_enabled'    => ! empty( $s['ack_enabled'] ),
			'ack_field'      => sanitize_key( $s['ack_field'] ),
			'ack_subject'    => sanitize_text_field( $s['ack_subject'] ),
			'ack_intro'      => sanitize_textarea_field( $s['ack_intro'] ),
			'ack_outro'      => sanitize_textarea_field( $s['ack_outro'] ),
			'store_entries'  => ! empty( $s['store_entries'] ),
			'store_ip'       => ! empty( $s['store_ip'] ),
			'rgpd_notice'    => sanitize_textarea_field( $s['rgpd_notice'] ),
		);

		return $out;
	}

	/**
	 * Sanitises one conditional logic block.
	 *
	 * @param mixed $logic Raw logic.
	 * @return array|null
	 */
	public static function sanitize_logic( $logic ) {
		if ( ! is_array( $logic ) || empty( $logic['rules'] ) || ! is_array( $logic['rules'] ) ) {
			return null;
		}

		$ops   = array( 'is', 'is_not', 'contains', 'not_contains', 'gt', 'lt', 'empty', 'not_empty' );
		$rules = array();

		foreach ( $logic['rules'] as $rule ) {
			if ( ! is_array( $rule ) || empty( $rule['field'] ) ) {
				continue;
			}
			$op      = isset( $rule['op'] ) && in_array( $rule['op'], $ops, true ) ? $rule['op'] : 'is';
			$rules[] = array(
				'field' => sanitize_key( $rule['field'] ),
				'op'    => $op,
				'value' => sanitize_text_field( isset( $rule['value'] ) ? $rule['value'] : '' ),
			);
		}

		if ( empty( $rules ) ) {
			return null;
		}

		return array(
			'mode'  => ( isset( $logic['mode'] ) && 'any' === $logic['mode'] ) ? 'any' : 'all',
			'rules' => $rules,
		);
	}

	/**
	 * Validates a hex colour, falling back when malformed.
	 *
	 * @param string $value    Candidate colour.
	 * @param string $fallback Default colour.
	 * @return string
	 */
	public static function sanitize_color( $value, $fallback ) {
		$value = is_string( $value ) ? trim( $value ) : '';
		return preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $value ) ? $value : $fallback;
	}

	/**
	 * Sanitises a comma separated list of recipients.
	 *
	 * @param string $value Raw list.
	 * @return string
	 */
	public static function sanitize_email_list( $value ) {
		$parts = preg_split( '/[,;\s]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY );
		$out   = array();

		foreach ( (array) $parts as $part ) {
			if ( is_email( $part ) ) {
				$out[] = sanitize_email( $part );
			}
		}

		return implode( ', ', array_unique( $out ) );
	}
}
