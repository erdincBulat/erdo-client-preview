( function () {
	var data = window.erdoClientPreviewAnnotationLocator;

	if ( ! data || ! data.items || ! data.items.length ) {
		return;
	}

	var i18n  = data.i18n || {};
	var docEl = document.documentElement;
	var popup = null;
	var pins  = [];

	function closePopup() {
		if ( popup ) {
			popup.remove();
			popup = null;
		}
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
	// Pin details popup — shows author, message, status and admin reply
	// ------------------------------------------------------------------

	function renderDetails( item, pin ) {
		closePopup();

		var box = document.createElement( 'div' );
		box.className = 'erdo-client-preview-annotation-details';
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

		var author = document.createElement( 'p' );
		author.className = 'erdo-client-preview-annotation-details-author';
		author.textContent = item.authorName || '';
		content.appendChild( author );

		var message = document.createElement( 'p' );
		message.className = 'erdo-client-preview-annotation-details-message';
		message.textContent = item.message || '';
		content.appendChild( message );

		var status = document.createElement( 'span' );
		status.className = 'erdo-client-preview-annotation-details-status erdo-client-preview-annotation-details-status--' + ( item.status || 'in_progress' );
		status.textContent = item.statusLabel || '';
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

		var rect = pin.getBoundingClientRect();
		positionElementAt( box, rect.left, rect.bottom + 8 );
		popup = box;
	}

	// ------------------------------------------------------------------
	// Pins — one persistent numbered marker per annotation, placed using
	// each note's position as a percentage of the full page width/height,
	// converted to viewport-fixed coordinates by subtracting the current
	// scroll offset. Kept in sync while the page scrolls or resizes.
	// ------------------------------------------------------------------

	function pinPosition( item ) {
		return {
			x: ( ( item.pageXPercent || 0 ) / 100 ) * docEl.scrollWidth,
			y: ( ( item.pageYPercent || 0 ) / 100 ) * docEl.scrollHeight
		};
	}

	function positionPin( pin, item ) {
		var pos = pinPosition( item );

		pin.style.setProperty( 'left', ( pos.x - window.scrollX ) + 'px', 'important' );
		pin.style.setProperty( 'top',  ( pos.y - window.scrollY ) + 'px', 'important' );
	}

	function repositionPins() {
		pins.forEach( function ( pin ) {
			positionPin( pin.el, pin.item );
		} );
	}

	var repositionScheduled = false;

	function schedulePosition() {
		if ( repositionScheduled ) {
			return;
		}

		repositionScheduled = true;

		requestAnimationFrame( function () {
			repositionScheduled = false;
			repositionPins();
		} );
	}

	function renderPin( item, index ) {
		var pin = document.createElement( 'div' );
		pin.className = 'erdo-client-preview-annotation-pin erdo-client-preview-annotation-pin--' + ( item.status || 'in_progress' );
		pin.textContent = String( index + 1 );

		pin.addEventListener( 'click', function ( event ) {
			event.preventDefault();
			event.stopPropagation();
			renderDetails( item, pin );
		} );

		document.body.appendChild( pin );
		pins.push( { item: item, el: pin } );

		positionPin( pin, item );

		return pin;
	}

	function init() {
		data.items.forEach( renderPin );

		window.addEventListener( 'resize', schedulePosition );
		window.addEventListener( 'scroll', schedulePosition, true );
		document.addEventListener( 'scroll', schedulePosition, true );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
