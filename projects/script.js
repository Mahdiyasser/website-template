/* Minimal, dependency-free frontend logic.
   Expects projects.json and posts.json in same folder.
   If fetch fails (file://), it uses the fallback JSON blocks embedded in index.html.
*/

const $ = (s, c = document) => c.querySelector(s);
const $$ = (s, c = document) => Array.from(c.querySelectorAll(s));

const projectsGrid = $('#projectsGrid');
const projectsEmpty = $('#projectsEmpty');
const postsArea = $('#postsArea');
const projectsArea = $('#projectsArea');
const postsGrid = $('#postsGrid');
const postsEmpty = $('#postsEmpty');
const postsTitle = $('#postsTitle');
const backBtn = $('#backBtn');
const searchInput = $('#search');
const openCms = $('#openCms');
const showAllBtn = $('#showAll');
const sortSelect = $('#sort');
const backToTop = $('#backToTop');

let projects = [];
let posts = [];
let activeProject = null;

async function loadJSON(path, fallbackId) {
    try {
        const r = await fetch(path, { cache: 'no-cache' });
        if (!r.ok) throw new Error('network');
        return await r.json();
    } catch (e) {
        // fallback to embedded
        try {
            const el = document.getElementById(fallbackId);
            return JSON.parse(el.textContent);
        } catch (err) {
            console.error('Failed to load', path, err);
            return [];
        }
    }
}

function sanitizeFilename(name) {
    return name.replace(/[\\\/:#?<>|"]/g, '').replace(/\s+/g, '-');
}

function createProjectCard(proj) {
    const card = document.createElement('article');
    card.className = 'card';
    card.dataset.project = proj.title;

    const thumb = document.createElement('div'); thumb.className = 'thumb';
    const img = document.createElement('img');
    img.alt = proj.title + ' thumbnail';
    img.src = proj.thumbnail || `assets/${encodeURIComponent(proj.title)}/images/${encodeURIComponent(proj.title)}.jpg`;
    thumb.appendChild(img);

    const title = document.createElement('h3'); title.textContent = proj.title;
    const bio = document.createElement('p'); bio.textContent = proj.bio || '';

    const meta = document.createElement('div'); meta.className = 'meta-row';
    const date = document.createElement('small'); date.textContent = proj.date || '';
    const open = document.createElement('button'); open.className = 'btn'; open.textContent = 'Open';
    open.onclick = () => openProject(proj.title);

    meta.appendChild(date); meta.appendChild(open);
    card.appendChild(thumb); card.appendChild(title); card.appendChild(bio); card.appendChild(meta);

    // clicking card (not button) also opens
    card.addEventListener('click', (e) => {
        if (e.target.tagName.toLowerCase() !== 'button') openProject(proj.title);
    });

    return card;
}

function renderProjects(list) {
    projectsGrid.innerHTML = '';
    if (!list || list.length === 0) { projectsEmpty.style.display = 'block'; return; } else projectsEmpty.style.display = 'none';
    list.forEach(p => projectsGrid.appendChild(createProjectCard(p)));
}

function buildPostCard(p) {
    const c = document.createElement('article'); c.className = 'card post-card';
    const thumb = document.createElement('div'); thumb.className = 'post-thumb';
    const img = document.createElement('img');
    img.alt = p.title + ' thumbnail';
    img.src = p.thumbnail || `assets/${encodeURIComponent(p.project)}/images/${encodeURIComponent(p.title)}-thumbnail.jpg`;
    thumb.appendChild(img);

    const info = document.createElement('div'); info.className = 'post-info';
    const h = document.createElement('h4'); h.textContent = p.title;
    const bio = document.createElement('p'); bio.textContent = p.bio || '';
    const meta = document.createElement('small'); meta.style.color = 'var(--muted)'; meta.textContent = p.date || '';

    info.appendChild(h); info.appendChild(bio); info.appendChild(meta);
    c.appendChild(thumb); c.appendChild(info);

    // open actual post file in new tab
    c.addEventListener('click', () => {
        const filename = sanitizeFilename(p.title) + '.html';
        const path = `assets/${encodeURIComponent(p.project)}/posts/${encodeURIComponent(p.title)}.html`;
        // try the nice path (keeps case) but fallback to sanitized filename
        window.open(path, '_blank') || window.open(`assets/${encodeURIComponent(p.project)}/posts/${encodeURIComponent(filename)}`, '_blank');
    });

    return c;
}

function renderPostsForProject(title) {
    postsGrid.innerHTML = '';
    const filtered = posts.filter(p => p.project === title);
    if (filtered.length === 0) { postsEmpty.style.display = 'block'; return; } else postsEmpty.style.display = 'none';

    const sortMode = sortSelect.value;
    const sorted = filtered.slice().sort((a, b) => {
        if (sortMode === 'new') return new Date(b.date) - new Date(a.date);
        if (sortMode === 'old') return new Date(a.date) - new Date(b.date);
        return a.title.localeCompare(b.title);
    });

    sorted.forEach(p => postsGrid.appendChild(buildPostCard(p)));
}

function openProject(title) {
    activeProject = title;
    document.title = title + ' — Projects';
    postsTitle.textContent = title;
    projectsArea.style.display = 'none';
    postsArea.style.display = 'block';
    renderPostsForProject(title);
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function closePostsView() {
    activeProject = null;
    document.title = 'Projects — Mini CMS';
    postsArea.style.display = 'none';
    projectsArea.style.display = 'block';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function attachEvents() {
    backBtn.addEventListener('click', closePostsView);
    $('#sort').addEventListener('change', () => { if (activeProject) renderPostsForProject(activeProject); });

    searchInput.addEventListener('input', (e) => {
        const q = e.target.value.trim().toLowerCase();
        if (!q) { renderProjects(projects); return; }
        // search projects and posts
        const projMatches = projects.filter(p => (p.title + ' ' + (p.bio || '')).toLowerCase().includes(q));
        const postMatches = posts.filter(p => (p.title + ' ' + (p.bio || '')).toLowerCase().includes(q));
        // show matching projects first (unique), then any projects that have matching posts
        const projectsFromPosts = Array.from(new Set(postMatches.map(p => p.project))).map(name => projects.find(x => x.title === name)).filter(Boolean);
        const final = Array.from(new Set([...projMatches, ...projectsFromPosts]));
        renderProjects(final);
    });

    showAllBtn.addEventListener('click', () => {
        searchInput.value = '';
        renderProjects(projects);
        closePostsView();
    });

    backToTop.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
}

async function init() {
    projects = await loadJSON('projects.json', 'projects-fallback');
    posts = await loadJSON('posts.json', 'posts-fallback');
    renderProjects(projects);
    attachEvents();
}

init();
