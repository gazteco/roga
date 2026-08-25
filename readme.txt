=== Roga Forms ===
Contributors: gazteco
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.3.13
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
* Logo sur l'écran d'accueil, choisi dans la médiathèque WordPress.
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

= 1.3.13 =
* Desktop : compaction de l'ecran de verification. Padding vertical
  reduit de 12 a 8 px, gap entre colonnes reduit de 16 a 12 px, taille
  du label reduite de 0.95em a 0.9em. Chaque ligne prend maintenant
  environ 30 pourcents de moins de hauteur, ce qui permet d'afficher
  plus de reponses d'un coup et donne un rendu tableau plus propre.

= 1.3.12 =
* Fix definitif du chevauchement/debordement des options : le cadre etait
  ecrase a une hauteur fixe (~54 px) par une regle du theme, coupant le
  contenu multi-lignes. Le hardening impose desormais height: auto,
  min-height: 0, max-height: none, line-height: 1.4, align-items:
  flex-start et overflow: visible en !important. Aucun theme ne peut plus
  forcer une hauteur ou un cropping sur les cadres d'options.

= 1.3.11 =
* Correction du chevauchement des options : le fix précédent (1.3.10)
  ajoutait un overflow: hidden pour empêcher le débordement, mais cela
  tronquait le texte des options multi-lignes. Le vrai fix est plus simple :
  garder align-items: flex-start et line-height: 1.4 comme dans 1.3.10,
  sans overflow. Le cadre grandit naturellement pour contenir tout le
  texte, et il n'y a plus de débordement puisque le contenu tient dans
  son cadre.

= 1.3.10 =
* Correction : sur mobile, quand une option de question à choix contenait
  du texte multi-ligne, son contenu pouvait déborder visuellement au-delà
  de la bordure supérieure du cadre suivant, donnant l'impression que les
  deux options se chevauchaient. Le layout flex est passé en align-items:
  flex-start avec un line-height explicite de 1.4, et un garde-fou
  overflow: hidden a été ajouté sur .roga-opt pour empêcher tout
  débordement futur, quelle que soit la source du problème.
* Mobile : compaction supplémentaire de la synthèse. Padding réduit à 6 px,
  label à 0.75em avec line-height 1.2, marge nulle entre label et valeur,
  crayon à 28 px. Chaque ligne fait à peu près 40 px de hauteur pour une
  réponse courte, soit environ un tiers de moins que la version précédente.

= 1.3.9 =
* Mobile : chaque ligne de la synthèse est nettement plus compacte. Le
  bouton crayon (Modifier) forçait auparavant la hauteur du flex parent
  à 32 px minimum, laissant beaucoup d'espace blanc en dessous des
  réponses courtes. Il est désormais positionné en absolute à droite,
  centré verticalement, et ne pousse plus la hauteur de la ligne.
  Padding vertical réduit à 8 px et label serré à 0.78em avec line-height
  1.3 : chaque entrée fait à peu près la moitié de sa hauteur précédente.

= 1.3.8 =
* Mobile : sur l'écran de vérification, chaque réponse est désormais tronquée
  à deux lignes maximum avec un ellipsis (…) au bout si elle est plus longue,
  ce qui garde les lignes courtes. La réponse complète reste accessible en
  cliquant Modifier.
* Mobile : le bouton « Modifier » devient une petite icône crayon dans un
  cercle discret à droite de la réponse. Il n'occupe plus une ligne dédiée
  et libère de la hauteur verticale. Le libellé texte « Modifier » reste
  utilisé en desktop et pour les lecteurs d'écran.
* Le spacing entre les options d'une question à choix passe de 10 à 14 px
  pour bien aérer les cadres, surtout sur mobile où les cadres pouvaient
  paraître collés.

= 1.3.7 =
* Mobile : les modalités d'une question à choix passent en 15 px (au lieu
  de 16 px) et leur padding est réduit, pour ne plus dominer visuellement
  l'intitulé de la question et sa description. Le titre de la question
  passe de 21 px à 20 px pour aller dans le même sens.
* Mobile : l'écran de vérification adopte un layout compact en deux lignes
  par ligne (label en petit gris au-dessus, valeur à gauche et bouton
  Modifier à droite en dessous), au lieu de trois éléments empilés
  verticalement. Chaque ligne devient nettement plus courte.

= 1.3.6 =
* Correction : sur mobile, les options d'une question à choix pouvaient se
  chevaucher verticalement. Le spacing entre options reposait sur un margin
  qui pouvait être écrasé par une règle globale du thème (typiquement
  `.elementor-widget button { margin: 0 }` sur les breakpoints tablette
  et mobile). Le margin est désormais imposé avec !important dans le bloc
  de hardening, comme les couleurs.

= 1.3.5 =
* Correction (suite de 1.3.4) : la contrainte de largeur qui limitait la
  synthèse à 640 px venait en réalité du container .roga-stage, pas de la
  card. Elle est maintenant levée spécifiquement quand le stage contient
  une review, via le sélecteur CSS :has(). La synthèse s'étire désormais
  vraiment sur toute la largeur disponible dans son container.

= 1.3.4 =
* Écran de vérification : la synthèse utilise maintenant toute la largeur
  disponible dans son container (elle n'est plus limitée à 640 px comme
  les écrans de questions). Sur une page large, le tableau récapitulatif
  s'étire donc pleinement, ce qui laisse encore plus de place aux libellés
  et aux réponses sans passer à la ligne.

= 1.3.3 =
* Écran de vérification : le tableau utilise maintenant trois colonnes
  distinctes (label, réponse, bouton Modifier) plutôt que deux, avec la
  colonne libellé qui prend 45 % de la largeur au lieu de 34 %. Les
  intitulés de questions un peu longs ne se coupent plus sur deux lignes,
  et le bouton Modifier reste toujours parfaitement aligné à droite.

= 1.3.2 =
* Correction : sur certains thèmes (Workalley notamment), la première option
  d'une question à choix pouvait apparaître avec un fond blanc et son texte
  invisible dès que le curseur passait dessus, et le bouton « Commencer »
  pouvait devenir invisible au survol. C'était dû à des règles CSS globales
  du thème qui recoloraient tous les <button> au :hover et :focus. Les
  couleurs Roga sont désormais imposées avec priorité maximale sur tous les
  états, quelle que soit la feuille de style du thème.

= 1.3.1 =
* Écran de vérification retravaillé : mise en page plus dense et plus nette
  (séparateurs entre les lignes plutôt que cartes individuelles, bordures
  droites, moins d'espacement vertical) pour réduire le scroll.
* Le bouton d'envoi apparaît maintenant à deux endroits : en haut de la
  synthèse, sous le titre, et dans un bandeau collé en bas de l'écran qui
  reste visible pendant que le visiteur fait défiler ses réponses. Objectif :
  ne jamais avoir à chercher où valider.

= 1.3.0 =
* Écran de vérification avant l'envoi : le visiteur voit un récapitulatif de
  ses réponses avec un bouton « Modifier » sur chaque ligne, puis clique
  « Envoyer » pour valider. Modifier une réponse ramène directement à la
  synthèse après validation, sans rejouer les questions suivantes.
* Nouveau réglage par formulaire : « Afficher un écran de vérification des
  réponses avant l'envoi ». Activé par défaut sur les nouveaux formulaires et
  les formulaires existants ; peut être désactivé pour envoyer directement
  après la dernière question.

= 1.2.0 =
* Logo sur l'écran d'accueil : sélection depuis la médiathèque WordPress,
  hauteur réglable, texte alternatif pour les lecteurs d'écran.

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
