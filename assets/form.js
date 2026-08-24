/**
 * Roga by Gazte Co. — conversational renderer.
 * One question per screen, keyboard driven, no dependencies.
 */
( function () {
	'use strict';

	var CFG  = window.ROGA_DATA || {};
	var T    = CFG.i18n || {};
	var ABC  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

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

	/* ---------- conditional logic (mirrors ROGA_Logic in PHP) ---------- */

	function testRule( rule, answers ) {
		var actual = answers[ rule.field ];
		var list   = Array.isArray( actual ) ? actual.map( String ) : [ String( actual === undefined ? '' : actual ) ];
		var flat   = list.join( ' ' );
		var value  = String( rule.value === undefined ? '' : rule.value );

		switch ( rule.op ) {
			case 'is':
				return -1 !== list.indexOf( value );
			case 'is_not':
				return -1 === list.indexOf( value );
			case 'contains':
				return '' !== value && -1 !== flat.toLowerCase().indexOf( value.toLowerCase() );
			case 'not_contains':
				return '' === value || -1 === flat.toLowerCase().indexOf( value.toLowerCase() );
			case 'gt':
				return '' !== flat && ! isNaN( parseFloat( flat ) ) && parseFloat( flat ) > parseFloat( value );
			case 'lt':
				return '' !== flat && ! isNaN( parseFloat( flat ) ) && parseFloat( flat ) < parseFloat( value );
			case 'empty':
				return '' === flat.trim();
			case 'not_empty':
				return '' !== flat.trim();
		}

		return true;
	}

	function isVisible( field, answers ) {
		if ( ! field.logic || ! field.logic.rules || ! field.logic.rules.length ) {
			return true;
		}
		var results = field.logic.rules.map( function ( r ) { return testRule( r, answers ); } );
		return 'any' === field.logic.mode
			? -1 !== results.indexOf( true )
			: -1 === results.indexOf( false );
	}

	// Exposed so the logic can be exercised in isolation; also handy for debugging.
	window.ROGA_LOGIC = { testRule: testRule, isVisible: isVisible };

	/* ---------- one form instance ---------- */

	function Form( root ) {
		var config  = JSON.parse( root.getAttribute( 'data-roga-config' ) || '{}' );
		var fields  = config.fields || [];
		var answers = {};
		var step    = config.welcome && config.welcome.enabled ? -1 : 0;
		var sending = false;
		// Sentinel value used for the review screen. Sits after every real field
		// so navigation logic keeps working without a separate flag.
		var REVIEW  = fields.length;
		// When the visitor edits an answer from the review, the next validation
		// should send them back to the review instead of the following question.
		var returnToReview = false;

		root.innerHTML = '';

		var stage = el( 'div', { class: 'roga-stage' } );
		var bar   = el( 'div', { class: 'roga-progress' }, [ el( 'div', { class: 'roga-progress-fill' } ) ] );

		root.appendChild( stage );
		root.appendChild( bar );

		function visibleFields() {
			return fields.filter( function ( f ) { return isVisible( f, answers ); } );
		}

		/**
		 * Moves forward to the next visible question, or to the review screen,
		 * or submits directly when the review is disabled.
		 */
		function next() {
			if ( returnToReview ) {
				returnToReview = false;
				step = REVIEW;
				draw();
				return;
			}
			var i = step + 1;
			while ( i < fields.length && ! isVisible( fields[ i ], answers ) ) {
				i++;
			}
			if ( i >= fields.length ) {
				if ( false !== config.review ) {
					step = REVIEW;
					draw();
				} else {
					submit();
				}
				return;
			}
			step = i;
			draw();
		}

		function back() {
			if ( step === REVIEW ) {
				// Coming back from the review lands on the last visible question.
				var last = -1;
				for ( var j = 0; j < fields.length; j++ ) {
					if ( isVisible( fields[ j ], answers ) ) {
						last = j;
					}
				}
				step = last < 0 ? ( config.welcome && config.welcome.enabled ? -1 : 0 ) : last;
				draw();
				return;
			}
			// If the visitor opened this question via the review's Edit link,
			// Retour cancels the edit and goes straight back to the review.
			if ( returnToReview ) {
				returnToReview = false;
				step = REVIEW;
				draw();
				return;
			}
			var i = step - 1;
			while ( i >= 0 && ! isVisible( fields[ i ], answers ) ) {
				i--;
			}
			step = i < 0 ? ( config.welcome && config.welcome.enabled ? -1 : 0 ) : i;
			draw();
		}

		function progress() {
			var list = visibleFields();
			var done = list.filter( function ( f, i ) {
				return fields.indexOf( f ) < step;
			} ).length;
			if ( step === REVIEW ) {
				done = list.length;
			}
			var pct = list.length ? Math.round( ( done / list.length ) * 100 ) : 0;
			bar.firstChild.style.width = pct + '%';
			bar.setAttribute( 'aria-valuenow', String( pct ) );
		}

		function value( field ) {
			var v = answers[ field.id ];
			if ( 'checkbox' === field.type ) {
				return Array.isArray( v ) ? v : [];
			}
			return v === undefined ? '' : v;
		}

		function isEmpty( field ) {
			var v = value( field );
			return Array.isArray( v ) ? ! v.length : '' === String( v ).trim();
		}

		function validate( field ) {
			if ( 'statement' === field.type ) {
				return '';
			}
			if ( field.required && isEmpty( field ) ) {
				return T.required;
			}
			if ( 'email' === field.type && ! isEmpty( field ) && ! /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test( value( field ) ) ) {
				return T.email;
			}
			return '';
		}

		/* ----- screens ----- */

		function drawWelcome() {
			var w    = config.welcome;
			var logo = null;

			if ( w.logo_url ) {
				logo = el( 'img', {
					class: 'roga-logo',
					src: w.logo_url,
					alt: w.logo_alt || '',
				} );
				logo.style.maxHeight = ( w.logo_height || 80 ) + 'px';
			}

			var card = el( 'div', { class: 'roga-card roga-welcome' }, [
				logo,
				el( 'h2', { class: 'roga-title', text: w.title } ),
				w.description ? el( 'p', { class: 'roga-desc', text: w.description } ) : null,
				el( 'button', {
					type: 'button',
					class: 'roga-btn',
					text: w.button || T.next,
					onclick: function () { step = -1; next(); },
				} ),
			] );
			stage.appendChild( card );
			card.querySelector( 'button' ).focus();
		}

		function drawThankyou( ty ) {
			stage.innerHTML = '';
			bar.style.display = 'none';
			stage.appendChild(
				el( 'div', { class: 'roga-card roga-thankyou' }, [
					el( 'div', { class: 'roga-check-mark', text: '✓' } ),
					el( 'h2', { class: 'roga-title', text: ty.title } ),
					ty.description ? el( 'p', { class: 'roga-desc', text: ty.description } ) : null,
				] )
			);
			root.scrollIntoView( { behavior: 'smooth', block: 'center' } );
		}

		/**
		 * Formats one answer for display on the review screen.
		 * Kept plain-text: linebreaks preserved via CSS, no HTML injected.
		 */
		function formatAnswer( field ) {
			var v = answers[ field.id ];
			if ( 'checkbox' === field.type ) {
				var arr = Array.isArray( v ) ? v : [];
				return arr.length ? arr.join( ', ' ) : '';
			}
			return v === undefined || v === null ? '' : String( v );
		}

		function drawReview() {
			var card = el( 'div', { class: 'roga-card roga-review' } );

			card.appendChild( el( 'h2', { class: 'roga-title', text: T.reviewTitle || 'Vérifiez vos réponses' } ) );
			card.appendChild( el( 'p', { class: 'roga-desc', text: T.reviewDesc || '' } ) );

			var list = el( 'dl', { class: 'roga-review-list' } );
			var visible = visibleFields();

			visible.forEach( function ( field ) {
				var row  = el( 'div', { class: 'roga-review-row' } );
				var text = formatAnswer( field );

				row.appendChild( el( 'dt', { class: 'roga-review-label', text: field.label || '' } ) );

				var body = el( 'dd', { class: 'roga-review-value' } );
				body.appendChild( el( 'span', {
					class: 'roga-review-answer' + ( text ? '' : ' is-empty' ),
					text: text || ( T.empty || '(non renseigné)' ),
				} ) );
				body.appendChild( el( 'button', {
					type: 'button',
					class: 'roga-review-edit',
					text: T.edit || 'Modifier',
					onclick: ( function ( f ) {
						return function () {
							returnToReview = true;
							step = fields.indexOf( f );
							draw();
						};
					} )( field ),
				} ) );

				row.appendChild( body );
				list.appendChild( row );
			} );

			card.appendChild( list );

			var nav = el( 'div', { class: 'roga-nav' } );
			nav.appendChild( el( 'button', {
				type: 'button',
				class: 'roga-btn',
				text: config.submit || T.ok || 'Envoyer',
				onclick: submit,
			} ) );
			nav.appendChild( el( 'button', {
				type: 'button',
				class: 'roga-btn roga-btn-ghost',
				text: '↑ ' + ( T.back || 'Retour' ),
				onclick: back,
			} ) );
			card.appendChild( nav );

			if ( config.rgpd ) {
				card.appendChild( el( 'p', { class: 'roga-rgpd', text: config.rgpd } ) );
			}

			stage.appendChild( card );
		}

		function drawQuestion() {
			var field = fields[ step ];
			var card  = el( 'div', { class: 'roga-card' } );
			var index = visibleFields().indexOf( field ) + 1;

			card.appendChild(
				el( 'div', { class: 'roga-q' }, [
					el( 'span', { class: 'roga-q-num', text: index + ' →' } ),
					el( 'h2', { class: 'roga-q-label' }, [
						document.createTextNode( field.label || '' ),
						field.required ? el( 'span', { class: 'roga-req', text: ' *', 'aria-hidden': 'true' } ) : null,
					] ),
				] )
			);

			if ( field.description ) {
				card.appendChild( el( 'p', { class: 'roga-desc', text: field.description } ) );
			}

			var control = buildControl( field, card );
			card.appendChild( control );

			var error = el( 'p', { class: 'roga-error', role: 'alert' } );
			card.appendChild( error );

			var isLast = ! hasNextVisible();
			// When the review screen is disabled, the last question doubles as
			// the send action, so we surface the submit label directly.
			var lastLabel = isLast && false === config.review
				? ( config.submit || T.ok )
				: T.ok;
			var nav    = el( 'div', { class: 'roga-nav' } );

			nav.appendChild(
				el( 'button', {
					type: 'button',
					class: 'roga-btn',
					text: lastLabel,
					onclick: function () {
						var message = validate( field );
						if ( message ) {
							error.textContent = message;
							return;
						}
						error.textContent = '';
						next();
					},
				} )
			);

			if ( ! isLast ) {
				nav.appendChild( el( 'span', { class: 'roga-kbd-hint', text: T.press + ' ' + T.enter + ' ↵' } ) );
			}

			if ( step > 0 || ( config.welcome && config.welcome.enabled ) ) {
				nav.appendChild(
					el( 'button', {
						type: 'button',
						class: 'roga-btn roga-btn-ghost',
						text: '↑ ' + T.back,
						onclick: back,
					} )
				);
			}

			card.appendChild( nav );

			if ( isLast && config.rgpd ) {
				card.appendChild( el( 'p', { class: 'roga-rgpd', text: config.rgpd } ) );
			}

			stage.appendChild( card );

			var focusable = card.querySelector( 'input:not([type=hidden]), textarea, select, .roga-opt' );
			if ( focusable ) {
				focusable.focus( { preventScroll: true } );
			}
		}

		function hasNextVisible() {
			for ( var i = step + 1; i < fields.length; i++ ) {
				if ( isVisible( fields[ i ], answers ) ) {
					return true;
				}
			}
			return false;
		}

		/* ----- controls ----- */

		function buildControl( field, card ) {
			var wrap = el( 'div', { class: 'roga-control' } );

			if ( 'statement' === field.type ) {
				return wrap;
			}

			if ( 'radio' === field.type || 'checkbox' === field.type ) {
				var multi = 'checkbox' === field.type;

				if ( multi ) {
					wrap.appendChild( el( 'p', { class: 'roga-hint', text: T.multiHint } ) );
				}

				( field.options || [] ).forEach( function ( option, i ) {
					var current  = value( field );
					var selected = multi ? -1 !== current.indexOf( option ) : current === option;
					var button   = el( 'button', {
						type: 'button',
						class: 'roga-opt' + ( selected ? ' is-selected' : '' ),
						'data-key': ABC[ i ] || '',
						onclick: function () { choose( field, option, multi, card ); },
					}, [
						el( 'span', { class: 'roga-opt-key', text: ABC[ i ] || '•' } ),
						el( 'span', { class: 'roga-opt-label', text: option } ),
					] );
					wrap.appendChild( button );
				} );

				if ( field.allow_other ) {
					var otherValue = '';
					var current2   = value( field );
					var known      = field.options || [];
					( Array.isArray( current2 ) ? current2 : [ current2 ] ).forEach( function ( v ) {
						if ( v && -1 === known.indexOf( v ) ) {
							otherValue = v;
						}
					} );

					var otherInput = el( 'input', {
						type: 'text',
						class: 'roga-input roga-other',
						placeholder: T.otherPh,
						value: otherValue,
					} );
					otherInput.addEventListener( 'input', function ( e ) {
						setOther( field, e.target.value, multi );
					} );
					wrap.appendChild( el( 'div', { class: 'roga-other-wrap' }, [
						el( 'span', { class: 'roga-opt-key', text: '✎' } ),
						otherInput,
					] ) );
				}

				return wrap;
			}

			if ( 'select' === field.type ) {
				var sel = el( 'select', { class: 'roga-input' } );
				sel.appendChild( el( 'option', { value: '', text: '—' } ) );
				( field.options || [] ).forEach( function ( option ) {
					var o = el( 'option', { value: option, text: option } );
					if ( value( field ) === option ) {
						o.selected = true;
					}
					sel.appendChild( o );
				} );
				sel.addEventListener( 'change', function ( e ) { answers[ field.id ] = e.target.value; } );
				wrap.appendChild( sel );
				return wrap;
			}

			if ( 'legal' === field.type ) {
				var box = el( 'input', { type: 'checkbox' } );
				box.checked = !! value( field );
				box.addEventListener( 'change', function ( e ) { answers[ field.id ] = e.target.checked ? '1' : ''; } );
				wrap.appendChild( el( 'label', { class: 'roga-legal' }, [ box, el( 'span', { text: field.placeholder || field.label } ) ] ) );
				return wrap;
			}

			if ( 'textarea' === field.type ) {
				var area = el( 'textarea', { class: 'roga-input', rows: 3, placeholder: field.placeholder || '' } );
				area.value = value( field );
				area.addEventListener( 'input', function ( e ) { answers[ field.id ] = e.target.value; } );
				wrap.appendChild( area );
				return wrap;
			}

			var types = { email: 'email', tel: 'tel', number: 'number', date: 'date' };
			var input = el( 'input', {
				class: 'roga-input',
				type: types[ field.type ] || 'text',
				placeholder: field.placeholder || '',
				value: value( field ),
				inputmode: 'number' === field.type ? 'numeric' : null,
				autocomplete: 'email' === field.type ? 'email' : ( 'tel' === field.type ? 'tel' : 'off' ),
			} );
			input.addEventListener( 'input', function ( e ) { answers[ field.id ] = e.target.value; } );
			wrap.appendChild( input );

			return wrap;
		}

		function choose( field, option, multi, card ) {
			if ( multi ) {
				var list = value( field ).slice();
				var at   = list.indexOf( option );
				if ( -1 === at ) {
					list.push( option );
				} else {
					list.splice( at, 1 );
				}
				answers[ field.id ] = list;
				draw();
				return;
			}

			answers[ field.id ] = option;
			// Single choice reads best when it advances on its own.
			window.setTimeout( function () {
				if ( ! validate( field ) ) {
					next();
				} else {
					draw();
				}
			}, 180 );
			draw();
		}

		function setOther( field, text, multi ) {
			var known = field.options || [];
			if ( multi ) {
				var list = value( field ).filter( function ( v ) { return -1 !== known.indexOf( v ); } );
				if ( text.trim() ) {
					list.push( text.trim() );
				}
				answers[ field.id ] = list;
			} else {
				answers[ field.id ] = text.trim();
			}
		}

		/* ----- submit ----- */

		function submit() {
			if ( sending ) {
				return;
			}
			sending = true;

			stage.innerHTML = '';
			stage.appendChild( el( 'div', { class: 'roga-card' }, [ el( 'p', { class: 'roga-desc', text: T.sending } ) ] ) );

			var payload = {
				form_id: config.formId,
				answers: answers,
				website: '',
				page: window.location.href,
			};

			window.fetch( CFG.endpoint, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': CFG.nonce,
				},
				body: JSON.stringify( payload ),
			} )
				.then( function ( response ) {
					return response.json().then( function ( body ) {
						return { ok: response.ok, body: body };
					} );
				} )
				.then( function ( result ) {
					if ( ! result.ok ) {
						throw result.body;
					}
					drawThankyou( result.body.thankyou || config.thankyou );
				} )
				.catch( function ( err ) {
					sending = false;
					stage.innerHTML = '';
					var message = ( err && err.message ) ? err.message : T.error;
					stage.appendChild(
						el( 'div', { class: 'roga-card' }, [
							el( 'p', { class: 'roga-error', text: message } ),
							el( 'button', {
								type: 'button',
								class: 'roga-btn',
								text: T.back,
								onclick: function () { back(); },
							} ),
						] )
					);
				} );
		}

		/* ----- shell ----- */

		function draw() {
			stage.innerHTML = '';
			if ( step < 0 ) {
				drawWelcome();
			} else if ( step === REVIEW ) {
				drawReview();
			} else {
				drawQuestion();
			}
			progress();
		}

		root.addEventListener( 'keydown', function ( e ) {
			if ( sending || step < 0 ) {
				if ( 'Enter' === e.key && step < 0 ) {
					e.preventDefault();
					step = -1;
					next();
				}
				return;
			}

			// On the review screen, Enter sends the form.
			if ( step === REVIEW ) {
				if ( 'Enter' === e.key && 'TEXTAREA' !== e.target.tagName ) {
					e.preventDefault();
					submit();
				}
				return;
			}

			var field = fields[ step ];
			if ( ! field ) {
				return;
			}

			if ( 'Enter' === e.key && ! e.shiftKey && 'TEXTAREA' !== e.target.tagName ) {
				e.preventDefault();
				var message = validate( field );
				var error   = root.querySelector( '.roga-error' );
				if ( message ) {
					if ( error ) {
						error.textContent = message;
					}
					return;
				}
				next();
				return;
			}

			// Letter shortcuts, but never while typing in a field.
			if ( ( 'radio' === field.type || 'checkbox' === field.type ) && ! e.metaKey && ! e.ctrlKey && ! e.altKey ) {
				if ( 'INPUT' === e.target.tagName || 'TEXTAREA' === e.target.tagName ) {
					return;
				}
				var i = ABC.indexOf( ( e.key || '' ).toUpperCase() );
				if ( i >= 0 && field.options && field.options[ i ] ) {
					e.preventDefault();
					choose( field, field.options[ i ], 'checkbox' === field.type );
				}
			}
		} );

		draw();
	}

	function boot() {
		Array.prototype.forEach.call( document.querySelectorAll( '.roga-root[data-roga-config]' ), function ( root ) {
			if ( ! root.getAttribute( 'data-roga-ready' ) ) {
				root.setAttribute( 'data-roga-ready', '1' );
				Form( root );
			}
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
