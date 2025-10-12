// cms.js - client JS for the CMS
// Basic AJAX + DOM UI. No framework.

const API = './cms.php';
const workArea = document.getElementById('workArea');
const statusBar = document.getElementById('statusBar');

function setStatus(msg, level = 'info') {
  statusBar.textContent = msg;
  statusBar.style.color = level === 'error' ? '#b00' : '#334';
}

// small wrapper to POST formdata
async function postForm(data) {
  try {
    const res = await fetch(API, { method: 'POST', body: data });
    const json = await res.json();
    return json;
  } catch (e) {
    console.error(e);
    return { status: 'error', msg: e.message || 'network error' };
  }
}

async function apiGet(action) {
  try {
    const res = await fetch(API + '?action=' + encodeURIComponent(action));
    return await res.json();
  } catch (e) {
    return { status: 'error', msg: e.message || 'network error' };
  }
}

/* ========== UI helpers ========== */

function clearWork() { workArea.innerHTML = ''; }
function el(tag, cls, html) {
  const node = document.createElement(tag);
  if (cls) node.className = cls;
  if (html !== undefined) node.innerHTML = html;
  return node;
}

/* ===== NEW PROJECT FORM ===== */

function showNewProjectForm() {
  clearWork();
  const h = el('h2', '', 'Create New Project');
  const form = el('form', '');
  form.innerHTML = `
    <div class="form-row">
      <label>Title</label>
      <input type="text" name="title" required>
    </div>
    <div class="form-row">
      <label>Short bio</label>
      <textarea name="bio"></textarea>
    </div>
    <div class="form-row">
      <label>Date (optional)</label>
      <input type="date" name="date">
    </div>
    <div class="form-row">
      <label>Thumbnail (optional)</label>
      <input type="file" name="thumbnail" accept="image/*">
    </div>
    <div class="button-row">
      <button class="btn" type="submit">Create Project</button>
      <button class="btn ghost" type="button" id="cancelNewProject">Cancel</button>
    </div>
  `;
  workArea.appendChild(h);
  workArea.appendChild(form);

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    setStatus('Creating project...');
    const fd = new FormData(form);
    fd.append('action', 'createProject');
    const res = await postForm(fd);
    if (res.status === 'ok') {
      setStatus('Project created.');
      renderProjectList(); // refresh list
    } else {
      setStatus('Error: ' + (res.msg || 'unknown'), 'error');
    }
  });
  document.getElementById('cancelNewProject').onclick = () => { clearWork(); setStatus('Cancelled'); };
}

/* ===== LIST PROJECTS ===== */

async function renderProjectList() {
  setStatus('Loading projects...');
  clearWork();
  const json = await apiGet('listProjects');
  if (json.status !== 'ok') { setStatus('Failed to load projects', 'error'); return; }
  const list = el('div', 'card-list', '');
  json.projects.forEach(p => {
    const it = el('div', 'card-item', '');
    const left = el('div', '', `<strong>${p.title}</strong><div class="meta">${p.bio || ''}</div>`);
    const controls = el('div', 'controls-inline', '');
    const btnOpen = document.createElement('button'); btnOpen.className = 'btn-small'; btnOpen.textContent = 'Open';
    const btnEdit = document.createElement('button'); btnEdit.className = 'btn-small'; btnEdit.textContent = 'Edit';
    const btnDelete = document.createElement('button'); btnDelete.className = 'btn-small danger'; btnDelete.textContent = 'Delete';
    btnOpen.onclick = () => { window.open('../index.html'); };
    btnEdit.onclick = () => showEditProjectForm(p);
    btnDelete.onclick = async () => {
      if (!confirm('Delete project and its posts? This is permanent.')) return;
      setStatus('Deleting project...');
      const f = new FormData(); f.append('action', 'deleteProject'); f.append('slug', p.slug);
      const res = await postForm(f);
      if (res.status === 'ok') { setStatus('Deleted'); renderProjectList(); } else setStatus('Error: ' + res.msg, 'error');
    };
    controls.appendChild(btnOpen);
    controls.appendChild(btnEdit);
    controls.appendChild(btnDelete);

    it.appendChild(left);
    it.appendChild(controls);
    list.appendChild(it);
  });
  workArea.appendChild(el('h2', '', '' + json.projects.length + ' Projects'));
  workArea.appendChild(list);
  setStatus('Projects loaded');
}

/* ===== EDIT PROJECT ===== */

function showEditProjectForm(project) {
  clearWork();
  const h = el('h2', '', 'Edit Project: ' + project.title);
  const form = el('form', '');
  form.innerHTML = `
    <div class="form-row"><label>Title</label><input type="text" name="title" value="${escapeHtml(project.title)}"></div>
    <div class="form-row"><label>Bio</label><textarea name="bio">${escapeHtml(project.bio || '')}</textarea></div>
    <div class="form-row"><label>Date</label><input type="date" name="date" value="${project.date || ''}"></div>
    <div class="form-row"><label>Replace thumbnail</label><input type="file" name="thumbnail" accept="image/*"></div>
    <div class="button-row"><button class="btn" type="submit">Update</button><button class="btn ghost" id="cancelEdit" type="button">Cancel</button></div>
  `;
  workArea.appendChild(h);
  workArea.appendChild(form);
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    setStatus('Updating project...');
    const fd = new FormData(form);
    fd.append('action', 'updateProject');
    fd.append('slug', project.slug);
    const res = await postForm(fd);
    if (res.status === 'ok') { setStatus('Project updated'); renderProjectList(); } else setStatus('Err: ' + res.msg, 'error');
  });
  document.getElementById('cancelEdit').onclick = () => { clearWork(); setStatus('Cancelled'); };
}

/* ===== POSTS UI ===== */

async function renderPostList() {
  setStatus('Loading posts...');
  clearWork();
  const json = await apiGet('listPosts');
  if (json.status !== 'ok') { setStatus('Failed to load posts', 'error'); return; }
  const list = el('div', 'card-list', '');
  json.posts.forEach(p => {
    const it = el('div', 'card-item', '');
    const left = el('div', '', `<strong>${p.title}</strong><div class="meta">${p.project || ''} • ${p.date || ''}</div>`);
    const controls = el('div', 'controls-inline', '');
    const btnView = document.createElement('button'); btnView.className = 'btn-small'; btnView.textContent = 'View';
    const btnEdit = document.createElement('button'); btnEdit.className = 'btn-small'; btnEdit.textContent = 'Edit';
    const btnDelete = document.createElement('button'); btnDelete.className = 'btn-small danger'; btnDelete.textContent = 'Delete';
    btnView.onclick = () => { window.open('../' + (p.path || ('assets/' + (p.project_slug || '') + '/posts/' + slugify(p.title) + '.html')), '_blank'); };
    btnEdit.onclick = () => showEditPostForm(p);
    btnDelete.onclick = async () => {
      if (!confirm('Delete post? This is permanent.')) return;
      setStatus('Deleting post...');
      const f = new FormData(); f.append('action', 'deletePost'); f.append('title', p.title);
      const res = await postForm(f);
      if (res.status === 'ok') { setStatus('Deleted'); renderPostList(); } else setStatus('Error: ' + res.msg, 'error');
    };
    controls.appendChild(btnView); controls.appendChild(btnEdit); controls.appendChild(btnDelete);
    it.appendChild(left); it.appendChild(controls); list.appendChild(it);
  });
  workArea.appendChild(el('h2', '', '' + json.posts.length + ' Posts'));
  workArea.appendChild(list);
  setStatus('Posts loaded');
}

function showNewPostFormFor(projectTitle) {
  // load project selection.
  clearWork();
  const h = el('h2', '', 'Create New Post');
  const form = el('form', '');
  form.innerHTML = `
    <div class="form-row"><label>Project (title)</label><input type="text" name="project" value="${escapeHtml(projectTitle || '')}" required></div>
    <div class="form-row"><label>Post Title</label><input type="text" name="title" required></div>
    <div class="form-row"><label>Short bio</label><textarea name="bio"></textarea></div>
    <div class="form-row"><label>Date</label><input type="date" name="date"></div>
    <div class="form-row"><label>Content (HTML allowed)</label><textarea name="content" rows="8"></textarea></div>
    <div class="form-row"><label>Thumbnail (optional)</label><input type="file" name="thumbnail" accept="image/*"></div>
    <div class="form-row"><label>Images (optional)</label><input type="file" name="images[]" multiple accept="image/*"></div>
    <div class="button-row"><button class="btn" type="submit">Create Post</button><button class="btn ghost" id="cancelPost">Cancel</button></div>
  `;
  workArea.appendChild(h); workArea.appendChild(form);
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    setStatus('Creating post...');
    const fd = new FormData(form); fd.append('action', 'createPost');
    const res = await postForm(fd);
    if (res.status === 'ok') { setStatus('Post created'); renderPostList(); } else setStatus('Error: ' + res.msg, 'error');
  });
  document.getElementById('cancelPost').onclick = () => { clearWork(); setStatus('Cancelled'); };
}

function showEditPostForm(post) {
  clearWork();
  const h = el('h2', '', 'Edit Post: ' + post.title);
  const form = el('form', '');
  form.innerHTML = `
    <div class="form-row"><label>Project (title)</label><input type="text" name="project" value="${escapeHtml(post.project || '')}" required></div>
    <div class="form-row"><label>Original Title (read-only)</label><input type="text" name="orig_title" value="${escapeHtml(post.title)}" readonly></div>
    <div class="form-row"><label>New Title</label><input type="text" name="title" value="${escapeHtml(post.title)}"></div>
    <div class="form-row"><label>Short bio</label><textarea name="bio">${escapeHtml(post.bio || '')}</textarea></div>
    <div class="form-row"><label>Date</label><input type="date" name="date" value="${post.date || ''}"></div>
    <div class="form-row"><label>Content (HTML allowed)</label><textarea name="content" rows="8"></textarea></div>
    <div class="form-row"><label>Replace thumbnail</label><input type="file" name="thumbnail" accept="image/*"></div>
    <div class="form-row"><label>Upload images (optional)</label><input type="file" name="images[]" multiple accept="image/*"></div>
    <div class="button-row"><button class="btn" type="submit">Update Post</button><button class="btn ghost" id="cancelEditPost">Cancel</button></div>
  `;
  workArea.appendChild(h); workArea.appendChild(form);

  // Optionally load existing post content into textarea via fetch of its path
  (async function tryLoadContent() {
    if (post.path) {
      try {
        const res = await fetch('../' + post.path);
        if (res.ok) {
          const text = await res.text();
          // naive extract of content div - not perfect, but helpful
          const m = text.match(/<div class=["']content["'][^>]*>([\s\S]*?)<\/div>/i);
          if (m) form.querySelector('[name=content]').value = m[1].trim();
        }
      } catch (e) { /* ignore */ }
    }
  })();

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    setStatus('Updating post...');
    const fd = new FormData(form); fd.append('action', 'updatePost');
    const res = await postForm(fd);
    if (res.status === 'ok') { setStatus('Post updated'); renderPostList(); } else setStatus('Err: ' + res.msg, 'error');
  });
  document.getElementById('cancelEditPost').onclick = () => { clearWork(); setStatus('Cancelled'); };
}

/* ===== misc helpers ===== */
function escapeHtml(s) {
  if (!s) return '';
  return String(s).replace(/[&<>"']/g, function (m) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]; });
}
function slugify(s) {
  return String(s || '').toLowerCase().replace(/[^\w\s-]/g, '').trim().replace(/\s+/g, '-');
}

/* ===== wire left-side buttons ===== */
document.getElementById('btnNewProject').addEventListener('click', () => showNewProjectForm());
document.getElementById('btnListProjects').addEventListener('click', () => renderProjectList());
document.getElementById('btnListPosts').addEventListener('click', () => renderPostList());

/* ===== init ===== */
setStatus('CMS ready');
renderProjectList();
/*===== Open Post CMS ===*/
document.addEventListener("DOMContentLoaded", () => {
  const btnPostCMS = document.getElementById("btnOpenPostCMS");
  if (btnPostCMS) {
    btnPostCMS.addEventListener("click", () => {
      // Load in the same window instead of opening a new tab
      window.location.href = "./post-index.html";
    });
  } else {
    console.error("⚠️ btnOpenPostCMS not found in DOM");
  }
});
