document.addEventListener('DOMContentLoaded', () => {
    const table = document.getElementById('assessmentTable');
    const modal = document.getElementById('addAssessmentModal');
    const btnAdd = document.getElementById('btnAddAssessment');
    const btnClose = document.getElementById('btnCloseAssessmentModal');

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
                    cell.innerHTML = value; // keep as is or revert
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
});
