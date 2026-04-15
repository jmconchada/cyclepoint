/**
 * CyclePoint Chat — Clean rebuild
 * No duplicate listeners · Mobile sidebar toggle · All functions working
 */

(function () {
  'use strict';

  /* ── Config ── */
  const POLL_MS      = 3000;
  const CONTACT_MS   = 5000;

  /* ── State ── */
  let lastMsgId      = 0;
  let pollTimer      = null;
  let contactTimer   = null;
  let selectedFiles  = [];
  const selectedUserId = window.selectedUserId || null;

  /* ── DOM refs (grabbed once) ── */
  const $ = id => document.getElementById(id);
  const msgInput      = $('messageInput');
  const sendBtn       = $('sendBtn');
  const chatMessages  = $('chatMessages');
  const searchInput   = $('searchUserInput');
  const emojiBtn      = $('emojiBtn');
  const emojiPicker   = $('emojiPicker');
  const emojiGrid     = $('emojiGrid');
  const fileBtn       = $('fileBtn');
  const fileInput     = $('fileInput');
  const filePreviewArea  = $('filePreviewArea');
  const filePreviewList  = $('filePreviewList');
  const typingIndicator  = $('typingIndicator');
  const menuBtn       = $('menuBtn');
  const chatMenu      = $('chatMenu');
  const usersContainer   = $('usersContainer');
  const userList      = document.querySelector('.user-list');
  const mobileBackBtn = $('mobileBackBtn');

  /* ══════════════════════════════════════
     INIT
  ══════════════════════════════════════ */
  function init() {
    buildEmojiGrid();
    setLastMsgId();
    bindEvents();

    if (selectedUserId) {
      startMsgPolling();
      scrollBottom(true);
      // On mobile: hide sidebar when chat is open
      hideSidebar();
    }

    startContactPolling();
    if (msgInput && selectedUserId) msgInput.focus();
  }

  /* ══════════════════════════════════════
     EMOJI GRID (built once)
  ══════════════════════════════════════ */
  function buildEmojiGrid() {
    if (!emojiGrid) return;
    const emojis = [
      '😀','😃','😄','😁','😆','😅','😂','🤣','😊','😇',
      '🙂','😉','😌','😍','🥰','😘','😋','😎','🤩','🥳',
      '😏','😒','😔','😟','🙁','😣','😫','😩','🥺','😢',
      '😭','😤','😠','🤯','😳','😱','🤗','🤔','🤫','🙄',
      '👍','👎','👌','✌️','🤞','🤙','👏','🙏','💪','✍️',
      '❤️','🧡','💛','💚','💙','💜','🖤','💔','💕','💯',
      '🔥','✨','💫','🎉','🎊','🏆','⭐','🌟','💬','🗨️'
    ];
    emojiGrid.innerHTML = '';
    const frag = document.createDocumentFragment();
    emojis.forEach(e => {
      const s = document.createElement('span');
      s.className = 'emoji-item';
      s.textContent = e;
      frag.appendChild(s);
    });
    emojiGrid.appendChild(frag);
  }

  /* ══════════════════════════════════════
     EVENT BINDING (single place, no duplicates)
  ══════════════════════════════════════ */
  function bindEvents() {
    /* Send */
    sendBtn?.addEventListener('click', sendMessage);
    msgInput?.addEventListener('keydown', e => {
      if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
    });

    /* Search */
    searchInput?.addEventListener('input', filterContacts);

    /* Emoji */
    emojiBtn?.addEventListener('click', e => { e.stopPropagation(); toggleEmoji(); });
    emojiGrid?.addEventListener('click', e => {
      if (e.target.classList.contains('emoji-item')) insertEmoji(e.target.textContent);
    });

    /* File */
    fileBtn?.addEventListener('click', () => fileInput?.click());
    fileInput?.addEventListener('change', onFileChange);

    /* Menu */
    menuBtn?.addEventListener('click', e => { e.stopPropagation(); toggleMenu(); });

    /* Mobile back button — show sidebar */
    mobileBackBtn?.addEventListener('click', showSidebar);

    /* Close dropdowns on outside click */
    document.addEventListener('click', e => {
      if (chatMenu && !chatMenu.contains(e.target) && e.target !== menuBtn)
        chatMenu.style.display = 'none';
      if (emojiPicker && !emojiPicker.contains(e.target) && e.target !== emojiBtn)
        emojiPicker.style.display = 'none';
    });

    /* Close dropdowns on chat scroll */
    chatMessages?.addEventListener('scroll', () => {
      if (chatMenu)   chatMenu.style.display = 'none';
      if (emojiPicker) emojiPicker.style.display = 'none';
    });

    /* Page unload — stop all timers */
    window.addEventListener('beforeunload', stopAll);
  }

  /* ══════════════════════════════════════
     MOBILE SIDEBAR TOGGLE
  ══════════════════════════════════════ */
  function showSidebar() {
    userList?.classList.remove('hidden');
  }
  function hideSidebar() {
    if (window.innerWidth <= 768) {
      userList?.classList.add('hidden');
    }
  }

  /* ══════════════════════════════════════
     SEND MESSAGE
  ══════════════════════════════════════ */
  function sendMessage() {
    if (!selectedUserId) return;
    const text = msgInput?.value.trim() || '';
    const hasPreview = window.prefilledImage?.trim();

    if (hasPreview)              { sendWithPreview(text); return; }
    if (selectedFiles.length)    { sendWithFiles(text);   return; }
    if (!text)                   return;
    sendText(text);
  }

  function setBusy(busy) {
    if (!sendBtn) return;
    sendBtn.disabled = busy;
    sendBtn.innerHTML = busy
      ? '<i class="fas fa-spinner fa-spin"></i>'
      : '<i class="fas fa-paper-plane"></i>';
    if (msgInput) msgInput.disabled = busy;
  }

  function afterSend(data, text, imagePath) {
    if (!data.success) { alert('Failed to send: ' + (data.error || 'unknown')); return; }
    msgInput.value = '';
    appendMessage({
      id: data.message_id,
      sender_id: window.loggedInUserId,
      message: text,
      image_path: imagePath || data.image_path || null,
      timestamp: new Date().toISOString(),
      is_read: 0,
      sender_picture: window.myProfilePic
    });
    lastMsgId = Math.max(lastMsgId, data.message_id);
    scrollBottom(true);
    updateContactList();
    msgInput?.focus();
  }

  function sendText(text) {
    setBusy(true);
    fetch('send_message.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ receiver_id: selectedUserId, message: text })
    })
    .then(r => r.json())
    .then(d => afterSend(d, text, null))
    .catch(() => alert('Failed to send. Try again.'))
    .finally(() => setBusy(false));
  }

  function sendWithPreview(text) {
    if (!text) { alert('Please type a message.'); return; }
    setBusy(true);
    const fd = new FormData();
    fd.append('receiver_id', selectedUserId);
    fd.append('message', text);
    fd.append('prefilled_image', window.prefilledImage);
    fetch('send_message.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
      afterSend(d, text, null);
      const pa = $('itemPreviewArea');
      if (pa) pa.style.display = 'none';
      window.prefilledImage = '';
    })
    .catch(() => alert('Failed to send. Try again.'))
    .finally(() => setBusy(false));
  }

  function sendWithFiles(text) {
    setBusy(true);
    const fd = new FormData();
    fd.append('receiver_id', selectedUserId);
    if (text) fd.append('message', text);
    selectedFiles.forEach(f => fd.append('images[]', f));
    fetch('upload_image.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
      if (!d.success) { alert('Upload failed: ' + (d.error || '')); return; }
      msgInput.value = '';
      (d.images || []).forEach(img => {
        appendMessage(img);
        lastMsgId = Math.max(lastMsgId, img.id);
      });
      selectedFiles = [];
      renderFilePreviews();
      scrollBottom(true);
      updateContactList();
    })
    .catch(() => alert('Upload failed. Try again.'))
    .finally(() => setBusy(false));
  }

  /* ══════════════════════════════════════
     APPEND MESSAGE TO DOM
  ══════════════════════════════════════ */
  function appendMessage(msg) {
    if (!chatMessages) return;
    chatMessages.querySelector('.no-messages')?.remove();

    const isSent = Number(msg.sender_id) === Number(window.loggedInUserId);
    const div = document.createElement('div');
    div.className = 'message ' + (isSent ? 'sent' : 'received');
    div.dataset.id = msg.id;

    let html = '';
    if (!isSent) {
      html += `<img src="${esc(msg.sender_picture || 'assets/images/profile-picture.png')}" class="msg-avatar" alt="">`;
    }
    html += '<div class="msg-bubble">';
    if (msg.image_path) {
      html += `<div class="msg-image-wrapper">
        <img src="${esc(msg.image_path)}" class="msg-image" alt="Image"
             onclick="openImageModal('${esc(msg.image_path)}')">
      </div>`;
    }
    if (msg.message && msg.message !== '[Image]') {
      html += `<div class="msg-text">${esc(msg.message).replace(/\n/g, '<br>')}</div>`;
    }
    html += `<div class="msg-time">${fmtTime(msg.timestamp)}`;
    if (isSent) html += msg.is_read
      ? ' <i class="fas fa-check-double read-check"></i>'
      : ' <i class="fas fa-check sent-check"></i>';
    html += '</div></div>';

    div.innerHTML = html;
    chatMessages.appendChild(div);
  }

  /* ══════════════════════════════════════
     POLLING — messages
  ══════════════════════════════════════ */
  function startMsgPolling() {
    clearInterval(pollTimer);
    pollTimer = setInterval(pollMessages, POLL_MS);
  }

  function pollMessages() {
    if (!selectedUserId) return;
    fetch(`get_new_messages.php?user_id=${selectedUserId}&last_id=${lastMsgId}`)
    .then(r => r.json())
    .then(d => {
      if (d.success && d.messages?.length) {
        d.messages.forEach(m => {
          appendMessage(m);
          lastMsgId = Math.max(lastMsgId, m.id);
        });
        scrollBottom(false);
      }
      if (d.is_online !== undefined) updateStatus(d.is_online);
    })
    .catch(() => {}); // silent
  }

  /* ── contacts ── */
  function startContactPolling() {
    clearInterval(contactTimer);
    contactTimer = setInterval(updateContactList, CONTACT_MS);
  }

  function updateContactList() {
    fetch('get_contacts.php')
    .then(r => r.json())
    .then(d => { if (d.success && d.contacts) renderContacts(d.contacts); })
    .catch(() => {});
  }

  function renderContacts(list) {
    if (!usersContainer) return;
    if (!list.length) {
      usersContainer.innerHTML = `<div class="no-users">
        <i class="fas fa-inbox"></i><p>No conversations yet</p>
        <small>Visit a listing and click "Chat With Owner" to start.</small></div>`;
      return;
    }
    usersContainer.innerHTML = list.map(c => {
      const active = selectedUserId && Number(selectedUserId) === Number(c.id);
      const unread = c.unread_count > 0;
      return `<a href="chat.php?user_id=${c.id}"
          class="user-item ${active?'active':''} ${unread?'unread':''}"
          data-user-id="${c.id}">
        <div class="user-avatar">
          <img src="${esc(c.profile_picture)}" alt="${esc(c.name)}">
          ${c.is_online ? '<span class="online-dot"></span>' : ''}
        </div>
        <div class="user-info">
          <div class="user-header">
            <span class="user-name">${esc(c.name)}</span>
            ${c.last_timestamp ? `<span class="user-time">${timeAgo(c.last_timestamp)}</span>` : ''}
          </div>
          <div class="user-preview">
            <span class="last-msg ${unread?'unread-text':''}">
              ${c.last_message ? esc(c.last_message).substring(0,40) : '<em>No messages yet</em>'}
            </span>
            ${unread ? `<span class="unread-badge">${c.unread_count}</span>` : ''}
          </div>
        </div>
      </a>`;
    }).join('');
  }

  function stopAll() {
    clearInterval(pollTimer);
    clearInterval(contactTimer);
  }

  /* ══════════════════════════════════════
     HELPERS
  ══════════════════════════════════════ */
  function setLastMsgId() {
    chatMessages?.querySelectorAll('.message[data-id]').forEach(m => {
      lastMsgId = Math.max(lastMsgId, parseInt(m.dataset.id) || 0);
    });
  }

  function scrollBottom(instant) {
    if (!chatMessages) return;
    if (instant) chatMessages.scrollTop = chatMessages.scrollHeight;
    else chatMessages.scrollTo({ top: chatMessages.scrollHeight, behavior: 'smooth' });
  }

  function filterContacts() {
    const q = searchInput?.value.toLowerCase().trim() || '';
    document.querySelectorAll('.user-item').forEach(el => {
      const name = el.querySelector('.user-name')?.textContent.toLowerCase() || '';
      el.style.display = name.includes(q) ? '' : 'none';
    });
  }

  function toggleEmoji() {
    if (!emojiPicker) return;
    const open = emojiPicker.style.display === 'block';
    if (open) { emojiPicker.style.display = 'none'; return; }
    const r = emojiBtn.getBoundingClientRect();
    emojiPicker.style.bottom = (window.innerHeight - r.top + 10) + 'px';
    emojiPicker.style.left   = Math.max(4, r.left) + 'px';
    emojiPicker.style.display = 'block';
  }

  function insertEmoji(emoji) {
    if (!msgInput) return;
    const s = msgInput.selectionStart, e = msgInput.selectionEnd;
    msgInput.value = msgInput.value.slice(0, s) + emoji + msgInput.value.slice(e);
    msgInput.selectionStart = msgInput.selectionEnd = s + emoji.length;
    msgInput.focus();
    emojiPicker.style.display = 'none';
  }

  function toggleMenu() {
    if (!chatMenu) return;
    chatMenu.style.display = chatMenu.style.display === 'block' ? 'none' : 'block';
  }

  function onFileChange(e) {
    Array.from(e.target.files).forEach(f => {
      if (f.type.startsWith('image/') && f.size <= 5*1024*1024) selectedFiles.push(f);
    });
    renderFilePreviews();
    fileInput.value = '';
  }

  function renderFilePreviews() {
    if (!filePreviewList || !filePreviewArea) return;
    filePreviewList.innerHTML = '';
    if (!selectedFiles.length) { filePreviewArea.style.display = 'none'; return; }
    filePreviewArea.style.display = 'block';
    selectedFiles.forEach((f, i) => {
      const r = new FileReader();
      r.onload = ev => {
        const d = document.createElement('div');
        d.className = 'file-preview-item';
        d.innerHTML = `<img src="${ev.target.result}">
          <button class="file-preview-remove" onclick="removeFile(${i})">
            <i class="fas fa-times"></i></button>`;
        filePreviewList.appendChild(d);
      };
      r.readAsDataURL(f);
    });
    const add = document.createElement('div');
    add.className = 'file-preview-add';
    add.innerHTML = '<i class="fas fa-plus"></i><span>More</span>';
    add.onclick = () => fileInput?.click();
    filePreviewList.appendChild(add);
  }

  window.removeFile = i => { selectedFiles.splice(i, 1); renderFilePreviews(); };

  function updateStatus(online) {
    const el = document.getElementById('chatStatus');
    if (!el) return;
    el.textContent = online ? 'Online' : 'Offline';
    el.className = 'chat-status ' + (online ? 'status-online' : 'status-offline');
  }

  function fmtTime(ts) {
    const d = new Date(ts);
    let h = d.getHours(), m = String(d.getMinutes()).padStart(2,'0');
    const ap = h >= 12 ? 'PM' : 'AM';
    h = h % 12 || 12;
    return `${h}:${m} ${ap}`;
  }

  function timeAgo(ts) {
    const s = Math.floor((Date.now() - new Date(ts).getTime()) / 1000);
    if (s < 60)    return 'Just now';
    if (s < 3600)  return Math.floor(s/60) + 'm';
    if (s < 86400) return Math.floor(s/3600) + 'h';
    if (s < 604800)return Math.floor(s/86400) + 'd';
    return new Date(ts).toLocaleDateString('en-US',{month:'short',day:'numeric'});
  }

  function esc(s) {
    if (!s && s !== 0) return '';
    const d = document.createElement('div');
    d.textContent = String(s);
    return d.innerHTML;
  }

  /* ── Image modal (global) ── */
  window.openImageModal = function(path) {
    const m = document.createElement('div');
    m.className = 'image-modal';
    m.innerHTML = `
      <div class="image-modal-backdrop" onclick="this.parentElement.remove()"></div>
      <div class="image-modal-content">
        <button class="image-modal-close" onclick="this.closest('.image-modal').remove()">
          <i class="fas fa-times"></i></button>
        <img src="${esc(path)}" alt="Image">
        <a href="${esc(path)}" download class="image-modal-download">
          <i class="fas fa-download"></i> Download</a>
      </div>`;
    document.body.appendChild(m);
    setTimeout(() => m.classList.add('show'), 10);
    document.addEventListener('keydown', function esc(e) {
      if (e.key === 'Escape') { m.remove(); document.removeEventListener('keydown', esc); }
    });
  };

  /* ── Boot ── */
  if (document.readyState === 'loading')
    document.addEventListener('DOMContentLoaded', init);
  else
    init();

})();