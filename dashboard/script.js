// Array of file configurations (Ensure your text files exist!)
const filesToLoad = [
    { file: 'devnotes.txt', elementId: 'devnotes-content' },
    { file: 'what-needs-to-be-fixed.txt', elementId: 'fixed-content' },
    { file: 'whats-new.txt', elementId: 'new-content' }
];

// --- THEME, CONTENT LOADING, FULLSCREEN (Confirmed Functional) ---

function setupThemeToggle() {
    const toggleButton = document.getElementById('theme-toggle');
    const body = document.body;
    const initialTheme = body.getAttribute('data-theme') || 'dark';
    body.setAttribute('data-theme', initialTheme);

    toggleButton.addEventListener('click', () => {
        const currentTheme = body.getAttribute('data-theme');
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        body.setAttribute('data-theme', newTheme);
    });
}

function processTextForHighlights(rawText) {
    const lines = rawText.split('\n');
    const processedLines = lines.map(line => {
        if (line.trim().startsWith('<--')) {
            return `<span class="highlight-note">${line}</span>`;
        }
        return line;
    });
    return processedLines.join('\n').trim();
}

async function loadFileContent(fileUrl, elementId) {
    const element = document.getElementById(elementId);
    if (!element) return;
    try {
        const response = await fetch(fileUrl, { cache: 'no-cache' });
        if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
        const rawTextContent = await response.text();
        const highlightedContent = processTextForHighlights(rawTextContent);
        element.innerHTML = highlightedContent || 'No content available.';
    } catch (error) {
        element.textContent = `ERROR: Could not load file (${fileUrl}).`;
    }
}

function loadAllFiles() {
    filesToLoad.forEach(config => {
        loadFileContent(config.file, config.elementId);
    });
}

function toggleFullscreen(card, forceExit = false) {
    const isFullscreen = card.classList.contains('fullscreen');
    if (forceExit && !isFullscreen) return;
    if (!forceExit && isFullscreen) return;

    const willEnter = !isFullscreen && !forceExit;

    // helper to reload file content for this card
    const reloadCardContent = () => {
        const pre = card.querySelector('pre');
        if (!pre) return;
        const fileConfig = filesToLoad.find(c => c.elementId === pre.id);
        if (fileConfig) {
            loadFileContent(fileConfig.file, fileConfig.elementId);
        }
    };

    // Temporarily disable transitions for an immediate switch
    document.body.classList.add('no-transitions');

    // Use rAF to ensure the no-transition class is applied before changing layout
    requestAnimationFrame(() => {
        if (willEnter) {
            card.classList.add('fullscreen');
            document.body.classList.add('fullscreen-active');
            // don't wait — enable transitions back next frame
            requestAnimationFrame(() => document.body.classList.remove('no-transitions'));
        } else {
            card.classList.remove('fullscreen');
            document.body.classList.remove('fullscreen-active');
            // reload content immediately after exit so card isn't empty
            reloadCardContent();
            requestAnimationFrame(() => document.body.classList.remove('no-transitions'));
        }
    });
}

function setupCardListeners() {
    document.querySelectorAll('.card').forEach(card => {
        card.addEventListener('click', (event) => {
            if (event.target.closest('.exit-btn')) return;
            if (!card.classList.contains('fullscreen')) {
                toggleFullscreen(card, false);
            }
        });

        const exitBtn = card.querySelector('.exit-btn');
        if (exitBtn) {
            exitBtn.addEventListener('click', (event) => {
                event.stopPropagation();
                toggleFullscreen(card, true);
            });
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            const activeCard = document.querySelector('.card.fullscreen');
            if (activeCard) {
                toggleFullscreen(activeCard, true);
            }
        }
    });
}


// ------------------------------------------------------------------
// --- STAR RATING LOGIC (MODIFIED FOR MODES, HALF-STARS, AND COUNT) ---
// ------------------------------------------------------------------

function renderStars() {
    const starCountElement = document.getElementById('star-count-number');
    const starModeElement = document.getElementById('star-mode-selector');
    const ratingContainer = document.getElementById('star-rating');

    if (!starCountElement || !ratingContainer || !starModeElement) return;

    const count = parseFloat(starCountElement.textContent);
    const mode = parseInt(starModeElement.textContent, 10);

    if (isNaN(count) || count < 0) {
        ratingContainer.textContent = '';
        return;
    }

    let starsHTML = '';
    const starChar = '★';
    const maxSupportedRating = 10;

    // Cap the displayed rating at maxSupportedRating
    const displayCount = Math.min(count, maxSupportedRating);

    const wholeCount = Math.floor(displayCount);
    const decimalPart = displayCount - wholeCount;

    // CRITICAL CHANGE: Only render the stars needed (e.g., 4.5 requires 5 star slots)
    const maxStarsToRender = Math.ceil(displayCount);

    // 1. Determine Star Mode Class
    let modeClass = '';
    if (mode >= 1 && mode <= 5) {
        modeClass = `star-mode-${mode}`;
    }

    ratingContainer.className = '';
    if (modeClass) {
        ratingContainer.classList.add(modeClass);
    }

    // 2. Build the Stars - Loop only up to maxStarsToRender
    for (let i = 1; i <= maxStarsToRender; i++) {
        let starClass = 'empty';

        // Full star check
        if (i <= wholeCount) {
            starClass = 'full';
        }

        // Half star check: Only at the position after the whole count
        else if (i === wholeCount + 1 && decimalPart >= 0.1) {
            starClass = 'half';
        }

        starsHTML += `<span class="${starClass}">${starChar}</span>`;
    }

    ratingContainer.innerHTML = starsHTML;
}

// ------------------------------------------------------------------
// --- STATUS COLOR LOGIC (UNCHANGED) ---
// ------------------------------------------------------------------

function renderStatusColor() {
    const selectorElement = document.getElementById('status-color-selector');
    const statusTextElement = document.getElementById('status-text');

    if (!selectorElement || !statusTextElement) return;

    const colorNumber = parseInt(selectorElement.textContent, 10);

    statusTextElement.className = '';

    if (colorNumber >= 1 && colorNumber <= 10) {
        statusTextElement.classList.add(`status-mode-${colorNumber}`);
    }
}

// --- INITIALIZATION & AUTO-RELOAD ---

document.addEventListener('DOMContentLoaded', () => {
    setupThemeToggle();
    renderStars();
    renderStatusColor();
    loadAllFiles();
    setupCardListeners();
});

const RELOAD_INTERVAL_MS = 5000;
setInterval(() => {
    renderStars();
    renderStatusColor();
    loadAllFiles();
}, RELOAD_INTERVAL_MS);