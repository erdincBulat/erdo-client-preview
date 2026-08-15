( function () {
	var ICONS = {
		success: '<svg class="erdo-client-preview-feedback-success-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M20 6 9 17l-5-5"></path></svg>',
		error: '<svg class="erdo-client-preview-feedback-error-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>'
	};

	var HISTORY_STORAGE_KEY = 'erdoClientPreviewFeedbackHistory';
	var HISTORY_MAX_ITEMS   = 20;

	function loadHistory() {
		try {
			var raw  = window.localStorage.getItem( HISTORY_STORAGE_KEY );
			var data = raw ? JSON.parse( raw ) : [];
			return Array.isArray( data ) ? data : [];
		} catch ( e ) {
			return [];
		}
	}

	function saveHistory( items ) {
		try {
			window.localStorage.setItem( HISTORY_STORAGE_KEY, JSON.stringify( items.slice( 0, HISTORY_MAX_ITEMS ) ) );
		} catch ( e ) {
			// localStorage unavailable (e.g. private browsing) — history just won't persist.
		}
	}

	function init() {
		var widget = document.querySelector( '.erdo-client-preview-feedback' );

		if ( ! widget ) {
			return;
		}

		// Some themes/page builders apply a transform/filter/perspective to an
		// ancestor (often the footer), which creates a new containing block and
		// breaks position: fixed relative to the viewport. Re-parent the widget
		// to <body> so it stays pinned regardless of page structure.
		if ( widget.parentNode !== document.body ) {
			document.body.appendChild( widget );
		}

		var toggle = widget.querySelector( '.erdo-client-preview-feedback-toggle' );
		var panel  = widget.querySelector( '.erdo-client-preview-feedback-panel' );
		var close  = widget.querySelector( '.erdo-client-preview-feedback-close' );

		if ( ! toggle || ! panel ) {
			return;
		}

		function openPanel() {
			panel.removeAttribute( 'hidden' );
			// Force layout so the opacity/transform transition runs.
			// eslint-disable-next-line no-unused-expressions
			panel.offsetHeight;
			panel.classList.add( 'is-open' );
			widget.setAttribute( 'data-open', 'true' );
			toggle.setAttribute( 'aria-expanded', 'true' );
		}

		function closePanel() {
			panel.classList.remove( 'is-open' );
			widget.setAttribute( 'data-open', 'false' );
			toggle.setAttribute( 'aria-expanded', 'false' );

			var hidePanel = function () {
				panel.setAttribute( 'hidden', '' );
			};

			panel.addEventListener( 'transitionend', hidePanel, { once: true } );
			// Fallback in case transitionend doesn't fire (e.g. reduced motion).
			setTimeout( hidePanel, 250 );
		}

		toggle.addEventListener( 'click', function () {
			if ( panel.classList.contains( 'is-open' ) ) {
				closePanel();
			} else {
				openPanel();
			}
		} );

		if ( close ) {
			close.addEventListener( 'click', closePanel );
		}

		document.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key && panel.classList.contains( 'is-open' ) ) {
				closePanel();
				toggle.focus();
			}
		} );

		var settings = window.erdoClientPreviewFeedback || {};
		var i18n     = settings.i18n || {};

		// ------------------------------------------------------------------
		// Past feedback — shows this visitor's own previous submissions,
		// remembered locally via localStorage, with live status updates.
		// ------------------------------------------------------------------

		var historyContainer = widget.querySelector( '#erdo-client-preview-feedback-history' );
		var historyList       = widget.querySelector( '#erdo-client-preview-feedback-history-list' );
		var feedbackHistory    = loadHistory();

		function renderHistory() {
			if ( ! historyContainer || ! historyList ) {
				return;
			}

			historyList.innerHTML = '';

			if ( ! feedbackHistory.length ) {
				historyContainer.setAttribute( 'hidden', '' );
				return;
			}

			feedbackHistory.forEach( function ( item ) {
				historyList.appendChild( createHistoryItem( item ) );
			} );

			historyContainer.removeAttribute( 'hidden' );
		}

		function createHistoryItem( item ) {
			var li = document.createElement( 'li' );
			li.className = 'erdo-client-preview-feedback-history-item';

			var avatar = document.createElement( 'span' );
			avatar.className   = 'erdo-client-preview-feedback-history-avatar';
			avatar.textContent = item.initial || '?';
			li.appendChild( avatar );

			var content = document.createElement( 'div' );
			content.className = 'erdo-client-preview-feedback-history-content';

			var message = document.createElement( 'p' );
			message.className   = 'erdo-client-preview-feedback-history-message';
			message.textContent = item.message || '';
			content.appendChild( message );

			var meta = document.createElement( 'div' );
			meta.className = 'erdo-client-preview-feedback-history-meta';

			var date = document.createElement( 'span' );
			date.className   = 'erdo-client-preview-feedback-history-date';
			date.textContent = item.date || '';
			meta.appendChild( date );

			if ( item.status_label ) {
				var status = document.createElement( 'span' );
				status.className   = 'erdo-client-preview-feedback-history-status erdo-client-preview-feedback-history-status--' + ( item.status || '' );
				status.textContent = item.status_label;
				meta.appendChild( status );
			}

			content.appendChild( meta );

			if ( item.reply ) {
				var reply = document.createElement( 'p' );
				reply.className = 'erdo-client-preview-feedback-history-reply';

				var replyLabel = document.createElement( 'strong' );
				replyLabel.className   = 'erdo-client-preview-feedback-history-reply-label';
				replyLabel.textContent = ( i18n.reply || 'Reply:' ) + ' ';
				reply.appendChild( replyLabel );

				reply.appendChild( document.createTextNode( item.reply ) );
				content.appendChild( reply );
			}

			li.appendChild( content );

			return li;
		}

		function syncHistoryStatuses() {
			if ( ! feedbackHistory.length || ! settings.statusUrl || ! window.fetch ) {
				return;
			}

			var items = feedbackHistory.filter( function ( item ) {
				return item.id && item.token;
			} ).map( function ( item ) {
				return item.id + ':' + item.token;
			} ).join( ',' );

			if ( ! items ) {
				return;
			}

			fetch( settings.statusUrl + '?items=' + encodeURIComponent( items ) )
				.then( function ( response ) {
					return response.json();
				} )
				.then( function ( data ) {
					var statusById = {};
					( data.items || [] ).forEach( function ( row ) {
						statusById[ row.id ] = row;
					} );

					var changed = false;
					feedbackHistory.forEach( function ( item ) {
						var fresh = statusById[ item.id ];
						if ( fresh && ( fresh.status !== item.status || fresh.status_label !== item.status_label || fresh.reply !== item.reply ) ) {
							item.status       = fresh.status;
							item.status_label = fresh.status_label;
							item.reply        = fresh.reply;
							changed = true;
						}
					} );

					if ( changed ) {
						saveHistory( feedbackHistory );
						renderHistory();
					}
				} )
				.catch( function () {} );
		}

		renderHistory();
		syncHistoryStatuses();

		// ------------------------------------------------------------------
		// Feedback form — submit via REST so the panel updates instantly
		// and stays open/usable for additional submissions.
		// ------------------------------------------------------------------

		var form        = widget.querySelector( '#erdo-client-preview-feedback-form' );
		var notice      = widget.querySelector( '#erdo-client-preview-feedback-notice' );
		var submitBtn   = form ? form.querySelector( '.erdo-client-preview-feedback-submit' ) : null;
		var submitLabel = submitBtn ? submitBtn.querySelector( '.erdo-client-preview-feedback-submit-label' ) : null;

		function autoDismiss( el, delay ) {
			setTimeout( function () {
				el.style.transition = 'opacity 0.3s ease';
				el.style.opacity    = '0';
				setTimeout( function () {
					el.remove();
				}, 300 );
			}, delay );
		}

		function showNotice( type, message ) {
			if ( ! notice ) {
				return;
			}

			notice.innerHTML = '';

			var box = document.createElement( 'div' );
			box.className = 'erdo-client-preview-feedback-' + type;
			box.setAttribute( 'role', 'status' );
			box.innerHTML = ICONS[ type ] || '';

			var span = document.createElement( 'span' );
			span.textContent = message;
			box.appendChild( span );

			notice.appendChild( box );
			autoDismiss( box, 4000 );
		}

		function setSubmitting( isSubmitting ) {
			if ( ! submitBtn ) {
				return;
			}
			submitBtn.disabled = isSubmitting;

			if ( isSubmitting ) {
				if ( submitLabel ) {
					submitLabel.textContent = i18n.sending || submitLabel.textContent;
				}
				if ( ! submitBtn.querySelector( '.erdo-client-preview-feedback-spinner' ) ) {
					var spinner = document.createElement( 'span' );
					spinner.className = 'erdo-client-preview-feedback-spinner';
					submitBtn.insertBefore( spinner, submitBtn.firstChild );
				}
			} else {
				if ( submitLabel && i18n.submit ) {
					submitLabel.textContent = i18n.submit;
				}
				var existingSpinner = submitBtn.querySelector( '.erdo-client-preview-feedback-spinner' );
				if ( existingSpinner ) {
					existingSpinner.remove();
				}
			}
		}

		if ( form && settings.restUrl && window.fetch ) {
			form.addEventListener( 'submit', function ( event ) {
				event.preventDefault();

				var nameInput    = form.querySelector( '#erdo-client-preview-feedback-name' );
				var messageInput = form.querySelector( '#erdo-client-preview-feedback-message' );
				var nonceInput   = form.querySelector( '[name="erdo_feedback_nonce"]' );

				var name    = nameInput ? nameInput.value.trim() : '';
				var message = messageInput ? messageInput.value.trim() : '';

				if ( ! name || ! message ) {
					return;
				}

				setSubmitting( true );

				fetch( settings.restUrl, {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify( {
						name: name,
						message: message,
						nonce: nonceInput ? nonceInput.value : ''
					} )
				} )
					.then( function ( response ) {
						return response.json().then( function ( data ) {
							return { ok: response.ok, data: data };
						} );
					} )
					.then( function ( result ) {
						if ( ! result.ok || ! result.data || ! result.data.success ) {
							// eslint-disable-next-line no-console
							console.error( 'Erdo Client Preview feedback error:', result.data && result.data.code, result.data && result.data.message );
							throw new Error( ( result.data && result.data.message ) || i18n.error || '' );
						}

						showNotice( 'success', i18n.success || '' );
						form.reset();
						if ( nameInput ) {
							nameInput.focus();
						}

						if ( result.data.item ) {
							feedbackHistory.unshift( result.data.item );
							saveHistory( feedbackHistory );
							renderHistory();
						}
					} )
					.catch( function ( error ) {
						showNotice( 'error', error.message || i18n.error || '' );
					} )
					.then( function () {
						setSubmitting( false );
					} );
			} );
		}

		// After a non-JS submission the page reloads with ?erdo_feedback=sent
		// so the panel auto-opens with a server-rendered success message,
		// while the (now empty) form stays visible for another submission.
		if ( panel.hasAttribute( 'data-auto-open' ) ) {
			openPanel();

			var url = new URL( window.location.href );
			if ( url.searchParams.has( 'erdo_feedback' ) ) {
				url.searchParams.delete( 'erdo_feedback' );
				window.history.replaceState( {}, '', url.toString() );
			}

			var success = notice ? notice.querySelector( '.erdo-client-preview-feedback-success' ) : null;
			if ( success ) {
				autoDismiss( success, 4000 );
			}
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
