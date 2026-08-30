(function () {
  'use strict';

  var contentInput = document.getElementById('input-content');
  var titleInput = document.getElementById('input-title');
  var descInput = document.getElementById('input-description');
  var previewTarget = document.getElementById('preview-target');
  var btnClean = document.getElementById('btn-clean-symbols');

  var scoreFlesch = document.getElementById('score-flesch');
  var scoreGrade = document.getElementById('score-grade');
  var scoreWps = document.getElementById('score-wps');
  var scoreWords = document.getElementById('score-words');
  var scoreSymbols = document.getElementById('score-symbols');

  if (!contentInput || !previewTarget) {
    return;
  }

  var smartCharPatterns = [
    { regex: /\u2014/g, rep: '-', name: 'Em Dash' },
    { regex: /\u2013/g, rep: '-', name: 'En Dash' },
    { regex: /\u2018|\u2019/g, rep: "'", name: 'Curly Single Quote' },
    { regex: /\u201C|\u201D/g, rep: '"', name: 'Curly Double Quote' },
    { regex: /\u00A0/g, rep: ' ', name: 'Non-Breaking Space' },
  ];

  function countSyllables(word) {
    word = word.toLowerCase().replace(/[^a-z]/g, '');
    if (!word) return 0;
    if (word.length <= 3) return 1;
    word = word.replace(/(?:[^laeiouy]|ed|es|e)$/, '');
    word = word.replace(/^y/, '');
    var matches = word.match(/[aeiouy]{1,2}/g);
    return matches ? Math.max(1, matches.length) : 1;
  }

  function parseMarkdown(md) {
    var html = md
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/^### (.*$)/gim, '<h3>$1</h3>')
      .replace(/^## (.*$)/gim, '<h2>$1</h2>')
      .replace(/^# (.*$)/gim, '<h1>$1</h1>')
      .replace(/^\> (.*$)/gim, '<blockquote>$1</blockquote>')
      .replace(/\*\*(.*)\*\*/gim, '<strong>$1</strong>')
      .replace(/\*(.*)\*/gim, '<em>$1</em>')
      .replace(/`([^`]+)`/gim, '<code>$1</code>')
      .replace(/\{\{\s*price\(["']([a-z0-9-]+)["']\)\s*\}\}/gim, '<div class="product-price-badge" style="display:inline-block;padding:4px 8px;background:#e0f2fe;color:#0369a1;border-radius:4px;font-weight:600;font-size:0.8rem;">[WIDGET CENY: $1]</div>')
      .replace(/^\s*-\s+(.*$)/gim, '<li>$1</li>')
      .replace(/\n\n+/g, '</p><p>');

    return '<p>' + html + '</p>';
  }

  function updateAnalysis() {
    var rawText = (titleInput ? titleInput.value : '') + ' '
                + (descInput ? descInput.value : '') + ' '
                + contentInput.value;

    var symbolCount = 0;
    for (var i = 0; i < smartCharPatterns.length; i++) {
      var found = rawText.match(smartCharPatterns[i].regex);
      if (found) {
        symbolCount += found.length;
      }
    }

    if (symbolCount === 0) {
      scoreSymbols.textContent = '0 (Czysto)';
      scoreSymbols.className = 'score-val score-good';
    } else {
      scoreSymbols.textContent = symbolCount + ' znaków AI';
      scoreSymbols.className = 'score-val score-bad';
    }

    var cleanText = contentInput.value
      .replace(/`[^`]+`/g, ' ')
      .replace(/\{\{[^}]+\}\}/g, ' ')
      .replace(/[^a-zA-Z0-9\s'-]/g, ' ')
      .replace(/\s+/g, ' ')
      .trim();

    var sentences = cleanText.split(/[.!?]+/).filter(function (s) { return s.trim().length > 0; });
    var words = cleanText.match(/\b[A-Za-z0-9'-]+\b/g) || [];

    var numSentences = Math.max(1, sentences.length);
    var numWords = Math.max(1, words.length);

    var syllables = 0;
    for (var j = 0; j < words.length; j++) {
      syllables += countSyllables(words[j]);
    }

    var wps = numWords / numSentences;
    var spw = syllables / numWords;

    var flesch = 206.835 - (1.015 * wps) - (84.6 * spw);
    var grade = (0.39 * wps) + (11.8 * spw) - 15.59;

    scoreWords.textContent = words.length.toString();
    scoreWps.textContent = wps.toFixed(1);
    scoreGrade.textContent = Math.max(1, grade).toFixed(1);

    var roundedFlesch = Math.min(100, Math.max(0, flesch)).toFixed(1);
    scoreFlesch.textContent = roundedFlesch;

    if (flesch >= 65) {
      scoreFlesch.className = 'score-val score-good';
    } else if (flesch >= 50) {
      scoreFlesch.className = 'score-val score-warn';
    } else {
      scoreFlesch.className = 'score-val score-bad';
    }

    previewTarget.innerHTML = parseMarkdown(contentInput.value);
  }

  function cleanAllFields() {
    function clean(str) {
      var res = str;
      for (var i = 0; i < smartCharPatterns.length; i++) {
        res = res.replace(smartCharPatterns[i].regex, smartCharPatterns[i].rep);
      }
      return res;
    }

    if (titleInput) titleInput.value = clean(titleInput.value);
    if (descInput) descInput.value = clean(descInput.value);
    contentInput.value = clean(contentInput.value);

    updateAnalysis();
  }

  if (btnClean) {
    btnClean.addEventListener('click', cleanAllFields);
  }

  contentInput.addEventListener('input', updateAnalysis);
  if (titleInput) titleInput.addEventListener('input', updateAnalysis);
  if (descInput) descInput.addEventListener('input', updateAnalysis);

  // Toolbar buttons
  var toolbarButtons = document.querySelectorAll('.editor-toolbar button[data-tag]');
  toolbarButtons.forEach(function (btn) {
    btn.addEventListener('click', function () {
      var tag = btn.getAttribute('data-tag');
      var start = contentInput.selectionStart;
      var end = contentInput.selectionEnd;
      var selected = contentInput.value.substring(start, end);
      var replacement = '';

      switch (tag) {
        case 'h2':
          replacement = '\n## ' + (selected || 'Śródtytuł') + '\n';
          break;
        case 'h3':
          replacement = '\n### ' + (selected || 'Podrozdział') + '\n';
          break;
        case 'bold':
          replacement = '**' + (selected || 'pogrubiony tekst') + '**';
          break;
        case 'code':
          replacement = '`' + (selected || 'kod') + '`';
          break;
        case 'price':
          replacement = '{{ price("' + (selected || 'macbook-air-m1-8gb') + '") }}';
          break;
        case 'list':
          replacement = '\n- Element 1\n- Element 2\n- Element 3\n';
          break;
      }

      contentInput.setRangeText(replacement, start, end, 'end');
      contentInput.focus();
      updateAnalysis();
    });
  });

  updateAnalysis();
})();
