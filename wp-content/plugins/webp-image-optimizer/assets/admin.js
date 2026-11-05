/* WebP Image Optimizer – admin UI (no dependencies). */
( function () {
	'use strict';

	var cfg = window.wioAdmin;
	if ( ! cfg ) {
		return;
	}
	var t = cfg.i18n;

	/* ------------------------------------------------------------------ */
	/* AJAX helper: tolerant of PHP notices printed before the JSON.       */
	/* ------------------------------------------------------------------ */
	function post( action, data ) {
		var body = new URLSearchParams();
		body.append( 'action', action );
		body.append( 'nonce', cfg.nonce );
		Object.keys( data || {} ).forEach( function ( k ) {
			body.append( k, data[ k ] );
		} );

		return fetch( cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body
		} ).then( function ( res ) {
			return res.text().then( function ( text ) {
				var json = null;
				var start = text.indexOf( '{"success"' );
				if ( start > -1 ) {
					try {
						json = JSON.parse( text.slice( start ) );
					} catch ( e ) {
						json = null;
					}
				}
				if ( ! json ) {
					var err = new Error( t.requestFail + ' (HTTP ' + res.status + ')' );
					err.transient = true;
					throw err;
				}
				if ( ! json.success ) {
					var e2 = new Error( ( json.data && json.data.message ) || t.requestFail );
					e2.code = json.data && json.data.code;
					throw e2;
				}
				return json.data;
			} );
		}, function () {
			var err = new Error( t.requestFail );
			err.transient = true;
			throw err;
		} );
	}

	function el( id ) {
		return document.getElementById( id );
	}

	function formatBytes( n ) {
		if ( ! n ) {
			return '0 B';
		}
		var units = [ 'B', 'KB', 'MB', 'GB', 'TB' ];
		var i = Math.min( units.length - 1, Math.floor( Math.log( n ) / Math.log( 1024 ) ) );
		return ( n / Math.pow( 1024, i ) ).toFixed( i ? 1 : 0 ) + ' ' + units[ i ];
	}

	function applyStats( stats ) {
		if ( ! stats ) {
			return;
		}
		[ 'converted', 'total', 'pending', 'skipped', 'failed', 'saved', 'percent' ].forEach( function ( key ) {
			document.querySelectorAll( '[data-wio-stat="' + key + '"]' ).forEach( function ( node ) {
				node.textContent = stats[ key ];
			} );
		} );
		if ( stats.counts ) {
			Object.keys( stats.counts ).forEach( function ( key ) {
				var node = document.querySelector( '[data-wio-count="' + key + '"]' );
				if ( node ) {
					node.textContent = '(' + stats.counts[ key ] + ')';
				}
			} );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Bulk runner                                                         */
	/* ------------------------------------------------------------------ */
	var startBtn = el( 'wio-start' );
	if ( startBtn ) {
		var pauseBtn = el( 'wio-pause' );
		var resumeBtn = el( 'wio-resume' );
		var stopBtn = el( 'wio-stop' );
		var stateEl = el( 'wio-state' );
		var logEl = el( 'wio-log' );
		var progressEl = el( 'wio-progress' );
		var fillEl = el( 'wio-bar-fill' );
		var doneEl = el( 'wio-done' );
		var totalEl = el( 'wio-total' );
		var savedEl = el( 'wio-run-saved' );

		var run = null;

		var setState = function ( text ) {
			stateEl.textContent = text || '';
		};

		var setButtons = function ( state ) {
			startBtn.hidden = state === 'running' || state === 'paused';
			pauseBtn.hidden = state !== 'running';
			resumeBtn.hidden = state !== 'paused';
			stopBtn.hidden = ! ( state === 'running' || state === 'paused' );
			document.querySelectorAll( 'input[name="wio_mode"]' ).forEach( function ( r ) {
				r.disabled = state === 'running' || state === 'paused';
			} );
		};

		var log = function ( item ) {
			logEl.hidden = false;
			var li = document.createElement( 'li' );
			li.className = 'wio-log-' + item.status;
			var name = document.createElement( 'strong' );
			name.textContent = item.name;
			li.appendChild( name );
			if ( item.message ) {
				li.appendChild( document.createTextNode( ' — ' + item.message ) );
			}
			logEl.insertBefore( li, logEl.firstChild );
			while ( logEl.children.length > 300 ) {
				logEl.removeChild( logEl.lastChild );
			}
		};

		var updateProgress = function () {
			var total = Math.max( run.total, run.done );
			doneEl.textContent = run.done;
			totalEl.textContent = total;
			savedEl.textContent = formatBytes( run.saved );
			fillEl.style.width = ( total ? Math.min( 100, ( run.done / total ) * 100 ) : 100 ) + '%';
		};

		var finish = function ( label ) {
			setButtons( 'idle' );
			setState( label );
			window.removeEventListener( 'beforeunload', guardUnload );
			post( 'wio_stats', {} ).then( function ( d ) {
				applyStats( d.stats );
			} ).catch( function () {} );
			run = null;
		};

		var step = function () {
			if ( ! run || run.state !== 'running' ) {
				return;
			}
			post( 'wio_bulk_step', { mode: run.mode, run: run.id, cursor: run.cursor } ).then( function ( d ) {
				if ( ! run ) {
					return;
				}
				run.failures = 0;
				run.cursor = d.cursor;
				( d.items || [] ).forEach( function ( item ) {
					run.done++;
					run.saved += item.saved || 0;
					log( item );
				} );
				updateProgress();
				if ( d.done ) {
					applyStats( d.stats );
					finish( t.done );
					return;
				}
				if ( run.state === 'running' ) {
					setTimeout( step, 150 );
				}
			} ).catch( function ( err ) {
				if ( ! run ) {
					return;
				}
				if ( err.code === 'locked' ) {
					run.state = 'stopped';
					finish( err.message );
					return;
				}
				// A crash on one image: the server marks it on the next request and moves on.
				run.failures++;
				if ( run.failures <= 4 ) {
					setState( t.retrying );
					setTimeout( step, 2000 * run.failures );
				} else {
					run.state = 'paused';
					run.failures = 0;
					setButtons( 'paused' );
					setState( t.gaveUp );
				}
			} );
		};

		var guardUnload = function ( e ) {
			if ( run && run.state === 'running' ) {
				e.preventDefault();
				e.returnValue = t.leave;
				return t.leave;
			}
		};

		var begin = function ( takeover ) {
			var mode = ( document.querySelector( 'input[name="wio_mode"]:checked' ) || {} ).value || 'new';
			if ( mode === 'remove' && ! takeover && ! window.confirm( t.confirmRm ) ) {
				return;
			}
			setState( t.starting );
			startBtn.disabled = true;
			post( 'wio_bulk_start', { mode: mode, takeover: takeover ? '1' : '0' } ).then( function ( d ) {
				startBtn.disabled = false;
				if ( ! d.total ) {
					setState( t.nothing );
					return;
				}
				run = { id: d.run, mode: mode, total: d.total, done: 0, saved: 0, cursor: 0, failures: 0, state: 'running' };
				logEl.innerHTML = '';
				progressEl.hidden = false;
				updateProgress();
				setButtons( 'running' );
				setState( t.working );
				window.addEventListener( 'beforeunload', guardUnload );
				step();
			} ).catch( function ( err ) {
				startBtn.disabled = false;
				if ( err.code === 'locked' && window.confirm( t.takeover ) ) {
					begin( true );
					return;
				}
				setState( err.message );
			} );
		};

		startBtn.addEventListener( 'click', function () {
			begin( false );
		} );
		pauseBtn.addEventListener( 'click', function () {
			if ( run ) {
				run.state = 'paused';
				setButtons( 'paused' );
				setState( t.paused );
			}
		} );
		resumeBtn.addEventListener( 'click', function () {
			if ( run ) {
				run.state = 'running';
				setButtons( 'running' );
				setState( t.working );
				step();
			}
		} );
		stopBtn.addEventListener( 'click', function () {
			if ( ! run ) {
				return;
			}
			var id = run.id;
			run.state = 'stopped';
			post( 'wio_bulk_stop', { run: id } ).catch( function () {} );
			finish( t.stopped );
		} );
	}

	/* ------------------------------------------------------------------ */
	/* Delivery check                                                      */
	/* ------------------------------------------------------------------ */
	var testBtn = el( 'wio-test' );
	if ( testBtn ) {
		testBtn.addEventListener( 'click', function () {
			var out = el( 'wio-test-result' );
			out.textContent = t.working;
			testBtn.disabled = true;
			post( 'wio_test', {} ).then( function ( d ) {
				testBtn.disabled = false;
				out.innerHTML = '';
				var ul = document.createElement( 'ul' );
				d.checks.forEach( function ( c ) {
					var li = document.createElement( 'li' );
					li.className = 'wio-check-' + c[ 0 ];
					li.textContent = c[ 1 ];
					ul.appendChild( li );
				} );
				out.appendChild( ul );
				if ( d.url ) {
					var a = document.createElement( 'a' );
					a.href = d.url;
					a.target = '_blank';
					a.rel = 'noopener';
					a.textContent = d.webp;
					out.appendChild( a );
				}
			} ).catch( function ( err ) {
				testBtn.disabled = false;
				out.textContent = err.message;
			} );
		} );
	}

	/* ------------------------------------------------------------------ */
	/* Media Library row buttons                                           */
	/* ------------------------------------------------------------------ */
	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest ? e.target.closest( '.wio-op' ) : null;
		if ( ! btn ) {
			return;
		}
		e.preventDefault();
		var box = btn.closest( '.wio-status' );
		if ( ! box || box.classList.contains( 'wio-busy' ) ) {
			return;
		}
		box.classList.add( 'wio-busy' );
		var note = document.createElement( 'span' );
		note.className = 'wio-muted';
		note.textContent = t.working;
		box.appendChild( note );

		post( 'wio_single', { id: btn.getAttribute( 'data-id' ), op: btn.getAttribute( 'data-op' ) } ).then( function ( d ) {
			var wrap = document.createElement( 'div' );
			wrap.innerHTML = d.html;
			if ( wrap.firstElementChild ) {
				box.replaceWith( wrap.firstElementChild );
			}
		} ).catch( function ( err ) {
			box.classList.remove( 'wio-busy' );
			note.className = 'wio-warn';
			note.textContent = err.message;
		} );
	} );
}() );
