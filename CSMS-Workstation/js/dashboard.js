/* ================================================================
   js/dashboard.js  —  ConstructPro v3
   ================================================================ */

/* ── SECTION NAVIGATION ── */
function initNav() {
  const links    = document.querySelectorAll('.nav-link[data-section]');
  const sections = document.querySelectorAll('.page-section');
  const topTitle = document.getElementById('topbarSection');

  function showSection(id) {
    sections.forEach(s => s.classList.toggle('active', s.id === id));
    links.forEach(l => l.classList.toggle('active', l.dataset.section === id));
    if (topTitle) {
      const lnk = document.querySelector(`.nav-link[data-section="${id}"]`);
      if (lnk) topTitle.textContent = lnk.dataset.label || '';
    }
    sessionStorage.setItem('activeSection', id);
  }

  links.forEach(l => l.addEventListener('click', e => { e.preventDefault(); showSection(l.dataset.section); }));

  const saved = sessionStorage.getItem('activeSection');
  const first = links[0]?.dataset.section;
  const target = saved && document.getElementById(saved) ? saved : first;
  if (target) showSection(target);
}

/* ── NOTIFICATION DRAWER ── */
function toggleDrawer() { document.getElementById('notifDrawer').classList.toggle('open'); }
function closeDrawer()  { document.getElementById('notifDrawer').classList.remove('open'); }

/* ── OPEN A MESSAGE in the viewer modal ── */
function openMessage(id, subject, from, sentAt, body, attachUrl, attachName) {
  document.getElementById('msgSubjectView').textContent = subject;
  document.getElementById('msgMetaView').textContent    = `From: ${from}  ·  ${sentAt}`;
  document.getElementById('msgBodyView').textContent    = body;

  /* Show or hide the download button based on whether there's an attachment */
  const attachWrap = document.getElementById('msgAttachWrap');
  const dlBtn      = document.getElementById('msgDownloadBtn');
  if (attachUrl && attachWrap && dlBtn) {
    dlBtn.href             = attachUrl;
    dlBtn.download         = attachName || 'attachment';
    dlBtn.innerHTML        = dlBtn.innerHTML.replace(/Download.*/,'Download ' + (attachName || 'Attachment'));
    attachWrap.style.display = 'block';
  } else if (attachWrap) {
    attachWrap.style.display = 'none';
  }

  document.getElementById('msgViewOverlay').classList.add('open');

  /* Mark as read */
  const item = document.getElementById('ndItem-' + id);
  if (item && item.classList.contains('unread')) {
    fetch('../php/mark_read.php', { method:'POST', body: new URLSearchParams({msg_id: id}) })
      .then(() => {
        item.classList.remove('unread');
        const dot = item.querySelector('.nd-dot');
        if (dot) dot.remove();
        const badge = document.getElementById('bellBadge');
        if (badge) {
          let n = parseInt(badge.textContent) - 1;
          if (n <= 0) badge.style.display = 'none';
          else badge.textContent = n;
        }
      });
  }
}
function closeMsgView() { document.getElementById('msgViewOverlay').classList.remove('open'); }

/* ── COMPOSE ── */
function openCompose(presetId) {
  document.getElementById('composeOverlay').classList.add('open');
  if (presetId) {
    const sel = document.getElementById('cmpReceiver');
    if (sel) sel.value = String(presetId);
  }
}
function closeCompose() {
  document.getElementById('composeOverlay').classList.remove('open');
}

/* Show a file chip when user selects a file */
document.addEventListener('DOMContentLoaded', function() {
  const fi   = document.getElementById('fileInput');
  const wrap = document.getElementById('fileChipWrap');
  if (!fi) return;
  fi.addEventListener('change', function() {
    wrap.innerHTML = '';
    if (fi.files && fi.files[0]) {
      const name = fi.files[0].name;
      const size = (fi.files[0].size / 1024).toFixed(1) + ' KB';
      const chip = document.createElement('div');
      chip.className = 'file-chip';
      chip.innerHTML = `<span>📎 ${name} <span style="color:var(--muted);font-weight:400;">(${size})</span></span>
        <button class="file-chip-remove" title="Remove" onclick="removeFile()">×</button>`;
      wrap.appendChild(chip);
    }
  });
});

function removeFile() {
  const fi   = document.getElementById('fileInput');
  const wrap = document.getElementById('fileChipWrap');
  if (fi)   fi.value = '';
  if (wrap) wrap.innerHTML = '';
}

function sendMessage() {
  const rid    = document.getElementById('cmpReceiver').value;
  const subj   = document.getElementById('cmpSubject').value.trim();
  const body   = document.getElementById('cmpBody').value.trim();
  const fi     = document.getElementById('fileInput');

  if (!rid || !subj || !body) { toast('Please fill in all fields.', 'err'); return; }

  const data = new FormData();
  data.append('receiver_id', rid);
  data.append('subject', subj);
  data.append('body', body);
  if (fi && fi.files && fi.files[0]) {
    data.append('attachment', fi.files[0]);
  }

  fetch('../php/send_message.php', { method: 'POST', body: data })
    .then(r => r.json()).then(res => {
      if (res.success) {
        closeCompose();
        document.getElementById('cmpReceiver').value = '';
        document.getElementById('cmpSubject').value  = '';
        document.getElementById('cmpBody').value     = '';
        removeFile();
        toast('Message sent!', 'ok');
      } else toast(res.error || 'Failed to send.', 'err');
    }).catch(() => toast('Network error.', 'err'));
}

/* ── TOAST ── */
function toast(msg, type) {
  let el = document.getElementById('_toast');
  if (!el) { el = document.createElement('div'); el.id = '_toast'; document.body.appendChild(el); }
  el.textContent = msg;
  Object.assign(el.style, {
    position:'fixed', bottom:'26px', right:'26px',
    padding:'12px 22px', borderRadius:'4px',
    fontFamily:'Inter,sans-serif', fontSize:'13px', fontWeight:'500',
    zIndex:'9999', opacity:'1', transition:'opacity .3s',
    boxShadow:'0 8px 24px rgba(0,0,0,.4)',
    background: type==='ok'?'#22c55e':'#ef4444', color:'#fff',
  });
  clearTimeout(el._t);
  el._t = setTimeout(() => { el.style.opacity='0'; setTimeout(()=>el.remove(),320); }, 3000);
}

/* ── Close on outside click ── */
document.addEventListener('click', e => {
  const drawer = document.getElementById('notifDrawer');
  const bell   = document.getElementById('bellBtn');
  if (drawer && !drawer.contains(e.target) && bell && !bell.contains(e.target))
    drawer.classList.remove('open');
});

document.addEventListener('DOMContentLoaded', initNav);
