( function () {
	function init() {
		var settings = window.erdoClientPreviewFeedbackAdmin || {};
		var i18n     = settings.i18n || {};

		if ( ! settings.ajaxUrl || ! window.fetch ) {
			return;
		}

		document.querySelectorAll( '.erdo-client-preview-feedback-reply-save' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				var wrapper = button.closest( '.erdo-client-preview-feedback-reply' );
				var textarea = wrapper ? wrapper.querySelector( '.erdo-client-preview-feedback-reply-input' ) : null;
				var status   = wrapper ? wrapper.querySelector( '.erdo-client-preview-feedback-reply-status' ) : null;

				if ( ! textarea ) {
					return;
				}

				var itemId = wrapper.getAttribute( 'data-item-id' );
				var action = wrapper.getAttribute( 'data-action' ) || 'erdo_client_preview_feedback_reply';

				button.disabled = true;
				if ( status ) {
					status.textContent = i18n.saving || '';
				}

				var body = new URLSearchParams();
				body.set( 'action', action );
				body.set( '_ajax_nonce', settings.nonce || '' );
				body.set( 'item_id', itemId || '' );
				body.set( 'reply', textarea.value );

				fetch( settings.ajaxUrl, {
					method: 'POST',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: body.toString()
				} )
					.then( function ( response ) {
						return response.json();
					} )
					.then( function ( result ) {
						if ( status ) {
							status.textContent = result && result.success ? ( i18n.saved || '' ) : ( i18n.error || '' );
						}
					} )
					.catch( function () {
						if ( status ) {
							status.textContent = i18n.error || '';
						}
					} )
					.then( function () {
						button.disabled = false;
					} );
			} );
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
