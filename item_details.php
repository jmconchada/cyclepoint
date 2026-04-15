<?php
session_start();
require __DIR__ . '/db.php';

// Check if user is logged in
$logged_in_user_id = $_SESSION['user_id'] ?? 0;
$is_logged_in = $logged_in_user_id > 0;

// Get item ID
$item_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$item_id) die("Invalid item.");

// Fetch item details with created_at
$item_res = $conn->query("SELECT * FROM listings WHERE id = $item_id LIMIT 1");
$item = $item_res->fetch_assoc();
if (!$item) die("Item not found.");

// Fetch images
$images_res = $conn->query("SELECT path FROM listing_images WHERE listing_id = $item_id ORDER BY sort_order ASC");
$images = [];
while ($row = $images_res->fetch_assoc()) {
    $images[] = $row['path'];
}

// Get first image for chat preview
$first_image = !empty($images) ? $images[0] : 'assets/images/no-image.png';

// Fetch owner info
$user_id = $item['user_id'];
$user_res = $conn->query("SELECT id, name, profile_picture, created_at FROM users WHERE id = $user_id LIMIT 1");
$user = $user_res->fetch_assoc();

$ownerName = $user['name'] ?? 'Unknown User';

// Set default profile picture if not exists or file doesn't exist
$profilePic = 'assets/images/profile-picture.png';
if (!empty($user['profile_picture']) && file_exists($user['profile_picture'])) {
    $profilePic = $user['profile_picture'];
}

$memberSince = $user['created_at'] ?? null;

// Format dates
$posted_date = $item['created_at'] ? date('F d, Y', strtotime($item['created_at'])) : 'Unknown';
$posted_time = $item['created_at'] ? date('h:i A', strtotime($item['created_at'])) : '';

// Check if this is the owner viewing
$is_owner = ($logged_in_user_id == $user_id);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($item['title']) ?> — CyclePoint</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/item_details.css">
    
    <script>
        const isLoggedIn = <?= json_encode($is_logged_in) ?>;
        const itemOwnerId = <?= $user_id ?>;
        const itemTitle = <?= json_encode($item['title']) ?>;
        const itemImage = <?= json_encode($first_image) ?>;
        const isOwner = <?= json_encode($is_owner) ?>;
    </script>
</head>
<body>

<!-- HEADER -->
<header class="item-header">
    <div class="container">
        <div class="header-content">
            <button class="back-btn" onclick="window.history.back()" title="Go Back">
                <i class="fas fa-arrow-left"></i>
            </button>
            <h1 class="header-title">Item Details</h1>
            <div style="width: 40px;"></div> <!-- Spacer for centering -->
        </div>
    </div>
</header>

<!-- MAIN CONTENT -->
<main class="container my-4">
    <div class="row g-4">
        
        <!-- LEFT COLUMN: Images & Description -->
        <div class="col-lg-8">
            
            <!-- IMAGE GALLERY -->
            <div class="image-gallery-card">
                <div class="main-image-container">
                    <?php if (!empty($images)): ?>
                        <img id="mainImage" src="<?= htmlspecialchars($images[0]) ?>" alt="<?= htmlspecialchars($item['title']) ?>" class="main-image">
                    <?php else: ?>
                        <div class="no-image-placeholder">
                            <i class="fas fa-image"></i>
                            <p>No images available</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if (count($images) > 1): ?>
                <div class="thumbnail-gallery">
                    <?php foreach ($images as $index => $img): ?>
                        <img src="<?= htmlspecialchars($img) ?>" 
                             alt="Thumbnail <?= $index + 1 ?>" 
                             class="thumbnail <?= $index === 0 ? 'active' : '' ?>"
                             onclick="changeMainImage('<?= htmlspecialchars($img) ?>', this)">
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- ITEM DETAILS CARD -->
            <div class="details-card">
                <div class="item-badge">FOR TRADE</div>
                <h2 class="item-title"><?= htmlspecialchars($item['title']) ?></h2>
                
                <div class="item-meta">
                    <span><i class="fas fa-tag"></i> <?= htmlspecialchars($item['category']) ?></span>
                    <span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($item['location']) ?></span>
                    <span><i class="fas fa-clock"></i> Posted on <?= $posted_date ?> at <?= $posted_time ?></span>
                </div>
                
                <hr>
                
                <h3 class="section-title">Description</h3>
                <p class="item-description"><?= nl2br(htmlspecialchars($item['description'])) ?></p>
                
                <?php if (!empty($item['desired_trade'])): ?>
                <hr>
                <h3 class="section-title">Looking to Trade For</h3>
                <p class="desired-trade"><?= nl2br(htmlspecialchars($item['desired_trade'])) ?></p>
                <?php endif; ?>
            </div>
            
        </div>
        
        <!-- RIGHT COLUMN: Owner Info & Actions -->
        <div class="col-lg-4">
            
            <!-- OWNER CARD -->
            <div class="owner-card">
                <div class="owner-header">
                    <img src="<?= htmlspecialchars($profilePic) ?>" alt="<?= htmlspecialchars($ownerName) ?>" class="owner-avatar">
                    <div class="owner-info">
                        <h4 class="owner-name"><?= htmlspecialchars($ownerName) ?></h4>
                        <?php if ($memberSince): ?>
                        <p class="member-since">Member since <?= date('M Y', strtotime($memberSince)) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (!$is_owner): ?>
                <button class="chat-btn" onclick="startChatWithOwner()">
                    <i class="fas fa-comments"></i> Chat With Owner
                </button>
                <?php else: ?>
                <div class="owner-notice">
                    <i class="fas fa-info-circle"></i> This is your listing
                </div>
                <?php endif; ?>
            </div>
            
            <!-- LOCATION CARD -->
            <div class="location-card">
                <h3 class="card-title">
                    <i class="fas fa-map-marker-alt"></i> Location
                </h3>
                <p class="location-text"><?= htmlspecialchars($item['location']) ?></p>
                
                <div class="map-container">
                    <?php
                    // Map coordinates for specific locations (your pilot area)
                    $location = $item['location'];
                    $mapUrl = '';
                    
                    // Exact coordinates for Brgy. Cuyambay, Tanay, Rizal (your pilot beneficiary area)
                    if (stripos($location, 'cuyambay') !== false || stripos($location, 'tanay') !== false) {
                        // Brgy. Cuyambay, Tanay, Rizal coordinates
                        $mapUrl = 'https://maps.google.com/maps?q=14.5449,121.2867&t=&z=16&ie=UTF8&iwloc=&output=embed';
                    } 
                    // Antipolo City
                    elseif (stripos($location, 'antipolo') !== false) {
                        $mapUrl = 'https://maps.google.com/maps?q=14.5859,121.1758&t=&z=14&ie=UTF8&iwloc=&output=embed';
                    }
                    // Quezon City
                    elseif (stripos($location, 'quezon') !== false) {
                        $mapUrl = 'https://maps.google.com/maps?q=14.6760,121.0437&t=&z=13&ie=UTF8&iwloc=&output=embed';
                    }
                    // Marikina City
                    elseif (stripos($location, 'marikina') !== false) {
                        $mapUrl = 'https://maps.google.com/maps?q=14.6507,121.1029&t=&z=14&ie=UTF8&iwloc=&output=embed';
                    }
                    // Default: search by location name
                    else {
                        $mapUrl = 'https://maps.google.com/maps?q=' . urlencode($location) . '&t=&z=15&ie=UTF8&iwloc=&output=embed';
                    }
                    ?>
                    <iframe
                        width="100%"
                        height="300"
                        frameborder="0"
                        style="border:0; border-radius:8px;"
                        referrerpolicy="no-referrer-when-downgrade"
                        src="<?= $mapUrl ?>"
                        allowfullscreen>
                    </iframe>
                    <a href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($item['location']) ?>" 
                       target="_blank" 
                       class="view-larger-map">
                        <i class="fas fa-external-link-alt"></i> View on Google Maps
                    </a>
                    <p class="pilot-area-badge">
                        <?php if (stripos($location, 'cuyambay') !== false || stripos($location, 'tanay') !== false): ?>
                            <i class="fas fa-map-pin"></i> Pilot Beneficiary Area
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            
            <!-- SAFETY TIPS CARD -->
            <div class="safety-card">
                <h3 class="card-title">
                    <i class="fas fa-shield-alt"></i> Safety Tips
                </h3>
                <ul class="safety-tips">
                    <li><i class="fas fa-check-circle"></i> Meet in a public place</li>
                    <li><i class="fas fa-check-circle"></i> Inspect items before trading</li>
                    <li><i class="fas fa-check-circle"></i> Don't share personal financial info</li>
                    <li><i class="fas fa-check-circle"></i> Trust your instincts</li>
                </ul>
            </div>
            
        </div>
        
    </div>
</main>

<!-- FOOTER (Same as index.php) -->
<footer class="cp-footer">
    <div class="cp-wrap">
        <div class="cp-footer-top">
            <div class="cp-footer-col">
                <h4>About CyclePoint</h4>
                <p>CyclePoint is a web-based bartering platform. Trade used clothes, gadgets, appliances, and more — no money involved.</p>
            </div>
            <div class="cp-footer-col cp-social">
                <h4>Follow Us</h4>
                <div class="cp-social-links">
                    <a href="https://facebook.com" target="_blank"><i class="fa-brands fa-facebook-f"></i></a>
                    <a href="https://instagram.com" target="_blank"><i class="fa-brands fa-instagram"></i></a>
                </div>
            </div>
            <div class="cp-footer-col">
                <h4>Contact Us</h4>
                <p><i class="fa-solid fa-phone"></i> +63 905 371 6679</p>
                <p><i class="fa-solid fa-envelope"></i> Support@cyclepoint.com</p>
            </div>
        </div>
        <div class="cp-footer-bottom">
            <a class="cp-logo" href="index.php">
                <img src="assets/images/logo.png" alt="CyclePoint Logo" class="cp-logo-img">
            </a>
            <p>© <?= date('Y') ?> CyclePoint — All rights reserved.</p>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/page-transitions.js"></script>
<script src="assets/js/notification-badges.js"></script>
<script>
// Change main image when thumbnail clicked
function changeMainImage(imageSrc, thumbnailElement) {
    document.getElementById('mainImage').src = imageSrc;
    
    // Update active thumbnail
    document.querySelectorAll('.thumbnail').forEach(thumb => {
        thumb.classList.remove('active');
    });
    thumbnailElement.classList.add('active');
}

// Start chat with owner
function startChatWithOwner() {
    if (!isLoggedIn) {
        alert('Please login to chat with the seller');
        window.location.href = 'login.php';
        return;
    }
    
    if (isOwner) {
        alert('This is your own listing!');
        return;
    }
    
    // Redirect to chat with pre-filled message
    const message = encodeURIComponent(`Hi! Is this item still available for trading?\n\n"${itemTitle}"`);
    window.location.href = `chat.php?user=${itemOwnerId}&message=${message}&image=${encodeURIComponent(itemImage)}`;
}
</script>
<script src="assets/js/item_details.js"></script>
</body>
</html>