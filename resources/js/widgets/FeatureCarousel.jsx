import { useEffect, useState } from 'react';

const DURATION = 6000;

/**
 * Autoplaying product tour: screenshots crossfade inside a fixed 16:10 frame,
 * and each feature title below carries the progress bar that times the slide.
 * Hover, keyboard focus or a hidden tab pauses it; reduced motion disables autoplay.
 */
export default function FeatureCarousel({ slides }) {
    const [active, setActive] = useState(0);
    const [paused, setPaused] = useState(false);
    const [still] = useState(() => window.matchMedia('(prefers-reduced-motion: reduce)').matches);

    useEffect(() => {
        const onVis = () => setPaused(document.hidden);
        document.addEventListener('visibilitychange', onVis);
        return () => document.removeEventListener('visibilitychange', onVis);
    }, []);

    const slide = slides[active];

    return (
        <div onMouseEnter={() => setPaused(true)} onMouseLeave={() => setPaused(false)}
            onFocus={() => setPaused(true)} onBlur={() => setPaused(false)}
            role="region" aria-roledescription="carousel" aria-label="Product tour">
            <div aria-live="polite" className="mb-5 flex min-h-[64px] flex-col justify-end sm:flex-row sm:items-end sm:justify-between sm:gap-8">
                <h3 key={active} className="fc-text font-display text-[clamp(1.4rem,2.4vw,2rem)] font-medium tracking-[-0.015em] text-fg">{slide.title}</h3>
                <p key={`b${active}`} className="fc-text mt-1 max-w-md text-[15px] leading-relaxed text-fg-2 sm:mt-0 sm:text-right">{slide.body}</p>
            </div>

            <figure className="relative m-0 aspect-[16/10] overflow-hidden border border-line bg-white shadow-[0_40px_80px_-40px_rgba(16,37,42,.35)]">
                {slides.map((s, i) => (
                    <img key={s.src} src={s.src} alt={i === active ? s.alt : ''} width="1440" height="900" loading={i === 0 ? 'eager' : 'lazy'} decoding="async"
                        className={`fc-img absolute inset-0 h-full w-full object-cover object-left-top ${i === active ? 'is-on' : ''}`} />
                ))}
            </figure>

            <div className="mt-5 grid grid-cols-2 gap-x-4 sm:grid-cols-4 lg:grid-cols-7" role="tablist" aria-label="Features">
                {slides.map((s, i) => (
                    <button key={s.src} type="button" role="tab" aria-selected={i === active} onClick={() => setActive(i)}
                        className={`m-0 border-0 bg-transparent px-0 py-2 text-left text-[13px] shadow-none font-medium transition-colors duration-200 ${i === active ? 'text-fg' : 'text-fg-3 hover:text-fg-2'}`}>
                        <span className="mb-2 block h-[2px] overflow-hidden bg-line">
                            {i === active && (
                                <span key={active} className={`block h-full bg-brand ${still ? '' : 'fc-bar'}`}
                                    style={{ animationDuration: `${DURATION}ms`, animationPlayState: paused ? 'paused' : 'running' }}
                                    onAnimationEnd={() => setActive((a) => (a + 1) % slides.length)} />
                            )}
                        </span>
                        {s.title}
                    </button>
                ))}
            </div>
        </div>
    );
}
