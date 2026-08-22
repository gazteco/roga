<?php
/**
 * Preset: "Demande de devis" for Le Château (Bayonne).
 * Reproduces the branching of the original Typeform.
 *
 * @package Roga
 */

defined( 'ABSPATH' ) || exit;

/**
 * Builds the Le Château quotation request form.
 *
 * @return array Configuration ready for ROGA_Forms::create().
 */
function roga_preset_lechateau() {

	$espaces = array(
		'Coliving',
		'Coworking',
		'Salles de réunion (séminaires, formations…)',
		'Domiciliation d\'entreprise',
		'Caves',
	);

	/**
	 * Shorthand for a field definition.
	 *
	 * @param string $id    Field id.
	 * @param string $type  Field type.
	 * @param string $label Question.
	 * @param array  $args  Optional keys: description, placeholder, required, options, allow_other, logic.
	 * @return array
	 */
	$f = function ( $id, $type, $label, $args = array() ) {
		return array_merge(
			array(
				'id'          => $id,
				'type'        => $type,
				'label'       => $label,
				'description' => '',
				'placeholder' => '',
				'required'    => false,
				'logic'       => null,
			),
			$args
		);
	};

	/**
	 * Shorthand for "shown when `espace` equals X".
	 *
	 * @param string $value Espace option.
	 * @return array
	 */
	$when = function ( $value ) {
		return array(
			'mode'  => 'all',
			'rules' => array( array( 'field' => 'espace', 'op' => 'is', 'value' => $value ) ),
		);
	};

	$contact_origine = array(
		'Recherche internet',
		'Recommandation / bouche à oreille',
		'Réseaux sociaux',
		'Presse (TV, radio, article…)',
		'Événement',
		'Publicité',
	);

	$fields = array();

	/* -------- Aiguillage -------- */

	$fields[] = $f( 'espace', 'radio', 'Quel espace vous intéresse ?', array(
		'required' => true,
		'options'  => $espaces,
	) );

	/* -------- Coliving -------- */

	$c = $when( 'Coliving' );

	$fields[] = $f( 'coliving_type', 'checkbox', 'OK, vous cherchez ?', array(
		'description' => 'Espaces communs du Coliving : cuisine, salle à manger, salon, buanderie, sanitaires.',
		'required'    => true,
		'options'     => array(
			'Une chambre avec salle d\'eau privative',
			'Un studio entièrement équipé avec salle d\'eau, sanitaires et cuisine (adapté aux personnes à mobilité réduite)',
		),
		'logic'       => $c,
	) );
	$fields[] = $f( 'coliving_date', 'date', 'Date d\'arrivée envisagée ?', array( 'required' => true, 'logic' => $c ) );
	$fields[] = $f( 'coliving_duree', 'text', 'Durée envisagée ?', array( 'required' => true, 'placeholder' => 'ex : 6 mois', 'logic' => $c ) );
	$fields[] = $f( 'coliving_freq', 'radio', 'Une location…', array(
		'required' => true,
		'options'  => array( 'À la semaine', 'Au mois', 'À l\'année' ),
		'logic'    => $c,
	) );
	$fields[] = $f( 'coliving_services', 'checkbox', 'Un de ces services additionnels vous intéresse ?', array(
		'options' => array( 'Coworking', 'Salles de réunion', 'Domiciliation d\'entreprise', 'Caves' ),
		'logic'   => $c,
	) );

	/* -------- Coworking -------- */

	$c = $when( 'Coworking' );

	$fields[] = $f( 'cowork_type', 'radio', 'OK, vous cherchez ?', array(
		'description' => 'Nous proposons 13 bureaux de 9 à 26 m², mobilier inclus.',
		'required'    => true,
		'options'     => array( 'Un bureau privatif', 'Un bureau fixe en open space', 'Un bureau flex en open space' ),
		'logic'       => $c,
	) );
	$fields[] = $f( 'cowork_date', 'date', 'Date d\'arrivée envisagée ?', array( 'required' => true, 'logic' => $c ) );
	$fields[] = $f( 'cowork_duree', 'text', 'Durée envisagée ?', array( 'required' => true, 'placeholder' => 'ex : 1 an', 'logic' => $c ) );
	$fields[] = $f( 'cowork_freq', 'radio', 'À quelle fréquence ?', array(
		'required'    => true,
		'options'     => array( 'À l\'année', 'Au mois', 'À la semaine', 'À la journée / demi-journée' ),
		'allow_other' => true,
		'logic'       => $c,
	) );
	$fields[] = $f( 'cowork_postes', 'number', 'Pour combien de postes de travail ?', array( 'placeholder' => 'ex : 4', 'logic' => $c ) );
	$fields[] = $f( 'cowork_entreprise', 'text', 'Quel est le nom de votre entreprise ?', array( 'required' => true, 'logic' => $c ) );
	$fields[] = $f( 'cowork_services', 'checkbox', 'Un de ces services additionnels vous intéresse ?', array(
		'options' => array( 'Salles de réunion', 'Service traiteur', 'Domiciliation d\'entreprise', 'Coliving', 'Caves' ),
		'logic'   => $c,
	) );

	/* -------- Salles de réunion -------- */

	$c = $when( 'Salles de réunion (séminaires, formations…)' );

	$fields[] = $f( 'salle_type', 'radio', 'OK, vous souhaitez ?', array(
		'description' => 'Salles entièrement équipées : écran tactile interactif, tableau blanc, tables rabattables. Service traiteur possible.',
		'required'    => true,
		'options'     => array( 'Location à la journée', 'Location à la demi-journée' ),
		'allow_other' => true,
		'logic'       => $c,
	) );
	$fields[] = $f( 'salle_date', 'date', 'Date envisagée ?', array( 'required' => true, 'logic' => $c ) );
	$fields[] = $f( 'salle_duree', 'text', 'Durée envisagée ?', array( 'required' => true, 'placeholder' => 'ex : 2 jours', 'logic' => $c ) );
	$fields[] = $f( 'salle_recurrence', 'radio', 'Est-ce un besoin récurrent ?', array(
		'options' => array( 'Non, seulement occasionnel', 'Oui, chaque semaine', 'Oui, chaque mois', 'Oui, chaque année' ),
		'logic'   => $c,
	) );
	$fields[] = $f( 'salle_personnes', 'number', 'Pour accueillir combien de personnes ?', array( 'required' => true, 'placeholder' => 'ex : 12', 'logic' => $c ) );
	$fields[] = $f( 'salle_entreprise', 'text', 'Quel est le nom de votre entreprise ?', array( 'required' => true, 'logic' => $c ) );
	$fields[] = $f( 'salle_services', 'checkbox', 'Un de ces services additionnels vous intéresse ?', array(
		'options' => array( 'Service traiteur (petit-déjeuner, brunch, lunch…)', 'Un bureau privatif', 'Un bureau fixe en open space', 'Un bureau flex en open space' ),
		'logic'   => $c,
	) );

	/* -------- Domiciliation -------- */

	$c    = $when( 'Domiciliation d\'entreprise' );
	$avec = 'Une domiciliation avec location de bureau';
	$c2   = array(
		'mode'  => 'all',
		'rules' => array(
			array( 'field' => 'espace', 'op' => 'is', 'value' => 'Domiciliation d\'entreprise' ),
			array( 'field' => 'domic_type', 'op' => 'is', 'value' => $avec ),
		),
	);

	$fields[] = $f( 'domic_type', 'radio', 'OK, vous cherchez ?', array(
		'description' => 'Des boîtes aux lettres accessibles 24 h/24.',
		'required'    => true,
		'options'     => array( 'Une domiciliation simple', $avec ),
		'logic'       => $c,
	) );
	$fields[] = $f( 'domic_bureau_type', 'radio', 'Quel type de bureau ?', array(
		'required' => true,
		'options'  => array( 'Un bureau privatif', 'Un bureau fixe en open space', 'Un bureau flex en open space' ),
		'logic'    => $c2,
	) );
	$fields[] = $f( 'domic_bureau_freq', 'radio', 'Une location…', array(
		'required'    => true,
		'options'     => array( 'À l\'année', 'Au mois', 'À la journée / demi-journée' ),
		'allow_other' => true,
		'logic'       => $c2,
	) );
	$fields[] = $f( 'domic_bureau_postes', 'number', 'Pour combien de postes de travail ?', array( 'placeholder' => 'ex : 2', 'logic' => $c2 ) );
	$fields[] = $f( 'domic_date', 'date', 'Date de début de contrat envisagée ?', array( 'required' => true, 'logic' => $c ) );
	$fields[] = $f( 'domic_duree', 'text', 'Durée envisagée ?', array( 'required' => true, 'placeholder' => 'ex : 1 an', 'logic' => $c ) );
	$fields[] = $f( 'domic_entreprise', 'text', 'Quel est le nom de votre entreprise ?', array( 'required' => true, 'logic' => $c ) );

	/* -------- Caves -------- */

	$c = $when( 'Caves' );

	$fields[] = $f( 'cave_taille', 'radio', 'OK, vous cherchez ?', array(
		'description' => '11 caves entièrement rénovées, de 3 à 11 m².',
		'required'    => true,
		'options'     => array( 'Entre 3 et 5 m²', 'Entre 5 et 6 m²', 'Entre 6 et 8 m²', 'Entre 9 et 10 m²', 'Je ne sais pas encore' ),
		'logic'       => $c,
	) );
	$fields[] = $f( 'cave_date', 'date', 'Date d\'arrivée envisagée ?', array( 'required' => true, 'logic' => $c ) );
	$fields[] = $f( 'cave_duree', 'text', 'Durée envisagée ?', array( 'placeholder' => 'ex : 6 mois', 'logic' => $c ) );
	$fields[] = $f( 'cave_services', 'checkbox', 'Un de ces services additionnels vous intéresse ?', array(
		'options' => array( 'Coliving', 'Coworking', 'Domiciliation d\'entreprise', 'Salles de réunion' ),
		'logic'   => $c,
	) );

	/* -------- Bloc contact, commun à tous les parcours -------- */

	$fields[] = $f( 'prenom', 'text', 'Merci, quel est votre prénom ?', array( 'required' => true ) );
	$fields[] = $f( 'nom', 'text', 'Et votre nom ?', array( 'required' => true ) );
	$fields[] = $f( 'email', 'email', 'Quelle est votre adresse e-mail ?', array(
		'description' => 'Nous ne l\'utiliserons que pour échanger sur vos besoins. Ni plus ni moins.',
		'placeholder' => 'vous@exemple.fr',
		'required'    => true,
	) );
	$fields[] = $f( 'telephone', 'tel', 'À quel numéro pouvons-nous vous joindre si besoin ?', array(
		'description' => 'Nous privilégions un premier échange par e-mail. Ce numéro ne sera utilisé qu\'en cas de nécessité.',
		'placeholder' => '+33 6 12 34 56 78',
		'required'    => true,
	) );
	$fields[] = $f( 'dispo_visite', 'text', 'Vos disponibilités pour une visite ?', array(
		'description' => 'Facultatif. Visite en visioconférence également possible.',
	) );
	$fields[] = $f( 'newsletter', 'radio', 'Souhaitez-vous recevoir les actualités du Château ?', array(
		'options' => array( 'Oui', 'Non' ),
	) );
	$fields[] = $f( 'origine', 'radio', 'Par quel biais nous avez-vous connus ?', array(
		'required'    => true,
		'options'     => $contact_origine,
		'allow_other' => true,
	) );
	$fields[] = $f( 'remarque', 'textarea', 'Un dernier détail à nous communiquer ?', array(
		'placeholder' => 'Facultatif',
	) );

	return array(
		'title'    => 'Le Château — Demande de devis',
		'welcome'  => array(
			'enabled'     => true,
			'title'       => 'Bonjour et bienvenue au CHÂTEAU !',
			'description' => "Afin de répondre au mieux à votre demande, nous vous invitons à remplir ce court formulaire (2 minutes maximum).\n\nNotre équipe reviendra vers vous dans les 48 heures.",
			'button'      => 'Commencer',
		),
		'thankyou' => array(
			'title'       => 'Merci de votre réponse !',
			'description' => 'Nous revenons vers vous dans les 48 heures.',
		),
		'fields'   => $fields,
		'settings' => array(
			'submit_label'   => 'Envoyer ma demande',
			'colors'         => array(
				'bg'       => '#eeded1',
				'text'     => '#0d493b',
				'accent'   => '#0d493b',
				'onaccent' => '#ffffff',
				'muted'    => '#6b7f74',
			),
			'notify_enabled' => true,
			'notify_to'      => get_option( 'admin_email' ),
			'notify_subject' => 'Nouvelle demande de devis — Le Château',
			'from_name'      => 'Le Château',
			'from_email'     => '',
			'ack_enabled'    => true,
			'ack_field'      => 'email',
			'ack_subject'    => 'Nous avons bien reçu votre demande — Le Château',
			'ack_intro'      => "Bonjour,\n\nMerci pour votre demande. Notre équipe l'étudie et revient vers vous dans les 48 heures.\n\nVoici le récapitulatif de ce que vous nous avez indiqué :",
			'ack_outro'      => "À très bientôt au Château,",
			'store_entries'  => true,
			'store_ip'       => false,
			'rgpd_notice'    => 'Les informations recueillies servent uniquement à traiter votre demande. Elles ne sont ni cédées ni revendues. Vous pouvez demander leur suppression à tout moment en nous écrivant.',
		),
	);
}
