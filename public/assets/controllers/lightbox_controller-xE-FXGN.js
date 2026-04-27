import { Controller } from '@hotwired/stimulus';

/**
 * Lightbox controller — fullscreen product photo slideshow.
 *
 * Usage:
 *   <div data-controller="lightbox">
 *     <img data-lightbox-target="photo" data-action="click->lightbox#open" src="..." />
 *   </div>
 */
export default class extends Controller {
    static targets = ['photo'];

    open(event) {
        const el = event.currentTarget;
        const photos = this.photoTargets.map((img) => img.src);
        if (photos.length === 0) return;

        // Find the clicked image src — either the element itself or its child <img>
        const img = el.tagName === 'IMG' ? el : el.querySelector('img');
        const clickedSrc = img ? img.src : null;
        const startIndex = clickedSrc ? photos.indexOf(clickedSrc) : 0;
        this._createOverlay(photos, startIndex >= 0 ? startIndex : 0);
    }

    _createOverlay(photos, index) {
        this.current = index;
        this.photos = photos;

        // Overlay
        const overlay = document.createElement('div');
        overlay.className = 'alma-lightbox';
        overlay.innerHTML = `
            <div class="alma-lightbox__backdrop"></div>
            <button class="alma-lightbox__close" aria-label="Fermer">
                <svg width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
            <div class="alma-lightbox__stage">
                <img class="alma-lightbox__img" src="${photos[index]}" alt="" />
            </div>
            ${photos.length > 1 ? `
                <button class="alma-lightbox__nav alma-lightbox__nav--prev" aria-label="Précédent">
                    <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/>
                    </svg>
                </button>
                <button class="alma-lightbox__nav alma-lightbox__nav--next" aria-label="Suivant">
                    <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
                    </svg>
                </button>
                <div class="alma-lightbox__dots">
                    ${photos.map((_, i) => `<button class="alma-lightbox__dot ${i === index ? 'alma-lightbox__dot--active' : ''}" data-index="${i}" aria-label="Photo ${i + 1}"></button>`).join('')}
                </div>
            ` : ''}
        `;

        document.body.appendChild(overlay);
        document.body.style.overflow = 'hidden';

        // Animate in
        requestAnimationFrame(() => overlay.classList.add('alma-lightbox--visible'));

        this.overlay = overlay;
        this.imgEl = overlay.querySelector('.alma-lightbox__img');

        // Events
        overlay.querySelector('.alma-lightbox__close').addEventListener('click', () => this._close());
        overlay.querySelector('.alma-lightbox__backdrop').addEventListener('click', () => this._close());

        // The stage sits above the backdrop and absorbs clicks in its padding zone,
        // so close when the click lands on the stage itself (i.e. outside the image).
        const stage = overlay.querySelector('.alma-lightbox__stage');
        stage.addEventListener('click', (e) => {
            if (e.target === stage) this._close();
        });

        if (photos.length > 1) {
            overlay.querySelector('.alma-lightbox__nav--prev').addEventListener('click', () => this._go(-1));
            overlay.querySelector('.alma-lightbox__nav--next').addEventListener('click', () => this._go(1));
            overlay.querySelectorAll('.alma-lightbox__dot').forEach((dot) => {
                dot.addEventListener('click', () => this._goTo(parseInt(dot.dataset.index, 10)));
            });
        }

        this._keyHandler = (e) => {
            if (e.key === 'Escape') this._close();
            if (e.key === 'ArrowLeft') this._go(-1);
            if (e.key === 'ArrowRight') this._go(1);
        };
        document.addEventListener('keydown', this._keyHandler);

        // Swipe support
        this._initSwipe(overlay.querySelector('.alma-lightbox__stage'));
    }

    _go(direction) {
        if (this.photos.length <= 1) return;
        this.current = (this.current + direction + this.photos.length) % this.photos.length;
        this._updateImage(direction);
    }

    _goTo(index) {
        const direction = index > this.current ? 1 : -1;
        this.current = index;
        this._updateImage(direction);
    }

    _updateImage(direction) {
        const img = this.imgEl;

        // Slide out
        img.classList.add(direction > 0 ? 'alma-lightbox__img--exit-left' : 'alma-lightbox__img--exit-right');

        setTimeout(() => {
            img.src = this.photos[this.current];
            img.classList.remove('alma-lightbox__img--exit-left', 'alma-lightbox__img--exit-right');
            img.classList.add(direction > 0 ? 'alma-lightbox__img--enter-right' : 'alma-lightbox__img--enter-left');

            setTimeout(() => {
                img.classList.remove('alma-lightbox__img--enter-right', 'alma-lightbox__img--enter-left');
            }, 300);
        }, 200);

        // Update dots
        this.overlay.querySelectorAll('.alma-lightbox__dot').forEach((dot, i) => {
            dot.classList.toggle('alma-lightbox__dot--active', i === this.current);
        });
    }

    _close() {
        this.overlay.classList.remove('alma-lightbox--visible');
        document.removeEventListener('keydown', this._keyHandler);

        setTimeout(() => {
            this.overlay.remove();
            document.body.style.overflow = '';
        }, 300);
    }

    _initSwipe(el) {
        let startX = 0;
        let startY = 0;

        el.addEventListener('touchstart', (e) => {
            startX = e.touches[0].clientX;
            startY = e.touches[0].clientY;
        }, { passive: true });

        el.addEventListener('touchend', (e) => {
            const dx = e.changedTouches[0].clientX - startX;
            const dy = e.changedTouches[0].clientY - startY;
            if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy)) {
                this._go(dx < 0 ? 1 : -1);
            }
        }, { passive: true });
    }
}
