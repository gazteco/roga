<?php
/**
 * Administration screens: form list, editor, entries and CSV export.
 *
 * @package Roga
 */

defined( 'ABSPATH' ) || exit;

class ROGA_Admin {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_post_roga_save_form', array( __CLASS__, 'save_form' ) );
		add_action( 'admin_post_roga_new_form', array( __CLASS__, 'new_form' ) );
		add_action( 'admin_post_roga_duplicate_form', array( __CLASS__, 'duplicate_form' ) );
		add_action( 'admin_post_roga_delete_form', array( __CLASS__, 'delete_form' ) );
		add_action( 'admin_post_roga_entry_action', array( __CLASS__, 'entry_action' ) );
		add_action( 'admin_post_roga_export', array( __CLASS__, 'export_csv' ) );
		add_action( 'admin_post_roga_export_form', array( __CLASS__, 'export_form' ) );
		add_action( 'admin_post_roga_import_form', array( __CLASS__, 'import_form' ) );
	}

	/**
	 * Registers the menu tree.
	 */
	public static function menu() {
		$cap   = roga_capability();
		$count = ROGA_Entries::count_new();
		$badge = $count ? ' <span class="update-plugins count-' . (int) $count . '"><span class="update-count">' . (int) $count . '</span></span>' : '';

		add_menu_page(
			roga_brand(),
			roga_brand() . $badge,
			$cap,
			'roga',
			array( __CLASS__, 'page_forms' ),
			'dashicons-format-chat',
			26
		);

		add_submenu_page( 'roga', __( 'Formulaires', 'roga' ), __( 'Formulaires', 'roga' ), $cap, 'roga', array( __CLASS__, 'page_forms' ) );
		add_submenu_page( 'roga', __( 'Demandes reçues', 'roga' ), __( 'Demandes reçues', 'roga' ), $cap, 'roga-entries', array( __CLASS__, 'page_entries' ) );
	}

	/**
	 * URL of the editor for one form.
	 *
	 * @param int $form_id Form post ID.
	 * @return string
	 */
	public static function edit_url( $form_id ) {
		return admin_url( 'admin.php?page=roga&form=' . (int) $form_id );
	}

	/**
	 * Loads editor assets only on the editor screen.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function assets( $hook ) {
		if ( false === strpos( $hook, 'roga' ) ) {
			return;
		}

		wp_enqueue_style( 'roga-admin', ROGA_URL . 'assets/admin.css', array(), ROGA_VERSION );

		// phpcs:ignore WordPress.Security.NonceVerification
		$editing = isset( $_GET['page'] ) && 'roga' === $_GET['page'] && isset( $_GET['form'] );

		if ( $editing ) {
			// The welcome-screen logo picker uses the WordPress media library.
			wp_enqueue_media();
			wp_enqueue_script( 'roga-admin', ROGA_URL . 'assets/admin.js', array( 'jquery' ), ROGA_VERSION, true );
			wp_localize_script(
				'roga-admin',
				'ROGA_ADMIN',
				array(
					'types'       => ROGA_Forms::field_types(),
					'choiceTypes' => ROGA_Forms::choice_types(),
					'i18n'        => array(
						'confirmDelete' => __( 'Supprimer cette question ?', 'roga' ),
						'newField'      => __( 'Nouvelle question', 'roga' ),
						'noFields'      => __( 'Aucune question pour le moment. Ajoutez-en une pour commencer.', 'roga' ),
						'always'        => __( 'Toujours affichée', 'roga' ),
						'conditional'   => __( 'Affichée sous condition', 'roga' ),
					),
				)
			);
		}
	}

	/* ---------------------------------------------------------------------
	 * Screens
	 * ------------------------------------------------------------------ */

	/**
	 * Main screen: the form list, or the editor when a form is selected.
	 */
	public static function page_forms() {
		if ( ! current_user_can( roga_capability() ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'roga' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification
		if ( isset( $_GET['form'] ) ) {
			self::page_edit();
			return;
		}

		$forms = ROGA_Forms::all();

		// phpcs:ignore WordPress.Security.NonceVerification
		if ( isset( $_GET['imported'] ) ) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html( sprintf(
					/* translators: %d: number of imported forms. */
					_n( '%d formulaire importé.', '%d formulaires importés.', (int) $_GET['imported'], 'roga' ), // phpcs:ignore WordPress.Security.NonceVerification
					(int) $_GET['imported'] // phpcs:ignore WordPress.Security.NonceVerification
				) )
			);
		}
		?>
		<div class="wrap roga-wrap">
			<div class="roga-brandbar">
				<span class="roga-brandmark" aria-hidden="true">R</span>
				<span class="roga-brandname"><?php echo esc_html( roga_brand() ); ?></span>
				<?php if ( roga_byline() ) : ?>
					<span class="roga-byline"><?php echo esc_html( roga_byline() ); ?></span>
				<?php endif; ?>
			</div>
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Formulaires', 'roga' ); ?></h1>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
				<input type="hidden" name="action" value="roga_new_form" />
				<?php wp_nonce_field( 'roga_new_form' ); ?>
				<button type="submit" class="page-title-action"><?php esc_html_e( 'Ajouter un formulaire', 'roga' ); ?></button>
			</form>
			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="roga-import">
				<input type="hidden" name="action" value="roga_import_form" />
				<?php wp_nonce_field( 'roga_import_form' ); ?>
				<label class="button">
					<?php esc_html_e( 'Importer un fichier .json', 'roga' ); ?>
					<input type="file" name="roga_file" accept="application/json,.json" onchange="this.form.submit()" hidden />
				</label>
			</form>
			<hr class="wp-header-end" />

			<?php if ( empty( $forms ) ) : ?>
				<p><?php esc_html_e( 'Aucun formulaire pour le moment.', 'roga' ); ?></p>
			<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Titre', 'roga' ); ?></th>
						<th><?php esc_html_e( 'Questions', 'roga' ); ?></th>
						<th><?php esc_html_e( 'Demandes', 'roga' ); ?></th>
						<th><?php esc_html_e( 'Code court', 'roga' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $forms as $form ) : ?>
					<?php
					$config = ROGA_Forms::get_config( $form->ID );
					$new    = ROGA_Entries::count_new( $form->ID );
					$all    = ROGA_Entries::query( array( 'form_id' => $form->ID, 'per_page' => 1 ) );
					?>
					<tr>
						<td>
							<strong><a href="<?php echo esc_url( self::edit_url( $form->ID ) ); ?>"><?php echo esc_html( get_the_title( $form ) ); ?></a></strong>
						</td>
						<td><?php echo (int) count( $config['fields'] ); ?></td>
						<td>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=roga-entries&form=' . $form->ID ) ); ?>">
								<?php echo (int) $all['total']; ?>
							</a>
							<?php if ( $new ) : ?>
								<span class="roga-pill"><?php echo (int) $new; ?> <?php esc_html_e( 'nouvelle(s)', 'roga' ); ?></span>
							<?php endif; ?>
						</td>
						<td><code>[roga id="<?php echo (int) $form->ID; ?>"]</code></td>
						<td class="roga-row-actions">
							<a class="button button-small" href="<?php echo esc_url( self::edit_url( $form->ID ) ); ?>"><?php esc_html_e( 'Modifier', 'roga' ); ?></a>
							<?php self::mini_form( 'roga_duplicate_form', $form->ID, __( 'Dupliquer', 'roga' ) ); ?>
							<?php self::mini_form( 'roga_export_form', $form->ID, __( 'Exporter', 'roga' ) ); ?>
							<?php self::mini_form( 'roga_delete_form', $form->ID, __( 'Supprimer', 'roga' ), __( 'Supprimer définitivement ce formulaire ? Les demandes déjà reçues seront conservées.', 'roga' ) ); ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Renders a one button POST form used by row actions.
	 *
	 * @param string $action  admin-post action.
	 * @param int    $form_id Form ID.
	 * @param string $label   Button label.
	 * @param string $confirm Optional confirmation message.
	 */
	protected static function mini_form( $action, $form_id, $label, $confirm = '' ) {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline"
			<?php if ( $confirm ) : ?>onsubmit="return confirm('<?php echo esc_js( $confirm ); ?>')"<?php endif; ?>>
			<input type="hidden" name="action" value="<?php echo esc_attr( $action ); ?>" />
			<input type="hidden" name="form" value="<?php echo esc_attr( $form_id ); ?>" />
			<?php wp_nonce_field( $action . '_' . $form_id ); ?>
			<button type="submit" class="button button-small"><?php echo esc_html( $label ); ?></button>
		</form>
		<?php
	}

	/**
	 * Form editor screen.
	 */
	public static function page_edit() {
		if ( ! current_user_can( roga_capability() ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'roga' ) );
		}

		$form_id = isset( $_GET['form'] ) ? (int) $_GET['form'] : 0; // phpcs:ignore WordPress.Security.NonceVerification
		$config  = ROGA_Forms::get_config( $form_id );

		if ( ! $config ) {
			wp_die( esc_html__( 'Formulaire introuvable.', 'roga' ) );
		}

		$title = get_the_title( $form_id );

		if ( isset( $_GET['saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Formulaire enregistré.', 'roga' ) . '</p></div>';
		}
		?>
		<div class="wrap roga-wrap roga-editor-wrap">
			<h1><?php esc_html_e( 'Modifier le formulaire', 'roga' ); ?></h1>
			<p class="roga-shortcode-hint">
				<?php esc_html_e( 'Pour afficher ce formulaire, collez ce code court dans une page :', 'roga' ); ?>
				<code>[roga id="<?php echo (int) $form_id; ?>"]</code>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="roga-editor-form">
				<input type="hidden" name="action" value="roga_save_form" />
				<input type="hidden" name="form" value="<?php echo esc_attr( $form_id ); ?>" />
				<?php wp_nonce_field( 'roga_save_form_' . $form_id ); ?>
				<input type="hidden" name="config" id="roga-config-input" value="" />

				<p>
					<label for="roga-title"><strong><?php esc_html_e( 'Nom du formulaire', 'roga' ); ?></strong></label><br />
					<input type="text" id="roga-title" name="title" class="regular-text" value="<?php echo esc_attr( $title ); ?>" />
				</p>

				<div class="roga-tabs">
					<button type="button" class="roga-tab is-active" data-tab="questions"><?php esc_html_e( 'Questions', 'roga' ); ?></button>
					<button type="button" class="roga-tab" data-tab="screens"><?php esc_html_e( 'Écrans', 'roga' ); ?></button>
					<button type="button" class="roga-tab" data-tab="notifications"><?php esc_html_e( 'Notifications', 'roga' ); ?></button>
					<button type="button" class="roga-tab" data-tab="design"><?php esc_html_e( 'Apparence', 'roga' ); ?></button>
				</div>

				<div id="roga-editor-root"
					data-config="<?php echo esc_attr( wp_json_encode( $config ) ); ?>"></div>

				<p class="roga-save-bar">
					<button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Enregistrer le formulaire', 'roga' ); ?></button>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=roga' ) ); ?>"><?php esc_html_e( 'Retour à la liste', 'roga' ); ?></a>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Entries screen: list, detail and export.
	 */
	public static function page_entries() {
		if ( ! current_user_can( roga_capability() ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'roga' ) );
		}

		// phpcs:disable WordPress.Security.NonceVerification
		$entry_id = isset( $_GET['entry'] ) ? (int) $_GET['entry'] : 0;
		$form_id  = isset( $_GET['form'] ) ? (int) $_GET['form'] : 0;
		$search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$paged    = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
		// phpcs:enable WordPress.Security.NonceVerification

		if ( $entry_id ) {
			self::render_entry( $entry_id );
			return;
		}

		$per_page = 25;
		$result   = ROGA_Entries::query(
			array(
				'form_id'  => $form_id,
				'search'   => $search,
				'page'     => $paged,
				'per_page' => $per_page,
			)
		);
		$forms    = ROGA_Forms::all();
		?>
		<div class="wrap roga-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Demandes reçues', 'roga' ); ?></h1>

			<form method="get" class="roga-filters">
				<input type="hidden" name="page" value="roga-entries" />
				<select name="form">
					<option value="0"><?php esc_html_e( 'Tous les formulaires', 'roga' ); ?></option>
					<?php foreach ( $forms as $f ) : ?>
						<option value="<?php echo (int) $f->ID; ?>" <?php selected( $form_id, $f->ID ); ?>><?php echo esc_html( get_the_title( $f ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Rechercher…', 'roga' ); ?>" />
				<button type="submit" class="button"><?php esc_html_e( 'Filtrer', 'roga' ); ?></button>
			</form>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="roga-export">
				<input type="hidden" name="action" value="roga_export" />
				<input type="hidden" name="form" value="<?php echo (int) $form_id; ?>" />
				<?php wp_nonce_field( 'roga_export' ); ?>
				<button type="submit" class="button"><?php esc_html_e( 'Exporter en CSV', 'roga' ); ?></button>
			</form>

			<?php if ( empty( $result['items'] ) ) : ?>
				<p><?php esc_html_e( 'Aucune demande pour le moment.', 'roga' ); ?></p>
			<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Date', 'roga' ); ?></th>
						<th><?php esc_html_e( 'Formulaire', 'roga' ); ?></th>
						<th><?php esc_html_e( 'Aperçu', 'roga' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $result['items'] as $row ) : ?>
					<tr<?php echo 'new' === $row['status'] ? ' class="roga-unread"' : ''; ?>>
						<td><?php echo esc_html( mysql2date( 'j M Y à H:i', $row['created_at'] ) ); ?></td>
						<td><?php echo esc_html( get_the_title( $row['form_id'] ) ); ?></td>
						<td><?php echo esc_html( self::preview( $row ) ); ?></td>
						<td>
							<a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=roga-entries&entry=' . (int) $row['id'] ) ); ?>"><?php esc_html_e( 'Voir', 'roga' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<?php
			$pages = (int) ceil( $result['total'] / $per_page );
			if ( $pages > 1 ) {
				echo '<div class="tablenav"><div class="tablenav-pages">';
				echo wp_kses_post(
					paginate_links(
						array(
							'base'    => add_query_arg( 'paged', '%#%' ),
							'format'  => '',
							'current' => $paged,
							'total'   => $pages,
						)
					)
				);
				echo '</div></div>';
			}
			?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Renders one entry in detail.
	 *
	 * @param int $entry_id Entry ID.
	 */
	protected static function render_entry( $entry_id ) {
		$entry = ROGA_Entries::get( $entry_id );

		if ( ! $entry ) {
			wp_die( esc_html__( 'Demande introuvable.', 'roga' ) );
		}

		if ( 'new' === $entry['status'] ) {
			ROGA_Entries::set_status( $entry_id, 'read' );
		}

		$labels = isset( $entry['meta']['labels'] ) && is_array( $entry['meta']['labels'] ) ? $entry['meta']['labels'] : array();
		$config = ROGA_Forms::get_config( $entry['form_id'] );
		?>
		<div class="wrap roga-wrap">
			<h1><?php esc_html_e( 'Demande', 'roga' ); ?> #<?php echo (int) $entry['id']; ?></h1>
			<p>
				<?php echo esc_html( mysql2date( 'j F Y à H:i', $entry['created_at'] ) ); ?>
				— <?php echo esc_html( get_the_title( $entry['form_id'] ) ); ?>
			</p>

			<table class="widefat striped roga-entry-table">
				<tbody>
				<?php foreach ( (array) $entry['data'] as $key => $value ) : ?>
					<?php
					$value = is_array( $value ) ? implode( ', ', $value ) : (string) $value;
					if ( '' === trim( $value ) ) {
						continue;
					}
					$label = isset( $labels[ $key ] ) ? $labels[ $key ] : $key;
					?>
					<tr>
						<th style="width:35%"><?php echo esc_html( $label ); ?></th>
						<td><?php echo nl2br( esc_html( $value ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<?php
			$email = $config ? ROGA_Mailer::find_visitor_email( $config, (array) $entry['data'] ) : '';
			if ( $email ) :
				?>
				<p><a class="button button-primary" href="mailto:<?php echo esc_attr( $email ); ?>"><?php esc_html_e( 'Répondre par e-mail', 'roga' ); ?></a></p>
			<?php endif; ?>

			<p>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=roga-entries' ) ); ?>"><?php esc_html_e( 'Retour à la liste', 'roga' ); ?></a>
				<?php self::mini_form( 'roga_entry_action', $entry_id, __( 'Supprimer cette demande', 'roga' ), __( 'Supprimer définitivement cette demande ?', 'roga' ) ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Builds a short preview string for the entries table.
	 *
	 * @param array $row Entry row.
	 * @return string
	 */
	protected static function preview( $row ) {
		$bits = array();

		foreach ( (array) $row['data'] as $value ) {
			$value = is_array( $value ) ? implode( ', ', $value ) : (string) $value;
			if ( '' !== trim( $value ) ) {
				$bits[] = $value;
			}
			if ( count( $bits ) >= 3 ) {
				break;
			}
		}

		return wp_trim_words( implode( ' — ', $bits ), 16 );
	}

	/* ---------------------------------------------------------------------
	 * Actions
	 * ------------------------------------------------------------------ */

	/**
	 * Creates an empty form and redirects to its editor.
	 */
	public static function new_form() {
		check_admin_referer( 'roga_new_form' );

		if ( ! current_user_can( roga_capability() ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'roga' ) );
		}

		$config          = ROGA_Forms::default_config();
		$config['title'] = __( 'Nouveau formulaire', 'roga' );
		$id              = ROGA_Forms::create( $config );

		wp_safe_redirect( self::edit_url( $id ) );
		exit;
	}

	/**
	 * Persists the editor payload.
	 */
	public static function save_form() {
		$form_id = isset( $_POST['form'] ) ? (int) $_POST['form'] : 0;

		check_admin_referer( 'roga_save_form_' . $form_id );

		if ( ! current_user_can( roga_capability() ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'roga' ) );
		}

		$raw    = isset( $_POST['config'] ) ? wp_unslash( $_POST['config'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$config = json_decode( (string) $raw, true );

		if ( is_array( $config ) ) {
			ROGA_Forms::save_config( $form_id, $config );
		}

		if ( isset( $_POST['title'] ) ) {
			wp_update_post(
				array(
					'ID'         => $form_id,
					'post_title' => sanitize_text_field( wp_unslash( $_POST['title'] ) ),
				)
			);
		}

		wp_safe_redirect( self::edit_url( $form_id ) . '&saved=1' );
		exit;
	}

	/**
	 * Duplicates a form, configuration included.
	 */
	public static function duplicate_form() {
		$form_id = isset( $_POST['form'] ) ? (int) $_POST['form'] : 0;

		check_admin_referer( 'roga_duplicate_form_' . $form_id );

		if ( ! current_user_can( roga_capability() ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'roga' ) );
		}

		$config = ROGA_Forms::get_config( $form_id );

		if ( $config ) {
			$config['title'] = get_the_title( $form_id ) . ' ' . __( '(copie)', 'roga' );
			ROGA_Forms::create( $config );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=roga' ) );
		exit;
	}

	/**
	 * Deletes a form. Entries are deliberately kept.
	 */
	public static function delete_form() {
		$form_id = isset( $_POST['form'] ) ? (int) $_POST['form'] : 0;

		check_admin_referer( 'roga_delete_form_' . $form_id );

		if ( ! current_user_can( roga_capability() ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'roga' ) );
		}

		wp_delete_post( $form_id, true );

		wp_safe_redirect( admin_url( 'admin.php?page=roga' ) );
		exit;
	}

	/**
	 * Deletes one entry.
	 */
	public static function entry_action() {
		$entry_id = isset( $_POST['form'] ) ? (int) $_POST['form'] : 0;

		check_admin_referer( 'roga_entry_action_' . $entry_id );

		if ( ! current_user_can( roga_capability() ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'roga' ) );
		}

		ROGA_Entries::delete( $entry_id );

		wp_safe_redirect( admin_url( 'admin.php?page=roga-entries' ) );
		exit;
	}

	/**
	 * Streams the entries of one form (or all) as CSV.
	 */
	public static function export_csv() {
		check_admin_referer( 'roga_export' );

		if ( ! current_user_can( roga_capability() ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'roga' ) );
		}

		$form_id = isset( $_POST['form'] ) ? (int) $_POST['form'] : 0;
		$result  = ROGA_Entries::query(
			array(
				'form_id'  => $form_id,
				'per_page' => 5000,
			)
		);

		$columns = array();
		$labels  = array();

		if ( $form_id ) {
			$config = ROGA_Forms::get_config( $form_id );
			if ( $config ) {
				foreach ( $config['fields'] as $field ) {
					if ( 'statement' === $field['type'] ) {
						continue;
					}
					$columns[]                 = $field['id'];
					$labels[ $field['id'] ] = $field['label'];
				}
			}
		}

		// Any key present in the data but missing from the current configuration
		// (an older version of the form) still gets its own column.
		foreach ( $result['items'] as $row ) {
			foreach ( (array) $row['data'] as $key => $ignored ) {
				if ( ! in_array( $key, $columns, true ) ) {
					$columns[] = $key;
					if ( ! isset( $labels[ $key ] ) && isset( $row['meta']['labels'][ $key ] ) ) {
						$labels[ $key ] = $row['meta']['labels'][ $key ];
					}
				}
			}
		}

		$filename = 'roga-' . ( $form_id ? sanitize_title( get_the_title( $form_id ) ) : 'toutes' ) . '-' . gmdate( 'Y-m-d' ) . '.csv';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		$out = fopen( 'php://output', 'w' );

		// BOM so Excel opens accented characters correctly.
		fwrite( $out, "\xEF\xBB\xBF" );

		$header = array( __( 'Date', 'roga' ), __( 'Formulaire', 'roga' ) );
		foreach ( $columns as $key ) {
			$header[] = isset( $labels[ $key ] ) ? $labels[ $key ] : $key;
		}
		fputcsv( $out, $header, ';' );

		foreach ( $result['items'] as $row ) {
			$line = array(
				mysql2date( 'Y-m-d H:i', $row['created_at'] ),
				get_the_title( $row['form_id'] ),
			);
			foreach ( $columns as $key ) {
				$value  = isset( $row['data'][ $key ] ) ? $row['data'][ $key ] : '';
				$value  = is_array( $value ) ? implode( ', ', $value ) : (string) $value;
				$line[] = self::csv_safe( $value );
			}
			fputcsv( $out, $line, ';' );
		}

		fclose( $out );
		exit;
	}


	/**
	 * Downloads one form as JSON so it can be reused on another site.
	 */
	public static function export_form() {
		$form_id = isset( $_POST['form'] ) ? (int) $_POST['form'] : 0;

		check_admin_referer( 'roga_export_form_' . $form_id );

		if ( ! current_user_can( roga_capability() ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'roga' ) );
		}

		$config = ROGA_Forms::get_config( $form_id );

		if ( ! $config ) {
			wp_die( esc_html__( 'Formulaire introuvable.', 'roga' ) );
		}

		$config['title'] = get_the_title( $form_id );

		$payload = array(
			'roga'      => ROGA_VERSION,
			'exported'  => gmdate( 'c' ),
			'origin'    => home_url( '/' ),
			'forms'     => array( $config ),
		);

		$filename = 'roga-' . sanitize_title( $config['title'] ) . '-' . gmdate( 'Y-m-d' ) . '.json';

		nocache_headers();
		header( 'Content-Type: application/json; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		echo wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		exit;
	}

	/**
	 * Creates one or more forms from an uploaded JSON export.
	 */
	public static function import_form() {
		check_admin_referer( 'roga_import_form' );

		if ( ! current_user_can( roga_capability() ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'roga' ) );
		}

		if ( empty( $_FILES['roga_file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['roga_file']['tmp_name'] ) ) {
			wp_die( esc_html__( 'Aucun fichier reçu.', 'roga' ) );
		}

		$size = isset( $_FILES['roga_file']['size'] ) ? (int) $_FILES['roga_file']['size'] : 0;

		if ( $size <= 0 || $size > 2 * MB_IN_BYTES ) {
			wp_die( esc_html__( 'Fichier vide ou trop volumineux (2 Mo maximum).', 'roga' ) );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions
		$raw     = file_get_contents( $_FILES['roga_file']['tmp_name'] );
		$payload = json_decode( (string) $raw, true );

		if ( ! is_array( $payload ) ) {
			wp_die( esc_html__( 'Ce fichier n\'est pas un export Roga valide.', 'roga' ) );
		}

		// Accept both a full export and a single bare form.
		$forms = isset( $payload['forms'] ) && is_array( $payload['forms'] ) ? $payload['forms'] : array( $payload );
		$made  = 0;

		foreach ( $forms as $config ) {
			if ( ! is_array( $config ) || ! isset( $config['fields'] ) ) {
				continue;
			}
			$config['title'] = isset( $config['title'] ) && '' !== trim( (string) $config['title'] )
				? $config['title']
				: __( 'Formulaire importé', 'roga' );

			if ( ROGA_Forms::create( $config ) ) {
				$made++;
			}
		}

		if ( ! $made ) {
			wp_die( esc_html__( 'Aucun formulaire exploitable dans ce fichier.', 'roga' ) );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=roga&imported=' . $made ) );
		exit;
	}

	/**
	 * Neutralises spreadsheet formula injection.
	 *
	 * @param string $value Cell value.
	 * @return string
	 */
	protected static function csv_safe( $value ) {
		if ( '' !== $value && in_array( $value[0], array( '=', '+', '-', '@' ), true ) ) {
			return "'" . $value;
		}
		return $value;
	}
}
