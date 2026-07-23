/**
 * TutorLMS Analytics — dashboard app.
 *
 * Responsibilities:
 *  - Accessible tab controller (roving tabindex, arrow keys, aria-selected).
 *  - Lazy loading: each section's data is fetched from the REST endpoint the
 *    first time its tab is opened (or when the date range changes).
 *  - KPI cards with period-over-period deltas.
 *  - Chart.js rendering with a colour-blind-safe palette + shared defaults.
 *  - Consistent skeleton / empty / error states.
 *  - Client-side search, sort and pagination for data tables.
 *
 * No build framework: vanilla ES module-ish IIFE, Chart.js is the only dep.
 */
( function () {
	'use strict';

	var cfg  = window.TutorLMSAnalyticsConfig || {};
	var i18n = cfg.i18n || {};
	var Chart = window.Chart;

	// Okabe–Ito colour-blind-safe palette.
	var PALETTE = [ '#0072B2', '#E69F00', '#009E73', '#D55E00', '#CC79A7', '#56B4E9', '#F0E442', '#999999' ];
	var C = {
		blue: '#0072B2', orange: '#E69F00', green: '#009E73',
		red: '#D55E00', purple: '#CC79A7', sky: '#56B4E9', grey: '#999999'
	};

	var charts = {};   // Registry so charts are destroyed before re-render.
	var loaded = {};   // section -> true once rendered for the current range.
	var cache  = {};   // section -> last payload (for table re-sorting).

	// ---- small DOM helpers ----

	function el( tag, attrs, children ) {
		var node = document.createElement( tag );
		attrs = attrs || {};
		Object.keys( attrs ).forEach( function ( k ) {
			if ( k === 'class' ) { node.className = attrs[ k ]; }
			else if ( k === 'html' ) { node.innerHTML = attrs[ k ]; }
			else if ( k === 'text' ) { node.textContent = attrs[ k ]; }
			else if ( k.indexOf( 'aria' ) === 0 || k === 'role' || k === 'tabindex' ) { node.setAttribute( k, attrs[ k ] ); }
			else if ( k.indexOf( 'data' ) === 0 ) { node.setAttribute( k, attrs[ k ] ); }
			else { node[ k ] = attrs[ k ]; }
		} );
		( children || [] ).forEach( function ( c ) {
			if ( c == null ) { return; }
			node.appendChild( typeof c === 'string' ? document.createTextNode( c ) : c );
		} );
		return node;
	}

	function clear( node ) { while ( node && node.firstChild ) { node.removeChild( node.firstChild ); } }

	function t( key, fallback ) { return i18n[ key ] || fallback || key; }

	// ---- formatting ----

	function fmtInt( n ) { return ( Number( n ) || 0 ).toLocaleString(); }
	function fmtMoney( n ) { return '฿' + ( Number( n ) || 0 ).toLocaleString( undefined, { maximumFractionDigits: 0 } ); }
	function fmtPct( n ) { return ( Number( n ) || 0 ).toFixed( 1 ) + '%'; }

	function fmtKpi( kpi ) {
		if ( ! kpi ) { return '—'; }
		switch ( kpi.format ) {
			case 'money': return fmtMoney( kpi.value );
			case 'rating': return ( Number( kpi.value ) || 0 ).toFixed( 1 ) + ' ★';
			case 'pct': return fmtPct( kpi.value );
			default: return fmtInt( kpi.value );
		}
	}

	// ---- state UI (skeleton / empty / error) ----

	function skeleton() {
		return el( 'div', { class: 'tla-skeleton', 'aria-hidden': 'true' }, [
			el( 'div', { class: 'tla-skel-line' } ),
			el( 'div', { class: 'tla-skel-line' } ),
			el( 'div', { class: 'tla-skel-block' } )
		] );
	}

	function emptyState( msg ) {
		return el( 'div', { class: 'tla-empty', role: 'status' }, [
			el( 'span', { class: 'tla-empty-icon', 'aria-hidden': 'true', text: '◔' } ),
			el( 'p', { text: msg || t( 'noData' ) } )
		] );
	}

	function errorState( onRetry ) {
		var btn = el( 'button', { class: 'tla-btn', type: 'button', text: t( 'retry' ) } );
		btn.addEventListener( 'click', onRetry );
		return el( 'div', { class: 'tla-error', role: 'alert' }, [
			el( 'p', { text: t( 'error' ) } ),
			btn
		] );
	}

	// ---- chart factory ----

	function baseOptions( extra ) {
		var o = {
			responsive: true,
			maintainAspectRatio: false,
			plugins: {
				legend: { labels: { font: { family: 'inherit' } } },
				tooltip: { enabled: true }
			},
			animation: { duration: 350 }
		};
		return Object.assign( o, extra || {} );
	}

	function destroyChart( id ) {
		if ( charts[ id ] ) { charts[ id ].destroy(); delete charts[ id ]; }
	}

	/**
	 * Draw a chart into a container that has a data-chart attribute. If the
	 * series is empty, render a consistent empty state instead of a blank canvas.
	 */
	function draw( containerId, hasData, buildConfig ) {
		var host = document.getElementById( containerId );
		if ( ! host ) { return; }
		clear( host );
		destroyChart( containerId );

		if ( ! hasData ) {
			host.appendChild( emptyState() );
			return;
		}
		var canvas = el( 'canvas' );
		host.appendChild( canvas );
		charts[ containerId ] = new Chart( canvas.getContext( '2d' ), buildConfig() );
	}

	function lineSeries( label, series, color ) {
		return {
			type: 'line',
			data: {
				labels: series.labels || [],
				datasets: [ {
					label: label, data: series.data || [],
					borderColor: color, backgroundColor: color + '22',
					borderWidth: 2, fill: true, tension: 0.3, pointRadius: 2
				} ]
			},
			options: baseOptions( { plugins: { legend: { display: false } } } )
		};
	}

	function barSeries( label, labels, data, colors ) {
		return {
			type: 'bar',
			data: { labels: labels, datasets: [ { label: label, data: data, backgroundColor: colors || C.blue, borderRadius: 4 } ] },
			options: baseOptions( { plugins: { legend: { display: false } } } )
		};
	}

	function doughnut( labels, data ) {
		return {
			type: 'doughnut',
			data: { labels: labels, datasets: [ { data: data, backgroundColor: PALETTE } ] },
			options: baseOptions( { plugins: { legend: { position: 'right' } } } )
		};
	}

	function objHasValues( obj ) {
		if ( ! obj ) { return false; }
		return Object.keys( obj ).some( function ( k ) { return Number( obj[ k ] ) > 0; } );
	}
	function seriesHasData( s ) { return s && s.data && s.data.length > 0; }

	// ---- KPI cards ----

	function kpiCard( label, kpi ) {
		var children = [
			el( 'h3', { class: 'tla-kpi-label', text: label } ),
			el( 'p', { class: 'tla-kpi-value', text: fmtKpi( kpi ) } )
		];
		if ( kpi && kpi.delta_pct !== null && kpi.delta_pct !== undefined ) {
			var up = kpi.delta_pct >= 0;
			children.push( el( 'span', {
				class: 'tla-kpi-delta ' + ( up ? 'is-up' : 'is-down' ),
				title: t( 'vsPrevious' )
			}, [ ( up ? '▲ ' : '▼ ' ) + Math.abs( kpi.delta_pct ) + '%' ] ) );
		}
		return el( 'div', { class: 'tla-card tla-kpi' }, children );
	}

	function renderKpis( targetId, kpis, defs ) {
		var host = document.getElementById( targetId );
		if ( ! host || ! kpis ) { return; }
		clear( host );
		defs.forEach( function ( d ) {
			if ( kpis[ d.key ] ) { host.appendChild( kpiCard( d.label, kpis[ d.key ] ) ); }
		} );
	}

	// ---- data tables (search / sort / paginate) ----

	function dataTable( host, opts ) {
		// opts: { columns:[{key,label,align,render,sortable}], rows:[], pageSize, searchKeys:[] }
		var state = { page: 1, sortKey: null, sortDir: 1, query: '' };
		var pageSize = opts.pageSize || 10;

		function filtered() {
			var rows = opts.rows.slice();
			if ( state.query && opts.searchKeys ) {
				var q = state.query.toLowerCase();
				rows = rows.filter( function ( r ) {
					return opts.searchKeys.some( function ( k ) {
						return String( r[ k ] == null ? '' : r[ k ] ).toLowerCase().indexOf( q ) !== -1;
					} );
				} );
			}
			if ( state.sortKey ) {
				rows.sort( function ( a, b ) {
					var av = a[ state.sortKey ], bv = b[ state.sortKey ];
					if ( typeof av === 'number' && typeof bv === 'number' ) { return ( av - bv ) * state.sortDir; }
					return String( av ).localeCompare( String( bv ) ) * state.sortDir;
				} );
			}
			return rows;
		}

		function render() {
			clear( host );
			var rows = filtered();
			var pages = Math.max( 1, Math.ceil( rows.length / pageSize ) );
			if ( state.page > pages ) { state.page = pages; }
			var slice = rows.slice( ( state.page - 1 ) * pageSize, state.page * pageSize );

			// Toolbar (search).
			if ( opts.searchKeys && opts.searchKeys.length ) {
				var input = el( 'input', {
					class: 'tla-search', type: 'search',
					placeholder: opts.searchPlaceholder || 'ค้นหา…', value: state.query
				} );
				input.setAttribute( 'aria-label', opts.searchPlaceholder || 'ค้นหา' );
				input.addEventListener( 'input', function () { state.query = input.value; state.page = 1; render(); input.focus(); } );
				host.appendChild( el( 'div', { class: 'tla-table-toolbar' }, [ input ] ) );
			}

			if ( ! rows.length ) { host.appendChild( emptyState() ); return; }

			var thead = el( 'thead', {}, [ el( 'tr', {}, opts.columns.map( function ( c ) {
				var th = el( 'th', { class: c.align === 'right' ? 'tla-right' : '', scope: 'col' } );
				if ( c.sortable ) {
					var arrow = state.sortKey === c.key ? ( state.sortDir === 1 ? ' ▲' : ' ▼' ) : '';
					var b = el( 'button', { class: 'tla-th-sort', type: 'button', text: c.label + arrow } );
					b.setAttribute( 'aria-label', c.label );
					b.addEventListener( 'click', function () {
						if ( state.sortKey === c.key ) { state.sortDir *= -1; } else { state.sortKey = c.key; state.sortDir = 1; }
						render();
					} );
					th.appendChild( b );
				} else { th.textContent = c.label; }
				return th;
			} ) ) ] );

			var tbody = el( 'tbody', {}, slice.map( function ( r ) {
				return el( 'tr', {}, opts.columns.map( function ( c ) {
					var td = el( 'td', { class: c.align === 'right' ? 'tla-right' : '' } );
					var content = c.render ? c.render( r ) : String( r[ c.key ] == null ? '' : r[ c.key ] );
					if ( content instanceof Node ) { td.appendChild( content ); } else { td.innerHTML = content; }
					return td;
				} ) );
			} ) );

			host.appendChild( el( 'div', { class: 'tla-table-wrap' }, [ el( 'table', { class: 'tla-table' }, [ thead, tbody ] ) ] ) );

			// Pagination.
			if ( pages > 1 ) {
				var prev = el( 'button', { class: 'tla-btn', type: 'button', text: '‹', disabled: state.page === 1 } );
				var next = el( 'button', { class: 'tla-btn', type: 'button', text: '›', disabled: state.page === pages } );
				prev.addEventListener( 'click', function () { if ( state.page > 1 ) { state.page--; render(); } } );
				next.addEventListener( 'click', function () { if ( state.page < pages ) { state.page++; render(); } } );
				host.appendChild( el( 'div', { class: 'tla-pagination' }, [
					prev,
					el( 'span', { class: 'tla-page-info', text: state.page + ' / ' + pages } ),
					next
				] ) );
			}
		}

		render();
	}

	// ---- section renderers ----

	var renderers = {};

	renderers.overview = function ( d ) {
		renderKpis( 'tla-kpis', d.kpis, [
			{ key: 'new_enrollments', label: 'ผู้สมัครใหม่' },
			{ key: 'completions', label: 'เรียนจบ' },
			{ key: 'net_revenue', label: 'รายได้สุทธิ' },
			{ key: 'avg_rating', label: 'คะแนนรีวิวเฉลี่ย' }
		] );
		draw( 'chart-enrollment', seriesHasData( d.enrollment_trend ), function () { return lineSeries( 'ผู้สมัครเรียนใหม่', d.enrollment_trend, C.blue ); } );
		draw( 'chart-active', seriesHasData( d.active_students_trend ), function () { return lineSeries( 'ผู้เข้าเรียน', d.active_students_trend, C.orange ); } );
		draw( 'chart-completions', seriesHasData( d.completion_trend ), function () { return barSeries( 'เรียนจบ', d.completion_trend.labels, d.completion_trend.data, C.green ); } );
		draw( 'chart-popularity', objHasValues( d.course_popularity ), function () { return doughnut( Object.keys( d.course_popularity ), Object.values( d.course_popularity ) ); } );
		draw( 'chart-activity-day', objHasValues( d.activity_by_day ), function () { return barSeries( 'กิจกรรม', Object.keys( d.activity_by_day ), Object.values( d.activity_by_day ), C.purple ); } );
		var dev = d.device_analytics || {};
		draw( 'chart-device', objHasValues( dev.device_distribution ), function () { return doughnut( Object.keys( dev.device_distribution ), Object.values( dev.device_distribution ) ); } );
		draw( 'chart-browser', objHasValues( dev.browser_distribution ), function () { return doughnut( Object.keys( dev.browser_distribution ), Object.values( dev.browser_distribution ) ); } );
		draw( 'chart-hourly', objHasValues( dev.hourly_activity ), function () { return intensityBar( dev.hourly_activity ); } );
	};

	function intensityBar( obj ) {
		var labels = Object.keys( obj ), data = Object.values( obj ).map( Number );
		var max = Math.max.apply( null, data.concat( [ 0 ] ) );
		var colors = data.map( function ( c ) { var i = max > 0 ? c / max : 0; return 'rgba(0,114,178,' + ( 0.2 + i * 0.8 ) + ')'; } );
		return barSeries( 'กิจกรรม', labels, data, colors );
	}

	renderers.courses = function ( d ) {
		var host = document.getElementById( 'tla-course-table' );
		if ( ! host ) { return; }
		dataTable( host, {
			rows: d.course_performance || [],
			pageSize: 12,
			searchKeys: [ 'title' ],
			searchPlaceholder: 'ค้นหาคอร์ส',
			columns: [
				{ key: 'title', label: 'คอร์สเรียน', sortable: true, render: function ( r ) {
					return el( 'a', { class: 'tla-link', href: courseUrl( r.id ), text: r.title } );
				} },
				{ key: 'health', label: 'สถานะ (Health)', sortable: true, align: 'right', render: healthBar },
				{ key: 'learners', label: 'ผู้เรียน', sortable: true, align: 'right', render: function ( r ) { return fmtInt( r.learners ); } },
				{ key: 'completion_rate', label: 'เรียนจบ %', sortable: true, align: 'right', render: function ( r ) { return fmtPct( r.completion_rate ); } },
				{ key: 'avg_rating', label: 'คะแนน', sortable: true, align: 'right', render: function ( r ) { return ( Number( r.avg_rating ) || 0 ).toFixed( 1 ) + '★'; } }
			]
		} );
	};

	function healthBar( r ) {
		var h = Number( r.health ) || 0;
		var cls = h > 75 ? 'is-good' : ( h > 50 ? 'is-mid' : 'is-bad' );
		return el( 'div', { class: 'tla-health' }, [
			el( 'div', { class: 'tla-health-track' }, [ el( 'div', { class: 'tla-health-fill ' + cls, style: 'width:' + h + '%' } ) ] ),
			el( 'span', { class: 'tla-health-num', text: h + '/100' } )
		] );
	}

	function courseUrl( id ) {
		var u = new URL( window.location.href );
		u.searchParams.set( 'course_id', id );
		u.searchParams.delete( 'section' );
		return u.toString();
	}

	renderers.monetization = function ( d ) {
		var m = d.monetization || {}, s = d.subscription || {}, b = d.bundle || {};
		renderKpis( 'tla-money-kpis', {
			gross: kpiWrap( m.gross_revenue, 'money' ),
			net: kpiWrap( m.net_revenue, 'money' ),
			orders: kpiWrap( m.orders_count, 'int' ),
			refund: kpiWrap( m.refund_rate, 'pct' )
		}, [
			{ key: 'gross', label: 'รายได้รวม' },
			{ key: 'net', label: 'รายได้สุทธิ' },
			{ key: 'orders', label: 'คำสั่งซื้อ' },
			{ key: 'refund', label: 'อัตราคืนเงิน' }
		] );
		draw( 'chart-revenue-trend', seriesHasData( m.trend ), function () { return lineSeries( 'รายได้สุทธิ', m.trend, C.green ); } );
		draw( 'chart-order-type', objHasValues( m.by_order_type ), function () {
			return doughnut( [ 'ซื้อครั้งเดียว', 'สมัครสมาชิก', 'ต่ออายุ' ], [ m.by_order_type.single_order, m.by_order_type.subscription, m.by_order_type.renewal ] );
		} );
		draw( 'chart-enroll-source', objHasValues( m.enrollment_sources ), function () {
			var es = m.enrollment_sources;
			return doughnut( [ 'Bundle', 'สมาชิก', 'ซื้อในระบบ', 'ภายนอก', 'เพิ่มเอง/ฟรี' ], [ es.bundle, es.subscription, es.native, es.external, es.manual_free ] );
		} );
		infoRow( 'tla-sub-facts', [
			factItem( 'สมาชิกที่ใช้งาน', s.active_subscriptions == null ? 'ไม่มีข้อมูล' : fmtInt( s.active_subscriptions ) ),
			factItem( 'สมัครใหม่ (ช่วงนี้)', fmtInt( s.new_subscriptions ) ),
			factItem( 'ต่ออายุ (ช่วงนี้)', fmtInt( s.renewals ) ),
			factItem( 'MRR โดยประมาณ', fmtMoney( s.mrr_estimate ) ),
			factItem( 'Churn', s.churn_rate == null ? 'ไม่มีข้อมูล' : fmtPct( s.churn_rate ) )
		] );
		// Coupons + bundles tables.
		var coupons = ( m.coupon_usage && m.coupon_usage.top_coupons ) || [];
		simpleList( 'tla-coupons', coupons, function ( r ) { return r.code + ' — ' + fmtInt( r.uses ) + ' ครั้ง / ' + fmtMoney( r.discount ); }, 'ยังไม่มีการใช้คูปอง' );
		simpleList( 'tla-bundles', b.bundles || [], function ( r ) { return r.title + ' — ' + fmtInt( r.enrollments ) + ' การลงทะเบียน' + ( r.revenue != null ? ' / ' + fmtMoney( r.revenue ) : '' ); }, 'ยังไม่มีข้อมูล Bundle' );
	};

	renderers.community = function ( d ) {
		var q = d.qna || {}, c = d.certificates || {}, l = d.live_lessons || {}, qt = d.quiz_types || {}, g = d.gradebook || {};
		infoRow( 'tla-community-facts', [
			factItem( 'คำถามทั้งหมด', fmtInt( q.total_questions ) ),
			factItem( 'ยังไม่ตอบ', fmtInt( q.unanswered ) ),
			factItem( 'อัตราการตอบ', fmtPct( q.answered_rate ) ),
			factItem( 'ตอบเฉลี่ยภายใน', q.avg_first_response_hours == null ? 'ไม่มีข้อมูล' : q.avg_first_response_hours + ' ชม.' ),
			factItem( 'ใบรับรอง (ช่วงนี้)', fmtInt( c.issued_in_range ) ),
			factItem( 'Live lessons', fmtInt( l.total ) )
		] );
		simpleList( 'tla-qna-unanswered', q.recent_unanswered || [], function ( r ) { return r.excerpt + ' — ' + r.author; }, 'ไม่มีคำถามค้างตอบ' );
		draw( 'chart-cert-trend', c.monthly_trend && seriesHasData( c.monthly_trend ), function () { return barSeries( 'ใบรับรอง', c.monthly_trend.labels, c.monthly_trend.data, C.green ); } );
		// New-4.0 quiz type adoption. Namespaced IDs (-comm) so this can coexist
		// with the assessment panel on a single-course page.
		var types = ( qt.types || [] );
		draw( 'chart-quiz-types-comm', types.length > 0, function () {
			return barSeries( 'จำนวนคำถาม', types.map( function ( r ) { return r.label; } ), types.map( function ( r ) { return r.questions; } ),
				types.map( function ( r ) { return r.is_new_v4 ? C.orange : C.blue; } ) );
		} );
		var host = document.getElementById( 'tla-v4-adoption-comm' );
		if ( host && qt.new_v4_adoption ) {
			host.textContent = 'คำถามแบบใหม่ 4.0: ' + fmtInt( qt.new_v4_adoption.new_type_questions ) + ' / ' + fmtInt( qt.new_v4_adoption.total_questions ) + ' (' + fmtPct( qt.new_v4_adoption.pct ) + ')';
		}
		if ( g.available ) {
			draw( 'chart-gradebook-comm', objHasValues( g.grade_distribution ), function () { return barSeries( 'จำนวนผู้เรียน', Object.keys( g.grade_distribution ), Object.values( g.grade_distribution ), C.purple ); } );
		} else {
			draw( 'chart-gradebook-comm', false, function () {} );
		}
		simpleList( 'tla-live-upcoming', l.upcoming || [], function ( r ) { return r.start + ' — ' + r.title + ' (' + r.type + ')'; }, 'ไม่มี Live lesson ที่กำลังจะมาถึง' );
	};

	// ---- single-course renderers ----

	renderers.insights = function ( d ) {
		renderKpis( 'tla-kpis', d.kpis, [
			{ key: 'new_enrollments', label: 'ผู้สมัครใหม่' },
			{ key: 'completions', label: 'เรียนจบ' },
			{ key: 'net_revenue', label: 'รายได้สุทธิ' },
			{ key: 'avg_rating', label: 'คะแนนรีวิว' }
		] );
		draw( 'chart-survival', seriesHasData( d.survival_curve ), function () {
			var cfgc = lineSeries( 'ยังเรียนอยู่ (%)', d.survival_curve, C.red );
			cfgc.data.datasets[ 0 ].stepped = true; cfgc.data.datasets[ 0 ].tension = 0;
			cfgc.options.scales = { y: { min: 0, max: 100 } };
			return cfgc;
		} );
		draw( 'chart-progress', objHasValues( d.progress_distribution ), function () { return doughnut( Object.keys( d.progress_distribution ), Object.values( d.progress_distribution ) ); } );
		draw( 'chart-quiz-dist', objHasValues( d.quiz_score_distribution ), function () { return barSeries( 'ผู้เข้าสอบ', Object.keys( d.quiz_score_distribution ), Object.values( d.quiz_score_distribution ), [ C.red, C.orange, C.sky, C.green ] ); } );
		draw( 'chart-passfail', objHasValues( d.pass_fail_ratio ), function () { return doughnut( Object.keys( d.pass_fail_ratio ), Object.values( d.pass_fail_ratio ) ); } );
		draw( 'chart-enrollment', seriesHasData( d.enrollment_trend ), function () { return lineSeries( 'ผู้สมัครใหม่', d.enrollment_trend, C.blue ); } );
		draw( 'chart-completions', seriesHasData( d.completion_trend ), function () { return barSeries( 'เรียนจบ', d.completion_trend.labels, d.completion_trend.data, C.green ); } );
	};

	renderers.teaching = function ( d ) {
		var time = d.time_analytics || {}, dev = d.device_analytics || {}, rate = d.rating_analytics || {};
		var tpc = time.time_per_content || [];
		draw( 'chart-time-content', tpc.length > 0, function () {
			var cfgc = barSeries( 'นาที', tpc.map( function ( r ) { return trunc( r.title, 28 ); } ), tpc.map( function ( r ) { return Math.round( ( r.avg_seconds || 0 ) / 60 ); } ), C.purple );
			cfgc.options.indexAxis = 'y';
			return cfgc;
		} );
		draw( 'chart-hourly', objHasValues( dev.hourly_activity ), function () { return intensityBar( dev.hourly_activity ); } );
		draw( 'chart-device', objHasValues( dev.device_distribution ), function () { return doughnut( Object.keys( dev.device_distribution ), Object.values( dev.device_distribution ) ); } );
		draw( 'chart-rating-dist', objHasValues( rate.distribution ), function () { return barSeries( 'จำนวนรีวิว', Object.keys( rate.distribution ), Object.values( rate.distribution ), [ C.red, C.orange, C.orange, C.green, C.green ] ); } );
		draw( 'chart-rating-trend', rate.rating_trend && seriesHasData( rate.rating_trend ), function () {
			var cfgc = lineSeries( 'คะแนนเฉลี่ย', rate.rating_trend, C.orange );
			cfgc.options.scales = { y: { min: 0, max: 5 } };
			return cfgc;
		} );
		var nps = rate.nps_score || {};
		infoRow( 'tla-nps', [
			factItem( 'NPS', nps.score == null ? '—' : String( nps.score ) ),
			factItem( 'Promoters', fmtInt( nps.promoters ) ),
			factItem( 'Passives', fmtInt( nps.passives ) ),
			factItem( 'Detractors', fmtInt( nps.detractors ) )
		] );
	};

	renderers.content = function ( d ) {
		var gaps = d.content_gaps || {}, time = d.time_analytics || {};
		rankedList( 'tla-dropoff', gaps.highest_dropoff_lessons || [], function ( r ) { return { title: r.title, meta: '-' + r.drop_pct + '% (' + r.drop_count + ' คน)' }; }, 'ข้อมูลไม่เพียงพอ' );
		rankedList( 'tla-hardest', gaps.hardest_quizzes || [], function ( r ) { return { title: r.title, meta: 'ผ่าน ' + r.pass_rate + '%' }; }, 'ข้อมูลไม่เพียงพอ' );
		rankedList( 'tla-exit', gaps.exit_lessons || [], function ( r ) { return { title: r.title, meta: 'Exit ' + r.exit_rate + '%' }; }, 'ข้อมูลไม่เพียงพอ' );
		rankedList( 'tla-revisit', time.lesson_revisit_rate || [], function ( r ) { return { title: r.title, meta: 'เปิดซ้ำ ' + r.revisit_rate + '%' }; }, 'ข้อมูลไม่เพียงพอ' );
		renderCurriculum( 'tla-curriculum', d.content_insights || [] );
	};

	renderers.assessment = function ( d ) {
		var qd = d.quiz_diagnostics || {}, qt = d.quiz_types || {}, a = d.assignments || {}, g = d.gradebook || {};
		rankedList( 'tla-q-difficulty', qd.question_difficulty || [], function ( r ) { return { title: r.title || ( 'ข้อ ' + r.question_id ), meta: 'ตอบถูก ' + r.correct_rate + '%' }; }, 'ไม่มีข้อมูล' );
		rankedList( 'tla-q-wrong', qd.common_wrong_answers || [], function ( r ) {
			var title = r.question_title ? r.question_title + ' — ' + ( r.answer || '' ) : ( r.answer || r.title );
			return { title: title, meta: 'เลือกผิด ' + r.selected_count + ' ครั้ง' };
		}, 'ไม่มีข้อมูล' );
		var types = qt.types || [];
		draw( 'chart-quiz-types', types.length > 0, function () {
			return barSeries( 'จำนวนคำถาม', types.map( function ( r ) { return r.label; } ), types.map( function ( r ) { return r.questions; } ),
				types.map( function ( r ) { return r.is_new_v4 ? C.orange : C.blue; } ) );
		} );
		infoRow( 'tla-assign-facts', a.available ? [
			factItem( 'งานทั้งหมด', fmtInt( a.total_assignments ) ),
			factItem( 'ส่งแล้ว (ช่วงนี้)', fmtInt( a.submissions ) ),
			factItem( 'รอตรวจ', a.pending_review == null ? 'ไม่มีข้อมูล' : fmtInt( a.pending_review ) ),
			factItem( 'คะแนนเฉลี่ย', a.avg_score_pct == null ? 'ไม่มีข้อมูล' : fmtPct( a.avg_score_pct ) ),
			factItem( 'ตรวจเฉลี่ยภายใน', a.grading_turnaround_hours == null ? 'ไม่มีข้อมูล' : a.grading_turnaround_hours + ' ชม.' )
		] : [ factItem( 'Assignments', 'ยังไม่เปิดใช้งาน / ไม่มีข้อมูล' ) ] );
		if ( g.available ) {
			draw( 'chart-gradebook', objHasValues( g.grade_distribution ), function () { return barSeries( 'จำนวนผู้เรียน', Object.keys( g.grade_distribution ), Object.values( g.grade_distribution ), C.purple ); } );
		} else {
			draw( 'chart-gradebook', false, function () {} );
		}
	};

	renderers.learners = function ( d ) {
		renderLessonMatrix( 'tla-lesson-matrix', d.lesson_matrix || {} );
		var cohort = ( d.cohort && d.cohort.completion_by_enrollment_cohort ) || [];
		draw( 'chart-cohort', cohort.length > 0, function () {
			return barSeries( 'อัตราเรียนจบ (%)', cohort.map( function ( r ) { return r.cohort; } ), cohort.map( function ( r ) { return r.completion_rate; } ), C.blue );
		} );
		var retention = ( d.cohort && d.cohort.retention_by_week ) || [];
		draw( 'chart-retention', retention.length > 0, function () {
			return lineSeries( 'Retention (%)', {
				labels: retention.map( function ( r ) { return r.week; } ),
				data: retention.map( function ( r ) { return r.retention_rate; } )
			}, C.green );
		} );
		var rows = d.student_table || [];
		var scores = ( d.engagement && d.engagement.scores ) || [];
		var scoreMap = {};
		scores.forEach( function ( s ) { scoreMap[ s.user_id ] = s.score; } );
		rows.forEach( function ( r ) { r.engagement = scoreMap[ r.user_id ] != null ? scoreMap[ r.user_id ] : 0; } );
		var host = document.getElementById( 'tla-learner-table' );
		if ( ! host ) { return; }
		dataTable( host, {
			rows: rows, pageSize: 15, searchKeys: [ 'display_name', 'email' ], searchPlaceholder: 'ค้นหาผู้เรียน',
			columns: [
				{ key: 'display_name', label: 'ผู้เรียน', sortable: true, render: function ( r ) {
					return el( 'div', {}, [ el( 'div', { class: 'tla-strong', text: r.display_name } ), el( 'div', { class: 'tla-muted', text: r.email } ) ] );
				} },
				{ key: 'status', label: 'สถานะ', sortable: true, render: function ( r ) {
					return el( 'span', { class: 'tla-badge ' + ( r.status === 'Active' ? 'is-active' : 'is-idle' ), text: r.status === 'Active' ? 'Active' : 'Inactive' } );
				} },
				{ key: 'engagement', label: 'Engagement', sortable: true, align: 'right', render: function ( r ) { return String( r.engagement ); } },
				{ key: 'avg_progress', label: 'ความคืบหน้า', sortable: true, align: 'right', render: function ( r ) {
					return el( 'div', { class: 'tla-health' }, [
						el( 'div', { class: 'tla-health-track' }, [ el( 'div', { class: 'tla-health-fill is-good', style: 'width:' + ( r.avg_progress || 0 ) + '%' } ) ] ),
						el( 'span', { class: 'tla-health-num', text: fmtPct( r.avg_progress ) } )
					] );
				} },
				{ key: 'quiz_avg_score', label: 'คะแนนควิซ', sortable: true, align: 'right', render: function ( r ) { return fmtPct( r.quiz_avg_score ); } }
			]
		} );
	};

	renderers.action = function ( d ) {
		var alerts = d.alerts || [];
		var atRisk = ( d.engagement && d.engagement.at_risk_students ) || [];
		var host = document.getElementById( 'tla-alerts' );
		if ( host ) {
			clear( host );
			if ( ! alerts.length ) {
				host.appendChild( el( 'div', { class: 'tla-ok', text: 'ยอดเยี่ยม! ไม่พบปัญหาสำคัญในคอร์สนี้' } ) );
			} else {
				alerts.forEach( function ( a ) {
					host.appendChild( el( 'div', { class: 'tla-alert ' + ( a.type === 'danger' ? 'is-danger' : 'is-warn' ) }, [
						el( 'h4', { text: a.title } ),
						el( 'p', { text: a.message } )
					] ) );
				} );
			}
		}
		rankedList( 'tla-atrisk', atRisk.slice( 0, 10 ), function ( r ) { return { title: r.display_name, meta: 'Progress ' + r.progress_pct + '% / score ' + r.score }; }, 'ไม่มีผู้เรียนกลุ่มเสี่ยง' );
	};

	// ---- shared list/curriculum helpers ----

	function kpiWrap( value, format ) { return { value: value, delta_pct: null, format: format }; }

	function factItem( label, value ) { return el( 'div', { class: 'tla-fact' }, [ el( 'span', { class: 'tla-fact-value', text: value } ), el( 'span', { class: 'tla-fact-label', text: label } ) ] ); }

	function infoRow( id, items ) { var host = document.getElementById( id ); if ( ! host ) { return; } clear( host ); items.forEach( function ( i ) { host.appendChild( i ); } ); }

	function simpleList( id, rows, fmt, empty ) {
		var host = document.getElementById( id );
		if ( ! host ) { return; }
		clear( host );
		if ( ! rows || ! rows.length ) { host.appendChild( emptyState( empty ) ); return; }
		var ul = el( 'ul', { class: 'tla-list' }, rows.map( function ( r ) { return el( 'li', { text: fmt( r ) } ); } ) );
		host.appendChild( ul );
	}

	function rankedList( id, rows, mapFn, empty ) {
		var host = document.getElementById( id );
		if ( ! host ) { return; }
		clear( host );
		if ( ! rows || ! rows.length ) { host.appendChild( emptyState( empty ) ); return; }
		var ul = el( 'ul', { class: 'tla-ranked' }, rows.map( function ( r, i ) {
			var m = mapFn( r );
			return el( 'li', {}, [
				el( 'span', { class: 'tla-rank-num', text: String( i + 1 ) } ),
				el( 'span', { class: 'tla-rank-title', text: m.title } ),
				el( 'span', { class: 'tla-rank-meta', text: m.meta } )
			] );
		} ) );
		host.appendChild( ul );
	}

	// Per-student lesson progress matrix (dot timeline).
	function renderLessonMatrix( id, data ) {
		var host = document.getElementById( id );
		if ( ! host ) { return; }
		clear( host );

		var lessons  = data.lessons || [];
		var students = data.students || [];
		var summary  = data.summary || {};
		if ( ! lessons.length || ! students.length ) {
			host.appendChild( emptyState( 'ยังไม่มีข้อมูลความคืบหน้าของผู้เรียน' ) );
			return;
		}

		// Summary facts.
		var facts = el( 'div', { class: 'tla-facts' }, [
			factItem( 'ผู้เรียนทั้งหมด', fmtInt( summary.total_students ) ),
			factItem( 'ความคืบหน้าเฉลี่ย', fmtPct( summary.avg_percent ) ),
			factItem( 'เรียนจบครบ', fmtInt( summary.completed_all ) ),
			factItem( 'กำลังเรียน', fmtInt( summary.in_progress ) ),
			factItem( 'ยังไม่เริ่ม', fmtInt( summary.not_started ) )
		] );
		host.appendChild( facts );

		var palette = [ C.blue, C.green, C.purple, C.orange, '#2A9D8F', '#E76F51' ];
		var orderMap = {};
		lessons.forEach( function ( l, i ) { orderMap[ l.id ] = i; } );

		// Header row.
		var headCells = [ el( 'div', { class: 'tla-lm-name tla-lm-head', text: 'ผู้เรียน' } ) ];
		lessons.forEach( function ( l, i ) {
			headCells.push( el( 'div', { class: 'tla-lm-cell tla-lm-head', title: l.title, text: 'บทที่ ' + ( i + 1 ) } ) );
		} );
		var grid = el( 'div', { class: 'tla-lm-grid', style: '--lm-cols:' + lessons.length, role: 'table' }, [
			el( 'div', { class: 'tla-lm-row', role: 'row' }, headCells )
		] );

		students.forEach( function ( s, si ) {
			var color = palette[ si % palette.length ];
			var done = {};
			( s.completed || [] ).forEach( function ( lid ) { done[ lid ] = true; } );
			var currentPos = s.current_lesson_id && orderMap[ s.current_lesson_id ] != null ? orderMap[ s.current_lesson_id ] : -1;

			var cells = [ el( 'div', { class: 'tla-lm-name', title: s.percent + '%', text: s.display_name } ) ];
			lessons.forEach( function ( l, i ) {
				var cls = 'tla-lm-cell';
				cls += done[ l.id ] ? ' is-done' : ' is-todo';
				if ( i === currentPos ) { cls += ' is-current'; }
				if ( i <= currentPos ) { cls += ' in-track'; }
				if ( i === 0 ) { cls += ' is-first'; }
				if ( i === lessons.length - 1 ) { cls += ' is-last'; }
				var tip = s.display_name + ' · ' + l.title + ( done[ l.id ] ? ' ✓' : '' );
				cells.push( el( 'div', { class: cls, title: tip }, [ el( 'span', { class: 'tla-lm-dot' } ) ] ) );
			} );
			grid.appendChild( el( 'div', { class: 'tla-lm-row', role: 'row', style: '--lmc:' + color }, cells ) );
		} );

		host.appendChild( el( 'div', { class: 'tla-lm-scroll' }, [ grid ] ) );
	}

	function renderCurriculum( id, topics ) {
		var host = document.getElementById( id );
		if ( ! host ) { return; }
		clear( host );
		if ( ! topics.length ) { host.appendChild( emptyState( 'ไม่มีข้อมูลโครงสร้างหลักสูตร' ) ); return; }
		topics.forEach( function ( topic ) {
			var items = ( topic.contents || [] ).map( function ( c ) {
				var meta = c.type === 'tutor_quiz'
					? ( 'คะแนนเฉลี่ย ' + ( c.avg_score || 0 ) + '% · ' + ( c.avg_attempts_per_user || 0 ) + ' ครั้ง/คน' )
					: ( 'เรียนจบ ' + ( c.completed_count || 0 ) + ' คน' );
				return el( 'li', {}, [ el( 'span', { class: 'tla-ci-title', text: c.title } ), el( 'span', { class: 'tla-ci-meta', text: meta } ) ] );
			} );
			host.appendChild( el( 'div', { class: 'tla-topic' }, [
				el( 'h4', { class: 'tla-topic-title', text: topic.title } ),
				el( 'ul', { class: 'tla-list' }, items )
			] ) );
		} );
	}

	function trunc( s, n ) { s = String( s || '' ); return s.length > n ? s.slice( 0, n - 1 ) + '…' : s; }

	// ---- fetching + section lifecycle ----

	function currentRange() {
		var params = new URLSearchParams( window.location.search );
		return { from: params.get( 'from' ), to: params.get( 'to' ) };
	}

	function loadSection( section, force ) {
		if ( loaded[ section ] && ! force ) { return; }
		var panel = document.getElementById( 'panel-' + section );
		if ( ! panel ) { return; }
		var body = panel.querySelector( '[data-section-body]' ) || panel;

		// If the panel ships server-rendered initial data, use it once.
		if ( ! force && panel.dataset.initial === '1' && window.TutorLMSAnalyticsInitial ) {
			cache[ section ] = window.TutorLMSAnalyticsInitial;
			loaded[ section ] = true;
			safeRender( section, window.TutorLMSAnalyticsInitial );
			return;
		}

		showLoading( panel );
		var r = currentRange();
		var url = new URL( cfg.restUrl );
		url.searchParams.set( 'section', section );
		url.searchParams.set( 'course_id', cfg.courseId || 0 );
		if ( r.from ) { url.searchParams.set( 'from', r.from ); }
		if ( r.to ) { url.searchParams.set( 'to', r.to ); }

		fetch( url.toString(), { headers: { 'X-WP-Nonce': cfg.nonce }, credentials: 'same-origin' } )
			.then( function ( res ) { if ( ! res.ok ) { throw new Error( 'HTTP ' + res.status ); } return res.json(); } )
			.then( function ( json ) {
				if ( ! json || ! json.success ) { throw new Error( 'bad payload' ); }
				cache[ section ] = json.data;
				loaded[ section ] = true;
				hideLoading( panel );
				safeRender( section, json.data );
			} )
			.catch( function () {
				hideLoading( panel );
				clear( body );
				body.appendChild( errorState( function () { loaded[ section ] = false; loadSection( section, true ); } ) );
			} );
	}

	function safeRender( section, data ) {
		try {
			if ( renderers[ section ] ) { renderers[ section ]( data || {} ); }
		} catch ( e ) {
			if ( window.console ) { window.console.error( 'TLA render error', section, e ); }
		}
	}

	function showLoading( panel ) {
		var overlay = panel.querySelector( '.tla-loading' );
		if ( ! overlay ) {
			overlay = el( 'div', { class: 'tla-loading', role: 'status', 'aria-live': 'polite' }, [ skeleton(), el( 'span', { class: 'tla-sr', text: t( 'loading' ) } ) ] );
			panel.insertBefore( overlay, panel.firstChild );
		}
		overlay.style.display = '';
	}
	function hideLoading( panel ) { var o = panel.querySelector( '.tla-loading' ); if ( o ) { o.style.display = 'none'; } }

	// ---- accessible tabs ----

	function initTabs() {
		var tablist = document.querySelector( '[role="tablist"]' );
		if ( ! tablist ) { return; }
		var tabs = Array.prototype.slice.call( tablist.querySelectorAll( '[role="tab"]' ) );

		function activate( tab, setFocus ) {
			tabs.forEach( function ( x ) {
				var selected = x === tab;
				x.setAttribute( 'aria-selected', selected ? 'true' : 'false' );
				x.tabIndex = selected ? 0 : -1;
				var panel = document.getElementById( x.getAttribute( 'aria-controls' ) );
				if ( panel ) { panel.hidden = ! selected; }
			} );
			if ( setFocus ) { tab.focus(); }
			var section = tab.dataset.section;
			// Persist last tab.
			try { window.localStorage.setItem( 'tla_tab_' + ( cfg.courseId || 0 ), section ); } catch ( e ) {}
			loadSection( section, false );
		}

		tabs.forEach( function ( tab, idx ) {
			tab.addEventListener( 'click', function () { activate( tab, false ); } );
			tab.addEventListener( 'keydown', function ( e ) {
				var i = idx;
				if ( e.key === 'ArrowRight' || e.key === 'ArrowDown' ) { i = ( idx + 1 ) % tabs.length; }
				else if ( e.key === 'ArrowLeft' || e.key === 'ArrowUp' ) { i = ( idx - 1 + tabs.length ) % tabs.length; }
				else if ( e.key === 'Home' ) { i = 0; }
				else if ( e.key === 'End' ) { i = tabs.length - 1; }
				else { return; }
				e.preventDefault();
				activate( tabs[ i ], true );
			} );
		} );

		// Choose initial tab: stored → server initial → first.
		var stored;
		try { stored = window.localStorage.getItem( 'tla_tab_' + ( cfg.courseId || 0 ) ); } catch ( e ) {}
		var initialTab = tabs.filter( function ( x ) { return x.dataset.section === stored; } )[ 0 ]
			|| tabs.filter( function ( x ) { return x.dataset.initial === '1'; } )[ 0 ]
			|| tabs[ 0 ];
		activate( initialTab, false );
	}

	// ---- date range control ----

	function initDateRange() {
		var form = document.getElementById( 'tla-range-form' );
		if ( ! form ) { return; }
		form.addEventListener( 'submit', function () { /* native GET submit reloads with from/to */ } );

		var presets = form.querySelectorAll( '[data-preset]' );
		Array.prototype.forEach.call( presets, function ( btn ) {
			btn.addEventListener( 'click', function () {
				var days = parseInt( btn.dataset.preset, 10 );
				var to = new Date();
				var from = new Date();
				from.setDate( to.getDate() - ( days - 1 ) );
				var u = new URL( window.location.href );
				u.searchParams.set( 'from', from.toISOString().slice( 0, 10 ) );
				u.searchParams.set( 'to', to.toISOString().slice( 0, 10 ) );
				window.location.href = u.toString();
			} );
		} );
	}

	function boot() {
		if ( ! Chart ) { if ( window.console ) { window.console.warn( 'Chart.js not loaded' ); } }
		initTabs();
		initDateRange();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
