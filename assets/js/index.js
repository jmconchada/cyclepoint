document.addEventListener('DOMContentLoaded', () => {

  // ---------------- PROFILE DROPDOWN ----------------
  const profileDropdownBtn = document.querySelector('.cp-avatar');
  const profileDropdownContent = document.querySelector('.profile-dropdown .dropdown-content');

  if (profileDropdownBtn && profileDropdownContent) {
    profileDropdownBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      profileDropdownContent.style.display = profileDropdownContent.style.display === 'block' ? 'none' : 'block';
    });
    document.addEventListener('click', (e) => {
      if (!profileDropdownBtn.contains(e.target) && !profileDropdownContent.contains(e.target)) {
        profileDropdownContent.style.display = 'none';
      }
    });
  }

  const updateProfileDropdown = () => {
    const profilePictureEl = document.querySelector('.cp-avatar img');
    if (profilePictureEl && userProfile) {
      profilePictureEl.src = userProfile.profile_picture || 'assets/images/profile-picture.png';
    }
  };
  updateProfileDropdown();

  // ---------------- LANGUAGE SELECTOR ----------------
  const langMenu = document.getElementById('langMenu');

  // toggleLangDropdown is called by onclick attribute in HTML
  window.toggleLangDropdown = () => {
    if (!langMenu) return;
    const isOpen = langMenu.style.display === 'block';
    langMenu.style.display = isOpen ? 'none' : 'block';
  };

  // changeLanguage is called by onclick on each option button
  window.changeLanguage = (lang) => {
    window.location.href = '?lang=' + lang;
  };

  // Close lang menu when clicking outside
  document.addEventListener('click', (e) => {
    if (!langMenu) return;
    const langBtn = document.getElementById('currentLangBtn');
    if (langBtn && !langBtn.contains(e.target) && !langMenu.contains(e.target)) {
      langMenu.style.display = 'none';
    }
  });

  const notifWrapper = document.querySelector('.cp-notif-wrapper');
const notifBell = document.querySelector('.cp-bell');
const notifDropdown = document.querySelector('.cp-notif-dropdown');

if (notifWrapper && notifBell && notifDropdown) {
  // Toggle dropdown
  notifBell.addEventListener('click', (e) => {
    e.stopPropagation();
    notifWrapper.classList.toggle('show');
    if (notifWrapper.classList.contains('show')) markNotificationsRead();
  });

  // Close dropdown when clicking outside
  document.addEventListener('click', () => {
    notifWrapper.classList.remove('show');
  });
}

// Fetch notifications (same logic)
const fetchNotifications = () => {
  fetch('fetch_notifications.php')
    .then(res => res.json())
    .then(data => {
      const notifList = document.createElement('ul');
      let unreadCount = 0;
      if (data.notifications && data.notifications.length > 0) {
        data.notifications.forEach(note => {
          const li = document.createElement('li');
          li.className = 'notif-item ' + (note.is_read == 0 ? 'unread' : '');
          li.innerHTML = `<div class="notif-text">${note.message}</div>
                          <div class="notif-time">${note.created_at}</div>`;
          notifList.appendChild(li);
          if (note.is_read == 0) unreadCount++;
        });
      } else {
        const li = document.createElement('li');
        li.className = 'no-notifs';
        li.textContent = 'No notifications';
        notifList.appendChild(li);
      }
      notifDropdown.innerHTML = '';
      notifDropdown.appendChild(notifList);

      const badge = document.querySelector('.cp-badge-dot');
      if (badge) {
        badge.style.display = unreadCount > 0 ? 'block' : 'none';
        badge.textContent = unreadCount;
      }
    });
};

// Mark as read
const markNotificationsRead = () => {
  fetch('mark_notifications_read.php').then(() => fetchNotifications());
};

// Initial fetch + polling
fetchNotifications();
setInterval(fetchNotifications, 30000);



 });