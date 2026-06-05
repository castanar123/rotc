// Login Form JavaScript - Military Theme

document.addEventListener('DOMContentLoaded', function() {
    // Initialize login form functionality
    initializeLoginForm();
    initializePasswordToggle();
    initializeFormValidation();
    initializeAnimations();
    initializeAccessibility();
});

/**
 * Initialize main login form functionality
 */
function initializeLoginForm() {
    const loginForm = document.getElementById('loginForm');
    const loginButton = loginForm?.querySelector('.btn-login');
    const buttonText = loginButton?.querySelector('span');
    const buttonLoading = loginButton?.querySelector('.btn-loading');
    
    if (!loginForm || !loginButton) return;
    
    // Handle form submission
    loginForm.addEventListener('submit', function(e) {
        // Show loading state
        if (buttonText && buttonLoading) {
            buttonText.style.display = 'none';
            buttonLoading.style.display = 'block';
            loginButton.disabled = true;
        }
        
        // Add ripple effect
        createRippleEffect(loginButton, e);
        
        // Form will submit normally, loading state will be reset on page reload
    });
    
    // Handle remember me functionality
    const rememberCheckbox = document.getElementById('remember_me');
    if (rememberCheckbox) {
        // Load saved username if remember me was checked
        const savedUsername = localStorage.getItem('rotc_remembered_username');
        if (savedUsername) {
            const usernameInput = document.getElementById('username');
            if (usernameInput) {
                usernameInput.value = savedUsername;
                rememberCheckbox.checked = true;
            }
        }
        
        // Save/remove username based on remember me checkbox
        loginForm.addEventListener('submit', function() {
            const usernameInput = document.getElementById('username');
            if (rememberCheckbox.checked && usernameInput) {
                localStorage.setItem('rotc_remembered_username', usernameInput.value);
            } else {
                localStorage.removeItem('rotc_remembered_username');
            }
        });
    }
}

/**
 * Initialize password visibility toggle
 */
function initializePasswordToggle() {
    const passwordToggle = document.getElementById('passwordToggle');
    const passwordInput = document.getElementById('password');
    
    if (!passwordToggle || !passwordInput) return;
    
    passwordToggle.addEventListener('click', function() {
        const isPassword = passwordInput.type === 'password';
        const icon = passwordToggle.querySelector('i');
        
        // Toggle password visibility
        passwordInput.type = isPassword ? 'text' : 'password';
        
        // Update icon
        if (icon) {
            icon.className = isPassword ? 'fas fa-eye-slash' : 'fas fa-eye';
        }
        
        // Add animation
        passwordToggle.style.transform = 'scale(0.9)';
        setTimeout(() => {
            passwordToggle.style.transform = 'scale(1)';
        }, 150);
        
        // Maintain focus on password input
        passwordInput.focus();
    });
}

/**
 * Initialize form validation
 */
function initializeFormValidation() {
    const form = document.getElementById('loginForm');
    const usernameInput = document.getElementById('username');
    const passwordInput = document.getElementById('password');
    
    if (!form || !usernameInput || !passwordInput) return;
    
    // Real-time validation
    usernameInput.addEventListener('input', function() {
        validateUsername(this);
    });
    
    passwordInput.addEventListener('input', function() {
        validatePassword(this);
    });
    
    // Validation on blur
    usernameInput.addEventListener('blur', function() {
        validateUsername(this);
    });
    
    passwordInput.addEventListener('blur', function() {
        validatePassword(this);
    });
    
    // Form submission validation
    form.addEventListener('submit', function(e) {
        const isUsernameValid = validateUsername(usernameInput);
        const isPasswordValid = validatePassword(passwordInput);
        
        if (!isUsernameValid || !isPasswordValid) {
            e.preventDefault();
            
            // Focus on first invalid field
            if (!isUsernameValid) {
                usernameInput.focus();
            } else if (!isPasswordValid) {
                passwordInput.focus();
            }
            
            // Show error animation
            showFormError();
        }
    });
}

/**
 * Validate username/email input
 */
function validateUsername(input) {
    const value = input.value.trim();
    const isValid = value.length >= 3;
    
    updateFieldValidation(input, isValid, isValid ? '' : 'Username must be at least 3 characters long');
    return isValid;
}

/**
 * Validate password input
 */
function validatePassword(input) {
    const value = input.value;
    const isValid = value.length >= 6;
    
    updateFieldValidation(input, isValid, isValid ? '' : 'Password must be at least 6 characters long');
    return isValid;
}

/**
 * Update field validation state
 */
function updateFieldValidation(input, isValid, errorMessage) {
    const formGroup = input.closest('.form-group');
    const feedback = formGroup?.querySelector('.invalid-feedback');
    
    if (!formGroup) return;
    
    // Remove existing validation classes
    input.classList.remove('is-valid', 'is-invalid');
    
    if (input.value.trim() !== '') {
        // Add appropriate validation class
        input.classList.add(isValid ? 'is-valid' : 'is-invalid');
        
        // Update feedback message
        if (feedback) {
            feedback.textContent = errorMessage;
        }
    }
}

/**
 * Show form error animation
 */
function showFormError() {
    const form = document.getElementById('loginForm');
    if (!form) return;
    
    form.style.animation = 'shake 0.5s ease-in-out';
    setTimeout(() => {
        form.style.animation = '';
    }, 500);
}

/**
 * Initialize animations and effects
 */
function initializeAnimations() {
    // Animate form elements on load
    animateFormElements();
    
    // Initialize role card hover effects
    initializeRoleCardEffects();
    
    // Initialize floating elements animation
    initializeFloatingElements();
}

/**
 * Animate form elements on page load
 */
function animateFormElements() {
    const elements = [
        '.login-header',
        '.login-form-container',
        '.register-section'
    ];
    
    elements.forEach((selector, index) => {
        const element = document.querySelector(selector);
        if (element) {
            element.style.opacity = '0';
            element.style.transform = 'translateY(30px)';
            
            setTimeout(() => {
                element.style.transition = 'all 0.6s ease';
                element.style.opacity = '1';
                element.style.transform = 'translateY(0)';
            }, index * 200);
        }
    });
}

/**
 * Initialize role card hover effects
 */
function initializeRoleCardEffects() {
    const roleCards = document.querySelectorAll('.role-card');
    
    roleCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px) scale(1.02)';
            this.style.boxShadow = '0 10px 25px rgba(0, 0, 0, 0.2)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
            this.style.boxShadow = '';
        });
    });
}

/**
 * Initialize floating elements animation
 */
function initializeFloatingElements() {
    const elements = document.querySelectorAll('.element');
    
    elements.forEach((element, index) => {
        // Random animation duration and delay
        const duration = 4 + Math.random() * 4; // 4-8 seconds
        const delay = Math.random() * 2; // 0-2 seconds
        
        element.style.animationDuration = `${duration}s`;
        element.style.animationDelay = `${delay}s`;
        
        // Add random movement
        setInterval(() => {
            const x = Math.random() * 20 - 10; // -10 to 10
            const y = Math.random() * 20 - 10; // -10 to 10
            element.style.transform = `translate(${x}px, ${y}px)`;
        }, 3000 + Math.random() * 2000);
    });
}

/**
 * Create ripple effect on button click
 */
function createRippleEffect(button, event) {
    const ripple = document.createElement('span');
    const rect = button.getBoundingClientRect();
    const size = Math.max(rect.width, rect.height);
    const x = event.clientX - rect.left - size / 2;
    const y = event.clientY - rect.top - size / 2;
    
    ripple.style.cssText = `
        position: absolute;
        width: ${size}px;
        height: ${size}px;
        left: ${x}px;
        top: ${y}px;
        background: rgba(255, 255, 255, 0.3);
        border-radius: 50%;
        transform: scale(0);
        animation: ripple 0.6s ease-out;
        pointer-events: none;
    `;
    
    button.style.position = 'relative';
    button.style.overflow = 'hidden';
    button.appendChild(ripple);
    
    setTimeout(() => {
        ripple.remove();
    }, 600);
}

/**
 * Initialize accessibility features
 */
function initializeAccessibility() {
    // Add keyboard navigation for custom elements
    const customCheckbox = document.querySelector('.remember-me');
    if (customCheckbox) {
        customCheckbox.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                const checkbox = this.querySelector('input[type="checkbox"]');
                if (checkbox) {
                    checkbox.checked = !checkbox.checked;
                }
            }
        });
        
        // Make it focusable
        customCheckbox.setAttribute('tabindex', '0');
    }
    
    // Add ARIA labels for better screen reader support
    const passwordToggle = document.getElementById('passwordToggle');
    if (passwordToggle) {
        passwordToggle.setAttribute('aria-label', 'Toggle password visibility');
    }
    
    // Add focus indicators
    const focusableElements = document.querySelectorAll('input, button, a, [tabindex]');
    focusableElements.forEach(element => {
        element.addEventListener('focus', function() {
            this.style.outline = '2px solid var(--accent-gold)';
            this.style.outlineOffset = '2px';
        });
        
        element.addEventListener('blur', function() {
            this.style.outline = '';
            this.style.outlineOffset = '';
        });
    });
}

/**
 * Handle form auto-fill detection
 */
function handleAutoFill() {
    const inputs = document.querySelectorAll('input');
    
    inputs.forEach(input => {
        // Check for autofill on load
        setTimeout(() => {
            if (input.value !== '') {
                input.classList.add('has-value');
            }
        }, 100);
        
        // Monitor for autofill changes
        input.addEventListener('animationstart', function(e) {
            if (e.animationName === 'onAutoFillStart') {
                this.classList.add('has-value');
            }
        });
        
        input.addEventListener('input', function() {
            if (this.value !== '') {
                this.classList.add('has-value');
            } else {
                this.classList.remove('has-value');
            }
        });
    });
}

/**
 * Handle network status and offline functionality
 */
function handleNetworkStatus() {
    const form = document.getElementById('loginForm');
    const submitButton = form?.querySelector('.btn-login');
    
    if (!form || !submitButton) return;
    
    function updateNetworkStatus() {
        if (!navigator.onLine) {
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-wifi-slash"></i> <span>No Internet Connection</span>';
            submitButton.style.background = 'var(--danger-color)';
        } else {
            submitButton.disabled = false;
            submitButton.innerHTML = '<i class="fas fa-sign-in-alt"></i> <span>LOGIN</span>';
            submitButton.style.background = '';
        }
    }
    
    window.addEventListener('online', updateNetworkStatus);
    window.addEventListener('offline', updateNetworkStatus);
    
    // Check initial status
    updateNetworkStatus();
}

/**
 * Add CSS animations
 */
function addCustomStyles() {
    const style = document.createElement('style');
    style.textContent = `
        @keyframes ripple {
            to {
                transform: scale(4);
                opacity: 0;
            }
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }
        
        @keyframes onAutoFillStart {
            from { background: transparent; }
            to { background: transparent; }
        }
        
        input:-webkit-autofill {
            animation-name: onAutoFillStart;
            animation-duration: 0.001s;
        }
        
        .form-control.has-value {
            background: rgba(var(--tactical-black-rgb), 0.7);
        }
        
        .form-control.is-valid {
            border-color: var(--success-color);
        }
        
        .form-control.is-invalid {
            border-color: var(--danger-color);
        }
        
        .invalid-feedback {
            color: var(--danger-color);
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }
    `;
    
    document.head.appendChild(style);
}

// Initialize additional features
document.addEventListener('DOMContentLoaded', function() {
    handleAutoFill();
    handleNetworkStatus();
    addCustomStyles();
});

// Handle page visibility changes
document.addEventListener('visibilitychange', function() {
    if (document.visibilityState === 'visible') {
        // Refresh any time-sensitive data when page becomes visible
        const form = document.getElementById('loginForm');
        if (form) {
            // Reset any loading states
            const submitButton = form.querySelector('.btn-login');
            const buttonText = submitButton?.querySelector('span');
            const buttonLoading = submitButton?.querySelector('.btn-loading');
            
            if (submitButton && buttonText && buttonLoading) {
                buttonText.style.display = 'inline';
                buttonLoading.style.display = 'none';
                submitButton.disabled = false;
            }
        }
    }
});

// Export functions for potential external use
window.LoginForm = {
    validateUsername,
    validatePassword,
    createRippleEffect,
    showFormError
};