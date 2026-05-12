/* ============================================================
   validate.js — Client-side Validation
   University of Botswana Online Admission System
   ============================================================ */

'use strict';

// ---- Password Policy Constants ----
const PASSWORD_RULES = {
    minLength:    { regex: /.{8,}/,                               label: 'At least 8 characters'               },
    uppercase:    { regex: /[A-Z]/,                               label: 'At least one uppercase letter (A–Z)' },
    lowercase:    { regex: /[a-z]/,                               label: 'At least one lowercase letter (a–z)' },
    digit:        { regex: /[0-9]/,                               label: 'At least one number (0–9)'           },
    special:      { regex: /[!@#$%^&*()\-_=+\[\]{}|;':",.<>?\/]/, label: 'At least one special character (!@#$%^&* etc.)' },
};

// ---- Password strength checker ----
function checkPasswordStrength(password) {
    let score = 0;
    for (const rule of Object.values(PASSWORD_RULES)) {
        if (rule.regex.test(password)) score++;
    }
    if (score <= 1) return { level: 'weak',   label: 'Weak' };
    if (score === 2) return { level: 'fair',   label: 'Fair' };
    if (score === 3) return { level: 'good',   label: 'Good' };
    if (score >= 4)  return { level: 'strong', label: 'Strong' };
}

// ---- Live password feedback ----
function initPasswordStrength(passwordInputId) {
    const input = document.getElementById(passwordInputId);
    if (!input) return;

    // Build UI elements
    const container = document.createElement('div');
    container.className = 'password-strength';

    const bar = document.createElement('div');
    bar.className = 'strength-bar';

    const text = document.createElement('span');
    text.className = 'strength-text';

    container.appendChild(bar);
    container.appendChild(text);
    input.parentNode.insertAdjacentElement('afterend', container);

    // Build rules checklist
    const ruleList = document.createElement('ul');
    ruleList.className = 'password-rules';

    const ruleItems = {};
    for (const [key, rule] of Object.entries(PASSWORD_RULES)) {
        const li = document.createElement('li');
        li.textContent = rule.label;
        li.dataset.rule = key;
        ruleList.appendChild(li);
        ruleItems[key] = li;
    }
    container.appendChild(ruleList);

    input.addEventListener('input', function () {
        const val = this.value;

        // Update rule checklist
        for (const [key, rule] of Object.entries(PASSWORD_RULES)) {
            if (rule.regex.test(val)) {
                ruleItems[key].classList.add('valid');
            } else {
                ruleItems[key].classList.remove('valid');
            }
        }

        // Update strength bar
        if (val.length === 0) {
            bar.className = 'strength-bar';
            text.className = 'strength-text';
            text.textContent = '';
            return;
        }

        const strength = checkPasswordStrength(val);
        bar.className = `strength-bar ${strength.level}`;
        text.className = `strength-text ${strength.level}`;
        text.textContent = `Strength: ${strength.label}`;
    });
}

// ---- Confirm password match ----
function initPasswordConfirm(passwordId, confirmId) {
    const password = document.getElementById(passwordId);
    const confirm  = document.getElementById(confirmId);
    if (!password || !confirm) return;

    const hint = document.createElement('span');
    hint.className = 'form-hint';
    confirm.parentNode.appendChild(hint);

    function validate() {
        if (confirm.value.length === 0) {
            hint.textContent = '';
            confirm.classList.remove('is-valid', 'is-invalid');
            return;
        }
        if (password.value === confirm.value) {
            hint.textContent = '✔ Passwords match.';
            hint.style.color = 'var(--success)';
            confirm.classList.add('is-valid');
            confirm.classList.remove('is-invalid');
        } else {
            hint.textContent = '✖ Passwords do not match.';
            hint.style.color = 'var(--red)';
            confirm.classList.add('is-invalid');
            confirm.classList.remove('is-valid');
        }
    }

    confirm.addEventListener('input', validate);
    password.addEventListener('input', validate);
}

// ---- Client-side password validation (before form submit) ----
function validatePasswordValue(password) {
    const errors = [];
    for (const [, rule] of Object.entries(PASSWORD_RULES)) {
        if (!rule.regex.test(password)) {
            errors.push(rule.label);
        }
    }
    return errors;
}

// ---- Signup form validation ----
function validateSignupForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return;

    form.addEventListener('submit', function (e) {
        const password = document.getElementById('password').value;
        const confirm  = document.getElementById('confirm_password').value;
        const errors   = validatePasswordValue(password);

        if (errors.length > 0) {
            e.preventDefault();
            showFormError(form, 'Password requirements not met:\n• ' + errors.join('\n• '));
            return;
        }

        if (password !== confirm) {
            e.preventDefault();
            showFormError(form, 'Passwords do not match. Please re-enter.');
            return;
        }
    });
}

// ---- Application form: Dynamic qualifications ----
function initQualificationsManager(containerId, addBtnId, subjects) {
    const container = document.getElementById(containerId);
    const addBtn    = document.getElementById(addBtnId);
    if (!container || !addBtn) return;

    let rowCount = container.querySelectorAll('.qual-row').length;

    addBtn.addEventListener('click', function () {
        if (rowCount >= 20) {
            alert('Maximum 20 subjects allowed.');
            return;
        }
        const row = buildQualRow(rowCount, subjects);
        container.appendChild(row);
        rowCount++;
    });

    // Remove handler (delegated)
    container.addEventListener('click', function (e) {
        if (e.target.classList.contains('btn-remove') || e.target.closest('.btn-remove')) {
            const row = e.target.closest('.qual-row');
            if (container.querySelectorAll('.qual-row').length > 1) {
                row.remove();
                rowCount--;
                renumberRows(container);
            } else {
                alert('You must have at least one subject.');
            }
        }
    });

    // Live points display
    container.addEventListener('change', function (e) {
        if (e.target.classList.contains('grade-select')) {
            updatePointsDisplay(e.target);
        }
    });
}

function buildQualRow(index, subjects) {
    const gradePoints = { 'A*': 8, 'A': 7, 'B': 6, 'C': 5, 'D': 4, 'E': 3, 'U': 0 };

    const row = document.createElement('div');
    row.className = 'qual-row';
    row.dataset.index = index;

    // Subject dropdown
    const subjDiv = document.createElement('div');
    const subjLabel = document.createElement('label');
    subjLabel.textContent = 'Subject';
    subjLabel.style.fontSize = '0.85rem';
    subjLabel.style.fontWeight = '600';
    const subjSel = document.createElement('select');
    subjSel.className = 'form-control subject-select';
    subjSel.name = `qualifications[${index}][subject]`;
    subjSel.required = true;

    const blankOpt = document.createElement('option');
    blankOpt.value = '';
    blankOpt.textContent = '— Select Subject —';
    subjSel.appendChild(blankOpt);

    for (const subj of subjects) {
        const opt = document.createElement('option');
        opt.value = subj;
        opt.textContent = subj;
        subjSel.appendChild(opt);
    }
    subjDiv.appendChild(subjLabel);
    subjDiv.appendChild(subjSel);

    // Grade dropdown
    const gradeDiv = document.createElement('div');
    const gradeLabel = document.createElement('label');
    gradeLabel.textContent = 'Grade';
    gradeLabel.style.fontSize = '0.85rem';
    gradeLabel.style.fontWeight = '600';
    const gradeSel = document.createElement('select');
    gradeSel.className = 'form-control grade-select';
    gradeSel.name = `qualifications[${index}][grade]`;
    gradeSel.required = true;

    const gradeBlank = document.createElement('option');
    gradeBlank.value = '';
    gradeBlank.textContent = '— Grade —';
    gradeSel.appendChild(gradeBlank);

    for (const [g, p] of Object.entries(gradePoints)) {
        const opt = document.createElement('option');
        opt.value = g;
        opt.textContent = `${g} (${p} pts)`;
        gradeSel.appendChild(opt);
    }
    gradeDiv.appendChild(gradeLabel);
    gradeDiv.appendChild(gradeSel);

    // Remove button
    const removeBtn = document.createElement('button');
    removeBtn.type = 'button';
    removeBtn.className = 'btn-remove';
    removeBtn.title = 'Remove this subject';
    removeBtn.innerHTML = '✖';
    removeBtn.style.alignSelf = 'flex-end';
    removeBtn.style.marginBottom = '2px';

    row.appendChild(subjDiv);
    row.appendChild(gradeDiv);
    row.appendChild(removeBtn);

    return row;
}

function updatePointsDisplay(gradeSelect) {
    const gradePoints = { 'A*': 8, 'A': 7, 'B': 6, 'C': 5, 'D': 4, 'E': 3, 'U': 0 };
    const grade = gradeSelect.value;
    const row   = gradeSelect.closest('.qual-row');
    let pointsDisplay = row.querySelector('.points-display');

    if (!pointsDisplay) {
        pointsDisplay = document.createElement('small');
        pointsDisplay.className = 'points-display text-muted';
        gradeSelect.parentNode.appendChild(pointsDisplay);
    }

    pointsDisplay.textContent = grade ? `Points: ${gradePoints[grade] ?? 0}` : '';
}

function renumberRows(container) {
    container.querySelectorAll('.qual-row').forEach((row, i) => {
        row.dataset.index = i;
        row.querySelectorAll('[name]').forEach(el => {
            el.name = el.name.replace(/\[\d+\]/, `[${i}]`);
        });
    });
}

// ---- File upload drag-and-drop enhancement ----
function initFileUpload(areaId) {
    const area = document.getElementById(areaId);
    if (!area) return;

    const input    = area.querySelector('input[type="file"]');
    const fileList = area.querySelector('.file-list');

    area.addEventListener('dragover', function (e) {
        e.preventDefault();
        area.classList.add('drag-over');
    });

    area.addEventListener('dragleave', function () {
        area.classList.remove('drag-over');
    });

    area.addEventListener('drop', function (e) {
        e.preventDefault();
        area.classList.remove('drag-over');
        input.files = e.dataTransfer.files;
        updateFileList(fileList, input.files);
    });

    if (input) {
        input.addEventListener('change', function () {
            updateFileList(fileList, this.files);
        });
    }
}

function updateFileList(listEl, files) {
    if (!listEl) return;
    listEl.innerHTML = '';
    for (const file of files) {
        const li = document.createElement('li');
        const sizeKb = (file.size / 1024).toFixed(1);
        li.textContent = `📄 ${file.name} (${sizeKb} KB)`;
        listEl.appendChild(li);
    }
}

// ---- Show error inside form ----
function showFormError(form, message) {
    let existing = form.querySelector('.js-form-error');
    if (!existing) {
        existing = document.createElement('div');
        existing.className = 'alert alert-error js-form-error';
        form.insertAdjacentElement('afterbegin', existing);
    }
    existing.innerHTML = '<span class="alert-icon">✖</span> ' + message.replace(/\n/g, '<br>');
    existing.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

// ---- Confirm dangerous actions ----
function confirmAction(message) {
    return confirm(message || 'Are you sure?');
}

// ---- Auto-dismiss alerts ----
document.addEventListener('DOMContentLoaded', function () {
    const alerts = document.querySelectorAll('.alert-success, .alert-info');
    alerts.forEach(function (alert) {
        setTimeout(function () {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 4000);
    });
});
