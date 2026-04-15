/**
 * CyclePoint Notification Badges - Real-time Updates
 * Works like Facebook: badges update automatically when messages are read
 */

(function() {
    'use strict';
    
    // Configuration
    const CHECK_INTERVAL = 10000; // Check every 10 seconds
    let updateTimer = null;
    
    /**
     * Update notification badges with new counts
     */
    function updateBadges(messageCount, notificationCount) {
        // Update message badge - find the messages link
        const messageLink = document.querySelector('a[href="chat.php"]');
        
        if (messageLink) {
            // Remove existing badge
            const oldBadge = messageLink.querySelector('.cp-badge-dot');
            if (oldBadge) {
                oldBadge.remove();
            }
            
            // Add new badge if count > 0
            if (messageCount > 0) {
                const badge = document.createElement('span');
                badge.className = 'cp-badge-dot';
                badge.textContent = messageCount > 99 ? '99+' : messageCount;
                messageLink.style.position = 'relative'; // Ensure relative positioning
                messageLink.appendChild(badge);
            }
        }
        
        // Update notification badge (red count like messages) - find the notifications link
        const notificationLink = document.querySelector('a[href="notifications.php"]');
        
        if (notificationLink) {
            // Remove existing badge (both classes)
            const oldBadge = notificationLink.querySelector('.cp-badge-dot');
            if (oldBadge) {
                oldBadge.remove();
            }
            const oldDot = notificationLink.querySelector('.cp-notification-dot');
            if (oldDot) {
                oldDot.remove();
            }
            
            // Add new badge if count > 0 (SAME AS MESSAGES - shows number!)
            if (notificationCount > 0) {
                const badge = document.createElement('span');
                badge.className = 'cp-badge-dot';  // Same class as message badge!
                badge.textContent = notificationCount > 99 ? '99+' : notificationCount;
                notificationLink.style.position = 'relative'; // Ensure relative positioning
                notificationLink.appendChild(badge);
            }
        }
    }
    
    /**
     * Fetch unread counts from server
     */
    function fetchUnreadCounts() {
        fetch('get_unread_counts.php', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateBadges(data.messages, data.notifications);
                console.log('✅ Badges updated:', data.messages, 'messages,', data.notifications, 'notifications');
            }
        })
        .catch(error => {
            console.error('❌ Failed to fetch unread counts:', error);
        });
    }
    
    /**
     * Mark messages as read when chat page is opened
     */
    function markMessagesAsRead() {
        fetch('mark_messages_read.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('✅ Messages marked as read:', data.marked_read);
                // Immediately update badges
                fetchUnreadCounts();
            }
        })
        .catch(error => {
            console.error('❌ Failed to mark messages as read:', error);
        });
    }
    
    /**
     * Mark notifications as read when notifications page is opened
     */
    function markNotificationsAsRead() {
        fetch('mark_notifications_read.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('✅ Notifications marked as read:', data.marked_read);
                // Immediately update badges
                fetchUnreadCounts();
            }
        })
        .catch(error => {
            console.error('❌ Failed to mark notifications as read:', error);
        });
    }
    
    /**
     * Initialize on chat.php page
     */
    function initChatPage() {
        // Check if we're on chat page
        if (window.location.pathname.includes('chat.php')) {
            console.log('📨 Chat page detected - marking messages as read');
            markMessagesAsRead();
        }
    }
    
    /**
     * Initialize on notifications.php page
     */
    function initNotificationsPage() {
        // Check if we're on notifications page
        if (window.location.pathname.includes('notifications.php')) {
            console.log('🔔 Notifications page detected - marking as read');
            markNotificationsAsRead();
        }
    }
    
    /**
     * Start periodic updates (like Facebook)
     */
    function startPeriodicUpdates() {
        // Initial fetch
        fetchUnreadCounts();
        
        // Update every 10 seconds
        updateTimer = setInterval(fetchUnreadCounts, CHECK_INTERVAL);
        
        console.log('🔄 Started periodic badge updates (every 10s)');
    }
    
    /**
     * Stop periodic updates
     */
    function stopPeriodicUpdates() {
        if (updateTimer) {
            clearInterval(updateTimer);
            updateTimer = null;
            console.log('⏸️ Stopped periodic badge updates');
        }
    }
    
    /**
     * Handle visibility change (pause when tab is hidden)
     */
    function handleVisibilityChange() {
        if (document.hidden) {
            stopPeriodicUpdates();
        } else {
            startPeriodicUpdates();
        }
    }
    
    /**
     * Initialize everything
     */
    function init() {
        // Wait for DOM
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
            return;
        }
        
        // Check if user is logged in
        if (typeof isLoggedIn === 'undefined' || !isLoggedIn) {
            console.log('ℹ️ User not logged in - skipping badge updates');
            return;
        }
        
        // Initialize page-specific functionality
        initChatPage();
        initNotificationsPage();
        
        // Start periodic updates
        startPeriodicUpdates();
        
        // Pause updates when tab is hidden (saves resources)
        document.addEventListener('visibilitychange', handleVisibilityChange);
        
        // Update badges when user returns to page
        window.addEventListener('focus', function() {
            console.log('👁️ Window focused - fetching latest counts');
            fetchUnreadCounts();
        });
        
        console.log('🎉 Notification badge system initialized');
    }
    
    // Start initialization
    init();
    
    // Expose functions globally for manual calls if needed
    window.CyclePointNotifications = {
        updateBadges: updateBadges,
        fetchCounts: fetchUnreadCounts,
        markMessagesRead: markMessagesAsRead,
        markNotificationsRead: markNotificationsAsRead
    };
    
})();