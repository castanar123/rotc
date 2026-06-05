/**
 * ROTC Activities Gallery Carousel
 * Interactive carousel with touch/drag support, auto-play, and mobile responsiveness
 */

class ROTCGalleryCarousel {
    constructor() {
        this.currentSlide = 0;
        this.totalSlides = 0;
        this.isAutoPlaying = true;
        this.autoPlayInterval = null;
        this.autoPlayDelay = 5000; // 5 seconds
        this.isDragging = false;
        this.startX = 0;
        this.currentX = 0;
        this.startY = 0;
        this.currentY = 0;
        this.threshold = 50; // Minimum drag distance to trigger slide change
        this.velocityThreshold = 0.5; // Minimum velocity for swipe detection
        this.startTime = 0;
        this.isMobile = this.detectMobile();
        this.isVerticalScroll = false;
        
        this.init();
    }

    init() {
        // Wait for DOM to be ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.setup());
        } else {
            this.setup();
        }
    }

    setup() {
        this.carousel = document.querySelector('.gallery-carousel');
        this.track = document.querySelector('.carousel-track');
        this.slides = document.querySelectorAll('.carousel-slide');
        this.prevBtn = document.querySelector('.carousel-prev');
        this.nextBtn = document.querySelector('.carousel-next');
        this.indicatorsContainer = document.querySelector('.carousel-indicators');
        
        if (!this.carousel || !this.track || !this.slides.length) {
            console.warn('Gallery carousel elements not found');
            return;
        }

        this.totalSlides = this.slides.length;
        this.createIndicators();
        this.bindEvents();
        this.showSlide(0);
        this.startAutoPlay();
        this.optimizeImages();
    }

    createIndicators() {
        if (!this.indicatorsContainer) return;
        
        this.indicatorsContainer.innerHTML = '';
        
        for (let i = 0; i < this.totalSlides; i++) {
            const dot = document.createElement('div');
            dot.className = 'indicator-dot';
            dot.setAttribute('data-slide', i);
            dot.addEventListener('click', () => this.goToSlide(i));
            this.indicatorsContainer.appendChild(dot);
        }
    }

    bindEvents() {
        // Navigation buttons
        if (this.prevBtn) {
            this.prevBtn.addEventListener('click', () => this.prevSlide());
        }
        if (this.nextBtn) {
            this.nextBtn.addEventListener('click', () => this.nextSlide());
        }

        // Enhanced Touch/Mouse events for dragging
        this.bindDragEvents();

        // Fullscreen modal events
        this.bindFullscreenEvents();

        // Keyboard navigation
        document.addEventListener('keydown', (e) => this.handleKeyboard(e));

        // Pause auto-play on hover
        this.carousel.addEventListener('mouseenter', () => this.pauseAutoPlay());
        this.carousel.addEventListener('mouseleave', () => this.startAutoPlay());

        // Handle visibility change (pause when tab is not active)
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.pauseAutoPlay();
            } else {
                this.startAutoPlay();
            }
        });

        // Resize handler for responsive behavior
        window.addEventListener('resize', () => this.handleResize());
    }

    // Enhanced Touch/Mouse drag functionality for mobile
    bindDragEvents() {
        let startX = 0;
        let startY = 0;
        let currentX = 0;
        let currentY = 0;
        let startTime = 0;
        let isHorizontalSwipe = false;
        
        const handleStart = (e) => {
            if (this.isDragging) return;
            
            this.isDragging = true;
            startTime = Date.now();
            isHorizontalSwipe = false;
            
            if (e.type === 'mousedown') {
                startX = e.clientX;
                startY = e.clientY;
                document.addEventListener('mousemove', handleMove);
                document.addEventListener('mouseup', handleEnd);
            } else {
                startX = e.touches[0].clientX;
                startY = e.touches[0].clientY;
                document.addEventListener('touchmove', handleMove, { passive: false });
                document.addEventListener('touchend', handleEnd);
            }
            
            this.track.style.transition = 'none';
            this.pauseAutoPlay();
        };
        
        const handleMove = (e) => {
            if (!this.isDragging) return;
            
            currentX = e.type === 'mousemove' ? e.clientX : e.touches[0].clientX;
            currentY = e.type === 'mousemove' ? e.clientY : e.touches[0].clientY;
            
            const deltaX = currentX - startX;
            const deltaY = currentY - startY;
            
            // Determine if this is a horizontal swipe
            if (!isHorizontalSwipe && (Math.abs(deltaX) > 10 || Math.abs(deltaY) > 10)) {
                isHorizontalSwipe = Math.abs(deltaX) > Math.abs(deltaY);
            }
            
            // Only prevent default and handle horizontal movement
            if (isHorizontalSwipe) {
                e.preventDefault();
                
                const currentTransform = -this.currentSlide * 100;
                const movePercent = (deltaX / this.track.offsetWidth) * 100;
                const newTransform = currentTransform + movePercent;
                
                // Add resistance at boundaries
                let finalTransform = newTransform;
                const maxTransform = 0;
                const minTransform = -(this.slides.length - 1) * 100;
                
                if (newTransform > maxTransform) {
                    finalTransform = maxTransform + (newTransform - maxTransform) * 0.3;
                } else if (newTransform < minTransform) {
                    finalTransform = minTransform + (newTransform - minTransform) * 0.3;
                }
                
                this.track.style.transform = `translateX(${finalTransform}%)`;
            }
        };
        
        const handleEnd = (e) => {
            if (!this.isDragging) return;
            
            this.isDragging = false;
            const endTime = Date.now();
            const deltaX = currentX - startX;
            const deltaTime = endTime - startTime;
            const velocity = Math.abs(deltaX) / deltaTime;
            
            // Only change slides if it was a horizontal swipe
            if (isHorizontalSwipe) {
                // More sensitive thresholds for mobile
                const threshold = this.isMobile ? 
                    this.track.offsetWidth * 0.15 : // 15% for mobile
                    this.track.offsetWidth * 0.25;  // 25% for desktop
                
                const velocityThreshold = this.isMobile ? 0.3 : 0.5;
                const shouldChangeSlide = Math.abs(deltaX) > threshold || velocity > velocityThreshold;
                
                if (shouldChangeSlide) {
                    if (deltaX > 0) {
                        this.prevSlide();
                    } else {
                        this.nextSlide();
                    }
                } else {
                    this.goToSlide(this.currentSlide);
                }
            } else {
                // If not horizontal swipe, just reset position
                this.goToSlide(this.currentSlide);
            }
            
            // Resume auto-play after interaction
            setTimeout(() => {
                this.startAutoPlay();
            }, 3000);
            
            // Clean up event listeners
            document.removeEventListener('mousemove', handleMove);
            document.removeEventListener('mouseup', handleEnd);
            document.removeEventListener('touchmove', handleMove);
            document.removeEventListener('touchend', handleEnd);
        };
        
        // Add event listeners with better mobile support
        this.track.addEventListener('mousedown', handleStart);
        this.track.addEventListener('touchstart', handleStart, { passive: true });
        
        // Prevent context menu on long press
        this.track.addEventListener('contextmenu', (e) => e.preventDefault());
        
        // Prevent image dragging
        this.track.addEventListener('dragstart', (e) => e.preventDefault());
    }

    // Fullscreen Modal Functionality
    bindFullscreenEvents() {
        // Add click listeners to all activity images
        const activityImages = this.carousel.querySelectorAll('.activity-image');
        
        activityImages.forEach((img, index) => {
            img.style.cursor = 'pointer';
            img.addEventListener('click', (e) => {
                e.stopPropagation();
                this.openFullscreen(index);
            });
        });

        // Get modal elements
        this.modal = document.getElementById('fullscreenModal');
        this.modalImage = document.getElementById('modalImage');
        this.modalTitle = document.getElementById('modalTitle');
        this.modalDescription = document.getElementById('modalDescription');
        this.modalTags = document.getElementById('modalTags');
        this.modalCloseBtn = document.getElementById('modalCloseBtn');
        this.modalOverlay = this.modal?.querySelector('.modal-overlay');

        if (!this.modal) {
            console.warn('Fullscreen modal not found');
            return;
        }

        // Close button event
        this.modalCloseBtn?.addEventListener('click', () => this.closeFullscreen());

        // Click outside to close
        this.modalOverlay?.addEventListener('click', () => this.closeFullscreen());

        // ESC key to close
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.modal.classList.contains('active')) {
                this.closeFullscreen();
            }
        });
    }

    openFullscreen(slideIndex) {
        if (!this.modal || !this.slides[slideIndex]) {
            return;
        }

        const slide = this.slides[slideIndex];
        const img = slide.querySelector('.activity-image');
        const content = slide.querySelector('.activity-content');
        const title = content?.querySelector('h3')?.textContent || '';
        const description = content?.querySelector('p')?.textContent || '';
        const tags = content?.querySelectorAll('.tag');

        // Set modal content
        if (this.modalImage && img) {
            this.modalImage.src = img.src;
            this.modalImage.alt = img.alt;
        }
        if (this.modalTitle) {
            this.modalTitle.textContent = title;
        }
        if (this.modalDescription) {
            this.modalDescription.textContent = description;
        }

        // Set tags
        if (this.modalTags) {
            this.modalTags.innerHTML = '';
            if (tags) {
                tags.forEach(tag => {
                    const tagElement = document.createElement('span');
                    tagElement.className = 'tag';
                    tagElement.textContent = tag.textContent;
                    this.modalTags.appendChild(tagElement);
                });
            }
        }

        // Show modal
        this.modal.style.visibility = 'visible';
        setTimeout(() => {
            this.modal.classList.add('active');
        }, 10);

        // Pause carousel auto-play
        this.pauseAutoPlay();

        // Prevent body scroll
        document.body.style.overflow = 'hidden';
    }

    closeFullscreen() {
        if (!this.modal) return;

        this.modal.classList.remove('active');
        setTimeout(() => {
            this.modal.style.visibility = 'hidden';
        }, 300);

        // Resume carousel auto-play
        setTimeout(() => {
            this.startAutoPlay();
        }, 1000);

        // Restore body scroll
        document.body.style.overflow = '';
    }

    getEventX(e) {
        return e.type.includes('mouse') ? e.clientX : e.touches[0].clientX;
    }
    
    getEventY(e) {
        return e.type.includes('mouse') ? e.clientY : e.touches[0].clientY;
    }
    
    detectMobile() {
        return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) || 
               ('ontouchstart' in window) || 
               (navigator.maxTouchPoints > 0);
    }

    handleKeyboard(e) {
        if (!this.carousel.matches(':hover')) return;
        
        switch (e.key) {
            case 'ArrowLeft':
                e.preventDefault();
                this.prevSlide();
                break;
            case 'ArrowRight':
                e.preventDefault();
                this.nextSlide();
                break;
            case ' ': // Spacebar
                e.preventDefault();
                this.toggleAutoPlay();
                break;
        }
    }

    handleResize() {
        // Recalculate positions on resize
        this.showSlide(this.currentSlide, false);
    }

    showSlide(index, animate = true) {
        if (index < 0 || index >= this.totalSlides) return;
        
        // Remove active classes
        this.slides.forEach(slide => {
            slide.classList.remove('active', 'slide-in-left', 'slide-in-right');
        });
        
        // Update indicators
        const indicators = document.querySelectorAll('.indicator-dot');
        indicators.forEach(dot => dot.classList.remove('active'));
        
        // Set current slide
        this.currentSlide = index;
        
        // Apply transform
        const translateX = -index * 100;
        if (animate) {
            this.track.style.transition = 'transform 0.6s cubic-bezier(0.25, 0.46, 0.45, 0.94)';
        } else {
            this.track.style.transition = 'none';
        }
        this.track.style.transform = `translateX(${translateX}%)`;
        
        // Add active class to current slide
        setTimeout(() => {
            this.slides[index].classList.add('active');
            if (indicators[index]) {
                indicators[index].classList.add('active');
            }
        }, animate ? 50 : 0);
        
        // Restore transition after immediate update
        if (!animate) {
            setTimeout(() => {
                this.track.style.transition = 'transform 0.6s cubic-bezier(0.25, 0.46, 0.45, 0.94)';
            }, 50);
        }
    }

    nextSlide() {
        const nextIndex = (this.currentSlide + 1) % this.totalSlides;
        this.slides[nextIndex]?.classList.add('slide-in-right');
        this.showSlide(nextIndex);
    }

    prevSlide() {
        const prevIndex = this.currentSlide === 0 ? this.totalSlides - 1 : this.currentSlide - 1;
        this.slides[prevIndex]?.classList.add('slide-in-left');
        this.showSlide(prevIndex);
    }

    goToSlide(index) {
        if (index === this.currentSlide) return;
        
        const direction = index > this.currentSlide ? 'slide-in-right' : 'slide-in-left';
        this.slides[index]?.classList.add(direction);
        this.showSlide(index);
    }

    startAutoPlay() {
        if (!this.isAutoPlaying) return;
        
        this.pauseAutoPlay(); // Clear any existing interval
        this.autoPlayInterval = setInterval(() => {
            this.nextSlide();
        }, this.autoPlayDelay);
    }

    pauseAutoPlay() {
        if (this.autoPlayInterval) {
            clearInterval(this.autoPlayInterval);
            this.autoPlayInterval = null;
        }
    }

    toggleAutoPlay() {
        this.isAutoPlaying = !this.isAutoPlaying;
        if (this.isAutoPlaying) {
            this.startAutoPlay();
        } else {
            this.pauseAutoPlay();
        }
    }

    optimizeImages() {
        // Lazy load images and optimize loading
        const images = this.carousel.querySelectorAll('.activity-image');
        
        images.forEach((img, index) => {
            // Preload first few images
            if (index < 3) {
                this.loadImage(img);
            } else {
                // Lazy load others
                this.setupLazyLoading(img);
            }
        });
    }

    loadImage(img) {
        if (img.dataset.src && !img.src) {
            img.src = img.dataset.src;
            img.addEventListener('load', () => {
                img.classList.add('loaded');
            });
            img.addEventListener('error', () => {
                img.classList.add('error');
                console.warn(`Failed to load image: ${img.dataset.src}`);
            });
        }
    }

    setupLazyLoading(img) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    this.loadImage(entry.target);
                    observer.unobserve(entry.target);
                }
            });
        }, {
            rootMargin: '50px'
        });
        
        observer.observe(img);
    }

    // Public API methods
    destroy() {
        this.pauseAutoPlay();
        // Remove event listeners
        // This would be implemented if needed for cleanup
    }

    setAutoPlayDelay(delay) {
        this.autoPlayDelay = delay;
        if (this.isAutoPlaying) {
            this.startAutoPlay();
        }
    }

    getCurrentSlide() {
        return this.currentSlide;
    }

    getTotalSlides() {
        return this.totalSlides;
    }
}

// Initialize carousel when DOM is ready
let rotcCarousel;

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        rotcCarousel = new ROTCGalleryCarousel();
    });
} else {
    rotcCarousel = new ROTCGalleryCarousel();
}

// Export for potential external use
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ROTCGalleryCarousel;
}

// Global access
window.ROTCGalleryCarousel = ROTCGalleryCarousel;
window.rotcCarousel = rotcCarousel;