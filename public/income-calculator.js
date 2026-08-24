(function () {
  const root = document.querySelector('[data-income-calculator]');
  if (!root || !window.PolandIncomeMath) return;
  const locale = root.dataset.locale === 'pl' ? 'pl-PL' : 'en-GB';
  const money = value => new Intl.NumberFormat(locale, { style: 'currency', currency: 'PLN' }).format(value);
  const controls = Object.fromEntries([...root.querySelectorAll('[data-control]')].map(node => [node.dataset.control, node]));
  const output = root.querySelector('[data-results]');
  const labels = JSON.parse(root.dataset.labels);
  const amountLabel = root.querySelector('[data-amount-label]');
  const amountHelp = root.querySelector('[data-amount-help]');
  const modeButtons = root.querySelectorAll('[data-mode-btn]');

  let currentMode = 'budget';

  function setMode(mode) {
    if (mode === currentMode) return;
    const currentVal = Number(controls.amount.value.replace(',', '.')) || 0;
    if (mode === 'uop_gross') {
      controls.amount.value = Math.round(currentVal / 1.2048);
      if (amountLabel) amountLabel.textContent = labels.uop_gross;
      if (amountHelp) amountHelp.textContent = labels.uop_gross_help;
    } else {
      controls.amount.value = Math.round(currentVal * 1.2048);
      if (amountLabel) amountLabel.textContent = labels.budget_label;
      if (amountHelp) amountHelp.textContent = labels.budget_help;
    }
    currentMode = mode;
    modeButtons.forEach(btn => {
      const active = btn.dataset.modeBtn === mode;
      btn.classList.toggle('active', active);
      btn.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
    render();
  }

  modeButtons.forEach(btn => {
    btn.addEventListener('click', () => setMode(btn.dataset.modeBtn));
  });

  function render() {
    const rawVal = controls.amount ? controls.amount.value.replace(',', '.') : '12000';
    const results = window.PolandIncomeMath.compare({
      inputMode: currentMode,
      budget: currentMode === 'budget' ? rawVal : undefined,
      grossUop: currentMode === 'uop_gross' ? rawVal : undefined,
      studentUnder26: controls.student ? controls.student.checked : false,
      costs: controls.costs ? controls.costs.value.replace(',', '.') : 0,
      llcCosts: controls.llcCosts ? controls.llcCosts.value.replace(',', '.') : 600,
      taxation: controls.taxation ? controls.taxation.value : 'linear',
      zus: controls.zus ? controls.zus.value : 'standard',
      lumpRate: controls.lumpRate ? controls.lumpRate.value : 12,
    });
    if (controls.lumpRate && controls.taxation) {
      const lumpField = controls.lumpRate.closest('.field');
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

  root.addEventListener('input', render);
  root.addEventListener('change', render);
  render();
})();
