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
    var selectedTime = booking.querySelector('#selected-time');
    booking.querySelectorAll('.slot-choice').forEach(function (choice) {
        choice.addEventListener('change', function () {
            if (selectedTime && choice.checked) selectedTime.textContent = choice.dataset.time;
        });
    });
});
