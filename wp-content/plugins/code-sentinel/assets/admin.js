/* global jQuery, CS */
( function ( $ ) {
	'use strict';

	var SEV = [ 'critical', 'high', 'medium', 'low', 'info' ];
	var lastResult = null;

	function esc( s ) {
		return String( s == null ? '' : s ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}

	/* tabs */
	$( '.cs-tab' ).on( 'click', function ( e ) {
		e.preventDefault();
		var t = $( this ).data( 'tab' );
		$( '.cs-tab' ).removeClass( 'cs-active' );
		$( this ).addClass( 'cs-active' );
		$( '.cs-panel' ).removeClass( 'cs-active' );
		$( '#cs-' + t ).addClass( 'cs-active' );
	} );

	function progress( on, text, pct ) {
		var $p = $( '#cs-progress' );
		$p.prop( 'hidden', ! on );
		if ( text ) { $p.find( '.cs-progress-text' ).text( text ); }
		if ( pct != null ) { $p.find( '.cs-bar-fill' ).css( 'width', pct + '%' ); }
	}

	/* run scan */
	$( '#cs-run' ).on( 'click', function () {
		var target = $( '#cs-target' ).val();
		var core = $( '#cs-core' ).is( ':checked' ) ? 1 : 0;
		$( '#cs-results' ).empty();
		progress( true, 'Scanning ' + ( CS.targets[ target ] || target ) + '… (this can take a moment)', 20 );
		$( '#cs-run' ).prop( 'disabled', true );

		var tick = setInterval( function () {
			var w = parseInt( $( '.cs-bar-fill' ).css( 'width' ), 10 ) || 20;
			progress( true, null, Math.min( 90, w + 3 ) );
		}, 500 );

		$.post( CS.ajax, { action: 'cs_scan', nonce: CS.nonce, target: target, core: core } )
			.done( function ( res ) {
				clearInterval( tick );
				progress( true, 'Done', 100 );
				setTimeout( function () { progress( false ); }, 400 );
				if ( res && res.success ) {
					lastResult = res.data;
					render( res.data );
					$( '#cs-export, #cs-export-json' ).prop( 'disabled', false );
				} else {
					$( '#cs-results' ).html( '<div class="cs-empty">Scan failed: ' + esc( res && res.data ) + '</div>' );
				}
			} )
			.fail( function ( xhr ) {
				clearInterval( tick );
				progress( false );
				$( '#cs-results' ).html( '<div class="cs-empty">Request failed (' + xhr.status + '). The scan may have hit a server limit; try a narrower target.</div>' );
			} )
			.always( function () { $( '#cs-run' ).prop( 'disabled', false ); } );
	} );

	function render( data ) {
		var sev = data.severity || {};
		var html = '<div class="cs-summary">';
		SEV.forEach( function ( s ) {
			html += '<div class="cs-stat ' + s + '"><b>' + ( sev[ s ] || 0 ) + '</b>' + s + '</div>';
		} );
		html += '</div>';

		html += '<div class="cs-filter"><button class="on" data-f="all">All</button>';
		SEV.forEach( function ( s ) {
			if ( sev[ s ] ) { html += '<button data-f="' + s + '">' + s + ' (' + sev[ s ] + ')</button>'; }
		} );
		html += '</div>';

		html += '<div class="cs-search"><input type="search" id="cs-q" placeholder="Filter by file path, rule or text…"></div>';

		var findings = data.findings || [];
		if ( ! findings.length ) {
			html += '<div class="cs-empty"><p>No issues found. 🎉</p><p class="cs-sub">' + ( data.stats ? data.stats.scanned + ' files scanned.' : '' ) + '</p></div>';
			$( '#cs-results' ).html( html );
			return;
		}

		var groups = {};
		var order = [];
		findings.forEach( function ( f ) {
			var k = f.owner || 'other';
			if ( ! groups[ k ] ) { groups[ k ] = []; order.push( k ); }
			groups[ k ].push( f );
		} );

		var ownerMeta = data.owners || {};
		order.forEach( function ( k ) {
			var name = ( ownerMeta[ k ] && ownerMeta[ k ].meta && ownerMeta[ k ].meta.name ) || k;
			html += '<div class="cs-owner" data-owner="' + esc( k ) + '"><h3>' + esc( name ) + ' <span class="cs-count">' + groups[ k ].length + '</span></h3>';
			groups[ k ].forEach( function ( f ) { html += row( f ); } );
			html += '</div>';
		} );

		html += '<p class="cs-sub">Scanned ' + ( data.stats ? data.stats.scanned : '?' ) + ' file(s), skipped ' + ( data.stats ? data.stats.skipped : 0 ) + '. PHP ' + esc( data.php ) + ' · WP ' + esc( data.wp ) + '.</p>';

		$( '#cs-results' ).html( html );
	}

	function row( f ) {
		var loc = esc( f.rel ) + ( f.line ? ':' + f.line : '' );
		var h = '<div class="cs-finding cs-sev-' + esc( f.sev ) + '" data-sev="' + esc( f.sev ) + '" data-txt="' + esc( ( f.rel + ' ' + f.rule + ' ' + ( f.title || '' ) + ' ' + ( f.snippet || '' ) ).toLowerCase() ) + '" data-fp="' + esc( f.fp ) + '">';
		h += '<div class="cs-f-head"><span class="cs-pill cs-pill-' + esc( f.sev ) + '">' + esc( f.sev ) + '</span> <span class="cs-f-title">' + esc( f.title || f.rule ) + '</span>';
		if ( f.count && f.count > 1 ) { h += ' <span class="cs-x">×' + f.count + '</span>'; }
		h += '<span class="cs-cat">' + esc( f.cat || '' ) + '</span></div>';
		h += '<div class="cs-f-loc"><code>' + loc + '</code><button class="cs-copy" data-path="' + esc( loc ) + '" title="Copy path">⧉</button></div>';
		if ( f.snippet ) { h += '<pre class="cs-snip">' + esc( f.snippet ) + '</pre>'; }
		if ( f.desc ) { h += '<div class="cs-f-desc">' + esc( f.desc ) + '</div>'; }
		if ( f.fix ) { h += '<div class="cs-f-fix"><strong>Fix:</strong> ' + esc( f.fix ) + '</div>'; }
		if ( ! f.runtime ) { h += '<button class="button-link cs-ignore" data-fp="' + esc( f.fp ) + '">Ignore</button>'; }
		h += '</div>';
		return h;
	}

	/* filter + search (delegated) */
	$( '#cs-results' ).on( 'click', '.cs-filter button', function () {
		var f = $( this ).data( 'f' );
		$( '.cs-filter button' ).removeClass( 'on' );
		$( this ).addClass( 'on' );
		applyFilters();
	} );
	$( '#cs-results' ).on( 'input', '#cs-q', applyFilters );

	function applyFilters() {
		var sev = $( '.cs-filter button.on' ).data( 'f' ) || 'all';
		var q = ( $( '#cs-q' ).val() || '' ).toLowerCase();
		$( '.cs-finding' ).each( function () {
			var $r = $( this );
			var ok = ( sev === 'all' || $r.data( 'sev' ) === sev ) && ( ! q || ( '' + $r.data( 'txt' ) ).indexOf( q ) > -1 );
			$r.toggleClass( 'cs-hidden', ! ok );
		} );
		$( '.cs-owner' ).each( function () {
			$( this ).toggleClass( 'cs-hidden', $( this ).find( '.cs-finding:not(.cs-hidden)' ).length === 0 );
		} );
	}

	/* copy path */
	$( document ).on( 'click', '.cs-copy', function () {
		var p = $( this ).data( 'path' );
		var btn = this;
		navigator.clipboard && navigator.clipboard.writeText( p ).then( function () {
			btn.textContent = '✓';
			setTimeout( function () { btn.textContent = '⧉'; }, 1000 );
		} );
	} );

	/* ignore */
	$( document ).on( 'click', '.cs-ignore', function () {
		var fp = $( this ).data( 'fp' );
		var $f = $( this ).closest( '.cs-finding' );
		$.post( CS.ajax, { action: 'cs_ignore', nonce: CS.nonce, fp: fp } ).done( function () {
			$f.slideUp( 150, function () { $f.remove(); } );
		} );
	} );

	$( '#cs-reset-ignores' ).on( 'click', function ( e ) {
		e.preventDefault();
		$.post( CS.ajax, { action: 'cs_ignore', nonce: CS.nonce, all: 1 } ).done( function () {
			location.reload();
		} );
	} );

	/* settings */
	$( '#cs-save-settings' ).on( 'click', function ( e ) {
		e.preventDefault();
		var form = $( '#cs-settings-form' ).serialize();
		$( '#cs-settings-msg' ).text( 'Saving…' );
		$.post( CS.ajax, { action: 'cs_save_settings', nonce: CS.nonce, form: form } ).done( function () {
			$( '#cs-settings-msg' ).text( 'Saved.' );
			setTimeout( function () { $( '#cs-settings-msg' ).text( '' ); }, 1500 );
		} );
	} );

	/* clear runtime */
	$( '#cs-clear-runtime' ).on( 'click', function () {
		$.post( CS.ajax, { action: 'cs_clear_runtime', nonce: CS.nonce } ).done( function () {
			$( '#cs-runtime-list' ).html( '<div class="cs-empty"><p>Log cleared.</p></div>' );
			$( '.cs-badge' ).text( '0' );
		} );
	} );

	/* export */
	function exportUrl( fmt ) {
		return CS.ajax; // handled via admin.php redirect below
	}
	$( '#cs-export' ).on( 'click', function () {
		window.location = CS.ajax.replace( 'admin-ajax.php', 'admin.php' ) + '?page=code-sentinel&cs_export=csv&_wpnonce=' + csExportNonce();
	} );
	$( '#cs-export-json' ).on( 'click', function () {
		window.location = CS.ajax.replace( 'admin-ajax.php', 'admin.php' ) + '?page=code-sentinel&cs_export=json&_wpnonce=' + csExportNonce();
	} );
	// export nonce fetched lazily
	var _exp = null;
	function csExportNonce() { return _exp || ''; }
	$.post( CS.ajax, { action: 'cs_export', nonce: CS.nonce } ).done( function ( r ) {
		if ( r && r.success && r.data && r.data.url ) {
			var m = r.data.url.match( /_wpnonce=([a-z0-9]+)/i );
			if ( m ) { _exp = m[ 1 ]; }
		}
	} );

} )( jQuery );
