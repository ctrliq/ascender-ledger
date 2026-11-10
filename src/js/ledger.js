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
		the browser to that input's data-url with the chosen value appended.
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
			var input = $('.filter-input[data-filter="' + f + '"]').find('select, input').first();
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
		$('.filter-input input[type="text"]').on('keydown', function (e) {
			if (e.key === 'Enter') {
				e.preventDefault();
				apply();
			}
		});
		show();
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
		A DataTable whose rows come from the server a page at a time.  Every column is shown
		as text unless it has its own render function.  The server stops counting rows at a
		cap, so the info line says "more than" rather than giving a wrong total.
	*/
	window.ledgerTable = function (selector, options) {
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
	});
})(jQuery);
