<?php
session_start();
require 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Check if user is admin (check if role column exists and equals 'admin')
$admin_id = $_SESSION['user_id'];
$check_admin = $conn->prepare("SELECT role FROM users WHERE id=? LIMIT 1");
$check_admin->bind_param("i", $admin_id);
$check_admin->execute();
$admin_check_result = $check_admin->get_result();

if ($admin_check_result->num_rows > 0) {
    $admin_data = $admin_check_result->fetch_assoc();
    if (!isset($admin_data['role']) || $admin_data['role'] !== 'admin') {
        // User is not admin, redirect to regular user pages
        header("Location: index.php");
        exit;
    }
} else {
    header("Location: login.php");
    exit;
}
$check_admin->close();

// Fetch admin data
$stmt = $conn->prepare("SELECT name, email, profile_picture FROM users WHERE id=? LIMIT 1");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get statistics
// Total users (excluding admin)
$total_users = $conn->query("SELECT COUNT(*) as count FROM users WHERE role != 'admin' OR role IS NULL")->fetch_assoc()['count'];

// Active users (not banned, excluding admin)
$active_users = $conn->query("SELECT COUNT(*) as count FROM users WHERE (banned=0 OR banned IS NULL) AND (role != 'admin' OR role IS NULL)")->fetch_assoc()['count'];

// Restricted users (excluding admin)
$restricted_users = $conn->query("SELECT COUNT(*) as count FROM users WHERE restriction_type IS NOT NULL AND restriction_type != '' AND (restriction_expiry IS NULL OR restriction_expiry > NOW()) AND (role != 'admin' OR role IS NULL)")->fetch_assoc()['count'];

// Banned users (excluding admin)
$banned_users = $conn->query("SELECT COUNT(*) as count FROM users WHERE banned=1 AND (role != 'admin' OR role IS NULL)")->fetch_assoc()['count'];

// Total listings
$total_listings = $conn->query("SELECT COUNT(*) as count FROM listings")->fetch_assoc()['count'];

// Recent listings (last 7 days)
$recent_listings = $conn->query("SELECT COUNT(*) as count FROM listings WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)")->fetch_assoc()['count'];

// Total categories
$total_categories = $conn->query("SELECT COUNT(DISTINCT category) as count FROM listings")->fetch_assoc()['count'];

// Get recent activity - Latest 5 users (excluding admin)
$recent_users_query = "SELECT id, name, email, created_at FROM users WHERE role != 'admin' OR role IS NULL ORDER BY created_at DESC LIMIT 5";
$recent_users = $conn->query($recent_users_query);

// Get recent listings - Latest 5 listings
$recent_listings_query = "SELECT l.id, l.title, l.category, l.created_at, u.name as user_name 
                          FROM listings l 
                          JOIN users u ON l.user_id = u.id 
                          ORDER BY l.created_at DESC LIMIT 5";
$recent_listings_result = $conn->query($recent_listings_query);

$admin_name = $admin['name'];
$admin_email = $admin['email'];
$admin_initials = strtoupper(substr($admin_name, 0, 1));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - CyclePoint</title>
    <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
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
            <a href="dashboard.php" class="nav-item active">
                <i class="fas fa-chart-line"></i>
                <span>Dashboard</span>
            </a>
            <div class="nav-section-label">Management</div>
            <a href="manage_posts.php" class="nav-item">
                <i class="fas fa-box-archive"></i>
                <span>Manage Posts</span>
            </a>
            <a href="admindash.php" class="nav-item">
                <i class="fas fa-users-gear"></i>
                <span>User Management</span>
            </a>
            <a href="announcements.php" class="nav-item">
                <i class="fas fa-bullhorn"></i>
                <span>Announcements</span>
            </a>
            <a href="admin_trades.php" class="nav-item">
                <i class="fas fa-handshake"></i>
                <span>Trades &amp; Ratings</span>
            </a>
            <div class="nav-section-label">System</div>
            <a href="logout.php" class="nav-item logout">
                <i class="fas fa-arrow-right-from-bracket"></i>
                <span>Logout</span>
            </a>
        </nav>

        <div class="sidebar-footer">
            <div class="admin-profile">
                <div class="admin-avatar">
                    <?= $admin_initials ?>
                </div>
                <div class="admin-info">
                    <div class="admin-name"><?= htmlspecialchars($admin_name) ?></div>
                    <div class="admin-role">Administrator</div>
                </div>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        
        <!-- Page Header -->
        <header class="page-header">
            <div class="header-content">
                <h1 class="page-title">Dashboard Overview</h1>
                <p class="page-subtitle">Welcome back, <?= htmlspecialchars(explode(' ', $admin_name)[0]) ?>! Here's what's happening today.</p>
            </div>
        </header>

        <!-- Statistics Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon total">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Total Users</div>
                    <div class="stat-value"><?= $total_users ?></div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon active">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Active Users</div>
                    <div class="stat-value"><?= $active_users ?></div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon restricted">
                    <i class="fas fa-user-lock"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Restricted</div>
                    <div class="stat-value"><?= $restricted_users ?></div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon banned">
                    <i class="fas fa-user-slash"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Banned Users</div>
                    <div class="stat-value"><?= $banned_users ?></div>
                </div>
            </div>
        </div>

        <!-- Listings Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon amber">
                    <i class="fas fa-box"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Total Listings</div>
                    <div class="stat-value"><?= $total_listings ?></div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Recent (7 Days)</div>
                    <div class="stat-value"><?= $recent_listings ?></div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon purple">
                    <i class="fas fa-tags"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Categories</div>
                    <div class="stat-value"><?= $total_categories ?></div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(236,72,153,.15);color:#f472b6;box-shadow:0 0 16px rgba(236,72,153,.2);">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Growth Rate</div>
                    <div class="stat-value">+12%</div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="table-container" style="margin-bottom: 32px;">
            <div class="table-header">
                <h2 class="table-title">Quick Actions</h2>
            </div>
            <div style="padding: 32px; display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                <a href="manage_posts.php" class="stat-card" style="text-decoration: none; cursor: pointer;">
                    <div class="stat-icon purple">
                        <i class="fas fa-box-archive"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">Manage Posts</div>
                        <div style="font-size: 14px; color: var(--text-3); margin-top: 8px;">View and moderate listings</div>
                    </div>
                </a>

                <a href="admindash.php" class="stat-card" style="text-decoration: none; cursor: pointer;">
                    <div class="stat-icon active">
                        <i class="fas fa-users-gear"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">User Management</div>
                        <div style="font-size: 14px; color: var(--text-3); margin-top: 8px;">Manage users & restrictions</div>
                    </div>
                </a>

                <a href="announcements.php" class="stat-card" style="text-decoration: none; cursor: pointer;">
                    <div class="stat-icon amber">
                        <i class="fas fa-bullhorn"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">Announcements</div>
                        <div style="font-size: 14px; color: var(--text-3); margin-top: 8px;">Broadcast messages</div>
                    </div>
                </a>

                <a href="reports.php" class="stat-card" style="text-decoration: none; cursor: pointer;">
                    <div class="stat-icon banned">
                        <i class="fas fa-flag"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">Reports</div>
                        <div style="font-size: 14px; color: var(--text-3); margin-top: 8px;">Review user reports</div>
                    </div>
                </a>
            </div>
        </div>

        <!-- Recent Activity Section -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 32px;">
            
            <!-- Recent Users -->
            <div class="table-container">
                <div class="table-header">
                    <h2 class="table-title">Recent Users</h2>
                    <a href="admindash.php" class="btn btn-secondary btn-small">
                        <i class="fas fa-arrow-right"></i>
                        View All
                    </a>
                </div>
                <div class="table-wrapper">
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Email</th>
                                <th>Joined</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recent_users && $recent_users->num_rows > 0): ?>
                                <?php while ($user = $recent_users->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <div class="user-cell">
                                                <div class="user-avatar" style="background: linear-gradient(135deg, var(--electric) 0%, var(--electric-d) 100%); display: flex; align-items: center; justify-content: center; color: white; font-weight: 700;">
                                                    <?= strtoupper(substr($user['name'], 0, 1)) ?>
                                                </div>
                                                <div class="user-details">
                                                    <div class="user-name"><?= htmlspecialchars($user['name']) ?></div>
                                                    <div class="user-id">ID: <?= $user['id'] ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="date-cell"><?= htmlspecialchars($user['email']) ?></td>
                                        <td class="date-cell"><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3">
                                        <div class="empty-state">
                                            <i class="fas fa-users"></i>
                                            <p>No recent users</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent Listings -->
            <div class="table-container">
                <div class="table-header">
                    <h2 class="table-title">Recent Listings</h2>
                    <a href="manage_posts.php" class="btn btn-secondary btn-small">
                        <i class="fas fa-arrow-right"></i>
                        View All
                    </a>
                </div>
                <div class="table-wrapper">
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>User</th>
                                <th>Posted</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recent_listings_result && $recent_listings_result->num_rows > 0): ?>
                                <?php while ($listing = $recent_listings_result->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <div class="user-details">
                                                <div class="user-name"><?= htmlspecialchars($listing['title']) ?></div>
                                                <div class="user-id"><?= htmlspecialchars($listing['category']) ?></div>
                                            </div>
                                        </td>
                                        <td class="date-cell"><?= htmlspecialchars($listing['user_name']) ?></td>
                                        <td class="date-cell"><?= date('M d, Y', strtotime($listing['created_at'])) ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3">
                                        <div class="empty-state">
                                            <i class="fas fa-box"></i>
                                            <p>No recent listings</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <!-- System Info -->
        <div class="table-container">
            <div class="table-header">
                <h2 class="table-title">System Information</h2>
            </div>
            <div style="padding: 32px;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 24px;">
                    <div>
                        <div class="stat-label">Platform Version</div>
                        <div style="font-size: 18px; font-weight: 600; color: var(--text-1); margin-top: 8px;">CyclePoint v1.0</div>
                    </div>
                    <div>
                        <div class="stat-label">Database Status</div>
                        <div style="font-size: 18px; font-weight: 600; color: var(--success); margin-top: 8px;">
                            <i class="fas fa-circle" style="font-size: 8px; margin-right: 8px;"></i>
                            Connected
                        </div>
                    </div>
                    <div>
                        <div class="stat-label">Server Time</div>
                        <div style="font-size: 18px; font-weight: 600; color: var(--text-1); margin-top: 8px;"><?= date('h:i A') ?></div>
                    </div>
                    <div>
                        <div class="stat-label">Last Backup</div>
                        <div style="font-size: 18px; font-weight: 600; color: var(--text-1); margin-top: 8px;"><?= date('M d, Y') ?></div>
                    </div>
                </div>
            </div>
        </div>

    </main>

    <script>
        // Add animation to stats on load
        document.addEventListener('DOMContentLoaded', function() {
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach((card, index) => {
                card.style.animation = `fadeIn 0.4s ease-out ${index * 0.1}s both`;
            });
        });
    </script>

</body>
</html>