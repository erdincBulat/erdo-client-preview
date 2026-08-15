( function () {
	var settings = window.erdoClientPreviewAnnotation;

	if ( ! settings ) {
		return;
	}

	var i18n      = settings.i18n || {};
	var active    = false;
	var highlight = null;
	var popup     = null;
	var pins      = [];
	var toggle    = null;
	var overlay   = null;

	function isOwnElement( el ) {
		if ( ! el || ! el.closest ) {
			return false;
		}

		// Exclude <body>/<html> themselves — setActive() adds a class to
		// <body> to flag annotation mode as active, which would otherwise
		// match the selector below for every element on the page.
		var match = el.closest( '[class*="erdo-client-preview-annotation"], [class*="erdo-client-preview-feedback"]' );

		return !! match && document.body !== match && document.documentElement !== match;
	}

	// ------------------------------------------------------------------
	// CSS selector capture — always builds an :nth-child(n) path from the
	// clicked element up to <body>, so it can be re-located on later loads.
	// ------------------------------------------------------------------

	function getSelector( el ) {
		if ( el === document.body ) {
			return 'body';
		}

		var path = [];
		var node = el;

		while ( node && node.nodeType === 1 && node !== document.body ) {
			var index   = 1;
			var sibling = node.previousElementSibling;

			while ( sibling ) {
				index++;
				sibling = sibling.previousElementSibling;
			}

			path.unshift( node.tagName.toLowerCase() + ':nth-child(' + index + ')' );
			node = node.parentElement;
		}

		path.unshift( 'body' );

		return path.join( ' > ' );
	}

	// ------------------------------------------------------------------
	// Toggle button
	// ------------------------------------------------------------------

	function injectToggle() {
		var wrap = document.createElement( 'div' );
		wrap.className = 'erdo-client-preview-annotation-toggle-wrap';

		toggle = document.createElement( 'button' );
		toggle.type = 'button';
		toggle.className = 'erdo-client-preview-annotation-toggle';
		toggle.textContent = i18n.toggleOn || 'Leave Feedback on Page';
		toggle.addEventListener( 'click', function () {
			setActive( ! active );
		} );

		wrap.appendChild( toggle );
		document.body.appendChild( wrap );
	}

	function setActive( next ) {
		active = next;

		document.body.classList.toggle( 'erdo-client-preview-annotation-active', active );
		toggle.classList.toggle( 'is-active', active );
		toggle.textContent = active ? ( i18n.toggleOff || 'Exit Annotation Mode' ) : ( i18n.toggleOn || 'Leave Feedback on Page' );

		if ( active ) {
			createOverlay();
		} else {
			removeOverlay();
			clearHighlight();
			closePopup();
		}
	}

	function clearHighlight() {
		if ( highlight ) {
			highlight.classList.remove( 'erdo-client-preview-annotation-hover' );
			highlight = null;
		}
	}

	// ------------------------------------------------------------------
	// Click-capture overlay — a transparent, full-viewport layer that sits
	// above the page so picking an element never triggers the page's own
	// click handlers (link navigation, button actions, etc.). The element
	// "under" the cursor is found via elementFromPoint() while the overlay
	// is briefly made click-through.
	// ------------------------------------------------------------------

	function createOverlay() {
		if ( overlay ) {
			return;
		}

		overlay = document.createElement( 'div' );
		overlay.className = 'erdo-client-preview-annotation-overlay';
		overlay.addEventListener( 'mousemove', onOverlayMouseMove );
		overlay.addEventListener( 'click', onOverlayClick );

		document.body.appendChild( overlay );
	}

	function removeOverlay() {
		if ( ! overlay ) {
			return;
		}

		overlay.remove();
		overlay = null;
	}

	function elementUnderPointer( x, y ) {
		// The overlay's stylesheet rule resets it with `all: initial !important`,
		// which also forces `pointer-events: auto !important`. A non-important
		// inline style can't override that, so use setProperty() with the
		// "important" priority to actually make it click-through for the
		// instant we need elementFromPoint() to see past it.
		overlay.style.setProperty( 'pointer-events', 'none', 'important' );
		var el = document.elementFromPoint( x, y );
		overlay.style.setProperty( 'pointer-events', 'auto', 'important' );

		return el;
	}

	function onOverlayMouseMove( event ) {
		var target = elementUnderPointer( event.clientX, event.clientY );

		if ( ! target || isOwnElement( target ) ) {
			clearHighlight();
			return;
		}

		if ( highlight && highlight !== target ) {
			highlight.classList.remove( 'erdo-client-preview-annotation-hover' );
		}

		highlight = target;
		highlight.classList.add( 'erdo-client-preview-annotation-hover' );
	}

	function onOverlayClick( event ) {
		event.preventDefault();
		event.stopPropagation();

		var target = elementUnderPointer( event.clientX, event.clientY );

		if ( ! target || isOwnElement( target ) ) {
			return;
		}

		var rect     = target.getBoundingClientRect();
		var xPercent = rect.width  ? ( ( event.clientX - rect.left ) / rect.width )  * 100 : 0;
		var yPercent = rect.height ? ( ( event.clientY - rect.top )  / rect.height ) * 100 : 0;

		var docEl        = document.documentElement;
		var pageWidth    = docEl.scrollWidth;
		var pageHeight   = docEl.scrollHeight;
		var pageXPercent = pageWidth  ? ( ( event.clientX + window.scrollX ) / pageWidth )  * 100 : 0;
		var pageYPercent = pageHeight ? ( ( event.clientY + window.scrollY ) / pageHeight ) * 100 : 0;

		openForm( target, xPercent, yPercent, pageXPercent, pageYPercent, event.clientX, event.clientY );
	}

	// ------------------------------------------------------------------
	// Popup positioning — popups are `position: fixed` and viewport-relative
	// so they render correctly even if the theme gives <body> its own
	// containing block (e.g. via transform), which would otherwise throw
	// off document-relative coordinates.
	// ------------------------------------------------------------------

	function positionElementAt( el, x, y ) {
		document.body.appendChild( el );

		var margin = 12;
		var rect   = el.getBoundingClientRect();
		var maxX   = Math.max( margin, window.innerWidth  - rect.width  - margin );
		var maxY   = Math.max( margin, window.innerHeight - rect.height - margin );

		// `all: initial !important` on these elements also resets `left`/`top`
		// to `auto !important`; a non-important inline style can't override
		// that, so use setProperty() with the "important" priority.
		el.style.setProperty( 'left', Math.min( Math.max( x, margin ), maxX ) + 'px', 'important' );
		el.style.setProperty( 'top',  Math.min( Math.max( y, margin ), maxY ) + 'px', 'important' );
	}

	// ------------------------------------------------------------------
	// New annotation form
	// ------------------------------------------------------------------

	function openForm( target, xPercent, yPercent, pageXPercent, pageYPercent, clientX, clientY ) {
		closePopup();

		var form = document.createElement( 'form' );
		form.className = 'erdo-client-preview-annotation-form';

		var nameField = document.createElement( 'input' );
		nameField.type = 'text';
		nameField.className = 'erdo-client-preview-annotation-input';
		nameField.placeholder = i18n.name || 'Your name';
		nameField.required = true;

		var messageField = document.createElement( 'textarea' );
		messageField.className = 'erdo-client-preview-annotation-textarea';
		messageField.placeholder = i18n.message || 'Your note';
		messageField.rows = 3;
		messageField.required = true;

		var error = document.createElement( 'p' );
		error.className = 'erdo-client-preview-annotation-error';
		error.setAttribute( 'hidden', '' );

		var actions = document.createElement( 'div' );
		actions.className = 'erdo-client-preview-annotation-form-actions';

		var cancelBtn = document.createElement( 'button' );
		cancelBtn.type = 'button';
		cancelBtn.className = 'erdo-client-preview-annotation-cancel';
		cancelBtn.textContent = i18n.cancel || 'Cancel';
		cancelBtn.addEventListener( 'click', closePopup );

		var submitBtn = document.createElement( 'button' );
		submitBtn.type = 'submit';
		submitBtn.className = 'erdo-client-preview-annotation-submit';
		submitBtn.textContent = i18n.submit || 'Add Note';

		actions.appendChild( cancelBtn );
		actions.appendChild( submitBtn );

		form.appendChild( nameField );
		form.appendChild( messageField );
		form.appendChild( error );
		form.appendChild( actions );

		form.addEventListener( 'click', function ( event ) {
			event.stopPropagation();
		} );

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();

			var name    = nameField.value.trim();
			var message = messageField.value.trim();

			if ( ! name || ! message || ! settings.restUrl || ! window.fetch ) {
				return;
			}

			submitBtn.disabled = true;
			submitBtn.textContent = i18n.sending || 'Sending…';

			fetch( settings.restUrl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify( {
					page_url: settings.pageUrl,
					selector: getSelector( target ),
					x_percent: xPercent,
					y_percent: yPercent,
					page_x_percent: pageXPercent,
					page_y_percent: pageYPercent,
					message: message,
					name: name,
					nonce: settings.nonce
				} )
			} )
				.then( function ( response ) {
					return response.json().then( function ( data ) {
						return { ok: response.ok, data: data };
					} );
				} )
				.then( function ( result ) {
					if ( ! result.ok || ! result.data || ! result.data.success ) {
						throw new Error( ( result.data && result.data.message ) || i18n.error || '' );
					}

					closePopup();
					renderPin( result.data.item );
				} )
				.catch( function ( err ) {
					error.textContent = err.message || i18n.error || '';
					error.removeAttribute( 'hidden' );
					submitBtn.disabled = false;
					submitBtn.textContent = i18n.submit || 'Add Note';
				} );
		} );

		positionElementAt( form, clientX, clientY );
		popup = form;

		nameField.focus();
	}

	function closePopup() {
		if ( popup ) {
			popup.remove();
			popup = null;
		}
	}

	// ------------------------------------------------------------------
	// Pins — numbered markers placed over the annotated element
	// ------------------------------------------------------------------

	function renderPin( item ) {
		var pin = document.createElement( 'div' );
		pin.className = 'erdo-client-preview-annotation-pin erdo-client-preview-annotation-pin--' + ( item.status || 'in_progress' );
		pin.textContent = String( pins.length + 1 );
		pin.setAttribute( 'data-id', item.id );

		pin.addEventListener( 'click', function ( event ) {
			event.preventDefault();
			event.stopPropagation();
			showDetails( item, pin );
		} );

		document.body.appendChild( pin );
		pins.push( { item: item, el: pin } );

		positionPin( pin, item );
	}

	function positionPin( pin, item ) {
		var target = null;

		try {
			target = document.querySelector( item.selector );
		} catch ( e ) {
			target = null;
		}

		var x = 16;
		var y = 16;

		if ( target ) {
			var rect = target.getBoundingClientRect();
			x = rect.left + ( ( item.x_percent || 0 ) / 100 ) * rect.width;
			y = rect.top  + ( ( item.y_percent || 0 ) / 100 ) * rect.height;
		}

		pin.style.setProperty( 'left', x + 'px', 'important' );
		pin.style.setProperty( 'top',  y + 'px', 'important' );
	}

	function repositionPins() {
		pins.forEach( function ( pin ) {
			positionPin( pin.el, pin.item );
		} );
	}

	var repositionScheduled = false;

	function scheduleRepositionPins() {
		if ( repositionScheduled ) {
			return;
		}

		repositionScheduled = true;

		requestAnimationFrame( function () {
			repositionScheduled = false;
			repositionPins();
		} );
	}

	// ------------------------------------------------------------------
	// Pin details popup — shows message, status and admin reply
	// ------------------------------------------------------------------

	function showDetails( item, pin ) {
		closePopup();

		var box = document.createElement( 'div' );
		box.className = 'erdo-client-preview-annotation-details';

		var rect = pin.getBoundingClientRect();

		box.addEventListener( 'click', function ( event ) {
			event.stopPropagation();
		} );

		var closeBtn = document.createElement( 'button' );
		closeBtn.type = 'button';
		closeBtn.className = 'erdo-client-preview-annotation-details-close';
		closeBtn.textContent = '×';
		closeBtn.setAttribute( 'aria-label', i18n.close || 'Close' );
		closeBtn.addEventListener( 'click', closePopup );
		box.appendChild( closeBtn );

		var content = document.createElement( 'div' );
		content.className = 'erdo-client-preview-annotation-details-content';
		box.appendChild( content );

		renderDetailsContent( content, item );

		positionElementAt( box, rect.left, rect.bottom + 8 );
		popup = box;

		if ( settings.statusUrl && window.fetch ) {
			fetch( settings.statusUrl + '?ids=' + encodeURIComponent( item.id ) )
				.then( function ( response ) {
					return response.json();
				} )
				.then( function ( data ) {
					var fresh = ( data.items || [] )[ 0 ];

					if ( ! fresh ) {
						return;
					}

					item.status       = fresh.status;
					item.status_label = fresh.status_label;
					item.reply         = fresh.reply;

					pin.className = 'erdo-client-preview-annotation-pin erdo-client-preview-annotation-pin--' + item.status;

					if ( popup === box ) {
						renderDetailsContent( content, item );
					}
				} )
				.catch( function () {} );
		}
	}

	function renderDetailsContent( content, item ) {
		content.innerHTML = '';

		var author = document.createElement( 'p' );
		author.className = 'erdo-client-preview-annotation-details-author';
		author.textContent = item.author_name || '';
		content.appendChild( author );

		var message = document.createElement( 'p' );
		message.className = 'erdo-client-preview-annotation-details-message';
		message.textContent = item.message || '';
		content.appendChild( message );

		var status = document.createElement( 'span' );
		status.className = 'erdo-client-preview-annotation-details-status erdo-client-preview-annotation-details-status--' + ( item.status || 'in_progress' );
		status.textContent = item.status_label || '';
		content.appendChild( status );

		if ( item.reply ) {
			var reply = document.createElement( 'p' );
			reply.className = 'erdo-client-preview-annotation-details-reply';

			var label = document.createElement( 'strong' );
			label.textContent = ( i18n.reply || 'Reply:' ) + ' ';

			reply.appendChild( label );
			reply.appendChild( document.createTextNode( item.reply ) );
			content.appendChild( reply );
		}
	}

	// ------------------------------------------------------------------
	// Load existing annotations for this page
	// ------------------------------------------------------------------

	function loadAnnotations() {
		if ( ! settings.restUrl || ! window.fetch ) {
			return;
		}

		fetch( settings.restUrl + '?page_url=' + encodeURIComponent( settings.pageUrl ) )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( data ) {
				( data.items || [] ).forEach( renderPin );
			} )
			.catch( function () {} );
	}

	function init() {
		injectToggle();
		loadAnnotations();

		window.addEventListener( 'resize', scheduleRepositionPins );
		window.addEventListener( 'scroll', scheduleRepositionPins, true );
		document.addEventListener( 'scroll', scheduleRepositionPins, true );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
