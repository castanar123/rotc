// Registration Form JavaScript
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('registrationForm');
    const steps = document.querySelectorAll('.form-step');
    const progressSteps = document.querySelectorAll('.progress-step');
    const nextBtn = document.getElementById('nextBtn');
    const prevBtn = document.getElementById('prevBtn');
    const submitBtn = document.getElementById('submitBtn');
    
    let currentStep = 1;
    const totalSteps = steps.length;
    
    // Initialize form
    showStep(currentStep);
    
    // Navigation event listeners
    nextBtn.addEventListener('click', function() {
        if (validateCurrentStep()) {
            if (currentStep < totalSteps) {
                currentStep++;
                showStep(currentStep);
            }
        }
    });
    
    prevBtn.addEventListener('click', function() {
        if (currentStep > 1) {
            currentStep--;
            showStep(currentStep);
        }
    });
    
    // Show specific step
    function showStep(step) {
        // Hide all steps
        steps.forEach(s => s.classList.remove('active'));
        progressSteps.forEach(s => s.classList.remove('active', 'completed'));
        
        // Show current step
        const currentStepElement = document.querySelector(`[data-step="${step}"]`);
        if (currentStepElement) {
            currentStepElement.classList.add('active');
        }
        
        // Update progress indicator
        progressSteps.forEach((s, index) => {
            if (index + 1 < step) {
                s.classList.add('completed');
            } else if (index + 1 === step) {
                s.classList.add('active');
            }
        });
        
        // Update navigation buttons
        prevBtn.style.display = step === 1 ? 'none' : 'inline-flex';
        nextBtn.style.display = step === totalSteps ? 'none' : 'inline-flex';
        submitBtn.style.display = step === totalSteps ? 'inline-flex' : 'none';
        
        // Scroll to top of form
        document.querySelector('.registration-form').scrollIntoView({ 
            behavior: 'smooth', 
            block: 'start' 
        });
    }
    
    // Validate current step
    function validateCurrentStep() {
        const currentStepElement = document.querySelector(`.form-step[data-step="${currentStep}"]`);
        const requiredFields = currentStepElement.querySelectorAll('[required]');
        let isValid = true;
        
        requiredFields.forEach(field => {
            if (!validateField(field)) {
                isValid = false;
            }
        });
        
        return isValid;
    }
    
    // Validate individual field
    function validateField(field) {
        const value = field.value.trim();
        let isValid = true;
        let errorMessage = '';
        
        // Remove existing error styling
        field.classList.remove('error');
        removeFieldError(field);
        
        // Required field validation
        if (field.hasAttribute('required') && !value) {
            isValid = false;
            errorMessage = 'This field is required';
        }
        
        // Email validation
        if (field.type === 'email' && value) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(value)) {
                isValid = false;
                errorMessage = 'Please enter a valid email address';
            }
        }
        
        // Phone validation
        if (field.type === 'tel' && value) {
            const phoneRegex = /^09\d{9}$/;
            if (!phoneRegex.test(value)) {
                isValid = false;
                errorMessage = 'Please enter a valid Philippine mobile number (09XXXXXXXXX)';
            }
        }
        
        // Password validation
        if (field.name === 'password' && value) {
            if (value.length < 8) {
                isValid = false;
                errorMessage = 'Password must be at least 8 characters long';
            }
        }
        
        // Confirm password validation
        if (field.name === 'confirm_password' && value) {
            const password = document.querySelector('[name="password"]').value;
            if (value !== password) {
                isValid = false;
                errorMessage = 'Passwords do not match';
            }
        }
        
        // Student number validation
        if (field.name === 'student_number' && value) {
            const studentNumberRegex = /^\d{4}-\d{4}$/;
            if (!studentNumberRegex.test(value)) {
                isValid = false;
                errorMessage = 'Student number format should be XXXX-XXXX';
            }
        }
        
        // Show error if invalid
        if (!isValid) {
            field.classList.add('error');
            showFieldError(field, errorMessage);
        }
        
        return isValid;
    }
    
    // Show field error
    function showFieldError(field, message) {
        const errorElement = document.createElement('div');
        errorElement.className = 'field-error';
        errorElement.textContent = message;
        errorElement.style.color = '#ff4757';
        errorElement.style.fontSize = '0.85rem';
        errorElement.style.marginTop = '0.25rem';
        
        field.parentNode.appendChild(errorElement);
    }
    
    // Remove field error
    function removeFieldError(field) {
        const existingError = field.parentNode.querySelector('.field-error');
        if (existingError) {
            existingError.remove();
        }
    }
    
    // Real-time validation for all form fields
    form.addEventListener('input', function(e) {
        const field = e.target;
        
        // Validate field on input
        if (field.hasAttribute('required') || field.value.trim()) {
            validateField(field);
        }
        
        // Password strength indicator
        if (field.name === 'password') {
            updatePasswordStrength(field.value);
        }
        
        // Password match indicator
        if (field.name === 'confirm_password' || field.name === 'password') {
            updatePasswordMatch();
        }
    });
    
    // Password strength indicator
    function updatePasswordStrength(password) {
        const strengthElement = document.getElementById('passwordStrength');
        if (!strengthElement) return;
        
        let strength = 0;
        let strengthText = '';
        let strengthClass = '';
        
        if (password.length >= 8) strength++;
        if (/[a-z]/.test(password)) strength++;
        if (/[A-Z]/.test(password)) strength++;
        if (/\d/.test(password)) strength++;
        if (/[^\w\s]/.test(password)) strength++;
        
        switch (strength) {
            case 0:
            case 1:
                strengthText = 'Very Weak';
                strengthClass = 'weak';
                break;
            case 2:
                strengthText = 'Weak';
                strengthClass = 'weak';
                break;
            case 3:
                strengthText = 'Fair';
                strengthClass = 'fair';
                break;
            case 4:
                strengthText = 'Good';
                strengthClass = 'good';
                break;
            case 5:
                strengthText = 'Strong';
                strengthClass = 'strong';
                break;
        }
        
        strengthElement.className = `password-strength ${strengthClass}`;
        strengthElement.innerHTML = `
            <div class="password-strength-bar"></div>
            <span style="font-size: 0.8rem; margin-top: 0.25rem; display: block;">${strengthText}</span>
        `;
    }
    
    // Password match indicator
    function updatePasswordMatch() {
        const password = document.querySelector('[name="password"]').value;
        const confirmPassword = document.querySelector('[name="confirm_password"]').value;
        const matchElement = document.getElementById('passwordMatch');
        
        if (!matchElement || !confirmPassword) return;
        
        if (password === confirmPassword) {
            matchElement.textContent = '✓ Passwords match';
            matchElement.className = 'password-match match';
        } else {
            matchElement.textContent = '✗ Passwords do not match';
            matchElement.className = 'password-match no-match';
        }
    }
    
    // Platoon selection enhancement
    const platoonOptions = document.querySelectorAll('.platoon-option');
    platoonOptions.forEach(option => {
        option.addEventListener('click', function() {
            const radio = this.querySelector('input[type="radio"]');
            radio.checked = true;
            
            // Remove active class from all options
            platoonOptions.forEach(opt => opt.classList.remove('selected'));
            
            // Add active class to selected option
            this.classList.add('selected');
            
            // Trigger validation
            validateField(radio);
        });
    });
    
    // Form submission
    form.addEventListener('submit', function(e) {
        // Final validation
        let isFormValid = true;
        
        for (let step = 1; step <= totalSteps; step++) {
            currentStep = step;
            if (!validateCurrentStep()) {
                isFormValid = false;
                showStep(step); // Show first invalid step
                break;
            }
        }
        
        if (!isFormValid) {
            e.preventDefault();
            showToast('Please fill in all required fields correctly', 'error');
            return;
        }
        
        // Show loading state
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
        submitBtn.disabled = true;
    });
    
    // Auto-format student number
    const studentNumberField = document.querySelector('[name="student_number"]');
    if (studentNumberField) {
        studentNumberField.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, ''); // Remove non-digits
            if (value.length >= 4) {
                value = value.substring(0, 4) + '-' + value.substring(4, 8);
            }
            e.target.value = value;
        });
    }
    
    // Auto-format phone numbers
    const phoneFields = document.querySelectorAll('[type="tel"]');
    phoneFields.forEach(field => {
        field.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, ''); // Remove non-digits
            if (value.length > 11) {
                value = value.substring(0, 11);
            }
            e.target.value = value;
        });
    });
    
    // Toast notification function
    function showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `
            <div class="toast-content">
                <i class="fas fa-${type === 'error' ? 'exclamation-triangle' : 'info-circle'}"></i>
                <span>${message}</span>
            </div>
        `;
        
        // Toast styles
        Object.assign(toast.style, {
            position: 'fixed',
            top: '20px',
            right: '20px',
            background: type === 'error' ? '#ff4757' : '#3742fa',
            color: 'white',
            padding: '1rem 1.5rem',
            borderRadius: '8px',
            boxShadow: '0 8px 32px rgba(0, 0, 0, 0.3)',
            zIndex: '10000',
            transform: 'translateX(100%)',
            transition: 'transform 0.3s ease',
            maxWidth: '400px'
        });
        
        document.body.appendChild(toast);
        
        // Animate in
        setTimeout(() => {
            toast.style.transform = 'translateX(0)';
        }, 100);
        
        // Remove after 5 seconds
        setTimeout(() => {
            toast.style.transform = 'translateX(100%)';
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 300);
        }, 5000);
    }
    
    // Add error styling to CSS
    const style = document.createElement('style');
    style.textContent = `
        .form-control.error {
            border-color: #ff4757 !important;
            box-shadow: 0 0 0 3px rgba(255, 71, 87, 0.2) !important;
        }
        
        .platoon-option.selected .platoon-card-mini {
            transform: scale(1.02);
            box-shadow: 0 8px 32px rgba(255, 215, 0, 0.3);
        }
        
        .toast-content {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .toast-content i {
            font-size: 1.2rem;
        }
    `;
    document.head.appendChild(style);
    
    // Smooth scroll for form navigation
    function smoothScrollToElement(element) {
        element.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    }
    
    // Progress step click navigation
    progressSteps.forEach((step, index) => {
        step.addEventListener('click', function() {
            const targetStep = index + 1;
            
            // Only allow navigation to completed or current step
            if (targetStep <= currentStep || step.classList.contains('completed')) {
                currentStep = targetStep;
                showStep(currentStep);
            }
        });
        
        // Add cursor pointer for clickable steps
        step.style.cursor = 'pointer';
    });
    
    // Initialize tooltips for form fields
    const tooltips = {
        'student_number': 'Format: XXXX-XXXX (e.g., 0424-0524)',
        'contact_number': 'Philippine mobile number starting with 09',
        'guardian_contact': 'Emergency contact number',
        'height': 'Enter height in feet and inches (e.g., 5\'6") or centimeters',
        'weight': 'Enter weight in kilograms',
        'password': 'Minimum 8 characters, include letters, numbers, and symbols for better security'
    };
    
    Object.keys(tooltips).forEach(fieldName => {
        const field = document.querySelector(`[name="${fieldName}"]`);
        if (field) {
            field.title = tooltips[fieldName];
        }
    });
    
    // Auto-save form data to localStorage (optional)
    const autoSaveFields = ['student_number', 'full_name', 'email', 'course', 'section'];
    
    autoSaveFields.forEach(fieldName => {
        const field = document.querySelector(`[name="${fieldName}"]`);
        if (field) {
            // Load saved data
            const savedValue = localStorage.getItem(`rotc_form_${fieldName}`);
            if (savedValue && !field.value) {
                field.value = savedValue;
            }
            
            // Save data on input
            field.addEventListener('input', function() {
                localStorage.setItem(`rotc_form_${fieldName}`, this.value);
            });
        }
    });
    
    // Clear saved data on successful submission
    form.addEventListener('submit', function() {
        autoSaveFields.forEach(fieldName => {
            localStorage.removeItem(`rotc_form_${fieldName}`);
        });
    });
});

// Additional utility functions
function formatStudentNumber(input) {
    let value = input.replace(/\D/g, '');
    if (value.length >= 4) {
        value = value.substring(0, 4) + '-' + value.substring(4, 8);
    }
    return value;
}

function formatPhoneNumber(input) {
    return input.replace(/\D/g, '').substring(0, 11);
}

function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

function validatePhilippinePhone(phone) {
    const re = /^09\d{9}$/;
    return re.test(phone);
}