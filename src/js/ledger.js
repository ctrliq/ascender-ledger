/*
	Shared page behaviour for Ascender Ledger
*/
(function ($) {
	'use strict';

	var csrfToken = $('meta[name="csrf-token"]').attr('content') || '';

	/*
		Every POST has to carry the session's CSRF token.  Rather than repeat a hidden field in
		every template, it is added to each form as the form is submitted, and sent as a
		header on any AJAX request that is not a GET.
	*/
	$(document).on('submit', 'form', function () {
		var form = $(this);
		if ((form.attr('method') || 'get').toLowerCase() === 'post' && form.find('input[name="csrf_token"]').length === 0) {
			$('<input>', { type: 'hidden', name: 'csrf_token', value: csrfToken }).appendTo(form);
		}
	});

	$.ajaxSetup({
		beforeSend: function (xhr, settings) {
			if (!/^(GET|HEAD|OPTIONS)$/i.test(settings.type)) {
				xhr.setRequestHeader('X-CSRF-Token', csrfToken);
			}
		}
	});

	/*
		Pickers.  A select with class ledger-select is a searchable menu over its own options;
		ledger-lookup asks the server for one page of matches at a time (data-lookup names the
		list), for lists too long to put in the page.
	*/
	window.ledgerSelect = function (scope) {
		$(scope || document).find('select.ledger-select').each(function () {
			var el = $(this);
			el.select2({
				theme: 'bootstrap',
				width: '100%',
				allowClear: !el.prop('multiple') && !el.data('noclear'),
				placeholder: el.data('placeholder') || '',
				tags: el.data('tags') === true,
				minimumResultsForSearch: el.data('nosearch') ? Infinity : 0,
				dropdownParent: el.closest('.modal').length ? el.closest('.modal') : $(document.body)
			});
		});

		$(scope || document).find('select.ledger-lookup').each(function () {
			var el = $(this);
			el.select2({
				theme: 'bootstrap',
				width: '100%',
				allowClear: !el.prop('multiple'),
				placeholder: el.data('placeholder') || 'Type to search...',
				dropdownParent: el.closest('.modal').length ? el.closest('.modal') : $(document.body),
				ajax: {
					url: '/lookup/',
					dataType: 'json',
					delay: 250,
					cache: true,
					data: function (params) {
						return { what: el.data('lookup'), q: params.term || '', page: params.page || 1 };
					}
				}
			});
		});
	};

	/*
		The "Add a Filter" bar on the list pages.  Choosing a filter shows its input; Add sends
		the browser to that input's data-url with the chosen value appended.  A filter made of
		several inputs (class filter-multi) has data-url itself, and gets all its named inputs
		appended as a query string.
	*/
	window.ledgerFilterBar = function () {
		var chooser = $('#addfilter');
		if (!chooser.length) {
			return;
		}

		function show() {
			var f = chooser.val();
			$('.filter-input').hide();
			if (f) {
				$('.filter-input[data-filter="' + f + '"]').show();
			}
		}

		function apply() {
			var f = chooser.val();
			if (!f) {
				return;
			}
			var box = $('.filter-input[data-filter="' + f + '"]');
			if (box.hasClass('filter-multi')) {
				window.location = box.data('url') + box.find('[name]').serialize();
				return;
			}
			var input = box.find('select, input').first();
			var value = input.val();
			if (value === null || value === undefined) {
				value = '';
			}
			window.location = input.data('url') + encodeURIComponent(value);
		}

		chooser.on('change', show);
		$('#add').on('click', function (e) {
			e.preventDefault();
			apply();
		});
		$('.filter-input input').on('keydown', function (e) {
			if (e.key === 'Enter') {
				e.preventDefault();
				apply();
			}
		});
		show();
	};

	/*
		The Saved Filters row under the filter bar.  Choosing a saved filter applies it; Create
		asks for a name and saves the current filters under it; Save updates the chosen one with
		the current filters; Delete removes it.
	*/
	window.ledgerSavedFilters = function () {
		var row = $('.ledger-saved-filters');
		if (!row.length) {
			return;
		}
		var page = row.data('page');
		var form = $('#saved_filter_form');

		function send(action, name) {
			form.find('input[name="action"]').val(action);
			if (name !== undefined) {
				form.find('input[name="name"]').val(name);
			}
			form.trigger('submit');
		}

		$('#saved_filter').on('change', function () {
			var id = $(this).val();
			if (id) {
				window.location = '/' + page + '/?action=applyfilter&filter=' + encodeURIComponent(id);
			}
		});
		$('#saved_filter_create').on('click', function () {
			var name = window.prompt('Enter a name for this Filter (up to 32 characters).', 'New Filter');
			if (name !== null && $.trim(name) !== '') {
				send('createfilter', $.trim(name));
			}
		});
		$('#saved_filter_save').on('click', function () {
			send('savefilter');
		});
		$('#saved_filter_delete').on('click', function () {
			if (window.confirm('Are you sure you want to delete the saved filter "' + $(this).data('name') + '"?')) {
				send('deletefilter');
			}
		});
	};

	/*
		Every table puts its search box and row count at the top left, as Ascender Ledger Pro
		does.  A table with export buttons puts them at the top right (layout.topEnd).
	*/
	$.extend(true, $.fn.dataTable.defaults, {
		layout: {
			topStart: {
				search: { text: 'Search' },
				pageLength: { text: 'Rows&nbsp;&nbsp;_MENU_' }
			},
			topEnd: null
		}
	});

	/*
		Copy, CSV, PDF and Print buttons for a table whose rows come from the server, as in
		Ascender Ledger Pro.  Copy and Print take the page on screen.  CSV and PDF take every row
		the filters match, up to the export limit (Settings > Visual): the table loads them all
		as one page, they are exported, and it goes back to the page it was on.  Cells are
		exported as their data, not as shown, so a long fact value is not cut short.
	*/
	var exportLimit = parseInt($('meta[name="export-limit"]').attr('content'), 10) || 10000;

	function exportAll(kind, title, columns) {
		return {
			extend: kind,
			title: title,
			orientation: (kind === 'pdf' ? 'landscape' : undefined),
			// Setting exportOptions replaces the CSV button's own, so keep its formula escaping
			// (a cell starting with = + - @ is not run as a spreadsheet formula)
			exportOptions: { columns: columns, orthogonal: 'export', escapeExcelFormula: true },
			action: function (e, dt, node, config, cb) {
				var self = this;
				var len = dt.page.len();
				var page = dt.page();
				var want = Math.min(dt.page.info().recordsTotal, exportLimit);
				var run = $.fn.dataTable.ext.buttons[kind + 'Html5'].action;
				if (want <= len && page === 0) {
					run.call(self, e, dt, node, config, cb);
					return;
				}
				dt.one('draw', function () {
					run.call(self, e, dt, node, config, function () {
						dt.page.len(len).page(page).draw('page');
						cb();
					});
				});
				dt.page.len(want).draw();
			}
		};
	}

	window.ledgerExportButtons = function (title, columns) {
		columns = columns || ':visible';
		return [
			{ extend: 'copy', exportOptions: { columns: columns, orthogonal: 'export' } },
			exportAll('csv', title, columns),
			exportAll('pdf', title, columns),
			{ extend: 'print', title: title, exportOptions: { columns: columns, orthogonal: 'export' } }
		];
	};

	/*
		A DataTable whose rows come from the server a page at a time.  Every column is shown
		as text unless it has its own render function.  The server stops counting rows at a
		cap, so the info line says "more than" rather than giving a wrong total.
	*/
	window.ledgerTable = function (selector, options) {
		// exportTitle adds the export buttons; exportColumns picks the columns they export
		if (options.exportTitle) {
			options = $.extend(true, {}, options, { layout: { topEnd: { buttons: window.ledgerExportButtons(options.exportTitle, options.exportColumns) } } });
		}
		var cols = options.columns.map(function (c) {
			if (!c.render) {
				c.render = $.fn.dataTable.render.text();
			}
			return c;
		});

		return $(selector).DataTable($.extend({
			serverSide: true,
			processing: true,
			searchDelay: 600,
			pageLength: 25,
			lengthMenu: [10, 25, 50, 100, 250, 500],
			ajax: { url: options.url, type: 'GET' },
			infoCallback: function (settings, start, end, max, total, pre) {
				var json = this.api().ajax.json();
				if (json && json.capped) {
					return 'Showing ' + start + ' to ' + end + ' of more than ' + total.toLocaleString() + ' entries (narrow the filters to see an exact count)';
				}
				return pre;
			}
		}, options, { columns: cols }));
	};

	/*
		Escapes text for building HTML in a render function
	*/
	window.ledgerEscape = function (s) {
		return $('<div>').text(s === null || s === undefined ? '' : String(s)).html();
	};

	/*
		A column renderer for values that can be very long (a fact value can be 64 KB).  Shows
		the first `max` characters with a link that shows the rest in place.  Give the column
		className 'text-break' as well, so long words wrap instead of widening the table.
	*/
	window.ledgerLongText = function (max) {
		max = max || 300;
		return function (d, t) {
			if (t !== 'display') {
				return d;
			}
			var s = (d === null || d === undefined ? '' : String(d));
			if (s.length <= max) {
				return window.ledgerEscape(s);
			}
			return window.ledgerEscape(s.substring(0, max)) + '&hellip; <a href="#" class="ledger-more">show all (' + s.length.toLocaleString() + ' characters)</a>';
		};
	};

	$(document).on('click', '.ledger-more', function (event) {
		event.preventDefault();
		var td = $(this).closest('td');
		var full = td.closest('table').DataTable().cell(td).data();
		td.text(full === null || full === undefined ? '' : String(full));
	});

	/*
		Links and buttons that delete something ask first
	*/
	$(document).on('click', '.dltlink', function (event) {
		if (!window.confirm($(this).data('confirm') || 'Are you sure you want to delete this?')) {
			event.preventDefault();
			event.stopImmediatePropagation();
		}
	});

	$(function () {
		window.ledgerSelect();
		window.ledgerFilterBar();
		window.ledgerSavedFilters();
	});
})(jQuery);
