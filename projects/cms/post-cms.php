<?php
/**
 * post-cms.php
 * Backend for Post CMS
 *
 * Place in: /var/www/html/projects/cms/post-cms.php
 *
 * Actions (via POST form-data):
 * - action=createPost
 * - action=updatePost
 * - action=deletePost
 *
 * Reads/writes:
 * - /var/www/html/projects/posts.json
 * - /var/www/html/projects/assets/<project-slug>/images/
 * - /var/www/html/projects/assets/<project-slug>/posts/<post-slug>.html
 *
 * Note: Run behind Apache. Ensure file_uploads=On in php.ini.
 */

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');

/* ---------- base paths ---------- */
$ROOT = realpath(__DIR__ . '/..');               // /var/www/html/projects
$ASSETS = $ROOT . '/assets';
$POSTS_JSON = $ROOT . '/posts.json';
$PROJECTS_JSON = $ROOT . '/projects.json';

/* ---------- ensure files exist ---------- */
if (!file_exists($POSTS_JSON)) file_put_contents($POSTS_JSON, json_encode([], JSON_PRETTY_PRINT));
if (!file_exists($PROJECTS_JSON)) file_put_contents($PROJECTS_JSON, json_encode([], JSON_PRETTY_PRINT));
if (!is_dir($ASSETS)) @mkdir($ASSETS, 0777, true);

/* ---------- helpers ---------- */
function respond($arr){ echo json_encode($arr, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES); exit; }
function read_json($p){ if(!file_exists($p)) return []; $t=@file_get_contents($p); $j=@json_decode($t,true); return is_array($j)?$j:[]; }
function write_json($p,$a){ return @file_put_contents($p,json_encode($a, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) !== false; }
function slug($s){ $s = trim((string)$s); $s = iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$s); $s = preg_replace('/[^\w\s\-]/','',$s); $s = preg_replace('/\s+/','-',$s); $s = trim($s, "-_"); if($s==='') $s='item-'.substr(bin2hex(random_bytes(3)),0,6); return strtolower($s); }
function safe_unlink($f){ if(file_exists($f)) @unlink($f); }
function rrmdir($dir){ if(!is_dir($dir)) return; $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST); foreach($it as $file){ if($file->isDir()) @rmdir($file->getRealPath()); else @unlink($file->getRealPath()); } @rmdir($dir); }

/* ---------- CORS preflight convenience ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin:*');
    header('Access-Control-Allow-Methods:GET,POST,OPTIONS');
    header('Access-Control-Allow-Headers:Content-Type');
    respond(['status'=>'ok']);
}
header('Access-Control-Allow-Origin:*');

/* ---------- route ---------- */
$action = $_POST['action'] ?? null;
if (!$action) respond(['status'=>'error','msg'=>'action required']);

/* ---------- ensure project directories ---------- */
function ensure_project_dirs($project_slug){
    global $ASSETS;
    $base = $ASSETS . DIRECTORY_SEPARATOR . $project_slug;
    $images = $base . DIRECTORY_SEPARATOR . 'images';
    $posts = $base . DIRECTORY_SEPARATOR . 'posts';
    if (!is_dir($base)) @mkdir($base, 0777, true);
    if (!is_dir($images)) @mkdir($images, 0777, true);
    if (!is_dir($posts)) @mkdir($posts, 0777, true);
    return [$base,$images,$posts];
}

/* ---------- parse video links into iframe HTML ---------- */
function parse_videos($raw){
    $lines = array_filter(array_map('trim', explode("\n", (string)$raw)));
    $out = [];
    foreach($lines as $v){
        if (strpos($v,'youtube.com/watch')!==false && preg_match('/v=([A-Za-z0-9_\-]+)/',$v,$m)) {
            $id = $m[1]; $out[] = "<iframe src=\"https://www.youtube.com/embed/{$id}\" allowfullscreen></iframe>";
        } elseif (strpos($v,'youtu.be/')!==false && preg_match('/youtu\.be\/([A-Za-z0-9_\-]+)/',$v,$m)) {
            $id = $m[1]; $out[] = "<iframe src=\"https://www.youtube.com/embed/{$id}\" allowfullscreen></iframe>";
        } else {
            $out[] = "<iframe src=\"".htmlspecialchars($v,ENT_QUOTES)."\" allowfullscreen></iframe>";
        }
    }
    return implode("\n", $out);
}

/* ---------- sanitize content for template where needed ---------- */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES); }

/* ---------- create post ---------- */
if ($action === 'createPost') {
    $project = trim($_POST['project'] ?? '');
    $project_slug = trim($_POST['project_slug'] ?? slug($project));
    $title = trim($_POST['title'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $content = $_POST['content'] ?? '';
    $videos_raw = trim($_POST['videos'] ?? '');
    $date = trim($_POST['date'] ?? date('Y-m-d'));
    $time = trim($_POST['time'] ?? '');
    $location = trim($_POST['location'] ?? '');

    if (!$project || !$title) respond(['status'=>'error','msg'=>'project and title required']);

    list($base,$imagesDir,$postsDir) = ensure_project_dirs($project_slug);
    $postSlug = slug($title);

    // handle thumbnail
    $thumbnailName = "{$postSlug}-thumbnail.jpg";
    if (!empty($_FILES['thumbnail']['tmp_name'])) {
        $ext = pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION) ?: 'jpg';
        $ext = preg_replace('/[^A-Za-z0-9]/','',$ext);
        $thumbnailName = "{$postSlug}-thumbnail.{$ext}";
        move_uploaded_file($_FILES['thumbnail']['tmp_name'], $imagesDir . DIRECTORY_SEPARATOR . $thumbnailName);
    }

    // handle images[]
    $savedImages = [];
    if (!empty($_FILES['images']['name'][0])) {
        foreach($_FILES['images']['name'] as $i=>$n) {
            $tmp = $_FILES['images']['tmp_name'][$i] ?? null; if(!$tmp) continue;
            $ext = pathinfo($n, PATHINFO_EXTENSION) ?: 'jpg'; $ext = preg_replace('/[^A-Za-z0-9]/','',$ext);
            $imgName = "{$postSlug}-image" . ($i+1) . ".{$ext}";
            move_uploaded_file($tmp, $imagesDir . DIRECTORY_SEPARATOR . $imgName);
            $savedImages[] = $imgName;
        }
    }

    // prepare images HTML
    $imgsHtml = '';
    foreach($savedImages as $im) $imgsHtml .= "<img src=\"/projects/assets/{$project_slug}/images/{$im}\" alt=\"{$im}\">\n";

    // videos html
    $videosHtml = parse_videos($videos_raw);

    // build final HTML using the exact template you provided (kept style & markup)
    $html = '<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>' . h($title) . '</title>
    <style>
        body { font-family: "Poppins", sans-serif; background: #f8fbff; color: #1a1a1a; margin:0; padding:0; }
        .container { max-width:850px; margin:50px auto; background:#ffffff; border-radius:20px; padding:40px; box-shadow:0 10px 40px rgba(0,0,0,0.08); border:1px solid #d9eafd; }
        h1{ color:#0078d4; margin-bottom:10px; font-size:2rem; }
        .meta{ font-size:0.9rem; color:#666; margin-bottom:20px; }
        .bio{ font-style:italic; color:#444; margin-bottom:25px; border-left:4px solid #0078d4; padding-left:12px; background:#f0f7ff; border-radius:6px; }
        .content{ line-height:1.8; font-size:1rem; color:#333; }
        .content p{ margin-bottom:18px; }
        .images{ display:grid; grid-template-columns: repeat(auto-fit, minmax(200px,1fr)); gap:12px; margin-top:25px; }
        .images img{ width:100%; border-radius:12px; transition:0.3s; cursor:pointer; box-shadow:0 4px 12px rgba(0,0,0,0.05); }
        .images img:hover{ transform:scale(1.04); opacity:0.9; }
        .videos{ margin-top:35px; display:grid; grid-template-columns:1fr; gap:20px; }
        .videos iframe{ width:100%; height:400px; border-radius:15px; border:none; box-shadow:0 6px 20px rgba(0,120,212,0.1); }
        footer{ margin-top:40px; text-align:center; font-size:0.95rem; color:#666; }
        a.back{ color:#0078d4; text-decoration:none; display:inline-block; margin-top:20px; font-weight:500; transition:0.3s; }
        a.back:hover{ color:#005fa3; text-decoration:underline; }
        .popup{ position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.9); display:flex; align-items:center; justify-content:center; opacity:0; pointer-events:none; transition:opacity 0.3s ease; z-index:9999; }
        .popup.active{ opacity:1; pointer-events:all; }
        .popup img{ max-width:90%; max-height:90%; border-radius:15px; box-shadow:0 0 25px rgba(0,0,0,0.5); }
        .popup span{ position:absolute; top:30px; right:40px; font-size:2.2rem; color:#fff; cursor:pointer; user-select:none; }
        @media (max-width:600px){ .container{ margin:20px; padding:25px; } .videos iframe{ height:250px; } }
    </style>
</head>

<body>
    <div class="container">
        <h1>' . h($title) . '</h1>
        <div class="meta">📅 <span id="date">' . h($date) . '</span> | 🕓 <span id="time">' . h($time) . '</span> | 📍 <span id="location">' . h($location) . '</span></div>

        <div class="bio">' . h($bio) . '</div>

        <div class="content">' . $content . '</div>

        <div class="images">' . $imgsHtml . '</div>

        <div class="videos">' . $videosHtml . '</div>

        <footer>
            <a href="/projects/index.html" class="back">← Back to Projects</a>
        </footer>
    </div>

    <div class="popup" id="popup"><span id="close">&times;</span><img src="" alt="popup image" id="popup-img"></div>

    <script>
        const popup = document.getElementById("popup");
        const popupImg = document.getElementById("popup-img");
        const close = document.getElementById("close");
        document.querySelectorAll(".images img").forEach(img => {
            img.addEventListener("click", () => {
                popup.classList.add("active");
                popupImg.src = img.src;
            });
        });
        close.addEventListener("click", () => popup.classList.remove("active"));
        popup.addEventListener("click", e => { if(e.target===popup) popup.classList.remove("active"); });
    </script>
</body>
</html>';

    // write file
    $postFile = $postsDir . DIRECTORY_SEPARATOR . "{$postSlug}.html";
    if (file_put_contents($postFile, $html) === false) respond(['status'=>'error','msg'=>'failed write html file']);

    // update posts.json
    $posts = read_json($POSTS_JSON);
    $entry = [
        'title' => $title,
        'bio' => $bio,
        'thumbnail' => "/projects/assets/{$project_slug}/images/{$thumbnailName}",
        'project' => $project,
        'project_slug' => $project_slug,
        'date' => $date,
        'time' => $time,
        'location' => $location,
        'videos' => array_filter(array_map('trim', explode("\n", $videos_raw))),
        'path' => "/projects/assets/{$project_slug}/posts/{$postSlug}.html",
        'contentRaw' => $content
    ];
    $posts[] = $entry;
    if (!write_json($POSTS_JSON, $posts)) {
        safe_unlink($postFile);
        respond(['status'=>'error','msg'=>'failed update posts.json']);
    }

    respond(['status'=>'ok','msg'=>'post created','post'=>$entry]);
}

/* ---------- updatePost ---------- */
if ($action === 'updatePost') {
    $project = trim($_POST['project'] ?? '');
    $project_slug = trim($_POST['project_slug'] ?? slug($project));
    $old_title = trim($_POST['old_title'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $content = $_POST['content'] ?? '';
    $videos_raw = trim($_POST['videos'] ?? '');
    $date = trim($_POST['date'] ?? date('Y-m-d'));
    $time = trim($_POST['time'] ?? '');
    $location = trim($_POST['location'] ?? '');

    if (!$project || !$old_title || !$title) respond(['status'=>'error','msg'=>'project/title required']);

    list($base,$imagesDir,$postsDir) = ensure_project_dirs($project_slug);
    $oldSlug = slug($old_title);
    $newSlug = slug($title);
    $oldFile = $postsDir . DIRECTORY_SEPARATOR . "{$oldSlug}.html";
    $newFile = $postsDir . DIRECTORY_SEPARATOR . "{$newSlug}.html";

    if (!file_exists($oldFile)) respond(['status'=>'error','msg'=>'original post not found']);

    // rename files & images if title changed
    if ($oldSlug !== $newSlug) {
        foreach (glob($imagesDir . DIRECTORY_SEPARATOR . "{$oldSlug}*") as $f) {
            $baseName = basename($f);
            $newName = preg_replace("/^{$oldSlug}/", $newSlug, $baseName);
            @rename($f, $imagesDir . DIRECTORY_SEPARATOR . $newName);
        }
        @rename($oldFile, $newFile);
    } else {
        $newFile = $oldFile;
    }

    // handle new thumbnail
    $thumbnailName = "{$newSlug}-thumbnail.jpg";
    foreach (glob($imagesDir . DIRECTORY_SEPARATOR . "{$newSlug}-thumbnail.*") as $g) { $thumbnailName = basename($g); break; }
    if (!empty($_FILES['thumbnail']['tmp_name'])) {
        $ext = pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION) ?: 'jpg'; $ext = preg_replace('/[^A-Za-z0-9]/','',$ext);
        $thumbnailName = "{$newSlug}-thumbnail.{$ext}";
        move_uploaded_file($_FILES['thumbnail']['tmp_name'], $imagesDir . DIRECTORY_SEPARATOR . $thumbnailName);
    }

    // new images
    if (!empty($_FILES['images']['name'][0])) {
        foreach($_FILES['images']['name'] as $i=>$n) {
            $tmp = $_FILES['images']['tmp_name'][$i] ?? null; if(!$tmp) continue;
            $ext = pathinfo($n, PATHINFO_EXTENSION) ?: 'jpg'; $ext = preg_replace('/[^A-Za-z0-9]/','',$ext);
            $imgName = "{$newSlug}-image".(time().rand(10,99)).".{$ext}";
            move_uploaded_file($tmp, $imagesDir . DIRECTORY_SEPARATOR . $imgName);
        }
    }

    // rebuild images HTML
    $savedImages = [];
    foreach (glob($imagesDir . DIRECTORY_SEPARATOR . "{$newSlug}-image*") as $f) $savedImages[] = basename($f);
    $imgsHtml = '';
    foreach ($savedImages as $im) $imgsHtml .= "<img src=\"/projects/assets/{$project_slug}/images/{$im}\" alt=\"{$im}\">\n";

    // videos html
    $videosHtml = parse_videos($videos_raw);

    // rebuild HTML (same template)
    $html = '<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/><title>' . h($title) . '</title>
<style>
/* same inline styles as create (keeps template consistent) */
body{font-family:"Poppins",sans-serif;background:#f8fbff;color:#1a1a1a;margin:0;padding:0}
.container{max-width:850px;margin:50px auto;background:#fff;border-radius:20px;padding:40px;box-shadow:0 10px 40px rgba(0,0,0,0.08);border:1px solid #d9eafd}
h1{color:#0078d4;margin-bottom:10px;font-size:2rem}.meta{font-size:.9rem;color:#666;margin-bottom:20px}.bio{font-style:italic;color:#444;margin-bottom:25px;border-left:4px solid #0078d4;padding-left:12px;background:#f0f7ff;border-radius:6px}.content{line-height:1.8;font-size:1rem;color:#333}.content p{margin-bottom:18px}.images{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-top:25px}.images img{width:100%;border-radius:12px;transition:.3s;cursor:pointer;box-shadow:0 4px 12px rgba(0,0,0,0.05)}.images img:hover{transform:scale(1.04);opacity:.9}.videos{margin-top:35px;display:grid;grid-template-columns:1fr;gap:20px}.videos iframe{width:100%;height:400px;border-radius:15px;border:none;box-shadow:0 6px 20px rgba(0,120,212,0.1)}footer{margin-top:40px;text-align:center;font-size:.95rem;color:#666}a.back{color:#0078d4;text-decoration:none;display:inline-block;margin-top:20px;font-weight:500;transition:.3s}a.back:hover{color:#005fa3;text-decoration:underline}.popup{position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.9);display:flex;align-items:center;justify-content:center;opacity:0;pointer-events:none;transition:opacity .3s ease;z-index:9999}.popup.active{opacity:1;pointer-events:all}.popup img{max-width:90%;max-height:90%;border-radius:15px;box-shadow:0 0 25px rgba(0,0,0,0.5)}.popup span{position:absolute;top:30px;right:40px;font-size:2.2rem;color:#fff;cursor:pointer;user-select:none}@media(max-width:600px){.container{margin:20px;padding:25px}.videos iframe{height:250px}}
</style>
</head>
<body>
<div class="container">
  <h1>' . h($title) . '</h1>
  <div class="meta">📅 <span id="date">' . h($date) . '</span> | 🕓 <span id="time">' . h($time) . '</span> | 📍 <span id="location">' . h($location) . '</span></div>
  <div class="bio">' . h($bio) . '</div>
  <div class="content">' . $content . '</div>
  <div class="images">' . $imgsHtml . '</div>
  <div class="videos">' . $videosHtml . '</div>
  <footer><a href="/projects/index.html" class="back">← Back to Projects</a></footer>
</div>
<div class="popup" id="popup"><span id="close">&times;</span><img src="" alt="popup" id="popup-img"></div>
<script>
const popup=document.getElementById("popup"),popupImg=document.getElementById("popup-img"),close=document.getElementById("close");
document.querySelectorAll(".images img").forEach(img=>img.addEventListener("click",()=>{popup.classList.add("active");popupImg.src=img.src}));
close.addEventListener("click",()=>popup.classList.remove("active"));popup.addEventListener("click",e=>{if(e.target===popup)popup.classList.remove("active")});
</script>
</body></html>';

    if (file_put_contents($newFile, $html) === false) respond(['status'=>'error','msg'=>'failed write updated post']);

    // update posts.json
    $posts = read_json($POSTS_JSON);
    foreach($posts as $i=>$p){
        if (($p['title'] ?? '') === $old_title && ($p['project'] ?? '') === $project) {
            $posts[$i]['title'] = $title;
            $posts[$i]['bio'] = $bio;
            $posts[$i]['date'] = $date;
            $posts[$i]['time'] = $time;
            $posts[$i]['location'] = $location;
            $posts[$i]['project'] = $project;
            $posts[$i]['project_slug'] = $project_slug;
            $posts[$i]['path'] = "/projects/assets/{$project_slug}/posts/{$newSlug}.html";
            $posts[$i]['thumbnail'] = "/projects/assets/{$project_slug}/images/{$thumbnailName}";
            $posts[$i]['contentRaw'] = $content;
            $posts[$i]['videos'] = array_filter(array_map('trim', explode("\n", $videos_raw)));
        }
    }
    write_json($POSTS_JSON, $posts);

    respond(['status'=>'ok','msg'=>'post updated']);
}

/* ---------- deletePost ---------- */
if ($action === 'deletePost') {
    $project = trim($_POST['project'] ?? '');
    $project_slug = trim($_POST['project_slug'] ?? slug($project));
    $title = trim($_POST['title'] ?? '');

    if (!$project || !$title) respond(['status'=>'error','msg'=>'project & title required']);

    list($base,$imagesDir,$postsDir) = ensure_project_dirs($project_slug);
    $postSlug = slug($title);
    $postFile = $postsDir . DIRECTORY_SEPARATOR . "{$postSlug}.html";

    safe_unlink($postFile);
    foreach(glob($imagesDir . DIRECTORY_SEPARATOR . "{$postSlug}*") as $f) safe_unlink($f);

    $posts = read_json($POSTS_JSON);
    $new = [];
    foreach($posts as $p) {
        if (($p['title'] ?? '') === $title && ($p['project'] ?? '') === $project) continue;
        $new[] = $p;
    }
    write_json($POSTS_JSON, $new);
    respond(['status'=>'ok','msg'=>'post deleted']);
}

/* ---------- unknown ---------- */
respond(['status'=>'error','msg'=>'unknown action']);
