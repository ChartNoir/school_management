// Validation Functions

function validateForm(formId) {
    const form = document.getElementById(formId);
    const inputs = form.querySelectorAll('input[required], textarea[required]');
    let valid = true;
    
    inputs.forEach(input => {
        if (!input.value.trim()) {
            valid = false;
            input.style.borderColor = 'red';
        } else {
            input.style.borderColor = '';
        }
    });
    return valid;
}


// Form Submission Handlers
document.getElementById('createStudentForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    if (!validateForm('createStudentForm')) {
        alert('Please fill all required fields');
        return;
    }
    const formData = new FormData(this);
    fetch('functions.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            alert(data.result === true ? 'Student created successfully!' : 'Error: ' + data.result);
            if (data.result === true) clearForm('createStudentForm');
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error: ' + error);
        });
});

document.getElementById('createTeacherForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    if (!validateForm('createTeacherForm')) {
        alert('Please fill all required fields');
        return;
    }
    const formData = new FormData(this);
    fetch('functions.php', { method: 'POST', body: formData })
       .then(response => {
            return response.text().then(text => {
                console.log('Raw response:', text);
                try {
                    return JSON.parse(text);
                } catch (err) {
                    throw new Error('Invalid JSON: ' + text);
                }
            });
        })
        .then(data => {
            alert(data.result === true ? 'Teacher created successfully!' : 'Error: ' + data.result);
            if (data.result === true) clearForm('createTeacherForm');
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error: ' + error);
        });
});

document.getElementById('createCourseForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    if (!validateForm('createCourseForm')) {
        alert('Please fill all required fields');
        return;
    }
    const formData = new FormData(this);
    fetch('functions.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            alert(data.result === true ? 'Course created successfully!' : 'Error: ' + data.result);
            if (data.result === true) clearForm('createCourseForm');
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error: ' + error);
        });
});

document.getElementById('createClassForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    if (!validateForm('createClassForm')) {
        alert('Please fill all required fields');
        return;
    }
    const formData = new FormData(this);
    fetch('functions.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            alert(data.result === true ? 'Class created successfully!' : 'Error: ' + data.result);
            if (data.result === true) clearForm('createClassForm');
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error: ' + error);
        });
});

document.getElementById('createEnrollmentForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    if (!validateForm('createEnrollmentForm')) {
        alert('Please fill all required fields');
        return;
    }
    const formData = new FormData(this);
    fetch('functions.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            alert(data.result === true ? 'Student enrolled successfully!' : 'Error: ' + data.result);
            if (data.result === true) clearForm('createEnrollmentForm');
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error: ' + error);
        });
});

// Clear Form Function
function clearForm(formId) {
    const form = document.getElementById(formId);
    form.reset();
    const inputs = form.querySelectorAll('input, textarea');
    inputs.forEach(input => input.style.borderColor = '');
}

// Additional Functions from index.php
function showSection(section) {
    document.querySelectorAll('.form-section').forEach(el => el.style.display = 'none');
    const sectionMap = {
        'createStudent': 'createStudent-section',
        'teacher': 'teacher-section',
        'course': 'course-section',
	'class': 'class-section',
	'enrollment': 'enrollment-section',
	'updateGrades': 'updateGrades-section',
        'viewRecords': 'viewRecords-section'
    };
    const sectionId = sectionMap[section] || section + '-section';
    const element = document.getElementById(sectionId);
    if (element) {
        element.style.display = 'block';
    } else {
        console.error(`Section with ID '${sectionId}' not found`);
    }
}

let personalData = null;

function toggleProfileMenu() {
    const menu = document.getElementById('profileMenu');
    menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
}

function showPasswordPrompt() {
    if (window.userRole === 'student' || window.userRole === 'teacher') {
        const dialog = new bootstrap.Modal(document.getElementById('passwordPromptDialog'));
        dialog.show();
    } else {
        requestPasswordReset();
    }
    toggleProfileMenu();
}

function closePasswordPrompt() {
    const dialog = bootstrap.Modal.getInstance(document.getElementById('passwordPromptDialog'));
    dialog.hide();
    document.getElementById('currentPasswordInput').value = '';
}

function showPasswordPrompt() {
    // Always show the password prompt for all roles
    const dialog = new bootstrap.Modal(document.getElementById('passwordPromptDialog'));
    dialog.show();
    toggleProfileMenu();
}

function requestPasswordReset() {
    const role = window.userRole;
    let currentPassword = document.getElementById('currentPasswordInput').value; // Always require password input
    if (!currentPassword) {
        alert('Please enter your current password.');
        return;
    }
    const formData = new FormData();
    formData.append('action', 'generate_reset_token');
    formData.append('csrf_token', window.csrfToken);
    formData.append('current_password', currentPassword); // Always send password
    fetch('functions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        console.log('Response from generate_reset_token:', data);
        if (typeof data.result === 'string') {
            if (data.result.includes("A password reset token has been generated")) {
                alert(data.result); // Show success message
                closePasswordPrompt();
            } else {
                alert('Error: ' + data.result);
            }
        } else if (data.result && typeof data.result === 'object' && data.result.token) {
            showResetTokenDialog(data.result.message, data.result.token);
            closePasswordPrompt();
        } else {
            alert('Error: Unexpected response: ' + JSON.stringify(data.result));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error: ' + error.message);
    });
}

function showResetTokenDialog(message, token) {
    const dialog = new bootstrap.Modal(document.getElementById('resetTokenDialog'));
    document.getElementById('resetTokenMessage').textContent = message;
    document.getElementById('resetTokenInput').value = token;
    dialog.show();
}

function showAdminContactDialog(message) {
    const dialog = new bootstrap.Modal(document.getElementById('adminContactDialog'));
    document.getElementById('adminContactMessage').innerHTML = message + '<br><br>Contact an administrator:<br>' + window.adminEmails;
    dialog.show();
}

function closeDialog() {
    const dialogs = ['resetTokenDialog', 'adminContactDialog'];
    dialogs.forEach(id => {
        const el = document.getElementById(id);
        const dialog = bootstrap.Modal.getInstance(el) || new bootstrap.Modal(el);
        dialog.hide();
    });
}

function copyTokenToClipboard() {
    const inputElement = document.getElementById('resetTokenInput');
    inputElement.select();
    document.execCommand('copy');
    alert('Token copied to clipboard!');
}

function goToResetPassword() {
    window.location.href = 'reset_password.php';
}

// Load records for editing
function loadEditRecords() {
    const table = document.getElementById('editTableSelector').value;
    const output = document.getElementById('editRecordsOutput');
    if (!table) {
        output.innerHTML = '<p>Please select a table to edit.</p>';
        return;
    }

    const formData = new FormData();
    formData.append('action', 'show_table');
    formData.append('table', table);
    formData.append('csrf_token', window.csrfToken);

    fetch('functions.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (typeof data.result === 'string') {
            output.innerHTML = `<div class="alert alert-info">${data.result}</div>`;
            return;
        }
        if (!data.result || data.result.length === 0) {
            output.innerHTML = '<div class="alert alert-warning">No records found.</div>';
            return;
        }

        let html = '<table class="table table-striped"><thead><tr>';
        const fields = Object.keys(data.result[0]);
        // Filter out specific fields based on the table
        const displayFields = table === 'users' 
            ? fields.filter(field => !['password', 'password_reset_token', 'password_reset_expiry', 'profile_image', 'created_at', 'updated_at', 'is_active'].includes(field))
            : (table === 'class' 
                ? fields.filter(field => !['created_at', 'updated_at'].includes(field))
                : fields);
        displayFields.forEach(field => {
            html += `<th>${field}</th>`;
        });
        html += '<th>Actions</th></tr></thead><tbody>';

        data.result.forEach((row, index) => {
            html += '<tr>';
            displayFields.forEach(field => {
                const isEditable = !['id', 'password', 'user_id', 'created_at', 'updated_at'].includes(field);
                html += `<td ${isEditable ? 'contenteditable="true"' : ''} data-field="${field}" data-id="${row.id}">${row[field] === null ? '' : row[field]}</td>`;
            });
            html += `<td>
                <button class="btn btn-success btn-sm" onclick="saveEdit('${table}', ${row.id}, ${index})">Save</button>
                <button class="btn btn-danger btn-sm" onclick="deleteRecord('${table}', ${row.id})">Delete</button>
            </td>`;
            html += '</tr>';
        });
        html += '</tbody></table>';
        output.innerHTML = html;
    })
    .catch(error => {
        console.error('Error in loadEditRecords:', error);
        output.innerHTML = `<div class="alert alert-danger">Error: ${error.message}</div>`;
    });
}

// Save edited record
function saveEdit(table, id, rowIndex) {
    const row = document.querySelectorAll('#editRecordsOutput table tbody tr')[rowIndex];
    const cells = row.querySelectorAll('td[contenteditable]');
    const updatedData = { id };

    cells.forEach(cell => {
        const field = cell.getAttribute('data-field');
        updatedData[field] = cell.textContent.trim();
    });

    const formData = new FormData();
    formData.append('action', `update_${table}_record`);
    formData.append('csrf_token', window.csrfToken);
    formData.append('data', JSON.stringify(updatedData));

    fetch('functions.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => {
        if (!response.ok) {
            return response.text().then(text => {
                throw new Error(`Server returned ${response.status}: ${text}`);
            });
        }
        return response.text(); // Get raw text first
    })
    .then(data => {
        alert(data.result === true ? 'Record updated successfully!' : 'Error: ' + data.result);
        if (data.result === true) loadEditRecords(); // Refresh table
    })
    .catch(error => {
        console.error('Error in saveEdit:', error);
        alert('Error: ' + error);
    });
}

// Delete record
function deleteRecord(table, id) {
    if (!confirm('Are you sure you want to delete this record?')) return;

    const formData = new FormData();
    formData.append('action', `delete_${table}_record`);
    formData.append('csrf_token', window.csrfToken);
    formData.append('id', id);

    fetch('functions.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        alert(data.result === true ? 'Record deleted successfully!' : 'Error: ' + data.result);
        if (data.result === true) loadEditRecords(); // Refresh table
    })
    .catch(error => {
        console.error('Error in deleteRecord:', error);
        alert('Error: ' + error);
    });
}

// Load field options for the Edit tab
function loadEditFieldOptions() {
    const table = document.getElementById('editTableSelector').value;
    const fieldFilterMenu = document.getElementById('editFieldFilterMenu');
    fieldFilterMenu.innerHTML = '';

    if (!table) {
        fieldFilterMenu.innerHTML = '<li class="dropdown-item">Select a table first</li>';
        document.getElementById('editFieldFilterDropdown').textContent = 'Choose Fields';
        return;
    }

    const hiddenFields = ['password', 'password_reset_token', 'password_reset_expiry', 'created_at', 'updated_at'];

    const formData = new FormData();
    formData.append('action', 'get_table_fields');
    formData.append('table', table);
    formData.append('csrf_token', window.csrfToken);

    fetch('functions.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (data.result && Array.isArray(data.result)) {
            const visibleFields = data.result.filter(field => !hiddenFields.includes(field));

            visibleFields.forEach(field => {
                const listItem = document.createElement('li');
                listItem.className = 'dropdown-item';
                listItem.innerHTML = `
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="edit_field_${field}" name="edit_fields" value="${field}">
                        <label class="form-check-label" for="edit_field_${field}">${field}</label>
                    </div>
                `;
                fieldFilterMenu.appendChild(listItem);
            });
            document.getElementById('editFieldFilterDropdown').textContent = `Choose Fields (${visibleFields.length} available)`;

            fieldFilterMenu.querySelectorAll('input[name="edit_fields"]').forEach(checkbox => {
                checkbox.addEventListener('change', () => {
                    const selectedCount = fieldFilterMenu.querySelectorAll('input[name="edit_fields"]:checked').length;
                    document.getElementById('editFieldFilterDropdown').textContent = `Choose Fields (${selectedCount} selected, ${visibleFields.length} available)`;
                });
            });
        } else {
            fieldFilterMenu.innerHTML = `<li class="dropdown-item">Error loading fields: ${data.result || 'Unknown error'}</li>`;
            document.getElementById('editFieldFilterDropdown').textContent = 'Choose Fields (Error)';
        }
    })
    .catch(error => {
        console.error('Error loading edit field options:', error);
        fieldFilterMenu.innerHTML = `<li class="dropdown-item">Error loading fields: ${error.message}</li>`;
        document.getElementById('editFieldFilterDropdown').textContent = 'Choose Fields (Error)';
    });
}

// Modified loadEditRecords function with pagination and field filtering
function loadEditRecords(page = 1) {
    const table = document.getElementById('editTableSelector').value;
    const output = document.getElementById('editRecordsOutput');
    const checkboxes = document.querySelectorAll('#editFieldFilterMenu input[type="checkbox"]');
    const selectedFields = Array.from(checkboxes)
        .filter(checkbox => checkbox.checked)
        .map(checkbox => checkbox.value);

    if (!table) {
        output.innerHTML = '<p>Please select a table to edit.</p>';
        return;
    }

    const formData = new FormData();
    formData.append('action', 'show_table');
    formData.append('table', table);
    formData.append('csrf_token', window.csrfToken);

    fetch('functions.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (typeof data.result === 'string') {
            output.innerHTML = `<div class="alert alert-info">${data.result}</div>`;
            return;
        }
        if (!data.result || data.result.length === 0) {
            output.innerHTML = '<div class="alert alert-warning">No records found.</div>';
            return;
        }

        let filteredData = data.result;
        let displayFields = selectedFields.length > 0 ? selectedFields : Object.keys(filteredData[0]);
        // Filter out sensitive fields
        const excludeFields = ['password', 'profile_image'];
        displayFields = displayFields.filter(field => !excludeFields.includes(field));

        const recordsPerPage = 10;
        const totalRecords = filteredData.length;
        const totalPages = Math.ceil(totalRecords / recordsPerPage);
        const currentPage = Math.min(Math.max(1, page), totalPages);

        const startIndex = (currentPage - 1) * recordsPerPage;
        const endIndex = startIndex + recordsPerPage;
        const paginatedData = filteredData.slice(startIndex, endIndex);

        let html = '<table class="table table-striped"><thead><tr>';
        displayFields.forEach(field => {
            html += `<th>${field}</th>`;
        });
        html += '<th>Actions</th></tr></thead><tbody>';

        paginatedData.forEach((row, index) => {
            html += '<tr>';
            displayFields.forEach(field => {
                const isEditable = !['id', 'user_id'].includes(field); // Define non-editable fields
                html += `<td ${isEditable ? 'contenteditable="true"' : ''} data-field="${field}" data-id="${row.id}">${row[field] === null ? '' : row[field]}</td>`;
            });
            html += `<td>
                <button class="btn btn-success btn-sm" onclick="saveEdit('${table}', ${row.id}, ${startIndex + index})">Save</button>
                <button class="btn btn-danger btn-sm" onclick="deleteRecord('${table}', ${row.id})">Delete</button>
            </td>`;
            html += '</tr>';
        });
        html += '</tbody></table>';

        let paginationHtml = '<nav aria-label="Edit records navigation"><ul class="pagination justify-content-center">';
        paginationHtml += '<li class="page-item ' + (currentPage === 1 ? 'disabled' : '') + '"><a class="page-link" href="#" onclick="loadEditRecords(' + (currentPage - 1) + '); return false;">Previous</a></li>';

        const maxPagesToShow = 5;
        let startPage = Math.max(1, currentPage - Math.floor(maxPagesToShow / 2));
        let endPage = Math.min(totalPages, startPage + maxPagesToShow - 1);

        if (endPage - startPage < maxPagesToShow - 1) {
            startPage = Math.max(1, endPage - maxPagesToShow + 1);
        }

        for (let i = startPage; i <= endPage; i++) {
            paginationHtml += '<li class="page-item ' + (i === currentPage ? 'active' : '') + '"><a class="page-link" href="#" onclick="loadEditRecords(' + i + '); return false;">' + i + '</a></li>';
        }

        paginationHtml += '<li class="page-item ' + (currentPage === totalPages ? 'disabled' : '') + '"><a class="page-link" href="#" onclick="loadEditRecords(' + (currentPage + 1) + '); return false;">Next</a></li>';
        paginationHtml += '</ul></nav>';

        output.innerHTML = html + paginationHtml;
    })
    .catch(error => {
        console.error('Error in loadEditRecords:', error);
        output.innerHTML = `<div class="alert alert-danger">Error: ${error.message}</div>`;
    });
}

// Reset Edit Filters Function
function resetEditFilters() {
    const checkboxes = document.querySelectorAll('#editFieldFilterMenu input[type="checkbox"]');
    checkboxes.forEach(checkbox => checkbox.checked = false);
    document.getElementById('editFieldFilterDropdown').textContent = 'Choose Fields';
    loadEditRecords(1);
}

// Update existing saveEdit function to use correct row index with pagination
function saveEdit(table, id, rowIndex) {
    const row = document.querySelectorAll('#editRecordsOutput table tbody tr')[rowIndex % 10]; // Adjust for pagination
    const cells = row.querySelectorAll('td[contenteditable]');
    const updatedData = { id };

    cells.forEach(cell => {
        const field = cell.getAttribute('data-field');
        updatedData[field] = cell.textContent.trim();
    });

    const formData = new FormData();
    formData.append('action', `update_${table}_record`);
    formData.append('csrf_token', window.csrfToken);
    formData.append('data', JSON.stringify(updatedData));

    fetch('functions.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        alert(data.result === true ? 'Record updated successfully!' : 'Error: ' + data.result);
        if (data.result === true) {
            const currentPage = document.querySelector('.pagination .active .page-link')?.textContent || 1;
            loadEditRecords(parseInt(currentPage));
        }
    })
    .catch(error => {
        console.error('Error in saveEdit:', error);
        alert('Error: ' + error);
    });
}

// Ensure event listeners are correctly set up
document.getElementById('editFieldFilterMenu')?.addEventListener('click', (e) => {
    e.stopPropagation();
});

// Event Listeners for Buttons
document.querySelectorAll('.clear-btn').forEach(btn => {
    btn.addEventListener('click', () => clearForm(btn.closest('form').id));
});

document.querySelector('#passwordPromptDialog .btn-close')?.addEventListener('click', closePasswordPrompt);
document.getElementById('submitPasswordBtn')?.addEventListener('click', requestPasswordReset);
document.getElementById('cancelPasswordBtn')?.addEventListener('click', closePasswordPrompt);

document.querySelector('#resetTokenDialog .btn-close')?.addEventListener('click', closeDialog);
document.getElementById('copyTokenBtn')?.addEventListener('click', copyTokenToClipboard);
document.getElementById('resetNowBtn')?.addEventListener('click', goToResetPassword);
document.getElementById('closeTokenBtn')?.addEventListener('click', closeDialog);

document.querySelector('#adminContactDialog .btn-close')?.addEventListener('click', closeDialog);
document.getElementById('closeAdminBtn')?.addEventListener('click', closeDialog);

document.getElementById('updateGradesForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    if (!validateForm('updateGradesForm')) {
        alert('Please fill all required fields');
        return;
    }
    const formData = new FormData(this);
    fetch('functions.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            alert(data.result === true ? 'Grade updated successfully!' : 'Error: ' + data.result);
            if (data.result === true) clearForm('updateGradesForm');
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error: ' + error);
        });
});

// Initialization
window.onload = function() {
    if (window.userRole === 'admin') {
        showSection('createStudent');
    }
};
