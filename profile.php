<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

if (isset($_POST['delete_item'])) {
    $item_id = (int)$_POST['item_id'];
    $checkStmt = $conn->prepare("SELECT user_id FROM listings WHERE id = ? AND user_id = ?");
    $checkStmt->bind_param("ii", $item_id, $user_id);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    if ($result->num_rows > 0) {
        $conn->query("DELETE FROM listing_images WHERE listing_id = $item_id");
        $deleteStmt = $conn->prepare("DELETE FROM listings WHERE id = ?");
        $deleteStmt->bind_param("i", $item_id);
        if ($deleteStmt->execute()) {
            $_SESSION['success_message'] = "Item deleted successfully!";
        } else {
            $_SESSION['error_message'] = "Failed to delete item.";
        }
        $deleteStmt->close();
    }
    $checkStmt->close();
    header("Location: profile.php");
    exit;
}

$userStmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$userStmt->bind_param("i", $user_id);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();
$userStmt->close();

$itemsStmt = $conn->prepare("
    SELECT l.*, 
           (SELECT path FROM listing_images WHERE listing_id = l.id LIMIT 1) as image_path,
           (SELECT COUNT(*) FROM listing_images WHERE listing_id = l.id) as image_count
    FROM listings l 
    WHERE l.user_id = ? 
    ORDER BY l.created_at DESC
");
$itemsStmt->bind_param("i", $user_id);
$itemsStmt->execute();
$items = $itemsStmt->get_result();
$itemsStmt->close();

$profile_picture = !empty($user['profile_picture']) ? $user['profile_picture'] : 'assets/images/profile-picture.png';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile — CyclePoint</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/profile.css">
</head>
<body>

<!-- ── Topbar ── -->
<header class="topbar">
    <div class="topbar-inner">
        <a href="index.php" class="btn-back">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="15 18 9 12 15 6"></polyline>
            </svg>
            Back to Home
        </a>
        <div class="topbar-title">My Profile</div>
        <div class="topbar-right"></div>
    </div>
</header>

<!-- ── Page Body ── -->
<div class="profile-page">

    <!-- Flash Messages -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?= htmlspecialchars($_SESSION['success_message']) ?>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?= htmlspecialchars($_SESSION['error_message']) ?>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <!-- ── Profile Card ── -->
    <div class="profile-card">
        <div class="profile-stripe"></div>

        <div class="profile-body">
            <!-- Avatar -->
            <div class="profile-avatar-wrap">
                <img src="<?= htmlspecialchars($profile_picture) ?>" alt="Profile Picture">
                <div class="avatar-cam" title="Change photo" onclick="window.location='edit_profile.php'">
                    <i class="fas fa-camera"></i>
                </div>
            </div>

            <!-- Info -->
            <div class="profile-info">
                <h2><?= htmlspecialchars($user['name']) ?></h2>
                <ul class="meta-list">
                    <li>
                        <span class="meta-icon"><i class="fas fa-envelope"></i></span>
                        <?= htmlspecialchars($user['email']) ?>
                    </li>
                    <?php if (!empty($user['contact_number'])): ?>
                    <li>
                        <span class="meta-icon"><i class="fas fa-phone"></i></span>
                        <?= htmlspecialchars($user['contact_number']) ?>
                    </li>
                    <?php endif; ?>
                    <?php if (!empty($user['area'])): ?>
                    <li>
                        <span class="meta-icon"><i class="fas fa-map-marker-alt"></i></span>
                        <?= htmlspecialchars($user['area']) ?>
                    </li>
                    <?php endif; ?>
                    <li>
                        <span class="meta-icon"><i class="fas fa-calendar-alt"></i></span>
                        Joined <?= date('F Y', strtotime($user['created_at'])) ?>
                    </li>
                </ul>
            </div>

            <!-- Edit button -->
            <div class="profile-actions">
                <a href="edit_profile.php" class="btn-edit-profile">
                    <i class="fas fa-edit"></i>
                    Edit Profile
                </a>
            </div>
        </div>

        <!-- Stats row -->
        <div class="profile-stats">
            <div class="stat-item">
                <span class="stat-num"><?= $items->num_rows ?></span>
                <div class="stat-label">Items Posted</div>
            </div>
            <div class="stat-item">
                <span class="stat-num"><?= date('Y') - date('Y', strtotime($user['created_at'])) > 0 ? date('Y') - date('Y', strtotime($user['created_at'])) : '&lt;1' ?></span>
                <div class="stat-label">Years Active</div>
            </div>
            <div class="stat-item">
                <span class="stat-num"><?= !empty($user['area']) ? '✓' : '—' ?></span>
                <div class="stat-label">Location Set</div>
            </div>
        </div>
    </div>

    <!-- ── Posted Items ── -->
    <div class="items-section">
        <div class="section-header">
            <h2>My Posted Items</h2>
            <a href="post_item.php" class="btn-post-item">
                <i class="fas fa-plus"></i>
                Post New Item
            </a>
        </div>

        <?php if ($items->num_rows > 0): ?>
            <div class="items-grid">
                <?php while ($item = $items->fetch_assoc()): ?>
                    <div class="item-card">
                        <div class="item-image">
                            <?php if ($item['image_path']): ?>
                                <img src="<?= htmlspecialchars($item['image_path']) ?>" alt="<?= htmlspecialchars($item['title']) ?>">
                                <?php if ($item['image_count'] > 1): ?>
                                    <div class="image-count">
                                        <i class="fas fa-images"></i>
                                        <?= $item['image_count'] ?>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="no-image">
                                    <i class="fas fa-image"></i>
                                    <span>No Image</span>
                                </div>
                            <?php endif; ?>
                            <div class="item-badge">FOR TRADE</div>
                        </div>

                        <div class="item-content">
                            <h3 class="item-title"><?= htmlspecialchars($item['title']) ?></h3>
                            <div class="item-meta">
                                <span><i class="fas fa-tag"></i><?= htmlspecialchars($item['category']) ?></span>
                                <span><i class="fas fa-map-marker-alt"></i><?= htmlspecialchars($item['location']) ?></span>
                            </div>
                            <p class="item-description">
                                <?= htmlspecialchars(substr($item['description'], 0, 100)) ?><?= strlen($item['description']) > 100 ? '…' : '' ?>
                            </p>
                            <div class="item-date">
                                <i class="fas fa-clock"></i>
                                Posted <?= date('M d, Y', strtotime($item['created_at'])) ?>
                            </div>
                        </div>

                        <div class="item-actions">
                            <a href="item_details.php?id=<?= $item['id'] ?>" class="btn-action btn-view">
                                <i class="fas fa-eye"></i> View
                            </a>
                            <a href="edit_item.php?id=<?= $item['id'] ?>" class="btn-action btn-edit">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <button onclick="confirmDelete(<?= $item['id'] ?>, '<?= htmlspecialchars(addslashes($item['title'])) ?>')" class="btn-action btn-delete">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>

        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon"><i class="fas fa-box-open"></i></div>
                <h3>No Items Posted Yet</h3>
                <p>Start trading by posting your first item!</p>
                <a href="post_item.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Post Your First Item
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ── Delete Modal ── -->
<div id="deleteModal" class="modal">
    <div class="modal-overlay" onclick="closeDeleteModal()"></div>
    <div class="modal-content">
        <div class="modal-strip"></div>
        <div class="modal-header">
            <div class="modal-icon-wrap"><i class="fas fa-trash-alt"></i></div>
            <h3>Delete this item?</h3>
        </div>
        <div class="modal-body">
            <p>You're about to delete "<strong id="itemTitle"></strong>".</p>
            <p class="warning-text">This action cannot be undone. The item and all its images will be permanently removed.</p>
        </div>
        <div class="modal-footer">
            <button onclick="closeDeleteModal()" class="btn btn-secondary">
                <i class="fas fa-times"></i> Cancel
            </button>
            <form method="POST" style="flex:1;display:flex;">
                <input type="hidden" name="item_id" id="deleteItemId">
                <button type="submit" name="delete_item" class="btn btn-danger" style="flex:1;">
                    <i class="fas fa-trash"></i> Yes, Delete
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function confirmDelete(itemId, itemTitle) {
    document.getElementById('deleteItemId').value = itemId;
    document.getElementById('itemTitle').textContent = itemTitle;
    const modal = document.getElementById('deleteModal');
    modal.style.display = 'flex';
    setTimeout(() => modal.classList.add('show'), 10);
    document.body.style.overflow = 'hidden';
}
function closeDeleteModal() {
    const modal = document.getElementById('deleteModal');
    modal.classList.remove('show');
    setTimeout(() => { modal.style.display = 'none'; document.body.style.overflow = ''; }, 300);
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeDeleteModal(); });
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.alert').forEach(el => {
        setTimeout(() => {
            el.style.animation = 'slideOut 0.3s ease-out forwards';
            setTimeout(() => el.remove(), 300);
        }, 5000);
    });
});
</script>
</body>
</html>