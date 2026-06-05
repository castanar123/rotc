// Registration Form JavaScript
console.log('External registration-form.js is loading');
document.addEventListener('DOMContentLoaded', function() {
    console.log('External script - DOMContentLoaded fired');
    
    const form = document.getElementById('registrationForm');
    const steps = document.querySelectorAll('.form-step');
    const progressSteps = document.querySelectorAll('.progress-step');
    const nextBtn = document.getElementById('nextBtn');
    const prevBtn = document.getElementById('prevBtn');
    const submitBtn = document.getElementById('submitBtn');
    // Inline eye icon toggles for password fields (mask via CSS, keep type=text)
    document.querySelectorAll('.password-toggle').forEach(btn => {
        btn.addEventListener('click', function() {
            const targetSel = this.getAttribute('data-target');
            const input = targetSel ? document.querySelector(targetSel) : null;
            if (!input) return;
            // Toggle masked class
            const isMasked = input.classList.contains('masked');
            if (isMasked) {
                input.classList.remove('masked'); // reveal
            } else {
                input.classList.add('masked'); // hide
            }
            const icon = this.querySelector('i');
            if (icon) {
                icon.classList.toggle('fa-eye');
                icon.classList.toggle('fa-eye-slash');
            }
            this.setAttribute('aria-pressed', (!isMasked).toString());
        });
    });
    
    console.log('Form elements found:');
    console.log('Form:', form);
    console.log('Steps found:', steps.length, steps);
    console.log('Next button:', nextBtn);
    console.log('Previous button:', prevBtn);
    console.log('Submit button:', submitBtn);
    console.log('Progress steps:', progressSteps.length, progressSteps);
    
    console.log('Form elements found:', {
        form: !!form,
        steps: steps.length,
        nextBtn: !!nextBtn,
        prevBtn: !!prevBtn,
        submitBtn: !!submitBtn
    });
    
    let currentStep = 1;
    const totalSteps = steps.length;
    
    // Initialize form
    console.log('Setting initial step to:', currentStep);
    showStep(currentStep);
    
    // Initialize next button state
    updateNextButtonState();
    
    // Navigation event listeners
    nextBtn.addEventListener('click', function() {
        console.log('Next button clicked! Current step:', currentStep);
        console.log('Total steps:', totalSteps);
        console.log('Validation result:', validateCurrentStep());
        
        if (validateCurrentStep()) {
            if (currentStep < totalSteps) {
                currentStep++;
                console.log('Moving to step:', currentStep);
                showStep(currentStep);
            }
        } else {
            console.log('Validation failed, staying on current step');
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
        console.log('showStep called with step:', step);
        console.log('Available steps:', steps.length);
        
        // Hide all steps
        steps.forEach((s, index) => {
            s.classList.remove('active');
            s.style.display = 'none';
            console.log(`Step ${index + 1} (data-step=${s.dataset.step}) hidden`);
        });
        progressSteps.forEach(s => s.classList.remove('active', 'completed'));
        
        // Show current step
        const currentStepElement = document.querySelector(`.form-step[data-step="${step}"]`);
        console.log('Current step element found:', !!currentStepElement);
        if (currentStepElement) {
            currentStepElement.classList.add('active');
            currentStepElement.style.display = 'block';
            currentStepElement.style.visibility = 'visible';
            currentStepElement.style.opacity = '1';
            console.log('Added active to step:', step);
            console.log('Step element classes:', currentStepElement.className);
            console.log('Step element display style:', window.getComputedStyle(currentStepElement).display);
        } else {
            console.error('Step element not found for step:', step);
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
        if (field.disabled) return true;
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
            const passwordValidation = validatePasswordRequirements(value);
            if (!passwordValidation.isValid) {
                isValid = false;
                errorMessage = 'Password must meet all requirements';
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
    
    // Show field error (prefer inside .password-input to avoid shifting the eye)
    function showFieldError(field, message) {
        const container = field.closest ? (field.closest('.password-input') || field.closest('.form-group') || field.parentNode) : field.parentNode;
        // Remove any existing error in this container
        const old = container.querySelector('.field-error');
        if (old) old.remove();
        const errorElement = document.createElement('div');
        errorElement.className = 'field-error';
        errorElement.textContent = message;
        errorElement.style.color = '#ff4757';
        errorElement.style.fontSize = '0.85rem';
        errorElement.style.marginTop = '0.25rem';
        container.appendChild(errorElement);
    }
    
    // Remove field error
    function removeFieldError(field) {
        const container = field.closest ? (field.closest('.password-input') || field.closest('.form-group') || field.parentNode) : field.parentNode;
        const existingError = container.querySelector('.field-error');
        if (existingError) existingError.remove();
    }
    
    // Real-time validation for all form fields
    form.addEventListener('input', function(e) {
        const field = e.target;
        
        // Validate field on input
        if (field.hasAttribute('required') || field.value.trim()) {
            validateField(field);
        }
        
        // Password strength indicator and requirements
        if (field.name === 'password') {
            updatePasswordRequirements(field.value);
            updateNextButtonState();
        }
        
        // Password match indicator and next button state
        if (field.name === 'confirm_password' || field.name === 'password') {
            updatePasswordMatch();
            updateNextButtonState();
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
    
    // Validate password requirements
    function validatePasswordRequirements(password) {
        const requirements = {
            length: password.length >= 8,
            uppercase: /[A-Z]/.test(password),
            lowercase: /[a-z]/.test(password),
            number: /[0-9]/.test(password),
            special: /[!@#$%^&*()_+\-=\[\]{};":.,<>?]/.test(password)
        };
        
        const isValid = Object.values(requirements).every(req => req);
        
        return {
            isValid,
            requirements
        };
    }
    
    // Update password requirements display
    function updatePasswordRequirements(password) {
        const feedbackElement = document.getElementById('password-feedback');
        if (!feedbackElement) return;
        
        const validation = validatePasswordRequirements(password);
        const requirements = validation.requirements;
        
        // Show feedback when password field has content
        if (password.length > 0) {
            feedbackElement.classList.add('show');
        } else {
            feedbackElement.classList.remove('show');
            return;
        }
        
        // Update each requirement
        const requirementElements = {
            length: feedbackElement.querySelector('#req-length'),
            uppercase: feedbackElement.querySelector('#req-uppercase'),
            lowercase: feedbackElement.querySelector('#req-lowercase'),
            number: feedbackElement.querySelector('#req-number'),
            special: feedbackElement.querySelector('#req-special')
        };
        
        Object.keys(requirements).forEach(key => {
            const element = requirementElements[key];
            if (element) {
                if (requirements[key]) {
                    element.className = 'requirement-met';
                    element.innerHTML = '✓ ' + element.textContent.replace(/[✓✗] /, '');
                } else {
                    element.className = 'requirement-unmet';
                    element.innerHTML = '✗ ' + element.textContent.replace(/[✓✗] /, '');
                }
            }
        });
    }
    
    // Update next button state based on current step validation
    function updateNextButtonState() {
        if (currentStep === 1) {
            const passwordField = document.querySelector('[name="password"]');
            const confirmPasswordField = document.querySelector('[name="confirm_password"]');
            
            if (passwordField && passwordField.value) {
                const passwordValidation = validatePasswordRequirements(passwordField.value);
                const passwordsMatch = confirmPasswordField && 
                    confirmPasswordField.value === passwordField.value;
                
                // Disable next button if password requirements not met or passwords don't match
                if (!passwordValidation.isValid || !passwordsMatch) {
                    nextBtn.disabled = true;
                    nextBtn.style.opacity = '0.5';
                    nextBtn.style.cursor = 'not-allowed';
                } else {
                    nextBtn.disabled = false;
                    nextBtn.style.opacity = '1';
                    nextBtn.style.cursor = 'pointer';
                }
            } else {
                nextBtn.disabled = true;
                nextBtn.style.opacity = '0.5';
                nextBtn.style.cursor = 'not-allowed';
            }
        } else {
            // For other steps, use normal validation
            nextBtn.disabled = false;
            nextBtn.style.opacity = '1';
            nextBtn.style.cursor = 'pointer';
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
    const autoSaveFields = ['student_number', 'first_name', 'middle_initial', 'last_name', 'email', 'course', 'section'];
    
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

    // --- PSGC location cascading dropdowns ---
    const psgcBase = 'https://psgc.gitlab.io/api';
    const regionSel = document.getElementById('region');
    const provinceSel = document.getElementById('province');
    const citySel = document.getElementById('city_municipality');
    const barangaySel = document.getElementById('barangay');
    const purokInput = document.getElementById('purok');
    const addressInput = document.getElementById('address');

    // Utility: set options
    // textProp: which property to show as label
    // codeProp: which property to store as dataset.code (for PSGC API fetching)
    // valueProp: which property to use for option.value (defaults to textProp)
    function fillSelect(select, items, textProp = 'name', codeProp = 'code', defaults = {}, valueProp = null) {
        if (!select) return;
        const currentDefault = select.getAttribute('data-default') || '';
        select.innerHTML = '<option value="">' + (defaults.placeholder || select.options[0]?.text || 'Select') + '</option>';
        items.forEach(item => {
            const opt = document.createElement('option');
            const text = item[textProp] ?? '';
            const val = valueProp ? (item[valueProp] ?? text) : text;
            opt.textContent = text;
            opt.value = val;
            if (codeProp && item[codeProp]) opt.dataset.code = item[codeProp];
            select.appendChild(opt);
        });
        // Try to restore selection from data-default (case-insensitive match by value/text)
        if (currentDefault) {
            const match = Array.from(select.options).find(o => o.value.toLowerCase() === currentDefault.toLowerCase());
            if (match) {
                select.value = match.value;
            }
        }
        select.disabled = items.length === 0;
    }

    async function fetchJson(url) {
        try {
            const res = await fetch(url, { cache: 'no-store' });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            return await res.json();
        } catch (e) {
            console.warn('PSGC fetch failed:', url, e);
            return [];
        }
    }

    async function loadRegions() {
        // Fixed region list with PSGC region codes
        const regionsList = [
            { code: '130000000', value: 'NCR',   label: 'NCR - National Capital Region' },
            { code: '140000000', value: 'CAR',   label: 'CAR - Cordillera Administrative Region' },
            { code: '010000000', value: 'I',     label: 'I - Ilocos' },
            { code: '020000000', value: 'II',    label: 'II - Cagayan Valley' },
            { code: '030000000', value: 'III',   label: 'III - Central Luzon' },
            { code: '040000000', value: 'IV-A',  label: 'IV-A - CALABARZON' },
            { code: '170000000', value: 'IV-B',  label: 'IV-B - MIMAROPA' },
            { code: '050000000', value: 'V',     label: 'V - Bicol' },
            { code: '060000000', value: 'VI',    label: 'VI - Western Visayas' },
            { code: '070000000', value: 'VII',   label: 'VII - Central Visayas' },
            { code: '080000000', value: 'VIII',  label: 'VIII - Eastern Visayas' },
            { code: '090000000', value: 'IX',    label: 'IX - Zamboanga Peninsula' },
            { code: '100000000', value: 'X',     label: 'X - Northern Mindanao' },
            { code: '110000000', value: 'XI',    label: 'XI - Davao' },
            { code: '120000000', value: 'XII',   label: 'XII - SOCCSKSARGEN' },
            { code: '160000000', value: 'XIII',  label: 'XIII - Caraga' },
            { code: '150000000', value: 'BARMM', label: 'BARMM - Bangsamoro Autonomous Region in Muslim Mindanao' }
        ];
        fillSelect(regionSel, regionsList, 'label', 'code', { placeholder: 'Select Region' }, 'value');
        if (regionSel && regionSel.value) {
            await onRegionChange(false);
        }
    }

    async function onRegionChange(clearDefaults = true) {
        const code = regionSel?.selectedOptions[0]?.dataset.code;
        fillSelect(provinceSel, [], 'name', 'code', { placeholder: 'Select Province' });
        fillSelect(citySel, [], 'name', 'code', { placeholder: 'Select City/Municipality' });
        fillSelect(barangaySel, [], 'name', 'code', { placeholder: 'Select Barangay' });
        if (!code) return;
        const provinces = await fetchJson(`${psgcBase}/regions/${code}/provinces/`);
        fillSelect(provinceSel, provinces, 'name', 'code', { placeholder: 'Select Province' });
        if (provinces.length === 0) {
            // Some regions (e.g., NCR) have no provinces. Load cities/municipalities directly.
            const cities = await fetchJson(`${psgcBase}/regions/${code}/cities-municipalities/`);
            fillSelect(citySel, cities, 'name', 'code', { placeholder: 'Select City/Municipality' });
            if (citySel.value) await onCityChange(false);
        } else if (provinceSel.value) {
            await onProvinceChange(false);
        }
        if (clearDefaults) provinceSel.setAttribute('data-default', '');
    }

    async function onProvinceChange(clearDefaults = true) {
        const code = provinceSel?.selectedOptions[0]?.dataset.code;
        fillSelect(citySel, [], 'name', 'code', { placeholder: 'Select City/Municipality' });
        fillSelect(barangaySel, [], 'name', 'code', { placeholder: 'Select Barangay' });
        if (!code) return;
        const cities = await fetchJson(`${psgcBase}/provinces/${code}/cities-municipalities/`);
        fillSelect(citySel, cities, 'name', 'code', { placeholder: 'Select City/Municipality' });
        if (citySel.value) await onCityChange(false);
        if (clearDefaults) citySel.setAttribute('data-default', '');
    }

    async function onCityChange(clearDefaults = true) {
        const code = citySel?.selectedOptions[0]?.dataset.code;
        fillSelect(barangaySel, [], 'name', 'code', { placeholder: 'Select Barangay' });
        if (!code) return;
        const b = await fetchJson(`${psgcBase}/cities-municipalities/${code}/barangays/`);
        fillSelect(barangaySel, b, 'name', 'code', { placeholder: 'Select Barangay' });
        if (clearDefaults) barangaySel.setAttribute('data-default', '');
        updateComposedAddressPreview();
    }

    function updateComposedAddressPreview() {
        if (!addressInput) return;
        if (addressInput.value && addressInput.value.trim() !== '') return; // user provided explicit address
        const parts = [];
        if (purokInput && purokInput.value) parts.push(`Purok ${purokInput.value}`);
        if (barangaySel && barangaySel.value) parts.push(`Brgy. ${barangaySel.value}`);
        if (citySel && citySel.value) parts.push(citySel.value);
        if (provinceSel && provinceSel.value) parts.push(provinceSel.value);
        if (regionSel && regionSel.value) parts.push(regionSel.value);
        addressInput.placeholder = parts.join(', ');
    }

    if (regionSel && provinceSel && citySel && barangaySel) {
        regionSel.addEventListener('change', () => onRegionChange());
        provinceSel.addEventListener('change', () => onProvinceChange());
        citySel.addEventListener('change', () => onCityChange());
        barangaySel.addEventListener('change', updateComposedAddressPreview);
        if (purokInput) purokInput.addEventListener('input', updateComposedAddressPreview);
        loadRegions();
    }
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