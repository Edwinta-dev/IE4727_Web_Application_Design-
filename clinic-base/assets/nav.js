(function () {
    var toggle = document.querySelector('.nav-toggle');
    var navigation = document.getElementById('site-navigation');
    if (!toggle || !navigation) return;

    var phone = window.matchMedia('(max-width: 42rem)');
    function setOpen(open) {
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        navigation.hidden = phone.matches && !open;
    }
    function syncViewport() {
        toggle.hidden = !phone.matches;
        setOpen(false);
    }
    toggle.addEventListener('click', function () {
        setOpen(toggle.getAttribute('aria-expanded') !== 'true');
    });
    navigation.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && phone.matches) {
            setOpen(false);
            toggle.focus();
        }
    });
    phone.addEventListener('change', syncViewport);
    syncViewport();
}());
