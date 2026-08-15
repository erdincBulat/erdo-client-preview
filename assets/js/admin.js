/* global erdoClientPreviewAdmin, wp */
( function ( $ ) {
	'use strict';

	$( function () {

		// -----------------------------------------------------------------------
		// Color pickers
		// -----------------------------------------------------------------------
		if ( $.fn.wpColorPicker ) {
			$( '.sm-color-picker' ).wpColorPicker();
		}

		// -----------------------------------------------------------------------
		// Toggle visibility helpers
		// -----------------------------------------------------------------------
		$( '#sm-countdown-toggle' ).on( 'change', function () {
			$( '.sm-countdown-field' ).toggleClass( 'sm-hidden', ! this.checked );
		} );

		$( '#sm-schedule-toggle' ).on( 'change', function () {
			$( '.sm-schedule-fields' ).toggleClass( 'sm-hidden', ! this.checked );
		} );

		$( '#sm-notify-toggle' ).on( 'change', function () {
			$( '.sm-notify-field' ).toggleClass( 'sm-hidden', ! this.checked );
		} );

		$( '#sm-page-excl-toggle' ).on( 'change', function () {
			$( '.sm-page-excl-fields' ).toggleClass( 'sm-hidden', ! this.checked );
		} );

		$( '#sm-recurring-toggle' ).on( 'change', function () {
			$( '.sm-recurring-fields' ).toggleClass( 'sm-hidden', ! this.checked );
		} );

		$( '#sm-wl-toggle' ).on( 'change', function () {
			$( '.sm-wl-fields' ).toggleClass( 'sm-hidden', ! this.checked );
		} );

		// Mode radio — highlight selected card
		$( document ).on( 'change', 'input[name$="[mode]"]', function () {
			$( '.sm-mode-option' ).removeClass( 'sm-mode-selected' );
			$( this ).closest( '.sm-mode-option' ).addClass( 'sm-mode-selected' );
		} );

		// -----------------------------------------------------------------------
		// Media picker — logo
		// -----------------------------------------------------------------------
		var logoFrame;
		$( '#sm-logo-btn' ).on( 'click', function () {
			if ( logoFrame ) { logoFrame.open(); return; }
			logoFrame = wp.media( {
				title:    erdoClientPreviewAdmin.mediaTitle,
				button:   { text: erdoClientPreviewAdmin.mediaButton },
				multiple: false,
				library:  { type: 'image' },
			} );
			logoFrame.on( 'select', function () {
				var att = logoFrame.state().get( 'selection' ).first().toJSON();
				$( '#sm-logo-url' ).val( att.url );
				// Update or add preview
				var $preview = $( '#sm-logo-url' ).closest( '.sm-field' ).find( '.sm-logo-preview' );
				if ( $preview.length ) {
					$preview.attr( 'src', att.url ).show();
				} else {
					$( '#sm-logo-url' ).closest( '.sm-field' ).append(
						$( '<img>' ).addClass( 'sm-logo-preview' ).attr( { src: att.url, alt: '' } )
					);
				}
				// Show remove button if not already there
				if ( ! $( '#sm-logo-url' ).closest( '.sm-media-row' ).find( '.sm-media-remove' ).length ) {
					$( '#sm-logo-url' ).closest( '.sm-media-row' ).append(
						$( '<button>' ).attr( { type: 'button', class: 'button sm-media-remove', 'data-target': '#sm-logo-url' } )
							.text( erdoClientPreviewAdmin.remove || 'Remove' )
					);
				}
			} );
			logoFrame.open();
		} );

		// -----------------------------------------------------------------------
		// Media picker — background image
		// -----------------------------------------------------------------------
		var bgFrame;
		$( '#sm-bg-image-btn' ).on( 'click', function () {
			if ( bgFrame ) { bgFrame.open(); return; }
			bgFrame = wp.media( {
				title:    erdoClientPreviewAdmin.mediaBgTitle,
				button:   { text: erdoClientPreviewAdmin.mediaBgButton },
				multiple: false,
				library:  { type: 'image' },
			} );
			bgFrame.on( 'select', function () {
				var att = bgFrame.state().get( 'selection' ).first().toJSON();
				$( '#sm-bg-image-url' ).val( att.url );
				// Update or add preview
				var $preview = $( '#sm-bg-image-url' ).closest( '.sm-field' ).find( '.sm-bg-preview' );
				if ( $preview.length ) {
					$preview.attr( 'src', att.url ).show();
				} else {
					$( '#sm-bg-image-url' ).closest( '.sm-field' ).append(
						$( '<img>' ).addClass( 'sm-bg-preview' ).attr( { src: att.url, alt: '' } )
					);
				}
				// Show remove button if not already there
				if ( ! $( '#sm-bg-image-url' ).closest( '.sm-media-row' ).find( '.sm-media-remove' ).length ) {
					$( '#sm-bg-image-url' ).closest( '.sm-media-row' ).append(
						$( '<button>' ).attr( { type: 'button', class: 'button sm-media-remove', 'data-target': '#sm-bg-image-url' } )
							.text( erdoClientPreviewAdmin.remove || 'Remove' )
					);
				}
			} );
			bgFrame.open();
		} );

		// -----------------------------------------------------------------------
		// Media picker — white label logo
		// -----------------------------------------------------------------------
		var wlFrame;
		$( '#sm-wl-logo-btn' ).on( 'click', function () {
			if ( wlFrame ) { wlFrame.open(); return; }
			wlFrame = wp.media( {
				title:    erdoClientPreviewAdmin.mediaWlTitle,
				button:   { text: erdoClientPreviewAdmin.mediaWlButton },
				multiple: false,
				library:  { type: 'image' },
			} );
			wlFrame.on( 'select', function () {
				var att = wlFrame.state().get( 'selection' ).first().toJSON();
				$( '#sm-wl-logo-url' ).val( att.url );
				var $field = $( '#sm-wl-logo-url' ).closest( '.sm-field' );
				var $preview = $field.find( '.sm-logo-preview' );
				if ( $preview.length ) {
					$preview.attr( 'src', att.url ).show();
				} else {
					$field.find( '.sm-media-row' ).after(
						$( '<img>' ).addClass( 'sm-logo-preview' ).attr( { src: att.url, alt: '' } ).css( 'margin-top', '8px' )
					);
				}
				if ( ! $( '#sm-wl-logo-url' ).closest( '.sm-media-row' ).find( '.sm-media-remove' ).length ) {
					$( '#sm-wl-logo-url' ).closest( '.sm-media-row' ).append(
						$( '<button>' ).attr( { type: 'button', class: 'button sm-media-remove', 'data-target': '#sm-wl-logo-url' } )
							.text( erdoClientPreviewAdmin.remove || 'Remove' )
					);
				}
			} );
			wlFrame.open();
		} );

		// -----------------------------------------------------------------------
		// Remove media (logo or background)
		// -----------------------------------------------------------------------
		$( document ).on( 'click', '.sm-media-remove', function () {
			var target  = $( this ).data( 'target' ); // e.g. "#sm-logo-url"
			var $field  = $( this ).closest( '.sm-field' );

			// Clear the URL input
			$( target ).val( '' );

			// Remove any preview image inside the same field
			$field.find( 'img.sm-logo-preview, img.sm-bg-preview' ).remove();

			// Remove this button
			$( this ).remove();
		} );

		// -----------------------------------------------------------------------
		// Add my IP to whitelist
		// -----------------------------------------------------------------------
		$( '.sm-add-ip' ).on( 'click', function () {
			var $ta = $( 'textarea[name$="[ip_whitelist]"]' );
			var ip  = erdoClientPreviewAdmin.detectedIp;
			var val = $ta.val().trim();
			if ( ip && val.indexOf( ip ) === -1 ) {
				$ta.val( val ? val + '\n' + ip : ip );
			}
		} );

		// -----------------------------------------------------------------------
		// Copy URL buttons (magic links + rescue URL)
		// -----------------------------------------------------------------------
		$( document ).on( 'click', '.sm-copy-btn', function () {
			var $btn = $( this );
			var url  = $btn.data( 'url' );
			var orig = $btn.text();

			var doCopy = function () {
				$btn.text( erdoClientPreviewAdmin.copied );
				setTimeout( function () { $btn.text( orig ); }, 2000 );
			};

			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				navigator.clipboard.writeText( url ).then( doCopy ).catch( function () {
					$btn.prev( 'input[readonly]' ).select();
					document.execCommand( 'copy' );
					doCopy();
				} );
			} else {
				$btn.prev( 'input[readonly]' ).select();
				document.execCommand( 'copy' );
				doCopy();
			}
		} );

	} ); // end document.ready

} )( jQuery );
