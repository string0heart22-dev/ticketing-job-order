// Toggle password visibility
function togglePassword() {
    const passwordInput = document.getElementById('password');
    const eyeIcon = document.getElementById('eye-icon');
    if (!passwordInput || !eyeIcon) return;

    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        eyeIcon.innerHTML = `
            <path d="M3.707 2.293a1 1 0 00-1.414 1.414l14 14a1 1 0 001.414-1.414l-1.473-1.473A10.014 10.014 0 0019.542 10C18.268 5.943 14.478 3 10 3a9.958 9.958 0 00-4.512 1.074l-1.78-1.781zm4.261 4.26l1.514 1.515a2.003 2.003 0 012.45 2.45l1.514 1.514a4 4 0 00-5.478-5.478z"/>
            <path d="M12.454 16.697L9.75 13.992a4 4 0 01-3.742-3.741L2.335 6.578A9.98 9.98 0 00.458 10c1.274 4.057 5.065 7 9.542 7 .847 0 1.669-.105 2.454-.303z"/>
        `;
    } else {
        passwordInput.type = 'password';
        eyeIcon.innerHTML = `
            <path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/>
            <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/>
        `;
    }
}

document.addEventListener('DOMContentLoaded', function () {
    // Auto-hide server-rendered alerts (logout/cleanup messages)
    document.querySelectorAll('.alert').forEach(function (alert) {
        setTimeout(function () { alert.style.opacity = '0'; alert.style.transition = 'opacity 0.4s'; setTimeout(function(){ alert.remove(); }, 400); }, 4000);
    });

    const form = document.querySelector('.login-form');
    if (!form) return;

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        submitLogin(form);
    });
});

function submitLogin(form) {
    const btn     = form.querySelector('.btn-login');
    const btnSpan = btn.querySelector('span');

    // Show loading state
    btn.disabled = true;
    btnSpan.textContent = 'Signing in…';

    // Remove any existing inline error
    const existing = form.querySelector('.alert-error-inline');
    if (existing) existing.remove();

    const formData = new FormData(form);
    formData.append('login', '1');

    fetch('login_ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
        if (data.success) {
            btnSpan.textContent = 'Redirecting…';
            // Fade out and redirect
            document.querySelector('.login-wrapper').style.transition = 'opacity 0.3s';
            document.querySelector('.login-wrapper').style.opacity = '0';
            setTimeout(function () { window.location.href = data.redirect; }, 300);
        } else {
            showInlineError(form, data.message || 'Login failed. Please try again.');
            btn.disabled = false;
            btnSpan.textContent = 'Sign In';
        }
    })
    .catch(function () {
        showInlineError(form, 'Network error. Please check your connection.');
        btn.disabled = false;
        btnSpan.textContent = 'Sign In';
    });
}
function showInlineError(form, message) {
    // --- PREVENT STACKING ---
    // Find any existing alerts and remove them immediately
    const existingAlerts = document.querySelectorAll('.alert-error-inline');
    existingAlerts.forEach(alert => alert.remove());

    const div = document.createElement('div');
    div.className = 'alert alert-error alert-error-inline';
    div.innerHTML = `
        <svg class="alert-icon" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd""")/>>
        </svg>
        <div><strong>Login Failed</strong><p>${message}</p></div>
    `;
    
    div.style.animation = 'slideDown 0.3s ease-out';
    form.insertAdjacentElement('beforebegin', div);

    // --- FADE OUT LOGIC (0.5 SECONDS) ---
    setTimeout(() => {
        // Check if the div is still in the DOM (it might have been removed by a new error)
        if (div.parentNode) {
            div.style.transition = 'opacity 0.5s ease-out, transform 0.5s ease-out';
            div.style.opacity = '0';
            div.style.transform = 'translateY(-5px)';
            
            setTimeout(() => div.remove(), 500);
        }
    }, 3000);

    // Shake the form
    form.style.animation = 'none';
    form.offsetHeight; 
    form.style.animation = 'shake 0.4s ease';
}