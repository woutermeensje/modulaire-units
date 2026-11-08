/**
 * The 3D prototype of a configured unit (partials/unit-3d-view) on the unit cards of /modulaire-units/ and on the unit
 * detail page: a rotatable 3D view that visitors turn with the mouse, switchable to its floor plan or a look inside.
 * Three.js is only downloaded when a card comes into view; until then, and on devices without WebGL, the 3D photo
 * stays in place.
 */
const cards = document.querySelectorAll('[data-unit-card]');

if (cards.length > 0) {
    let viewerModule = null;

    const setUp = (card) => {
        const container = card.querySelector('[data-unit-card-viewer]');
        const plan = card.querySelector('[data-unit-card-plan]');
        const buttons = card.querySelectorAll('[data-unit-card-view]');
        const { configuration, dimensions, backdrops } = JSON.parse(container.dataset.unitCardViewer);
        let viewer = null;
        let currentView = '3d';

        const start = async () => {
            try {
                viewerModule ??= import('../unit-viewer');
                const { createUnitViewer } = await viewerModule;
                viewer = createUnitViewer(container, backdrops, { zoom: false, distance: 1 });
                viewer.update(configuration, dimensions, { interior: currentView === 'interior' });
                container.querySelector('[data-unit-card-viewer-poster]')?.remove();
                container.classList.add('is-ready');
            } catch (error) {
                // No WebGL on this device: keep the photo and the floor plan, without the look inside.
                console.error(error);
                container.querySelector('canvas')?.remove();
                card.querySelector('[data-unit-card-view="interior"]')?.remove();
            }
        };

        const showView = (view) => {
            currentView = view;
            buttons.forEach((button) => {
                const isActive = button.dataset.unitCardView === view;
                button.classList.toggle('is-active', isActive);
                button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            });
            plan.hidden = view !== '2d';
            container.hidden = view === '2d';
            if (view !== '2d' && viewer) {
                viewer.update(configuration, dimensions, { interior: view === 'interior' });
            }
        };

        buttons.forEach((button) => button.addEventListener('click', () => showView(button.dataset.unitCardView)));

        return start;
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                observer.unobserve(entry.target);
                setUp(entry.target)();
            }
        });
    }, { rootMargin: '200px' });

    cards.forEach((card) => observer.observe(card));
}
