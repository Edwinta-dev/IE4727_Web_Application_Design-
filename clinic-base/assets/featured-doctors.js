/* Shared paging for server-rendered doctor and specialty scroll-snap rows. */
document.querySelectorAll('[data-doctor-carousel]').forEach(function (carousel) {
    var track = carousel.querySelector('.doctor-tiles, .specialty-strip');
    var tiles = Array.from(track.querySelectorAll('.doctor-tile, .specialty-item'));
    if (!tiles.length) return;
    var previous = carousel.querySelector('[data-doctor-previous]');
    var next = carousel.querySelector('[data-doctor-next]');
    var status = carousel.querySelector('[data-doctor-status]');
    var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    carousel.classList.add('has-doctor-carousel');
    previous.hidden = next.hidden = false;

    function pageSize() {
        return Number(getComputedStyle(track).getPropertyValue('--doctors-per-page'));
    }
    function update() {
        var maximum = track.scrollWidth - track.clientWidth;
        previous.disabled = track.scrollLeft <= 1;
        next.disabled = track.scrollLeft >= maximum - 1;
        var step = tiles.length > 1 ? tiles[1].offsetLeft - tiles[0].offsetLeft : tiles[0].offsetWidth;
        var first = Math.round(track.scrollLeft / step);
        status.textContent = (carousel.dataset.carouselLabel || 'Doctors') + ' ' + (first + 1) + '–' + Math.min(tiles.length, first + pageSize()) + ' of ' + tiles.length;
    }
    function move(direction) {
        var step = tiles.length > 1 ? tiles[1].offsetLeft - tiles[0].offsetLeft : tiles[0].offsetWidth;
        track.scrollTo({ left: track.scrollLeft + direction * pageSize() * step,
            behavior: reducedMotion.matches ? 'instant' : 'smooth' });
    }
    previous.addEventListener('click', function () { move(-1); });
    next.addEventListener('click', function () { move(1); });
    track.addEventListener('scroll', update);
    window.addEventListener('resize', update);
    update();
});
