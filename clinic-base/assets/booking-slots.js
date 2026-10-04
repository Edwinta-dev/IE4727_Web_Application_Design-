'use strict';

document.querySelectorAll('.schedule-grid').forEach(function (grid) {
    var confirmations = grid.querySelectorAll('.slot-booking');
    confirmations.forEach(function (confirmation) {
        confirmation.addEventListener('toggle', function () {
            if (!confirmation.open) return;
            confirmations.forEach(function (other) {
                if (other !== confirmation) other.open = false;
            });
        });
    });
});
