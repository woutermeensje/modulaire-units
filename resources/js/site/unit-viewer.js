/**
 * "Bekijk in 3D" on unit detail pages: swaps the photos for a rotatable 3D view of the unit's configuration.
 * Three.js is only downloaded once a visitor asks for it.
 */
const container = document.querySelector('[data-unit-viewer]');

if (container) {
    const gallery = document.getElementById('mh-open-gallery');
    const open = document.querySelector('[data-unit-viewer-open]');
    const close = document.querySelector('[data-unit-viewer-close]');
    const hint = document.querySelector('[data-unit-viewer-hint]');
    let viewer = null;

    const showViewer = (isOpen) => {
        container.hidden = !isOpen;
        hint.hidden = !isOpen;
        close.hidden = !isOpen;
        open.hidden = isOpen;
        if (gallery) {
            gallery.hidden = isOpen;
        }
    };

    open.addEventListener('click', async () => {
        if (viewer) {
            showViewer(true);
            return;
        }

        open.disabled = true;

        try {
            const { createUnitViewer } = await import('../unit-viewer');
            const { configuration, dimensions, backdrops } = JSON.parse(container.dataset.unitViewer);
            showViewer(true);
            viewer = createUnitViewer(container, backdrops, { zoom: false, distance: 0.75 });
            viewer.update(configuration, dimensions);
        } catch (error) {
            // No WebGL on this device: keep the photos and drop the button.
            console.error(error);
            showViewer(false);
            open.hidden = true;
        } finally {
            open.disabled = false;
        }
    });

    close.addEventListener('click', () => showViewer(false));
}
