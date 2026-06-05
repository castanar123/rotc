// ROTC QR Inventory Management JavaScript

// Helper function to get correct API URL for both localhost and production
function getApiUrl(endpoint) {
    // Get the current page's base URL
    const currentUrl = window.location.href;
    const baseUrl = currentUrl.substring(0, currentUrl.lastIndexOf('/') + 1);
    let url = baseUrl + 'api/' + endpoint;
    // Cache bust to avoid stale API responses (e.g., behind CDN)
    url += (url.includes('?') ? '&' : '?') + '_v=' + Date.now();
    return url;
}

// Edit Supply Item (name, absolute quantity, category, unit, returnable)
function editSupplyItem(itemId, currentName, currentQty, currentCategory, currentUnit, currentReturnable) {
    if (!itemId) { showError('Invalid item'); return; }
    const modalHtml = `
        <div class="modal fade" id="editSupplyModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content bg-dark text-light">
                    <div class="modal-header border-secondary">
                        <h5 class="modal-title">Edit Supply Item</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="editSupplyForm">
                            <input type="hidden" id="editItemId" value="${itemId}">
                            <div class="mb-3">
                                <label class="form-label">Name</label>
                                <input type="text" class="form-control" id="editItemName" value="${escapeHtml(currentName || '')}">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Total Quantity</label>
                                <input type="number" class="form-control" id="editItemQuantity" min="0" step="1" value="${Number.isFinite(currentQty) ? currentQty : 0}">
                                <div class="form-text text-muted">Leave as-is if you don't want to change.</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Category</label>
                                <select class="form-select" id="editItemCategory">
                                    <option value="" ${String(currentCategory||'')===''?'selected':''}>— Keep current —</option>
                                    <option value="Consumable" ${String(currentCategory||'')==='Consumable'?'selected':''}>Consumable</option>
                                    <option value="Non-consumable" ${String(currentCategory||'')==='Non-consumable'?'selected':''}>Non-consumable</option>
                                    <option value="Semi-expendable" ${String(currentCategory||'')==='Semi-expendable'?'selected':''}>Semi-expendable</option>
                                    <option value="Capital" ${String(currentCategory||'')==='Capital'?'selected':''}>Capital</option>
                                    <option value="Disposable" ${String(currentCategory||'')==='Disposable'?'selected':''}>Disposable</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Unit</label>
                                <input type="text" class="form-control" id="editItemUnit" placeholder="e.g. pcs, box, set" value="${escapeHtml(currentUnit || '')}">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Returnable</label>
                                <select class="form-select" id="editItemReturnable">
                                    <option value="">— Keep current —</option>
                                    <option value="returnable" ${String(currentReturnable||'').toLowerCase()==='returnable'?'selected':''}>Returnable</option>
                                    <option value="non-returnable" ${String(currentReturnable||'').toLowerCase()==='non-returnable'?'selected':''}>Non-returnable</option>
                                </select>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer border-secondary">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" id="saveEditSupplyBtn" class="btn btn-success">
                            <i class="fas fa-save me-2"></i>Save changes
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    // Remove existing modal if any
    const existing = document.getElementById('editSupplyModal');
    if (existing) { existing.remove(); }
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    const modalEl = document.getElementById('editSupplyModal');
    const modal = new bootstrap.Modal(modalEl);
    modal.show();

    const saveBtn = document.getElementById('saveEditSupplyBtn');
    if (saveBtn && !saveBtn.dataset.bound) {
        saveBtn.addEventListener('click', function() {
            if (saveBtn.dataset.clicked === '1') return;
            saveBtn.dataset.clicked = '1';
            const send = { id: (/^\d+$/.test(String(itemId)) ? parseInt(itemId,10) : itemId) };

            const nameVal = document.getElementById('editItemName').value.trim();
            if (nameVal && nameVal !== (currentName || '')) send.item_name = nameVal;

            const qtyValRaw = document.getElementById('editItemQuantity').value;
            if (qtyValRaw !== '' && qtyValRaw !== null) {
                const q = parseInt(qtyValRaw, 10);
                if (!Number.isFinite(q) || q < 0) {
                    showError('Quantity must be a non-negative integer');
                    saveBtn.dataset.clicked = '';
                    return;
                }
                if (!Number.isNaN(q) && q !== currentQty) send.quantity = q;
            }

            const catVal = document.getElementById('editItemCategory').value;
            if (catVal && catVal !== (currentCategory || '')) send.category = catVal;

            const unitVal = document.getElementById('editItemUnit').value.trim();
            if (unitVal && unitVal !== (currentUnit || '')) send.unit = unitVal;

            const retVal = document.getElementById('editItemReturnable').value;
            if (retVal && retVal.toLowerCase() !== String(currentReturnable||'').toLowerCase()) {
                const rv = retVal.toLowerCase();
                if (['returnable','non-returnable'].includes(rv)) send.can_be_returned = rv;
            }

            if (!send.item_name && (send.quantity === undefined) && !send.category && !send.unit && !send.can_be_returned) {
                showError('No changes to save');
                saveBtn.dataset.clicked = '';
                return;
            }

            showLoading('Updating item...');
            fetch(getApiUrl('supply.php?action=update_item'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(send)
            })
            .then(async (r) => { const t = await r.text(); try { return JSON.parse(t); } catch(e){ console.error('Update item raw:', t); return {success:false, message:t||('HTTP '+r.status)}; }})
            .then(d => {
                hideLoading();
                if (d && d.success) {
                    try { bootstrap.Modal.getInstance(modalEl).hide(); } catch (e) {}
                    cleanupBootstrapModals();
                    showSuccess('Item updated successfully');
                    loadSupplyItems();
                    try { loadTextLogs(300); } catch (e) {}
                } else {
                    showError('Update failed: ' + (d && d.message ? d.message : 'Unknown error'));
                    saveBtn.dataset.clicked = '';
                }
            })
            .catch(err => { hideLoading(); console.error(err); showError('Network error while updating'); saveBtn.dataset.clicked = ''; });
        });
        saveBtn.dataset.bound = '1';
    }

    modalEl.addEventListener('hidden.bs.modal', function() {
        setTimeout(() => { cleanupBootstrapModals(); }, 50);
    });
}

// Delete Supply Item (PIN 472005) via modal confirm
function deleteSupplyItem(itemId, itemName) {
    if (!itemId) { showError('Invalid item'); return; }
    const modalHtml = `
        <div class="modal fade" id="deleteSupplyModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content bg-dark text-light">
                    <div class="modal-header border-secondary">
                        <h5 class="modal-title">Delete Item</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete <strong>${escapeHtml(itemName || 'this item')}</strong>? This action cannot be undone.</p>
                        <div class="mb-3">
                            <label class="form-label">Reason for deletion</label>
                            <textarea class="form-control" id="deleteSupplyReason" rows="3" placeholder="Explain why this item is being deleted (required)"></textarea>
                            <div class="form-text text-muted">This reason will be recorded in the logs.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">PIN</label>
                            <input type="password" class="form-control" id="deleteSupplyPin" placeholder="Enter PIN" autocomplete="off">
                        </div>
                    </div>
                    <div class="modal-footer border-secondary">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" id="confirmDeleteSupplyBtn" class="btn btn-danger">
                            <i class="fas fa-trash me-2"></i>Delete
                        </button>
                    </div>
                </div>
            </div>
        </div>`;

    const existing = document.getElementById('deleteSupplyModal');
    if (existing) existing.remove();
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    const modalEl = document.getElementById('deleteSupplyModal');
    const modal = new bootstrap.Modal(modalEl);
    modal.show();

    const input = document.getElementById('deleteSupplyPin');
    if (input && !input.dataset.bound) {
        input.addEventListener('keydown', function(ev) {
            if (ev.key === 'Enter') {
                const btn = document.getElementById('confirmDeleteSupplyBtn');
                if (btn) btn.click();
            }
        });
        input.dataset.bound = '1';
        setTimeout(() => { try { input.focus(); } catch (e) {} }, 150);
    }

    const confirmBtn = document.getElementById('confirmDeleteSupplyBtn');
    if (confirmBtn && !confirmBtn.dataset.bound) {
        confirmBtn.addEventListener('click', function() {
            if (confirmBtn.dataset.clicked === '1') return;
            const pin = (document.getElementById('deleteSupplyPin')?.value || '').trim();
            const reason = (document.getElementById('deleteSupplyReason')?.value || '').trim();
            if (!reason || reason.length < 3) { showError('Please provide a reason (at least 3 characters)'); return; }
            if (!pin) { showError('Please enter PIN'); return; }
            confirmBtn.dataset.clicked = '1';
            showLoading('Deleting item...');
            fetch(getApiUrl('supply.php?action=delete_item'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: (/^\d+$/.test(String(itemId)) ? parseInt(itemId,10) : itemId), pin, reason })
            })
            .then(async (response) => {
                const text = await response.text();
                try { return JSON.parse(text); } catch (e) { console.error('Delete item raw:', text); return { success:false, message: text || `HTTP ${response.status}`}; }
            })
            .then(data => {
                hideLoading();
                if (data && data.success) {
                    try { bootstrap.Modal.getInstance(modalEl).hide(); } catch (e) {}
                    cleanupBootstrapModals();
                    showSuccess('Item deleted successfully');
                    loadSupplyItems();
                    try { loadTextLogs(300); } catch (e) {}
                } else {
                    showError('Failed to delete item: ' + (data && data.message ? data.message : 'Unknown error'));
                    confirmBtn.dataset.clicked = '';
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Error deleting item:', error);
                showError('Error deleting item');
                confirmBtn.dataset.clicked = '';
            });
        });
        confirmBtn.dataset.bound = '1';
    }

    modalEl.addEventListener('hidden.bs.modal', function() {
        setTimeout(() => { cleanupBootstrapModals(); }, 50);
    });
}

// Load tail of text logs and summary stats into the Logs tab
function loadTextLogs(tail) {
    try {
        const linesEl = document.getElementById('textLogsContent');
        if (linesEl) {
            linesEl.textContent = 'Loading logs...';
        }
        let url = getApiUrl('read_log.php');
        url += (url.includes('?') ? '&' : '?') + 'tail=' + encodeURIComponent(tail || 200);
        fetch(url)
            .then(r => r.json())
            .then(data => {
                if (!data || !data.success) {
                    if (linesEl) linesEl.textContent = 'Failed to load logs' + (data && data.message ? (': ' + data.message) : '');
                    return;
                }
                // Cache raw results for filtering
                window._logLines = Array.isArray(data.lines) ? data.lines : null;
                window._logContent = typeof data.content === 'string' ? data.content : (Array.isArray(data.lines) ? data.lines.join('\n') : '');
                // Apply current filters to render
                applyLogFilters();
                // Summary badges
                const s = data.summary || null;
                if (s) {
                    const elItems = document.getElementById('logSummaryItems');
                    const elTotal = document.getElementById('logSummaryTotal');
                    const elAvail = document.getElementById('logSummaryAvailable');
                    const elBorr  = document.getElementById('logSummaryBorrowed');
                    if (elItems) elItems.textContent = String(s.items_count ?? '-');
                    if (elTotal) elTotal.textContent = String(s.total ?? '-');
                    if (elAvail) elAvail.textContent = String(s.available ?? '-');
                    if (elBorr)  elBorr.textContent  = String(s.borrowed ?? '-');
                }
            })
            .catch(err => {
                if (linesEl) linesEl.textContent = 'Error loading logs';
                console.error('loadTextLogs error:', err);
            });
    } catch (e) {
        console.warn('loadTextLogs failed to start:', e);
    }
}

// Escape HTML for safe innerHTML usage
function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, function(ch){
        switch (ch) {
            case '&': return '&amp;';
            case '<': return '&lt;';
            case '>': return '&gt;';
            case '"': return '&quot;';
            case "'": return '&#39;';
            default: return ch;
        }
    });
}

// Apply filters to cached logs and render
function applyLogFilters() {
    const linesEl = document.getElementById('textLogsContent');
    if (!linesEl) return;
    let lines = window._logLines;
    if (!Array.isArray(lines) && typeof window._logContent === 'string') {
        lines = window._logContent.split(/\r?\n/);
    }
    if (!Array.isArray(lines)) {
        linesEl.textContent = window._logContent || '(no log entries)';
        return;
    }
    const typeVal = (document.getElementById('logTypeFilter')?.value || '').toUpperCase();
    const q = (document.getElementById('logSearchInput')?.value || '').toLowerCase();
    const only = !!(document.getElementById('logOnlyMatches')?.checked);
    
    // Always apply type filter first
    let typeFiltered = lines.slice();
    if (typeVal && typeVal !== 'ALL') {
        const tag = '[' + typeVal + ']';
        typeFiltered = typeFiltered.filter(line => String(line).toUpperCase().includes(tag));
    }
    
    // If no search query, render typeFiltered as plain text
    if (!q) {
        linesEl.textContent = typeFiltered.join('\n') || '(no log entries)';
        return;
    }
    
    // With search query: if only==true, filter down to matches; else highlight matches
    if (only) {
        const filtered = typeFiltered.filter(line => String(line).toLowerCase().includes(q));
        linesEl.textContent = filtered.join('\n') || '(no log entries)';
    } else {
        // Highlight matches across all typeFiltered lines
        const esc = q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        const re = new RegExp(esc, 'ig');
        const html = typeFiltered.map(line => {
            const safe = escapeHtml(line);
            return safe.replace(re, (m) => `<mark>${escapeHtml(m)}</mark>`);
        }).join('\n');
        linesEl.innerHTML = html || '(no log entries)';
    }
}

// Helper: sanitize and clamp quantity to integer >=1 and <= max (if provided)
function sanitizeQuantity(raw, max) {
    let n = Number(raw);
    if (!Number.isFinite(n)) {
        // Strip non-digits and retry
        const digits = String(raw || '').replace(/[^0-9]/g, '');
        n = Number(digits);
    }
    if (!Number.isFinite(n)) return NaN;
    n = Math.floor(n);
    if (n <= 0) return NaN;
    if (Number.isFinite(max) && max > 0 && n > max) n = max;
    return n;
}

// Auto-load borrowed items when Return tab is shown or already active on load
document.addEventListener('DOMContentLoaded', function() {
    try {
        // Initialize full inventory system (forms, cart, tabs, etc.)
        if (typeof initializeInventorySystem === 'function') {
            initializeInventorySystem();
        }
        const returnTabBtn = document.querySelector('button[data-bs-target="#return"]');
        if (returnTabBtn) {
            returnTabBtn.addEventListener('shown.bs.tab', function() {
                loadBorrowedItems();
            });
        }
        const logsTabBtn = document.querySelector('button[data-bs-target="#logs"]');
        if (logsTabBtn) {
            logsTabBtn.addEventListener('shown.bs.tab', function() {
                try { loadTextLogs(300); } catch (e) { console.warn('Failed to load logs on tab show', e); }
            });
        }
        const returnTabPane = document.getElementById('return');
        if (returnTabPane && returnTabPane.classList.contains('active')) {
            loadBorrowedItems();
        }
    } catch (e) {
        console.warn('Auto-load borrowed items setup failed:', e);
    }
    // Initialize item selection modal events when shown
    try {
        const itemSelModalEl = document.getElementById('itemSelectionModal');
        if (itemSelModalEl && !itemSelModalEl.dataset.bound) {
            itemSelModalEl.addEventListener('shown.bs.modal', initializeItemSelectionModal);
            itemSelModalEl.dataset.bound = '1';
        }
    } catch (e) {}
});

// Global function declarations for inline onclick handlers
window.loadBorrowedItems = function() {
    const categoryFilter = document.getElementById('returnCategoryFilter')?.value || '';
    const borrowerFilterEl = document.getElementById('returnBorrowerFilter');
    // Send borrower NAME to backend which matches by LIKE on borrower_name
    const borrowerNameFilter = borrowerFilterEl && borrowerFilterEl.value
        ? (borrowerFilterEl.options[borrowerFilterEl.selectedIndex]?.dataset?.name || '')
        : '';
    
    let url = getApiUrl('borrowed_items.php?action=get_borrowed');
    const params = new URLSearchParams();
    // Only add filters if they have actual values
    if (categoryFilter && categoryFilter.trim() !== '') params.append('category', categoryFilter);
    // Keep param name as borrower_id for backwards compatibility with API which applies LIKE on borrower_name
    if (borrowerNameFilter && borrowerNameFilter.trim() !== '') params.append('borrower_id', borrowerNameFilter);
    if (params.toString()) url += '&' + params.toString();
    
    showLoading();
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 10000); // 10s timeout
    
    fetch(url, { signal: controller.signal })
        .then(resp => {
            if (!resp.ok) throw new Error('HTTP ' + resp.status);
            return resp.json();
        })
        .then(data => {
            if (data && data.success) {
                let items = Array.isArray(data.data) ? data.data : [];
                // Apply client-side filters to ensure UX even if backend schema lacks columns
                if (categoryFilter && categoryFilter.trim() !== '') {
                    items = items.filter(it => (String(it.category || '') === categoryFilter));
                }
                if (borrowerNameFilter && borrowerNameFilter.trim() !== '') {
                    const q = borrowerNameFilter.toLowerCase();
                    items = items.filter(it => String(it.borrower_name || '').toLowerCase().includes(q));
                }
                displayBorrowedItems(items);
            } else {
                const msg = data && (data.message || data.error) ? (data.message || data.error) : 'Unknown server error';
                showError('Failed to load borrowed items: ' + msg);
            }
        })
        .catch(error => {
            if (error.name === 'AbortError') {
                showError('Loading borrowed items timed out. Please try again.');
            } else {
                console.error('Error loading borrowed items:', error);
                showError('Error loading borrowed items');
            }
        })
        .finally(() => {
            clearTimeout(timeout);
            hideLoading();
        });
}

// (Removed duplicate window.showReturnModal; using the later named showReturnModal instead)

// Unified processItemReturn function (uses borrowed_items API)
function processItemReturn() {
    const transactionId = document.getElementById('returnTransactionId').value;
    const quantity = document.getElementById('returnQuantity').value;
    const condition = document.getElementById('returnCondition').value;
    const notes = document.getElementById('returnNotes').value;
    
    if (!quantity || quantity <= 0) {
        showError('Please enter a valid quantity');
        return;
    }
    
    const returnData = {
        transaction_id: transactionId,
        return_quantity: parseInt(quantity),
        condition: condition,
        notes: notes
    };
    
    showLoading();
    
    fetch(getApiUrl('borrowed_items.php?action=return_item'), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(returnData)
    })
    .then(async (response) => {
        const text = await response.text();
        try { return JSON.parse(text); }
        catch (e) { console.error('Return API (legacy) raw response:', text); throw e; }
    })
    .then(data => {
        hideLoading();
        if (data.success) {
            showSuccess(`Successfully returned ${data.returned_quantity} ${data.item_name}`);
            
            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('returnModal'));
            modal.hide();
            
            // Reload borrowed items
            loadBorrowedItems();
            // Refresh logs panel so the new RETURN entry is visible immediately
            try { loadTextLogs(300); } catch (e) {}
            // Also refresh top statistics so 'Borrowed' count decreases (to 0 if fully returned)
            try { refreshStatistics(); } catch (e) {}
        } else {
            showError('Return failed: ' + data.message);
        }
    })
    .catch(error => {
        hideLoading();
        console.error('Error processing return:', error);
        showError('Error processing return');
    });
};

// Initialize when document is ready
document.addEventListener('DOMContentLoaded', function() {
    initializeReturnDate();
    
    // Handle tab switching without page reload
    const tabButtons = document.querySelectorAll('[data-bs-toggle="tab"]');
    tabButtons.forEach(button => {
        button.addEventListener('shown.bs.tab', function(e) {
            const targetTab = e.target.getAttribute('data-bs-target');
            
            // Load data for specific tabs
            if (targetTab === '#return') {
                loadBorrowedItems();
            } else if (targetTab === '#borrow') {
                loadInventoryItems();
            } else if (targetTab === '#logs') {
                loadTextLogs(300);
            }
        });
    });
    
    initializeInventorySystem();
    loadBorrowers();
    loadBorrowersForReturn();
    setupEventListeners();
    setDefaultReturnDate();
    
    // Load borrowers for return filter
    loadBorrowersForReturn();
    enableMultipleSelection();
    
    // Logs filter controls
    try {
        const lf = document.getElementById('logTypeFilter');
        const ls = document.getElementById('logSearchInput');
        const lo = document.getElementById('logOnlyMatches');
        if (lf && !lf.dataset.bound) { lf.addEventListener('change', applyLogFilters); lf.dataset.bound = '1'; }
        if (ls && !ls.dataset.bound) { ls.addEventListener('input', applyLogFilters); ls.dataset.bound = '1'; }
        if (lo && !lo.dataset.bound) { lo.addEventListener('change', applyLogFilters); lo.dataset.bound = '1'; }
    } catch (e) {}
});

// Set default return date to today
function setDefaultReturnDate() {
    const returnDateInput = document.getElementById('expected_return_date');
    if (returnDateInput) {
        const today = new Date();
        const formattedDate = today.toISOString().split('T')[0];
        returnDateInput.value = formattedDate;
    }
}

function setupEventListeners() {
    // Borrower selection change
    const borrowerSelect = document.getElementById('borrowerSelect');
    if (borrowerSelect) {
        borrowerSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (this.value && selectedOption.dataset.isGuest !== '1') {
                showPinValidation();
            } else {
                hidePinValidation();
                if (this.value && selectedOption.dataset.isGuest === '1') {
                    const borrower = {
                        name: selectedOption.textContent.split(' (')[0],
                        rank_position: selectedOption.dataset.rank || 'N/A',
                        unit: selectedOption.dataset.unit || 'N/A',
                        contact_number: selectedOption.dataset.contact || 'N/A'
                    };
                    updateBorrowerInfo(borrower);
                } else {
                    document.getElementById('borrowerInfo').innerHTML = '';
                }
            }
        });
    }

    // PIN validation
    const borrowerPin = document.getElementById('borrowerPin');
    if (borrowerPin) {
        borrowerPin.addEventListener('input', validateBorrowerPin);
    }

    // Add borrower button
    const addBorrowerBtn = document.getElementById('addBorrowerBtn');
    if (addBorrowerBtn) {
        addBorrowerBtn.addEventListener('click', showAddBorrowerModal);
    }
    
    // Guest borrower button
    const guestBorrowerBtn = document.getElementById('guestBorrowerBtn');
    if (guestBorrowerBtn) {
        guestBorrowerBtn.addEventListener('click', selectGuestBorrower);
    }
    
    // Save borrower button
    const saveBorrowerBtn = document.getElementById('saveBorrowerBtn');
    if (saveBorrowerBtn) {
        saveBorrowerBtn.addEventListener('click', saveBorrower);
    }
    
    // Load borrowed items button
    const loadBorrowedBtn = document.getElementById('loadBorrowedBtn');
    if (loadBorrowedBtn) {
        loadBorrowedBtn.addEventListener('click', loadBorrowedItems);
    }
    
    // Return category filter change
    const returnCategoryFilter = document.getElementById('returnCategoryFilter');
    if (returnCategoryFilter) {
        returnCategoryFilter.addEventListener('change', function() {
            // Auto-load when filter changes if items are already loaded
            const container = document.getElementById('borrowedItemsList');
            if (container && container.innerHTML.trim() !== '') {
                loadBorrowedItems();
            }
        });
    }
    
    // Return borrower filter change
    const returnBorrowerFilter = document.getElementById('returnBorrowerFilter');
    if (returnBorrowerFilter) {
        returnBorrowerFilter.addEventListener('change', function() {
            // Auto-load when filter changes if items are already loaded
            const container = document.getElementById('borrowedItemsList');
            if (container && container.innerHTML.trim() !== '') {
                loadBorrowedItems();
            }
        });
    }
    
    // Supply functionality event listeners
    const loadSupplyBtn = document.getElementById('loadSupplyBtn') || document.getElementById('loadSupplyItemsBtn');
    if (loadSupplyBtn) {
        // If there's no inline onclick already, bind the handler here
        const hasInline = typeof loadSupplyBtn.onclick === 'function' || !!loadSupplyBtn.getAttribute('onclick');
        if (!hasInline && !loadSupplyBtn.dataset.bound) {
            loadSupplyBtn.addEventListener('click', loadSupplyItems);
            loadSupplyBtn.dataset.bound = '1';
        }
    }
    
    const supplyCategory = document.getElementById('supplyCategory');
    if (supplyCategory) {
        supplyCategory.addEventListener('change', function() {
            document.getElementById('supplyItemsList').innerHTML = '';
            document.getElementById('processResupplyBtn').style.display = 'none';
        });
    }
    
    const processResupplyBtn = document.getElementById('processResupplyBtn');
    if (processResupplyBtn) {
        processResupplyBtn.addEventListener('click', processResupply);
    }
    
    const addSupplyForm = document.getElementById('addSupplyForm');
    if (addSupplyForm) {
        addSupplyForm.addEventListener('submit', addSupplyItem);
    }
    const supplySearchBtn = document.getElementById('supplySearchBtn');
    if (supplySearchBtn && !supplySearchBtn.dataset.bound) {
        supplySearchBtn.addEventListener('click', searchSupplyItems);
        supplySearchBtn.dataset.bound = '1';
    }
    const supplySearchInput = document.getElementById('supplySearchInput');
    if (supplySearchInput && !supplySearchInput.dataset.bound) {
        supplySearchInput.addEventListener('keypress', function(e){ if (e.key === 'Enter') searchSupplyItems(); });
        supplySearchInput.dataset.bound = '1';
    }
}

// Borrower Management Functions
function loadBorrowers() {
    fetch(getApiUrl('borrowers.php?action=get_all'))
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                populateBorrowerSelect(data.borrowers);
            } else {
                console.error('Failed to load borrowers:', data.message);
            }
        })
        .catch(error => {
            console.error('Error loading borrowers:', error);
        });
}

function populateBorrowerSelect(borrowers) {
    const borrowerSelect = document.getElementById('borrowerSelect');
    if (!borrowerSelect) return;
    
    // Clear existing options except the first one
    borrowerSelect.innerHTML = '<option value="">Select Borrower</option>';
    
    // Add borrowers to select
    borrowers.forEach(borrower => {
        const option = document.createElement('option');
        option.value = borrower.id;
        option.textContent = `${borrower.name} (${borrower.rank_position || 'N/A'})`;
        option.dataset.pin = borrower.pin;
        option.dataset.rank = borrower.rank_position || '';
        option.dataset.unit = borrower.unit || '';
        option.dataset.contact = borrower.contact_number || '';
        option.dataset.isGuest = borrower.is_guest;
        borrowerSelect.appendChild(option);
    });
}

function showAddBorrowerModal() {
    const modal = new bootstrap.Modal(document.getElementById('addBorrowerModal'));
    modal.show();
}

function selectGuestBorrower() {
    fetch(getApiUrl('borrowers.php?action=get_guest'))
        .then(response => response.json())
        .then(data => {
            if (data.success && data.guest) {
                const borrowerSelect = document.getElementById('borrowerSelect');
                borrowerSelect.value = data.guest.id;
                updateBorrowerInfo(data.guest);
                hidePinValidation();
            } else {
                showError('Guest borrower not found');
            }
        })
        .catch(error => {
            console.error('Error selecting guest borrower:', error);
            showError('Error selecting guest borrower');
        });
}

function updateBorrowerInfo(borrower) {
    const infoDiv = document.getElementById('borrowerInfo');
    if (!infoDiv) return;
    
    infoDiv.innerHTML = `
        <div class="alert alert-info">
            <strong>Selected Borrower:</strong><br>
            <strong>Name:</strong> ${borrower.name}<br>
            <strong>Rank/Position:</strong> ${borrower.rank_position || 'N/A'}<br>
            <strong>Unit:</strong> ${borrower.unit || 'N/A'}<br>
            <strong>Contact:</strong> ${borrower.contact_number || 'N/A'}
        </div>
    `;
}

function showPinValidation() {
    // PIN validation disabled per requirement
    const pinRow = document.getElementById('pinValidationRow');
    if (pinRow) {
        pinRow.style.display = 'none';
    }
    const pinInput = document.getElementById('borrowerPin');
    if (pinInput) {
        pinInput.required = false;
        pinInput.value = '';
        pinInput.classList.remove('is-valid', 'is-invalid');
    }
}

function hidePinValidation() {
    const pinRow = document.getElementById('pinValidationRow');
    if (pinRow) {
        pinRow.style.display = 'none';
        document.getElementById('borrowerPin').required = false;
        document.getElementById('borrowerPin').value = '';
    }
}

function validateBorrowerPin() {
    // PIN validation disabled; immediately consider valid and update info
    const borrowerSelect = document.getElementById('borrowerSelect');
    const selectedOption = borrowerSelect && borrowerSelect.options[borrowerSelect.selectedIndex];
    if (!selectedOption) return;
    const borrower = {
        name: selectedOption.textContent.split(' (')[0],
        rank_position: selectedOption.dataset.rank || 'N/A',
        unit: selectedOption.dataset.unit || 'N/A',
        contact_number: selectedOption.dataset.contact || 'N/A'
    };
    updateBorrowerInfo(borrower);
    hidePinValidation();
}

function saveBorrower() {
    const form = document.getElementById('addBorrowerForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    const params = new URLSearchParams();
    params.append('action', 'add_borrower');
    params.append('name', document.getElementById('newBorrowerName').value);
    params.append('pin', document.getElementById('newBorrowerPin').value);
    params.append('rank_position', document.getElementById('newBorrowerRank').value);
    params.append('unit', document.getElementById('newBorrowerUnit').value);
    params.append('contact_number', document.getElementById('newBorrowerContact').value);
    
    fetch(getApiUrl('borrowers.php?action=add_borrower'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: params.toString()
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccess('Borrower added successfully!');
            form.reset();
            const mdl = bootstrap.Modal.getInstance(document.getElementById('addBorrowerModal'));
            if (mdl) mdl.hide();
            loadBorrowers(); // Reload borrowers list
        } else {
            showError('Error adding borrower: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error adding borrower:', error);
        showError('Error adding borrower');
    });
}

// Cart functionality for multiple borrow
let borrowCart = [];

function addToCart() {
    const codeInput = document.getElementById('selectedItemCode');
    const qtyInput = document.getElementById('itemQuantity');
    const nameSpan = document.getElementById('selectedItemName');
    
    if (!codeInput || !qtyInput) {
        alert('Item selection controls not found');
        return;
    }
    
    const code = (codeInput.value || '').trim();
    // Sanitize and clamp quantity
    const maxAvailText = document.getElementById('selectedItemQuantity')?.textContent || '';
    const maxAvail = parseInt(maxAvailText, 10);
    const quantity = sanitizeQuantity(qtyInput.value, Number.isFinite(maxAvail) ? maxAvail : undefined);
    const name = (nameSpan && nameSpan.textContent ? nameSpan.textContent : code) || code;
    
    if (!code) {
        showError('Please select an item first');
        return;
    }
    if (!Number.isInteger(quantity) || quantity <= 0) {
        showError('Please enter a valid quantity');
        return;
    }
    
    // Check if item already in cart
    const existingIndex = borrowCart.findIndex(item => item.code === code);
    if (existingIndex >= 0) {
        borrowCart[existingIndex].quantity += quantity;
    } else {
        borrowCart.push({ code, name, quantity });
    }
    
    // Clear selection UI and disable add button until next selection
    codeInput.value = '';
    qtyInput.value = '1';
    const addBtn = document.getElementById('addToCartBtn');
    if (addBtn) addBtn.disabled = true;
    const selectedDisplay = document.getElementById('selectedItemDisplay');
    if (selectedDisplay) selectedDisplay.style.display = 'none';
    
    updateCartDisplay();
    updateSubmitButton();
}

function removeFromCart(index) {
    borrowCart.splice(index, 1);
    updateCartDisplay();
    updateSubmitButton();
}

function clearCart() {
    borrowCart = [];
    updateCartDisplay();
    updateSubmitButton();
}

function updateCartDisplay() {
    const cartItems = document.getElementById('cartItems');
    const cartCount = document.getElementById('cartItemCount');
    const cartSection = document.getElementById('cartSection');
    
    if (!cartItems || !cartCount) return;
    
    if (borrowCart.length === 0) {
        cartItems.innerHTML = '<div class="text-muted text-center py-3">No items in cart</div>';
        cartCount.textContent = '0';
        if (cartSection) cartSection.style.display = 'none';
        return;
    }
    
    cartCount.textContent = borrowCart.length;
    if (cartSection) cartSection.style.display = '';
    
    cartItems.innerHTML = borrowCart.map((item, index) => `
        <div class="d-flex justify-content-between align-items-center border-bottom py-2">
            <div>
                <strong>${item.name}</strong><br>
                <small class="text-muted">Code: ${item.code} | Qty: ${item.quantity}</small>
            </div>
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeFromCart(${index})">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    `).join('');
}

function updateSubmitButton() {
    const submitBtn = document.getElementById('processBorrowBtn');
    if (submitBtn) {
        submitBtn.disabled = borrowCart.length === 0;
    }
}

function showMultipleTransactionResult(data) {
    let resultHtml = `
        <div class="alert alert-success">
            <h5><i class="fas fa-check-circle me-2"></i>Multiple Borrow Successful</h5>
            <p>Successfully processed ${data.ids.length} borrow transaction(s):</p>
            <ul>
    `;
    
    data.items.forEach((item, index) => {
        resultHtml += `<li><strong>${item.name}</strong> (Code: ${item.code}) - Qty: ${item.quantity}</li>`;
    });
    
    resultHtml += `
            </ul>
            <p class="mb-0"><small>Transaction IDs: ${data.ids.join(', ')}</small></p>
        </div>
    `;
    
    // Show in a modal or alert
    const resultModal = document.createElement('div');
    resultModal.innerHTML = `
        <div class="modal fade" id="transactionResultModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Transaction Result</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        ${resultHtml}
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(resultModal);
    const modal = new bootstrap.Modal(document.getElementById('transactionResultModal'));
    modal.show();
    
    // Remove modal after hiding
    document.getElementById('transactionResultModal').addEventListener('hidden.bs.modal', function() {
        document.body.removeChild(resultModal);
    });
}

function initializeInventorySystem() {
    // Idempotent guard to prevent double-binding
    if (window._inventoryInitialized) return;
    window._inventoryInitialized = true;
    // Initialize form handlers
    initializeBorrowForm();
    initializeReturnForm();
    initializeSupplyForm();
    initializeSearchForm();
    
    // Initialize QR scanner simulation
    initializeQRScanner();
    
    // Initialize tab switching
    initializeTabSwitching();
    
    // Initialize cart functionality
    initializeCartSystem();
    
    // Initialize expected return date
    initializeReturnDate();
}

function initializeReturnDate() {
    const returnDateField = document.getElementById('returnDate');
    if (returnDateField) {
        const today = new Date();
        const todayString = today.toISOString().split('T')[0]; // Format: YYYY-MM-DD
        returnDateField.value = todayString;
    }
}

function initializeCartSystem() {
    // Add event listener for Add to Cart button
    const addToCartBtn = document.getElementById('addToCartBtn');
    if (addToCartBtn) {
        if (!addToCartBtn.dataset.bound) {
            addToCartBtn.addEventListener('click', addToCart);
            addToCartBtn.dataset.bound = '1';
        }
    }
    
    // Add event listener for Clear Cart button
    const clearCartBtn = document.getElementById('clearCartBtn');
    if (clearCartBtn) {
        if (!clearCartBtn.dataset.bound) {
            clearCartBtn.addEventListener('click', clearCart);
            clearCartBtn.dataset.bound = '1';
        }
    }
    
    // Initialize cart display
    updateCartDisplay();
    updateSubmitButton();
}

// Borrow Form Handler
function initializeBorrowForm() {
    const borrowForm = document.getElementById('borrowForm');
    if (!borrowForm) return;
    if (borrowForm.dataset.bound === '1') return; // prevent duplicate submit bindings
    
    borrowForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Validate cart has items
        if (borrowCart.length === 0) {
            alert('Please add items to cart before processing');
            return;
        }
        
        // Validate borrower selection
        const borrowerSelect = document.getElementById('borrowerSelect');
        if (!borrowerSelect.value) {
            alert('Please select a borrower');
            return;
        }
        
        // Validate return date
        const returnDate = document.getElementById('returnDate');
        if (!returnDate.value) {
            alert('Please set expected return date');
            return;
        }
        
        // Prepare data for multiple items
        const borrowData = {
            action: 'multiple_borrow',
            borrower_id: borrowerSelect.value,
            borrower_name: borrowerSelect.options[borrowerSelect.selectedIndex].text.split(' (')[0],
            purpose: document.getElementById('purpose').value,
            expected_return_date: returnDate.value,
            items: borrowCart
        };
        
        showLoading('Processing multiple borrow request...');
        
        fetch(getApiUrl('inventory_handler.php'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(borrowData)
        })
        .then(response => response.json())
        .then(data => {
            hideLoading();
            
            if (data.success) {
                showSuccess(data.message);
                
                // Clear cart and reset form
                borrowCart = [];
                updateCartDisplay();
                updateSubmitButton();
                document.getElementById('borrowForm').reset();
                initializeReturnDate(); // Reset return date to today
                
                // Preserve the return date field after form reset
                setTimeout(() => {
                    initializeReturnDate();
                }, 100);           
                // Show transaction details
                if (data.transaction_ids && data.transaction_ids.length > 0) {
                    showMultipleTransactionResult({
                        ids: data.transaction_ids,
                        type: 'Multiple Borrow',
                        items: data.processed_items
                    });
                }
                
                // Refresh statistics
                refreshStatistics();
            } else {
                showError(data.message);
            }
        })
        .catch(error => {
            hideLoading();
            showError('Network error occurred');
            console.error('Error:', error);
        });
    });
    borrowForm.dataset.bound = '1';
    
    // Add item search functionality
    addItemSearchToForm(borrowForm);
    
    // Reset return date when form is reset
    borrowForm.addEventListener('reset', function() {
        setTimeout(initializeReturnDate, 100); // Delay to ensure form reset completes
    });
}

// Return Form Handler
function initializeReturnForm() {
    const returnForm = document.getElementById('returnForm');
    if (!returnForm) return;
    
    returnForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const transactionId = returnForm.querySelector('[name="transaction_id"]').value.trim();
        
        if (!transactionId) {
            showError('Transaction ID is required');
            return;
        }
        
        // First search for the transaction
        searchTransactionForReturn(transactionId);
    });
}

// Supply Form Handler
function initializeSupplyForm() {
    const supplyForm = document.getElementById('supplyForm');
    if (!supplyForm) return;
    
    supplyForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(supplyForm);
        formData.append('action', 'supply');
        
        showLoading('Adding to inventory...');
        
        fetch(getApiUrl('inventory_handler.php'), {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            hideLoading();
            
            if (data.success) {
                showSuccess(data.message);
                supplyForm.reset();
                
                // Show transaction details
                if (data.transaction_id) {
                    showTransactionResult({
                        id: data.transaction_id,
                        type: 'Supply'
                    });
                }
                
                // Refresh statistics
                refreshStatistics();
            } else {
                showError(data.message);
            }
        })
        .catch(error => {
            hideLoading();
            showError('Network error occurred');
            console.error('Error:', error);
        });
    });
}

// Search Form Handler
function initializeSearchForm() {
    const searchForm = document.getElementById('searchForm');
    if (!searchForm) return;
    
    searchForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const searchTerm = searchForm.querySelector('[name="search_term"]').value.trim();
        
        if (!searchTerm) {
            showError('Search term is required');
            return;
        }
        
        searchTransactions(searchTerm);
    });
}

// QR Scanner Simulation
function initializeQRScanner() {
    // Simulate QR scanner button
    const scanButtons = document.querySelectorAll('.qr-scan-btn');
    
    scanButtons.forEach(button => {
        button.addEventListener('click', function() {
            simulateQRScan(this);
        });
    });
}

// Tab Switching
function initializeTabSwitching() {
    const tabButtons = document.querySelectorAll('[data-bs-toggle="tab"]');
    
    tabButtons.forEach(button => {
        button.addEventListener('shown.bs.tab', function(e) {
            const targetTab = e.target.getAttribute('data-bs-target');
            
            // Refresh data when switching to logs tab
            if (targetTab === '#logs') {
                refreshTransactionLogs();
            }
        });
    });
}

// Helper Functions

function addItemSearchToForm(form) {
    const itemSelectorBtn = document.getElementById('itemSelectorBtn');
    if (!itemSelectorBtn) return;
    
    itemSelectorBtn.addEventListener('click', function() {
        // Open the browse items modal and initialize its controls
        const modalEl = document.getElementById('itemSelectionModal');
        if (modalEl) {
            const modal = new bootstrap.Modal(modalEl);
            modal.show();
            initializeItemSelectionModal();
            // Load all items by default
            loadAllItems();
        } else {
            alert('Item selection modal not found.');
        }
    });
}

// Item Selection Modal Functions
function showItemSelectionModal() {
    const modal = new bootstrap.Modal(document.getElementById('itemSelectionModal'));
    modal.show();
    
    // Load items when modal opens
    loadAllItems();
    
    // Initialize modal event listeners
    initializeItemSelectionModal();

    // Ensure any backdrop is fully removed on close to prevent page from being unclickable
    const modalEl = document.getElementById('itemSelectionModal');
    if (modalEl && !modalEl.dataset.cleanupBound) {
        modalEl.addEventListener('hidden.bs.modal', function() {
            cleanupBootstrapModals();
        });
        modalEl.dataset.cleanupBound = '1';
    }
}

// (Removed duplicate initializeItemSelectionModal; keeping the earlier definition near modal opening)

function getCategoryFromTab(target) {
    const categoryMap = {
        '#consumable-items': 'Consumable',
        '#non-consumable-items': 'Non-consumable',
        '#semi-expendable-items': 'Semi-expendable',
        '#capital-items': 'Capital',
        '#disposable-items': 'Disposable'
    };
    return categoryMap[target] || null;
}

function loadAllItems() {
    showItemsLoading();
    
    const apiUrl = getApiUrl('get_items.php?action=get_all');
    fetch(apiUrl)
        .then(async (response) => {
            const text = await response.text();
            try { return JSON.parse(text); }
            catch (e) { console.error('Items API (get_all) raw response:', text); throw e; }
        })
        .then(data => {
            hideItemsLoading();
            if (data.success) {
                displayItems(data.items, 'itemsGrid');
            } else {
                showNoItemsMessage();
            }
        })
        .catch(error => {
            hideItemsLoading();
            console.error('Error loading items:', error);
            showError('Failed to load items');
        });
}

function loadItemsByCategory(category) {
    showItemsLoading();
    
    const apiUrl = getApiUrl(`get_items.php?action=get_by_category&category=${encodeURIComponent(category)}`);
    fetch(apiUrl)
        .then(async (response) => {
            const text = await response.text();
            try { return JSON.parse(text); }
            catch (e) { console.error('Items API (get_by_category) raw response:', text); throw e; }
        })
        .then(data => {
            hideItemsLoading();
            if (data.success) {
                const gridId = getCategoryGridId(category);
                displayItemsArray(data.items, gridId);
            } else {
                showNoItemsMessage();
            }
        })
        .catch(error => {
            hideItemsLoading();
            console.error('Error loading items:', error);
            showError('Failed to load items');
        });
}

function getCategoryGridId(category) {
    const gridMap = {
        'Consumable': 'consumableItemsGrid',
        'Non-consumable': 'nonConsumableItemsGrid',
        'Semi-expendable': 'semiExpendableItemsGrid',
        'Capital': 'capitalItemsGrid',
        'Disposable': 'disposableItemsGrid'
    };
    return gridMap[category] || 'itemsGrid';
}

function performItemSearch() {
    const searchTerm = document.getElementById('itemSearchInput').value.trim();
    const category = document.getElementById('categoryFilter').value;
    
    if (!searchTerm) {
        showError('Please enter a search term');
        return;
    }
    
    showItemsLoading();
    
    let url = `get_items.php?action=search&search=${encodeURIComponent(searchTerm)}`;
    if (category) {
        url += `&category=${encodeURIComponent(category)}`;
    }
    
    const apiUrl = getApiUrl(url);
    fetch(apiUrl)
        .then(async (response) => {
            const text = await response.text();
            try { return JSON.parse(text); }
            catch (e) { console.error('Items API (search) raw response:', text); throw e; }
        })
        .then(data => {
            hideItemsLoading();
            if (data.success) {
                // Normalize items: accept array or grouped object
                const items = Array.isArray(data.items) ? data.items : flattenItems(data.items || {});
                displayItemsArray(items, 'itemsGrid');
                // Show "All Items" tab without triggering our click handler that reloads all items
                try {
                    const allTabBtn = document.getElementById('all-tab');
                    if (allTabBtn && window.bootstrap && bootstrap.Tab) {
                        bootstrap.Tab.getOrCreateInstance(allTabBtn).show();
                    }
                } catch (e) { /* ignore */ }
            } else {
                showNoItemsMessage();
            }
        })
        .catch(error => {
            hideItemsLoading();
            console.error('Error searching items:', error);
            showError('Failed to search items');
        });
}

function displayItems(itemsByCategory, gridId) {
    const grid = document.getElementById(gridId);
    if (!grid) return;
    
    grid.innerHTML = '';
    // Use CSS Grid for natural, appealing fit
    grid.classList.remove('row','g-2','g-3','g-sm-3','row-cols-1','row-cols-2','row-cols-3','row-cols-4','row-cols-5','row-cols-6','row-cols-sm-2','row-cols-sm-3','row-cols-sm-4','row-cols-md-3','row-cols-md-4','row-cols-lg-5','row-cols-lg-6');
    grid.classList.add('items-css-grid');
    
    if (Object.keys(itemsByCategory).length === 0) {
        showNoItemsMessage();
        return;
    }
    
    // Render all items without category headers (single compact grid)
    Object.keys(itemsByCategory).forEach(category => {
        const items = itemsByCategory[category] || [];
        items.forEach(item => {
            const itemCard = createItemCard(item);
            grid.appendChild(itemCard);
        });
    });
    // After rendering, enable multiple selection if available
    try { enableMultipleSelection(); } catch (e) {}
}

function displayItemsArray(items, gridId) {
    const grid = document.getElementById(gridId);
    if (!grid) return;
    
    grid.innerHTML = '';
    // Use CSS Grid for natural, appealing fit
    grid.classList.remove('row','g-2','g-sm-3','row-cols-1','row-cols-2','row-cols-3','row-cols-4','row-cols-5','row-cols-6','row-cols-sm-2','row-cols-sm-3','row-cols-sm-4','row-cols-md-3','row-cols-md-4','row-cols-lg-5','row-cols-lg-6');
    grid.classList.add('items-css-grid');
    
    if (items.length === 0) {
        showNoItemsMessage();
        return;
    }
    
    items.forEach(item => {
        const itemCard = createItemCard(item);
        grid.appendChild(itemCard);
    });
    // After rendering, enable multiple selection if available
    try { enableMultipleSelection(); } catch (e) {}
}

// (Removed older createItemCard with "Select Item" button; keeping later version that supports direct click borrowing)

function getCategoryIcon(category) {
    const iconMap = {
        'Consumable': 'fas fa-battery-half',
        'Non-consumable': 'fas fa-tools',
        'Semi-expendable': 'fas fa-usb',
        'Capital': 'fas fa-desktop',
        'Disposable': 'fas fa-trash'
    };
    return iconMap[category] || 'fas fa-box';
}

function selectItem(item) {
    // Update hidden input and display
    let codeFallback = item.item_code || item.itemCode || item.code || item.id || item.item_name || '';
    if (!codeFallback) {
        // As a last resort, generate a temporary code to allow carting
        codeFallback = 'ITEM-' + Date.now();
    }
    document.getElementById('selectedItemCode').value = codeFallback;
    document.getElementById('selectedItemName').textContent = item.item_name || codeFallback;
    document.getElementById('selectedItemCodeDisplay').textContent = codeFallback;
    document.getElementById('selectedItemQuantity').textContent = item.available_quantity;
    const selectedDisplay = document.getElementById('selectedItemDisplay');
    if (selectedDisplay) selectedDisplay.style.display = '';
    const addBtn = document.getElementById('addToCartBtn');
    if (addBtn) {
        // Always enable after a selection to allow adding to cart
        // Binding is handled once in initializeCartSystem() to avoid duplicates
        addBtn.disabled = false;
    }

    // Always show and require a return date (default to today)
    const returnDateField = document.getElementById('returnDate');
    if (returnDateField) {
        const returnDateGroup = returnDateField.closest('.mb-3');
        if (returnDateGroup) returnDateGroup.style.display = 'block';
        returnDateField.required = true;
        const today = new Date().toISOString().split('T')[0];
        if (!returnDateField.value) returnDateField.value = today;
        returnDateField.min = today;
    }

    // Close item selection modal and clean up any lingering overlays
    const modalEl = document.getElementById('itemSelectionModal');
    if (modalEl) {
        const modalInst = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
        try { modalInst.hide(); } catch (e) { /* ignore */ }
    }
    cleanupBootstrapModals();

    // Show selected item display
    document.getElementById('selectedItemDisplay').style.display = 'block';

    // Update button text
    document.getElementById('itemSelectorBtn').innerHTML = `
        <i class="fas fa-check me-2"></i>Item Selected: ${item.item_name}
    `;

    showSuccess(`Selected: ${item.item_name}`);
}

function showItemsLoading() {
    const itemsLoading = document.getElementById('itemsLoading');
    if (itemsLoading) itemsLoading.style.display = 'block';
    const noItemsMsg = document.getElementById('noItemsMessage');
    if (noItemsMsg) noItemsMsg.style.display = 'none';
    
    // Hide all grids
    const grids = ['itemsGrid', 'consumableItemsGrid', 'nonConsumableItemsGrid', 'semiExpendableItemsGrid', 'capitalItemsGrid', 'disposableItemsGrid'];
    grids.forEach(gridId => {
        const grid = document.getElementById(gridId);
        if (grid) grid.innerHTML = '';
    });
}

function hideItemsLoading() {
    const itemsLoading = document.getElementById('itemsLoading');
    if (itemsLoading) itemsLoading.style.display = 'none';
}

function showNoItemsMessage() {
    const noItemsMsg = document.getElementById('noItemsMessage');
    if (noItemsMsg) noItemsMsg.style.display = 'block';
    const itemsLoading = document.getElementById('itemsLoading');
    if (itemsLoading) itemsLoading.style.display = 'none';
}

function searchTransactionForReturn(transactionId) {
    showLoading('Searching transaction...');
    
    const formData = new FormData();
    formData.append('action', 'search_transaction');
    formData.append('search_term', transactionId);
    
    fetch(getApiUrl('inventory_handler.php'), {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        
        if (data.success && data.transactions.length > 0) {
            const transaction = data.transactions[0];
            showReturnConfirmation(transaction);
        } else {
            showError(data.message || 'Transaction not found');
        }
    })
    .catch(error => {
        hideLoading();
        showError('Network error occurred');
        console.error('Error:', error);
    });
}

function showReturnConfirmation(transaction) {
    const modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.innerHTML = `
        <div class="modal-dialog">
            <div class="modal-content bg-dark text-light">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Return</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <strong>Transaction ID:</strong> ${transaction.transaction_id}<br>
                        <strong>Item:</strong> ${transaction.item_name} (${transaction.item_code})<br>
                        <strong>Quantity:</strong> ${transaction.quantity}<br>
                        <strong>Borrower:</strong> ${transaction.borrower_name}<br>
                        <strong>Borrowed Date:</strong> ${new Date(transaction.created_at).toLocaleDateString()}
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Return Condition</label>
                        <select class="form-select" id="returnCondition">
                            <option value="good">Good</option>
                            <option value="damaged">Damaged</option>
                            <option value="lost">Lost</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" id="returnNotes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="processReturn('${transaction.transaction_id}')">Confirm Return</button>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
    
    modal.addEventListener('hidden.bs.modal', function() {
        document.body.removeChild(modal);
    });
}

function processReturn(transactionId) {
    const condition = document.getElementById('returnCondition').value;
    const notes = document.getElementById('returnNotes').value;
    
    showLoading('Processing return...');
    
    const formData = new FormData();
    formData.append('action', 'return');
    formData.append('transaction_id', transactionId);
    formData.append('return_condition', condition);
    formData.append('notes', notes);
    
    fetch(getApiUrl('inventory_handler.php'), {
        method: 'POST',
        body: formData
    })
    .then(async (response) => {
        const text = await response.text();
        try { return JSON.parse(text); }
        catch (e) {
            console.error('Return (inventory_handler) raw response:', text);
            throw new Error('Invalid JSON from inventory_handler');
        }
    })
    .then(data => {
        hideLoading();
        
        if (data.success) {
            showSuccess(data.message);
            
            // Close modal
            const modal = bootstrap.Modal.getInstance(document.querySelector('.modal'));
            if (modal) modal.hide();
            
            // Reset return form
            const returnForm = document.getElementById('returnForm');
            if (returnForm) returnForm.reset();
            
            // Refresh statistics and logs
            refreshStatistics();
            refreshTransactionLogs();
        } else {
            showError(data.message);
        }
    })
    .catch(error => {
        hideLoading();
        showError('Network error occurred');
        console.error('Error:', error);
    });
}

function simulateQRScan(button) {
    // Simulate QR code scanning
    const sampleQRData = [
        'TXN20241201001',
        'TXN20241201002',
        'TXN20241201003'
    ];
    
    const randomQR = sampleQRData[Math.floor(Math.random() * sampleQRData.length)];
    
    // Find the nearest input field
    const input = button.closest('.form-group, .mb-3').querySelector('input');
    if (input) {
        input.value = randomQR;
        input.focus();
        showSuccess('QR Code scanned: ' + randomQR);
    }
}

function showTransactionResult(transaction) {
    const alert = document.createElement('div');
    alert.className = 'alert alert-success alert-dismissible fade show mt-3';
    alert.innerHTML = `
        <strong>Success!</strong> ${transaction.type} transaction completed.<br>
        <strong>Transaction ID:</strong> ${transaction.id}
        ${transaction.qr_data ? '<br><small>QR Code data generated for tracking</small>' : ''}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    const container = document.querySelector('.tab-content .tab-pane.active');
    if (container) {
        container.insertBefore(alert, container.firstChild);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (alert.parentNode) {
                alert.remove();
            }
        }, 5000);
    }
}

function refreshStatistics() {
    // This would typically fetch updated statistics from the server
    // For now, we'll just reload the page to get fresh data
    setTimeout(() => {
        window.location.reload();
    }, 2000);
}

function refreshTransactionLogs() {
    // Disabled automatic reload to prevent continuous reloading issue
    // Transaction logs are already loaded server-side in dashboard.php
    console.log('Transaction logs refreshed (server-side)');
}

function showItemSearchModal() {
    // Simplified item search modal
    alert('Item search functionality would be implemented here.\nFor now, please enter the item code manually.');
}

function searchTransactions(searchTerm) {
    showLoading('Searching transactions...');
    
    const formData = new FormData();
    formData.append('action', 'search_transaction');
    formData.append('search_term', searchTerm);
    
    fetch(getApiUrl('inventory_handler.php'), {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        
        if (data.success) {
            displaySearchResults(data.transactions);
        } else {
            showError(data.message);
        }
    })
    .catch(error => {
        hideLoading();
        showError('Network error occurred');
        console.error('Error:', error);
    });
}

function displaySearchResults(transactions) {
    // This would display search results in a modal or dedicated area
    console.log('Search results:', transactions);
    showSuccess(`Found ${transactions.length} transaction(s)`);
}

// Utility Functions

// Flatten grouped object {category:[items]} -> single array
function flattenItems(grouped) {
    const out = [];
    try {
        Object.keys(grouped || {}).forEach(k => {
            const arr = grouped[k];
            if (Array.isArray(arr)) out.push(...arr);
        });
    } catch (e) {}
    return out;
}

// Map category name to grid id in the item selection modal
// (Removed duplicate getCategoryGridId; using the earlier definition)

// Initialize browse modal (search + filters)
function initializeItemSelectionModal() {
    const searchBtn = document.getElementById('searchItemsBtn');
    const searchInput = document.getElementById('itemSearchInput');
    const categoryFilter = document.getElementById('categoryFilter');
    const categoryTabs = document.querySelectorAll('#categoryTabs button[data-bs-toggle="pill"]');
    
    if (searchBtn && !searchBtn.dataset.bound) {
        searchBtn.addEventListener('click', performItemSearch);
        searchBtn.dataset.bound = '1';
    }
    if (searchInput && !searchInput.dataset.bound) {
        searchInput.addEventListener('keypress', function(e){ if (e.key === 'Enter') performItemSearch(); });
        searchInput.dataset.bound = '1';
    }
    if (categoryFilter && !categoryFilter.dataset.bound) {
        categoryFilter.addEventListener('change', function(){
            const category = this.value;
            if (category) { loadItemsByCategory(category); } else { loadAllItems(); }
        });
        categoryFilter.dataset.bound = '1';
    }
    if (categoryTabs && categoryTabs.length) {
        categoryTabs.forEach(tab => {
            if (!tab.dataset.bound) {
                tab.addEventListener('click', function(){
                    const target = this.getAttribute('data-bs-target');
                    const map = { '#all-items': null, '#consumable-items': 'Consumable', '#non-consumable-items': 'Non-consumable', '#semi-expendable-items': 'Semi-expendable', '#capital-items': 'Capital', '#disposable-items': 'Disposable' };
                    const cat = map[target] || null;
                    if (cat) { loadItemsByCategory(cat); } else { loadAllItems(); }
                });
                tab.dataset.bound = '1';
            }
        });
    }
}

// Supply search
function searchSupplyItems() {
    const term = (document.getElementById('supplySearchInput')?.value || '').trim();
    const category = document.getElementById('supplyCategory')?.value || '';
    if (!term) { showError('Please enter a search term'); return; }
    showLoading('Searching supplies...');
    let url = `get_items.php?action=search&search=${encodeURIComponent(term)}`;
    if (category && category.toLowerCase() !== 'all') { url += `&category=${encodeURIComponent(category)}`; }
    fetch(getApiUrl(url))
        .then(async (r) => {
            const text = await r.text();
            try { return JSON.parse(text); } catch (e) { console.error('Supply search raw:', text); throw e; }
        })
        .then(d => {
            hideLoading();
            if (d.success) {
                const items = Array.isArray(d.items) ? d.items : flattenItems(d.items || {});
                displaySupplyItems(items);
            } else {
                showError(d.message || 'Search failed');
            }
        })
        .catch(e => { hideLoading(); console.error(e); showError('Network error'); });
}

// Text log loader (legacy variant retained for reference)
function loadTextLogsLegacy(tail = 200) {
    showLoading('Loading logs...');
    fetch('api/read_log.php?tail=' + encodeURIComponent(tail))
        .then(r => r.json())
        .then(d => {
            hideLoading();
            const el = document.getElementById('textLogsContent');
            if (el) { el.textContent = (d.content || (Array.isArray(d.lines) ? d.lines.join('\n') : '') || d.message || '').trim() || 'No logs.'; }
            // Update summary badges if present
            if (d.summary) {
                const s = d.summary;
                const set = (id, val) => { const e = document.getElementById(id); if (e) e.textContent = String(val); };
                set('logSummaryItems', s.items_count ?? '-');
                set('logSummaryTotal', s.total ?? '-');
                set('logSummaryAvailable', s.available ?? '-');
                set('logSummaryBorrowed', s.borrowed ?? '-');
            }
            if (!d.success) { showError(d.message || 'Failed to read logs'); }
        })
        .catch(e => { hideLoading(); console.error(e); showError('Failed to read logs'); });
}

function showLoading(message = 'Loading...') {
    // Remove existing loading
    hideLoading();
    
    const loading = document.createElement('div');
    loading.id = 'loadingOverlay';
    loading.className = 'position-fixed top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center';
    loading.style.backgroundColor = 'rgba(0,0,0,0.7)';
    loading.style.zIndex = '9999';
    loading.innerHTML = `
        <div class="text-center text-light">
            <div class="spinner-border text-success mb-3" role="status"></div>
            <div>${message}</div>
        </div>
    `;
    
    document.body.appendChild(loading);
}

function hideLoading() {
    const loading = document.getElementById('loadingOverlay');
    if (loading) {
        loading.remove();
    }
}

function showSuccess(message) {
    showToast(message, 'success');
}

function showError(message) {
    showToast(message, 'error');
}

function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `alert alert-${type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info'} alert-dismissible fade show position-fixed`;
    toast.style.top = '20px';
    toast.style.right = '20px';
    toast.style.zIndex = '10000';
    toast.style.minWidth = '300px';
    toast.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(toast);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (toast.parentNode) {
            toast.remove();
        }
    }, 5000);
}

// Return Management Functions

// Load borrowers for return filter
function loadBorrowersForReturn() {
    fetch(getApiUrl('borrowers.php?action=get_all'))
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const select = document.getElementById('returnBorrowerFilter');
                if (select) {
                    // Clear existing options except "All Borrowers"
                    select.innerHTML = '<option value="">All Borrowers</option>';
                    
                    data.borrowers.forEach(borrower => {
                        const option = document.createElement('option');
                        option.value = borrower.id;
                        option.textContent = `${borrower.rank_position} ${borrower.name} (${borrower.unit})`;
                        option.dataset.name = borrower.name || '';
                        select.appendChild(option);
                    });
                }
            }
        })
        .catch(error => {
            console.error('Error loading borrowers for return:', error);
        });
}

// Load borrowed items with filters
// Duplicate function removed - now defined globally at top of file

// Display borrowed items
function displayBorrowedItems(items) {
    const container = document.getElementById('borrowedItemsList');
    
    // Frontend safeguard: hide rows that are already returned or fully zeroed out
    try {
        items = (items || []).filter(it => {
            const status = (it.bi_status || '').toString().trim().toLowerCase();
            const ret = (it.bi_return_date || '').toString().trim();
            const qty = Number(it.quantity_borrowed ?? it.quantity ?? 0);
            const statusOk = (status === '' || (['returned','complete','completed'].indexOf(status) === -1));
            const retOk = (ret === '' || ret === '0000-00-00' || ret === '0000-00-00 00:00:00');
            const qtyOk = (!Number.isFinite(qty) || qty > 0);
            return statusOk && retOk && qtyOk;
        });
    } catch (e) {
        console.warn('Failed to apply frontend return filter:', e);
    }
    
    if (items.length === 0) {
        container.innerHTML = '<p class="text-muted">No borrowed items found with the selected filters.</p>';
        return;
    }
    
    // Build compact cards view (on top)
    let cards = '<div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3 mb-3">';
    items.forEach(item => {
        const officerName = item.duty_officer || 'Unknown';
        const borrowedDate = item.borrowed_date ? new Date(item.borrowed_date).toLocaleDateString() : '—';
        const expectedReturn = item.expected_return_date ? new Date(item.expected_return_date).toLocaleDateString() : '—';
        const isOverdue = item.expected_return_date ? (new Date(item.expected_return_date) < new Date()) : false;
        cards += `
        <div class="col">
          <div class="card h-100 shadow-sm">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <div class="fw-bold">${item.item_name}</div>
                  <div class="small text-muted">${item.category} • ${item.quantity_borrowed} ${item.item_unit}</div>
                </div>
                <i class="fas fa-question-circle text-muted ms-2" data-bs-toggle="tooltip" title="Duty Officer: ${officerName}"></i>
              </div>
              <div class="mt-2 small">
                <div><i class="fas fa-user me-1"></i>${item.rank_position || ''} ${item.borrower_name}</div>
                <div><i class="fas fa-calendar-alt me-1"></i>Borrowed: ${borrowedDate}</div>
                <div><i class="fas fa-clock me-1"></i>Due: ${expectedReturn}${isOverdue ? ' <i class="fas fa-exclamation-triangle text-warning" title="Overdue"></i>' : ''}</div>
              </div>
            </div>
            <div class="card-footer bg-transparent border-0">
              ${item.can_return === 'yes' ? `<button class="btn btn-sm btn-success w-100" onclick="showReturnModal(${item.transaction_id}, '${item.item_name}', ${item.quantity_borrowed}, '${item.item_unit}')"><i class=\"fas fa-undo me-1\"></i>Return</button>` : '<span class="badge bg-secondary">Non-returnable</span>'}
            </div>
          </div>
        </div>`;
    });
    cards += '</div>';
    
    // Build detailed table view (moved below)
    let html = '<div class="table-responsive"><table class="table table-striped">';
    html += '<thead><tr>';
    html += '<th>Transaction ID</th>';
    html += '<th>Borrower</th>';
    html += '<th>Item</th>';
    html += '<th>Category</th>';
    html += '<th>Quantity</th>';
    html += '<th>Borrowed Date</th>';
    html += '<th>Expected Return</th>';
    html += '<th>Purpose</th>';
    html += '<th>Action</th>';
    html += '</tr></thead><tbody>';
    
    items.forEach(item => {
        const expectedReturn = item.expected_return_date ? new Date(item.expected_return_date).toLocaleDateString() : '—';
        const isOverdue = item.expected_return_date ? (new Date(item.expected_return_date) < new Date()) : false;
        
        html += '<tr' + (isOverdue ? ' class="table-success"' : '') + '>';
        html += `<td>${item.transaction_id}</td>`;
        const officerName = item.duty_officer || 'Unknown';
        html += `<td>${item.rank_position} ${item.borrower_name}
                  <i class="fas fa-question-circle text-muted ms-1" data-bs-toggle="tooltip" title="Duty Officer: ${officerName}"></i>
                  <br><small class="text-muted">${item.unit}</small></td>`;
        html += `<td>${item.item_name}</td>`;
        html += `<td><span class="badge bg-secondary">${item.category}</span></td>`;
        html += `<td>${item.quantity_borrowed} ${item.item_unit}</td>`;
        const borrowedDate = item.borrowed_date ? new Date(item.borrowed_date).toLocaleDateString() : '—';
        html += `<td>${borrowedDate}</td>`;
        html += `<td>${expectedReturn}${isOverdue ? ' <i class="fas fa-exclamation-triangle text-success" title="Overdue"></i>' : ''}</td>`;
        html += `<td>${item.purpose || 'N/A'}</td>`;
        
        if (item.can_return === 'yes') {
            html += `<td><button class="btn btn-sm btn-success" onclick="showReturnModal(${item.transaction_id}, '${item.item_name}', ${item.quantity_borrowed}, '${item.item_unit}')">Return</button></td>`;
        } else {
            html += '<td><span class="badge bg-success">Cannot Return</span><br><small class="text-muted">Consumable/Disposable</small></td>';
        }
        
        html += '</tr>';
    });
    
    html += '</tbody></table></div>';
    container.innerHTML = cards + html;
    // Initialize Bootstrap tooltips for duty officer icons
    try {
        const ttEls = [].slice.call(container.querySelectorAll('[data-bs-toggle="tooltip"]'));
        ttEls.forEach(el => { try { new bootstrap.Tooltip(el); } catch (e) {} });
    } catch (e) { /* ignore */ }
}

// Global add-to-cart for quantity prompt modal used in browse flow
window.addItemToCart = function(code, name) {
    try {
        const qtyInput = document.getElementById('borrowQuantity');
        const qty = parseInt(qtyInput && qtyInput.value ? qtyInput.value : '0', 10);
        if (!code || !qty || qty <= 0) {
            showError('Please enter a valid quantity');
            return;
        }
        addToCartDirectly(code, name || code, qty);
        // Close the quantity modal and clean overlays
        const qm = bootstrap.Modal.getInstance(document.getElementById('quantityModal'));
        if (qm) { try { qm.hide(); } catch (e) {} }
        cleanupBootstrapModals();
    } catch (e) {
        console.error('addItemToCart error:', e);
        showError('Failed to add item to cart');
    }
};

// Helper to fully clean up Bootstrap modal artifacts to prevent page from being unclickable
function cleanupBootstrapModals() {
    try {
        document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('padding-right');
    } catch (e) { /* ignore */ }
}

// Show return modal
function showReturnModal(transactionId, itemName, maxQuantity, unit) {
    const modalHtml = `
        <div class="modal fade" id="returnModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Return Item</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="returnItemForm">
                            <input type="hidden" id="returnTransactionId" value="${transactionId}">
                            <div class="mb-3">
                                <label class="form-label">Item: <strong>${itemName}</strong></label>
                            </div>
                            <div class="mb-3">
                                <label for="returnQuantity" class="form-label">Return Quantity</label>
                                <input type="number" class="form-control" id="returnQuantity" min="1" max="${maxQuantity}" value="${maxQuantity}" required>
                                <small class="text-muted">Max: ${maxQuantity} ${unit}</small>
                            </div>
                            <div class="mb-3">
                                <label for="returnCondition" class="form-label">Condition</label>
                                <select class="form-select" id="returnCondition" required>
                                    <option value="good">Good</option>
                                    <option value="fair">Fair</option>
                                    <option value="poor">Poor</option>
                                    <option value="damaged">Damaged</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="returnNotes" class="form-label">Notes (Optional)</label>
                                <textarea class="form-control" id="returnNotes" rows="3" placeholder="Any additional notes about the return"></textarea>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-success" onclick="processItemReturn()">Process Return</button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remove existing modal if any
    const existingModal = document.getElementById('returnModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Add modal to body
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('returnModal'));
    modal.show();
}

// Process item return
// Legacy function retained for backward compatibility but not exported
function processItemReturnLegacy() {
    const transactionId = document.getElementById('returnTransactionId').value;
    const quantity = document.getElementById('returnQuantity').value;
    const condition = document.getElementById('returnCondition').value;
    const notes = document.getElementById('returnNotes').value;
    
    if (!quantity || quantity <= 0) {
        showError('Please enter a valid quantity');
        return;
    }
    
    const returnData = {
        transaction_id: transactionId,
        return_quantity: parseInt(quantity),
        condition: condition,
        notes: notes
    };
    
    showLoading();
    
    fetch(getApiUrl('borrowed_items.php?action=return_item'), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(returnData)
    })
    .then(async (response) => {
        const text = await response.text();
        try { return JSON.parse(text); }
        catch (e) {
            console.error('Return API raw response:', text);
            throw new Error('Invalid JSON from return API');
        }
    })
    .then(data => {
        hideLoading();
        if (data.success) {
            showSuccess(`Successfully returned ${data.returned_quantity} ${data.item_name}`);
            
            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('returnModal'));
            modal.hide();
            
            // Reload borrowed items
            loadBorrowedItems();
        } else {
            showError('Return failed: ' + data.message);
        }
    })
    .catch(error => {
        hideLoading();
        console.error('Error processing return:', error);
        // Fallback: try legacy return path if JSON was invalid or API failed
        try {
            const txn = document.getElementById('returnTransactionId')?.value;
            if (txn) {
                showLoading('Retrying return via fallback...');
                processReturn(txn);
                return;
            }
        } catch (e) { /* ignore */ }
        showError('Error processing return');
    });
}

// Supply Management Functions

// Load supply items by category
function loadSupplyItems() {
    const category = document.getElementById('supplyCategory').value;
    showLoading();
    
    // Support All Categories by loading all then flattening
    const isAll = !category || category.toLowerCase() === 'all';
    const url = isAll 
        ? getApiUrl('get_items.php?action=get_all')
        : getApiUrl(`get_items.php?action=get_by_category&category=${encodeURIComponent(category)}`);
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            hideLoading();
            if (data.success) {
                const items = Array.isArray(data.items) ? data.items : flattenItems(data.items || {});
                displaySupplyItems(items);
            } else {
                showError('Failed to load supply items: ' + data.message);
            }
        })
        .catch(error => {
            hideLoading();
            console.error('Error loading supply items:', error);
            showError('Error loading supply items');
        });
}

// Display supply items
function displaySupplyItems(items) {
    const container = document.getElementById('supplyItemsList');
    const processBtn = document.getElementById('processResupplyBtn');
    
    if (items.length === 0) {
        container.innerHTML = '<p class="text-muted">No supply items found in this category.</p>';
        processBtn.style.display = 'none';
        return;
    }
    
    let html = '<div class="items-css-grid">';
        items.forEach(item => {
        // Show exactly what backend provides as DB quantity when available
        const quantity = (item.quantity != null ? item.quantity : (item.available_quantity != null ? item.available_quantity : 0));
        const stockClass = quantity < 10 ? 'text-danger' : quantity < 50 ? 'text-warning' : 'text-success';
        const stockIcon = quantity < 10 ? 'fas fa-exclamation-triangle' : quantity < 50 ? 'fas fa-exclamation-circle' : 'fas fa-check-circle';
        const retRaw = (item.can_be_returned ?? '').toString().toLowerCase();
        const retVal = (retRaw === '' ? '' : ((retRaw === 'returnable' || retRaw === '1' || retRaw === 'true') ? 'returnable' : 'non-returnable'));
        
        html += `
            <div class="card mb-2 supply-item-card" data-item-id="${item.id ?? item.item_id ?? item.code ?? item.item_code}" data-category="${item.category || ''}" data-unit="${item.unit || ''}" data-returnable="${retVal}">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <h6 class="card-title mb-1">${item.item_name}</h6>
                            <p class="card-text mb-2">
                                <span class="badge bg-secondary">${item.category || 'Uncategorized'}</span>
                                <span class="ms-2 ${stockClass}">
                                    <i class="${stockIcon}"></i> ${quantity} ${item.unit || 'pcs'}
                                </span>
                            </p>
                        </div>
                        <div class="resupply-controls" style="min-width: 120px;">
                            <div class="input-group input-group-sm">
                                <input type="number" class="form-control resupply-quantity" 
                                       step="1" value="1" inputmode="numeric" pattern="-?\\d*"
                                       placeholder="+/- Qty" title="Use negative value to decrease stock">
                                <button class="btn btn-outline-danger btn-sm prefill-decrease" 
                                        type="button" title="Prefill negative quantity">–</button>
                                <button class="btn btn-outline-primary btn-sm toggle-resupply" 
                                        type="button" data-item-id="${item.id}">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                            <div class="d-flex gap-1 justify-content-end mt-2">
                                <button class="btn btn-outline-secondary btn-sm edit-item" type="button" title="Edit item">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <button class="btn btn-outline-danger btn-sm delete-item" type="button" title="Delete item">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    container.innerHTML = html;
    
    // Enable multiple item selection with checkboxes
    enableMultipleSelection();
    
    // Add event listeners for resupply toggles
    document.querySelectorAll('.toggle-resupply').forEach(btn => {
        btn.addEventListener('click', function() {
            const card = this.closest('.supply-item-card');
            const isSelected = card.classList.contains('selected');
            
            if (isSelected) {
                card.classList.remove('selected');
                this.innerHTML = '<i class="fas fa-plus"></i>';
                this.classList.remove('btn-success');
                this.classList.add('btn-outline-primary');
            } else {
                card.classList.add('selected');
                this.innerHTML = '<i class="fas fa-check"></i>';
                this.classList.remove('btn-outline-primary');
                this.classList.add('btn-success');
            }
            
            updateProcessButton();
        });
    });
    
    // Decrease helper: fill negative quantity quickly
    document.querySelectorAll('.prefill-decrease').forEach(btn => {
        btn.addEventListener('click', function() {
            const input = this.closest('.input-group').querySelector('.resupply-quantity');
            if (!input) return;
            const abs = Math.abs(parseInt(input.value) || 1);
            input.value = -abs;
            input.focus();
        });
    });
    
    // Edit item handler (simple prompts for now)
    document.querySelectorAll('.edit-item').forEach(btn => {
        btn.addEventListener('click', function() {
            const card = this.closest('.supply-item-card');
            const id = card?.dataset?.itemId;
            const nameEl = card?.querySelector('.card-title');
            const qtyEl = card?.querySelector('.card-text .ms-2');
            const currentName = nameEl ? nameEl.textContent.trim() : '';
            // Extract number part from "<i>icon</i> N unit"
            let currentQty = 0;
            try {
                const qtyTextNode = qtyEl ? qtyEl.textContent : '';
                const m = String(qtyTextNode).match(/(-?\d+)/);
                currentQty = m ? parseInt(m[1], 10) : 0;
            } catch (e) {}
            const currentCategory = card?.dataset?.category || '';
            const currentUnit = card?.dataset?.unit || '';
            const currentReturnable = card?.dataset?.returnable || '';
            editSupplyItem(id, currentName, currentQty, currentCategory, currentUnit, currentReturnable);
        });
    });
    
    // Delete item handler (PIN protected)
    document.querySelectorAll('.delete-item').forEach(btn => {
        btn.addEventListener('click', function() {
            const card = this.closest('.supply-item-card');
            const id = card?.dataset?.itemId;
            const nameEl = card?.querySelector('.card-title');
            const itemName = nameEl ? nameEl.textContent.trim() : 'this item';
            deleteSupplyItem(id, itemName);
        });
    });
    
    processBtn.style.display = 'none';
}

// Update process button visibility based on selected items
function updateProcessButton() {
    const processBtn = document.getElementById('processResupplyBtn');
    const selectedCards = document.querySelectorAll('.supply-item-card.selected');
    
    if (processBtn) {
        processBtn.style.display = selectedCards.length > 0 ? 'inline-block' : 'none';
    }
}

// Enable multiple item selection with checkboxes
function enableMultipleSelection() {
    // Wait for items to be loaded
    setTimeout(() => {
        const itemCards = document.querySelectorAll('.item-card, .card[data-item-code]');
        // If items are not yet rendered, retry shortly without spamming logs
        if (!itemCards || itemCards.length === 0) {
            setTimeout(() => {
                const retryCards = document.querySelectorAll('.item-card, .card[data-item-code]');
                if (!retryCards || retryCards.length === 0) return; // give up silently
                retryCards.forEach(card => attachCheckboxToCard(card));
                if (typeof updateProcessButton === 'function') updateProcessButton();
            }, 500);
            return;
        }
        // Attach on first pass
        itemCards.forEach(card => attachCheckboxToCard(card));
        if (typeof updateProcessButton === 'function') updateProcessButton();
    }, 500);
}

// Safe no-op to avoid errors if checkbox attachment is not needed in current UI
function attachCheckboxToCard(card) {
    try {
        if (!card) return;
        if (card.dataset && card.dataset.checkboxAttached === '1') return;
        // No UI change required for current borrowing/supply flows.
        if (card.dataset) card.dataset.checkboxAttached = '1';
    } catch (e) { /* ignore */ }
}

// Process selected resupply
function processResupply() {
    const selectedCards = document.querySelectorAll('.supply-item-card.selected');
    
    if (selectedCards.length === 0) {
        showError('No items selected for resupply');
        return;
    }
    
    const resupplyItems = [];
    
    selectedCards.forEach(card => {
        const itemIdRaw = card.dataset.itemId;
        // Coerce to number when possible; otherwise pass through the raw value
        const itemId = (/^\d+$/.test(String(itemIdRaw))) ? parseInt(itemIdRaw, 10) : itemIdRaw;
        const quantityInput = card.querySelector('.resupply-quantity');
        const quantity = parseInt(quantityInput.value) || 0;
        
        if (quantity !== 0) {
            resupplyItems.push({
                id: itemId,
                quantity: quantity
            });
        }
    });
    
    if (resupplyItems.length === 0) {
        showError('Please enter valid quantities for selected items');
        return;
    }
    
    showLoading();
    
    fetch(getApiUrl('supply.php?action=process_resupply'), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ items: resupplyItems })
    })
    .then(async (response) => {
        const text = await response.text();
        try {
            return JSON.parse(text);
        } catch (e) {
            // Fallback: surface raw response to the UI so users don't see a JSON.parse error
            console.error('Resupply API raw response (non-JSON):', text);
            return { success: false, message: (text || `HTTP ${response.status}`), _raw: text, _status: response.status };
        }
    })
    .then(data => {
        hideLoading();
        if (data.success) {
            let message = 'Resupply completed successfully:\n';
            data.updated_items.forEach(item => {
                const delta = Number(item.added);
                const sign = delta >= 0 ? '+' : '';
                message += `• ${item.name}: ${sign}${delta} (Total: ${item.new_total})\n`;
            });
            showSuccess(message);
            
            // Reload supply items
            loadSupplyItems();
        } else {
            showError('Resupply failed: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        hideLoading();
        console.error('Error processing resupply:', error);
        showError('Error processing resupply: ' + (error && error.message ? error.message : 'Network or parse error'));
    });
}

// Add new supply item
function addSupplyItem(event) {
    event.preventDefault();
    
    const itemName = document.getElementById('newSupplyName').value.trim();
    const category = document.getElementById('newSupplyCategory').value;
    const unit = document.getElementById('newSupplyUnit').value;
    const quantity = parseInt(document.getElementById('newSupplyQuantity').value) || 0;
    
    if (!itemName || !category || !unit || quantity <= 0) {
        showError('Please fill in all fields with valid values');
        return;
    }
    
    const returnableSelect = document.getElementById('newSupplyReturnable');
    const canBeReturned = returnableSelect ? returnableSelect.value : 'returnable';

    const supplyData = {
        item_name: itemName,
        category: category,
        unit: unit,
        quantity: quantity,
        can_be_returned: canBeReturned
    };
    
    showLoading();
    
    fetch(getApiUrl('supply.php?action=add_supply_item'), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(supplyData)
    })
    .then(async (response) => {
        const text = await response.text();
        try { return JSON.parse(text); }
        catch (e) { console.error('Add Supply API raw response:', text); throw e; }
    })
    .then(data => {
        hideLoading();
        if (data.success) {
            showSuccess(`Successfully added ${itemName} to inventory`);
            
            // Reset form
            document.getElementById('addSupplyForm').reset();
            
            // Reload supply items if same category is selected
            const selectedCategory = document.getElementById('supplyCategory').value;
            if (selectedCategory === category) {
                loadSupplyItems();
            }
        } else {
            showError('Failed to add supply item: ' + data.message);
        }
    })
    .catch(error => {
        hideLoading();
        console.error('Error adding supply item:', error);
        showError('Error adding supply item');
    });
}

// Global functions for modal callbacks
window.processReturn = processReturn;
window.simulateQRScan = simulateQRScan;
window.loadBorrowedItems = loadBorrowedItems;
window.showReturnModal = showReturnModal;
window.processItemReturn = processItemReturn;
window.loadSupplyItems = loadSupplyItems;
window.processResupply = processResupply;
window.addSupplyItem = addSupplyItem;

// Simplified borrowing - direct item click with quantity prompt (CSS Grid compatible)
function createItemCard(item) {
    const col = document.createElement('div');
    // Grid item works with .items-css-grid container
    col.className = 'grid-item';
    
    const categoryIcon = getCategoryIcon(item.category);
    const statusBadge = item.available_quantity > 0 ? 
        `<span class="badge bg-success">Available: ${item.available_quantity}</span>` : 
        '<span class="badge bg-danger">Out of Stock</span>';

    // Determine returnable status from ENUM or legacy values
    const retRaw = (item.can_be_returned ?? '').toString().toLowerCase();
    const retFlag = (retRaw === '' ? true : (retRaw === 'returnable' || retRaw === '1' || retRaw === 'true'));
    const returnableBadge = retFlag 
        ? '<span class="badge bg-info ms-1">Returnable</span>' 
        : '<span class="badge bg-success ms-1">Non-returnable</span>';
    
    col.innerHTML = `
        <div class="card bg-secondary h-100 item-card ${item.available_quantity <= 0 ? 'disabled' : ''}" style="cursor: pointer;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <h6 class="card-title" title="${item.item_name}" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">${item.item_name}</h6>
                    <i class="${categoryIcon} text-primary"></i>
                </div>
                <p class="card-text small text-muted mb-2">${(item.category || 'Uncategorized')} • ${item.unit}</p>
                <div class="item-badges mb-2">
                    ${statusBadge}
                    ${returnableBadge}
                </div>
                ${item.description ? `<p class="card-text small mt-2">${item.description}</p>` : ''}
            </div>
        </div>
    `;
    
    // Add click event for simplified borrowing
    if (item.available_quantity > 0) {
        col.addEventListener('click', function() {
            promptQuantityAndAddToCart(item);
        });
    }
    
    return col;
}

function promptQuantityAndAddToCart(item) {
    const modalHtml = `
        <div class="modal fade" id="quantityModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Borrow Item</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label"><strong>Item:</strong> ${item.item_name}</label>
                            <p class="text-muted small">Available: ${item.available_quantity} ${item.unit}</p>
                        </div>
                        <div class="mb-3">
                            <label for="borrowQuantity" class="form-label">Quantity to Borrow</label>
                            <input type="number" class="form-control" id="borrowQuantity" 
                                   min="1" max="${item.available_quantity}" step="1" value="1" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-success" id="confirmAddToCartBtn">
                            <i class="fas fa-cart-plus me-2"></i>Add to Cart
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remove existing modal if any
    const existingModal = document.getElementById('quantityModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    const modal = new bootstrap.Modal(document.getElementById('quantityModal'));

    // Preserve return date before showing modal
    const returnDateValue = document.getElementById('returnDate')?.value;

    modal.show();

    // Bind Add to Cart programmatically to avoid relying on global function names
    const confirmBtn = document.getElementById('confirmAddToCartBtn');
    if (confirmBtn) {
        const code = item.item_code || item.qr_code || (item.id ? String(item.id) : '');
        // Guard against double-click / double-binding
        confirmBtn.addEventListener('click', function onConfirm() {
            if (confirmBtn.dataset.clicked === '1') return;
            confirmBtn.dataset.clicked = '1';
            confirmBtn.disabled = true;
            const qtyInput = document.getElementById('borrowQuantity');
            const qty = sanitizeQuantity(qtyInput && qtyInput.value ? qtyInput.value : '0', parseInt(item.available_quantity, 10));
            if (!code) {
                showError('This item has no code (item_code/qr_code). Cannot add to cart.');
                confirmBtn.disabled = false;
                confirmBtn.dataset.clicked = '';
                return;
            }
            if (!Number.isInteger(qty) || qty <= 0) {
                showError('Please enter a valid quantity');
                confirmBtn.disabled = false;
                confirmBtn.dataset.clicked = '';
                return;
            }
            addToCartDirectly(code, item.item_name, qty);
            try { bootstrap.Modal.getInstance(document.getElementById('quantityModal')).hide(); } catch (e) {}
            cleanupBootstrapModals();
        });
    }

 

    // Restore return date after modal is shown
    modal._element.addEventListener('shown.bs.modal', function() {
        if (returnDateValue) {
            const returnDateInput = document.getElementById('returnDate');
            if (returnDateInput) {
                returnDateInput.value = returnDateValue;
            }
        }
    });

    // Also restore after modal is hidden and clean overlays
    modal._element.addEventListener('hidden.bs.modal', function() {
        setTimeout(() => {
            if (returnDateValue) {
                const returnDateInput = document.getElementById('returnDate');
                if (returnDateInput) {
                    returnDateInput.value = returnDateValue;
                }
            } else {
                initializeReturnDate();
            }
            cleanupBootstrapModals();
        }, 50);
    });
}

function addToCartDirectly(itemCode, itemName, quantity) {
    // Check if item already exists in cart
    const existingItemIndex = borrowCart.findIndex(item => item.code === itemCode);
    
    if (existingItemIndex !== -1) {
        // Update quantity if item exists
        borrowCart[existingItemIndex].quantity += quantity;
    } else {
        // Add new item to cart
        borrowCart.push({
            code: itemCode,
            name: itemName,
            quantity: quantity
        });
    }
    
    // Update cart display and submit button
    updateCartDisplay();
    updateSubmitButton();
    
    // Show success message
    showSuccess(`Added ${quantity} ${itemName} to cart`);
}

function loadInventoryItems() {
    // This function should load items and then enable multiple selection
    setTimeout(() => {
        enableMultipleSelection();
    }, 500); // Wait for items to load
}