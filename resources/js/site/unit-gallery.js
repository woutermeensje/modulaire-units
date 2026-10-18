/**
 * Image preview slider + lightbox on unit and product detail pages.
 */
const preview = document.getElementById('mh-open-gallery');

if (preview) {
    const previewImages = preview.querySelectorAll('img');
    const dots = document.querySelectorAll('#mh-preview-counter span');
    const previous = document.getElementById('mh-preview-prev');
    const next = document.getElementById('mh-preview-next');
    let current = 0;

    const showSlide = (index) => {
        if (!previewImages.length) {
            return;
        }
        current = (index + previewImages.length) % previewImages.length;
        previewImages.forEach((image, i) => image.classList.toggle('is-active', i === current));
        dots.forEach((dot, i) => dot.classList.toggle('is-active', i === current));
    };

    previous?.addEventListener('click', (event) => {
        event.stopPropagation();
        showSlide(current - 1);
    });
    next?.addEventListener('click', (event) => {
        event.stopPropagation();
        showSlide(current + 1);
    });

    const lightbox = document.getElementById('mh-lightbox');
    const strip = document.getElementById('mh-lightbox-strip');

    if (lightbox && strip) {
        const lightboxImages = strip.querySelectorAll('img');
        let lightboxCurrent = 0;

        const scrollTo = (index) => {
            if (!lightboxImages.length) {
                return;
            }
            lightboxCurrent = Math.max(0, Math.min(lightboxImages.length - 1, index));
            lightboxImages[lightboxCurrent].scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
        };

        const close = () => {
            lightbox.classList.remove('is-open');
            document.body.style.overflow = '';
        };

        previewImages.forEach((image) => {
            image.style.cursor = 'zoom-in';
            image.addEventListener('click', () => {
                lightbox.classList.add('is-open');
                document.body.style.overflow = 'hidden';
                scrollTo(current);
            });
        });

        document.getElementById('mh-lightbox-close')?.addEventListener('click', close);
        document.getElementById('mh-arrow-prev')?.addEventListener('click', () => scrollTo(lightboxCurrent - 1));
        document.getElementById('mh-arrow-next')?.addEventListener('click', () => scrollTo(lightboxCurrent + 1));
        lightbox.addEventListener('click', (event) => {
            if (event.target === lightbox) {
                close();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (!lightbox.classList.contains('is-open')) {
                return;
            }
            if (event.key === 'Escape') {
                close();
            }
            if (event.key === 'ArrowLeft') {
                scrollTo(lightboxCurrent - 1);
            }
            if (event.key === 'ArrowRight') {
                scrollTo(lightboxCurrent + 1);
            }
        });
    }
}
