// script.js - Complete Version (Location Removed)

const feedElement = document.getElementById('feed');
const body = document.body;
const HEADER_SELECTOR = '.main-header';

// --- Theme Management ---

/**
 * Applies the specified theme ('light' or 'dark') to the body element
 * and saves the preference to localStorage. It also updates the toggle button icon.
 * @param {string} theme - The theme to apply.
 */
function applyTheme(theme) {
    const toggleButton = document.querySelector('.theme-toggle-button');
    if (theme === 'dark') {
        body.classList.add('dark-theme');
        localStorage.setItem('theme', 'dark');
        if (toggleButton) {
            // Moon icon for dark theme
            toggleButton.innerHTML = `
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 3a9 9 0 009 9c0 1.2-.2 2.3-.58 3.4A8.995 8.995 0 0112 21a9 9 0 01-9-9 9 9 0 019-9zm0 2a7 7 0 00-7 7c0 1.76.62 3.39 1.65 4.67A7 7 0 0019 12a7 7 0 00-7-7z"/>
                </svg>
            `;
        }
    } else {
        body.classList.remove('dark-theme');
        localStorage.setItem('theme', 'light');
        if (toggleButton) {
            // Sun icon for light theme
            toggleButton.innerHTML = `
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 3a9 9 0 010 18A9 9 0 0112 3zm0 2a7 7 0 000 14A7 7 0 0012 5zm0-2.5c.28 0 .5-.22.5-.5V1.5c0-.28-.22-.5-.5-.5s-.5.22-.5.5v1c0 .28.22.5.5.5zm0 19c-.28 0-.5.22-.5.5v1c0 .28.22.5.5.5s.5-.22.5-.5v-1c0-.28-.22-.5-.5-.5zM3.5 12c0-.28-.22-.5-.5-.5H1.5c-.28 0-.5.22-.5.5s.22.5.5.5h1c.28 0 .5-.22.5-.5zm19 0c0-.28-.22-.5-.5-.5h-1c-.28 0-.5.22-.5.5s.22.5.5.5h1c.28 0 .5-.22.5-.5zM4.93 4.93a.5.5 0 00-.7-.7l-.7.7a.5.5 0 00.7.7l.7-.7zM19.07 19.07a.5.5 0 00-.7-.7l-.7.7a.5.5 0 00.7.7l.7-.7zM4.93 19.07a.5.5 0 01-.7.7l-.7-.7a.5.5 0 01.7-.7l.7.7zM19.07 4.93a.5.5 0 01.7-.7l.7.7a.5.5 0 01-.7.7l-.7-.7z"/>
                </svg>
            `;
        }
    }
}

/**
 * Creates and appends the theme toggle button to the main header.
 */
function createThemeToggleButton() {
    const header = document.querySelector(HEADER_SELECTOR);
    if (!header) return;

    const toggleButton = document.createElement('button');
    toggleButton.className = 'theme-toggle-button';
    toggleButton.setAttribute('aria-label', 'Toggle light and dark themes');

    toggleButton.addEventListener('click', () => {
        const currentTheme = body.classList.contains('dark-theme') ? 'dark' : 'light';
        const newTheme = currentTheme === 'light' ? 'dark' : 'light';
        applyTheme(newTheme);
    });

    header.appendChild(toggleButton);
}

// --- Post Rendering ---

/**
 * Renders the list of posts fetched from posts.json into the feed element.
 * @param {Array<Object>} posts - The array of post objects.
 */
function renderPosts(posts) {
    if (!posts || posts.length === 0) {
        feedElement.innerHTML = '<p>No posts found. Please check posts.json.</p>';
        return;
    }

    posts.forEach(post => {
        const card = document.createElement('article');
        card.className = 'post-card';

        // 1. Thumbnail Image 
        if (post.thumbnail) {
            const thumbnail = document.createElement('img');
            thumbnail.src = post.thumbnail;
            thumbnail.alt = `Thumbnail for ${post.title}`;
            thumbnail.className = 'post-thumbnail';
            card.appendChild(thumbnail);
        }

        // 2. Content Wrapper 
        const contentWrapper = document.createElement('div');
        contentWrapper.className = 'card-content-wrapper';

        // 3. Title
        const title = document.createElement('h2');
        title.className = 'post-title';
        title.textContent = post.title;

        // 4. Meta (Formatted with Emojis)
        const postDate = new Date(post.date);
        
        // Date part: "📅 October 11, 2025"
        const datePart = `📅 ${postDate.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}`;
        
        let metaParts = [datePart];

        // Time part: "🕓 04:25 PM" (Only if the date string in JSON included time)
        const timeRegex = /\s\d{2}:\d{2}/; 
        if (post.date && timeRegex.test(post.date)) {
            const timePart = `🕓 ${postDate.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true })}`;
            metaParts.push(timePart);
        }

        // Location part removed as per user request.

        // Combine only the date and time parts with the desired separator
        const metaText = metaParts.join(' | ');

        const meta = document.createElement('span');
        meta.className = 'post-meta';
        meta.textContent = metaText;


        // 5. Description 
        const content = document.createElement('p');
        content.className = 'post-content';
        content.textContent = post.desc; 

        // 6. Read More Link
        const readMore = document.createElement('a');
        readMore.className = 'read-more';
        readMore.href = post.file || '#'; 
        readMore.textContent = 'Read Full Post »';

        // Append elements to the wrapper
        contentWrapper.appendChild(title);
        contentWrapper.appendChild(meta);
        contentWrapper.appendChild(content);
        contentWrapper.appendChild(readMore);

        // Append the wrapper to the card
        card.appendChild(contentWrapper); 
        
        // Append the final card to the feed
        feedElement.appendChild(card);
    });
}

// --- Data Fetching ---

/**
 * Fetches post data from the posts.json file.
 */
async function fetchPosts() {
    try {
        const response = await fetch('posts.json');
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const posts = await response.json();
        renderPosts(posts);
    } catch (error) {
        console.error("Could not fetch posts:", error);
        feedElement.innerHTML = `<p class="error-message">Error fetching posts: ${error.message}. Make sure 'posts.json' is available and correctly formatted.</p>`;
    }
}

// --- Initialization ---

document.addEventListener('DOMContentLoaded', () => {
    // 1. Create the theme toggle button first
    createThemeToggleButton();

    // 2. Load theme (defaults to 'dark' for the futuristic aesthetic)
    const savedTheme = localStorage.getItem('theme') || 'dark';
    applyTheme(savedTheme);

    // 3. Fetch and display posts
    fetchPosts();
});
