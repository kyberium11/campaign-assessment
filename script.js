document.addEventListener('DOMContentLoaded', () => {
    const table = document.querySelector('table');
    const modal = document.getElementById('addModal');
    const btnAdd = document.getElementById('btnAdd');
    const btnCloseModal = document.getElementById('btnCloseModal');
    const addForm = document.getElementById('addForm');
    const btnSaveAndKeep = document.getElementById('btnSaveAndKeep');

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
                    alert('Error saving: ' + data.message);
                    // Ideally revert UI here if failed
                }
            })
            .catch(err => {
                console.error(err);
                alert('Network error');
            });
    }

    // --- Modal Logic ---
    btnAdd.addEventListener('click', () => {
        addForm.reset();
        modal.classList.add('open');
    });

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
                    alert('Error processing: ' + data.message);
                }
            })
            .catch(err => {
                console.error(err);
                alert('Network error');
            });
    }

    // --- CSV Import ---
    const btnImport = document.getElementById('btnImport');
    const csvInput = document.getElementById('csvInput');

    btnImport.addEventListener('click', () => {
        csvInput.click();
    });

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
                    alert('Error importing: ' + data.message);
                }
            })
            .catch(err => {
                console.error(err);
                alert('Network error during import.');
            })
            .finally(() => {
                btnImport.textContent = 'Import CSV';
                btnImport.disabled = false;
                csvInput.value = '';
            });
    });

    // --- Paste from Spreadsheet ---
    const btnPaste = document.getElementById('btnPaste');
    const pasteModal = document.getElementById('pasteModal');
    const btnClosePasteModal = document.getElementById('btnClosePasteModal');
    const pasteArea = document.getElementById('pasteArea');
    const pastePreview = document.getElementById('pastePreview');
    const previewBody = document.getElementById('previewBody');
    const previewCount = document.getElementById('previewCount');
    const btnClearPaste = document.getElementById('btnClearPaste');
    const btnImportPaste = document.getElementById('btnImportPaste');

    let parsedPasteData = [];

    if (btnPaste) {
        btnPaste.addEventListener('click', () => {
            pasteModal.classList.add('open');
            pasteArea.value = '';
            parsedPasteData = [];
            pastePreview.style.display = 'none';
            previewBody.innerHTML = '';
            setTimeout(() => pasteArea.focus(), 100);
        });
    }

    if (btnClosePasteModal) {
        btnClosePasteModal.addEventListener('click', () => {
            pasteModal.classList.remove('open');
        });
    }

    if (pasteModal) {
        pasteModal.addEventListener('click', (e) => {
            if (e.target === pasteModal) {
                pasteModal.classList.remove('open');
            }
        });
    }

    if (pasteArea) {
        pasteArea.addEventListener('input', () => {
            parsePastedData(pasteArea.value);
        });

        // Also handle direct paste event for better UX
        pasteArea.addEventListener('paste', (e) => {
            setTimeout(() => {
                parsePastedData(pasteArea.value);
            }, 50);
        });
    }

    function parsePastedData(text) {
        if (!text.trim()) {
            parsedPasteData = [];
            pastePreview.style.display = 'none';
            previewBody.innerHTML = '';
            return;
        }

        // Split by newlines, then by tabs (spreadsheet format)
        const lines = text.trim().split(/\r?\n/);
        parsedPasteData = [];
        previewBody.innerHTML = '';

        lines.forEach((line, idx) => {
            const cols = line.split('\t');

            // Skip if no metrics_id (first column)
            if (!cols[0] || cols[0].trim() === '') return;

            // Skip header row if detected
            if (idx === 0 && cols[0].toLowerCase().includes('metrics') && cols[0].toLowerCase().includes('id')) {
                return;
            }

            parsedPasteData.push({
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

            // Add preview row (show first 3 columns)
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td style="padding: 4px; border: 1px solid #ddd;">${cols[0] || ''}</td>
                <td style="padding: 4px; border: 1px solid #ddd;">${cols[1] || ''}</td>
                <td style="padding: 4px; border: 1px solid #ddd;">${cols[2] || ''}</td>
            `;
            previewBody.appendChild(tr);
        });

        previewCount.textContent = parsedPasteData.length;
        pastePreview.style.display = parsedPasteData.length > 0 ? 'block' : 'none';
    }

    if (btnClearPaste) {
        btnClearPaste.addEventListener('click', () => {
            pasteArea.value = '';
            parsedPasteData = [];
            pastePreview.style.display = 'none';
            previewBody.innerHTML = '';
            pasteArea.focus();
        });
    }

    if (btnImportPaste) {
        btnImportPaste.addEventListener('click', () => {
            if (parsedPasteData.length === 0) {
                alert('No data to import. Please paste spreadsheet data first.');
                return;
            }

            btnImportPaste.textContent = 'Importing...';
            btnImportPaste.disabled = true;

            const formData = new FormData();
            formData.append('action', 'import_paste');
            formData.append('data', JSON.stringify(parsedPasteData));

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
                        alert('Error importing: ' + data.message);
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Network error during import.');
                })
                .finally(() => {
                    btnImportPaste.textContent = 'Import Data';
                    btnImportPaste.disabled = false;
                });
        });
    }

    // --- Embed Logic ---
});
