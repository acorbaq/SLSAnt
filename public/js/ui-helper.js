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

  global.AppUIHelpers = AppUIHelpers;
})(window);