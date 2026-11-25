import Swiper from 'swiper';
import { A11y, Autoplay, Keyboard, Navigation, Pagination } from 'swiper/modules';
import 'swiper/css';
import 'swiper/css/a11y';
import 'swiper/css/navigation';
import 'swiper/css/pagination';

document.addEventListener('DOMContentLoaded', () => {
    const adultDialog = document.querySelector('[data-adult-dialog]');

    if (adultDialog instanceof HTMLDialogElement) {
        const intendedInput = adultDialog.querySelector('[data-adult-intended]');
        const passwordInput = adultDialog.querySelector('input[type="password"]');

        const openAdultDialog = (intended = '') => {
            if (intendedInput instanceof HTMLInputElement) {
                intendedInput.value = intended;
            }

            if (!adultDialog.open) {
                adultDialog.showModal();
            }

            passwordInput?.focus();
        };

        document.querySelectorAll('[data-adult-gate]').forEach((link) => {
            link.addEventListener('click', (event) => {
                event.preventDefault();
                openAdultDialog(link.dataset.adultIntended ?? '');
            });
        });

        adultDialog.querySelectorAll('[data-adult-dialog-close]').forEach((button) => {
            button.addEventListener('click', () => adultDialog.close());
        });

        adultDialog.addEventListener('click', (event) => {
            if (event.target === adultDialog) {
                adultDialog.close();
            }
        });

        if (adultDialog.hasAttribute('data-adult-gate-open')) {
            openAdultDialog(intendedInput instanceof HTMLInputElement ? intendedInput.value : '');
        }
    }

    document.querySelectorAll('[data-hero-carousel]').forEach((carousel) => {
        const fallback = carousel.parentElement?.querySelector('[data-hero-fallback]');
        const fallbackContent = carousel.parentElement?.querySelector('[data-hero-fallback-content]');
        const controls = carousel.querySelector('[data-hero-controls]');
        const wrapper = carousel.querySelector('.swiper-wrapper');
        let swiper = null;
        let isPointerOverCarousel = carousel.matches(':hover');
        let isFocusWithinCarousel = false;
        let autoplayStoppedForFocus = false;

        if (!(wrapper instanceof HTMLElement)) {
            return;
        }

        const slideCount = () => wrapper.querySelectorAll(':scope > [data-hero-slide]').length;
        const canResumeAutoplay = () => swiper !== null && swiper.params.autoplay !== false && !swiper.destroyed;

        const resumeAutoplayWhenAllowed = () => {
            if (autoplayStoppedForFocus && !isPointerOverCarousel && !isFocusWithinCarousel && canResumeAutoplay()) {
                swiper.autoplay.paused = false;
                swiper.autoplay.start();
                autoplayStoppedForFocus = false;
            }
        };

        const destroySwiper = () => {
            if (swiper === null || swiper.destroyed) {
                return;
            }

            swiper.autoplay?.stop();
            swiper.destroy(true, true);
            swiper = null;
            autoplayStoppedForFocus = false;
        };

        const updateHero = () => {
            destroySwiper();

            const count = slideCount();
            const hasSlides = count > 0;
            carousel.hidden = !hasSlides;

            if (fallback instanceof HTMLElement) {
                fallback.hidden = hasSlides;
            }

            if (fallbackContent instanceof HTMLElement) {
                fallbackContent.hidden = hasSlides;
            }

            if (controls instanceof HTMLElement) {
                controls.hidden = count <= 1;
            }

            if (count <= 1) {
                return;
            }

            swiper = new Swiper(carousel, {
                modules: [A11y, Autoplay, Keyboard, Navigation, Pagination],
                a11y: {
                    enabled: true,
                    containerMessage: '精选媒体轮播',
                    nextSlideMessage: '显示上一部精选媒体',
                    prevSlideMessage: '显示上一部精选媒体',
                    paginationBulletMessage: '显示第 {{index}} 部精选媒体',
                },
                autoplay: {
                    delay: 7000,
                    disableOnInteraction: false,
                    pauseOnMouseEnter: true,
                },
                keyboard: {
                    enabled: true,
                },
                slidesPerView: 1.7,
                spaceBetween: 12,
                centeredSlides: true,
                breakpoints: {
                    640: {
                        slidesPerView: 1,
                        spaceBetween: 0,
                        centeredSlides: false,
                    },
                },
                loop: true,
                navigation: {
                    nextEl: carousel.querySelector('[data-hero-next]'),
                    prevEl: carousel.querySelector('[data-hero-previous]'),
                },
                pagination: {
                    clickable: true,
                    el: carousel.querySelector('[data-hero-pagination]'),
                },
            });
        };

        const removeFailedSlide = (poster) => {
            const slide = poster.closest('[data-hero-slide]');

            if (
                !(slide instanceof HTMLElement)
                || slide.dataset.heroSlideFailed === 'true'
                || slide.parentElement !== wrapper
            ) {
                return;
            }

            slide.dataset.heroSlideFailed = 'true';
            slide.remove();
            updateHero();
        };

        carousel.querySelectorAll('[data-hero-poster]').forEach((poster) => {
            poster.addEventListener('error', () => removeFailedSlide(poster));

            if (poster.dataset.heroPosterFailed === 'true') {
                removeFailedSlide(poster);
            }
        });

        carousel.addEventListener('focusin', () => {
            isFocusWithinCarousel = true;

            if (!autoplayStoppedForFocus && swiper?.autoplay) {
                swiper.autoplay.stop();
                autoplayStoppedForFocus = true;
            }
        });

        carousel.addEventListener('focusout', (event) => {
            if (event.relatedTarget instanceof Node && carousel.contains(event.relatedTarget)) {
                return;
            }

            isFocusWithinCarousel = false;
            resumeAutoplayWhenAllowed();
        });

        carousel.addEventListener('pointerenter', (event) => {
            if (event.pointerType === 'mouse') {
                isPointerOverCarousel = true;
            }
        });

        carousel.addEventListener('pointerleave', (event) => {
            if (event.pointerType === 'mouse') {
                isPointerOverCarousel = false;
                resumeAutoplayWhenAllowed();
            }
        });

        updateHero();
    });
});
