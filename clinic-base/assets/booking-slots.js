'use strict';

document.querySelectorAll('.booking-filters').forEach(function (form) {
    var specialty = form.querySelector('#specialty');
    var doctor = form.querySelector('#doctor');
    function applyFilters() {
        form.requestSubmit();
    }
    specialty.addEventListener('change', function () {
        doctor.value = '';
        applyFilters();
    });
    doctor.addEventListener('change', function () {
        var option = doctor.selectedOptions[0];
        if (option && option.dataset.specialty) specialty.value = option.dataset.specialty;
        applyFilters();
    });
    form.querySelectorAll('input[type="time"]').forEach(function (input) {
        input.addEventListener('change', applyFilters);
    });
});

document.querySelectorAll('.booking-days').forEach(function (days) {
    var tabs = days.querySelector('.day-tabs');
    var arrows = days.querySelectorAll('.day-scroll');
    function updateArrows() {
        arrows.forEach(function (arrow) {
            arrow.hidden = tabs.scrollWidth <= tabs.clientWidth;
            arrow.disabled = arrow.dataset.direction === '-1'
                ? tabs.scrollLeft <= 1
                : tabs.scrollLeft + tabs.clientWidth >= tabs.scrollWidth - 1;
        });
    }
    arrows.forEach(function (arrow) {
        arrow.addEventListener('click', function () {
            // Only this week's seven bookable days exist. Move the strip,
            // without animation, rather than linking outside the booking window.
            tabs.scrollLeft = arrow.dataset.direction === '-1' ? 0 : tabs.scrollWidth;
            updateArrows();
        });
    });
    tabs.addEventListener('scroll', updateArrows);
    window.addEventListener('resize', updateArrows);
    updateArrows();
    var selected = tabs.querySelector('.selected');
    if (selected) {
        tabs.scrollLeft = selected.offsetLeft - tabs.firstElementChild.offsetLeft;
        updateArrows();
    }
});

document.querySelectorAll('.slot-booking').forEach(function (booking) {
    var results = booking.closest('.booking-results');
    var doctors = Array.from(results.querySelectorAll('.booking-doctor-option'));
    var choices = Array.from(booking.querySelectorAll('.slot-choice'));
    var selectedTime = booking.querySelector('#selected-time');
    var selectedDoctor = booking.querySelector('#selected-doctor');
    var submit = booking.querySelector('.booking-confirmation button[type="submit"]');
    function alternatives(choice) {
        var listed = Array.from(choice.closest('.slot').querySelectorAll('.slot-doctors [data-doctor-id]'));
        return listed.length ? listed.map(function (node) {
            return { doctor: node.dataset.doctorId, slot: node.dataset.slotId };
        }) : [{ doctor: choice.dataset.doctorId, slot: choice.defaultValue }];
    }
    function refresh() {
        var choice = choices.find(function (item) { return item.checked; });
        var selected = doctors.find(function (item) { return item.querySelector('input').checked; });
        if (choice) {
            var covering = alternatives(choice);
            doctors.forEach(function (item) {
                item.hidden = !covering.some(function (entry) { return entry.doctor === item.dataset.doctorId; });
            });
            if (selected && selected.hidden) {
                selected.querySelector('input').checked = false;
                selected = null;
            }
            if (!selected && covering.length === 1) {
                selected = doctors.find(function (item) { return item.dataset.doctorId === covering[0].doctor; });
                if (selected) selected.querySelector('input').checked = true;
            }
        } else {
            doctors.forEach(function (item) { item.hidden = false; });
        }
        var doctorId = selected ? selected.dataset.doctorId : null;
        choices.forEach(function (item) {
            var covers = alternatives(item).some(function (entry) { return entry.doctor === doctorId; });
            item.closest('.slot').hidden = !choice && doctorId !== null && !covers;
        });
        if (choice && selected) {
            var matched = alternatives(choice).find(function (entry) { return entry.doctor === doctorId; });
            if (matched) choice.value = matched.slot;
        }
        if (selectedTime) selectedTime.textContent = choice ? choice.dataset.time : 'Time selected above';
        if (selectedDoctor) selectedDoctor.textContent = selected ? selected.querySelector('strong').textContent : 'Choose a doctor';
        if (submit) submit.disabled = !(choice && selected);
    }
    choices.forEach(function (choice) {
        choice.addEventListener('change', refresh);
    });
    doctors.forEach(function (doctor) {
        doctor.querySelector('input').addEventListener('change', refresh);
    });
    refresh();
});
