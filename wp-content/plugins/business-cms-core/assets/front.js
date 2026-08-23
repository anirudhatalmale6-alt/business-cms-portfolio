/* global BCMS_FRONT */
/**
 * Front-end behaviour: portfolio filtering and the contact form.
 *
 * Both are progressive. The filter chips are real links to industry archives
 * and the form is a real POST target, so if this file fails to load the site
 * still works — it just does a full page load instead of a swap.
 */
( function () {
	'use strict';

	var cfg = window.BCMS_FRONT || {};
	var i18n = cfg.i18n || {};

	/* ------------------------------------------------------------------
	 * Portfolio filter
	 * --------------------------------------------------------------- */

	function initGrid( block ) {
		var items = block.querySelector( '[data-bcms-grid-items]' );
		var chips = block.querySelectorAll( '[data-bcms-filter]' );

		if ( ! items || ! chips.length ) {
			return;
		}

		var pending = null;

		function select( chip ) {
			Array.prototype.forEach.call( chips, function ( c ) {
				c.classList.toggle( 'is-active', c === chip );
				c.setAttribute( 'aria-current', c === chip ? 'true' : 'false' );
			} );
		}

		Array.prototype.forEach.call( chips, function ( chip ) {
			chip.addEventListener( 'click', function ( event ) {
				// Let modified clicks open a new tab as the user asked.
				if ( event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0 ) {
					return;
				}
				event.preventDefault();

				var industry = chip.getAttribute( 'data-bcms-filter' ) || '';

				select( chip );
				items.classList.add( 'is-loading' );
				items.setAttribute( 'aria-busy', 'true' );

				// A fast clicker can outrun the network; abandoning the older
				// request stops an earlier response overwriting a later one.
				if ( pending && pending.abort ) {
					pending.abort();
				}

				var controller = window.AbortController ? new AbortController() : null;
				pending = controller;

				var url =
					cfg.root +
					'grid?industry=' +
					encodeURIComponent( industry ) +
					'&count=' +
					encodeURIComponent( block.getAttribute( 'data-bcms-count' ) || '6' ) +
					'&summary=' +
					( block.getAttribute( 'data-bcms-summary' ) === '0' ? 'false' : 'true' );

				window
					.fetch( url, {
						credentials: 'same-origin',
						signal: controller ? controller.signal : undefined,
					} )
					.then( function ( response ) {
						if ( ! response.ok ) {
							throw new Error( 'HTTP ' + response.status );
						}
						return response.json();
					} )
					.then( function ( payload ) {
						items.innerHTML = payload.html || '';
						items.classList.remove( 'is-loading' );
						items.removeAttribute( 'aria-busy' );
						if ( window.history && window.history.pushState ) {
							window.history.pushState( {}, '', chip.getAttribute( 'href' ) );
						}
					} )
					.catch( function ( error ) {
						if ( error && error.name === 'AbortError' ) {
							return;
						}
						// Fall back to the plain link rather than leaving the
						// visitor staring at a spinner.
						window.location.href = chip.getAttribute( 'href' );
					} );
			} );
		} );
	}

	/* ------------------------------------------------------------------
	 * Contact form
	 * --------------------------------------------------------------- */

	function fieldError( form, name, message ) {
		var slot = form.querySelector( '[data-error-for="' + name + '"]' );
		var input = form.querySelector( '[name="' + name + '"]' );
		if ( slot ) {
			slot.textContent = message || '';
		}
		if ( input ) {
			if ( message ) {
				input.setAttribute( 'aria-invalid', 'true' );
			} else {
				input.removeAttribute( 'aria-invalid' );
			}
		}
	}

	function clearErrors( form ) {
		Array.prototype.forEach.call( form.querySelectorAll( '[data-error-for]' ), function ( slot ) {
			fieldError( form, slot.getAttribute( 'data-error-for' ), '' );
		} );
	}

	function validate( form ) {
		var ok = true;
		clearErrors( form );

		[ 'name', 'message' ].forEach( function ( name ) {
			var input = form.querySelector( '[name="' + name + '"]' );
			if ( input && ! input.value.trim() ) {
				fieldError( form, name, i18n.required );
				ok = false;
			}
		} );

		var email = form.querySelector( '[name="email"]' );
		if ( email && ! /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test( email.value.trim() ) ) {
			fieldError( form, 'email', i18n.email );
			ok = false;
		}

		return ok;
	}

	function initForm( form ) {
		var status = form.querySelector( '.bcms-form-status' );
		var button = form.querySelector( 'button[type="submit"]' );
		var label = button ? button.textContent : '';

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();

			if ( ! validate( form ) ) {
				var firstBad = form.querySelector( '[aria-invalid="true"]' );
				if ( firstBad ) {
					firstBad.focus();
				}
				return;
			}

			var payload = {};
			Array.prototype.forEach.call( form.elements, function ( element ) {
				if ( element.name && element.type !== 'submit' ) {
					payload[ element.name ] = element.value;
				}
			} );

			if ( button ) {
				button.disabled = true;
				button.textContent = i18n.sending || 'Sending…';
			}
			if ( status ) {
				status.textContent = '';
				status.className = 'bcms-form-status';
			}

			window
				.fetch( cfg.root + 'enquiry', {
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': cfg.nonce || '',
					},
					body: JSON.stringify( payload ),
				} )
				.then( function ( response ) {
					return response.json().then( function ( body ) {
						return { ok: response.ok, body: body };
					} );
				} )
				.then( function ( result ) {
					if ( result.ok ) {
						form.innerHTML =
							'<p class="bcms-form-success" role="status">' +
							( form.getAttribute( 'data-success' ) || 'Thank you.' ) +
							'</p>';
						document.dispatchEvent( new CustomEvent( 'bcms:enquiry-sent' ) );
						if ( window.gtag ) {
							window.gtag( 'event', 'generate_lead', { method: 'contact_form' } );
						}
						if ( window._paq ) {
							window._paq.push( [ 'trackGoal', 1 ] );
						}
						return;
					}

					var fields =
						result.body && result.body.data && result.body.data.fields
							? result.body.data.fields
							: null;

					if ( fields ) {
						Object.keys( fields ).forEach( function ( name ) {
							fieldError( form, name, fields[ name ] );
						} );
					}

					if ( status ) {
						status.className = 'bcms-form-status is-error';
						status.textContent =
							( result.body && result.body.message ) || i18n.error;
					}
				} )
				.catch( function () {
					if ( status ) {
						status.className = 'bcms-form-status is-error';
						status.textContent = i18n.error;
					}
				} )
				.then( function () {
					if ( button ) {
						button.disabled = false;
						button.textContent = label;
					}
				} );
		} );
	}

	function boot() {
		Array.prototype.forEach.call( document.querySelectorAll( '[data-bcms-grid]' ), initGrid );
		Array.prototype.forEach.call( document.querySelectorAll( '[data-bcms-form]' ), initForm );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
