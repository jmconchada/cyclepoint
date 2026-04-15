<?php
session_start();
require 'db.php';

// Protect admin page
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Handle delete action
if (isset($_POST['delete_listing'])) {
    $delete_id = (int)$_POST['delete_listing'];
    
    // Delete listing images first
    $sql_delete_images = "DELETE FROM listing_images WHERE listing_id = ?";
    $stmt_delete_images = $conn->prepare($sql_delete_images);
    $stmt_delete_images->bind_param("i", $delete_id);
    $stmt_delete_images->execute();
    
    // Delete the listing
    $sql_delete = "DELETE FROM listings WHERE id = ?";
    $stmt_delete = $conn->prepare($sql_delete);
    $stmt_delete->bind_param("i", $delete_id);
    $stmt_delete->execute();
    
    $_SESSION['flash_message'] = 'Listing deleted successfully.';
    $_SESSION['flash_type'] = 'success';
    header("Location: manage_posts.php");
    exit;
}

// Get listing ID from URL
$listing_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($listing_id === 0) {
    header("Location: manage_posts.php");
    exit;
}

// Fetch listing details with user information
$sql = "
    SELECT 
        l.*,
        u.name AS user_name,
        u.email AS user_email,
        u.profile_picture AS user_profile_picture,
        u.created_at AS user_joined,
        u.role AS user_role
    FROM listings l
    LEFT JOIN users u ON l.user_id = u.id
    WHERE l.id = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $listing_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['flash_message'] = 'Listing not found.';
    $_SESSION['flash_type'] = 'error';
    header("Location: manage_posts.php");
    exit;
}

$listing = $result->fetch_assoc();
$stmt->close();

// Fetch listing images
$sql_images = "SELECT * FROM listing_images WHERE listing_id = ? ORDER BY sort_order";
$stmt_images = $conn->prepare($sql_images);
$stmt_images->bind_param("i", $listing_id);
$stmt_images->execute();
$images_result = $stmt_images->get_result();
$images = [];
while ($img = $images_result->fetch_assoc()) {
    $images[] = $img;
}
$stmt_images->close();

// Count user's total listings
$sql_count = "SELECT COUNT(*) as total FROM listings WHERE user_id = ?";
$stmt_count = $conn->prepare($sql_count);
$stmt_count->bind_param("i", $listing['user_id']);
$stmt_count->execute();
$count_result = $stmt_count->get_result();
$user_listings_count = $count_result->fetch_assoc()['total'];
$stmt_count->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Listing - <?= htmlspecialchars($listing['title']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #1a1a2e;
            --secondary: #16213e;
            --accent: #0f3460;
            --accent-light: #533483;
            --danger: #e94560;
            --danger-dark: #c42847;
            --success: #06ffa5;
            --warning: #ffd93d;
            --info: #4ea8de;
            --gray-50: #fafafa;
            --gray-100: #f5f5f5;
            --gray-200: #eeeeee;
            --gray-300: #e0e0e0;
            --gray-400: #bdbdbd;
            --gray-500: #9e9e9e;
            --gray-600: #757575;
            --gray-700: #616161;
            --gray-800: #424242;
            --gray-900: #212121;
            --white: #ffffff;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            --shadow-2xl: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-xl: 20px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 20px;
            line-height: 1.6;
            color: var(--gray-900);
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 24px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            color: var(--gray-900);
            text-decoration: none;
            border-radius: var(--radius-lg);
            font-weight: 600;
            font-size: 14px;
            transition: var(--transition);
            box-shadow: var(--shadow-md);
            margin-bottom: 30px;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .back-button:hover {
            transform: translateX(-8px);
            background: var(--white);
            box-shadow: var(--shadow-xl);
        }

        .back-button i {
            transition: var(--transition);
        }

        .back-button:hover i {
            transform: translateX(-4px);
        }

        .listing-hero {
            background: linear-gradient(135deg, rgba(255,255,255,0.95) 0%, rgba(255,255,255,0.9) 100%);
            backdrop-filter: blur(20px);
            border-radius: var(--radius-xl);
            padding: 50px;
            margin-bottom: 30px;
            box-shadow: var(--shadow-2xl);
            border: 1px solid rgba(255, 255, 255, 0.5);
            position: relative;
            overflow: hidden;
        }

        .listing-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
        }

        .listing-hero h1 {
            font-family: 'Playfair Display', serif;
            font-size: 42px;
            font-weight: 800;
            margin-bottom: 20px;
            color: var(--primary);
            line-height: 1.2;
        }

        .hero-meta {
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
            align-items: center;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: var(--gray-600);
            font-weight: 500;
        }

        .meta-item i {
            color: var(--accent-light);
            font-size: 16px;
        }

        .content-layout {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 30px;
        }

        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: var(--radius-xl);
            padding: 40px;
            box-shadow: var(--shadow-xl);
            border: 1px solid rgba(255, 255, 255, 0.5);
            margin-bottom: 30px;
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--gray-100);
        }

        .card-header i {
            font-size: 24px;
            color: var(--accent-light);
        }

        .card-title {
            font-family: 'Playfair Display', serif;
            font-size: 28px;
            font-weight: 700;
            color: var(--primary);
        }

        .image-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 20px;
        }

        .gallery-item {
            position: relative;
            padding-bottom: 100%;
            border-radius: var(--radius-lg);
            overflow: hidden;
            cursor: pointer;
            box-shadow: var(--shadow-md);
            transition: var(--transition);
            background: var(--gray-100);
        }

        .gallery-item::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
            opacity: 0;
            transition: var(--transition);
            z-index: 1;
        }

        .gallery-item:hover::before {
            opacity: 1;
        }

        .gallery-item:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: var(--shadow-2xl);
        }

        .gallery-item img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .no-images {
            text-align: center;
            padding: 80px 20px;
            color: var(--gray-400);
        }

        .no-images i {
            font-size: 80px;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        .no-images p {
            font-size: 16px;
            font-weight: 500;
        }

        .description-content {
            font-size: 16px;
            line-height: 1.8;
            color: var(--gray-700);
            white-space: pre-wrap;
        }

        .detail-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .detail-item {
            display: grid;
            grid-template-columns: 180px 1fr;
            gap: 20px;
            padding: 20px;
            background: var(--gray-50);
            border-radius: var(--radius-md);
            transition: var(--transition);
        }

        .detail-item:hover {
            background: var(--gray-100);
        }

        .detail-label {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            color: var(--gray-800);
            font-size: 14px;
        }

        .detail-label i {
            color: var(--accent-light);
            font-size: 18px;
        }

        .detail-value {
            color: var(--gray-600);
            font-size: 15px;
            display: flex;
            align-items: center;
        }

        .category-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            box-shadow: var(--shadow-sm);
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

        .user-profile {
            position: sticky;
            top: 20px;
        }

        .user-header {
            text-align: center;
            padding-bottom: 30px;
            border-bottom: 2px solid var(--gray-100);
            margin-bottom: 30px;
        }

        .user-avatar {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            margin: 0 auto 20px;
            border: 5px solid var(--gray-100);
            object-fit: cover;
            box-shadow: var(--shadow-lg);
            transition: var(--transition);
        }

        .user-avatar:hover {
            transform: scale(1.05);
            box-shadow: var(--shadow-2xl);
        }

        .user-name {
            font-size: 26px;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 8px;
        }

        .user-email {
            color: var(--gray-500);
            font-size: 14px;
            font-weight: 500;
        }

        .user-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: linear-gradient(135deg, var(--gray-50) 0%, var(--gray-100) 100%);
            padding: 20px;
            border-radius: var(--radius-md);
            text-align: center;
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-md);
        }

        .stat-value {
            font-size: 32px;
            font-weight: 800;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 8px;
        }

        .stat-label {
            font-size: 12px;
            color: var(--gray-600);
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }

        .delete-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--danger) 0%, var(--danger-dark) 100%);
            color: white;
            border: none;
            border-radius: var(--radius-lg);
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: var(--shadow-md);
        }

        .delete-btn:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-2xl);
            background: linear-gradient(135deg, var(--danger-dark) 0%, #a02030 100%);
        }

        .delete-btn:active {
            transform: translateY(-2px);
        }

        .delete-btn i {
            font-size: 18px;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(10px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 20px;
            animation: fadeIn 0.3s ease;
        }

        .modal.show {
            display: flex;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-dialog {
            background: white;
            border-radius: var(--radius-xl);
            max-width: 500px;
            width: 100%;
            box-shadow: var(--shadow-2xl);
            animation: slideUp 0.3s ease;
            overflow: hidden;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            padding: 30px;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: linear-gradient(135deg, var(--danger) 0%, var(--danger-dark) 100%);
            color: white;
        }

        .modal-header h3 {
            font-size: 24px;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .modal-close {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 20px;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
        }

        .modal-close:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: rotate(90deg);
        }

        .modal-body {
            padding: 40px 30px;
        }

        .modal-body p {
            font-size: 16px;
            line-height: 1.6;
            color: var(--gray-700);
        }

        .modal-footer {
            padding: 20px 30px;
            background: var(--gray-50);
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }

        .btn {
            padding: 12px 28px;
            border: none;
            border-radius: var(--radius-md);
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-secondary {
            background: var(--gray-200);
            color: var(--gray-700);
        }

        .btn-secondary:hover {
            background: var(--gray-300);
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--danger) 0%, var(--danger-dark) 100%);
            color: white;
            box-shadow: var(--shadow-md);
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        /* Image Modal */
        .image-modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.95);
            backdrop-filter: blur(10px);
            z-index: 10000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .image-modal.show {
            display: flex;
        }

        .image-modal-content {
            max-width: 90vw;
            max-height: 90vh;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-2xl);
            animation: zoomIn 0.3s ease;
        }

        @keyframes zoomIn {
            from {
                opacity: 0;
                transform: scale(0.9);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .image-modal-close {
            position: absolute;
            top: 30px;
            right: 30px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: none;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 24px;
            color: var(--gray-700);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
            z-index: 10001;
            box-shadow: var(--shadow-xl);
        }

        .image-modal-close:hover {
            background: var(--danger);
            color: white;
            transform: rotate(90deg) scale(1.1);
        }

        .modal-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: none;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 24px;
            color: var(--gray-700);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
            z-index: 10001;
            box-shadow: var(--shadow-xl);
        }

        .modal-nav:hover {
            background: white;
            color: var(--accent-light);
            transform: translateY(-50%) scale(1.1);
        }

        .modal-nav-prev {
            left: 30px;
        }

        .modal-nav-next {
            right: 30px;
        }

        @media (max-width: 1024px) {
            .content-layout {
                grid-template-columns: 1fr;
            }

            .user-profile {
                position: relative;
                top: 0;
            }

            .listing-hero h1 {
                font-size: 32px;
            }
        }

        @media (max-width: 768px) {
            body {
                padding: 20px 10px;
            }

            .listing-hero {
                padding: 30px 20px;
            }

            .card {
                padding: 25px 20px;
            }

            .detail-item {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .image-gallery {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
                gap: 15px;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <a href="manage_posts.php" class="back-button">
        <i class="fas fa-arrow-left"></i>
        Back to Manage Posts
    </a>

    <div class="listing-hero">
        <h1><?= htmlspecialchars($listing['title']) ?></h1>
        <div class="hero-meta">
            <div class="meta-item">
                <i class="fas fa-calendar-alt"></i>
                <span>Posted <?= date('F j, Y', strtotime($listing['created_at'])) ?></span>
            </div>
            <div class="meta-item">
                <i class="fas fa-map-marker-alt"></i>
                <span><?= htmlspecialchars($listing['location'] ?? 'Not specified') ?></span>
            </div>
            <div class="meta-item">
                <i class="fas fa-hashtag"></i>
                <span>ID: <?= $listing['id'] ?></span>
            </div>
        </div>
    </div>

    <div class="content-layout">
        <div class="main-content">
            <!-- Images Section -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-images"></i>
                    <h2 class="card-title">Gallery (<?= count($images) ?>)</h2>
                </div>
                
                <?php if (count($images) > 0): ?>
                    <div class="image-gallery">
                        <?php foreach ($images as $index => $image): ?>
                            <div class="gallery-item" onclick="openImageModal(<?= $index ?>)">
                                <img src="<?= htmlspecialchars($image['path']) ?>" alt="Listing Image">
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="no-images">
                        <i class="fas fa-images"></i>
                        <p>No images available</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Description Section -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-align-left"></i>
                    <h2 class="card-title">Description</h2>
                </div>
                <div class="description-content">
                    <?= nl2br(htmlspecialchars($listing['description'])) ?>
                </div>
            </div>

            <!-- Details Section -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-info-circle"></i>
                    <h2 class="card-title">Listing Details</h2>
                </div>
                
                <div class="detail-list">
                    <div class="detail-item">
                        <div class="detail-label">
                            <i class="fas fa-tag"></i>
                            Category
                        </div>
                        <div class="detail-value">
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
                        </div>
                    </div>

                    <div class="detail-item">
                        <div class="detail-label">
                            <i class="fas fa-exchange-alt"></i>
                            Desired Trade
                        </div>
                        <div class="detail-value">
                            <?= htmlspecialchars($listing['desired_trade'] ?? 'Open to any offers') ?>
                        </div>
                    </div>

                    <div class="detail-item">
                        <div class="detail-label">
                            <i class="fas fa-map-marker-alt"></i>
                            Location
                        </div>
                        <div class="detail-value">
                            <?= htmlspecialchars($listing['location'] ?? 'Not specified') ?>
                        </div>
                    </div>

                    <div class="detail-item">
                        <div class="detail-label">
                            <i class="fas fa-clock"></i>
                            Posted On
                        </div>