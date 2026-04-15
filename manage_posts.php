<?php
session_start();
require 'db.php';

// Protect admin page
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Handle listing deletion
if (isset($_GET['delete_listing'])) {
    $listing_id = (int)$_GET['delete_listing'];
    
    // Delete listing images first
    $stmt = $conn->prepare("DELETE FROM listing_images WHERE listing_id = ?");
    $stmt->bind_param("i", $listing_id);
    $stmt->execute();
    $stmt->close();
    
    // Delete listing
    $stmt = $conn->prepare("DELETE FROM listings WHERE id = ?");
    $stmt->bind_param("i", $listing_id);
    
    if ($stmt->execute()) {
        $_SESSION['flash_message'] = 'Listing deleted successfully.';
        $_SESSION['flash_type'] = 'success';
    } else {
        $_SESSION['flash_message'] = 'Error deleting listing: ' . $conn->error;
        $_SESSION['flash_type'] = 'error';
    }
    $stmt->close();
    
    header("Location: manage_posts.php");
    exit;
}

// Fetch listings with their images
$sql_listings = "
    SELECT 
        l.id AS listing_id, 
        l.user_id,
        l.title AS listing_title, 
        l.description AS listing_description, 
        l.category, 
        l.location, 
        l.desired_trade, 
        l.created_at AS listing_created_at,
        u.name AS user_name,
        u.email AS user_email,
        u.profile_picture AS user_profile_picture,
        GROUP_CONCAT(li.path ORDER BY li.sort_order SEPARATOR '|||') AS image_paths
    FROM listings l
    LEFT JOIN users u ON l.user_id = u.id
    LEFT JOIN listing_images li ON l.id = li.listing_id
    GROUP BY l.id, l.user_id, l.title, l.description, l.category, l.location, l.desired_trade, l.created_at, u.name, u.email, u.profile_picture
    ORDER BY l.created_at DESC
";

$result_listings = $conn->query($sql_listings);

if (!$result_listings) {
    die("SQL Error: " . $conn->error);
}

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total_listings,
    COUNT(DISTINCT l.user_id) as total_posters,
    COUNT(DISTINCT l.category) as total_categories,
    COUNT(li.id) as total_images
FROM listings l
LEFT JOIN listing_images li ON l.id = li.listing_id";

$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

// Get category breakdown
$category_query = "SELECT category, COUNT(*) as count FROM listings GROUP BY category ORDER BY count DESC";
$category_result = $conn->query($category_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Manage Posts - Admin Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/admindash.css">
    <style>
        /* Additional styles specific to listings */
        .listing-images {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .listing-images img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid var(--gray-200);
            transition: all var(--transition-base);
            cursor: pointer;
        }
        
        .listing-images img:hover {
            transform: scale(1.1);
            border-color: var(--electric);
            box-shadow: var(--shadow-md);
        }
        
        .image-count {
            font-size: 11px;
            color: var(--gray-500);
            font-weight: 600;
            padding: 4px 8px;
            background: var(--gray-100);
            border-radius: 12px;
        }
        
        .no-images {
            font-size: 12px;
            color: var(--gray-400);
            font-style: italic;
        }
        
        .listing-description {
            max-width: 250px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-size: 13px;
            color: var(--text-3);
        }
        
        .category-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            border-radius: 16px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        
        .category-electronics {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .category-furniture {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }
        
        .category-clothing {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
        }
        
        .category-books {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            color: white;
        }
        
        .category-other {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            color: white;
        }
        
        .listing-title {
            font-weight: 600;
            color: var(--text-1);
            font-size: 14px;
            max-width: 200px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        /* Image Modal */
        .image-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            z-index: 3000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .image-modal.show {
            display: flex;
        }
        
        .image-modal-content {
            max-width: 90%;
            max-height: 90%;
            border-radius: 12px;
            box-shadow: var(--shadow-xl);
        }
        
        .image-modal-close {
            position: absolute;
            top: 20px;
            right: 20px;
            background: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 20px;
            color: var(--text-2);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all var(--transition-base);
        }
        
        .image-modal-close:hover {
            background: var(--danger);
            color: white;
            transform: rotate(90deg);
        }
        
        .view-details-btn {
            background: var(--info);
            color: white;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all var(--transition-base);
        }
        
        .view-details-btn:hover {
            background: #2563eb;
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
    </style>
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
            <a href="manage_posts.php" class="nav-item active">
                <i class="fas fa-box-archive"></i><span>Manage Posts</span>
            </a>
            <div class="nav-section-label">Management</div>
            <a href="admindash.php" class="nav-item">
                <i class="fas fa-users-gear"></i><span>User Management</span>
            </a>
            <a href="announcements.php" class="nav-item">
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
                <h1 class="page-title">Manage Posts & Listings</h1>
                <p class="page-subtitle">Monitor and manage all user listings and trades</p>
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
                <i class="fas fa-boxes-stacked"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Total Listings</div>
                <div class="stat-value"><?= number_format($stats['total_listings']) ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon active">
                <i class="fas fa-user-tag"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Active Traders</div>
                <div class="stat-value"><?= number_format($stats['total_posters']) ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon restricted">
                <i class="fas fa-tags"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Categories</div>
                <div class="stat-value"><?= number_format($stats['total_categories']) ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon banned">
                <i class="fas fa-images"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Total Images</div>
                <div class="stat-value"><?= number_format($stats['total_images']) ?></div>
            </div>
        </div>
    </div>

    <!-- Listings Table -->
    <div class="table-container">
        <div class="table-header">
            <h2 class="table-title">All Listings</h2>
            <div class="table-actions">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search listings...">
                </div>
            </div>
        </div>

        <?php if ($result_listings && $result_listings->num_rows > 0): ?>
            <div class="table-wrapper">
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Title</th>
                            <th>Description</th>
                            <th>Category</th>
                            <th>Location</th>
                            <th>Desired Trade</th>
                            <th>Images</th>
                            <th>Posted</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php 
                    $result_listings->data_seek(0);
                    while($listing = $result_listings->fetch_assoc()): 
                        $defaultImage = 'assets/images/profile-picture.png';
                        $profilePicture = $listing['user_profile_picture'] ?? '';
                        
                        if (empty($profilePicture) || $profilePicture === $defaultImage) {
                            $displayImage = $defaultImage;
                        } else if (filter_var($profilePicture, FILTER_VALIDATE_URL)) {
                            $displayImage = $profilePicture;
                        } else {
                            $displayImage = $profilePicture;
                        }
                    ?>
                        <tr class="user-row listing-row">
                            <td>
                                <div class="user-id" style="font-weight: 600; color: var(--text-2);">
                                    #<?= htmlspecialchars($listing['listing_id']) ?>
                                </div>
                            </td>
                            <td>
                                <div class="user-cell">
                                    <img src="<?= htmlspecialchars($displayImage) ?>" 
                                         alt="Profile" 
                                         class="user-avatar"
                                         onerror="this.onerror=null; this.src='assets/img/profile-picture.png';">
                                    <div class="user-details">
                                        <div class="user-name"><?= htmlspecialchars($listing['user_name']) ?></div>
                                        <div class="user-id">ID: <?= htmlspecialchars($listing['user_id']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="listing-title" title="<?= htmlspecialchars($listing['listing_title']) ?>">
                                    <?= htmlspecialchars($listing['listing_title']) ?>
                                </div>
                            </td>
                            <td>
                                <div class="listing-description" title="<?= htmlspecialchars($listing['listing_description']) ?>">
                                    <?= htmlspecialchars($listing['listing_description']) ?>
                                </div>
                            </td>
                            <td>
                                <?php
                                $category = strtolower($listing['category']);
                                $categoryClass = 'category-other';
                                $categoryIcon = 'box';
                                
                                if (strpos($category, 'electronic') !== false) {
                                    $categoryClass = 'category-electronics';
                                    $categoryIcon = 'laptop';
                                } elseif (strpos($category, 'furniture') !== false) {
                                    $categoryClass = 'category-furniture';
                                    $categoryIcon = 'couch';
                                } elseif (strpos($category, 'clothing') !== false || strpos($category, 'fashion') !== false) {
                                    $categoryClass = 'category-clothing';
                                    $categoryIcon = 'shirt';
                                } elseif (strpos($category, 'book') !== false) {
                                    $categoryClass = 'category-books';
                                    $categoryIcon = 'book';
                                }
                                ?>
                                <span class="category-badge <?= $categoryClass ?>">
                                    <i class="fas fa-<?= $categoryIcon ?>"></i>
                                    <?= htmlspecialchars($listing['category']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="location-cell">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span><?= htmlspecialchars($listing['location'] ?? 'Not specified') ?></span>
                                </div>
                            </td>
                            <td>
                                <div class="listing-description">
                                    <?= htmlspecialchars($listing['desired_trade'] ?? 'Any') ?>
                                </div>
                            </td>
                            <td>
                                <div class="listing-images">
                                    <?php
                                    if (!empty($listing['image_paths'])) {
                                        $images = explode('|||', $listing['image_paths']);
                                        $count = 0;
                                        foreach ($images as $image_path) {
                                            if ($count < 3) {
                                                echo '<img src="' . htmlspecialchars($image_path) . '" 
                                                      alt="Listing Image" 
                                                      onclick="openImageModal(\'' . htmlspecialchars($image_path) . '\')">';
                                                $count++;
                                            }
                                        }
                                        if (count($images) > 3) {
                                            echo '<span class="image-count">+' . (count($images) - 3) . '</span>';
                                        }
                                    } else {
                                        echo '<span class="no-images">No images</span>';
                                    }
                                    ?>
                                </div>
                            </td>
                            <td>
                                <div class="date-cell">
                                    <?= date('M d, Y', strtotime($listing['listing_created_at'])) ?>
                                </div>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="view_listing.php?id=<?= $listing['listing_id'] ?>" 
                                       class="view-details-btn"
                                       target="_blank">
                                        <i class="fas fa-eye"></i>
                                        <span>View</span>
                                    </a>
                                    <button class="btn btn-delete" 
                                            onclick="handleDeleteListing(<?= $listing['listing_id'] ?>, '<?= htmlspecialchars($listing['listing_title']) ?>')">
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
                <i class="fas fa-boxes-stacked"></i>
                <h3>No Listings Found</h3>
                <p>There are currently no listings in the system.</p>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Delete Listing</h3>
            <button class="modal-close" onclick="closeDeleteModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <p id="deleteMessage"></p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
            <a href="#" id="deleteConfirmBtn" class="btn btn-danger">
                <i class="fas fa-trash"></i>
                Delete Listing
            </a>
        </div>
    </div>
</div>

<!-- Image Modal -->
<div id="imageModal" class="image-modal" onclick="closeImageModal()">
    <button class="image-modal-close">
        <i class="fas fa-times"></i>
    </button>
    <img id="modalImage" class="image-modal-content" src="" alt="Listing Image">
</div>

<script>
// Search functionality
document.getElementById('searchInput')?.addEventListener('input', function(e) {
    const searchTerm = e.target.value.toLowerCase();
    const rows = document.querySelectorAll('.listing-row');
    
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

// Delete Modal
function handleDeleteListing(listingId, listingTitle) {
    document.getElementById('deleteMessage').textContent = 
        `Are you sure you want to permanently delete "${listingTitle}"? This will also delete all associated images. This action cannot be undone.`;
    document.getElementById('deleteConfirmBtn').href = `manage_posts.php?delete_listing=${listingId}`;
    
    const modal = document.getElementById('deleteModal');
    modal.style.display = 'flex';
    setTimeout(() => modal.classList.add('show'), 10);
}

function closeDeleteModal() {
    const modal = document.getElementById('deleteModal');
    modal.classList.remove('show');
    setTimeout(() => modal.style.display = 'none', 300);
}

// Image Modal
function openImageModal(imageSrc) {
    const modal = document.getElementById('imageModal');
    const modalImage = document.getElementById('modalImage');
    modalImage.src = imageSrc;
    modal.classList.add('show');
}

function closeImageModal() {
    const modal = document.getElementById('imageModal');
    modal.classList.remove('show');
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

// Prevent image modal from closing when clicking the image
document.getElementById('modalImage')?.addEventListener('click', function(e) {
    e.stopPropagation();
});
</script>

</body>
</html>