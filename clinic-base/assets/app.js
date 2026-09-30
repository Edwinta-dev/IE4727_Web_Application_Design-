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
    var track = document.querySelector('.registration-role-track');
    var heading = document.querySelector('.registration-role-heading');
    var switcher = document.querySelector('.registration-role-switcher');
    var typeFieldset = document.getElementById('registration-type');
    if (track && heading && switcher && typeFieldset) {
        document.getElementById('registration-form').classList.add('has-role-switcher');
        typeFieldset.classList.add('has-role-switcher');
        switcher.hidden = false;
        track.setAttribute('data-active-role', role);
        heading.textContent = role === 'doctor' ? 'Doctor account' : 'Patient account';
        track.querySelectorAll('[data-role-fields]').forEach(function (fieldset) {
            fieldset.hidden = false;
            fieldset.setAttribute('aria-hidden', fieldset.getAttribute('data-role-fields') !== role ? 'true' : 'false');
        });
    }
    document.querySelectorAll('[data-role-fields]').forEach(function (fieldset) {
        var active = fieldset.getAttribute('data-role-fields') === role;
        if (!track) {
            fieldset.hidden = !active;
        }
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
    form.querySelectorAll('[data-role-switch]').forEach(function (button) {
        button.addEventListener('click', function () {
            var role = button.getAttribute('data-role-switch');
            var radio = form.querySelector('[name="role"][value="' + role + '"]');
            if (radio) {
                radio.checked = true;
                setRegistrationRole(role);
            }
        });
    });
    var roleFieldset = document.getElementById('registration-type');
    if (roleFieldset) {
        roleFieldset.addEventListener('keydown', function (event) {
            if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') {
                return;
            }
            var role = event.key === 'ArrowRight' ? 'doctor' : 'patient';
            var radio = form.querySelector('[name="role"][value="' + role + '"]');
            if (radio) {
                event.preventDefault();
                radio.checked = true;
                setRegistrationRole(role);
            }
        });
    }
    var selected = form.querySelector('[name="role"]:checked');
    setRegistrationRole(selected ? selected.value : 'patient');
    form.addEventListener('submit', validateRegistrationForm);
}

document.addEventListener('DOMContentLoaded', initialiseRegistrationForm);
