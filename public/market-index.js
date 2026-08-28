document.querySelectorAll('[data-market-family]').forEach((family) => {
  const selects = [...family.querySelectorAll('[data-market-spec-select]')];
  const configurations = JSON.parse(family.dataset.marketConfigurations || '[]');
  const link = family.querySelector('[data-market-link]');
  const price = family.querySelector('[data-market-price]');
  const note = family.querySelector('[data-market-note]');
  if (!selects.length || !configurations.length || !link || !price || !note) return;

  const selectedSpecs = () => Object.fromEntries(selects.map((select) => [select.dataset.marketSpecSelect, select.value]));
  const matches = (configuration, specs, ignoredKey = null) => Object.entries(specs).every(([key, value]) => key === ignoredKey || configuration.specs[key] === value);

  const sync = (changedKey = null) => {
    let specs = selectedSpecs();

    selects.forEach((select) => {
      [...select.options].forEach((option) => {
        option.disabled = !configurations.some((configuration) => matches(configuration, { ...specs, [select.dataset.marketSpecSelect]: option.value }, select.dataset.marketSpecSelect));
      });
      if (select.selectedOptions[0]?.disabled) {
        const firstAvailable = [...select.options].find((option) => !option.disabled);
        if (firstAvailable) select.value = firstAvailable.value;
      }
    });
    specs = selectedSpecs();

    const selected = configurations.find((configuration) => matches(configuration, specs))
      || configurations.find((configuration) => matches(configuration, specs, changedKey))
      || configurations[0];
    if (!selected) return;

    selects.forEach((select) => {
      const key = select.dataset.marketSpecSelect;
      if (selected.specs[key] !== undefined) select.value = selected.specs[key];
    });
    link.href = selected.url;
    price.textContent = selected.price || '';
    note.textContent = selected.note || '';
  };
  selects.forEach((select) => select.addEventListener('change', () => sync(select.dataset.marketSpecSelect)));
  sync();
});

// Hero Search Controller
(function () {
  const searchRoot = document.querySelector('[data-hero-search]');
  if (!searchRoot) return;

  const input = searchRoot.querySelector('.market-hero-input');
  const clearBtn = searchRoot.querySelector('.search-clear-btn');
  const dropdown = searchRoot.querySelector('.market-search-dropdown');
  const chips = searchRoot.querySelectorAll('[data-search-chip]');
  if (!input || !dropdown) return;

  let products = [];
  try {
    products = JSON.parse(searchRoot.dataset.products || '[]');
  } catch (_) {
    products = [];
  }

  let activeIndex = -1;
  let matches = [];

  function normalize(str) {
    return (str || '')
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/[^a-z0-9]/g, ' ')
      .trim();
  }

  function scoreProduct(product, queryTokens) {
    const text = normalize(product.search_text);
    let matched = 0;
    for (const token of queryTokens) {
      if (text.includes(token)) {
        matched++;
      }
    }
    return matched === queryTokens.length ? 1 : 0;
  }

  function search(query) {
    const cleanQuery = query.trim();
    if (!cleanQuery) {
      dropdown.hidden = true;
      dropdown.innerHTML = '';
      if (clearBtn) clearBtn.hidden = true;
      input.setAttribute('aria-expanded', 'false');
      activeIndex = -1;
      matches = [];
      return;
    }

    if (clearBtn) clearBtn.hidden = false;

    const queryTokens = normalize(cleanQuery).split(/\s+/).filter(Boolean);
    matches = [];

    for (const product of products) {
      if (scoreProduct(product, queryTokens) > 0) {
        matches.push(product);
      }
    }

    // Sort: prioritize shorter names / closer matches, limit to 8
    matches.sort((a, b) => a.name.length - b.name.length);
    const topMatches = matches.slice(0, 8);

    if (topMatches.length === 0) {
      const promptText = dropdown.dataset.requestPrompt || 'Nie znaleziono? Zgłoś model do wyceny';
      const ctaText = dropdown.dataset.requestCta || 'Zgłoś ten model →';
      dropdown.innerHTML = `
        <div class="search-empty-state">
          <p><strong>${dropdown.dataset.noResults || 'Brak wyników'}</strong> dla „<em>${escapeHtml(cleanQuery)}</em>”</p>
          <button type="button" class="search-request-cta-btn" data-prefill="${escapeHtml(cleanQuery)}">
            ${escapeHtml(promptText)} (${escapeHtml(ctaText)})
          </button>
        </div>
      `;
      dropdown.hidden = false;
      input.setAttribute('aria-expanded', 'true');
      activeIndex = -1;
      return;
    }

    dropdown.innerHTML = topMatches.map((item, idx) => `
      <a href="${item.url}"
         class="search-result-item"
         role="option"
         data-index="${idx}"
         id="search-opt-${idx}">
        <span class="search-item-category">${escapeHtml(item.category_label || item.category)}</span>
        <strong class="search-item-name">${escapeHtml(item.name)}</strong>
        <span class="search-item-price">${escapeHtml(item.price)}</span>
      </a>
    `).join('');

    dropdown.hidden = false;
    input.setAttribute('aria-expanded', 'true');
    activeIndex = -1;
  }

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function highlightActive() {
    const items = dropdown.querySelectorAll('.search-result-item');
    items.forEach((item, idx) => {
      const isActive = idx === activeIndex;
      item.classList.toggle('active', isActive);
      item.setAttribute('aria-selected', isActive ? 'true' : 'false');
      if (isActive) {
        item.scrollIntoView({ block: 'nearest' });
      }
    });
  }

  input.addEventListener('input', () => {
    search(input.value);
  });

  input.addEventListener('focus', () => {
    if (input.value.trim()) {
      search(input.value);
    }
  });

  input.addEventListener('keydown', (e) => {
    const items = dropdown.querySelectorAll('.search-result-item');
    if (items.length === 0) return;

    if (e.key === 'ArrowDown') {
      e.preventDefault();
      activeIndex = (activeIndex + 1) % items.length;
      highlightActive();
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      activeIndex = (activeIndex - 1 + items.length) % items.length;
      highlightActive();
    } else if (e.key === 'Enter') {
      if (activeIndex >= 0 && items[activeIndex]) {
        e.preventDefault();
        items[activeIndex].click();
      } else if (items.length > 0) {
        e.preventDefault();
        items[0].click();
      }
    } else if (e.key === 'Escape') {
      dropdown.hidden = true;
      input.setAttribute('aria-expanded', 'false');
      activeIndex = -1;
    }
  });

  if (clearBtn) {
    clearBtn.addEventListener('click', () => {
      input.value = '';
      input.focus();
      search('');
    });
  }

  chips.forEach(chip => {
    chip.addEventListener('click', () => {
      const term = chip.dataset.searchChip || chip.textContent;
      input.value = term;
      input.focus();
      search(term);
    });
  });

  dropdown.addEventListener('click', (e) => {
    const reqBtn = e.target.closest('.search-request-cta-btn');
    if (reqBtn) {
      e.preventDefault();
      const prefill = reqBtn.dataset.prefill || input.value;
      const requestSection = document.querySelector('.market-request');
      const reqInput = document.getElementById('requested-product');
      if (requestSection) {
        requestSection.scrollIntoView({ behavior: 'smooth' });
      }
      if (reqInput) {
        reqInput.value = prefill;
        reqInput.focus();
      }
      dropdown.hidden = true;
      input.setAttribute('aria-expanded', 'false');
    }
  });

  document.addEventListener('click', (e) => {
    if (!searchRoot.contains(e.target)) {
      dropdown.hidden = true;
      input.setAttribute('aria-expanded', 'false');
      activeIndex = -1;
    }
  });
})();

document.querySelectorAll('[data-product-request-form]').forEach(form => {
  form.addEventListener('submit', async event => {
    event.preventDefault();
    const button = form.querySelector('button[type="submit"]');
    const status = form.querySelector('[data-contact-status]');
    button.disabled = true;
    status.textContent = form.dataset.saving;
    try {
      const response = await fetch(form.action, {method: 'POST', body: new FormData(form), headers: {'Accept': 'application/json'}});
      const result = await response.json();
      status.textContent = result.message || result.error || form.dataset.fallback;
      status.classList.toggle('lead-success', response.ok);
      if (response.ok) form.reset();
      button.disabled = false;
    } catch (_) {
      status.textContent = form.dataset.fallback;
      button.disabled = false;
    }
  });
});
