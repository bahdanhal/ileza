(function () {
  const root = document.querySelector('[data-income-calculator]');
  if (!root || !window.PolandIncomeMath) return;
  const locale = root.dataset.locale === 'pl' ? 'pl-PL' : 'en-GB';
  const money = value => new Intl.NumberFormat(locale, { style: 'currency', currency: 'PLN' }).format(value);
  const controls = Object.fromEntries([...root.querySelectorAll('[data-control]')].map(node => [node.dataset.control, node]));
  const output = root.querySelector('[data-results]');
  const labels = JSON.parse(root.dataset.labels);

  const EMPLOYER_COST_FACTOR = 1.2048;
  let activeSource = 'budget';

  function formatSyncValue(val) {
    if (isNaN(val) || val <= 0) return '';
    const rounded = Math.round(val * 100) / 100;
    return Number.isInteger(rounded) ? String(rounded) : String(rounded);
  }

  const budgetInput = controls.budget;
  const grossInput = controls.uopGross;

  function syncGrossFromBudget() {
    if (!budgetInput || !grossInput) return;
    const raw = budgetInput.value.trim().replace(',', '.');
    const b = parseFloat(raw);
    if (!isNaN(b) && b > 0) {
      grossInput.value = formatSyncValue(b / EMPLOYER_COST_FACTOR);
    } else if (raw === '') {
      grossInput.value = '';
    }
  }

  function syncBudgetFromGross() {
    if (!budgetInput || !grossInput) return;
    const raw = grossInput.value.trim().replace(',', '.');
    const g = parseFloat(raw);
    if (!isNaN(g) && g > 0) {
      budgetInput.value = formatSyncValue(g * EMPLOYER_COST_FACTOR);
    } else if (raw === '') {
      budgetInput.value = '';
    }
  }

  if (budgetInput && grossInput) {
    budgetInput.addEventListener('input', () => {
      activeSource = 'budget';
      syncGrossFromBudget();
      render();
    });

    grossInput.addEventListener('input', () => {
      activeSource = 'uopGross';
      syncBudgetFromGross();
      render();
    });
  }

  function render() {
    let rawVal = '12000';
    if (activeSource === 'uopGross' && grossInput) {
      rawVal = grossInput.value.trim().replace(',', '.');
    } else if (budgetInput) {
      rawVal = budgetInput.value.trim().replace(',', '.');
    }

    const numVal = parseFloat(rawVal) || 0;

    const results = window.PolandIncomeMath.compare({
      inputMode: activeSource === 'uopGross' ? 'uop_gross' : 'budget',
      budget: activeSource === 'budget' ? numVal : undefined,
      grossUop: activeSource === 'uopGross' ? numVal : undefined,
      studentUnder26: controls.student ? controls.student.checked : false,
      costs: controls.costs ? controls.costs.value.replace(',', '.') : 0,
      llcCosts: controls.llcCosts ? controls.llcCosts.value.replace(',', '.') : 600,
      taxation: controls.taxation ? controls.taxation.value : 'linear',
      zus: controls.zus ? controls.zus.value : 'standard',
      lumpRate: controls.lumpRate ? controls.lumpRate.value : 12,
    });

    if (controls.lumpRate && controls.taxation) {
      const lumpField = controls.lumpRate.closest('.field') || root.querySelector('[data-field="lumpRate"]');
      if (lumpField) lumpField.hidden = controls.taxation.value !== 'lump';
    }

    output.innerHTML = Object.entries(results).map(([type, item]) => {
      const typeLabel = labels[type] || type;
      return `<article class="result-card result-${type}">
        <div class="result-head">
          <span>${typeLabel}</span>
          <strong>${money(item.net)}</strong>
          <small>${labels.net}</small>
        </div>
        <dl>
          <div><dt>${labels.budget}</dt><dd>${money(item.cost)}</dd></div>
          <div><dt>${labels.gross}</dt><dd>${money(item.gross)}</dd></div>
          ${item.businessCosts ? `<div><dt>${labels.costs}</dt><dd>−${money(item.businessCosts)}</dd></div>` : ''}
          <div><dt>${labels.social}</dt><dd>${item.social > 0 ? `−${money(item.social)}` : money(0)}</dd></div>
          <div><dt>${labels.health}</dt><dd>${item.health > 0 ? `−${money(item.health)}` : money(0)}</dd></div>
          <div><dt>${labels.tax}</dt><dd>${item.tax > 0 ? `−${money(item.tax)}` : money(0)}</dd></div>
        </dl>
      </article>`;
    }).join('');
  }

  root.addEventListener('change', e => {
    if (e.target !== budgetInput && e.target !== grossInput) {
      render();
    }
  });

  root.addEventListener('input', e => {
    if (e.target !== budgetInput && e.target !== grossInput) {
      render();
    }
  });

  render();
})();
