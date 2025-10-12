/* post-cms.js
   Frontend logic for Post CMS
   - Loads projects.json and posts.json
   - Create/update/delete posts via post-cms.php (FormData)
   - Renders posts list with correct /projects/assets/... paths
   - Hybrid editor (text or HTML)
*/

/* -------- config -------- */
const API = './post-cms.php';               // backend
const PROJECTS_JSON = '/projects/projects.json';
const POSTS_JSON = '/projects/posts.json';

/* -------- DOM refs -------- */
const btnNew = document.getElementById('btnNew');
const btnShowAll = document.getElementById('btnShowAll');
const filterProject = document.getElementById('filterProject');
const searchInput = document.getElementById('searchInput');

const postsGrid = document.getElementById('postsGrid');
const postsStats = document.getElementById('postsStats');

const formWrap = document.getElementById('formWrap');
const postForm = document.getElementById('postForm');

const editModeInput = document.getElementById('editMode');
const originalTitleInput = document.getElementById('originalTitle');

const postProject = document.getElementById('postProject');
const postTitle = document.getElementById('postTitle');
const postBio = document.getElementById('postBio');
const postDate = document.getElementById('postDate');
const postTime = document.getElementById('postTime');
const postLocation = document.getElementById('postLocation');
const postContent = document.getElementById('postContent');
const postVideos = document.getElementById('postVideos');
const postThumbnail = document.getElementById('postThumbnail');
const postImages = document.getElementById('postImages');

const btnSave = document.getElementById('btnSave');
const btnCancel = document.getElementById('btnCancel');
const btnPreview = document.getElementById('btnPreview');
const btnCloseForm = document.getElementById('btnCloseForm');

const previewWrap = document.getElementById('previewWrap');
const previewArea = document.getElementById('previewArea');
const btnHidePreview = document.getElementById('btnHidePreview');

const toast = document.getElementById('toast');

let allProjects = [];
let allPosts = [];

/* -------- helpers -------- */
function showToast(msg, ok = true) {
    if (!toast) return;
    toast.textContent = msg;
    toast.style.background = ok ? '#111' : '#b00';
    toast.classList.remove('hidden');
    setTimeout(() => toast.classList.add('hidden'), 2200);
}

function slugify(s) {
    return String(s || '').normalize('NFKD').replace(/[\u0300-\u036f]/g, '').replace(/[^\w\s-]/g, '').trim().replace(/\s+/g, '-');
}

async function fetchJson(path) {
    try {
        const res = await fetch(path + '?_=' + Date.now());
        if (!res.ok) throw new Error('Network response not ok');
        return await res.json();
    } catch (e) {
        console.error('fetchJson', e);
        return null;
    }
}

/* -------- load projects -------- */
async function loadProjects() {
    const data = await fetchJson(PROJECTS_JSON);
    if (!data) { showToast('Failed load projects', false); return; }
    allProjects = data;
    filterProject.innerHTML = '<option value="">— All Projects —</option>';
    postProject.innerHTML = '<option value="">Select project</option>';
    data.forEach(p => {
        const opt = document.createElement('option'); opt.value = p.title; opt.textContent = p.title;
        filterProject.appendChild(opt);
        postProject.appendChild(opt.cloneNode(true));
    });
}

/* -------- load posts -------- */
async function loadPosts(filter = null) {
    const data = await fetchJson(POSTS_JSON);
    if (!data) { showToast('Failed load posts', false); return; }
    allPosts = data;
    renderPosts(filter);
}

/* -------- render posts -------- */
function renderPosts(filter = null) {
    postsGrid.innerHTML = '';
    let posts = Array.isArray(allPosts) ? allPosts.slice() : [];
    if (filter) posts = posts.filter(p => p.project === filter);
    const q = (searchInput && searchInput.value || '').trim().toLowerCase();
    if (q) posts = posts.filter(p => (p.title || '').toLowerCase().includes(q));
    posts.sort((a, b) => {
        if (a.date && b.date) return new Date(b.date) - new Date(a.date);
        return 0;
    });

    if (!posts.length) {
        postsGrid.innerHTML = '<div class="small muted">No posts found.</div>';
        postsStats.textContent = '0 posts';
        return;
    }

    posts.forEach(p => {
        const card = document.createElement('div'); card.className = 'post-card';
        const thumb = document.createElement('img'); thumb.className = 'thumb';
        thumb.alt = p.title || 'thumb';

        // thumbnail resolution: p.thumbnail should be stored as /projects/assets/<slug>/images/<file>
        if (p.thumbnail && p.thumbnail.startsWith('/projects')) thumb.src = p.thumbnail;
        else if (p.thumbnail) thumb.src = '/projects/assets/' + encodeURIComponent(p.project_slug || slugify(p.project)) + '/images/' + encodeURIComponent(p.thumbnail);
        else thumb.src = '/projects/assets/default-thumb.jpg';

        const h3 = document.createElement('h3'); h3.textContent = p.title;
        const meta = document.createElement('div'); meta.className = 'meta'; meta.textContent = `${p.project || ''}${p.date ? ' • ' + p.date : ''}`;
        const bio = document.createElement('div'); bio.className = 'small muted'; bio.textContent = p.bio || '';

        const actions = document.createElement('div'); actions.className = 'card-actions';

        const btnView = document.createElement('button'); btnView.className = 'btn'; btnView.textContent = 'View';
        btnView.onclick = () => {
            let path = p.path || `/projects/assets/${p.project_slug || slugify(p.project)}/posts/${slugify(p.title)}.html`;
            if (!path.startsWith('/projects')) path = '/projects' + path;
            window.open(path, '_blank');
        };

        const btnEdit = document.createElement('button'); btnEdit.className = 'btn'; btnEdit.textContent = 'Edit';
        btnEdit.onclick = () => openEditForm(p);

        const btnDelete = document.createElement('button'); btnDelete.className = 'btn danger'; btnDelete.textContent = 'Delete';
        btnDelete.onclick = () => deletePost(p);

        actions.appendChild(btnView); actions.appendChild(btnEdit); actions.appendChild(btnDelete);

        card.appendChild(thumb); card.appendChild(h3); card.appendChild(meta); card.appendChild(bio); card.appendChild(actions);
        postsGrid.appendChild(card);
    });

    postsStats.textContent = `${posts.length} post(s)`;
}

/* -------- open forms -------- */
function openNewForm() {
    editModeInput.value = '0';
    originalTitleInput.value = '';
    postForm.reset();
    formWrap.classList.remove('hidden');
    document.getElementById('formTitle').textContent = 'Create New Post';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function openEditForm(post) {
    editModeInput.value = '1';
    originalTitleInput.value = post.title || '';
    postProject.value = post.project || '';
    postTitle.value = post.title || '';
    postBio.value = post.bio || '';
    postDate.value = post.date || '';
    postTime.value = post.time || '';
    postLocation.value = post.location || '';
    postContent.value = post.contentRaw || '';
    postVideos.value = (post.videos || []).join('\n');
    formWrap.classList.remove('hidden');
    document.getElementById('formTitle').textContent = 'Edit Post';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

/* -------- save (create/update) -------- */
async function handleSave(ev) {
    ev.preventDefault();
    const isEdit = editModeInput.value === '1';
    const project = postProject.value.trim();
    const title = postTitle.value.trim();
    if (!project || !title) return alert('Project and Title required.');

    const bio = postBio.value.trim();
    const date = postDate.value || new Date().toISOString().slice(0, 10);
    const time = postTime.value || '';
    const location = postLocation.value || '';
    const content = postContent.value || '';
    const videos = postVideos.value || '';
    const thumbnailFile = postThumbnail.files && postThumbnail.files[0] ? postThumbnail.files[0] : null;
    const imageFiles = postImages.files ? Array.from(postImages.files) : [];

    const fd = new FormData();
    fd.append('action', isEdit ? 'updatePost' : 'createPost');
    fd.append('project', project);
    fd.append('project_slug', slugify(project));
    if (isEdit) fd.append('old_title', originalTitleInput.value || '');
    fd.append('title', title);
    fd.append('bio', bio);
    fd.append('date', date);
    fd.append('time', time);
    fd.append('location', location);
    fd.append('content', content);
    fd.append('videos', videos);

    if (thumbnailFile) fd.append('thumbnail', thumbnailFile);
    if (imageFiles.length) imageFiles.forEach((f) => fd.append('images[]', f));

    try {
        const res = await fetch(API, { method: 'POST', body: fd });
        const j = await res.json();
        if (j.status === 'ok') {
            showToast(isEdit ? 'Post updated' : 'Post created');
            postForm.reset();
            formWrap.classList.add('hidden');
            await loadPosts(filterProject.value || null);
        } else {
            showToast('Error: ' + (j.msg || 'unknown'), false);
            console.error('api error', j);
        }
    } catch (e) {
        console.error(e);
        showToast('Network error', false);
    }
}

/* -------- delete -------- */
async function deletePost(post) {
    if (!confirm(`Delete post "${post.title}"? This removes html & images.`)) return;
    const fd = new FormData();
    fd.append('action', 'deletePost');
    fd.append('project', post.project);
    fd.append('project_slug', slugify(post.project));
    fd.append('title', post.title);

    try {
        const res = await fetch(API, { method: 'POST', body: fd });
        const j = await res.json();
        if (j.status === 'ok') { showToast('Post deleted'); await loadPosts(filterProject.value || null); }
        else { showToast('Delete failed: ' + (j.msg || ''), false); }
    } catch (e) { console.error(e); showToast('Network error', false); }
}

/* -------- preview -------- */
function parseVideosHtml(vraw) {
    const lines = (vraw || '').split('\n').map(s => s.trim()).filter(Boolean);
    const out = [];
    lines.forEach(v => {
        if (v.includes('youtube.com/watch') && /v=([A-Za-z0-9_\-]+)/.test(v)) {
            const m = v.match(/v=([A-Za-z0-9_\-]+)/); out.push(`<iframe src="https://www.youtube.com/embed/${m[1]}" allowfullscreen></iframe>`);
        } else if (v.includes('youtu.be/') && /youtu\.be\/([A-Za-z0-9_\-]+)/.test(v)) {
            const m = v.match(/youtu\.be\/([A-Za-z0-9_\-]+)/); out.push(`<iframe src="https://www.youtube.com/embed/${m[1]}" allowfullscreen></iframe>`);
        } else {
            out.push(`<iframe src="${escapeHtml(v)}" allowfullscreen></iframe>`);
        }
    });
    return out.join('\n');
}
function escapeHtml(s) { if (!s) return ''; return String(s).replace(/[&<>"'`=\/]/g, function (x) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;', '/': '&#x2F;', '`': '&#x60;', '=': '&#x3D;' }[x]; }); }

btnPreview && btnPreview.addEventListener('click', () => {
    const fields = {
        title: postTitle.value,
        date: postDate.value,
        time: postTime.value,
        location: postLocation.value,
        bio: postBio.value,
        content: postContent.value,
        imagesHtml: '',
        videosHtml: parseVideosHtml(postVideos.value)
    };
    if (postImages.files && postImages.files.length) {
        fields.imagesHtml = Array.from(postImages.files).map(f => `<div class="img-file-name">${escapeHtml(f.name)}</div>`).join('');
    }
    previewArea.innerHTML = `
    <h1>${escapeHtml(fields.title)}</h1>
    <div class="meta">📅 <span id="date">${escapeHtml(fields.date)}</span> | 🕓 <span id="time">${escapeHtml(fields.time)}</span> | 📍 <span id="location">${escapeHtml(fields.location)}</span></div>
    <div class="bio">${escapeHtml(fields.bio)}</div>
    <div class="content">${fields.content}</div>
    <div class="images">${fields.imagesHtml}</div>
    <div class="videos">${fields.videosHtml}</div>
  `;
    previewWrap.classList.remove('hidden');
});
btnHidePreview && btnHidePreview.addEventListener('click', () => previewWrap.classList.add('hidden'));

/* editor toolbar helpers */
document.getElementById('btnBold').addEventListener('click', () => insertAtCursor(postContent, '<strong>bold</strong>'));
document.getElementById('btnItalic').addEventListener('click', () => insertAtCursor(postContent, '<em>italic</em>'));
document.getElementById('btnLink').addEventListener('click', () => insertAtCursor(postContent, '<a href=\"https://example.com\" target=\"_blank\">link</a>'));
document.getElementById('btnIframe').addEventListener('click', () => insertAtCursor(postContent, '<iframe src=\"https://www.youtube.com/embed/VIDEO_ID\" allowfullscreen></iframe>'));
function insertAtCursor(input, text) {
    if (!input) return;
    const start = input.selectionStart, end = input.selectionEnd;
    const v = input.value;
    input.value = v.substring(0, start) + text + v.substring(end);
    input.selectionStart = input.selectionEnd = start + text.length;
    input.focus();
}

/* -------- events -------- */
postForm && postForm.addEventListener('submit', handleSave);
btnCancel && btnCancel.addEventListener('click', () => { postForm.reset(); formWrap.classList.add('hidden'); });
btnCloseForm && btnCloseForm.addEventListener('click', () => formWrap.classList.add('hidden'));
btnNew && btnNew.addEventListener('click', openNewForm);
btnShowAll && btnShowAll.addEventListener('click', () => { filterProject.value = ''; loadPosts(); });
filterProject && filterProject.addEventListener('change', () => renderPosts(filterProject.value || null));
searchInput && searchInput.addEventListener('input', () => renderPosts(filterProject.value || null));

/* bootstrap */
(async function init() {
    await loadProjects();
    await loadPosts();
})();



