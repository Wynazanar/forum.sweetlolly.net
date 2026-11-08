<?php
namespace App\Components;

class Slider
{
    private array $images;
    private string $class;
    private string $id;

    private static int $counter = 0;

    public function __construct(
        array $images,
        string $class = 'photo-carousel'
    ) {
        $this->images = array_values($images);
        $this->class  = $class;
        $this->id     = 'carousel-' . (++self::$counter) . '-' . bin2hex(random_bytes(3));
    }

    public function render(): string
    {
        if (empty($this->images)) {
            return '';
        }

        $total = count($this->images);
        $slidesHtml = '';
        $dotsHtml   = '';
        $lightboxSlides = '';

        foreach ($this->images as $index => $src) {
            $src = htmlspecialchars($src, ENT_QUOTES, 'UTF-8');

            $slidesHtml .= sprintf(
                '<div class="slide" data-index="%d">
                    <img src="%s" alt="Фото %d" loading="lazy" draggable="false">
                </div>',
                $index,
                $src,
                $index + 1
            );

            $dotsHtml .= sprintf(
                '<button class="dot%s" type="button" data-index="%d" aria-label="Фото %d"></button>',
                $index === 0 ? ' is-active' : '',
                $index,
                $index + 1
            );

            // Слайды для лайтбокса
            $lightboxSlides .= sprintf(
                '<div class="lightbox-slide" data-index="%d">
                    <img src="%s" alt="Фото %d">
                </div>',
                $index,
                $src,
                $index + 1
            );
        }

        $html = sprintf(
            '<div class="%s" id="%s" data-total="%d">
                <div class="carousel-viewport">
                    <div class="carousel-track">
                        %s
                    </div>
                </div>

                <button class="nav-btn prev" type="button" aria-label="Предыдущее">‹</button>
                <button class="nav-btn next" type="button" aria-label="Следующее">›</button>

                <div class="carousel-footer">
                    <div class="dots">%s</div>
                </div>

                <!-- Лайтбокс -->
                <div class="lightbox" aria-hidden="true">
                    <button class="lightbox-close" type="button" aria-label="Закрыть">×</button>
                    <button class="lightbox-prev" type="button" aria-label="Предыдущее">‹</button>
                    <button class="lightbox-next" type="button" aria-label="Следующее">›</button>

                    <div class="lightbox-content">
                        %s
                    </div>

                    <div class="lightbox-counter">
                        <span class="lightbox-current">1</span> / %d
                    </div>
                </div>
            </div>',
            htmlspecialchars($this->class, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($this->id, ENT_QUOTES, 'UTF-8'),
            $total,
            $slidesHtml,
            $dotsHtml,
            $lightboxSlides,
            $total
        );

        $html .= sprintf(<<<'JS'
        <script>
        (function() {
            const root = document.getElementById("%s");
            if (!root) return;

            const viewport   = root.querySelector(".carousel-viewport");
            const track      = root.querySelector(".carousel-track");
            const slides     = Array.from(root.querySelectorAll(".slide"));
            const dots       = Array.from(root.querySelectorAll(".dot"));
            const prevBtn    = root.querySelector(".prev");
            const nextBtn    = root.querySelector(".next");

            // Лайтбокс
            const lightbox        = root.querySelector(".lightbox");
            const lightboxSlides  = Array.from(root.querySelectorAll(".lightbox-slide"));
            const lightboxClose   = root.querySelector(".lightbox-close");
            const lightboxPrev    = root.querySelector(".lightbox-prev");
            const lightboxNext    = root.querySelector(".lightbox-next");
            const lightboxCurrent = root.querySelector(".lightbox-current");

            const total = slides.length;
            let current = 0;
            let isLightboxOpen = false;

            // Для различения клика и свайпа
            let startX = 0;
            let startY = 0;
            let isDragging = false;
            let hasMoved = false;
            let pointerOnSlide = false; // был ли pointerdown именно на фото

            function normalizeDiff(diff) {
                const half = Math.floor(total / 2);
                if (diff > half)  diff -= total;
                if (diff < -half) diff += total;
                return diff;
            }

            function getMetrics() {
                const slide = slides[0];
                if (!slide) return { width: 0, height: 0, step: 0 };
                const style = getComputedStyle(slide);
                const width = slide.offsetWidth;
                const margin = parseFloat(style.marginLeft) + parseFloat(style.marginRight);
                return {
                    width,
                    height: slide.offsetHeight,
                    step: width + margin
                };
            }

            function updateCarousel() {
                const { width, height, step } = getMetrics();
                if (height > 0) {
                    viewport.style.height = height + "px";
                }

                slides.forEach((slide, i) => {
                    const diff = normalizeDiff(i - current);
                    const x = diff * step;
                    const scale = diff === 0 ? 1 : 0.72;
                    const opacity = Math.abs(diff) > 1 ? 0 : (diff === 0 ? 1 : 0.45);
                    const brightness = diff === 0 ? 1 : 0.7;
                    const z = 20 - Math.abs(diff);

                    slide.style.transform = `translateX(${x}px) scale(${scale})`;
                    slide.style.opacity = opacity;
                    slide.style.filter = `brightness(${brightness})`;
                    slide.style.zIndex = z;
                    slide.classList.toggle("is-center", diff === 0);
                });

                dots.forEach((dot, i) => {
                    dot.classList.toggle("is-active", i === current);
                });

                const centerOffset = (viewport.offsetWidth - width) / 2;
                track.style.transform = `translateX(${centerOffset}px)`;
            }

            function updateLightbox() {
                lightboxSlides.forEach((slide, i) => {
                    slide.classList.toggle("is-active", i === current);
                });
                lightboxCurrent.textContent = current + 1;
            }

            function goTo(index) {
                current = (index + total) %% total;
                updateCarousel();
                if (isLightboxOpen) {
                    updateLightbox();
                }
            }

            // ===== Лайтбокс =====
            function openLightbox(index) {
                current = index;
                isLightboxOpen = true;
                lightbox.classList.add("is-open");
                lightbox.setAttribute("aria-hidden", "false");
                document.body.style.overflow = "hidden";
                updateLightbox();
                updateCarousel();
            }

            function closeLightbox() {
                isLightboxOpen = false;
                lightbox.classList.remove("is-open");
                lightbox.setAttribute("aria-hidden", "true");
                document.body.style.overflow = "";
            }

            // ===== Навигация =====
            prevBtn.addEventListener("click", (e) => {
                e.stopPropagation();
                goTo(current - 1);
            });
            nextBtn.addEventListener("click", (e) => {
                e.stopPropagation();
                goTo(current + 1);
            });

            dots.forEach(dot => {
                dot.addEventListener("click", (e) => {
                    e.stopPropagation();
                    const index = parseInt(dot.dataset.index, 10);
                    goTo(index);
                });
            });

            // Лайтбокс — кнопки
            lightboxClose.addEventListener("click", (e) => {
                e.stopPropagation();
                closeLightbox();
            });
            lightboxPrev.addEventListener("click", (e) => {
                e.stopPropagation();
                goTo(current - 1);
            });
            lightboxNext.addEventListener("click", (e) => {
                e.stopPropagation();
                goTo(current + 1);
            });

            // Клик по тёмному фону → закрыть
            lightbox.addEventListener("click", (e) => {
                if (e.target === lightbox) {
                    closeLightbox();
                }
            });

            // Клавиши
            document.addEventListener("keydown", (e) => {
                if (!isLightboxOpen) return;
                if (e.key === "Escape") closeLightbox();
                if (e.key === "ArrowLeft") goTo(current - 1);
                if (e.key === "ArrowRight") goTo(current + 1);
            });

            // ===== Свайп + клик только по фото =====
            track.addEventListener("pointerdown", e => {
                // Игнорируем кнопки
                if (e.target.closest(".nav-btn") || e.target.closest(".dot")) return;

                // Важно: открываем только если кликнули именно по .slide
                const slideEl = e.target.closest(".slide");
                pointerOnSlide = !!slideEl;

                isDragging = true;
                hasMoved = false;
                startX = e.clientX;
                startY = e.clientY;
                track.setPointerCapture(e.pointerId);
                slides.forEach(s => s.style.transition = "none");
            });

            track.addEventListener("pointermove", e => {
                if (!isDragging) return;

                const diffX = e.clientX - startX;
                const diffY = e.clientY - startY;

                if (Math.abs(diffX) > 8 || Math.abs(diffY) > 8) {
                    hasMoved = true;
                }

                const { step } = getMetrics();
                slides.forEach((slide, i) => {
                    const d = normalizeDiff(i - current);
                    const scale = d === 0 ? 1 : 0.72;
                    slide.style.transform = `translateX(${d * step + diffX}px) scale(${scale})`;
                });
            });

            track.addEventListener("pointerup", e => {
                if (!isDragging) return;
                isDragging = false;
                slides.forEach(s => s.style.transition = "");

                const diffX = e.clientX - startX;

                if (hasMoved && Math.abs(diffX) > 50) {
                    // Свайп
                    goTo(current + (diffX < 0 ? 1 : -1));
                } else if (!hasMoved && pointerOnSlide) {
                    // Обычный клик именно по фото → открываем лайтбокс
                    openLightbox(current);
                } else {
                    // Клик мимо фото или маленькое движение
                    updateCarousel();
                }

                pointerOnSlide = false;
            });

            track.addEventListener("pointercancel", () => {
                isDragging = false;
                hasMoved = false;
                pointerOnSlide = false;
                slides.forEach(s => s.style.transition = "");
                updateCarousel();
            });

            // Дополнительно: клик по боковым слайдам
            slides.forEach((slide, i) => {
                slide.addEventListener("click", (e) => {
                    if (hasMoved) return;

                    e.stopPropagation();
                    if (i !== current) {
                        goTo(i);
                        setTimeout(() => openLightbox(i), 200);
                    } else {
                        openLightbox(i);
                    }
                });
            });

            window.addEventListener("resize", updateCarousel);

            function tryUpdate() {
                if (slides[0] && slides[0].offsetHeight > 0) {
                    updateCarousel();
                } else {
                    setTimeout(tryUpdate, 40);
                }
            }

            tryUpdate();
            window.addEventListener("load", updateCarousel);
        })();
        </script>
        JS, $this->id);

        return $html;
    }

    public function __toString(): string
    {
        return $this->render();
    }

    public static function make(...$args): self
    {
        return new self(...$args);
    }
}