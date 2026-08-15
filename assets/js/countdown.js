( function () {
	var el = document.getElementById( 'sm-countdown' );
	var bar = document.getElementById( 'sm-bar' );
	if ( ! el ) {
		return;
	}
	var target = parseInt( el.dataset.target, 10 ) * 1000;
	var start  = parseInt( el.dataset.start, 10 ) * 1000;
	var total  = target - start;
	function pad( n ) {
		return String( n ).padStart( 2, '0' );
	}
	function tick() {
		var now = Date.now(), diff = Math.max( 0, target - now );
		document.getElementById( 'sm-d' ).textContent = pad( Math.floor( diff / 86400000 ) );
		document.getElementById( 'sm-h' ).textContent = pad( Math.floor( ( diff % 86400000 ) / 3600000 ) );
		document.getElementById( 'sm-m' ).textContent = pad( Math.floor( ( diff % 3600000 ) / 60000 ) );
		document.getElementById( 'sm-s' ).textContent = pad( Math.floor( ( diff % 60000 ) / 1000 ) );
		if ( bar && total > 0 ) {
			bar.style.width = Math.max( 0, Math.min( 100, diff / total * 100 ) ) + '%';
		}
		if ( diff > 0 ) {
			setTimeout( tick, 1000 );
		}
	}
	tick();
} )();
