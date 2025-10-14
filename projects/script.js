document.addEventListener('DOMContentLoaded', () => {
    const projectsFeed = document.getElementById('projects-feed');
    const postsList = document.getElementById('project-posts-list');
    const postsTitle = document.getElementById('posts-title');
    const projectsView = document.getElementById('projects-view');
    const postsView = document.getElementById('posts-view');
    const backButton = document.getElementById('back-to-projects');
    const loadingMessage = document.getElementById('loading-projects');
    const themeToggle = document.getElementById('theme-toggle');

    let allPostsData = [];
    let allProjectsData = [];

    // --- Utility Functions ---

    // Sorts posts by date (latest to oldest)
    function sortPosts(posts) {
        return posts.sort((a, b) => {
            const dateA = new Date(a.date);
            const dateB = new Date(b.date);
            return dateB - dateA; // Latest date first
        });
    }

    // --- View Switching Functions ---

    function showPostsView() {
        projectsView.classList.add('hidden');
        postsView.classList.remove('hidden');
        window.scrollTo(0, 0); // Scroll to top when switching view
    }

    function showProjectsView() {
        postsView.classList.add('hidden');
        projectsView.classList.remove('hidden');
        window.scrollTo(0, 0); // Scroll to top when switching view
    }

    // --- Rendering Functions ---

    function renderProjectCards(projects) {
        if (!projects || projects.length === 0) {
            loadingMessage.textContent = "No projects found in projects.json.";
            return;
        }

        loadingMessage.style.display = 'none';
        projectsFeed.innerHTML = '';

        projects.forEach(project => {
            const projectSlug = project.slug; 
            const fallbackThumbnail = 'https://via.placeholder.com/600x400?text=Project+Thumbnail';
            const thumbnailPath = project.thumbnail || fallbackThumbnail;

            const card = document.createElement('div');
            card.classList.add('project-card');
            card.dataset.slug = projectSlug;
            
            // Add click listener for filtering
            card.addEventListener('click', () => filterAndShowPosts(projectSlug, project.name));

            card.innerHTML = `
                <div class="project-card-image">
                    <img src="${thumbnailPath}" alt="${project.name} Thumbnail" onerror="this.onerror=null;this.src='${fallbackThumbnail}';">
                </div>
                <div class="project-card-content">
                    <h3>${project.name}</h3>
                    <p>${project.bio}</p>
                </div>
            `;
            projectsFeed.appendChild(card);
        });
    }

    function renderFilteredPosts(posts) {
        postsList.innerHTML = ''; 

        if (!posts || posts.length === 0) {
            postsList.innerHTML = '<p style="text-align:center; color: var(--text-secondary);">No posts found for this project.</p>';
            return;
        }

        const sortedPosts = sortPosts(posts);

        sortedPosts.forEach(post => {
            const postDate = new Date(post.date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
            const postUrl = post.file; 
            const fallbackThumbnail = 'https://via.placeholder.com/600x200?text=Post+Image';
            const thumbnailPath = post.thumbnail || fallbackThumbnail;

            const cardLink = document.createElement('a');
            cardLink.href = postUrl;
            // Removed: cardLink.target = "_blank"; 
            cardLink.classList.add('post-card');

            cardLink.innerHTML = `
                <div class="project-card-image">
                    <img src="${thumbnailPath}" alt="${post.title} Thumbnail" onerror="this.onerror=null;this.src='${fallbackThumbnail}';">
                </div>
                <div class="post-card-content">
                    <h3>${post.title}</h3>
                    <div class="post-meta">
                        📅 ${postDate}
                    </div>
                    <p>${post.desc}</p>
                    <span class="post-read-link">Read Post →</span>
                </div>
            `;
            postsList.appendChild(cardLink);
        });
    }

    // The core filtering function
    function filterAndShowPosts(slug, name) {
        // Filter posts where the post's 'file' path contains the project's 'slug'
        const filteredPosts = allPostsData.filter(post => {
            return post.file && post.file.includes(`/${slug}/`); 
        });

        // MODIFIED LINE: Title is stacked and clean
        postsTitle.innerHTML = `Posts for<br>${name}`;
        
        renderFilteredPosts(filteredPosts);
        showPostsView();
    }


    // --- Data Fetching ---

    async function fetchAllData() {
        try {
            // Fetch projects.json
            const projectsResponse = await fetch('projects.json');
            if (!projectsResponse.ok) throw new Error(`HTTP error fetching projects!`);
            allProjectsData = await projectsResponse.json();

            // Fetch posts.json
            const postsResponse = await fetch('posts.json');
            if (!postsResponse.ok) {
                 console.warn("posts.json not found or inaccessible. Proceeding with project data only.");
                 allPostsData = [];
            } else {
                 allPostsData = await postsResponse.json();
            }
            
            // Initial render: show all project cards
            renderProjectCards(allProjectsData);

        } catch (e) {
            console.error("Failed to load data:", e);
            loadingMessage.textContent = "Error loading data. Ensure projects.json and posts.json are accessible.";
        }
    }

    // --- Event Listeners & Theme ---

    // This makes the "Back to Projects" button work seamlessly
    backButton.addEventListener('click', showProjectsView);

    // Initial theme setup
    const currentTheme = localStorage.getItem('theme') || 'dark';
    if (currentTheme === 'light') {
        document.body.classList.add('light-theme');
        themeToggle.textContent = '🌙 Switch to Dark';
    } else {
        themeToggle.textContent = '☀️ Switch to Light';
    }

    themeToggle.addEventListener('click', () => {
        const isLight = document.body.classList.toggle('light-theme');
        if (isLight) {
            localStorage.setItem('theme', 'light');
            themeToggle.textContent = '🌙 Switch to Dark';
        } else {
            localStorage.setItem('theme', 'dark');
            themeToggle.textContent = '☀️ Switch to Light';
        }
    });

    // Start data load
    fetchAllData();
});
