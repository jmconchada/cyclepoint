<?php
session_start();
require 'db.php';

// Protect admin page
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Handle announcement creation
if (isset($_POST['create_announcement'])) {
    $title = $_POST['announcement_title'];
    $message = $_POST['announcement_message'];
    $type = $_POST['announcement_type'];
    $priority = $_POST['announcement_priority'];
    $expiry_date = $_POST['announcement_expiry'] ?? null;
    
    $stmt = $conn->prepare("INSERT INTO announcements (title, message, type, priority, created_by, created_at, expiry_date, is_active) VALUES (?, ?, ?, ?, ?, NOW(), ?, 1)");
    $stmt->bind_param("ssssis", $title, $message, $type, $priority, $_SESSION['user_id'], $expiry_date);
    
    if ($stmt->execute()) {
        // ✅ NEW: Create notifications for ALL users
        $announcement_message = "📢 New Announcement: " . $title;
        
        // Get all user IDs
        $users_query = "SELECT id FROM users";
        $users_result = $conn->query($users_query);
        
        if ($users_result && $users_result->num_rows > 0) {
            // Prepare notification insert statement
            $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, type, message, is_read, created_at) VALUES (?, 'announcement', ?, 0, NOW())");
            
            $notification_count = 0;
            while ($user = $users_result->fetch_assoc()) {
                $notif_stmt->bind_param("is", $user['id'], $announcement_message);
                if ($notif_stmt->execute()) {
                    $notification_count++;
                }
            }
            $notif_stmt->close();
            
            $_SESSION['flash_message'] = "Announcement created successfully and sent to $notification_count users.";
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = 'Announcement created but no users found to notify.';
            $_SESSION['flash_type'] = 'success';
        }
    } else {
        $_SESSION['flash_message'] = 'Error creating announcement: ' . $conn->error;
        $_SESSION['flash_type'] = 'error';
    }
    $stmt->close();
    
    header("Location: announcements.php");
    exit;
}

// Handle announcement deletion
if (isset($_GET['delete_announcement'])) {
    $announcement_id = (int)$_GET['delete_announcement'];
    
    $stmt = $conn->prepare("DELETE FROM announcements WHERE id = ?");
    $stmt->bind_param("i", $announcement_id);
    
    if ($stmt->execute()) {
        $_SESSION['flash_message'] = 'Announcement deleted successfully.';
        $_SESSION['flash_type'] = 'success';
    } else {
        $_SESSION['flash_message'] = 'Error deleting announcement: ' . $conn->error;
        $_SESSION['flash_type'] = 'error';
    }
    $stmt->close();
    
    header("Location: announcements.php");
    exit;
}

// Handle toggle announcement status
if (isset($_GET['toggle_announcement'])) {
    $announcement_id = (int)$_GET['toggle_announcement'];
    
    // Get announcement details first
    $check_stmt = $conn->prepare("SELECT title, is_active FROM announcements WHERE id = ?");
    $check_stmt->bind_param("i", $announcement_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $announcement = $result->fetch_assoc();
    $check_stmt->close();
    
    if ($announcement) {
        $stmt = $conn->prepare("UPDATE announcements SET is_active = NOT is_active WHERE id = ?");
        $stmt->bind_param("i", $announcement_id);
        
        if ($stmt->execute()) {
            // ✅ NEW: Notify users when announcement is reactivated
            if ($announcement['is_active'] == 0) { // Was inactive, now active
                $notif_message = "📢 Announcement Reactivated: " . $announcement['title'];
                
                $users_query = "SELECT id FROM users";
                $users_result = $conn->query($users_query);
                
                if ($users_result && $users_result->num_rows > 0) {
                    $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, type, message, is_read, created_at) VALUES (?, 'announcement', ?, 0, NOW())");
                    
                    while ($user = $users_result->fetch_assoc()) {
                        $notif_stmt->bind_param("is", $user['id'], $notif_message);
                        $notif_stmt->execute();
                    }
                    $notif_stmt->close();
                }
            }
            
            $_SESSION['flash_message'] = 'Announcement status updated.';
            $_SESSION['flash_type'] = 'success';
        }
        $stmt->close();
    }
    
    header("Location: announcements.php");
    exit;
}

// Get all announcements (including inactive)
$announcements_query = "SELECT a.*, u.name as admin_name 
                        FROM announcements a 
                        LEFT JOIN users u ON a.created_by = u.id 
                        ORDER BY a.is_active DESC, a.priority DESC, a.created_at DESC";
$announcements_result = $conn->query($announcements_query);

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
    SUM(CASE WHEN expiry_date IS NOT NULL AND expiry_date > NOW() THEN 1 ELSE 0 END) as expiring_soon,
    SUM(CASE WHEN expiry_date IS NOT NULL AND expiry_date <= NOW() THEN 1 ELSE 0 END) as expired
FROM announcements";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements Management - Admin Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/admindash.css">
</head>
<body>

<!-- Sidebar -->
<aside class="sidebar">
            <div class="sidebar-header">
            <div class="logo">
                <i class="fas fa-shield-halved"></i>
                <span>Admin Panel</span>
            </div>
            <a href="index.php" class="visit-site-btn">
                <i class="fas fa-arrow-up-right-from-square"></i>
                <span>Visit Site</span>
            </a>
        </div>
    
            <nav class="sidebar-nav">
            <div class="nav-section-label">Overview</div>
            <a href="dashboard.php" class="nav-item">
                <i class="fas fa-chart-line"></i><span>Dashboard</span>
            </a>
            <a href="manage_posts.php" class="nav-item">
                <i class="fas fa-box-archive"></i><span>Manage Posts</span>
            </a>
            <div class="nav-section-label">Management</div>
            <a href="admindash.php" class="nav-item">
                <i class="fas fa-users-gear"></i><span>User Management</span>
            </a>
            <a href="announcements.php" class="nav-item active">
                <i class="fas fa-bullhorn"></i><span>Announcements</span>
            </a>
            <a href="admin_trades.php" class="nav-item">
                <i class="fas fa-handshake"></i><span>Trades &amp; Ratings</span>
            </a>
            <div class="nav-section-label">System</div>
            <a href="logout.php" class="nav-item logout">
                <i class="fas fa-arrow-right-from-bracket"></i><span>Logout</span>
            </a>
        </nav>
    
    <div class="sidebar-footer">
        <div class="admin-profile">
            <div class="admin-avatar">
                <?= strtoupper(substr($_SESSION['user_name'], 0, 1)) ?>
            </div>
            <div class="admin-info">
                <div class="admin-name"><?= htmlspecialchars($_SESSION['user_name']) ?></div>
                <div class="admin-role">Administrator</div>
            </div>
        </div>
    </div>
</aside>

<!-- Main Content -->
<main class="main-content">
    <header class="page-header">
        <div class="header-content">
            <div class="header-text">
                <h1 class="page-title">Announcements Management</h1>
                <p class="page-subtitle">Create and manage system-wide announcements for all users</p>
            </div>
            <button class="btn btn-primary btn-large" onclick="openAnnouncementModal()">
                <i class="fas fa-plus"></i>
                <span>Create Announcement</span>
            </button>
        </div>
    </header>

    <!-- Flash Message -->
    <?php if (isset($_SESSION['flash_message'])): ?>
        <div class="alert alert-<?= $_SESSION['flash_type'] ?? 'success' ?>">
            <i class="fas fa-<?= ($_SESSION['flash_type'] ?? 'success') === 'success' ? 'circle-check' : 'circle-exclamation' ?>"></i>
            <span><?= htmlspecialchars($_SESSION['flash_message']); ?></span>
        </div>
        <?php 
        unset($_SESSION['flash_message']); 
        unset($_SESSION['flash_type']);
        ?>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon total">
                <i class="fas fa-bullhorn"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Total Announcements</div>
                <div class="stat-value"><?= number_format($stats['total']) ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon active">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Active</div>
                <div class="stat-value"><?= number_format($stats['active']) ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon restricted">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Expiring Soon</div>
                <div class="stat-value"><?= number_format($stats['expiring_soon']) ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon banned">
                <i class="fas fa-calendar-times"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Expired</div>
                <div class="stat-value"><?= number_format($stats['expired']) ?></div>
            </div>
        </div>
    </div>

    <!-- Announcements Grid -->
    <div class="announcements-section">
        <div class="section-header">
            <h2 class="section-title">All Announcements</h2>
            <div class="filter-group">
                <button class="filter-btn active" onclick="filterAnnouncements('all')">All</button>
                <button class="filter-btn" onclick="filterAnnouncements('active')">Active</button>
                <button class="filter-btn" onclick="filterAnnouncements('inactive')">Inactive</button>
                <button class="filter-btn" onclick="filterAnnouncements('expired')">Expired</button>
            </div>
        </div>

        <div class="announcements-grid">
            <?php if ($announcements_result && $announcements_result->num_rows > 0): ?>
                <?php while($announcement = $announcements_result->fetch_assoc()): 
                    $isExpired = $announcement['expiry_date'] && strtotime($announcement['expiry_date']) < time();
                    $isActive = $announcement['is_active'] == 1;
                ?>
                    <div class="announcement-card announcement-<?= $announcement['type'] ?> priority-<?= $announcement['priority'] ?> <?= !$isActive ? 'inactive' : '' ?> <?= $isExpired ? 'expired' : '' ?>" 
                         data-status="<?= $isActive ? 'active' : 'inactive' ?>" 
                         data-expired="<?= $isExpired ? 'yes' : 'no' ?>">
                        
                        <div class="announcement-status-indicator">
                            <?php if ($isExpired): ?>
                                <span class="status-badge status-expired">
                                    <i class="fas fa-calendar-times"></i>
                                    Expired
                                </span>
                            <?php elseif ($isActive): ?>
                                <span class="status-badge status-active">
                                    <i class="fas fa-check-circle"></i>
                                    Active
                                </span>
                            <?php else: ?>
                                <span class="status-badge status-inactive">
                                    <i class="fas fa-pause-circle"></i>
                                    Inactive
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="announcement-header-card">
                            <div class="announcement-type-badge">
                                <?php
                                $typeIcons = [
                                    'info' => 'info-circle',
                                    'warning' => 'exclamation-triangle',
                                    'success' => 'check-circle',
                                    'urgent' => 'bell'
                                ];
                                $icon = $typeIcons[$announcement['type']] ?? 'bullhorn';
                                ?>
                                <i class="fas fa-<?= $icon ?>"></i>
                                <?= ucfirst($announcement['type']) ?>
                            </div>
                            <div class="announcement-priority">
                                <?= ucfirst($announcement['priority']) ?> Priority
                            </div>
                        </div>
                        
                        <h3 class="announcement-title"><?= htmlspecialchars($announcement['title']) ?></h3>
                        <p class="announcement-message"><?= nl2br(htmlspecialchars($announcement['message'])) ?></p>
                        
                        <div class="announcement-footer">
                            <div class="announcement-meta">
                                <span><i class="fas fa-user"></i> <?= htmlspecialchars($announcement['admin_name']) ?></span>
                                <span><i class="fas fa-calendar"></i> <?= date('M d, Y', strtotime($announcement['created_at'])) ?></span>
                                <?php if ($announcement['expiry_date']): ?>
                                    <span class="<?= $isExpired ? 'text-danger' : '' ?>">
                                        <i class="fas fa-clock"></i> 
                                        <?= $isExpired ? 'Expired: ' : 'Expires: ' ?>
                                        <?= date('M d, Y', strtotime($announcement['expiry_date'])) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="announcement-actions">
                                <?php if (!$isExpired): ?>
                                    <a href="?toggle_announcement=<?= $announcement['id'] ?>" 
                                       class="btn btn-secondary btn-small"
                                       title="<?= $isActive ? 'Deactivate' : 'Activate' ?>">
                                        <i class="fas fa-<?= $isActive ? 'pause' : 'play' ?>"></i>
                                    </a>
                                <?php endif; ?>
                                <a href="?delete_announcement=<?= $announcement['id'] ?>" 
                                   class="btn btn-delete btn-small"
                                   onclick="return confirm('Delete this announcement? This action cannot be undone.')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-bullhorn"></i>
                    <h3>No Announcements Yet</h3>
                    <p>Create your first announcement to notify all users.</p>
                    <button class="btn btn-primary" onclick="openAnnouncementModal()">
                        <i class="fas fa-plus"></i>
                        Create Announcement
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Announcement Modal -->
<div id="announcementModal" class="modal">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h3>Create New Announcement</h3>
            <button class="modal-close" onclick="closeAnnouncementModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <div class="form-group">
                    <label>Announcement Title <span class="required">*</span></label>
                    <input type="text" name="announcement_title" class="form-control" required placeholder="e.g., System Maintenance Notice">
                </div>
                
                <div class="form-group">
                    <label>Message <span class="required">*</span></label>
                    <textarea name="announcement_message" class="form-control" rows="6" required placeholder="Write your announcement message here..."></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Type <span class="required">*</span></label>
                        <select name="announcement_type" class="form-control" required>
                            <option value="info">ℹ️ Information</option>
                            <option value="success">✅ Success / Good News</option>
                            <option value="warning">⚠️ Warning</option>
                            <option value="urgent">🚨 Urgent</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Priority <span class="required">*</span></label>
                        <select name="announcement_priority" class="form-control" required>
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Expiry Date (Optional)</label>
                    <input type="datetime-local" name="announcement_expiry" class="form-control">
                    <small class="form-help">Leave empty for permanent announcement</small>
                </div>

                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <span>This announcement will be visible to all users and sent as a notification.</span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeAnnouncementModal()">Cancel</button>
                <button type="submit" name="create_announcement" class="btn btn-primary">
                    <i class="fas fa-bullhorn"></i>
                    Create & Notify All Users
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Auto-hide flash message
document.addEventListener('DOMContentLoaded', function() {
    const alert = document.querySelector('.alert');
    if (alert) {
        setTimeout(() => {
            alert.style.animation = 'slideOut 0.3s ease-out forwards';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    }
});

// Announcement Modal
function openAnnouncementModal() {
    const modal = document.getElementById('announcementModal');
    modal.style.display = 'flex';
    setTimeout(() => modal.classList.add('show'), 10);
}

function closeAnnouncementModal() {
    const modal = document.getElementById('announcementModal');
    modal.classList.remove('show');
    setTimeout(() => modal.style.display = 'none', 300);
}

// Filter announcements
function filterAnnouncements(filter) {
    const cards = document.querySelectorAll('.announcement-card');
    const buttons = document.querySelectorAll('.filter-btn');
    
    // Update active button
    buttons.forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');
    
    // Filter cards
    cards.forEach(card => {
        const status = card.dataset.status;
        const expired = card.dataset.expired;
        
        let show = false;
        
        switch(filter) {
            case 'all':
                show = true;
                break;
            case 'active':
                show = status === 'active' && expired === 'no';
                break;
            case 'inactive':
                show = status === 'inactive' && expired === 'no';
                break;
            case 'expired':
                show = expired === 'yes';
                break;
        }
        
        card.style.display = show ? '' : 'none';
    });
}

// Close modal on outside click
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('show');
            setTimeout(() => this.style.display = 'none', 300);
        }
    });
});
</script>

</body>
</html>