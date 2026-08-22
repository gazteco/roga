<?php
/**
 * Outgoing notifications: internal alert and visitor acknowledgement.
 *
 * @package Roga
 */

defined( 'ABSPATH' ) || exit;

class ROGA_Mailer {

	/**
	 * Sends the internal notification.
	 *
	 * @param array $config  Form configuration.
	 * @param array $answers Map of field id => answer.
	 * @param array $context Extra context (form title, entry id, page url).
	 * @return bool
	 */
	public static function notify( $config, $answers, $context = array() ) {
		$s = $config['settings'];

		if ( empty( $s['notify_enabled'] ) || '' === $s['notify_to'] ) {
			return false;
		}

		$subject = $s['notify_subject'];
		$summary = self::summary_html( $config, $answers );

		$body  = '<p>' . esc_html__( 'Une nouvelle demande vient d\'être envoyée depuis le site.', 'roga' ) . '</p>';
		$body .= $summary;

		if ( ! empty( $context['entry_url'] ) ) {
			$body .= '<p><a href="' . esc_url( $context['entry_url'] ) . '">' . esc_html__( 'Voir la demande dans l\'administration', 'roga' ) . '</a></p>';
		}

		$reply_to = self::find_visitor_email( $config, $answers );
		$headers  = self::headers( $s, $reply_to );

		return wp_mail(
			array_map( 'trim', explode( ',', $s['notify_to'] ) ),
			$subject,
			self::wrap( $subject, $body, $config ),
			$headers
		);
	}

	/**
	 * Sends the acknowledgement to the visitor.
	 *
	 * @param array $config  Form configuration.
	 * @param array $answers Map of field id => answer.
	 * @return bool
	 */
	public static function acknowledge( $config, $answers ) {
		$s = $config['settings'];

		if ( empty( $s['ack_enabled'] ) ) {
			return false;
		}

		$to = self::find_visitor_email( $config, $answers );
		if ( ! $to ) {
			return false;
		}

		$body  = '<p>' . nl2br( esc_html( $s['ack_intro'] ) ) . '</p>';
		$body .= self::summary_html( $config, $answers );
		$body .= '<p>' . nl2br( esc_html( $s['ack_outro'] ) ) . '</p>';
		$body .= '<p><strong>' . esc_html( $s['from_name'] ) . '</strong></p>';

		return wp_mail( $to, $s['ack_subject'], self::wrap( $s['ack_subject'], $body, $config ), self::headers( $s ) );
	}

	/**
	 * Builds the From / Reply-To headers.
	 *
	 * @param array  $settings Form settings.
	 * @param string $reply_to Optional reply address.
	 * @return string[]
	 */
	protected static function headers( $settings, $reply_to = '' ) {
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		if ( ! empty( $settings['from_email'] ) ) {
			$name      = $settings['from_name'] ? $settings['from_name'] : get_bloginfo( 'name' );
			$headers[] = sprintf( 'From: %s <%s>', $name, $settings['from_email'] );
		}

		if ( $reply_to ) {
			$headers[] = 'Reply-To: ' . $reply_to;
		}

		return $headers;
	}

	/**
	 * Finds the visitor email address among the answers.
	 *
	 * @param array $config  Form configuration.
	 * @param array $answers Collected answers.
	 * @return string
	 */
	public static function find_visitor_email( $config, $answers ) {
		$key = $config['settings']['ack_field'];

		if ( $key && ! empty( $answers[ $key ] ) && is_email( $answers[ $key ] ) ) {
			return sanitize_email( $answers[ $key ] );
		}

		foreach ( $config['fields'] as $field ) {
			if ( 'email' === $field['type'] && ! empty( $answers[ $field['id'] ] ) && is_email( $answers[ $field['id'] ] ) ) {
				return sanitize_email( $answers[ $field['id'] ] );
			}
		}

		return '';
	}

	/**
	 * Renders the answers as an HTML table, skipping hidden and empty ones.
	 *
	 * @param array $config  Form configuration.
	 * @param array $answers Collected answers.
	 * @return string
	 */
	public static function summary_html( $config, $answers ) {
		$rows = '';

		foreach ( $config['fields'] as $field ) {
			if ( 'statement' === $field['type'] ) {
				continue;
			}
			if ( ! ROGA_Logic::is_visible( $field, $answers ) ) {
				continue;
			}

			$value = isset( $answers[ $field['id'] ] ) ? $answers[ $field['id'] ] : '';
			$value = is_array( $value ) ? implode( ', ', $value ) : (string) $value;

			if ( '' === trim( $value ) ) {
				continue;
			}

			$rows .= '<tr>'
				. '<td style="padding:8px 12px;border-bottom:1px solid #e8e2da;vertical-align:top;color:#6b7280;width:40%;">' . esc_html( $field['label'] ) . '</td>'
				. '<td style="padding:8px 12px;border-bottom:1px solid #e8e2da;vertical-align:top;"><strong>' . nl2br( esc_html( $value ) ) . '</strong></td>'
				. '</tr>';
		}

		if ( '' === $rows ) {
			return '<p>' . esc_html__( 'Aucune réponse renseignée.', 'roga' ) . '</p>';
		}

		return '<table cellpadding="0" cellspacing="0" style="border-collapse:collapse;width:100%;font-size:15px;">' . $rows . '</table>';
	}

	/**
	 * Wraps a body fragment in a minimal, brand coloured HTML shell.
	 *
	 * @param string $title  Email title.
	 * @param string $body   HTML body.
	 * @param array  $config Form configuration.
	 * @return string
	 */
	protected static function wrap( $title, $body, $config ) {
		$accent = $config['settings']['colors']['accent'];

		return '<!doctype html><html><head><meta charset="utf-8"></head>'
			. '<body style="margin:0;padding:24px;background:#f6f5f3;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Helvetica,Arial,sans-serif;color:#1d1d1f;">'
			. '<div style="max-width:640px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e8e2da;">'
			. '<div style="background:' . esc_attr( $accent ) . ';padding:18px 24px;color:#ffffff;font-size:16px;font-weight:600;">' . esc_html( $title ) . '</div>'
			. '<div style="padding:24px;">' . $body . '</div>'
			. '</div></body></html>';
	}
}
