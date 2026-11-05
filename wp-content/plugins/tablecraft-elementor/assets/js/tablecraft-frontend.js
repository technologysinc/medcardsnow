(function () {
  'use strict';

  function toComparable(value) {
    var text = String(value || '').trim();
    var cleaned = text.replace(/[\s,$€£¥%]/g, '').replace(/,/g, '');
    if (cleaned !== '' && /^-?\d+(\.\d+)?$/.test(cleaned)) {
      return { type: 'number', value: Number(cleaned) };
    }
    var dateValue = Date.parse(text);
    if (text && !Number.isNaN(dateValue) && /[-/]/.test(text)) {
      return { type: 'date', value: dateValue };
    }
    return { type: 'text', value: text.toLocaleLowerCase() };
  }

  function initTable(root) {
    if (!root || root.dataset.tcInitialized === '1') return;
    root.dataset.tcInitialized = '1';

    var table = root.querySelector('.tc-table');
    var body = table ? table.querySelector('tbody') : null;
    if (!table || !body) return;

    var search = root.querySelector('.tc-search');
    var count = root.querySelector('.tc-count');
    var pagination = root.querySelector('.tc-pagination');
    var headers = Array.prototype.slice.call(table.querySelectorAll('thead th'));
    var allRows = Array.prototype.slice.call(body.querySelectorAll('tr:not(.tc-empty-row)'));
    var pageSize = Math.max(1, parseInt(root.dataset.pageSize || '10', 10));
    var currentPage = 1;
    var sortState = { index: -1, direction: 1 };
    var filteredRows = allRows.slice();
    var emptyText = root.dataset.empty || 'No records found.';

    headers.forEach(function (header, index) {
      if (header.dataset.sortable !== '1') return;
      header.tabIndex = 0;
      header.setAttribute('aria-sort', 'none');
      function sort() {
        if (sortState.index === index) {
          sortState.direction *= -1;
        } else {
          sortState.index = index;
          sortState.direction = 1;
        }
        headers.forEach(function (item, itemIndex) {
          var active = itemIndex === index;
          item.setAttribute('aria-sort', active ? (sortState.direction === 1 ? 'ascending' : 'descending') : 'none');
          var indicator = item.querySelector('.tc-sort-indicator');
          if (indicator) indicator.textContent = active ? (sortState.direction === 1 ? '↑' : '↓') : '↕';
        });
        currentPage = 1;
        render();
      }
      header.addEventListener('click', sort);
      header.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault();
          sort();
        }
      });
    });

    function getFilteredRows() {
      var query = search ? search.value.trim().toLocaleLowerCase() : '';
      var result = !query ? allRows.slice() : allRows.filter(function (row) {
        return row.textContent.toLocaleLowerCase().indexOf(query) !== -1;
      });
      if (sortState.index >= 0) {
        result.sort(function (a, b) {
          var aCell = a.children[sortState.index] ? a.children[sortState.index].textContent : '';
          var bCell = b.children[sortState.index] ? b.children[sortState.index].textContent : '';
          var av = toComparable(aCell);
          var bv = toComparable(bCell);
          if (av.type === bv.type && av.value < bv.value) return -1 * sortState.direction;
          if (av.type === bv.type && av.value > bv.value) return 1 * sortState.direction;
          return String(aCell).localeCompare(String(bCell), undefined, { numeric: true, sensitivity: 'base' }) * sortState.direction;
        });
      }
      return result;
    }

    function renderPagination(totalPages) {
      if (!pagination) return;
      pagination.innerHTML = '';
      if (root.dataset.pagination !== '1' || totalPages <= 1) return;

      function button(label, page, disabled, current) {
        var control = document.createElement('button');
        control.type = 'button';
        control.className = 'tc-page-btn';
        control.textContent = label;
        if (label === '‹') control.setAttribute('aria-label', 'Previous page');
        else if (label === '›') control.setAttribute('aria-label', 'Next page');
        else control.setAttribute('aria-label', 'Page ' + label);
        control.disabled = !!disabled;
        if (current) control.setAttribute('aria-current', 'page');
        control.addEventListener('click', function () {
          currentPage = page;
          render();
        });
        pagination.appendChild(control);
      }

      button('‹', Math.max(1, currentPage - 1), currentPage === 1, false);
      var start = Math.max(1, currentPage - 2);
      var end = Math.min(totalPages, start + 4);
      start = Math.max(1, end - 4);
      for (var page = start; page <= end; page += 1) {
        button(String(page), page, false, page === currentPage);
      }
      button('›', Math.min(totalPages, currentPage + 1), currentPage === totalPages, false);
    }

    function render() {
      filteredRows = getFilteredRows();
      var total = filteredRows.length;
      var usePagination = root.dataset.pagination === '1';
      var totalPages = usePagination ? Math.max(1, Math.ceil(total / pageSize)) : 1;
      currentPage = Math.min(currentPage, totalPages);
      var startIndex = usePagination ? (currentPage - 1) * pageSize : 0;
      var endIndex = usePagination ? Math.min(startIndex + pageSize, total) : total;
      var visible = filteredRows.slice(startIndex, endIndex);

      allRows.forEach(function (row) { row.hidden = visible.indexOf(row) === -1; });
      // Move matching rows into their current sort order; hidden rows do not affect visual order.
      filteredRows.forEach(function (row) { body.appendChild(row); });

      var emptyRow = body.querySelector('.tc-empty-row');
      if (total === 0) {
        if (!emptyRow) {
          emptyRow = document.createElement('tr');
          emptyRow.className = 'tc-empty-row';
          var cell = document.createElement('td');
          cell.colSpan = Math.max(1, headers.length);
          emptyRow.appendChild(cell);
          body.appendChild(emptyRow);
        }
        emptyRow.hidden = false;
        emptyRow.querySelector('td').textContent = search && search.value.trim() ? 'No matching records found.' : emptyText;
      } else if (emptyRow) {
        emptyRow.hidden = true;
      }

      if (count && root.dataset.count === '1') {
        count.textContent = total ? 'Showing ' + (startIndex + 1) + '–' + endIndex + ' of ' + total + ' entries' : '0 entries';
      } else if (count) {
        count.textContent = '';
      }
      renderPagination(totalPages);
    }

    if (search && root.dataset.search === '1') {
      search.addEventListener('input', function () { currentPage = 1; render(); });
    }
    render();
  }

  function initAll(scope) {
    var context = scope && scope.querySelectorAll ? scope : document;
    if (context.matches && context.matches('[data-tc-table]')) initTable(context);
    Array.prototype.forEach.call(context.querySelectorAll('[data-tc-table]'), initTable);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { initAll(document); });
  } else {
    initAll(document);
  }

  window.addEventListener('elementor/frontend/init', function () {
    if (window.elementorFrontend && window.elementorFrontend.hooks) {
      window.elementorFrontend.hooks.addAction('frontend/element_ready/tablecraft-table.default', function ($scope) {
        initAll($scope && $scope[0] ? $scope[0] : document);
      });
    }
  });
})();
