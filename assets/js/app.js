/* TPMS - Application JavaScript */

document.addEventListener('DOMContentLoaded', function() {
    // Mobile sidebar toggle
    const sidebarToggle = document.getElementById('sidebar-toggle');
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', toggleSidebar);
    }

    // Initialize animations on scroll
    initScrollAnimations();

    // Initialize counters
    initCounters();

    // Initialize tooltips
    initTooltips();

    // Initialize delete confirmations
    initDeleteConfirmations();

    // Initialize search filters
    initSearchFilters();

    // Prevent duplicate form submissions
    initDuplicatePrevention();
});

/**
 * Custom Dialog System
 */
function showDialog(options) {
    return new Promise((resolve) => {
        const dialog = document.getElementById('custom-dialog');
        if (!dialog) {
            console.error('Custom dialog container is missing:', options.message);
            resolve(false);
            return;
        }

        const backdrop = dialog.querySelector('.dialog-backdrop');
        const panel = dialog.querySelector('.dialog-panel');
        const titleEl = document.getElementById('dialog-title');
        const messageEl = document.getElementById('dialog-message');
        const iconEl = document.getElementById('dialog-icon');
        const iconSymbolEl = document.getElementById('dialog-icon-symbol');
        const confirmBtn = document.getElementById('dialog-confirm');
        const cancelBtn = document.getElementById('dialog-cancel');

        // Set content
        titleEl.textContent = options.title || 'Confirm Action';
        messageEl.textContent = options.message || 'Are you sure?';
        confirmBtn.textContent = options.confirmText || (options.type === 'alert' ? 'OK' : 'Confirm');
        cancelBtn.textContent = options.cancelText || 'Cancel';

        // Configure styling based on type
        const type = options.type || 'confirm';
        const style = options.style || (type === 'alert' ? 'info' : 'danger');

        const styles = {
            danger: { bg: 'bg-red-600', hover: 'hover:bg-red-700', ring: 'focus:ring-red-500', iconBg: 'bg-red-100', icon: 'fa-exclamation-triangle', iconColor: 'text-red-600' },
            warning: { bg: 'bg-yellow-600', hover: 'hover:bg-yellow-700', ring: 'focus:ring-yellow-500', iconBg: 'bg-yellow-100', icon: 'fa-exclamation-circle', iconColor: 'text-yellow-600' },
            info: { bg: 'bg-blue-600', hover: 'hover:bg-blue-700', ring: 'focus:ring-blue-500', iconBg: 'bg-blue-100', icon: 'fa-info-circle', iconColor: 'text-blue-600' },
            success: { bg: 'bg-green-600', hover: 'hover:bg-green-700', ring: 'focus:ring-green-500', iconBg: 'bg-green-100', icon: 'fa-check-circle', iconColor: 'text-green-600' }
        };

        const s = styles[style] || styles.info;
        confirmBtn.className = `inline-flex w-full justify-center rounded-lg px-4 py-2 text-sm font-semibold text-white shadow-sm sm:w-auto transition-all hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-offset-2 ${s.bg} ${s.hover} ${s.ring}`;
        iconEl.className = `mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full ${s.iconBg} sm:mx-0 sm:h-10 sm:w-10`;
        iconSymbolEl.className = `fas ${s.icon} ${s.iconColor}`;

        // Show/hide cancel button for alerts
        if (type === 'alert') {
            cancelBtn.classList.add('hidden');
        } else {
            cancelBtn.classList.remove('hidden');
        }

        // Show dialog
        dialog.classList.remove('hidden');
        document.body.style.overflow = 'hidden';

        // Animate in
        requestAnimationFrame(() => {
            backdrop.classList.remove('opacity-0');
            panel.classList.remove('opacity-0', 'scale-95');
            panel.classList.add('opacity-100', 'scale-100');
        });

        function closeDialog(result) {
            // Animate out
            backdrop.classList.add('opacity-0');
            panel.classList.remove('opacity-100', 'scale-100');
            panel.classList.add('opacity-0', 'scale-95');

            setTimeout(() => {
                dialog.classList.add('hidden');
                document.body.style.overflow = '';
                cleanup();
                resolve(result);
            }, 200);
        }

        function cleanup() {
            confirmBtn.removeEventListener('click', onConfirm);
            cancelBtn.removeEventListener('click', onCancel);
            backdrop.removeEventListener('click', onCancel);
            document.removeEventListener('keydown', onKeydown);
        }

        function onConfirm() {
            closeDialog(true);
        }

        function onCancel() {
            closeDialog(false);
        }

        function onKeydown(e) {
            if (e.key === 'Escape') {
                closeDialog(false);
            } else if (e.key === 'Enter' && type === 'alert') {
                closeDialog(true);
            }
        }

        confirmBtn.addEventListener('click', onConfirm);
        cancelBtn.addEventListener('click', onCancel);
        backdrop.addEventListener('click', onCancel);
        document.addEventListener('keydown', onKeydown);
    });
}

function showAlert(message, title, style = 'info') {
    return showDialog({ type: 'alert', title: title || 'Notice', message, style });
}

function showConfirm(message, title, style = 'danger') {
    return showDialog({ type: 'confirm', title: title || 'Confirm Action', message, style });
}

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    if (sidebar && overlay) {
        const isClosed = sidebar.classList.contains('-translate-x-full');
        if (isClosed) {
            sidebar.classList.remove('-translate-x-full');
            overlay.classList.remove('hidden');
        } else {
            sidebar.classList.add('-translate-x-full');
            overlay.classList.add('hidden');
        }
    }
}

function initScrollAnimations() {
    const animatedElements = document.querySelectorAll('[data-animate]');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const animation = entry.target.dataset.animate;
                entry.target.classList.add(animation);
                entry.target.style.opacity = '1';
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });

    animatedElements.forEach(el => {
        el.style.opacity = '0';
        observer.observe(el);
    });
}

function initCounters() {
    const counters = document.querySelectorAll('[data-counter]');
    counters.forEach(counter => {
        const target = parseInt(counter.dataset.counter);
        const duration = parseInt(counter.dataset.duration) || 1500;
        const prefix = counter.dataset.prefix || '';
        const suffix = counter.dataset.suffix || '';
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    animateCounter(counter, target, duration, prefix, suffix);
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.5 });

        observer.observe(counter);
    });
}

function animateCounter(element, target, duration, prefix, suffix) {
    const start = 0;
    const increment = target / (duration / 16);
    let current = start;
    
    const timer = setInterval(() => {
        current += increment;
        if (current >= target) {
            current = target;
            clearInterval(timer);
        }
        element.textContent = prefix + Math.floor(current).toLocaleString() + suffix;
    }, 16);
}

function initTooltips() {
    const tooltips = document.querySelectorAll('[data-tooltip]');
    tooltips.forEach(el => {
        el.addEventListener('mouseenter', function() {
            const text = this.dataset.tooltip;
            const tooltip = document.createElement('div');
            tooltip.className = 'fixed z-50 px-2 py-1 text-xs text-white bg-gray-900 rounded shadow-lg pointer-events-none';
            tooltip.textContent = text;
            tooltip.id = 'active-tooltip';
            document.body.appendChild(tooltip);
            
            const rect = this.getBoundingClientRect();
            tooltip.style.left = rect.left + rect.width / 2 - tooltip.offsetWidth / 2 + 'px';
            tooltip.style.top = rect.top - tooltip.offsetHeight - 8 + 'px';
        });
        
        el.addEventListener('mouseleave', function() {
            const tooltip = document.getElementById('active-tooltip');
            if (tooltip) tooltip.remove();
        });
    });
}

function initDeleteConfirmations() {
    document.querySelectorAll('[data-confirm]').forEach(el => {
        el.addEventListener('click', async function(e) {
            e.preventDefault();
            const message = this.dataset.confirm || 'Are you sure you want to delete this item?';
            const title = this.dataset.confirmTitle || 'Confirm Action';
            const style = this.dataset.confirmStyle || 'danger';
            const confirmed = await showConfirm(message, title, style);
            if (confirmed) {
                window.location.href = this.href;
            }
        });
    });
}

function initSearchFilters() {
    const searchInputs = document.querySelectorAll('[data-search]');
    searchInputs.forEach(input => {
        input.addEventListener('input', function() {
            const target = this.dataset.search;
            const term = this.value.toLowerCase();
            const rows = document.querySelectorAll(target);
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(term) ? '' : 'none';
            });
        });
    });
}

function initDuplicatePrevention() {
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function() {
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
            }
        });
    });
}

// Modal functions
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.style.overflow = '';
    }
}

// Tab functions
function switchTab(tabId, contentId) {
    document.querySelectorAll('[data-tab]').forEach(tab => {
        tab.classList.remove('border-primary-600', 'text-primary-600');
        tab.classList.add('border-transparent', 'text-gray-500');
    });
    
    document.querySelectorAll('[data-tab-content]').forEach(content => {
        content.classList.add('hidden');
    });
    
    const activeTab = document.querySelector(`[data-tab="${tabId}"]`);
    const activeContent = document.getElementById(contentId);
    
    if (activeTab) {
        activeTab.classList.remove('border-transparent', 'text-gray-500');
        activeTab.classList.add('border-primary-600', 'text-primary-600');
    }
    if (activeContent) {
        activeContent.classList.remove('hidden');
    }
}

// Drag and drop for pipeline (deals and projects)
function allowDrop(ev) {
    ev.preventDefault();
    const column = ev.target.closest('.pipeline-column');
    if (column) column.classList.add('drag-over');
}

function drag(ev) {
    const card = ev.target.closest('.deal-card');
    if (!card) return;
    if (card.dataset.dealId) {
        ev.dataTransfer.setData("dealId", card.dataset.dealId);
    } else if (card.dataset.projectId) {
        ev.dataTransfer.setData("projectId", card.dataset.projectId);
    }
    card.classList.add('opacity-50');
}

function drop(ev) {
    ev.preventDefault();
    const column = ev.target.closest('.pipeline-column');
    if (column) {
        column.classList.remove('drag-over');
        const dealId = ev.dataTransfer.getData("dealId");
        const projectId = ev.dataTransfer.getData("projectId");
        const stage = column.dataset.stage;
        const status = column.dataset.status;
        if (dealId && stage) {
            updateDealStage(dealId, stage);
        } else if (projectId && status) {
            updateProjectStatus(projectId, status);
        }
    }
    document.querySelectorAll('.deal-card').forEach(card => card.classList.remove('opacity-50'));
}

function dragLeave(ev) {
    const column = ev.target.closest('.pipeline-column');
    if (column) column.classList.remove('drag-over');
}

function updateDealStage(dealId, stage) {
    fetch('api/deal_actions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=update_stage&deal_id=${dealId}&stage=${stage}&csrf_token=${getCsrfToken()}`
    })
    .then(response => response.json())
    .then(async data => {
        if (data.success) {
            window.location.reload();
        } else {
            await showAlert(data.message || 'Failed to update deal stage', 'Error', 'danger');
        }
    })
    .catch(async error => {
        console.error('Error:', error);
        await showAlert('An error occurred while updating the deal stage.', 'Error', 'danger');
    });
}

function updateProjectStatus(projectId, status) {
    fetch('api/project_actions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=update_status&project_id=${projectId}&status=${status}&csrf_token=${getCsrfToken()}`
    })
    .then(response => response.json())
    .then(async data => {
        if (data.success) {
            window.location.reload();
        } else {
            await showAlert(data.message || 'Failed to update project status', 'Error', 'danger');
        }
    })
    .catch(async error => {
        console.error('Error:', error);
        await showAlert('An error occurred while updating the project status.', 'Error', 'danger');
    });
}

function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.content : '';
}
