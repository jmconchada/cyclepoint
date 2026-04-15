<?php
session_start();
require 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$item_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch item and verify ownership
$itemStmt = $conn->prepare("SELECT * FROM listings WHERE id = ? AND user_id = ?");
$itemStmt->bind_param("ii", $item_id, $user_id);
$itemStmt->execute();
$item = $itemStmt->get_result()->fetch_assoc();
$itemStmt->close();

if (!$item) {
    $_SESSION['error_message'] = "Item not found or you don't have permission to edit it.";
    header("Location: profile.php");
    exit;
}

// Fetch existing images
$imagesStmt = $conn->prepare("SELECT * FROM listing_images WHERE listing_id = ? ORDER BY id ASC");
$imagesStmt->bind_param("i", $item_id);
$imagesStmt->execute();
$images = $imagesStmt->get_result();
$imagesStmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_item'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $category = trim($_POST['category']);
    $location = trim($_POST['location']);
    
    // Validate
    if (empty($title) || empty($description) || empty($category) || empty($location)) {
        $_SESSION['error_message'] = "All fields are required.";
    } else {
        // Update item
        $updateStmt = $conn->prepare("UPDATE listings SET title = ?, description = ?, category = ?, location = ? WHERE id = ? AND user_id = ?");
        $updateStmt->bind_param("ssssii", $title, $description, $category, $location, $item_id, $user_id);
        
        if ($updateStmt->execute()) {
            // Handle new image uploads
            if (!empty($_FILES['new_images']['name'][0])) {
                $upload_dir = 'uploads/listings/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                $max_size = 5 * 1024 * 1024; // 5MB
                
                foreach ($_FILES['new_images']['tmp_name'] as $key => $tmp_name) {
                    if (empty($tmp_name)) continue;
                    
                    $file_name = $_FILES['new_images']['name'][$key];
                    $file_size = $_FILES['new_images']['size'][$key];
                    $file_type = $_FILES['new_images']['type'][$key];
                    
                    // Validate
                    if (!in_array($file_type, $allowed_types)) continue;
                    if ($file_size > $max_size) continue;
                    
                    // Generate unique filename
                    $extension = pathinfo($file_name, PATHINFO_EXTENSION);
                    $new_filename = 'item_' . $item_id . '_' . time() . '_' . rand(1000, 9999) . '.' . $extension;
                    $destination = $upload_dir . $new_filename;
                    
                    // Move file
                    if (move_uploaded_file($tmp_name, $destination)) {
                        // Insert to database
                        $imgStmt = $conn->prepare("INSERT INTO listing_images (listing_id, path) VALUES (?, ?)");
                        $imgStmt->bind_param("is", $item_id, $destination);
                        $imgStmt->execute();
                        $imgStmt->close();
                    }
                }
            }
            
            $_SESSION['success_message'] = "Item updated successfully!";
            header("Location: profile.php");
            exit;
        } else {
            $_SESSION['error_message'] = "Failed to update item.";
        }
        $updateStmt->close();
    }
}

// Handle image deletion
if (isset($_POST['delete_image'])) {
    $image_id = (int)$_POST['image_id'];
    
    // Get image path
    $imgStmt = $conn->prepare("SELECT path FROM listing_images WHERE id = ? AND listing_id = ?");
    $imgStmt->bind_param("ii", $image_id, $item_id);
    $imgStmt->execute();
    $imgResult = $imgStmt->get_result();
    
    if ($imgRow = $imgResult->fetch_assoc()) {
        // Delete file
        if (file_exists($imgRow['path'])) {
            unlink($imgRow['path']);
        }
        
        // Delete from database
        $delStmt = $conn->prepare("DELETE FROM listing_images WHERE id = ?");
        $delStmt->bind_param("i", $image_id);
        $delStmt->execute();
        $delStmt->close();
        
        $_SESSION['success_message'] = "Image deleted successfully!";
    }
    $imgStmt->close();
    
    header("Location: edit_item.php?id=$item_id");
    exit;
}

// Categories
$categories = [
    'Used Clothes / Shoes / Bags',
    'Used Gadgets & Electronics',
    'Used Home Appliances',
    'Furniture & Home Items',
    'Homegrown Food / Goods',
    'Tools & Equipment'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Item - CyclePoint</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/edit_item.css">
</head>
<body>

<div class="edit-page">
    
    <!-- Header -->
    <div class="page-header">
        <a href="profile.php" class="btn-back">
            <i class="fas fa-arrow-left"></i>
            <span>Back to Profile</span>
        </a>
        <h1><i class="fas fa-edit"></i> Edit Item</h1>
    </div>

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

    <div class="edit-container">
        
        <!-- Left Side: Form -->
        <div class="form-section">
            <div class="section-card">
                <div class="card-header">
                    <h2><i class="fas fa-info-circle"></i> Item Details</h2>
                </div>
                
                <form method="POST" enctype="multipart/form-data" class="edit-form">
                    
                    <!-- Title -->
                    <div class="form-group">
                        <label for="title">
                            <i class="fas fa-heading"></i>
                            Item Title <span class="required">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="title" 
                            name="title" 
                            class="form-control" 
                            value="<?= htmlspecialchars($item['title']) ?>" 
                            required
                            placeholder="e.g., Mountain Bike"
                        >
                    </div>

                    <!-- Description -->
                    <div class="form-group">
                        <label for="description">
                            <i class="fas fa-align-left"></i>
                            Description <span class="required">*</span>
                        </label>
                        <textarea 
                            id="description" 
                            name="description" 
                            class="form-control" 
                            rows="6" 
                            required
                            placeholder="Describe your item in detail..."
                        ><?= htmlspecialchars($item['description']) ?></textarea>
                        <small class="form-help">
                            <i class="fas fa-lightbulb"></i>
                            Include condition, age, features, and what you're looking to trade for
                        </small>
                    </div>

                    <!-- Category -->
                    <div class="form-group">
                        <label for="category">
                            <i class="fas fa-tag"></i>
                            Category <span class="required">*</span>
                        </label>
                        <select id="category" name="category" class="form-control" required>
                            <option value="">Choose a category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat) ?>" <?= $item['category'] === $cat ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Location -->
                    <div class="form-group">
                        <label for="location">
                            <i class="fas fa-map-marker-alt"></i>
                            Location <span class="required">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="location" 
                            name="location" 
                            class="form-control" 
                            value="<?= htmlspecialchars($item['location']) ?>" 
                            required
                            placeholder="e.g., Manila, Philippines"
                        >
                    </div>

                    <!-- Add New Images -->
                    <div class="form-group">
                        <label for="new_images">
                            <i class="fas fa-images"></i>
                            Add New Images
                        </label>
                        <div class="file-upload-area" id="fileUploadArea">
                            <input 
                                type="file" 
                                id="new_images" 
                                name="new_images[]" 
                                class="file-input" 
                                accept="image/*" 
                                multiple
                            >
                            <div class="upload-content">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <p>Click to upload or drag and drop</p>
                                <small>PNG, JPG, GIF, WEBP (Max 5MB each)</small>
                            </div>
                        </div>
                        <div id="newImagePreview" class="image-preview-grid"></div>
                    </div>

                    <!-- Submit Button -->
                    <div class="form-actions">
                        <a href="profile.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i>
                            Cancel
                        </a>
                        <button type="submit" name="update_item" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            Save Changes
                        </button>
                    </div>

                </form>
            </div>
        </div>

        <!-- Right Side: Current Images -->
        <div class="images-section">
            <div class="section-card">
                <div class="card-header">
                    <h2><i class="fas fa-image"></i> Current Images</h2>
                    <span class="image-count"><?= $images->num_rows ?> image(s)</span>
                </div>
                
                <?php if ($images->num_rows > 0): ?>
                    <div class="current-images-grid">
                        <?php 
                        $images->data_seek(0); // Reset pointer
                        while ($img = $images->fetch_assoc()): 
                        ?>
                            <div class="image-item">
                                <img src="<?= htmlspecialchars($img['path']) ?>" alt="Item Image">
                                <div class="image-overlay">
                                    <button 
                                        type="button" 
                                        class="btn-delete-image" 
                                        onclick="confirmDeleteImage(<?= $img['id'] ?>)"
                                        title="Delete Image"
                                    >
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="no-images">
                        <i class="fas fa-image"></i>
                        <p>No images yet</p>
                        <small>Add images using the form</small>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<!-- Delete Image Modal -->
<div id="deleteImageModal" class="modal">
    <div class="modal-overlay" onclick="closeImageModal()"></div>
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-icon delete">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h3>Delete Image?</h3>
        </div>
        <div class="modal-body">
            <p>Are you sure you want to delete this image?</p>
            <p class="warning-text">This action cannot be undone.</p>
        </div>
        <div class="modal-footer">
            <button onclick="closeImageModal()" class="btn btn-secondary">
                <i class="fas fa-times"></i>
                Cancel
            </button>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="image_id" id="deleteImageId">
                <button type="submit" name="delete_image" class="btn btn-danger">
                    <i class="fas fa-trash"></i>
                    Yes, Delete
                </button>
            </form>
        </div>
    </div>
</div>

<script>
// File upload preview
const fileInput = document.getElementById('new_images');
const fileUploadArea = document.getElementById('fileUploadArea');
const previewGrid = document.getElementById('newImagePreview');

fileInput.addEventListener('change', function() {
    previewGrid.innerHTML = '';
    
    if (this.files.length > 0) {
        Array.from(this.files).forEach((file, index) => {
            const reader = new FileReader();
            reader.onload = function(e) {
                const previewItem = document.createElement('div');
                previewItem.className = 'preview-item';
                previewItem.innerHTML = `
                    <img src="${e.target.result}" alt="Preview ${index + 1}">
                    <div class="preview-name">${file.name}</div>
                `;
                previewGrid.appendChild(previewItem);
            };
            reader.readAsDataURL(file);
        });
    }
});

// Drag and drop
fileUploadArea.addEventListener('dragover', (e) => {
    e.preventDefault();
    fileUploadArea.classList.add('dragover');
});

fileUploadArea.addEventListener('dragleave', () => {
    fileUploadArea.classList.remove('dragover');
});

fileUploadArea.addEventListener('drop', (e) => {
    e.preventDefault();
    fileUploadArea.classList.remove('dragover');
    fileInput.files = e.dataTransfer.files;
    fileInput.dispatchEvent(new Event('change'));
});

// Delete image modal
function confirmDeleteImage(imageId) {
    document.getElementById('deleteImageId').value = imageId;
    const modal = document.getElementById('deleteImageModal');
    modal.style.display = 'flex';
    setTimeout(() => modal.classList.add('show'), 10);
    document.body.style.overflow = 'hidden';
}

function closeImageModal() {
    const modal = document.getElementById('deleteImageModal');
    modal.classList.remove('show');
    setTimeout(() => {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }, 300);
}

// Close on escape
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        closeImageModal();
    }
});

// Auto-hide alerts
document.addEventListener('DOMContentLoaded', () => {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.animation = 'slideOut 0.3s ease-out forwards';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });
});
</script>

</body>
</html>