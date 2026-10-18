/**
 * +/- buttons next to quantity inputs (unit pages and "Mijn offerte").
 */
document.querySelectorAll('.mh-qty-stepper, [data-stepper]').forEach((stepper) => {
    const input = stepper.querySelector('input[type="number"]');
    if (!input) {
        return;
    }

    const step = (delta) => {
        const value = parseInt(input.value, 10) || 1;
        input.value = Math.max(1, value + delta);
    };

    stepper.querySelector('.mh-qty-btn--minus, .mh-qp__qty-btn--minus')?.addEventListener('click', () => step(-1));
    stepper.querySelector('.mh-qty-btn--plus, .mh-qp__qty-btn--plus')?.addEventListener('click', () => step(1));
});
