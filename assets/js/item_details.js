/**
 * Item Details Page JavaScript
 * Handles image gallery and chat functionality
 */

console.log("Item details page loaded.");

/**
 * Change main image when thumbnail is clicked
 */
function changeMainImage(imageSrc, thumbnailElement) {
    const mainImage = document.getElementById('mainImage');
    
    if (mainImage) {
        // Fade out effect
        mainImage.style.opacity = '0.5';
        
        setTimeout(() => {
            mainImage.src = imageSrc;
            mainImage.style.opacity = '1';
        }, 150);
    }
    
    // Update active thumbnail
    document.querySelectorAll('.thumbnail').forEach(thumb => {
        thumb.classList.remove('active');
    });
    
    if (thumbnailElement) {
        thumbnailElement.classList.add('active');
    }
}

/**
 * Start chat with owner - opens chat with pre-filled message
 */
function startChatWithOwner() {
    // Check if user is logged in (from global variable set in PHP)
    if (typeof isLoggedIn === 'undefined' || !isLoggedIn) {
        alert('Please login to chat with the seller');
        window.location.href = 'login.php';
        return;
    }
    
    // Check if viewing own item (from global variable set in PHP)
    if (typeof isOwner !== 'undefined' && isOwner) {
        alert('This is your own listing!');
        return;
    }
    
    // Get item details from global variables (set in PHP)
    const ownerId = typeof itemOwnerId !== 'undefined' ? itemOwnerId : 0;
    const title = typeof itemTitle !== 'undefined' ? itemTitle : 'this item';
    const image = typeof itemImage !== 'undefined' ? itemImage : '';
    
    if (!ownerId) {
        alert('Unable to start chat. Please try again.');
        return;
    }
    
    // Create pre-filled message
    const message = `Hi! Is this item still available for trading?\n\n"${title}"`;
    
    // Encode parameters for URL
    const encodedMessage = encodeURIComponent(message);
    const encodedImage = encodeURIComponent(image);
    
    // Redirect to chat page with parameters
    window.location.href = `chat.php?user=${ownerId}&message=${encodedMessage}&image=${encodedImage}`;
}

/**
 * Image zoom on hover (optional enhancement)
 */
document.addEventListener('DOMContentLoaded', function() {
    const mainImage = document.getElementById('mainImage');
    
    if (mainImage) {
        mainImage.addEventListener('click', function() {
            // Toggle fullscreen view
            if (this.requestFullscreen) {
                this.requestFullscreen();
            } else if (this.webkitRequestFullscreen) {
                this.webkitRequestFullscreen();
            } else if (this.msRequestFullscreen) {
                this.msRequestFullscreen();
            }
        });
        
        // Add cursor pointer to indicate clickable
        mainImage.style.cursor = 'pointer';
        mainImage.title = 'Click to view fullscreen';
    }
    
    // Smooth scroll to description when clicking "View More"
    const viewMoreButtons = document.querySelectorAll('[data-scroll-to]');
    viewMoreButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('data-scroll-to');
            const targetElement = document.getElementById(targetId);
            
            if (targetElement) {
                targetElement.scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'start' 
                });
            }
        });
    });
    
    // Add loading animation for images
    const allImages = document.querySelectorAll('.main-image, .thumbnail');
    allImages.forEach(img => {
        img.addEventListener('load', function() {
            this.style.animation = 'fadeIn 0.3s ease-in';
        });
    });
});

/**
 * Keyboard navigation for image gallery
 */
document.addEventListener('keydown', function(e) {
    const thumbnails = Array.from(document.querySelectorAll('.thumbnail'));
    const activeThumbnail = document.querySelector('.thumbnail.active');
    
    if (!activeThumbnail || thumbnails.length <= 1) return;
    
    const currentIndex = thumbnails.indexOf(activeThumbnail);
    let newIndex;
    
    // Left arrow key
    if (e.key === 'ArrowLeft') {
        newIndex = currentIndex > 0 ? currentIndex - 1 : thumbnails.length - 1;
        thumbnails[newIndex].click();
    }
    
    // Right arrow key
    if (e.key === 'ArrowRight') {
        newIndex = currentIndex < thumbnails.length - 1 ? currentIndex + 1 : 0;
        thumbnails[newIndex].click();
    }
});

// CSS animation keyframes (add this style dynamically)
const style = document.createElement('style');
style.textContent = `
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: scale(0.95);
        }
        to {
            opacity: 1;
            transform: scale(1);
        }
    }
    
    .main-image {
        transition: opacity 0.3s ease;
    }
`;
document.head.appendChild(style);