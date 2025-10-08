
async function loadPosts() {
  const res = await fetch("projects.json");
  const posts = await res.json();
  const feed = document.getElementById("feed");

  posts.forEach(post => {
    const div = document.createElement("div");
    div.className = "post";
    div.innerHTML = `
          <img src="${post.thumbnail}" alt="${post.title}">
          <h2>${post.title}</h2>
          <small>${post.date}</small>
          <p>${post.desc}</p>
          <a href="${post.file}" style="color:#00ff99;">Read More →</a>
        `;
    feed.appendChild(div);
  });
}
loadPosts();
