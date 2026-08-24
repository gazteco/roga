/**
 * Roga by Gazte Co. — form editor.
 * No build step, no dependencies: the whole editor is one small state object
 * serialised into a hidden input when the page is submitted.
 */
( function () {
	'use strict';

	var root = document.getElementById( 'roga-editor-root' );
	if ( ! root ) {
		return;
	}

	var T      = ( window.ROGA_ADMIN && window.ROGA_ADMIN.i18n ) || {};
	var TYPES  = ( window.ROGA_ADMIN && window.ROGA_ADMIN.types ) || {};
	var CHOICE = ( window.ROGA_ADMIN && window.ROGA_ADMIN.choiceTypes ) || [];

	var state    = JSON.parse( root.getAttribute( 'data-config' ) || '{}' );
	var selected = 0;
	var tab      = 'questions';

	state.fields   = state.fields || [];
	state.welcome  = state.welcome || {};
	state.thankyou = state.thankyou || {};
	state.settings = state.settings || {};
	state.settings.colors = state.settings.colors || {};

	/* ---------- helpers ---------- */

	function el( tag, attrs, children ) {
		var node = document.createElement( tag );
		attrs = attrs || {};
		Object.keys( attrs ).forEach( function ( key ) {
			if ( 'class' === key ) {
				node.className = attrs[ key ];
			} else if ( 'text' === key ) {
				node.textContent = attrs[ key ];
			} else if ( 0 === key.indexOf( 'on' ) ) {
				node.addEventListener( key.slice( 2 ), attrs[ key ] );
			} else if ( null !== attrs[ key ] && false !== attrs[ key ] ) {
				node.setAttribute( key, attrs[ key ] );
			}
		} );
		( children || [] ).forEach( function ( child ) {
			if ( child ) {
				node.appendChild( 'string' === typeof child ? document.createTextNode( child ) : child );
			}
		} );
		return node;
	}

	function slug( text ) {
		return ( text || '' )
			.toString()
			.normalize( 'NFD' )
			.replace( /[̀-ͯ]/g, '' )
			.toLowerCase()
			.replace( /[^a-z0-9]+/g, '_' )
			.replace( /^_+|_+$/g, '' )
			.slice( 0, 40 );
	}

	function uniqueId( base, skipIndex ) {
		var id = base || 'champ';
		var n  = 2;
		var taken = state.fields
			.filter( function ( f, i ) { return i !== skipIndex; } )
			.map( function ( f ) { return f.id; } );
		while ( -1 !== taken.indexOf( id ) ) {
			id = base + '_' + n;
			n++;
		}
		return id;
	}

	function isChoice( type ) {
		return -1 !== CHOICE.indexOf( type );
	}

	function field( label, control, hint ) {
		return el( 'div', { class: 'roga-field' }, [
			el( 'label', { class: 'roga-label', text: label } ),
			control,
			hint ? el( 'p', { class: 'roga-hint', text: hint } ) : null,
		] );
	}

	function input( value, onChange, attrs ) {
		attrs = attrs || {};
		attrs.type  = attrs.type || 'text';
		attrs.value = value || '';
		attrs.oninput = function ( e ) { onChange( e.target.value ); };
		return el( 'input', attrs );
	}

	function textarea( value, onChange, rows ) {
		var node = el( 'textarea', { rows: rows || 3 } );
		node.value = value || '';
		node.addEventListener( 'input', function ( e ) { onChange( e.target.value ); } );
		return node;
	}

	function select( value, options, onChange ) {
		var node = el( 'select', {} );
		options.forEach( function ( opt ) {
			var o = el( 'option', { value: opt.value, text: opt.label } );
			if ( opt.value === value ) {
				o.selected = true;
			}
			node.appendChild( o );
		} );
		node.addEventListener( 'change', function ( e ) { onChange( e.target.value ); } );
		return node;
	}

	function checkbox( label, checked, onChange ) {
		var box = el( 'input', { type: 'checkbox' } );
		box.checked = !! checked;
		box.addEventListener( 'change', function ( e ) { onChange( e.target.checked ); } );
		return el( 'label', { class: 'roga-check' }, [ box, el( 'span', { text: label } ) ] );
	}

	/* ---------- questions tab ---------- */

	function renderQuestions() {
		var wrap = el( 'div', { class: 'roga-cols' } );

		/* left: the list */
		var list = el( 'div', { class: 'roga-list' } );

		if ( ! state.fields.length ) {
			list.appendChild( el( 'p', { class: 'roga-empty', text: T.noFields || '' } ) );
		}

		state.fields.forEach( function ( f, i ) {
			var badge = f.logic && f.logic.rules && f.logic.rules.length ? '⑂' : '';
			var item  = el(
				'div',
				{
					class: 'roga-item' + ( i === selected ? ' is-selected' : '' ),
					draggable: 'true',
					'data-index': i,
					onclick: function () { selected = i; render(); },
				},
				[
					el( 'span', { class: 'roga-item-num', text: String( i + 1 ) } ),
					el( 'span', { class: 'roga-item-label', text: f.label || TYPES[ f.type ] || f.type } ),
					el( 'span', { class: 'roga-item-type', text: badge + ' ' + ( TYPES[ f.type ] || f.type ) } ),
				]
			);

			item.addEventListener( 'dragstart', function ( e ) {
				e.dataTransfer.setData( 'text/plain', String( i ) );
				e.dataTransfer.effectAllowed = 'move';
			} );
			item.addEventListener( 'dragover', function ( e ) {
				e.preventDefault();
				item.classList.add( 'is-over' );
			} );
			item.addEventListener( 'dragleave', function () { item.classList.remove( 'is-over' ); } );
			item.addEventListener( 'drop', function ( e ) {
				e.preventDefault();
				item.classList.remove( 'is-over' );
				var from = parseInt( e.dataTransfer.getData( 'text/plain' ), 10 );
				if ( isNaN( from ) || from === i ) {
					return;
				}
				var moved = state.fields.splice( from, 1 )[ 0 ];
				state.fields.splice( i, 0, moved );
				selected = i;
				render();
			} );

			list.appendChild( item );
		} );

		var adder = el( 'div', { class: 'roga-adder' } );
		var typeSelect = select(
			'text',
			Object.keys( TYPES ).map( function ( k ) { return { value: k, label: TYPES[ k ] }; } ),
			function () {}
		);
		adder.appendChild( typeSelect );
		adder.appendChild(
			el( 'button', {
				type: 'button',
				class: 'button button-primary',
				text: '+',
				onclick: function () {
					var type = typeSelect.value;
					var f    = {
						id: uniqueId( 'champ_' + ( state.fields.length + 1 ) ),
						type: type,
						label: T.newField || 'Question',
						description: '',
						placeholder: '',
						required: false,
						logic: null,
					};
					if ( isChoice( type ) ) {
						f.options     = [ 'Option 1', 'Option 2' ];
						f.allow_other = false;
					}
					state.fields.push( f );
					selected = state.fields.length - 1;
					render();
				},
			} )
		);
		list.appendChild( adder );

		wrap.appendChild( list );

		/* right: the detail panel */
		wrap.appendChild( renderDetail() );

		return wrap;
	}

	function renderDetail() {
		var panel = el( 'div', { class: 'roga-panel' } );
		var f     = state.fields[ selected ];

		if ( ! f ) {
			panel.appendChild( el( 'p', { class: 'roga-empty', text: T.noFields || '' } ) );
			return panel;
		}

		panel.appendChild(
			field(
				'Question',
				textarea( f.label, function ( v ) {
					f.label = v;
					refreshList();
				}, 2 )
			)
		);

		panel.appendChild(
			field(
				'Précision sous la question',
				textarea( f.description, function ( v ) { f.description = v; }, 2 ),
				'Facultatif. S\'affiche en plus petit sous la question.'
			)
		);

		panel.appendChild(
			field(
				'Type de réponse',
				select(
					f.type,
					Object.keys( TYPES ).map( function ( k ) { return { value: k, label: TYPES[ k ] }; } ),
					function ( v ) {
						f.type = v;
						if ( isChoice( v ) && ! f.options ) {
							f.options     = [ 'Option 1', 'Option 2' ];
							f.allow_other = false;
						}
						render();
					}
				)
			)
		);

		if ( 'statement' !== f.type ) {
			panel.appendChild(
				field(
					'Identifiant',
					input( f.id, function ( v ) {
						f.id = uniqueId( slug( v ) || 'champ', selected );
					} ),
					'Sert de nom de colonne dans l\'export CSV et dans les conditions. Sans accent ni espace.'
				)
			);

			panel.appendChild( el( 'div', { class: 'roga-field' }, [
				checkbox( 'Réponse obligatoire', f.required, function ( v ) { f.required = v; } ),
			] ) );
		}

		if ( isChoice( f.type ) ) {
			panel.appendChild(
				field(
					'Choix proposés',
					textarea( ( f.options || [] ).join( '\n' ), function ( v ) {
						f.options = v.split( '\n' ).map( function ( s ) { return s.trim(); } ).filter( Boolean );
					}, 6 ),
					'Un choix par ligne.'
				)
			);
			panel.appendChild( el( 'div', { class: 'roga-field' }, [
				checkbox( 'Proposer « Autre » avec champ libre', f.allow_other, function ( v ) { f.allow_other = v; } ),
			] ) );
		}

		if ( -1 !== [ 'text', 'textarea', 'email', 'tel', 'number' ].indexOf( f.type ) ) {
			panel.appendChild( field( 'Texte d\'exemple dans le champ', input( f.placeholder, function ( v ) { f.placeholder = v; } ) ) );
		}

		panel.appendChild( renderLogic( f ) );

		panel.appendChild(
			el( 'div', { class: 'roga-panel-actions' }, [
				el( 'button', {
					type: 'button',
					class: 'button',
					text: 'Dupliquer',
					onclick: function () {
						var copy = JSON.parse( JSON.stringify( f ) );
						copy.id  = uniqueId( f.id );
						state.fields.splice( selected + 1, 0, copy );
						selected = selected + 1;
						render();
					},
				} ),
				el( 'button', {
					type: 'button',
					class: 'button roga-danger',
					text: 'Supprimer',
					onclick: function () {
						if ( ! window.confirm( T.confirmDelete ) ) {
							return;
						}
						state.fields.splice( selected, 1 );
						selected = Math.max( 0, selected - 1 );
						render();
					},
				} ),
			] )
		);

		return panel;
	}

	function renderLogic( f ) {
		var box    = el( 'div', { class: 'roga-logic' } );
		var active = !! ( f.logic && f.logic.rules && f.logic.rules.length );

		box.appendChild( el( 'h3', { text: 'Affichage' } ) );
		box.appendChild(
			checkbox( T.conditional || '', active, function ( v ) {
				if ( v ) {
					var previous = state.fields[ selected - 1 ];
					f.logic = {
						mode: 'all',
						rules: [ { field: previous ? previous.id : '', op: 'is', value: '' } ],
					};
				} else {
					f.logic = null;
				}
				render();
			} )
		);

		if ( ! active ) {
			box.appendChild( el( 'p', { class: 'roga-hint', text: T.always || '' } ) );
			return box;
		}

		box.appendChild(
			field(
				'La question s\'affiche si',
				select(
					f.logic.mode,
					[
						{ value: 'all', label: 'toutes les conditions sont vraies' },
						{ value: 'any', label: 'au moins une condition est vraie' },
					],
					function ( v ) { f.logic.mode = v; }
				)
			)
		);

		// Only earlier questions can be used as a condition source.
		var sources = state.fields.slice( 0, selected ).filter( function ( x ) {
			return 'statement' !== x.type;
		} );

		f.logic.rules.forEach( function ( rule, ri ) {
			var row = el( 'div', { class: 'roga-rule' } );

			row.appendChild(
				select(
					rule.field,
					sources.map( function ( s ) {
						return { value: s.id, label: ( s.label || s.id ).slice( 0, 45 ) };
					} ),
					function ( v ) {
						rule.field = v;
						render();
					}
				)
			);

			row.appendChild(
				select(
					rule.op,
					[
						{ value: 'is', label: 'est' },
						{ value: 'is_not', label: 'n\'est pas' },
						{ value: 'contains', label: 'contient' },
						{ value: 'not_contains', label: 'ne contient pas' },
						{ value: 'gt', label: 'est supérieur à' },
						{ value: 'lt', label: 'est inférieur à' },
						{ value: 'not_empty', label: 'est renseigné' },
						{ value: 'empty', label: 'est vide' },
					],
					function ( v ) {
						rule.op = v;
						render();
					}
				)
			);

			if ( -1 === [ 'empty', 'not_empty' ].indexOf( rule.op ) ) {
				var source = sources.filter( function ( s ) { return s.id === rule.field; } )[ 0 ];
				if ( source && isChoice( source.type ) && ( source.options || [] ).length && -1 !== [ 'is', 'is_not' ].indexOf( rule.op ) ) {
					row.appendChild(
						select(
							rule.value,
							[ { value: '', label: '— choisir —' } ].concat(
								source.options.map( function ( o ) { return { value: o, label: o }; } )
							),
							function ( v ) { rule.value = v; }
						)
					);
				} else {
					row.appendChild( input( rule.value, function ( v ) { rule.value = v; } ) );
				}
			}

			row.appendChild(
				el( 'button', {
					type: 'button',
					class: 'button button-small roga-danger',
					text: '×',
					onclick: function () {
						f.logic.rules.splice( ri, 1 );
						if ( ! f.logic.rules.length ) {
							f.logic = null;
						}
						render();
					},
				} )
			);

			box.appendChild( row );
		} );

		box.appendChild(
			el( 'button', {
				type: 'button',
				class: 'button button-small',
				text: '+ Ajouter une condition',
				onclick: function () {
					f.logic.rules.push( { field: sources.length ? sources[ 0 ].id : '', op: 'is', value: '' } );
					render();
				},
			} )
		);

		if ( ! sources.length ) {
			box.appendChild( el( 'p', { class: 'roga-hint', text: 'Placez cette question après celle dont elle dépend pour pouvoir la conditionner.' } ) );
		}

		return box;
	}

	/* ---------- other tabs ---------- */

	function renderLogoPicker( w ) {
		var box = el( 'div', { class: 'roga-field roga-logo' } );

		box.appendChild( el( 'label', { class: 'roga-label', text: 'Logo affiché au-dessus du titre' } ) );

		var preview = el( 'div', { class: 'roga-logo-preview' } );

		function refresh() {
			preview.innerHTML = '';
			if ( w.logo_url ) {
				var img = el( 'img', { src: w.logo_url, alt: w.logo_alt || '' } );
				img.style.maxHeight = ( w.logo_height || 80 ) + 'px';
				preview.appendChild( img );
			} else {
				preview.appendChild( el( 'span', { class: 'roga-logo-empty', text: 'Aucun logo pour le moment.' } ) );
			}
		}

		var actions = el( 'div', { class: 'roga-logo-actions' } );

		actions.appendChild(
			el( 'button', {
				type: 'button',
				class: 'button',
				text: w.logo_url ? 'Changer le logo' : 'Choisir un logo dans la médiathèque',
				onclick: function () {
					if ( ! window.wp || ! window.wp.media ) {
						window.alert( 'La médiathèque WordPress est indisponible. Rechargez la page et réessayez.' );
						return;
					}
					var frame = window.wp.media( {
						title: 'Choisir un logo',
						button: { text: 'Utiliser ce logo' },
						library: { type: 'image' },
						multiple: false,
					} );
					frame.on( 'select', function () {
						var attachment = frame.state().get( 'selection' ).first().toJSON();
						w.logo_url = attachment.url || '';
						if ( ! w.logo_alt && attachment.alt ) {
							w.logo_alt = attachment.alt;
						}
						render();
					} );
					frame.open();
				},
			} )
		);

		if ( w.logo_url ) {
			actions.appendChild(
				el( 'button', {
					type: 'button',
					class: 'button roga-danger',
					text: 'Retirer',
					onclick: function () {
						w.logo_url = '';
						w.logo_alt = '';
						render();
					},
				} )
			);
		}

		box.appendChild( preview );
		box.appendChild( actions );

		if ( w.logo_url ) {
			box.appendChild(
				field(
					'Texte alternatif (lu par les lecteurs d\'écran)',
					input( w.logo_alt || '', function ( v ) { w.logo_alt = v; } )
				)
			);

			var heightRow = el( 'div', { class: 'roga-logo-height' } );
			heightRow.appendChild( el( 'span', { class: 'roga-label', text: 'Hauteur affichée' } ) );

			var slider = el( 'input', { type: 'range', min: 24, max: 240, step: 4, value: w.logo_height || 80 } );
			var valLbl = el( 'span', { class: 'roga-logo-value', text: ( w.logo_height || 80 ) + ' px' } );

			slider.addEventListener( 'input', function ( e ) {
				w.logo_height = parseInt( e.target.value, 10 ) || 80;
				valLbl.textContent = w.logo_height + ' px';
				var img = preview.querySelector( 'img' );
				if ( img ) {
					img.style.maxHeight = w.logo_height + 'px';
				}
			} );

			heightRow.appendChild( slider );
			heightRow.appendChild( valLbl );
			box.appendChild( heightRow );
		}

		refresh();

		return box;
	}

	function renderScreens() {
		var w = state.welcome;
		var t = state.thankyou;
		var box = el( 'div', { class: 'roga-panel roga-panel-wide' } );

		box.appendChild( el( 'h3', { text: 'Écran d\'accueil' } ) );
		box.appendChild( el( 'div', { class: 'roga-field' }, [
			checkbox( 'Afficher un écran d\'accueil avant la première question', w.enabled, function ( v ) { w.enabled = v; } ),
		] ) );
		box.appendChild( field( 'Titre', input( w.title, function ( v ) { w.title = v; } ) ) );
		box.appendChild( field( 'Texte', textarea( w.description, function ( v ) { w.description = v; }, 4 ) ) );
		box.appendChild( field( 'Libellé du bouton', input( w.button, function ( v ) { w.button = v; } ) ) );
		box.appendChild( renderLogoPicker( w ) );

		box.appendChild( el( 'h3', { text: 'Écran de remerciement' } ) );
		box.appendChild( field( 'Titre', input( t.title, function ( v ) { t.title = v; } ) ) );
		box.appendChild( field( 'Texte', textarea( t.description, function ( v ) { t.description = v; }, 3 ) ) );

		box.appendChild( el( 'h3', { text: 'Bouton d\'envoi' } ) );
		box.appendChild( field( 'Libellé', input( state.settings.submit_label, function ( v ) { state.settings.submit_label = v; } ) ) );

		box.appendChild( field(
			'Mention RGPD',
			textarea( state.settings.rgpd_notice, function ( v ) { state.settings.rgpd_notice = v; }, 3 ),
			'Affichée en petit sous le bouton d\'envoi. Laissez vide pour ne rien afficher.'
		) );

		return box;
	}

	function renderNotifications() {
		var s   = state.settings;
		var box = el( 'div', { class: 'roga-panel roga-panel-wide' } );

		box.appendChild( el( 'h3', { text: 'Notification interne' } ) );
		box.appendChild( el( 'div', { class: 'roga-field' }, [
			checkbox( 'Envoyer un e-mail à chaque nouvelle demande', s.notify_enabled, function ( v ) { s.notify_enabled = v; } ),
		] ) );
		box.appendChild( field(
			'Destinataire(s)',
			input( s.notify_to, function ( v ) { s.notify_to = v; } ),
			'Séparez plusieurs adresses par une virgule.'
		) );
		box.appendChild( field( 'Objet', input( s.notify_subject, function ( v ) { s.notify_subject = v; } ) ) );

		box.appendChild( el( 'h3', { text: 'Expéditeur' } ) );
		box.appendChild( field( 'Nom affiché', input( s.from_name, function ( v ) { s.from_name = v; } ) ) );
		box.appendChild( field(
			'Adresse d\'expédition',
			input( s.from_email, function ( v ) { s.from_email = v; }, { type: 'email' } ),
			'Laissez vide pour utiliser l\'adresse par défaut de WordPress. Pour une bonne délivrabilité, utilisez une adresse du domaine du site.'
		) );

		box.appendChild( el( 'h3', { text: 'Accusé de réception au visiteur' } ) );
		box.appendChild( el( 'div', { class: 'roga-field' }, [
			checkbox( 'Envoyer une confirmation à la personne qui remplit le formulaire', s.ack_enabled, function ( v ) { s.ack_enabled = v; } ),
		] ) );

		var emailFields = state.fields.filter( function ( f ) { return 'email' === f.type; } );
		box.appendChild( field(
			'Champ contenant son adresse',
			select(
				s.ack_field,
				[ { value: '', label: '— premier champ e-mail du formulaire —' } ].concat(
					emailFields.map( function ( f ) { return { value: f.id, label: f.label || f.id }; } )
				),
				function ( v ) { s.ack_field = v; }
			)
		) );
		box.appendChild( field( 'Objet', input( s.ack_subject, function ( v ) { s.ack_subject = v; } ) ) );
		box.appendChild( field( 'Texte d\'introduction', textarea( s.ack_intro, function ( v ) { s.ack_intro = v; }, 4 ) ) );
		box.appendChild( field( 'Formule de fin', textarea( s.ack_outro, function ( v ) { s.ack_outro = v; }, 2 ) ) );

		box.appendChild( el( 'h3', { text: 'Conservation des demandes' } ) );
		box.appendChild( el( 'div', { class: 'roga-field' }, [
			checkbox( 'Enregistrer les demandes dans le site (consultables et exportables)', s.store_entries, function ( v ) { s.store_entries = v; } ),
		] ) );
		box.appendChild( el( 'div', { class: 'roga-field' }, [
			checkbox( 'Enregistrer aussi l\'adresse IP', s.store_ip, function ( v ) { s.store_ip = v; } ),
		] ) );

		return box;
	}

	function renderDesign() {
		var c   = state.settings.colors;
		var box = el( 'div', { class: 'roga-panel roga-panel-wide' } );
		var map = [
			[ 'bg', 'Fond' ],
			[ 'text', 'Texte' ],
			[ 'accent', 'Couleur principale (boutons, sélection)' ],
			[ 'onaccent', 'Texte sur la couleur principale' ],
			[ 'muted', 'Texte secondaire' ],
		];

		box.appendChild( el( 'h3', { text: 'Couleurs' } ) );

		map.forEach( function ( pair ) {
			var key  = pair[ 0 ];
			var row  = el( 'div', { class: 'roga-color-row' } );
			var swat = el( 'input', { type: 'color', value: c[ key ] || '#000000' } );
			var text = el( 'input', { type: 'text', value: c[ key ] || '', class: 'roga-color-hex' } );

			swat.addEventListener( 'input', function ( e ) {
				c[ key ]   = e.target.value;
				text.value = e.target.value;
				preview();
			} );
			text.addEventListener( 'input', function ( e ) {
				c[ key ] = e.target.value;
				if ( /^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test( e.target.value ) ) {
					swat.value = e.target.value;
				}
				preview();
			} );

			row.appendChild( el( 'span', { class: 'roga-color-label', text: pair[ 1 ] } ) );
			row.appendChild( swat );
			row.appendChild( text );
			box.appendChild( row );
		} );

		box.appendChild( el( 'h3', { text: 'Aperçu' } ) );
		var pv = el( 'div', { class: 'roga-preview', id: 'roga-preview' } );
		pv.appendChild( el( 'p', { class: 'roga-preview-q', text: state.fields.length ? ( state.fields[ 0 ].label || 'Votre question' ) : 'Votre question' } ) );
		pv.appendChild( el( 'span', { class: 'roga-preview-opt', text: 'Un choix proposé' } ) );
		pv.appendChild( el( 'span', { class: 'roga-preview-btn', text: state.settings.submit_label || 'Envoyer' } ) );
		box.appendChild( pv );

		return box;
	}

	function preview() {
		var pv = document.getElementById( 'roga-preview' );
		if ( ! pv ) {
			return;
		}
		var c = state.settings.colors;
		pv.style.setProperty( '--roga-bg', c.bg );
		pv.style.setProperty( '--roga-text', c.text );
		pv.style.setProperty( '--roga-accent', c.accent );
		pv.style.setProperty( '--roga-onaccent', c.onaccent );
		pv.style.setProperty( '--roga-muted', c.muted );
	}

	/* ---------- shell ---------- */

	function refreshList() {
		var items = root.querySelectorAll( '.roga-item-label' );
		if ( items[ selected ] ) {
			items[ selected ].textContent = state.fields[ selected ].label || '';
		}
	}

	function render() {
		root.innerHTML = '';

		if ( 'questions' === tab ) {
			root.appendChild( renderQuestions() );
		} else if ( 'screens' === tab ) {
			root.appendChild( renderScreens() );
		} else if ( 'notifications' === tab ) {
			root.appendChild( renderNotifications() );
		} else {
			root.appendChild( renderDesign() );
			preview();
		}
	}

	Array.prototype.forEach.call( document.querySelectorAll( '.roga-tab' ), function ( button ) {
		button.addEventListener( 'click', function () {
			Array.prototype.forEach.call( document.querySelectorAll( '.roga-tab' ), function ( b ) {
				b.classList.remove( 'is-active' );
			} );
			button.classList.add( 'is-active' );
			tab = button.getAttribute( 'data-tab' );
			render();
		} );
	} );

	document.getElementById( 'roga-editor-form' ).addEventListener( 'submit', function () {
		document.getElementById( 'roga-config-input' ).value = JSON.stringify( state );
	} );

	render();
} )();
