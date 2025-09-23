(function (global) {
  'use strict';

  var AppUIHelpers = {};

  // Dynamic list manager: clone <template> rows, delegate remove
  AppUIHelpers.initDynamicList = function (opts) {
    var addBtn = document.getElementById(opts.addBtnId);
    var tpl = document.getElementById(opts.tplId);
    var list = document.getElementById(opts.listId);

    function addRow(data) {
      if (!tpl || !list) return null;
      var node = tpl.content ? tpl.content.cloneNode(true) : tpl.cloneNode(true);
      // optionally fill inputs if data provided (name,peso)
      if (data && data.name || data && data.peso) {
        var container = node.querySelector ? node.querySelector('.js-line-row') : null;
        if (container) {
          var nameEl = container.querySelector('input[name="salida_nombre[]"]');
          var pesoEl = container.querySelector('input[name="salida_peso[]"]');
          if (nameEl && data.name !== undefined) nameEl.value = data.name;
          if (pesoEl && data.peso !== undefined) pesoEl.value = data.peso;
        }
      }
      list.appendChild(node);
      if (typeof opts.onAdded === 'function') opts.onAdded();
      return list.lastElementChild;
    }

    // delegate remove
    if (list) {
      list.addEventListener('click', function (ev) {
        var btn = ev.target.closest && ev.target.closest('.js-remove-row');
        if (!btn) return;
        var row = btn.closest && btn.closest('.js-line-row');
        row && row.remove();
        if (typeof opts.onRemoved === 'function') opts.onRemoved();
      });
    }

    if (addBtn) {
      addBtn.addEventListener('click', function () {
        addRow(opts.defaultData || {});
      });
    }

    return {
      addRow: addRow,
      root: list
    };
  };

  // Bind a text input to filter a select's options by option text (simple client-side)
  AppUIHelpers.bindFilterInput = function (cfg) {
    var input = document.getElementById(cfg.inputId);
    var select = document.getElementById(cfg.selectId);
    if (!input || !select) return;

    function filter(qRaw) {
      var q = String(qRaw || '').trim().toLowerCase();
      Array.from(select.options).forEach(function (opt) {
        if (opt.value === '') { opt.hidden = false; return; }
        var txt = (opt.textContent || opt.innerText || '').toLowerCase();
        opt.hidden = q !== '' && txt.indexOf(q) === -1;
      });
      // select best match
      var exact = Array.from(select.options).find(function (o) { return ((o.textContent||o.innerText)||'').toLowerCase() === q; });
      if (exact) { select.value = exact.value; select.dispatchEvent(new Event('change', { bubbles: true })); return; }
      if (q === '') return;
      var first = Array.from(select.options).find(function (o) { return !o.hidden && o.value !== ''; });
      if (first) { select.value = first.value; select.dispatchEvent(new Event('change', { bubbles: true })); }
    }

    input.addEventListener('input', function () { filter(this.value); });
    input.addEventListener('keydown', function (ev) {
      if (ev.key === 'ArrowDown') { ev.preventDefault(); select.focus(); }
    });

    return { filter: filter, input: input, select: select };
  };
  // Generic calculator: sum inputs matching selector and update target
  AppUIHelpers.initSumCalculator = function (cfg) {
    var pesoInicial = document.getElementById(cfg.pesoInicialId);
    var restosEl = document.getElementById(cfg.restosId);
    var rowInputSelector = cfg.rowInputSelector || 'input[name="salida_peso[]"]';
    var decimals = typeof cfg.decimals === 'number' ? cfg.decimals : 3;

    function calc() {
      var inicial = parseFloat((pesoInicial && pesoInicial.value) || 0) || 0;
      var sum = 0;
      Array.from(document.querySelectorAll(rowInputSelector)).forEach(function (el) {
        sum += parseFloat(el.value) || 0;
      });
      var restos = inicial - sum;
      if (restosEl) restosEl.value = (Math.round(restos * Math.pow(10, decimals)) / Math.pow(10, decimals)).toFixed(decimals);
    }

    // delegate inputs (works for dynamic rows too)
    document.addEventListener('input', function (ev) {
      if (ev.target && (ev.target.matches && (ev.target.matches(rowInputSelector) || ev.target === pesoInicial))) {
        calc();
      }
    });

    // initial calc
    calc();

    return { calc: calc };
  };

  /**
   * Sincroniza y muestra las indicaciones/alérgenos de los ingredientes que están
   * presentes en una lista dinámica (por ejemplo lista de filas con inputs name="ingredientes[]").
   *
   * cfg:
   *  - containerId: id del elemento donde renderizar la información (HTML)
   *  - listId: id del contenedor donde están las filas (tbody o div)
   *  - selectId: id del <select> oculto que contiene options con data-indic / data-alergenos
   *
   * Comportamiento:
   *  - Detecta los inputs name="ingredientes[]" o elementos .js-ing-id dentro del listId.
   *  - Busca las opciones correspondientes en selectId por value (id).
   *  - Renderiza una lista ordenada con nombre, indicaciones y alérgenos.
   *  - Observa cambios en el listId y actualiza automáticamente (MutationObserver si está disponible).
   */
  AppUIHelpers.syncIndicacionesForList = function (cfg) {
    var container = document.getElementById(cfg.containerId);
    var listRoot = document.getElementById(cfg.listId);
    var select = document.getElementById(cfg.selectId);
    if (!container || !listRoot || !select) {
      return {
        render: function () {}
      };
    }

    function escapeHtml(s) {
      return String(s || '').replace(/[&<>"']/g, function (m) {
        return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m];
      });
    }

    function gatherIds() {
      var ids = [];
      // prefer hidden inputs with name="ingredientes[]"
      var inputs = listRoot.querySelectorAll('input[name="ingredientes[]"], input.js-ing-id');
      inputs.forEach(function (inp) {
        var v = (inp.value || '').toString().trim();
        if (v !== '') ids.push(v);
      });
      return ids;
    }

    function render() {
      var ids = gatherIds();
      if (ids.length === 0) {
        container.innerHTML = '';
        return;
      }
      var parts = [];
      ids.forEach(function (id) {
        var opt = select.querySelector('option[value="' + id + '"]');
        if (!opt) return;
        var name = opt.dataset && opt.dataset.name ? opt.dataset.name : (opt.textContent || '').trim();
        var indic = opt.dataset && opt.dataset.indic ? opt.dataset.indic : '';
        var algs = opt.dataset && opt.dataset.alergenos ? opt.dataset.alergenos : '';
        var html = '<div class="mb-2 p-2 bg-gray-50 border rounded">';
        html += '<div class="font-medium">' + escapeHtml(name) + '</div>';
        if (indic) html += '<div class="text-xs text-gray-700 mt-1"><strong>Indicaciones:</strong> ' + escapeHtml(indic) + '</div>';
        if (algs) html += '<div class="text-xs text-gray-700 mt-1"><strong>Alérgenos:</strong> ' + escapeHtml(algs) + '</div>';
        html += '</div>';
        parts.push(html);
      });
      container.innerHTML = parts.join('');
    }

    // observe mutations on listRoot to re-render automatically
    if (window.MutationObserver) {
      var mo = new MutationObserver(function () {
        render();
      });
      mo.observe(listRoot, { childList: true, subtree: true, attributes: true, attributeFilter: ['value'] });
    } else {
      // fallback: listen click and input events that likely change list
      listRoot.addEventListener('click', render);
      listRoot.addEventListener('input', render);
    }

    // run initial render
    render();

    return { render: render };
  };

  global.AppUIHelpers = AppUIHelpers;
})(window);