# 👋 Welcome to Mahdi’s Website

🗓️ **Date:** 2025-10-14 09:55  
🌐 **Live Site:** https://mahdiyasser.site  
🧱 **Version:** V10.0 — *“The Golden Build”*  
📦 **Status:** FANTASTIC  

---

## 📖 Table of Contents

1. Quick Links  
2. What Is My Website  
3. Content Management Systems  
4. Front-End Overview  
5. Blog System (Front-End)  
6. Projects Section (Front-End) 
7. How To Customize  
8. Development Stats  
9. Repositories  
10. CMS Previews  
11. Technical Notes  
12. Final Thoughts  

---

## 🌐 Quick Links

🏠 Main Site — https://mahdiyasser.site  
📝 Blog — https://mahdiyasser.site/blog  
💼 Projects — https://mahdiyasser.site/projects  
📊 Dashboard — https://mahdiyasser.site/dashboard  

---

## 🧠 What Is My Website

My website is a **mix of a personal portfolio, blog, and project hub**, built fully from scratch using **PHP, HTML, CSS, and JavaScript** — no WordPress, no frameworks, no builder tools.

Each part (Blog + Projects) is **modular**, meaning you can copy them as standalone sites and they’ll still work perfectly with some paths editing.

To access the CMS:

https://your-domain/cms

From there, you can pick:
- 📰 Blog CMS  
- 🚀 Projects CMS  

---

## ⚙️ Content Management Systems (CMS)

### 📰 Blog CMS
A lightweight CMS built for **easy content creation** with zero coding required.

**Form Fields:**
1. Title  
2. Date  
3. Time  
4. Bio  
5. Content  
6. Thumbnail  
7. Images  

You can edit, delete, or preview posts easily.  
All posts automatically appear on the main blog page as cards.

---

### 🚀 Projects CMS
Functions like the Blog CMS, but with **project folders and organization tools** built in.

**Project Post Form:**
1. Choose existing project
2. Title  
3. Date  
4. Time  
5. Bio  
6. Content  
7. Thumbnail  
8. Images  

**Project Creation Form:**
1. Title  
2. Date  
3. Time  
4. Bio  
5. Thumbnail  

**Extras:**
- Edit or delete projects  
- Move posts between projects  
- Same clean UI as Blog CMS for consistency  

---

## 🖥️ Front-End Overview

Visit the live site → https://mahdiyasser.site  

### Homepage Layout:
1. Personal picture (PFP)  
2. 4 social media icons  
3. Long bio / description  
4. 3 external server cards  
5. 2 directory cards → Blog & Projects  
6. Footer with site timeline / milestones  

To customize → edit `index.html` (bio, links, images, footer).  

---

## 🗞️ Blog System (Front-End)

Blog posts appear dynamically as cards.  
Each post entry is structured like this:


```json
{
  "title": "<post-title>",
  "date": "<yyyy-mm-dd>",
  "thumbnail": "<thumbnail-path>",
  "file": "<post-link>",
  "desc": "<post-bio>"
}
```

✅ CMS-managed  
✅ Includes “Read Post” buttons  

---

## 🧩 Projects Section

Projects display in card format just like the blog, but with a project-based filter system.

**Project Entry Example:**

```json
{
  "title": "<project-title>",
  "date": "<yyyy-mm-dd>",
  "thumbnail": "<project-thumbnail>",
  "file": "<project-link>",
  "desc": "<project-bio>"
}
```

**Project Post Example:**
```json
{
  "tag": "1st-project",
  "title": "1st-post",
  "date": "2025-10-14 03:31",
  "thumbnail": "/projects/assets/1st-project/images/1st-post/thumbnail.jpg",
  "file": "/projects/assets/1st-project/posts/1st-post.html",
  "desc": "test test test test test test test",
  "location": "Hosh Issa, Beheira, Egypt"
}
```

📍 The *location* field only shows inside posts (keeps layout clean).  

---

## 🧰 How To Customize

1. Open `index.html`  
2. Edit metadata. images, links, bio, and footer  
3. Don’t modify JS/CSS unless you know what you’re doing  
4. Inside `/blog` or `/projects`, only edit index.html body and footer  
5. Keep the `/assets/` folder untouched — it’s path-critical for the CMS  

---

## 🕒 Development Stats

| Metric | Detail |
|--------|--------|
| Duration | 2025-10-01 → 2025-10-14 |
| Work Per Day | ~6 hours |
| Total Time | ≈ 84+ hours |

---

## 📦 Repositories

### 🧩 Template Repo  
https://github.com/Mahdiyasser/website-template  
Same structure as the main site, but stripped of posts and projects — lightweight and easy to customize.  

### 🔧 Add-ons Repo  
https://github.com/Mahdiyasser/website-DLCs  
Includes plug-and-play add-ons.  

Each add-on comes with:  
- README.md  
- Folder containing files    

Example Add-on → **Dashboard**  
Live Demo: https://mahdiyasser.site/dashboard  

Don’t want it? Just delete `/dashboard` after cloning.  

---

## 🖼️ CMS Previews

🖥️ CMS can be accessed locally or through **any real web hosting service** — it runs on **any web server**, not just Apache or Nginx.

- Blog CMS
- ![Blog CMS Screenshot 1](https://mahdiyasser.site/img/cms1.png)
- ![Blog CMS Screenshot 2](https://mahdiyasser.site/img/cms2.png)
- Projects CMS
- ![Projects CMS Screenshot 1](https://mahdiyasser.site/img/cms3.png) 
- ![Projects CMS Screenshot 2](https://mahdiyasser.site/img/cms4.png) 

---

## 🧩 Technical Notes

- Both CMS are PHP-based (≈900–1400 lines each).  
- Compatible with **any real hosting platform or web server** (Apache, Nginx, LiteSpeed, etc).  
- Fully modular — every section can be moved or reused independently with paths editing.  
- Optimized for performance, readability, and low latency.  

---

## 🎯 Final Thoughts

This project represents **two weeks of hard, detailed work** .  
No frameworks. No templates. Just raw logic, precision, and creativity.  

It’s designed to be:
- 💡 Simple  
- ⚙️ Modular  
- ⚡ Lightweight  
- 🧱 Reliable on any host  

Fork it. Remix it. Make it yours —  
just **don’t break the paths** 😉  

---

**Made with 💻, ☕, and 84 hours of pure grind.**  
© 2025 [Mahdi Yasser](https://mahdiyasser.site)
