/* ================================================================
   js/ai.js  —  CSMS AI Feature Controller
   Three features:
   1. Smart Assistant panel (chat)
   2. Report Generator (foreman dashboard)
   3. Safety Risk Predictor (safety + site manager dashboards)
   ================================================================ */

/* ================================================================
   1. SMART ASSISTANT PANEL
   ================================================================ */

let aiPanelOpen = false;

function toggleAIPanel() {
  const panel = document.getElementById('aiPanel');
  if (!panel) return;
  aiPanelOpen = !aiPanelOpen;
  panel.classList.toggle('open', aiPanelOpen);

  // Close notifications drawer if open
  const nd = document.getElementById('notifDrawer');
  if (nd) nd.classList.remove('open');

  // Load greeting message on first open
  if (aiPanelOpen && panel.dataset.greeted !== 'true') {
    panel.dataset.greeted = 'true';
    const name = panel.dataset.name   || 'there';
    const role = panel.dataset.role   || 'user';
    appendAIMessage(
      `Hello ${name}! I'm your CSMS AI Assistant.\n\nI can help you with:\n• Summarising your site data\n• Answering questions about reports, attendance, or safety\n• Drafting messages or summaries\n• Explaining anything in the system\n\nWhat can I help you with today?`,
      'assistant'
    );
  }
}

function closeAIPanel() {
  const panel = document.getElementById('aiPanel');
  if (panel) { panel.classList.remove('open'); aiPanelOpen = false; }
}

/* Send a message to the AI assistant */
async function sendAIMessage() {
  const input   = document.getElementById('aiInput');
  const sendBtn = document.getElementById('aiSendBtn');
  const message = input.value.trim();
  if (!message) return;

  // Show user's message
  appendAIMessage(message, 'user');
  input.value = '';
  input.style.height = 'auto';

  // Disable button + show typing indicator
  sendBtn.disabled = true;
  const typingId = appendTyping();

  try {
    const data = new FormData();
    data.append('message', message);

    const res   = await fetch('../php/ai_chat.php', { method:'POST', body:data });
    const json  = await res.json();

    removeTyping(typingId);
    sendBtn.disabled = false;

    if (json.success) {
      appendAIMessage(json.reply, 'assistant');
    } else if (json.error === 'offline') {
      appendAIMessage(
        '⚠ ' + json.message + '\n\nAll other system features (reports, attendance, messaging) continue working normally.',
        'offline'
      );
    } else if (json.error === 'api_key_not_set') {
      appendAIMessage(
        'ⓘ ' + json.message,
        'system'
      );
    } else {
      appendAIMessage('Sorry, something went wrong: ' + (json.message || 'Unknown error'), 'system');
    }
  } catch (err) {
    removeTyping(typingId);
    sendBtn.disabled = false;
    appendAIMessage(
      '⚠ AI assistant requires an internet connection. The rest of the system continues working normally.',
      'offline'
    );
  }
}

/* Use a quick-prompt chip */
function usePrompt(text) {
  const input = document.getElementById('aiInput');
  if (input) {
    input.value = text;
    input.focus();
  }
}

/* Append a message bubble to the chat */
function appendAIMessage(text, type) {
  const container = document.getElementById('aiMessages');
  if (!container) return;

  const div = document.createElement('div');
  div.className = 'ai-msg ' + type;
  div.textContent = text;
  container.appendChild(div);
  container.scrollTop = container.scrollHeight;
}

/* Show animated typing dots, return unique id */
function appendTyping() {
  const container = document.getElementById('aiMessages');
  if (!container) return null;

  const id  = 'typing-' + Date.now();
  const div = document.createElement('div');
  div.className = 'ai-typing';
  div.id = id;
  div.innerHTML = '<span></span><span></span><span></span>';
  container.appendChild(div);
  container.scrollTop = container.scrollHeight;
  return id;
}

function removeTyping(id) {
  if (id) { const el = document.getElementById(id); if (el) el.remove(); }
}

/* Auto-resize textarea as user types */
document.addEventListener('DOMContentLoaded', function() {
  const input = document.getElementById('aiInput');
  if (!input) return;

  input.addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 120) + 'px';
  });

  input.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      sendAIMessage();
    }
  });
});

/* Close AI panel when clicking outside */
document.addEventListener('click', function(e) {
  const panel  = document.getElementById('aiPanel');
  const button = document.getElementById('aiPanelBtn');
  if (panel && aiPanelOpen && !panel.contains(e.target) && button && !button.contains(e.target)) {
    closeAIPanel();
  }
});

/* ================================================================
   2. AI REPORT GENERATOR (Foreman dashboard)
   ================================================================ */

async function generateAIReport() {
  const btn      = document.getElementById('aiReportBtn');
  const statusEl = document.getElementById('aiReportStatus');
  const siteEl   = document.querySelector('input[name="site_name"]');
  const dateEl   = document.querySelector('input[name="report_date"]');
  const progEl   = document.querySelector('textarea[name="progress_update"]');
  const resEl    = document.querySelector('textarea[name="resource_usage"]');
  const equEl    = document.querySelector('textarea[name="equipment_needs"]');

  if (!btn || !siteEl || !progEl) return;

  const site = siteEl.value.trim();
  const date = dateEl ? dateEl.value : new Date().toISOString().slice(0,10);

  // Show loading state
  btn.disabled = true;
  btn.innerHTML = btn.innerHTML.replace(/Generate.*/,'Generating…');
  if (statusEl) { statusEl.textContent = 'AI is analysing your site data…'; statusEl.classList.add('show'); }

  // Add shimmer to textareas
  [progEl, resEl, equEl].forEach(el => { if (el) el.classList.add('ai-generating'); });

  try {
    const data = new FormData();
    data.append('site_name', site);
    data.append('date', date);

    const res  = await fetch('../php/ai_report.php', { method:'POST', body:data });
    const json = await res.json();

    [progEl, resEl, equEl].forEach(el => { if (el) el.classList.remove('ai-generating'); });
    btn.disabled = false;
    btn.innerHTML = btn.innerHTML.replace(/Generating.*/,'Generate AI Draft ✓');
    if (statusEl) statusEl.classList.remove('show');

    if (json.success) {
      if (progEl && json.progress)  { progEl.value = json.progress;  triggerInput(progEl); }
      if (resEl  && json.resources) { resEl.value  = json.resources; triggerInput(resEl); }
      if (equEl  && json.equipment) { equEl.value  = json.equipment; triggerInput(equEl); }
      toast('AI draft generated — review and edit before submitting.', 'ok');
    } else if (json.error === 'offline') {
      if (statusEl) { statusEl.textContent = '⚠ ' + json.message; statusEl.classList.add('show'); }
      setTimeout(() => { if (statusEl) statusEl.classList.remove('show'); }, 5000);
      toast('No internet — AI unavailable. Write the report manually.', 'err');
    } else {
      toast(json.message || 'AI generation failed.', 'err');
    }
  } catch(err) {
    [progEl, resEl, equEl].forEach(el => { if (el) el.classList.remove('ai-generating'); });
    btn.disabled = false;
    if (statusEl) statusEl.classList.remove('show');
    toast('⚠ AI requires an internet connection.', 'err');
  }
}

function triggerInput(el) {
  el.dispatchEvent(new Event('input', { bubbles:true }));
}

/* ================================================================
   3. SAFETY RISK PREDICTOR
   ================================================================ */

async function loadRiskPredictor(site) {
  const container = document.getElementById('riskPredictorBody');
  if (!container) return;

  // Show loading state
  container.innerHTML = '<div class="risk-loading"><div class="spinner-sm"></div> Analysing safety data…</div>';

  try {
    const url = '../php/ai_safety.php?site=' + encodeURIComponent(site);
    const res  = await fetch(url);
    const json = await res.json();

    if (!json.success) {
      container.innerHTML = '<div style="font-size:13px;color:var(--muted);">Could not load safety analysis.</div>';
      return;
    }

    renderRiskPredictor(container, json);

  } catch(err) {
    container.innerHTML = '<div style="font-size:13px;color:var(--muted);">Could not reach safety analysis service.</div>';
  }
}

function renderRiskPredictor(container, data) {
  const colorMap = { low:'#22c55e', medium:'#f59e0b', high:'#f97316', critical:'#ef4444' };
  const color    = colorMap[data.risk_level] || '#aaa';
  const score    = data.risk_score || 0;

  let recsHTML = '';
  if (data.recommendations && data.recommendations.length) {
    recsHTML = '<ul class="risk-recs">' +
      data.recommendations.map(r => `<li class="risk-rec">${r}</li>`).join('') +
      '</ul>';
  }

  const offlineNotice = !data.ai ? `
    <div class="ai-offline-notice">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
        <path d="M12 9v4m0 4v.01"/>
        <path d="M10.363 3.591l-8.106 13.534a1.914 1.914 0 0 0 1.636 2.871h16.214a1.914 1.914 0 0 0 1.636 -2.87l-8.106 -13.536a1.914 1.914 0 0 0 -3.274 0z"/>
      </svg>
      <span>AI internet unavailable — showing local rule-based analysis. Connect to the internet for AI-enhanced predictions.</span>
    </div>` : '';

  container.innerHTML = `
    <div class="risk-score-row">
      <div class="risk-score-num" style="color:${color}">${score}</div>
      <div class="risk-level-badge rl-${data.risk_level}">${data.risk_level.toUpperCase()}</div>
    </div>
    <div class="risk-meter">
      <div class="risk-meter-fill risk-${data.risk_level}" style="width:${score}%"></div>
    </div>
    <p class="risk-summary">${data.summary || ''}</p>
    ${recsHTML}
    ${offlineNotice}
    <div class="risk-ai-tag">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
        <path d="M9.5 14.5l-1.5 1.5"/>
        <path d="M14.5 14.5l1.5 1.5"/>
        <path d="M9 9h.01"/>
        <path d="M15 9h.01"/>
        <path d="M10 13a2 2 0 0 0 4 0v-3a2 2 0 0 0 -4 0v3"/>
        <path d="M6 6m-3 0a3 3 0 1 0 6 0a3 3 0 1 0 -6 0"/>
        <path d="M18 6m-3 0a3 3 0 1 0 6 0a3 3 0 1 0 -6 0"/>
        <path d="M6 9a9 9 0 0 0 12 0"/>
      </svg>
      ${data.ai ? 'AI-Enhanced Analysis by Claude' : 'Local Rule-Based Analysis'}
    </div>
  `;
}
