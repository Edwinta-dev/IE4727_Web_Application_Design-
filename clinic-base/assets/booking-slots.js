'use strict';

document.querySelectorAll('.slot-booking').forEach(function (booking) {
    var selectedTime = booking.querySelector('#selected-time');
    booking.querySelectorAll('.slot-choice').forEach(function (choice) {
        choice.addEventListener('change', function () {
            if (selectedTime && choice.checked) selectedTime.textContent = choice.dataset.time;
        });
    });
});
