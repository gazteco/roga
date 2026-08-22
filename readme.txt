=== Roga Forms ===
Contributors: gazteco
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.1.4
License: GPLv2 or later

Conversational forms that ask one question at a time: branching logic, stored
submissions, notifications and acknowledgements. No ads, no limits, no
third-party calls.

== Description ==

Roga — du latin *rogare*, demander.

Le formulaire s'affiche une question à la fois, comme un échange. Le visiteur
ne voit que les questions qui le concernent : la logique conditionnelle masque
tout le reste.

Fonctionnalités :

* Nombre de formulaires illimité, chacun avec son propre habillage.
* Éditeur intégré : ajout, réordonnancement par glisser-déposer, duplication.
* Logique conditionnelle sur toutes les questions, avec plusieurs conditions
  combinables en « toutes » ou « au moins une ».
* Onze types de réponse : texte court et long, e-mail, téléphone, nombre,
  date, choix unique, choix multiple, liste déroulante, consentement, message.
* Enregistrement des demandes dans le site, consultation et export CSV.
* Import / export des formulaires en JSON, pour réutiliser un formulaire d'un
  site client à l'autre.
* Notification par e-mail à chaque demande, avec réponse directe au visiteur.
* Accusé de réception automatique récapitulant les réponses.
* Mises à jour en un clic depuis GitHub Releases, comme n'importe quelle
  extension du répertoire officiel.
* Navigation au clavier : lettres pour les choix, Entrée pour avancer.
* Version de repli complète pour les visiteurs sans JavaScript.
* Anti-spam par pot de miel et limitation de cadence, sans CAPTCHA.

== Installation ==

1. Extensions → Ajouter → Téléverser une extension, puis choisir le zip.
2. Activer l'extension.
3. Menu « Roga Forms » dans la colonne de gauche.
4. Coller le code court affiché dans la page qui doit recevoir le formulaire.

Pour une mise à jour manuelle, il suffit de téléverser le nouveau zip :
WordPress détecte l'extension déjà installée et propose « Remplacer l'actuelle
par celle téléversée ». Rien n'est perdu, ni les formulaires ni les demandes.

== Utilisation ==

Le code court accepte deux attributs :

    [roga id="12"]
    [roga id="12" height="720px"]

L'ancien code court `[gazte_form id="12"]` reste reconnu, pour ne pas casser
les pages publiées avant le changement de nom.

== Mises à jour automatiques ==

Roga interroge les releases GitHub du dépôt configuré et se propose à la mise
à jour comme n'importe quelle extension.

Par défaut le dépôt est `gazteco/roga`. Pour en viser un autre, ou pour un
dépôt privé, ajouter dans wp-config.php :

    define( 'ROGA_GITHUB_REPO', 'organisation/depot' );
    define( 'ROGA_GITHUB_TOKEN', 'ghp_votre_jeton' ); // dépôt privé seulement

Côté dépôt, le workflow `.github/workflows/release.yml` construit le zip et
l'attache à la release dès qu'un tag `vX.Y.Z` est poussé. Le tag doit
correspondre à l'en-tête Version du fichier `roga.php`, sinon la publication
échoue volontairement.

Le résultat des releases est mis en cache six heures. Le lien « Vérifier les
mises à jour », sur l'écran Extensions, force une relecture immédiate.

== Marque blanche ==

Pour livrer le plugin sous une autre identité, deux filtres suffisent :

    add_filter( 'roga_brand',  fn() => 'Formulaires Machin' );
    add_filter( 'roga_byline', '__return_empty_string' );

== Notes techniques ==

* Les réponses sont validées deux fois : dans le navigateur pour le confort,
  puis sur le serveur, qui recalcule lui-même quelles questions étaient
  réellement affichées avant de vérifier les champs obligatoires.
* Les tables de la base ne sont jamais supprimées à la désinstallation, sauf
  si l'option `gzf_delete_data_on_uninstall` est activée.
* Les identifiants internes de base de données ont gardé leur préfixe d'origine
  (`gzf_`) lors du changement de nom, afin qu'aucun formulaire ni aucune
  demande ne soit perdu à la mise à jour.
* Deux points d'extension sont disponibles pour les développeurs :
  le filtre `roga_pre_submit` et l'action `roga_after_submit`.

== Changelog ==

= 1.1.4 =
* Correction : les mises à jour n'étaient pas détectées sur hébergement
  mutualisé. L'API GitHub limite les requêtes anonymes à 60 par heure et par
  adresse IP, quota partagé par tous les sites du même serveur. Pour un dépôt
  public, Roga ne passe plus par l'API : il lit la redirection de
  `releases/latest` et télécharge `roga-x.y.z.zip` à son adresse prévisible.
  L'API reste utilisée uniquement quand un token est configuré (dépôt privé).

= 1.1.3 =
* Version de test du canal de mise à jour GitHub. Aucun changement fonctionnel.

= 1.1.2 =
* Correction : texte blanc sur fond clair au survol des options, lorsque le
  thème style globalement `button:hover` (constaté avec Workalley).
* Correction : champ « Précisez… » décalé vers le haut et boîte trop haute,
  lorsque le thème impose une hauteur et une marge basse à `input[type=text]`.
* Les sélecteurs du formulaire sont renforcés (`.roga-root button.roga-opt`,
  `.roga-root input.roga-input`) pour résister aux styles globaux des thèmes.
* Correction : l'en-tête Version de `roga.php` était resté à 1.1.0 en 1.1.1.

= 1.1.1 =
* Le menu d'administration affiche « Roga Forms », qui tient sur une ligne.
  L'attribution « by Gazte Co. » a été déplacée en tête de l'écran principal.
* Deux filtres pour la marque blanche : `roga_brand` et `roga_byline`.

= 1.1.0 =
* L'extension prend le nom de Roga Forms.
* Mises à jour en un clic depuis GitHub Releases, avec vérification manuelle
  depuis l'écran Extensions.
* Import et export des formulaires en JSON, pour les réutiliser d'un site à
  l'autre.
* Nouveau code court `[roga id="…"]`, l'ancien reste reconnu.
* Les formulaires et les demandes existants sont conservés à la mise à jour.

= 1.0.1 =
* Correction : l'écran de modification d'un formulaire renvoyait « Vous n'avez
  pas l'autorisation d'accéder à cette page ».

= 1.0.0 =
* Première version.
