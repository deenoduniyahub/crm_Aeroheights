/**
 * Master Client-Side Application Engine
 * Handles CSRF injection, global API wrappers, toast alerts, and modal injection.
 */

// CSRF Protected Fetch Wrapper
async function apiRequest(endpoint, payload = null, method = 'POST') {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    
    const options = {
        method: method,
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
        }
    };

    if (payload && method !== 'GET') {
        options.body = JSON.stringify(payload);
    }

    try {
        const response = await fetch(endpoint, options);
        if (!response.ok) {
            const errData = await response.json().catch(() => ({}));
            throw new Error(errData.message || `Server returned error status ${response.status}`);
        }
        return await response.json();
    } catch (error) {
        showToast('error', error.message || 'Network communication error');
        throw error;
    }
}

// Global Toast Notification Generator
function showToast(type, message) {
    let container = document.getElementById('globalToastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'globalToastContainer';
        container.className = 'fixed top-4 right-4 z-50 space-y-2 no-print';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    const bgClass = type === 'success' ? 'bg-emerald-600' : (type === 'error' ? 'bg-rose-600' : 'bg-slate-900');
    const iconClass = type === 'success' ? 'fa-circle-check' : (type === 'error' ? 'fa-triangle-exclamation' : 'fa-circle-info');

    toast.className = `flex items-center p-3.5 rounded-xl shadow-2xl text-white text-xs font-semibold ${bgClass} transition-all duration-300 transform translate-y-0 opacity-100`;
    toast.innerHTML = `
        <i class="fa-solid ${iconClass} text-sm mr-2.5"></i>
        <span>${message}</span>
        <button onclick="this.parentElement.remove()" class="ml-4 text-white/80 hover:text-white"><i class="fa-solid fa-xmark"></i></button>
    `;

    container.appendChild(toast);

    setTimeout(() => {
        toast.classList.add('opacity-0', '-translate-y-2');
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}

// Modal Dismissal Handler
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        const modal = document.getElementById('dynamicModalContainer');
        if (modal) modal.innerHTML = '';
    }
});

