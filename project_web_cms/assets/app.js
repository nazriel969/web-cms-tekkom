/**
 * app.js — Enhanced JS untuk semua halaman publik
 */

// ============================================================
// DARK MODE (apply sebelum DOM load untuk cegah flash)
// ============================================================
(function() {
    if (localStorage.getItem('darkMode') === 'on')
        document.documentElement.classList.add('dark');
})();

// ============================================================
// LOADING BAR
// ============================================================
(function() {
    const bar = document.createElement('div');
    bar.id = 'loading-bar';
    document.body.prepend(bar);
    let progress = 0, interval;

    function start() {
        progress = 0;
        bar.style.width = '0%';
        bar.style.opacity = '1';
        bar.classList.remove('done');
        interval = setInterval(() => {
            if (progress < 85) {
                progress += Math.random() * 10;
                bar.style.width = Math.min(progress, 85) + '%';
            }
        }, 100);
    }
    function finish() {
        clearInterval(interval);
        bar.style.width = '100%';
        setTimeout(() => bar.classList.add('done'), 300);
    }

    document.addEventListener('click', e => {
        const link = e.target.closest('a');
        if (!link) return;
        const href = link.getAttribute('href');
        if (!href || href.startsWith('#') || href.startsWith('mailto:')
            || href.startsWith('javascript:') || link.target === '_blank') return;
        start();
    });

    window.addEventListener('load', finish);
    window.addEventListener('pageshow', finish);
})();

// ============================================================
// SMOOTH SCROLL
// ============================================================
document.addEventListener('click', e => {
    const link = e.target.closest('a');
    if (!link) return;
    const href = link.getAttribute('href');
    if (!href || !href.startsWith('#') || href === '#') return;
    const target = document.querySelector(href);
    if (!target) return;
    e.preventDefault();
    window.scrollTo({ top: target.getBoundingClientRect().top + window.scrollY - 80, behavior: 'smooth' });
});

// ============================================================
// ANIMASI FADE-UP (Intersection Observer)
// ============================================================
document.addEventListener('DOMContentLoaded', () => {
    const observer = new IntersectionObserver(entries => {
        entries.forEach(e => {
            if (e.isIntersecting) {
                e.target.classList.add('visible');
                observer.unobserve(e.target);
            }
        });
    }, { threshold: 0.08 });
    document.querySelectorAll('.fade-up').forEach(el => observer.observe(el));
});

// ============================================================
// ANIMASI COUNTER (untuk statistik)
// ============================================================
function animateCounter(el) {
    const target = parseInt(el.getAttribute('data-target') || el.textContent.replace(/\D/g, ''));
    if (isNaN(target) || target === 0) return;
    const suffix = el.textContent.replace(/[\d,\.]/g, '').trim();
    let current = 0;
    const duration = 1600;
    const step = target / (duration / 16);
    const timer = setInterval(() => {
        current = Math.min(current + step, target);
        el.textContent = Math.floor(current).toLocaleString('id-ID') + suffix;
        if (current >= target) clearInterval(timer);
    }, 16);
}

document.addEventListener('DOMContentLoaded', () => {
    const counterObserver = new IntersectionObserver(entries => {
        entries.forEach(e => {
            if (e.isIntersecting) {
                animateCounter(e.target);
                counterObserver.unobserve(e.target);
            }
        });
    }, { threshold: 0.5 });
    document.querySelectorAll('.counter-num').forEach(el => counterObserver.observe(el));
});

// ============================================================
// BACK TO TOP
// ============================================================
document.addEventListener('DOMContentLoaded', () => {
    const btn = document.getElementById('backToTop');
    if (!btn) return;
    window.addEventListener('scroll', () => btn.classList.toggle('visible', window.scrollY > 300));
    btn.addEventListener('click', e => { e.preventDefault(); window.scrollTo({ top: 0, behavior: 'smooth' }); });
});

// ============================================================
// DARK MODE TOGGLE
// ============================================================
document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.getElementById('darkToggle');
    if (!toggle) return;
    const isDark = document.documentElement.classList.contains('dark');
    toggle.textContent = isDark ? '☀️' : '🌙';
    toggle.title = isDark ? 'Mode Terang' : 'Mode Gelap';

    toggle.addEventListener('click', () => {
        const on = document.documentElement.classList.toggle('dark');
        localStorage.setItem('darkMode', on ? 'on' : 'off');
        toggle.textContent = on ? '☀️' : '🌙';
        toggle.title = on ? 'Mode Terang' : 'Mode Gelap';
    });
});

// ============================================================
// TICKER AUTO-SCROLL
// ============================================================
document.addEventListener('DOMContentLoaded', () => {
    const ticker = document.querySelector('.ticker-items');
    if (!ticker || ticker.children.length === 0) return;

    // Duplikat item untuk efek loop mulus
    const items = Array.from(ticker.children);
    items.forEach(item => ticker.appendChild(item.cloneNode(true)));

    let pos = 0;
    let paused = false;
    const speed = 0.6; // px per frame

    function scroll() {
        if (!paused) {
            pos += speed;
            const halfWidth = ticker.scrollWidth / 2;
            if (pos >= halfWidth) pos = 0;
            ticker.style.transform = `translateX(-${pos}px)`;
        }
        requestAnimationFrame(scroll);
    }

    ticker.addEventListener('mouseenter', () => paused = true);
    ticker.addEventListener('mouseleave', () => paused = false);
    ticker.style.whiteSpace = 'nowrap';
    ticker.style.display = 'flex';
    ticker.style.willChange = 'transform';

    requestAnimationFrame(scroll);
});

// ============================================================
// LIVE SEARCH AUTOCOMPLETE
// ============================================================
document.addEventListener('DOMContentLoaded', () => {
    const searchInputs = document.querySelectorAll('.header-search input[type="search"]');

    searchInputs.forEach(input => {
        const wrapper = input.closest('.header-search');
        const dropdown = document.createElement('div');
        dropdown.className = 'search-dropdown';
        wrapper.appendChild(dropdown);
        wrapper.style.position = 'relative';

        let debounce;
        input.addEventListener('input', () => {
            clearTimeout(debounce);
            const q = input.value.trim();
            if (q.length < 2) { dropdown.innerHTML = ''; dropdown.classList.remove('open'); return; }

            debounce = setTimeout(async () => {
                try {
                    const res = await fetch(`search_api.php?q=${encodeURIComponent(q)}`);
                    const data = await res.json();
                    if (!data.length) { dropdown.innerHTML = '<div class="sd-empty">Tidak ada hasil</div>'; }
                    else {
                        dropdown.innerHTML = data.map(item =>
                            `<a href="post.php?id=${item.id}" class="sd-item">
                                <span class="sd-title">${item.title}</span>
                                <span class="sd-cat">${item.category}</span>
                            </a>`
                        ).join('');
                    }
                    dropdown.classList.add('open');
                } catch(e) {}
            }, 300);
        });

        document.addEventListener('click', e => {
            if (!wrapper.contains(e.target)) { dropdown.innerHTML = ''; dropdown.classList.remove('open'); }
        });

        input.addEventListener('keydown', e => {
            if (e.key === 'Escape') { dropdown.innerHTML = ''; dropdown.classList.remove('open'); }
        });
    });
});

// ============================================================
// GALERI LIGHTBOX dengan PREV/NEXT
// ============================================================
let galleryPhotos = [];
let currentPhoto = 0;

function initGallery() {
    const items = document.querySelectorAll('.gallery-item');
    galleryPhotos = Array.from(items).map(item => ({
        img:   item.dataset.img,
        title: item.dataset.title,
        desc:  item.dataset.desc || ''
    }));

    items.forEach((item, idx) => {
        item.removeAttribute('onclick');
        item.addEventListener('click', () => openLightboxIdx(idx));
    });

    // Keyboard navigation
    document.addEventListener('keydown', e => {
        const lb = document.getElementById('lightbox');
        if (!lb || lb.style.display === 'none') return;
        if (e.key === 'Escape')     closeLightbox();
        if (e.key === 'ArrowRight') lightboxNext();
        if (e.key === 'ArrowLeft')  lightboxPrev();
    });
}

function openLightboxIdx(idx) {
    currentPhoto = idx;
    const p = galleryPhotos[idx];
    if (!p) return;
    document.getElementById('lightboxImg').src = 'assets/uploads/' + p.img;
    document.getElementById('lightboxTitle').textContent = p.title;
    document.getElementById('lightboxDesc').textContent  = p.desc;
    document.getElementById('lightboxCounter').textContent = `${idx + 1} / ${galleryPhotos.length}`;
    document.getElementById('lightbox').style.display = 'flex';
    document.body.style.overflow = 'hidden';

    // Tombol prev/next visibility
    document.getElementById('lightboxPrev').style.display = idx > 0 ? 'flex' : 'none';
    document.getElementById('lightboxNext').style.display = idx < galleryPhotos.length - 1 ? 'flex' : 'none';
}

function lightboxPrev() { if (currentPhoto > 0) openLightboxIdx(currentPhoto - 1); }
function lightboxNext() { if (currentPhoto < galleryPhotos.length - 1) openLightboxIdx(currentPhoto + 1); }

function closeLightbox() {
    const lb = document.getElementById('lightbox');
    if (lb) { lb.style.display = 'none'; document.body.style.overflow = ''; }
}

// Legacy support (onclick di PHP lama)
function openLightbox(img, title, desc) {
    const idx = galleryPhotos.findIndex(p => p.img === img);
    if (idx >= 0) openLightboxIdx(idx);
}

document.addEventListener('DOMContentLoaded', () => {
    if (document.querySelector('.gallery-item')) initGallery();
});

// ============================================================
// HERO PARTICLE ANIMATION (Canvas)
// ============================================================
document.addEventListener('DOMContentLoaded', () => {
    const hero = document.querySelector('.hero');
    if (!hero) return;

    const canvas = document.createElement('canvas');
    canvas.style.cssText = 'position:absolute;inset:0;width:100%;height:100%;pointer-events:none;z-index:0;opacity:.35';
    hero.style.position = 'relative';
    hero.prepend(canvas);

    const ctx = canvas.getContext('2d');
    let W, H, particles = [];

    function resize() {
        W = canvas.width  = hero.offsetWidth;
        H = canvas.height = hero.offsetHeight;
    }

    function createParticle() {
        return {
            x: Math.random() * W,
            y: Math.random() * H,
            r: Math.random() * 2 + 0.5,
            dx: (Math.random() - 0.5) * 0.4,
            dy: -Math.random() * 0.5 - 0.2,
            alpha: Math.random() * 0.5 + 0.1
        };
    }

    resize();
    for (let i = 0; i < 60; i++) particles.push(createParticle());

    function draw() {
        ctx.clearRect(0, 0, W, H);
        particles.forEach(p => {
            ctx.beginPath();
            ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
            ctx.fillStyle = `rgba(255,255,255,${p.alpha})`;
            ctx.fill();
            p.x += p.dx;
            p.y += p.dy;
            if (p.y < -5) { Object.assign(p, createParticle()); p.y = H + 5; }
            if (p.x < -5 || p.x > W + 5) { Object.assign(p, createParticle()); }
        });
        requestAnimationFrame(draw);
    }

    draw();
    window.addEventListener('resize', resize);
});

// ============================================================
// "ARTIKEL BARU" BADGE — tandai artikel < 3 hari
// ============================================================
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.post-card[data-date]').forEach(card => {
        const date = new Date(card.dataset.date);
        const now  = new Date();
        const diff = (now - date) / (1000 * 60 * 60 * 24);
        if (diff <= 3) {
            const badge = document.createElement('span');
            badge.className = 'badge-new';
            badge.textContent = 'Baru';
            const thumb = card.querySelector('.post-thumb');
            if (thumb) thumb.appendChild(badge);
        }
    });
});

// ============================================================
// SKELETON LOADING untuk post cards
// ============================================================
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.post-card img').forEach(img => {
        img.closest('.post-thumb')?.classList.add('img-loading');
        img.addEventListener('load',  () => img.closest('.post-thumb')?.classList.remove('img-loading'));
        img.addEventListener('error', () => img.closest('.post-thumb')?.classList.remove('img-loading'));
    });
});

// ============================================================
// READING PROGRESS BAR
// ============================================================
document.addEventListener('DOMContentLoaded', () => {
    const article = document.querySelector('.post-detail-content');
    if (!article) return;

    const bar = document.createElement('div');
    bar.id = 'readingProgress';
    document.body.prepend(bar);

    window.addEventListener('scroll', () => {
        const total  = document.documentElement.scrollHeight - window.innerHeight;
        const current = window.scrollY;
        bar.style.width = total > 0 ? (current / total * 100) + '%' : '0%';
    });
});

// ============================================================
// AUTO TABLE OF CONTENTS
// ============================================================
document.addEventListener('DOMContentLoaded', () => {
    const content = document.querySelector('.post-detail-content');
    if (!content) return;

    const headings = content.querySelectorAll('h2, h3');
    if (headings.length < 3) return; // Hanya tampilkan jika ada >= 3 heading

    // Beri ID pada setiap heading
    headings.forEach((h, i) => {
        if (!h.id) h.id = 'section-' + i;
    });

    // Buat TOC
    const toc = document.createElement('details');
    toc.className = 'toc-box';
    toc.open = true;
    toc.innerHTML = `
        <summary>📋 Daftar Isi</summary>
        <ul class="toc-list">
            ${Array.from(headings).map(h =>
                `<li style="${h.tagName==='H3'?'padding-left:1rem':''}">
                    <a href="#${h.id}">${h.textContent}</a>
                </li>`
            ).join('')}
        </ul>
    `;

    content.insertBefore(toc, content.firstChild);
});

// ============================================================
// COPY CODE BUTTON
// ============================================================
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.post-detail-content pre').forEach(pre => {
        const wrapper = document.createElement('div');
        wrapper.className = 'code-wrapper';
        pre.parentNode.insertBefore(wrapper, pre);
        wrapper.appendChild(pre);

        const btn = document.createElement('button');
        btn.className = 'copy-code-btn';
        btn.textContent = '📋 Copy';
        wrapper.appendChild(btn);

        btn.addEventListener('click', () => {
            navigator.clipboard.writeText(pre.textContent).then(() => {
                btn.textContent = '✓ Tersalin';
                setTimeout(() => btn.textContent = '📋 Copy', 2000);
            });
        });
    });
});

// ============================================================
// SHARE BUTTON DI KARTU ARTIKEL
// ============================================================
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.post-card').forEach(card => {
        const link = card.querySelector('.post-card-link');
        if (!link) return;
        const href = link.getAttribute('href');
        const title = card.querySelector('.post-title')?.textContent?.trim() || '';
        const fullUrl = new URL(href, window.location.origin).href;

        const shareDiv = document.createElement('div');
        shareDiv.className = 'post-card-share';
        shareDiv.innerHTML = `
            <a href="https://wa.me/?text=${encodeURIComponent(title + ' ' + fullUrl)}"
               target="_blank" rel="noopener"
               class="post-share-btn post-share-wa" title="Bagikan ke WhatsApp"
               onclick="event.stopPropagation()">📱</a>
            <a href="https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(fullUrl)}"
               target="_blank" rel="noopener"
               class="post-share-btn post-share-fb" title="Bagikan ke Facebook"
               onclick="event.stopPropagation()">👥</a>
        `;
        card.appendChild(shareDiv);
    });
});

// ============================================================
// GRADIENT PLACEHOLDER TEXT (inisial kategori)
// ============================================================
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.post-thumb-placeholder').forEach(el => {
        const cat = el.closest('.post-card')?.querySelector('.post-category')?.textContent?.trim();
        if (cat) el.textContent = cat.charAt(0).toUpperCase();
    });
});
