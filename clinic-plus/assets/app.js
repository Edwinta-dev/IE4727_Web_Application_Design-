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
        registrationFieldError('confirm_password', 'Passwords must match.');
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
    form.querySelectorAll('[name="role"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            setRegistrationRole(radio.value);
        });
    });
    var selected = form.querySelector('[name="role"]:checked');
    setRegistrationRole(selected ? selected.value : 'patient');
    form.addEventListener('submit', validateRegistrationForm);
}

document.addEventListener('DOMContentLoaded', initialiseRegistrationForm);
