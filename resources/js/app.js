import Swiper from 'swiper';
import { A11y, Autoplay, Keyboard, Navigation, Pagination } from 'swiper/modules';
import 'swiper/css';
import 'swiper/css/a11y';
import 'swiper/css/navigation';
import 'swiper/css/pagination';

document.addEventListener('DOMContentLoaded', () => {
    const detailDialog = document.querySelector('[data-torrent-detail-dialog]');

    if (detailDialog instanceof HTMLDialogElement) {
        const title = detailDialog.querySelector('[data-torrent-detail-title]');
        const name = detailDialog.querySelector('[data-torrent-detail-name]');
        const description = detailDialog.querySelector('[data-torrent-detail-description]');
        const loading = detailDialog.querySelector('[data-torrent-detail-loading]');
        const error = detailDialog.querySelector('[data-torrent-detail-error]');
        const content = detailDialog.querySelector('[data-torrent-detail-content]');
        let requestController = null;

        const setHidden = (element, hidden) => {
            if (element instanceof HTMLElement) {
                element.hidden = hidden;
                element.classList.toggle('hidden', hidden);
            }
        };

        const httpUrl = (value) => {
            try {
                const url = new URL(value.trim());

                return ['http:', 'https:'].includes(url.protocol) ? url.href : null;
            } catch {
                return null;
            }
        };

        const renderDescription = (value) => {
            if (!(description instanceof HTMLElement)) {
                return;
            }

            const text = typeof value === 'string' && value !== '' ? value : '暂无影片简介。';
            const tokenPattern = /\[!\[([^\]]*)\]\(([^)\s]+)\)\]\(([^)\s]+)\)|!\[([^\]]*)\]\(([^)\s]+)\)|\[([^\]]+)\]\(([^)\s]+)\)|\[\/?(?:b|i|u|quote|code|center|color(?:=[^\]]+)?|size(?:=[^\]]+)?|font(?:=[^\]]+)?|url(?:=[^\]]+)?|img)\]/gi;
            const safeColors = {
                blue: '#60a5fa',
                green: '#4ade80',
                red: '#f87171',
                yellow: '#facc15',
                orange: '#fb923c',
            };
            const stack = [{ tag: 'root', container: description }];
            let lastIndex = 0;

            const current = () => stack.at(-1);
            const appendText = (plainText) => {
                const entry = current();

                if (entry?.tag === 'img' || entry?.tag === 'url-auto') {
                    entry.value += plainText;

                    return;
                }

                entry?.container.append(document.createTextNode(plainText));
            };
            const appendImage = (container, source, alt = '详情图片', linkUrl = null) => {
                const imageUrl = httpUrl(source);

                if (imageUrl === null) {
                    return false;
                }

                const image = document.createElement('img');

                image.src = imageUrl;
                image.alt = alt;
                image.loading = 'lazy';
                image.referrerPolicy = 'no-referrer';
                image.className = 'my-3 block max-h-[32rem] max-w-full object-contain';

                if (linkUrl !== null) {
                    const link = document.createElement('a');

                    link.href = linkUrl;
                    link.target = '_blank';
                    link.rel = 'noopener noreferrer';
                    link.append(image);
                    container.append(link);
                } else {
                    container.append(image);
                }

                return true;
            };
            const appendLink = (container, destination, label) => {
                const url = httpUrl(destination);

                if (url === null) {
                    return false;
                }

                const link = document.createElement('a');

                link.href = url;
                link.target = '_blank';
                link.rel = 'noopener noreferrer';
                link.className = 'font-semibold text-sky-300 underline decoration-sky-300/50 underline-offset-2 transition hover:text-sky-200 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-300';
                link.textContent = label.trim() || url;
                container.append(link);

                return true;
            };

            description.replaceChildren();

            let match;

            while ((match = tokenPattern.exec(text)) !== null) {
                appendText(text.slice(lastIndex, match.index));
                const [tag, linkedImageAlt, linkedImageSource, linkedImageUrl, imageAlt, imageSource, linkLabel, linkUrl] = match;
                const container = current()?.container;

                if (!(container instanceof HTMLElement)) {
                    appendText(tag);
                } else if (tag.startsWith('[![')) {
                    if (!appendImage(container, linkedImageSource, linkedImageAlt, httpUrl(linkedImageUrl))) {
                        appendText(tag);
                    }
                } else if (tag.startsWith('![')) {
                    if (!appendImage(container, imageSource, imageAlt)) {
                        appendText(tag);
                    }
                } else if (tag.startsWith('[') && tag.includes('](')) {
                    if (!appendLink(container, linkUrl, linkLabel)) {
                        appendText(tag);
                    }
                } else {
                    const closing = /^\[\/([a-z]+)\]$/i.exec(tag);
                    const opening = /^\[([a-z]+)(?:=([^\]]+))?\]$/i.exec(tag);

                    if (closing !== null) {
                        const tagName = closing[1].toLowerCase();
                        const entry = current();

                        if (entry?.tag !== tagName && !(tagName === 'url' && entry?.tag === 'url-auto')) {
                            appendText(tag);
                        } else {
                            stack.pop();

                            if (tagName === 'img') {
                                if (!appendImage(current().container, entry.value)) {
                                    current().container.append(document.createTextNode(tag));
                                }
                            } else if (entry.tag === 'url-auto') {
                                if (!appendLink(current().container, entry.value, entry.value)) {
                                    current().container.append(document.createTextNode(entry.value));
                                }
                            }
                        }
                    } else if (opening !== null) {
                        const [, rawTagName, attribute] = opening;
                        const tagName = rawTagName.toLowerCase();
                        const parent = current().container;

                        if (current().tag === 'code') {
                            appendText(tag);
                        } else if (tagName === 'img') {
                            stack.push({ tag: 'img', container: parent, value: '' });
                        } else if (tagName === 'url' && attribute === undefined) {
                            stack.push({ tag: 'url-auto', container: parent, value: '' });
                        } else {
                            let element;
                            let childContainer;

                            if (tagName === 'b') {
                                element = document.createElement('strong');
                            } else if (tagName === 'i') {
                                element = document.createElement('em');
                            } else if (tagName === 'u') {
                                element = document.createElement('span');
                                element.className = 'underline';
                            } else if (tagName === 'quote') {
                                element = document.createElement('blockquote');
                                element.className = 'my-3 border-l-2 border-sky-300/60 pl-4 text-zinc-300';
                            } else if (tagName === 'code') {
                                element = document.createElement('pre');
                                element.className = 'my-3 overflow-x-auto border border-white/10 bg-black/20 p-3 text-xs leading-5 text-zinc-300';
                                childContainer = document.createElement('code');
                                element.append(childContainer);
                            } else if (tagName === 'center') {
                                element = document.createElement('div');
                                element.className = 'text-center';
                            } else if (tagName === 'color') {
                                element = document.createElement('span');
                                const color = attribute?.trim().toLowerCase();

                                if (color !== undefined && (safeColors[color] !== undefined || /^#[\da-f]{3}(?:[\da-f]{3})?$/i.test(color))) {
                                    element.style.color = safeColors[color] ?? color;
                                }
                            } else if (tagName === 'url') {
                                const url = attribute === undefined ? null : httpUrl(attribute);

                                if (url === null) {
                                    stack.push({ tag: 'url', container: parent });

                                    lastIndex = tokenPattern.lastIndex;

                                    continue;
                                }

                                element = document.createElement('a');
                                element.href = url;
                                element.target = '_blank';
                                element.rel = 'noopener noreferrer';
                                element.className = 'font-semibold text-sky-300 underline decoration-sky-300/50 underline-offset-2 transition hover:text-sky-200 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-300';
                            } else {
                                stack.push({ tag: tagName, container: parent });

                                lastIndex = tokenPattern.lastIndex;

                                continue;
                            }

                            parent.append(element);
                            stack.push({ tag: tagName, container: childContainer ?? element });
                        }
                    } else {
                        appendText(tag);
                    }
                }

                lastIndex = tokenPattern.lastIndex;
            }

            appendText(text.slice(lastIndex));
        };

        const renderDetail = (detail) => {
            if (title instanceof HTMLElement) {
                title.textContent = detail.title ?? '未命名资源';
            }

            if (name instanceof HTMLElement) {
                name.textContent = detail.name ?? '';
                name.hidden = name.textContent === '';
            }

            renderDescription(detail.description);

        };

        const closeDetail = () => {
            requestController?.abort();
            requestController = null;
            detailDialog.close();
        };

        detailDialog.querySelectorAll('[data-torrent-detail-close]').forEach((button) => {
            button.addEventListener('click', closeDetail);
        });

        detailDialog.addEventListener('click', (event) => {
            if (event.target === detailDialog) {
                closeDetail();
            }
        });

        document.querySelectorAll('[data-torrent-detail-trigger]').forEach((trigger) => {
            trigger.addEventListener('click', async () => {
                const url = trigger.dataset.torrentDetailUrl;

                if (!url) {
                    return;
                }

                requestController?.abort();
                const controller = new AbortController();
                requestController = controller;
                setHidden(loading, false);
                setHidden(error, true);
                setHidden(content, true);

                if (title instanceof HTMLElement) {
                    title.textContent = '加载详情中…';
                }

                if (name instanceof HTMLElement) {
                    name.textContent = '';
                    name.hidden = true;
                }

                if (!detailDialog.open) {
                    detailDialog.showModal();
                }

                try {
                    const response = await fetch(url, {
                        headers: { Accept: 'application/json' },
                        signal: controller.signal,
                    });
                    const detail = await response.json();

                    if (!response.ok) {
                        throw new Error(detail.message ?? '获取影片详情失败，请稍后重试。');
                    }

                    renderDetail(detail);
                    setHidden(content, false);
                } catch (requestError) {
                    if (requestError.name === 'AbortError') {
                        return;
                    }

                    if (error instanceof HTMLElement) {
                        error.textContent = requestError.message || '获取影片详情失败，请稍后重试。';
                    }

                    setHidden(error, false);
                } finally {
                    if (requestController === controller) {
                        setHidden(loading, true);
                    }
                }
            });
        });
    }

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
