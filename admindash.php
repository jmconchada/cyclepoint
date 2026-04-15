<?php
session_start();
require 'db.php';

// Protect admin page
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Handle user restriction actions
if (isset($_POST['restrict_user'])) {
    $user_id = (int)$_POST['user_id'];
    $restriction_type = $_POST['restriction_type'];
    $restriction_reason = $_POST['restriction_reason'] ?? '';
    $restriction_duration = $_POST['restriction_duration'] ?? null;
    
    // Calculate expiry date if duration is set
    $expiry_date = null;
    if ($restriction_duration && $restriction_duration !== 'permanent') {
        $expiry_date = date('Y-m-d H:i:s', strtotime("+$restriction_duration"));
    }
    
    // Update user restrictions
    $stmt = $conn->prepare("UPDATE users SET 
        restriction_type = ?,
        restriction_reason = ?,
        restriction_date = NOW(),
        restriction_expiry = ?,
        banned = CASE WHEN ? = 'banned' THEN 1 ELSE 0 END
    WHERE id = ?");
    
    $stmt->bind_param("ssssi", $restriction_type, $restriction_reason, $expiry_date, $restriction_type, $user_id);
    
    if ($stmt->execute()) {
        $_SESSION['flash_message'] = 'User restriction applied successfully.';
        $_SESSION['flash_type'] = 'success';
    } else {
        $_SESSION['flash_message'] = 'Error applying restriction: ' . $conn->error;
        $_SESSION['flash_type'] = 'error';
    }
    $stmt->close();
    
    header("Location: admindash.php");
    exit;
}

// Handle remove restriction
if (isset($_POST['remove_restriction'])) {
    $user_id = (int)$_POST['user_id'];
    
    $stmt = $conn->prepare("UPDATE users SET 
        restriction_type = NULL,
        restriction_reason = NULL,
        restriction_date = NULL,
        restriction_expiry = NULL,
        banned = 0
    WHERE id = ?");
    
    $stmt->bind_param("i", $user_id);
    
    if ($stmt->execute()) {
        $_SESSION['flash_message'] = 'User restriction removed successfully.';
        $_SESSION['flash_type'] = 'success';
    } else {
        $_SESSION['flash_message'] = 'Error removing restriction: ' . $conn->error;
        $_SESSION['flash_type'] = 'error';
    }
    $stmt->close();
    
    header("Location: admindash.php");
    exit;
}

// Clear any pending results
while ($conn->more_results()) {
    $conn->next_result();
}

// Check columns
$checkRole = $conn->query("SHOW COLUMNS FROM users LIKE 'role'");
$hasRole = ($checkRole && $checkRole->num_rows > 0);

while ($conn->more_results()) {
    $conn->next_result();
}

// Build users query with restrictions
$sql_users = "SELECT id, email, name, profile_picture, created_at, area, contact_number, 
              restriction_type, restriction_reason, restriction_date, restriction_expiry, 
              COALESCE(banned, 0) as banned";

if ($hasRole) {
    $sql_users .= ", role";
}

$sql_users .= " FROM users ";

if ($hasRole) {
    $sql_users .= "WHERE (role != 'admin' OR role IS NULL) ";
}

$sql_users .= "ORDER BY id DESC";

$result_users = $conn->query($sql_users);

if (!$result_users) {
    die("Query failed: " . $conn->error);
}

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN banned = 0 OR banned IS NULL THEN 1 ELSE 0 END) as active,
    SUM(CASE WHEN banned = 1 THEN 1 ELSE 0 END) as banned,
    SUM(CASE WHEN restriction_type IS NOT NULL AND restriction_type != 'banned' THEN 1 ELSE 0 END) as restricted
FROM users";

if ($hasRole) {
    $stats_query .= " WHERE (role != 'admin' OR role IS NULL)";
}

$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - User Management</title>
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
        <a href="index.php" class="visit-site-btn" title="Visit Site">
            <i class="fas fa-arrow-up-right-from-square"></i>
            <span>Visit Site</span>
        </a>
    </div>
    
    <nav class="sidebar-nav">
        <div class="nav-section-label">Overview</div>
        <a href="dashboard.php" class="nav-item">
            <i class="fas fa-chart-line"></i>
            <span>Dashboard</span>
        </a>
        <div class="nav-section-label">Management</div>
        <a href="manage_posts.php" class="nav-item">
            <i class="fas fa-box-archive"></i>
            <span>Manage Posts</span>
        </a>
        <a href="admindash.php" class="nav-item active">
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
                <h1 class="page-title">User Management</h1>
                <p class="page-subtitle">Monitor users and manage account restrictions</p>
            </div>
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
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Total Users</div>
                <div class="stat-value"><?= number_format($stats['total']) ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon active">
                <i class="fas fa-user-check"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Active Users</div>
                <div class="stat-value"><?= number_format($stats['active']) ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon restricted">
                <i class="fas fa-user-lock"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Restricted</div>
                <div class="stat-value"><?= number_format($stats['restricted']) ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon banned">
                <i class="fas fa-user-slash"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Banned Users</div>
                <div class="stat-value"><?= number_format($stats['banned']) ?></div>
            </div>
        </div>
    </div>

    <!-- Users Table -->
    <div class="table-container">
        <div class="table-header">
            <h2 class="table-title">Registered Users</h2>
            <div class="table-actions">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search users...">
                </div>
            </div>
        </div>

        <?php if ($result_users && $result_users->num_rows > 0): ?>
            <div class="table-wrapper">
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Contact Information</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th>Restriction</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php 
                    $result_users->data_seek(0);
                    while($user = $result_users->fetch_assoc()): 
                        $isBanned = ((int)$user['banned'] === 1);
                        $hasRestriction = !empty($user['restriction_type']);
                        
                        $defaultImage = 'assets/images/profile-picture.png';
                        $profilePicture = $user['profile_picture'] ?? '';
                        
                        if (empty($profilePicture) || $profilePicture === $defaultImage) {
                            $displayImage = $defaultImage;
                        } else if (filter_var($profilePicture, FILTER_VALIDATE_URL)) {
                            $displayImage = $profilePicture;
                        } else {
                            $displayImage = $profilePicture;
                        }
                        
                        // Check if restriction is expired
                        $restrictionExpired = false;
                        if ($hasRestriction && $user['restriction_expiry']) {
                            $restrictionExpired = strtotime($user['restriction_expiry']) < time();
                        }
                    ?>
                        <tr class="user-row <?= $isBanned ? 'banned-row' : '' ?> <?= $hasRestriction ? 'restricted-row' : '' ?>">
                            <td>
                                <div class="user-cell">
                                    <img src="<?= htmlspecialchars($displayImage) ?>" 
                                         alt="Profile" 
                                         class="user-avatar"
                                         onerror="this.onerror=null; this.src='assets/images/profile-picture.png';">
                                    <div class="user-details">
                                        <div class="user-name"><?= htmlspecialchars($user['name']) ?></div>
                                        <div class="user-id">ID: <?= htmlspecialchars($user['id']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="contact-info">
                                    <div class="contact-item">
                                        <i class="fas fa-envelope"></i>
                                        <span><?= htmlspecialchars($user['email']) ?></span>
                                    </div>
                                    <div class="contact-item">
                                        <i class="fas fa-phone"></i>
                                        <span><?= htmlspecialchars($user['contact_number'] ?? 'Not provided') ?></span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="location-cell">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span><?= htmlspecialchars($user['area'] ?? 'Not specified') ?></span>
                                </div>
                            </td>
                            <td>
                                <?php if ($isBanned): ?>
                                    <span class="status-badge status-banned">
                                        <i class="fas fa-ban"></i>
                                        Banned
                                    </span>
                                <?php else: ?>
                                    <span class="status-badge status-active">
                                        <i class="fas fa-check-circle"></i>
                                        Active
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($hasRestriction && !$restrictionExpired): ?>
                                    <div class="restriction-info">
                                        <span class="restriction-badge restriction-<?= $user['restriction_type'] ?>">
                                            <?php 
                                            $restrictionIcons = [
                                                'banned' => 'ban',
                                                'read_only' => 'eye',
                                                'no_post' => 'edit-slash',
                                                'no_comment' => 'comment-slash',
                                                'limited' => 'hourglass-half'
                                            ];
                                            $icon = $restrictionIcons[$user['restriction_type']] ?? 'exclamation-circle';
                                            ?>
                                            <i class="fas fa-<?= $icon ?>"></i>
                                            <?= ucfirst(str_replace('_', ' ', $user['restriction_type'])) ?>
                                        </span>
                                        <?php if ($user['restriction_reason']): ?>
                                            <div class="restriction-reason" title="<?= htmlspecialchars($user['restriction_reason']) ?>">
                                                <i class="fas fa-info-circle"></i>
                                                <?= htmlspecialchars(substr($user['restriction_reason'], 0, 30)) ?>...
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php elseif ($restrictionExpired): ?>
                                    <span class="restriction-badge restriction-expired">
                                        <i class="fas fa-clock"></i>
                                        Expired
                                    </span>
                                <?php else: ?>
                                    <span class="no-restriction">None</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="date-cell">
                                    <?= date('M d, Y', strtotime($user['created_at'])) ?>
                                </div>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-restrict" 
                                            onclick="openRestrictionModal(<?= $user['id'] ?>, '<?= htmlspecialchars($user['name']) ?>', <?= $hasRestriction ? 'true' : 'false' ?>)">
                                        <i class="fas fa-gavel"></i>
                                        <span><?= $hasRestriction ? 'Modify' : 'Restrict' ?></span>
                                    </button>
                                    
                                    <?php if ($hasRestriction): ?>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Remove restriction for this user?')">
                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                            <button type="submit" name="remove_restriction" class="btn btn-success">
                                                <i class="fas fa-unlock"></i>
                                                <span>Remove</span>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <button class="btn btn-delete" 
                                            onclick="handleDelete(<?= $user['id'] ?>, '<?= htmlspecialchars($user['name']) ?>')">
                                        <i class="fas fa-trash-alt"></i>
                                        <span>Delete</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-users-slash"></i>
                <h3>No Users Found</h3>
                <p>There are currently no registered users in the system.</p>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- Restriction Modal -->
<div id="restrictionModal" class="modal">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h3 id="restrictionModalTitle">Apply User Restriction</h3>
            <button class="modal-close" onclick="closeRestrictionModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="user_id" id="restriction_user_id">
                
                <div class="form-group">
                    <label>Restriction Type <span class="required">*</span></label>
                    <select name="restriction_type" class="form-control" required>
                        <option value="">Select restriction type...</option>
                        <option value="banned">🚫 Banned - Complete account suspension</option>
                        <option value="read_only">👁️ Read Only - Can view but not interact</option>
                        <option value="no_post">✍️ No Posting - Cannot create new posts</option>
                        <option value="no_comment">💬 No Comments - Cannot comment on posts</option>
                        <option value="limited">⏳ Limited Access - Restricted features</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Reason for Restriction <span class="required">*</span></label>
                    <textarea name="restriction_reason" class="form-control" rows="3" required placeholder="Explain why this restriction is being applied..."></textarea>
                </div>
                
                <div class="form-group">
                    <label>Duration</label>
                    <select name="restriction_duration" class="form-control">
                        <option value="permanent">Permanent</option>
                        <option value="1 day">1 Day</option>
                        <option value="3 days">3 Days</option>
                        <option value="1 week">1 Week</option>
                        <option value="2 weeks">2 Weeks</option>
                        <option value="1 month">1 Month</option>
                        <option value="3 months">3 Months</option>
                        <option value="6 months">6 Months</option>
                        <option value="1 year">1 Year</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeRestrictionModal()">Cancel</button>
                <button type="submit" name="restrict_user" class="btn btn-danger">
                    <i class="fas fa-gavel"></i>
                    Apply Restriction
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Delete User</h3>
        </div>
        <div class="modal-body">
            <p id="deleteMessage"></p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
            <a href="#" id="deleteConfirmBtn" class="btn btn-danger">
                <i class="fas fa-trash"></i>
                Delete User
            </a>
        </div>
    </div>
</div>

<script>
// Search functionality
document.getElementById('searchInput')?.addEventListener('input', function(e) {
    const searchTerm = e.target.value.toLowerCase();
    const rows = document.querySelectorAll('.user-row');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
});

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

// Restriction Modal
function openRestrictionModal(userId, userName, hasRestriction) {
    document.getElementById('restriction_user_id').value = userId;
    document.getElementById('restrictionModalTitle').textContent = 
        (hasRestriction ? 'Modify' : 'Apply') + ' Restriction for ' + userName;
    
    const modal = document.getElementById('restrictionModal');
    modal.style.display = 'flex';
    setTimeout(() => modal.classList.add('show'), 10);
}

function closeRestrictionModal() {
    const modal = document.getElementById('restrictionModal');
    modal.classList.remove('show');
    setTimeout(() => modal.style.display = 'none', 300);
}

// Delete Modal
function handleDelete(userId, userName) {
    document.getElementById('deleteMessage').textContent = 
        `Are you sure you want to permanently delete "${userName}"? This action cannot be undone.`;
    document.getElementById('deleteConfirmBtn').href = `delete_user.php?type=local&id=${userId}`;
    
    const modal = document.getElementById('deleteModal');
    modal.style.display = 'flex';
    setTimeout(() => modal.classList.add('show'), 10);
}

function closeDeleteModal() {
    const modal = document.getElementById('deleteModal');
    modal.classList.remove('show');
    setTimeout(() => modal.style.display = 'none', 300);
}

// Close modals on outside click
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