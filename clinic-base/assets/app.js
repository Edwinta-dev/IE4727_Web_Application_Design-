function registrationFieldError(field, message) {
    var error = document.querySelector('[data-error-for="' + field + '"]');
    if (error) {
        error.textContent = message;
    }
}

function clearRegistrationErrors() {
    document.querySelectorAll('[data-error-for]').forEach(function (error) {
        error.textContent = '';
    });
}

function updateRegistrationPasswordMatch(password, confirmation, includeEmpty) {
    var mismatch = (includeEmpty || confirmation.value !== '') && password.value !== confirmation.value;
    registrationFieldError('confirm_password', mismatch ? 'Passwords must match.' : '');
    if (mismatch) {
        confirmation.setAttribute('aria-invalid', 'true');
    } else {
        confirmation.removeAttribute('aria-invalid');
    }
    return mismatch;
}

function setRegistrationRole(role) {
    document.querySelectorAll('[data-role-fields]').forEach(function (fieldset) {
        var active = fieldset.getAttribute('data-role-fields') === role;
        fieldset.hidden = !active;
        fieldset.querySelectorAll('input, select, textarea').forEach(function (field) {
            field.disabled = !active;
        });
    });
}

function validateRegistrationForm(event) {
    clearRegistrationErrors();
    var form = event.target;
    var valid = true;
    var password = form.querySelector('[name="password"]');
    var confirmation = form.querySelector('[name="confirm_password"]');

    form.querySelectorAll('input, select, textarea').forEach(function (field) {
        if (!field.disabled && !field.checkValidity()) {
            registrationFieldError(field.name, field.validationMessage);
            valid = false;
        }
    });
    if (password && confirmation && password.value !== confirmation.value) {
        updateRegistrationPasswordMatch(password, confirmation, true);
        valid = false;
    }
    if (!valid) {
        event.preventDefault();
        return false;
    }
    return true;
}

function initialiseRegistrationForm() {
    var form = document.getElementById('registration-form');
    if (!form) {
        return;
    }
    var password = form.querySelector('[name="password"]');
    var confirmation = form.querySelector('[name="confirm_password"]');
    if (password && confirmation) {
        var confirmationTouched = false;
        confirmation.addEventListener('input', function () {
            confirmationTouched = true;
            updateRegistrationPasswordMatch(password, confirmation, confirmationTouched);
        });
        [password, confirmation].forEach(function (field) {
            field.addEventListener('blur', function () {
                updateRegistrationPasswordMatch(password, confirmation, confirmationTouched);
            });
        });
        password.addEventListener('input', function () {
            updateRegistrationPasswordMatch(password, confirmation, confirmationTouched);
        });
    }
    var role = form.querySelector('select[name="role"]');
    role.addEventListener('change', function () {
        setRegistrationRole(role.value);
    });
    setRegistrationRole(role.value);
    var noKnownAllergies = form.querySelector('[name="NoKnownAllergies"]');
    var allergies = form.querySelector('[name="Allergies"]');
    if (noKnownAllergies && allergies) {
        var typedAllergies = noKnownAllergies.checked ? '' : allergies.value;
        var updateAllergies = function () {
            if (noKnownAllergies.checked) {
                typedAllergies = allergies.value === 'No Known Allergies' ? typedAllergies : allergies.value;
                allergies.value = 'No Known Allergies';
            } else {
                allergies.value = typedAllergies;
            }
            allergies.readOnly = noKnownAllergies.checked;
        };
        noKnownAllergies.addEventListener('change', updateAllergies);
        updateAllergies();
    }
    form.addEventListener('submit', validateRegistrationForm);
}

document.addEventListener('DOMContentLoaded', initialiseRegistrationForm);

document.addEventListener('DOMContentLoaded', function () {
    var specialty = document.querySelector('.specialty-filter select[name="specialty"]');
    if (specialty) {
        specialty.addEventListener('change', function () {
            specialty.form.requestSubmit();
        });
    }
});

document.addEventListener('DOMContentLoaded', function () {
    var scheduleDate = document.querySelector('.schedule-date-filter input[name="date"]');
    if (scheduleDate) {
        scheduleDate.addEventListener('change', function () {
            if (scheduleDate.checkValidity()) {
                scheduleDate.form.requestSubmit();
            }
        });
    }
});


function initialiseDoctorDirectorySearch() {
    var searchInput = document.getElementById('doctor-search');
    var emptyMessage = document.getElementById('doctor-search-empty');
    if (!searchInput) {
        return;
    }

    function onSearchInput() {
        var query = searchInput.value.trim().toLowerCase();
        var rows = document.querySelectorAll('.doctor-directory tbody tr');
        var matchedCount = 0;

        rows.forEach(function (row) {
            if (row.classList.contains('empty-state')) {
                return;
            }
            var text = (row.textContent || '').toLowerCase();
            var matched = query === '' || text.includes(query);
            row.hidden = !matched;
            if (matched) {
                matchedCount++;
            }
        });

        if (emptyMessage) {
            emptyMessage.hidden = matchedCount > 0;
        }
    }

    searchInput.addEventListener('input', onSearchInput);
    searchInput.addEventListener('search', onSearchInput);
    searchInput.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
        }
    });

    if (searchInput.value) {
        onSearchInput();
    }
}

document.addEventListener('DOMContentLoaded', initialiseDoctorDirectorySearch);
