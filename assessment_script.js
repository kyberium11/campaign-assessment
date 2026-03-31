document.addEventListener('DOMContentLoaded', () => {
    const table = document.getElementById('assessmentTable');
    const modal = document.getElementById('addAssessmentModal');
    const btnAdd = document.getElementById('btnAddAssessment');
    const btnClose = document.getElementById('btnCloseAssessmentModal');

    // --- Delete Row ---
    table.addEventListener('click', (e) => {
        const deleteBtn = e.target.closest('.btn-delete-row');
        if (!deleteBtn) return;

        const row = deleteBtn.closest('tr');
        const id = row.dataset.id;
        const recordId = row.querySelector('td:nth-child(2)')?.innerText || 'this assessment';

        if (!confirm(`Delete "${recordId}"?`)) return;

        // Send delete request
        const formData = new FormData();
        formData.append('action', 'delete_assessment');
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
                    alert('Error: ' + result.message);
                    deleteBtn.textContent = '−';
                    deleteBtn.disabled = false;
                }
            })
            .catch(err => {
                console.error(err);
                alert('Network error');
                deleteBtn.textContent = '−';
                deleteBtn.disabled = false;
            });
    });

    // --- Inline Selection/Editing for Assessment Scores ---
    table.addEventListener('dblclick', (e) => {
        const cell = e.target.closest('td.editable');
        if (!cell || cell.classList.contains('editing')) return;

        const originalValue = cell.innerText.trim();
        cell.classList.add('editing');
        cell.innerHTML = `<input type="number" min="1" max="5" class="cell-editor" value="${originalValue}" style="width: 50px;">`;

        const input = cell.querySelector('input');
        input.focus();

        input.addEventListener('blur', () => {
            saveAssessmentCell(cell, input.value);
        });

        input.addEventListener('keydown', (evt) => {
            if (evt.key === 'Enter') input.blur();
        });
    });

    function saveAssessmentCell(cell, value) {
        const row = cell.parentElement;
        const id = row.dataset.id;
        const column = cell.dataset.col;

        const formData = new FormData();
        formData.append('action', 'update_assessment_cell');
        formData.append('id', id);
        formData.append('column', column);
        formData.append('value', value);

        fetch('api.php', {
            method: 'POST',
            body: formData
        })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    cell.innerHTML = value;
                    cell.classList.remove('editing');
                    // Update Health Score and Label in UI
                    row.querySelector('.health-cell').innerText = data.health;
                    const labelSpan = row.querySelector('.label-cell');
                    labelSpan.innerText = data.label;
                    labelSpan.className = 'status-badge ' + data.class + ' label-cell';
                } else {
                    alert('Error: ' + data.message);
                    cell.innerHTML = originalValue; // revert
                }
            });
    }

    // --- Modal Logic ---
    if (btnAdd) {
        btnAdd.addEventListener('click', () => modal.classList.add('open'));
    }
    if (btnClose) {
        btnClose.addEventListener('click', () => modal.classList.remove('open'));
    }

    window.createAssessment = function (metricId) {
        const formData = new FormData();
        formData.append('action', 'create_assessment');
        formData.append('metrics_row_id', metricId);

        fetch('api.php', {
            method: 'POST',
            body: formData
        })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert('Failed: ' + data.message);
                }
            });
    };

    // --- Run All Assessment ---
    const btnRunFullAssessment = document.getElementById('btnRunFullAssessment');
    if (btnRunFullAssessment) {
        btnRunFullAssessment.addEventListener('click', () => {
            if (!confirm('Run full automated assessment for all metrics? This will update all health scores.')) return;

            btnRunFullAssessment.textContent = 'Running...';
            btnRunFullAssessment.disabled = true;

            const formData = new FormData();
            formData.append('action', 'run_full_assessment');

            fetch('api.php', {
                method: 'POST',
                body: formData
            })
                .then(r => r.json())
                .then(result => {
                    if (result.success) {
                        alert(result.message);
                        window.location.reload();
                    } else {
                        alert('Error: ' + result.message);
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Network error');
                })
                .finally(() => {
                    btnRunFullAssessment.textContent = 'Run Full Automated Assessment';
                    btnRunFullAssessment.disabled = false;
                });
        });
    }

    // --- Table Sorting Logic ---
    const headers = document.querySelectorAll('th.sortable');
    const tbody = table.querySelector('tbody');

    headers.forEach(header => {
        header.addEventListener('click', () => {
            const index = Array.from(header.parentElement.children).indexOf(header);
            const isAscending = header.classList.contains('sort-asc');
            const direction = isAscending ? -1 : 1;

            // Update header UI
            headers.forEach(h => h.classList.remove('sort-asc', 'sort-desc', 'sortable'));
            headers.forEach(h => h.classList.add('sortable')); // Ensure all keep sortable class
            header.classList.add(isAscending ? 'sort-desc' : 'sort-asc');

            const rowsArray = Array.from(tbody.querySelectorAll('tr'));
            
            rowsArray.sort((rowA, rowB) => {
                const cellA = rowA.children[index].innerText.trim();
                const cellB = rowB.children[index].innerText.trim();

                const valA = parseAssessmentCellValue(cellA);
                const valB = parseAssessmentCellValue(cellB);

                if (valA < valB) return -1 * direction;
                if (valA > valB) return 1 * direction;
                return 0;
            });

            // Re-append sorted rows
            rowsArray.forEach(row => tbody.appendChild(row));
        });
    });

    function parseAssessmentCellValue(value) {
        let clean = value.replace(/,/g, '').trim();
        
        // Handle dates (e.g. Feb 01, 2026)
        if (clean.includes(',') || isNaN(clean)) {
            const date = Date.parse(clean);
            if (!isNaN(date)) return date;
        }

        // Handle numbers/scores
        if (!isNaN(parseFloat(clean))) return parseFloat(clean);

        return clean.toLowerCase();
    }
});
