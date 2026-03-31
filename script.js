document.addEventListener('DOMContentLoaded', () => {
    const table = document.querySelector('table');
    const modal = document.getElementById('addModal');
    const btnAdd = document.getElementById('btnAdd');
    const btnCloseModal = document.getElementById('btnCloseModal');
    const addForm = document.getElementById('addForm');
    const btnSaveAndKeep = document.getElementById('btnSaveAndKeep');

    // --- Delete Row ---
    table.addEventListener('click', (e) => {
        const deleteBtn = e.target.closest('.btn-delete-row');
        if (!deleteBtn) return;

        const row = deleteBtn.closest('tr');
        const id = row.dataset.id;
        const metricsId = row.querySelector('td:nth-child(2)')?.innerText || 'this row';
        
        showConfirm(`Delete "${metricsId}"?`, () => {
            // Proceed with delete
            const formData = new FormData();
            formData.append('action', 'delete_row');
            formData.append('id', id);

            deleteBtn.textContent = '...';
            deleteBtn.disabled = true;

            fetch('api.php', {
                method: 'POST',
                body: formData
            })
                .then(r => r.json())
                .then(result => {
                    if (result.success) {
                        // Fade out and remove row
                        row.style.transition = 'opacity 0.3s';
                        row.style.opacity = '0';
                        setTimeout(() => row.remove(), 300);
                    } else {
                        showAlert('Error: ' + result.message);
                        deleteBtn.textContent = '−';
                        deleteBtn.disabled = false;
                    }
                })
                .catch(err => {
                    console.error(err);
                    showAlert('Network error');
                    deleteBtn.textContent = '−';
                    deleteBtn.disabled = false;
                });
        }, 'Delete', true);
    });

    // --- Inline Editing ---
    table.addEventListener('dblclick', (e) => {
        const cell = e.target.closest('td.editable');
        if (!cell || cell.classList.contains('editing')) return;

        const originalValue = cell.innerText.trim();
        // Remove formatting like % for editing
        let cleanValue = originalValue.replace('%', '').replace(',', '');

        cell.classList.add('editing');
        cell.innerHTML = `<input type="text" class="cell-editor" value="${cleanValue}">`;

        const input = cell.querySelector('input');
        input.focus();

        input.addEventListener('blur', () => {
            const newValue = input.value;
            saveCell(cell, newValue);
        });

        input.addEventListener('keydown', (evt) => {
            if (evt.key === 'Enter') {
                input.blur();
            }
        });
    });

    function saveCell(cell, value) {
        const row = cell.parentElement;
        const id = row.dataset.id;
        const column = cell.dataset.col;

        // Optimistic UI Update (restore text immediately)
        // Add % back if it was a percentage column
        let displayValue = value;
        if (cell.classList.contains('col-percentage')) {
            displayValue += '%';
        }

        cell.innerHTML = displayValue;
        cell.classList.remove('editing');

        // Send to Backend
        const formData = new FormData();
        formData.append('action', 'update_cell');
        formData.append('id', id);
        formData.append('column', column);
        formData.append('value', value);

        fetch('api.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    showAlert('Error saving: ' + data.message);
                    // Ideally revert UI here if failed
                }
            })
            .catch(err => {
                console.error(err);
                showAlert('Network error');
            });
    }

    // --- Modal Logic ---
    if (btnAdd) {
        btnAdd.addEventListener('click', () => {
            addForm.reset();
            modal.classList.add('open');
        });
    }

    btnCloseModal.addEventListener('click', () => {
        modal.classList.remove('open');
    });

    // Close on click outside
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.classList.remove('open');
        }
    });

    // Handle "Save & Close" via Form Submit
    addForm.addEventListener('submit', (e) => {
        e.preventDefault();
        saveRow(true);
    });

    // Handle "Save" (Keep open)
    btnSaveAndKeep.addEventListener('click', () => {
        if (addForm.checkValidity()) {
            saveRow(false);
        } else {
            addForm.reportValidity();
        }
    });

    function saveRow(closeModal) {
        const formData = new FormData(addForm);
        formData.append('action', 'create_row');

        fetch('api.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // In a full SPA, we'd append the row to the table here.
                    // For simplicity, we can reload to see the new row, 
                    // OR ideally append it dynamically to strict to requirements "without refresh" if implied.
                    // Since user didn't explicitly strict "no refresh", re-fetching or appending is fine.
                    // Let's reload to be safe on sorting/ID unless we build the DOM row manually.
                    window.location.reload();
                } else {
                    showAlert('Error processing: ' + data.message);
                }
            })
            .catch(err => {
                console.error(err);
                showAlert('Network error');
            });
    }

    // --- CSV Import ---
    const btnImport = document.getElementById('btnImport');
    const csvInput = document.getElementById('csvInput');

    if (btnImport) {
        btnImport.addEventListener('click', () => {
            csvInput.click();
        });
    }

    if (csvInput) {
        csvInput.addEventListener('change', () => {
        if (csvInput.files.length === 0) return;

        const file = csvInput.files[0];
        const formData = new FormData();
        formData.append('action', 'import_csv');
        formData.append('csv_file', file);

        btnImport.textContent = 'Importing...';
        btnImport.disabled = true;

        fetch('api.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    window.location.reload();
                } else {
                    showAlert('Error importing: ' + data.message);
                }
            })
            .catch(err => {
                console.error(err);
                showAlert('Network error during import.');
            })
            .finally(() => {
                btnImport.textContent = 'Import CSV';
                btnImport.disabled = false;
                csvInput.value = '';
            });
        });
    }

    // --- Run Assessment ---
    const btnRunAssessment = document.getElementById('btnRunAssessment');
    if (btnRunAssessment) {
        btnRunAssessment.addEventListener('click', () => {
            showConfirm('Run full automated assessment for all metrics? This will update health scores.', () => {
                btnRunAssessment.textContent = 'Running...';
                btnRunAssessment.disabled = true;

                const formData = new FormData();
                formData.append('action', 'run_full_assessment');

                fetch('api.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(r => r.json())
                    .then(result => {
                        if (result.success) {
                            showAlert(result.message, () => {
                                window.location.href = 'assessment.php';
                            });
                        } else {
                            showAlert('Error: ' + result.message);
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        showAlert('Network error');
                    })
                    .finally(() => {
                        btnRunAssessment.textContent = 'Run All Assessment';
                        btnRunAssessment.disabled = false;
                    });
            });
        });
    }

    const btnRunMonthAssessment = document.getElementById('btnRunMonthAssessment');
    const runMonthSelect = document.getElementById('runMonthSelect');
    if (btnRunMonthAssessment) {
        btnRunMonthAssessment.addEventListener('click', () => {
            const selectedMonth = runMonthSelect.value;
            if (!selectedMonth) {
                showAlert('Please select a Month & Yr first.');
                return;
            }

            showConfirm(`Run automated assessment for ${selectedMonth}?`, () => {
                btnRunMonthAssessment.textContent = 'Running...';
                btnRunMonthAssessment.disabled = true;

                const formData = new FormData();
                formData.append('action', 'run_month_assessment');
                formData.append('month_yr', selectedMonth);

                fetch('api.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(r => r.json())
                    .then(result => {
                        if (result.success) {
                            showAlert(result.message, () => {
                                window.location.href = 'assessment.php';
                            });
                        } else {
                            showAlert('Error: ' + result.message);
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        showAlert('Network error');
                    })
                    .finally(() => {
                        btnRunMonthAssessment.textContent = 'Run for Selected Month';
                        btnRunMonthAssessment.disabled = false;
                    });
            });
        });
    }

    // --- Spreadsheet-like Paste & Add Row ---

    const tableContainer = document.getElementById('metricsTableContainer');
    const metricsTable = document.getElementById('metricsTable');
    const addRowZone = document.getElementById('addRowZone');
    const tbody = metricsTable ? metricsTable.querySelector('tbody') : null;

    // Column definitions for new row
    const columns = [
        { name: 'metrics_id', label: 'Metrics_ID', type: 'text' },
        { name: 'record_id', label: 'Record ID', type: 'display' }, // Auto-generated, not editable
        { name: 'campaign', label: 'Campaign', type: 'text' },
        { name: 'month_yr', label: 'Month & Yr', type: 'text', placeholder: 'MM/DD/YYYY' },
        { name: 'speed_mobile', label: 'Mobile %', type: 'number', class: 'col-percentage' },
        { name: 'speed_desktop', label: 'Desktop %', type: 'number', class: 'col-percentage' },
        { name: 'speed_avg', label: 'Avg %', type: 'number', class: 'col-percentage' },
        { name: 'leads', label: 'Leads', type: 'number', class: 'col-number' },
        { name: 'ranking', label: 'Ranking', type: 'number', class: 'col-number' },
        { name: 'traffic', label: 'Traffic', type: 'number', class: 'col-number' },
        { name: 'engagement', label: 'Engagement', type: 'text', class: 'col-center', placeholder: '0:00' },
        { name: 'conversion', label: 'Conversion', type: 'number', class: 'col-decimal', step: '0.1' }
    ];

    // Double-click on add row zone to add a new empty row
    if (addRowZone && tbody) {
        addRowZone.addEventListener('dblclick', () => {
            createEmptyEditableRow();
        });
    }

    function createEmptyEditableRow() {
        // Check if there's already a new row being edited
        if (document.querySelector('.new-editable-row')) {
            document.querySelector('.new-editable-row input')?.focus();
            return;
        }

        const tr = document.createElement('tr');
        tr.className = 'new-editable-row new-row';
        tr.dataset.isNew = 'true';

        columns.forEach((col, idx) => {
            const td = document.createElement('td');
            td.className = col.class || '';

            if (col.type === 'display') {
                // Record ID - will be auto-generated
                td.style.color = '#8c959f';
                td.style.fontSize = '11px';
                td.innerHTML = '<em>Auto</em>';
            } else {
                const input = document.createElement('input');
                input.type = col.type === 'number' ? 'text' : 'text'; // Use text for flexible input
                input.name = col.name;
                input.placeholder = col.placeholder || col.label;
                input.className = 'new-row-input';
                input.style.cssText = 'width: 100%; padding: 6px 8px; border: 1px solid #d0d7de; border-radius: 4px; font-size: 12px; box-sizing: border-box;';

                // Tab navigation between inputs
                input.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') {
                        saveNewRow(tr);
                    } else if (e.key === 'Escape') {
                        tr.remove();
                    }
                });

                td.appendChild(input);
            }

            tr.appendChild(td);
        });

        // Add save/cancel buttons cell
        const actionTd = document.createElement('td');
        actionTd.style.cssText = 'white-space: nowrap;';
        actionTd.innerHTML = `
            <button class="btn-save-row" style="background: #1f883d; color: white; border: none; padding: 4px 10px; border-radius: 4px; cursor: pointer; font-size: 11px; margin-right: 4px;">Save</button>
            <button class="btn-cancel-row" style="background: #f6f8fa; color: #24292f; border: 1px solid #d0d7de; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-size: 11px;">✕</button>
        `;
        tr.appendChild(actionTd);

        actionTd.querySelector('.btn-save-row').addEventListener('click', () => saveNewRow(tr));
        actionTd.querySelector('.btn-cancel-row').addEventListener('click', () => tr.remove());

        tbody.appendChild(tr);

        // Focus first input
        tr.querySelector('input')?.focus();

        // Scroll to bottom
        tableContainer.scrollTop = tableContainer.scrollHeight;
    }

    function saveNewRow(tr) {
        const inputs = tr.querySelectorAll('input');
        const data = {};

        inputs.forEach(input => {
            data[input.name] = input.value;
        });

        // Validate required field
        if (!data.metrics_id || !data.metrics_id.trim()) {
            showToast('Metrics ID is required', 'error');
            tr.querySelector('input[name="metrics_id"]')?.focus();
            return;
        }

        // Send to API
        const formData = new FormData();
        formData.append('action', 'create_row');

        Object.keys(data).forEach(key => {
            formData.append(key, data[key]);
        });

        const saveBtn = tr.querySelector('.btn-save-row');
        saveBtn.textContent = '...';
        saveBtn.disabled = true;

        fetch('api.php', {
            method: 'POST',
            body: formData
        })
            .then(r => r.json())
            .then(result => {
                if (result.success) {
                    showToast('Row saved successfully', 'success');
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showToast('Error: ' + result.message, 'error');
                    saveBtn.textContent = 'Save';
                    saveBtn.disabled = false;
                }
            })
            .catch(err => {
                console.error(err);
                showToast('Network error', 'error');
                saveBtn.textContent = 'Save';
                saveBtn.disabled = false;
            });
    }

    // Listen for paste events on the table container
    if (tableContainer) {
        // Make the container focusable and show focus indicator
        tableContainer.addEventListener('focus', () => {
            tableContainer.classList.add('paste-ready');
        });

        tableContainer.addEventListener('blur', () => {
            tableContainer.classList.remove('paste-ready');
        });

        // Handle paste event
        tableContainer.addEventListener('paste', (e) => {
            // Don't intercept if user is editing a cell
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
                return;
            }

            e.preventDefault();

            const clipboardData = e.clipboardData || window.clipboardData;
            const pastedText = clipboardData.getData('text');

            if (!pastedText.trim()) return;

            const rows = parseSpreadsheetData(pastedText);

            if (rows.length === 0) {
                showToast('No valid data found in clipboard', 'error');
                return;
            }

            // Import the pasted data
            importPastedRows(rows);
        });

        // Also listen on document level when table is focused
        document.addEventListener('paste', (e) => {
            // Only handle if table container is focused or user clicked on table area
            if (!tableContainer.contains(document.activeElement) && document.activeElement !== tableContainer) {
                return;
            }

            // Don't intercept if user is editing something
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
                return;
            }
        });
    }

    function parseSpreadsheetData(text) {
        const lines = text.trim().split(/\r?\n/);
        const rows = [];

        lines.forEach((line, idx) => {
            const cols = line.split('\t');

            // Skip empty first column
            if (!cols[0] || cols[0].trim() === '') return;

            // Skip header row if detected
            if (idx === 0 && cols[0].toLowerCase().includes('metrics') && cols[0].toLowerCase().includes('id')) {
                return;
            }

            rows.push({
                metrics_id: cols[0]?.trim() || '',
                campaign: cols[1]?.trim() || '',
                month_yr: cols[2]?.trim() || '',
                speed_mobile: cols[3]?.trim() || '0',
                speed_desktop: cols[4]?.trim() || '0',
                speed_avg: cols[5]?.trim() || '0',
                leads: cols[6]?.trim() || '0',
                ranking: cols[7]?.trim() || '0',
                traffic: cols[8]?.trim() || '0',
                engagement: cols[9]?.trim() || '0:00',
                conversion: cols[10]?.trim() || '0'
            });
        });

        return rows;
    }

    function importPastedRows(rows) {
        showToast(`Importing ${rows.length} row(s)...`, 'info');

        const formData = new FormData();
        formData.append('action', 'import_paste');
        formData.append('data', JSON.stringify(rows));

        fetch('api.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    // Reload after a brief delay to show the toast
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    showToast('Error: ' + data.message, 'error');
                }
            })
            .catch(err => {
                console.error(err);
                showToast('Network error during import', 'error');
            });
    }

    function showToast(message, type = 'info') {
        // Remove existing toast
        const existingToast = document.querySelector('.paste-toast');
        if (existingToast) existingToast.remove();

        const toast = document.createElement('div');
        toast.className = 'paste-toast';
        toast.textContent = message;

        // Color based on type
        if (type === 'error') {
            toast.style.background = '#cf222e';
        } else if (type === 'success') {
            toast.style.background = '#1f883d';
        } else {
            toast.style.background = '#0969da';
        }

        document.body.appendChild(toast);

        // Auto remove after 3 seconds
        setTimeout(() => {
            toast.remove();
        }, 3000);
    }

    // Click on table container to focus it (for paste to work)
    if (tableContainer) {
        tableContainer.addEventListener('click', (e) => {
            // Don't focus if clicking on an editable cell or input
            if (e.target.classList.contains('editable') || e.target.tagName === 'INPUT') {
                return;
            }
            tableContainer.focus();
        });
    }

    // --- Table Sorting Logic ---
    const headers = document.querySelectorAll('th.sortable');
    headers.forEach(header => {
        header.addEventListener('click', () => {
            const index = Array.from(header.parentElement.children).indexOf(header);
            const isAscending = header.classList.contains('sort-asc');
            const direction = isAscending ? -1 : 1;

            // Update header UI
            headers.forEach(h => h.classList.remove('sort-asc', 'sort-desc'));
            header.classList.add(isAscending ? 'sort-desc' : 'sort-asc');

            const rowsArray = Array.from(tbody.querySelectorAll('tr:not(.new-row)'));
            
            rowsArray.sort((rowA, rowB) => {
                const cellA = rowA.children[index].innerText.trim();
                const cellB = rowB.children[index].innerText.trim();

                // Handle different types
                const valA = parseCellValue(cellA);
                const valB = parseCellValue(cellB);

                if (valA < valB) return -1 * direction;
                if (valA > valB) return 1 * direction;
                return 0;
            });

            // Re-append sorted rows
            rowsArray.forEach(row => tbody.appendChild(row));
        });
    });


    function parseCellValue(value) {
        // Clean %, commas, etc.
        let clean = value.replace('%', '').replace(/,/g, '').trim();
        
        // Handle dates (e.g. 3/15/2023 or March 15, 2023)
        if (clean.includes('/') || clean.includes(',') || isNaN(clean)) {
            const date = Date.parse(clean);
            if (!isNaN(date)) return date;
        }

        // Handle time (e.g. 0:20)
        if (clean.includes(':')) {
            const parts = clean.split(':');
            return (parseInt(parts[0]) * 60) + (parseInt(parts[1]) || 0);
        }

        // Handle numbers
        if (!isNaN(parseFloat(clean))) return parseFloat(clean);

        return clean.toLowerCase();
    }

    // --- Global Modal Utilities ---
    const confirmModal = document.getElementById('confirmModal');
    const confirmTitle = document.getElementById('confirmTitle');
    const confirmMessage = document.getElementById('confirmMessage');
    const btnConfirmAction = document.getElementById('btnConfirmAction');
    const btnConfirmCancel = document.getElementById('btnConfirmCancel');
    const btnCloseConfirmModal = document.getElementById('btnCloseConfirmModal');

    let confirmCallback = null;

    window.showConfirm = function (message, onConfirm, actionText = 'Proceed', isDanger = false, title = 'Confirm Action') {
        confirmMessage.textContent = message;
        confirmTitle.textContent = title;
        btnConfirmAction.textContent = actionText;
        btnConfirmCancel.style.display = 'block';
        
        if (isDanger) {
            btnConfirmAction.classList.add('danger');
        } else {
            btnConfirmAction.classList.remove('danger');
        }

        confirmCallback = onConfirm;
        confirmModal.classList.add('open');
    };

    window.showAlert = function (message, onOk = null, title = 'Notification') {
        confirmMessage.textContent = message;
        confirmTitle.textContent = title;
        btnConfirmAction.textContent = 'OK';
        btnConfirmCancel.style.display = 'none';
        btnConfirmAction.classList.remove('danger');

        confirmCallback = onOk;
        confirmModal.classList.add('open');
    };

    function closeConfirm() {
        confirmModal.classList.remove('open');
        confirmCallback = null;
    }

    btnConfirmAction.addEventListener('click', () => {
        const callback = confirmCallback;
        closeConfirm();
        if (callback) callback();
    });

    btnConfirmCancel.addEventListener('click', closeConfirm);
    btnCloseConfirmModal.addEventListener('click', closeConfirm);

    // Close on click outside
    confirmModal.addEventListener('click', (e) => {
        if (e.target === confirmModal) {
            closeConfirm();
        }
    });

});

