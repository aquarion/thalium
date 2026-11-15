// Sticky audio player, in-page navigation between a system's Documents / Art /
// Music tabs (so the <audio> element is never torn down), and the art lightbox.

const SECTION = "system-section";

function isPlainLeftClick(event) {
    return (
        event.button === 0 &&
        !event.metaKey &&
        !event.ctrlKey &&
        !event.shiftKey &&
        !event.altKey
    );
}

// ---- Player ---------------------------------------------------------------

function initPlayer() {
    const root = document.getElementById("media-player");
    if (!root) {
        return;
    }

    const audio = document.getElementById("media-audio");
    const toggle = document.getElementById("media-toggle");
    const seek = document.getElementById("media-seek");
    const volume = document.getElementById("media-volume");
    const title = document.getElementById("media-title");

    let queue = [];
    let index = -1;

    function play(i) {
        if (i < 0 || i >= queue.length) {
            return;
        }
        index = i;
        audio.src = queue[i].url;
        title.textContent = queue[i].title;
        root.hidden = false;
        document.body.classList.add("has-player");
        audio.play().catch(() => {});
    }

    document.addEventListener("click", (event) => {
        const button = event.target.closest("[data-track-url]");
        if (!button) {
            return;
        }
        event.preventDefault();
        const section = document.getElementById(SECTION) || document;
        queue = Array.from(section.querySelectorAll("[data-track-url]")).map(
            (el) => ({ url: el.dataset.trackUrl, title: el.dataset.trackTitle }),
        );
        play(queue.findIndex((track) => track.url === button.dataset.trackUrl));
    });

    toggle.addEventListener("click", () => {
        if (audio.paused) {
            audio.play().catch(() => {});
        } else {
            audio.pause();
        }
    });
    document.getElementById("media-prev").addEventListener("click", () => play(index - 1));
    document.getElementById("media-next").addEventListener("click", () => play(index + 1));

    audio.addEventListener("play", () => {
        toggle.firstElementChild.className = "bi bi-pause-fill";
    });
    audio.addEventListener("pause", () => {
        toggle.firstElementChild.className = "bi bi-play-fill";
    });
    audio.addEventListener("ended", () => play(index + 1));
    audio.addEventListener("timeupdate", () => {
        if (audio.duration) {
            seek.value = (audio.currentTime / audio.duration) * 100;
        }
    });
    seek.addEventListener("input", () => {
        if (audio.duration) {
            audio.currentTime = (seek.value / 100) * audio.duration;
        }
    });
    volume.addEventListener("input", () => {
        audio.volume = Number(volume.value);
    });
}

// ---- In-page navigation ---------------------------------------------------

async function swapSection(url, push) {
    const fallback = () => {
        window.location.href = url;
    };

    let doc;
    try {
        const response = await fetch(url, {
            credentials: "same-origin",
            headers: { "X-Requested-With": "XMLHttpRequest" },
        });
        // A redirect (e.g. to the login page) or an error needs a real navigation.
        if (!response.ok || response.redirected) {
            return fallback();
        }
        doc = new DOMParser().parseFromString(await response.text(), "text/html");
    } catch (error) {
        return fallback();
    }

    const fresh = doc.getElementById(SECTION);
    const current = document.getElementById(SECTION);
    if (!fresh || !current) {
        return fallback();
    }

    current.replaceWith(document.importNode(fresh, true));
    document.title = doc.title;
    if (push) {
        window.history.pushState({}, "", url);
    }
    window.scrollTo(0, 0);
}

function initNavigation() {
    document.addEventListener("click", (event) => {
        const link = event.target.closest(
            "a[data-section-link], #system-section .pagination a",
        );
        if (
            !link ||
            !isPlainLeftClick(event) ||
            link.target ||
            link.origin !== window.location.origin ||
            !document.getElementById(SECTION)
        ) {
            return;
        }
        event.preventDefault();
        swapSection(link.href, true);
    });

    window.addEventListener("popstate", () => {
        if (document.getElementById(SECTION)) {
            swapSection(window.location.href, false);
        }
    });
}

// ---- Lightbox -------------------------------------------------------------

function initLightbox() {
    let overlay = null;
    let images = [];
    let position = 0;

    function show(i) {
        position = (i + images.length) % images.length;
        overlay.querySelector("img").src = images[position].href;
        overlay.querySelector(".media-lightbox-title").textContent =
            images[position].dataset.title || "";
    }

    function close() {
        if (overlay) {
            overlay.remove();
            overlay = null;
        }
        document.removeEventListener("keydown", onKey);
    }

    function onKey(event) {
        if (event.key === "Escape") {
            close();
        } else if (event.key === "ArrowLeft") {
            show(position - 1);
        } else if (event.key === "ArrowRight") {
            show(position + 1);
        }
    }

    document.addEventListener("click", (event) => {
        const link = event.target.closest("a[data-lightbox]");
        if (!link || !isPlainLeftClick(event)) {
            return;
        }
        event.preventDefault();

        const section = document.getElementById(SECTION) || document;
        images = Array.from(section.querySelectorAll("a[data-lightbox]"));

        overlay = document.createElement("div");
        overlay.className = "media-lightbox";
        overlay.innerHTML =
            '<button type="button" class="media-lightbox-prev" aria-label="Previous">&#8249;</button>' +
            '<figure><img alt=""><figcaption class="media-lightbox-title"></figcaption></figure>' +
            '<button type="button" class="media-lightbox-next" aria-label="Next">&#8250;</button>' +
            '<button type="button" class="media-lightbox-close" aria-label="Close">&times;</button>';
        overlay.addEventListener("click", (e) => {
            if (e.target.closest(".media-lightbox-prev")) {
                show(position - 1);
            } else if (e.target.closest(".media-lightbox-next")) {
                show(position + 1);
            } else if (!e.target.closest("img")) {
                close();
            }
        });
        document.body.appendChild(overlay);
        document.addEventListener("keydown", onKey);
        show(images.indexOf(link));
    });
}

initPlayer();
initNavigation();
initLightbox();
