<?php
/**
 * Front end rendering: shortcode, assets and the no-JavaScript fallback.
 *
 * @package Roga
 */

defined( 'ABSPATH' ) || exit;

class ROGA_Render {

	/**
	 * Forms already printed on the current request.
	 *
	 * @var int[]
	 */
	protected static $printed = array();

	/**
	 * Hooks.
	 */
	public static function init() {
		add_shortcode( 'roga', array( __CLASS__, 'shortcode' ) );
		// Kept so pages published before the rename keep working.
		add_shortcode( 'gazte_form', array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_action( 'admin_post_nopriv_roga_fallback', array( __CLASS__, 'handle_fallback' ) );
		add_action( 'admin_post_roga_fallback', array( __CLASS__, 'handle_fallback' ) );
	}

	/**
	 * Registers (without enqueueing) the front end assets.
	 */
	public static function register_assets() {
		wp_register_style( 'roga', ROGA_URL . 'assets/form.css', array(), ROGA_VERSION );
		wp_register_script( 'roga', ROGA_URL . 'assets/form.js', array(), ROGA_VERSION, true );
	}

	/**
	 * Renders `[roga id="12"]`.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'     => 0,
				'height' => '',
			),
			$atts,
			'roga'
		);

		$form_id = (int) $atts['id'];
		$config  = ROGA_Forms::get_config( $form_id );

		if ( ! $config ) {
			return current_user_can( roga_capability() )
				? '<p>' . esc_html__( 'Roga Forms : identifiant de formulaire introuvable.', 'roga' ) . '</p>'
				: '';
		}

		wp_enqueue_style( 'roga' );
		wp_enqueue_script( 'roga' );

		wp_localize_script(
			'roga',
			'ROGA_DATA',
			array(
				'endpoint' => esc_url_raw( rest_url( 'roga/v1/submit' ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'i18n'     => array(
					'required'  => __( 'Cette réponse est nécessaire pour continuer.', 'roga' ),
					'email'     => __( 'Cette adresse e-mail ne semble pas valide.', 'roga' ),
					'next'      => __( 'Suivant', 'roga' ),
					'back'      => __( 'Retour', 'roga' ),
					'ok'        => __( 'OK', 'roga' ),
					'enter'     => __( 'Entrée', 'roga' ),
					'press'     => __( 'appuyez sur', 'roga' ),
					'other'     => __( 'Autre', 'roga' ),
					'otherPh'   => __( 'Précisez…', 'roga' ),
					'sending'   => __( 'Envoi en cours…', 'roga' ),
					'error'     => __( 'L\'envoi a échoué. Merci de réessayer dans un instant.', 'roga' ),
					'multiHint' => __( 'Plusieurs réponses possibles', 'roga' ),
					'progress'  => __( '%s%% complété', 'roga' ),
				),
			)
		);

		$uid    = 'roga-' . $form_id . '-' . wp_rand( 1000, 9999 );
		$height = $atts['height'] ? $atts['height'] : '640px';

		// The public payload deliberately omits every setting that is not needed
		// to draw the form (recipients, email bodies, storage flags).
		$public = array(
			'formId'   => $form_id,
			'welcome'  => $config['welcome'],
			'thankyou' => $config['thankyou'],
			'fields'   => $config['fields'],
			'submit'   => $config['settings']['submit_label'],
			'rgpd'     => $config['settings']['rgpd_notice'],
			'colors'   => $config['settings']['colors'],
		);

		ob_start();
		?>
		<div class="roga-root" id="<?php echo esc_attr( $uid ); ?>"
			style="--roga-bg:<?php echo esc_attr( $config['settings']['colors']['bg'] ); ?>;--roga-text:<?php echo esc_attr( $config['settings']['colors']['text'] ); ?>;--roga-accent:<?php echo esc_attr( $config['settings']['colors']['accent'] ); ?>;--roga-onaccent:<?php echo esc_attr( $config['settings']['colors']['onaccent'] ); ?>;--roga-muted:<?php echo esc_attr( $config['settings']['colors']['muted'] ); ?>;min-height:<?php echo esc_attr( $height ); ?>;"
			data-roga-config="<?php echo esc_attr( wp_json_encode( $public ) ); ?>">
			<noscript>
				<?php self::fallback_form( $form_id, $config ); ?>
			</noscript>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Prints a plain, single page version of the form for visitors without JavaScript.
	 *
	 * @param int   $form_id Form post ID.
	 * @param array $config  Form configuration.
	 */
	public static function fallback_form( $form_id, $config ) {
		?>
		<form class="roga-fallback" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="roga_fallback" />
			<input type="hidden" name="form_id" value="<?php echo esc_attr( $form_id ); ?>" />
			<?php wp_nonce_field( 'roga_fallback_' . $form_id, 'roga_nonce' ); ?>
			<p class="roga-hp" style="position:absolute;left:-9999px;" aria-hidden="true">
				<label><?php esc_html_e( 'Ne remplissez pas ce champ', 'roga' ); ?>
					<input type="text" name="roga_website" value="" tabindex="-1" autocomplete="off" />
				</label>
			</p>
			<?php foreach ( $config['fields'] as $field ) : ?>
				<?php if ( 'statement' === $field['type'] ) : ?>
					<p><strong><?php echo esc_html( $field['label'] ); ?></strong></p>
					<?php continue; ?>
				<?php endif; ?>
				<p>
					<label for="roga-f-<?php echo esc_attr( $field['id'] ); ?>">
						<?php echo esc_html( $field['label'] ); ?>
						<?php if ( $field['required'] ) : ?><span aria-hidden="true"> *</span><?php endif; ?>
					</label><br />
					<?php if ( $field['description'] ) : ?>
						<span class="description"><?php echo esc_html( $field['description'] ); ?></span><br />
					<?php endif; ?>
					<?php if ( in_array( $field['type'], array( 'radio', 'checkbox' ), true ) ) : ?>
						<?php foreach ( $field['options'] as $i => $opt ) : ?>
							<label>
								<input type="<?php echo esc_attr( $field['type'] ); ?>"
									name="gzf[<?php echo esc_attr( $field['id'] ); ?>]<?php echo 'checkbox' === $field['type'] ? '[]' : ''; ?>"
									value="<?php echo esc_attr( $opt ); ?>" />
								<?php echo esc_html( $opt ); ?>
							</label><br />
						<?php endforeach; ?>
					<?php elseif ( 'select' === $field['type'] ) : ?>
						<select id="roga-f-<?php echo esc_attr( $field['id'] ); ?>" name="gzf[<?php echo esc_attr( $field['id'] ); ?>]">
							<option value=""><?php esc_html_e( '— Choisir —', 'roga' ); ?></option>
							<?php foreach ( $field['options'] as $opt ) : ?>
								<option value="<?php echo esc_attr( $opt ); ?>"><?php echo esc_html( $opt ); ?></option>
							<?php endforeach; ?>
						</select>
					<?php elseif ( 'textarea' === $field['type'] ) : ?>
						<textarea id="roga-f-<?php echo esc_attr( $field['id'] ); ?>" name="gzf[<?php echo esc_attr( $field['id'] ); ?>]" rows="4"></textarea>
					<?php elseif ( 'legal' === $field['type'] ) : ?>
						<label><input type="checkbox" name="gzf[<?php echo esc_attr( $field['id'] ); ?>]" value="1" /> <?php echo esc_html( $field['label'] ); ?></label>
					<?php else : ?>
						<input id="roga-f-<?php echo esc_attr( $field['id'] ); ?>"
							type="<?php echo esc_attr( self::html_input_type( $field['type'] ) ); ?>"
							name="gzf[<?php echo esc_attr( $field['id'] ); ?>]"
							placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>" />
					<?php endif; ?>
				</p>
			<?php endforeach; ?>
			<p><button type="submit"><?php echo esc_html( $config['settings']['submit_label'] ); ?></button></p>
		</form>
		<?php
	}

	/**
	 * Maps a field type to an HTML input type.
	 *
	 * @param string $type Field type.
	 * @return string
	 */
	public static function html_input_type( $type ) {
		$map = array(
			'email'  => 'email',
			'tel'    => 'tel',
			'number' => 'number',
			'date'   => 'date',
		);

		return isset( $map[ $type ] ) ? $map[ $type ] : 'text';
	}

	/**
	 * Processes the no-JavaScript submission.
	 */
	public static function handle_fallback() {
		$form_id = isset( $_POST['form_id'] ) ? (int) $_POST['form_id'] : 0;

		if ( ! $form_id || ! isset( $_POST['roga_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['roga_nonce'] ) ), 'roga_fallback_' . $form_id ) ) {
			wp_die( esc_html__( 'Requête expirée. Merci de recharger la page et de réessayer.', 'roga' ) );
		}

		if ( ! empty( $_POST['roga_website'] ) ) {
			wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url( '/' ) );
			exit;
		}

		$raw    = isset( $_POST['gzf'] ) ? wp_unslash( $_POST['gzf'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$result = ROGA_Rest::process( $form_id, is_array( $raw ) ? $raw : array(), array( 'source' => 'nojs' ) );

		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ) );
		}

		$config = ROGA_Forms::get_config( $form_id );

		wp_die(
			'<h1>' . esc_html( $config['thankyou']['title'] ) . '</h1><p>' . esc_html( $config['thankyou']['description'] ) . '</p>',
			esc_html( $config['thankyou']['title'] ),
			array( 'response' => 200 )
		);
	}
}
