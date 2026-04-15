/**
 * CyclePoint Smooth Page Transitions
 * Add this script to all pages for elegant navigation
 */

(function() {
  'use strict';

  // Page transition duration (matches CSS animation)
  const TRANSITION_DURATION = 300; // milliseconds

  /**
   * Initialize smooth page transitions
   */
  function initPageTransitions() {
    // Get all internal links
    const links = document.querySelectorAll('a[href]:not([href^="#"]):not([href^="mailto:"]):not([href^="tel:"]):not([target="_blank"])');
    
    links.forEach(link => {
      // Skip if already has listener
      if (link.hasAttribute('data-transition-ready')) return;
      
      link.setAttribute('data-transition-ready', 'true');
      link.addEventListener('click', handleLinkClick);
    });
  }

  /**
   * Handle link click with smooth transition
   */
  function handleLinkClick(e) {
    const link = e.currentTarget;
    const href = link.getAttribute('href');
    
    // Skip if link has special attributes
    if (link.hasAttribute('download') || 
        link.getAttribute('target') === '_blank' ||
        !href || 
        href.startsWith('#') || 
        href.startsWith('mailto:') || 
        href.startsWith('tel:')) {
      return;
    }

    // Prevent default navigation
    e.preventDefault();
    
    // Add exit animation
    document.body.classList.add('page-exit');
    
    // Navigate after animation completes
    setTimeout(() => {
      window.location.href = href;
    }, TRANSITION_DURATION);
  }

  /**
   * Smooth category filter transitions
   */
  function initCategoryTransitions() {
    const categories = document.querySelectorAll('.category');
    const grid = document.querySelector('.cp-grid');
    
    if (!categories.length || !grid) return;
    
    categories.forEach(category => {
      category.addEventListener('click', function() {
        // Remove active from all
        categories.forEach(cat => cat.classList.remove('active'));
        
        // Add active to clicked
        this.classList.add('active');
        
        // Animate grid items
        const cards = grid.querySelectorAll('.cp-card');
        
        // First fade out
        cards.forEach((card, index) => {
          card.style.animation = 'none';
          card.style.opacity = '0';
          card.style.transform = 'translateY(20px)';
        });
        
        // Then fade in with stagger
        setTimeout(() => {
          cards.forEach((card, index) => {
            setTimeout(() => {
              card.style.animation = 'cardFadeIn 0.5s ease-out forwards';
            }, index * 50);
          });
        }, 100);
      });
    });
  }

  /**
   * Add smooth scroll behavior
   */
  function initSmoothScroll() {
    // Smooth scroll to top on page load
    window.scrollTo({
      top: 0,
      behavior: 'instant'
    });
    
    // Smooth scroll for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
      anchor.addEventListener('click', function(e) {
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
          e.preventDefault();
          target.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
          });
        }
      });
    });
  }

  /**
   * Page reveal on load
   */
  function initPageReveal() {
    // Ensure body is visible
    document.body.style.opacity = '1';
    
    // Remove any lingering exit classes
    document.body.classList.remove('page-exit');
  }

  /**
   * Handle browser back/forward buttons
   */
  function initHistoryTransition() {
    window.addEventListener('pageshow', function(event) {
      // If page is restored from cache
      if (event.persisted) {
        document.body.classList.remove('page-exit');
        document.body.style.animation = 'pageLoad 0.5s ease-out';
      }
    });
  }

  /**
   * Initialize all transitions
   */
  function init() {
    // Wait for DOM to be ready
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', init);
      return;
    }
    
    initPageReveal();
    initPageTransitions();
    initCategoryTransitions();
    initSmoothScroll();
    initHistoryTransition();
    
    console.log('✨ CyclePoint smooth transitions initialized');
  }

  // Start initialization
  init();

  // Re-initialize on dynamic content changes (for AJAX/dynamic loading)
  if (window.MutationObserver) {
    const observer = new MutationObserver(function(mutations) {
      // Check if new links were added
      const hasNewLinks = mutations.some(mutation => 
        Array.from(mutation.addedNodes).some(node => 
          node.nodeName === 'A' || (node.querySelectorAll && node.querySelectorAll('a').length > 0)
        )
      );
      
      if (hasNewLinks) {
        initPageTransitions();
      }
    });
    
    observer.observe(document.body, {
      childList: true,
      subtree: true
    });
  }

})();