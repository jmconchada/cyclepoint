<?php
session_start();
include 'db.php';

$user_id = $_SESSION['user_id'] ?? 0;

// ✅ MARK ALL NOTIFICATIONS AS READ WHEN PAGE OPENS
if ($user_id > 0) {
    $markReadStmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
    if ($markReadStmt) {
        $markReadStmt->bind_param("i", $user_id);
        $markReadStmt->execute();
        $affected = $markReadStmt->affected_rows;
        $markReadStmt->close();
        error_log("Marked $affected notifications as read for user $user_id");
    }
}

// Fetch user-specific + admin notifications
$sql = "SELECT * FROM notifications 
        WHERE user_id = $user_id OR user_id IS NULL
        ORDER BY created_at DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Notifications - CyclePoint</title>
<link rel="stylesheet" href="assets/css/notifications.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
/* Notification Badge Styles */
.notification-icon-wrapper {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
}

.notification-icon-wrapper:hover {
    transform: scale(1.1);
}

.notification-badge,
.notif-badge {
    position: absolute;
    top: -8px;
    right: -8px;
    background: linear-gradient(135deg, #ff4757, #ff6348);
    color: white;
    font-size: 11px;
    font-weight: 700;
    padding: 2px 6px;
    border-radius: 12px;
    min-width: 20px;
    height: 20px;
    display: none;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 8px rgba(255, 71, 87, 0.4);
    animation: badgePulse 2s infinite;
    z-index: 10;
    border: 2px solid white;
}

@keyframes badgePulse {
    0%, 100% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.1);
    }
}

.notification-badge.has-notifications,
.notif-badge.has-notifications {
    animation: badgePulse 2s infinite, badgeAppear 0.3s ease;
}

@keyframes badgeAppear {
    0% {
        transform: scale(0);
        opacity: 0;
    }
    60% {
        transform: scale(1.2);
    }
    100% {
        transform: scale(1);
        opacity: 1;
    }
}

.notification-bell {
    font-size: 20px;
    color: #333;
    transition: all 0.3s ease;
}

.notification-icon-wrapper:hover .notification-bell {
    color: #ff4757;
    animation: bellRing 0.5s ease;
}

@keyframes bellRing {
    0%, 100% {
        transform: rotate(0deg);
    }
    10%, 30%, 50%, 70%, 90% {
        transform: rotate(-10deg);
    }
    20%, 40%, 60%, 80% {
        transform: rotate(10deg);
    }
}

.nav-notification-link {
    position: relative;
    display: inline-flex;
    align-items: center;
    padding: 10px 15px;
    text-decoration: none;
    color: #333;
    transition: all 0.3s ease;
}

.nav-notification-link:hover {
    background-color: rgba(255, 71, 87, 0.1);
    border-radius: 8px;
}

/* Message Badge (different color) */
.message-badge {
    background: linear-gradient(135deg, #5f27cd, #341f97);
    box-shadow: 0 2px 8px rgba(95, 39, 205, 0.4);
}

@media (max-width: 768px) {
    .notification-badge,
    .notif-badge,
    .message-badge {
        top: -6px;
        right: -6px;
        font-size: 10px;
        min-width: 18px;
        height: 18px;
        padding: 2px 5px;
    }
    
    .notification-bell {
        font-size: 18px;
    }
}
</style>

<script>
// Pass user login status to JavaScript
const isLoggedIn = <?php echo $user_id > 0 ? 'true' : 'false'; ?>;
</script>
</head>
<body>
<div class="notifications-container">
    <div class="notifications-header">
        <h1>🔔 Notifications</h1>
        <p>Stay updated with the latest announcements and activity on your posts and profile.</p>
    </div>

    <table id="notificationTable">
        <thead>
            <tr>
                <th>Type</th>
                <th>Message</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr class="<?php echo $row['is_read'] ? '' : 'unread'; ?>">
                        <td><?php echo htmlspecialchars($row['type'] ?? 'Notification'); ?></td>
                        <td><?php echo htmlspecialchars($row['message']); ?></td>
                        <td><?php echo date('M d, Y H:i', strtotime($row['created_at'])); ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="3" style="text-align: center; padding: 20px;">
                        No notifications yet.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    
    <div style="margin-top: 20px; text-align: center;">
        <a href="index.php" class="btn-back" style="display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px;">
            ← Back to Home
        </a>
    </div>
</div>

<script src="assets/js/notifications.js"></script>
<script src="assets/js/page-transitions.js"></script>

<script>
// ========================================
// NOTIFICATION & MESSAGE BADGE SYSTEM
// Uses existing get_unread_counts.php
// ========================================

// Function to fetch unread counts (messages + notifications)
async function fetchUnreadCounts() {
    if (!isLoggedIn) return;
    
    try {
        const response = await fetch('get_unread_counts.php');
        const data = await response.json();
        
        if (data.success) {
            // Update notification badges
            updateBadge('.notification-badge, .notif-badge', data.notifications);
            
            // Update message badges (if you have them)
            updateBadge('.message-badge, .msg-badge', data.messages);
            
            // Update page title with notification count
            updatePageTitle(data.notifications);
        }
    } catch (error) {
        console.error('Error fetching unread counts:', error);
    }
}

// Function to update specific badge type
function updateBadge(selector, count) {
    const badges = document.querySelectorAll(selector);
    
    badges.forEach(badge => {
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : count;
            badge.style.display = 'flex';
            badge.classList.add('has-notifications');
        } else {
            badge.textContent = '0';
            badge.style.display = 'none';
            badge.classList.remove('has-notifications');
        }
    });
}

// Function to update page title with notification count
function updatePageTitle(count) {
    const baseTitle = document.title.replace(/^\(\d+\)\s/, '');
    if (count > 0) {
        document.title = `(${count}) ${baseTitle}`;
    } else {
        document.title = baseTitle;
    }
}

// Check for updates every 30 seconds
function startBadgePolling() {
    if (!isLoggedIn) return;
    
    // Initial fetch
    fetchUnreadCounts();
    
    // Poll every 30 seconds
    setInterval(fetchUnreadCounts, 30000);
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', startBadgePolling);
} else {
    startBadgePolling();
}

// Mark notifications as read when on notifications page
if (window.location.pathname.includes('notifications.php')) {
    // Wait a bit then refresh count (notifications are marked read on page load)
    setTimeout(() => {
        fetchUnreadCounts();
    }, 500);
}

// Also refresh when page becomes visible (user switches back to tab)
document.addEventListener('visibilitychange', function() {
    if (!document.hidden && isLoggedIn) {
        fetchUnreadCounts();
    }
});
</script>

</body>
</html>