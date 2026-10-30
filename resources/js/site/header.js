/**
 * Mobile navigation and header search (ported from the WordPress child theme).
 */
const hamburger = document.querySelector('.mh-header__hamburger');
const mobileNav = document.getElementById('mh-mobile-nav');
const closeButton = document.querySelector('.mh-mobile-nav__close');
const searchBlocks = Array.from(document.querySelectorAll('.mh-header-search'))
    .map((wrap) => ({
        toggle: wrap.querySelector('.mh-header-search__toggle'),
        form: wrap.querySelector('.mh-header-search__form'),
        input: wrap.querySelector('.mh-header-search__input'),
    }))
    .filter((block) => block.toggle && block.form);

const closeSearch = (targetBlock = null) => {
    searchBlocks.forEach((block) => {
        if (targetBlock && block !== targetBlock) {
            return;
        }
        block.form.classList.remove('is-open');
        block.toggle.setAttribute('aria-expanded', 'false');
    });
};

const openSearch = (targetBlock) => {
    closeSearch();
    targetBlock.form.classList.add('is-open');
    targetBlock.toggle.setAttribute('aria-expanded', 'true');
    if (targetBlock.input) {
        setTimeout(() => targetBlock.input.focus(), 50);
    }
};

searchBlocks.forEach((block) => {
    block.toggle.addEventListener('click', () => {
        if (block.form.classList.contains('is-open')) {
            closeSearch(block);
            return;
        }
        openSearch(block);
    });
});

if (hamburger && mobileNav) {
    const openMenu = () => {
        mobileNav.classList.add('is-open');
        hamburger.classList.add('is-open');
        hamburger.setAttribute('aria-expanded', 'true');
        mobileNav.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    };

    const closeMenu = () => {
        mobileNav.classList.remove('is-open');
        hamburger.classList.remove('is-open');
        hamburger.setAttribute('aria-expanded', 'false');
        mobileNav.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        closeSearch();
    };

    hamburger.addEventListener('click', () => {
        if (mobileNav.classList.contains('is-open')) {
            closeMenu();
        } else {
            openMenu();
        }
    });

    closeButton?.addEventListener('click', closeMenu);

    mobileNav.addEventListener('click', (event) => {
        if (!event.target.closest('.mh-mobile-nav__panel')) {
            closeMenu();
        }
    });

    mobileNav.querySelectorAll('a').forEach((link) => link.addEventListener('click', closeMenu));

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeMenu();
        }
    });

    window.addEventListener('resize', () => {
        if (window.innerWidth > 960) {
            closeMenu();
        }
    });
}
