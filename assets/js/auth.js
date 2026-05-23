/**
 * Bealet Website - Authentication JavaScript
 * Password validation, strength meter, and form handling
 */

// Password Strength Checker
class PasswordStrengthChecker {
    constructor(passwordInputId, strengthMeterId, strengthTextId) {
        this.passwordInput = document.getElementById(passwordInputId);
        this.strengthMeter = document.getElementById(strengthMeterId);
        this.strengthText = document.getElementById(strengthTextId);
        
        if (this.passwordInput) {
            this.passwordInput.addEventListener('input', (e) => this.checkStrength(e.target.value));
        }
    }
    
    checkStrength(password) {
        let strength = 0;
        const requirements = {
            length: password.length >= 8,
            uppercase: /[A-Z]/.test(password),
            number: /\d/.test(password),
            special: /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)
        };
        
        // Calculate strength
        if (requirements.length) strength += 25;
        if (requirements.uppercase) strength += 25;
        if (requirements.number) strength += 25;
        if (requirements.special) strength += 25;
        
        // Update meter
        if (this.strengthMeter) {
            const fill = this.strengthMeter.querySelector('.password-strength-meter-fill');
            if (fill) {
                fill.style.width = strength + '%';
                fill.className = 'password-strength-meter-fill';
                
                if (strength < 25) fill.classList.add('weak');
                else if (strength < 50) fill.classList.add('fair');
                else if (strength < 75) fill.classList.add('good');
                else fill.classList.add('strong');
            }
        }
        
        // Update text
        if (this.strengthText) {
            let text = '';
            if (password.length === 0) text = '';
            else if (strength < 25) text = '<span class="text-danger">Very Weak</span>';
            else if (strength < 50) text = '<span class="text-warning">Weak</span>';
            else if (strength < 75) text = '<span class="text-info">Good</span>';
            else text = '<span class="text-success">Very Strong</span>';
            
            this.strengthText.innerHTML = text;
        }
        
        return strength;
    }
}

// Toggle Password Visibility
class PasswordToggle {
    constructor(toggleButtonClass = 'password-toggle') {
        this.buttons = document.querySelectorAll('.' + toggleButtonClass);
        this.init();
    }
    
    init() {
        this.buttons.forEach(button => {
            button.addEventListener('click', (e) => this.toggle(e));
        });
    }
    
    toggle(event) {
        event.preventDefault();
        
        const button = event.currentTarget;
        const input = button.previousElementSibling;
        
        if (!input || (input.tagName !== 'INPUT' && !input.querySelector('input'))) {
            // Find input in parent
            const parent = button.closest('.input-group') || button.closest('div');
            const inputElement = parent ? parent.querySelector('input[type="password"], input[type="text"]') : null;
            
            if (inputElement) {
                this.toggleInput(inputElement, button);
            }
        } else if (input.tagName === 'INPUT') {
            this.toggleInput(input, button);
        } else {
            const inputElement = input.querySelector('input');
            if (inputElement) {
                this.toggleInput(inputElement, button);
            }
        }
    }
    
    toggleInput(input, button) {
        const isPassword = input.type === 'password';
        input.type = isPassword ? 'text' : 'password';
        
        const icon = button.querySelector('i');
        if (icon) {
            icon.className = isPassword ? 'fas fa-eye-slash' : 'fas fa-eye';
        }
        
        input.focus();
    }
}

// Email Validation
function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

function displayEmailError(inputId, message) {
    const input = document.getElementById(inputId);
    if (!input) return;
    
    input.classList.add('is-invalid');
    
    let feedback = input.nextElementSibling;
    if (!feedback || !feedback.classList.contains('invalid-feedback')) {
        feedback = document.createElement('div');
        feedback.className = 'invalid-feedback d-block';
        input.parentNode.insertBefore(feedback, input.nextSibling);
    }
    
    feedback.textContent = message;
}

function clearEmailError(inputId) {
    const input = document.getElementById(inputId);
    if (!input) return;
    
    input.classList.remove('is-invalid');
    
    const feedback = input.nextElementSibling;
    if (feedback && feedback.classList.contains('invalid-feedback')) {
        feedback.remove();
    }
}

// Phone Validation
function validatePhone(phone) {
    const re = /^(?:\+233[0-9]{9}|0[0-9]{9}|[0-9]{10})$/;
    return re.test(phone.replace(/[\s-()]/g, ''));
}

function displayPhoneError(inputId, message) {
    const input = document.getElementById(inputId);
    if (!input) return;
    
    input.classList.add('is-invalid');
    
    let feedback = input.nextElementSibling;
    if (!feedback || !feedback.classList.contains('invalid-feedback')) {
        feedback = document.createElement('div');
        feedback.className = 'invalid-feedback d-block';
        input.parentNode.insertBefore(feedback, input.nextSibling);
    }
    
    feedback.textContent = message;
}

function clearPhoneError(inputId) {
    const input = document.getElementById(inputId);
    if (!input) return;
    
    input.classList.remove('is-invalid');
    
    const feedback = input.nextElementSibling;
    if (feedback && feedback.classList.contains('invalid-feedback')) {
        feedback.remove();
    }
}

// Real-time Email Validation
function setupEmailValidation(inputId) {
    const input = document.getElementById(inputId);
    if (!input) return;
    
    input.addEventListener('blur', function() {
        if (this.value && !validateEmail(this.value)) {
            displayEmailError(inputId, 'Please enter a valid email address');
        } else {
            clearEmailError(inputId);
        }
    });
    
    input.addEventListener('focus', function() {
        clearEmailError(inputId);
    });
}

// Real-time Phone Validation
function setupPhoneValidation(inputId) {
    const input = document.getElementById(inputId);
    if (!input) return;
    
    input.addEventListener('blur', function() {
        if (this.value && !validatePhone(this.value)) {
            displayPhoneError(inputId, 'Please enter a valid Ghana phone number (e.g., +233 24 000 0000 or 024 000 0000)');
        } else {
            clearPhoneError(inputId);
        }
    });
    
    input.addEventListener('focus', function() {
        clearPhoneError(inputId);
    });
}

// Form Validation for Registration
function validateRegistrationForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return false;
    
    let isValid = true;
    
    const name = form.querySelector('input[name="name"]');
    if (name && name.value.trim().length < 3) {
        displayFieldError(name, 'Name must be at least 3 characters');
        isValid = false;
    }
    
    const email = form.querySelector('input[name="email"]');
    if (email && !validateEmail(email.value)) {
        displayEmailError(email.id || 'email', 'Please enter a valid email');
        isValid = false;
    }
    
    const phone = form.querySelector('input[name="phone"]');
    if (phone && phone.value && !validatePhone(phone.value)) {
        displayPhoneError(phone.id || 'phone', 'Please enter a valid Ghana phone number');
        isValid = false;
    }
    
    const password = form.querySelector('input[name="password"]');
    if (password) {
        const errors = getPasswordErrors(password.value);
        if (errors.length > 0) {
            displayFieldError(password, errors.join(', '));
            isValid = false;
        }
    }
    
    const confirmPassword = form.querySelector('input[name="confirm_password"]');
    if (confirmPassword && password && confirmPassword.value !== password.value) {
        displayFieldError(confirmPassword, 'Passwords do not match');
        isValid = false;
    }
    
    return isValid;
}

// Get Password Validation Errors
function getPasswordErrors(password) {
    const errors = [];
    
    if (password.length < 8) {
        errors.push('At least 8 characters');
    }
    
    if (!/[A-Z]/.test(password)) {
        errors.push('One uppercase letter');
    }
    
    if (!/[0-9]/.test(password)) {
        errors.push('One number');
    }
    
    if (!/[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)) {
        errors.push('One special character');
    }
    
    return errors;
}

// Display Field Error
function displayFieldError(field, message) {
    field.classList.add('is-invalid');
    
    let feedback = field.nextElementSibling;
    if (!feedback || !feedback.classList.contains('invalid-feedback')) {
        feedback = document.createElement('div');
        feedback.className = 'invalid-feedback d-block';
        field.parentNode.insertBefore(feedback, field.nextSibling);
    }
    
    feedback.textContent = message;
}

// Clear Field Error
function clearFieldError(field) {
    field.classList.remove('is-invalid');
    
    const feedback = field.nextElementSibling;
    if (feedback && feedback.classList.contains('invalid-feedback')) {
        feedback.remove();
    }
}

// Check Email Availability (via API)
async function checkEmailAvailability(email) {
    if (!validateEmail(email)) {
        return false;
    }
    
    try {
        const response = await fetch('/bealet-website/api/check-email.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ email })
        });
        
        const data = await response.json();
        return data.available === true;
    } catch (error) {
        console.error('Error checking email:', error);
        return false;
    }
}

// Real-time Email Availability Check
function setupEmailAvailabilityCheck(inputId) {
    const input = document.getElementById(inputId);
    if (!input) return;
    
    let timeout;
    
    input.addEventListener('input', function() {
        clearTimeout(timeout);
        
        if (!this.value || !validateEmail(this.value)) {
            return;
        }
        
        timeout = setTimeout(async () => {
            const available = await checkEmailAvailability(this.value);
            
            if (!available) {
                displayFieldError(this, 'This email is already registered');
            } else {
                clearFieldError(this);
            }
        }, 500);
    });
}

// Initialize Authentication Features
document.addEventListener('DOMContentLoaded', function() {
    // Initialize password strength checker
    if (document.getElementById('password')) {
        new PasswordStrengthChecker('password', 'passwordStrengthMeter', 'passwordStrengthText');
    }
    
    // Initialize password toggles
    new PasswordToggle('password-toggle');
    
    // Setup email validation
    if (document.getElementById('email')) {
        setupEmailValidation('email');
        setupEmailAvailabilityCheck('email');
    }
    
    // Setup phone validation
    if (document.getElementById('phone')) {
        setupPhoneValidation('phone');
    }
    
    // Form submission handlers
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            const email = this.querySelector('input[name="email"]');
            const password = this.querySelector('input[name="password"]');
            
            let isValid = true;
            
            if (!validateEmail(email.value)) {
                displayEmailError(email.id || 'login-email', 'Please enter a valid email');
                isValid = false;
            }
            
            if (!password.value) {
                displayFieldError(password, 'Password is required');
                isValid = false;
            }
            
            if (!isValid) {
                e.preventDefault();
            }
        });
    }
    
    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        registerForm.addEventListener('submit', function(e) {
            if (!validateRegistrationForm('registerForm')) {
                e.preventDefault();
            }
        });
    }
    
    // Clear errors on focus
    document.querySelectorAll('input').forEach(input => {
        input.addEventListener('focus', function() {
            clearFieldError(this);
        });
    });
});

console.log('Bealet Website - Auth JS Loaded');
