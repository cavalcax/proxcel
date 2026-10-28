/**
 * Proxcel - JavaScript Global
 */

// ============================================================
// SIDEBAR
// ============================================================
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.toggle('-translate-x-full');
    overlay.classList.toggle('hidden');
}

// Close sidebar on resize to desktop
window.addEventListener('resize', () => {
    if (window.innerWidth >= 1024) {
        const overlay = document.getElementById('sidebarOverlay');
        if (overlay) overlay.classList.add('hidden');
    }
});

// ============================================================
// USER MENU
// ============================================================
function toggleUserMenu() {
    const menu = document.getElementById('userMenu');
    menu.classList.toggle('hidden');
}

// Close user menu on outside click
document.addEventListener('click', (e) => {
    const container = document.getElementById('userMenuContainer');
    const menu = document.getElementById('userMenu');
    if (container && menu && !container.contains(e.target)) {
        menu.classList.add('hidden');
    }
});

// ============================================================
// CSRF TOKEN
// ============================================================
function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

// ============================================================
// AJAX HELPER
// ============================================================
function ajaxRequest(url, data = {}, method = 'POST') {
    data.csrf_token = getCsrfToken();
    
    return $.ajax({
        url: url,
        type: method,
        data: data,
        dataType: 'json',
        headers: {
            'X-CSRF-TOKEN': getCsrfToken(),
            'X-Requested-With': 'XMLHttpRequest'
        }
    });
}

function ajaxFormData(url, formData) {
    formData.append('csrf_token', getCsrfToken());
    
    return $.ajax({
        url: url,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        headers: {
            'X-CSRF-TOKEN': getCsrfToken(),
            'X-Requested-With': 'XMLHttpRequest'
        }
    });
}

// ============================================================
// SWEETALERT2 HELPERS
// ============================================================
const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
});

function showSuccess(message) {
    Toast.fire({ icon: 'success', title: message });
}

function showError(message) {
    Toast.fire({ icon: 'error', title: message, timer: 4000 });
}

function showInfo(message) {
    Toast.fire({ icon: 'info', title: message });
}

function confirmDelete(callback) {
    Swal.fire({
        title: 'Tem certeza?',
        text: 'Esta ação não pode ser desfeita!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sim, excluir!',
        cancelButtonText: 'Cancelar',
        reverseButtons: true,
        customClass: {
            confirmButton: 'btn btn-danger',
            cancelButton: 'btn btn-secondary'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            callback();
        }
    });
}

function confirmAction(title, text, callback) {
    Swal.fire({
        title: title,
        text: text,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sim, confirmar!',
        cancelButtonText: 'Cancelar',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            callback();
        }
    });
}

// ============================================================
// PROXCEL DYNAMIC DATA TABLE (Sorting, Pagination, Filtering)
// ============================================================
window._proxcelTables = window._proxcelTables || {};

class ProxcelTable {
    constructor(tableSelector, options = {}) {
        this.table = typeof tableSelector === 'string' ? document.querySelector(tableSelector) : tableSelector;
        if (!this.table) return;

        if (!this.table.id) {
            this.table.id = 'table_' + Math.random().toString(36).substr(2, 9);
        }
        window._proxcelTables[this.table.id] = this;

        this.tbody = this.table.querySelector('tbody');
        if (!this.tbody) return;

        this.headers = Array.from(this.table.querySelectorAll('thead th'));
        this.allRows = Array.from(this.tbody.querySelectorAll('tr:not([id*="no"]):not([id*="empty"])'));
        this.filteredRows = [...this.allRows];

        this.options = Object.assign({
            perPage: 10,
            perPageOptions: [10, 25, 50, 100],
            currentPage: 1,
            searchInput: null,
            customFilter: null
        }, options);

        this.currentPage = this.options.currentPage;
        this.perPage = this.options.perPage;
        this.isAll = false;
        this.sortCol = -1;
        this.sortAsc = true;

        this.init();
    }

    init() {
        this.setupSorting();
        this.setupFooter();
        this.setupSearch();
        this.render();
    }

    setupSorting() {
        this.headers.forEach((th, idx) => {
            const text = th.textContent.trim().toLowerCase();
            if (th.classList.contains('text-center') || text.includes('ações') || text.includes('acoes') || th.hasAttribute('data-no-sort')) {
                return;
            }

            th.classList.add('sortable');
            const iconSpan = document.createElement('span');
            iconSpan.className = 'sort-icon';
            iconSpan.innerHTML = '<i class="fas fa-sort"></i>';
            th.appendChild(iconSpan);

            th.addEventListener('click', () => {
                if (this.sortCol === idx) {
                    this.sortAsc = !this.sortAsc;
                } else {
                    this.sortCol = idx;
                    this.sortAsc = true;
                }
                this.sort(this.sortCol, this.sortAsc);
            });
        });
    }

    parseValue(val) {
        val = val.trim();
        // Date DD/MM/YYYY or DD/MM/YYYY HH:MM
        const dateMatch = val.match(/^(\d{2})\/(\d{2})\/(\d{4})(?:\s+(\d{2}):(\d{2}))?/);
        if (dateMatch) {
            const [_, d, m, y, h = '00', min = '00'] = dateMatch;
            return new Date(y, m - 1, d, h, min).getTime();
        }
        // Size format (KB, MB, GB, B)
        const sizeMatch = val.match(/^([\d.,]+)\s*(B|KB|MB|GB)$/i);
        if (sizeMatch) {
            const num = parseFloat(sizeMatch[1].replace(',', '.'));
            const unit = sizeMatch[2].toUpperCase();
            const mult = { B: 1, KB: 1024, MB: 1024 * 1024, GB: 1024 * 1024 * 1024 };
            return num * (mult[unit] || 1);
        }
        // Pure number
        const cleanNum = val.replace(/[^\d.,-]/g, '').replace(',', '.');
        if (cleanNum && !isNaN(cleanNum) && !val.includes('/')) {
            return parseFloat(cleanNum);
        }
        return val.toLowerCase();
    }

    sort(colIdx, asc) {
        this.headers.forEach((th, i) => {
            const icon = th.querySelector('.sort-icon');
            if (!icon) return;
            if (i === colIdx) {
                th.classList.add('active-sort');
                icon.innerHTML = asc ? '<i class="fas fa-sort-up"></i>' : '<i class="fas fa-sort-down"></i>';
            } else {
                th.classList.remove('active-sort');
                icon.innerHTML = '<i class="fas fa-sort"></i>';
            }
        });

        this.filteredRows.sort((a, b) => {
            const aCell = a.children[colIdx]?.textContent || '';
            const bCell = b.children[colIdx]?.textContent || '';
            const aVal = this.parseValue(aCell);
            const bVal = this.parseValue(bCell);

            if (aVal < bVal) return asc ? -1 : 1;
            if (aVal > bVal) return asc ? 1 : -1;
            return 0;
        });

        this.render();
    }

    setupFooter() {
        const cardParent = this.table.closest('.card') || this.table.parentElement;
        this.footer = document.createElement('div');
        this.footer.className = 'table-footer flex flex-col sm:flex-row items-center justify-between gap-4 p-4 border-t border-gray-100 bg-white rounded-b-2xl';
        cardParent.appendChild(this.footer);
    }

    setupSearch() {
        if (this.options.searchInput) {
            const input = typeof this.options.searchInput === 'string' ? document.querySelector(this.options.searchInput) : this.options.searchInput;
            if (input) {
                let debounce;
                input.addEventListener('input', () => {
                    clearTimeout(debounce);
                    debounce = setTimeout(() => this.applyFilters(), 100);
                });
            }
        }
    }

    applyFilters() {
        let searchTerm = '';
        if (this.options.searchInput) {
            const input = typeof this.options.searchInput === 'string' ? document.querySelector(this.options.searchInput) : this.options.searchInput;
            if (input) searchTerm = input.value.toLowerCase().trim();
        }

        this.filteredRows = this.allRows.filter(row => {
            const matchSearch = !searchTerm || row.textContent.toLowerCase().includes(searchTerm);
            let matchCustom = true;
            if (typeof this.options.customFilter === 'function') {
                matchCustom = this.options.customFilter(row);
            }
            return matchSearch && matchCustom;
        });

        if (this.sortCol !== -1) {
            this.sort(this.sortCol, this.sortAsc);
        } else {
            this.currentPage = 1;
            this.render();
        }
    }

    setPage(page) {
        this.currentPage = page;
        this.render();
    }

    setPerPage(count) {
        if (count === 'all') {
            this.isAll = true;
            this.perPage = 999999;
        } else {
            this.isAll = false;
            this.perPage = parseInt(count, 10) || 10;
        }
        this.currentPage = 1;
        this.render();
    }

    render() {
        const total = this.filteredRows.length;
        const effectivePerPage = this.isAll ? (total || 1) : this.perPage;
        const totalPages = Math.max(1, Math.ceil(total / effectivePerPage));

        if (this.currentPage > totalPages) this.currentPage = totalPages;
        if (this.currentPage < 1) this.currentPage = 1;

        const startIdx = (this.currentPage - 1) * effectivePerPage;
        const endIdx = this.isAll ? total : Math.min(startIdx + effectivePerPage, total);

        // Hide all rows
        this.allRows.forEach(r => r.style.display = 'none');

        // Show page slice of filtered
        for (let i = startIdx; i < endIdx; i++) {
            if (this.filteredRows[i]) {
                this.filteredRows[i].style.display = '';
            }
        }

        // Check empty feedback
        const noResults = this.table.querySelector('.no-results-row, #noDocResultsRow, #noClienteResultsRow, #noUserResultsRow, #noLogResultsRow');
        if (noResults) {
            noResults.style.display = (total === 0 && this.allRows.length > 0) ? '' : 'none';
        }

        // Render Footer
        this.renderFooter(total, startIdx, endIdx, totalPages);
    }

    renderFooter(total, startIdx, endIdx, totalPages) {
        if (!this.footer) return;

        const displayedStart = total === 0 ? 0 : startIdx + 1;
        const displayedEnd = endIdx;

        let optionsHtml = this.options.perPageOptions.map(opt => 
            `<option value="${opt}" ${(!this.isAll && this.perPage === opt) ? 'selected' : ''}>${opt}</option>`
        ).join('');
        optionsHtml += `<option value="all" ${this.isAll ? 'selected' : ''}>Todos</option>`;

        let paginationBtnsHtml = '';
        if (totalPages > 1) {
            paginationBtnsHtml += `
                <button type="button" class="table-pagination-btn" ${this.currentPage === 1 ? 'disabled' : ''} data-action="prev" title="Anterior">
                    <i class="fas fa-chevron-left text-xs"></i>
                </button>
            `;

            const startPage = Math.max(1, this.currentPage - 2);
            const endPage = Math.min(totalPages, this.currentPage + 2);

            if (startPage > 1) {
                paginationBtnsHtml += `<button type="button" class="table-pagination-btn" data-page="1">1</button>`;
                if (startPage > 2) paginationBtnsHtml += `<span class="px-1 text-gray-400 text-xs">...</span>`;
            }

            for (let p = startPage; p <= endPage; p++) {
                paginationBtnsHtml += `
                    <button type="button" class="table-pagination-btn ${p === this.currentPage ? 'active' : ''}" data-page="${p}">${p}</button>
                `;
            }

            if (endPage < totalPages) {
                if (endPage < totalPages - 1) paginationBtnsHtml += `<span class="px-1 text-gray-400 text-xs">...</span>`;
                paginationBtnsHtml += `<button type="button" class="table-pagination-btn" data-page="${totalPages}">${totalPages}</button>`;
            }

            paginationBtnsHtml += `
                <button type="button" class="table-pagination-btn" ${this.currentPage === totalPages ? 'disabled' : ''} data-action="next" title="Próximo">
                    <i class="fas fa-chevron-right text-xs"></i>
                </button>
            `;
        }

        this.footer.innerHTML = `
            <div class="flex flex-wrap items-center gap-2 sm:gap-3 text-xs text-gray-500">
                <div class="flex items-center gap-1.5">
                    <span>Exibir</span>
                    <select class="table-per-page-select">
                        ${optionsHtml}
                    </select>
                    <span>por página</span>
                </div>
                <span class="hidden sm:inline text-gray-300">|</span>
                <div>
                    Mostrando <strong class="text-gray-800 font-semibold">${displayedStart} a ${displayedEnd}</strong> de <strong class="text-gray-800 font-semibold">${total}</strong> registros
                </div>
            </div>
            <div class="flex items-center gap-1">
                ${paginationBtnsHtml}
            </div>
        `;

        // Bind events directly
        const select = this.footer.querySelector('.table-per-page-select');
        if (select) {
            select.addEventListener('change', (e) => {
                this.setPerPage(e.target.value);
            });
        }

        this.footer.querySelectorAll('.table-pagination-btn[data-page]').forEach(btn => {
            btn.addEventListener('click', () => {
                this.setPage(parseInt(btn.getAttribute('data-page'), 10));
            });
        });

        const prevBtn = this.footer.querySelector('.table-pagination-btn[data-action="prev"]');
        if (prevBtn && !prevBtn.disabled) {
            prevBtn.addEventListener('click', () => this.setPage(this.currentPage - 1));
        }

        const nextBtn = this.footer.querySelector('.table-pagination-btn[data-action="next"]');
        if (nextBtn && !nextBtn.disabled) {
            nextBtn.addEventListener('click', () => this.setPage(this.currentPage + 1));
        }
    }
}

// ============================================================
// SELECT2 INITIALIZATION
// ============================================================
function initSelect2() {
    if (typeof $.fn.select2 === 'undefined') return;
    
    $('.select2').select2({
        language: 'pt-BR',
        placeholder: 'Selecione...',
        allowClear: true,
        width: '100%'
    });
}

// ============================================================
// INPUT MASKS
// ============================================================
function initMasks() {
    if (typeof $.fn.inputmask === 'undefined') return;
    
    $('[data-mask="cpf"]').inputmask('999.999.999-99');
    $('[data-mask="cnpj"]').inputmask('99.999.999/9999-99');
    $('[data-mask="phone"]').inputmask(['(99) 9999-9999', '(99) 99999-9999']);
    $('[data-mask="cep"]').inputmask('99999-999');
    $('[data-mask="date"]').inputmask('99/99/9999');
}

// ============================================================
// PASSWORD VALIDATION
// ============================================================
function validatePassword(password) {
    const errors = [];
    if (password.length < 8) errors.push('Mínimo 8 caracteres');
    if (!/[A-Z]/.test(password)) errors.push('Uma letra maiúscula');
    if (!/[0-9]/.test(password)) errors.push('Um número');
    if (!/[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)) errors.push('Um caractere especial');
    return errors;
}

function initPasswordValidation(inputSelector, feedbackSelector) {
    const input = document.querySelector(inputSelector);
    const feedback = document.querySelector(feedbackSelector);
    if (!input || !feedback) return;

    input.addEventListener('input', function() {
        const errors = validatePassword(this.value);
        if (this.value.length === 0) {
            feedback.innerHTML = '';
            return;
        }
        
        const rules = [
            { test: this.value.length >= 8, label: '8+ caracteres' },
            { test: /[A-Z]/.test(this.value), label: 'Maiúscula' },
            { test: /[0-9]/.test(this.value), label: 'Número' },
            { test: /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(this.value), label: 'Especial' }
        ];

        feedback.innerHTML = rules.map(r => 
            `<span class="inline-flex items-center gap-1 text-xs ${r.test ? 'text-green-600' : 'text-gray-400'}">
                <i class="fas ${r.test ? 'fa-check-circle' : 'fa-circle'} text-[10px]"></i>${r.label}
            </span>`
        ).join(' ');
    });
}

// ============================================================
// FILE UPLOAD ZONE
// ============================================================
function initUploadZone(zoneSelector, inputSelector) {
    const zone = document.querySelector(zoneSelector);
    const input = document.querySelector(inputSelector);
    if (!zone || !input) return;

    zone.addEventListener('click', () => input.click());

    zone.addEventListener('dragover', (e) => {
        e.preventDefault();
        zone.classList.add('dragover');
    });

    zone.addEventListener('dragleave', () => {
        zone.classList.remove('dragover');
    });

    zone.addEventListener('drop', (e) => {
        e.preventDefault();
        zone.classList.remove('dragover');
        input.files = e.dataTransfer.files;
        input.dispatchEvent(new Event('change'));
    });
}

// ============================================================
// PWA SERVICE WORKER
// ============================================================
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        const swPath = document.querySelector('meta[name="csrf-token"]')?.closest('head')?.querySelector('link[rel="manifest"]')?.href;
        const baseUrl = swPath ? new URL(swPath).origin + new URL(swPath).pathname.replace('/manifest.json', '') : '';
        
        navigator.serviceWorker.register(baseUrl + '/sw.js')
            .then(reg => console.log('SW registered:', reg.scope))
            .catch(err => console.log('SW registration failed:', err));
    });
}

// PWA Install Prompt
let deferredPrompt;
window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredPrompt = e;
    
    // Show install banner
    const banner = document.getElementById('pwa-banner');
    if (banner) {
        banner.classList.add('show');
    }
});

function installPWA() {
    if (!deferredPrompt) return;
    deferredPrompt.prompt();
    deferredPrompt.userChoice.then(choice => {
        if (choice.outcome === 'accepted') {
            showSuccess('App instalado com sucesso!');
        }
        deferredPrompt = null;
        const banner = document.getElementById('pwa-banner');
        if (banner) banner.classList.remove('show');
    });
}

function dismissPWA() {
    const banner = document.getElementById('pwa-banner');
    if (banner) banner.classList.remove('show');
}

// ============================================================
// DYNAMIC TABLE SEARCH HELPER
// ============================================================
function initDynamicSearch(inputSelector, tableSelector) {
    const input = document.querySelector(inputSelector);
    const table = document.querySelector(tableSelector);
    if (!input || !table) return;

    input.addEventListener('input', () => {
        const query = input.value.toLowerCase().trim();
        const rows = table.querySelectorAll('tbody tr:not(.no-results-row):not([id*="no"]):not([id*="empty"])');
        let visibleCount = 0;

        rows.forEach(row => {
            const match = !query || row.textContent.toLowerCase().includes(query);
            row.style.display = match ? '' : 'none';
            if (match) visibleCount++;
        });

        const noResults = table.querySelector('.no-results-row, #noDocResultsRow, #noClienteResultsRow, #noUserResultsRow, #noLogResultsRow');
        if (noResults) {
            noResults.style.display = (visibleCount === 0 && rows.length > 0) ? '' : 'none';
        }
    });
}

// ============================================================
// INITIALIZATION
// ============================================================
document.addEventListener('DOMContentLoaded', () => {
    initSelect2();
    initMasks();
    initDynamicSearch('#searchInput', '#dataTable');
    
    // Password validation
    initPasswordValidation('#senha', '#senha-feedback');
    initPasswordValidation('#nova_senha', '#nova-senha-feedback');
});

