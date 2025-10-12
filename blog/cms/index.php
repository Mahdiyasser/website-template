<?php
// Post Maker - Absolute
// by Mahdi & ChatGPT
// Upgraded: Edit (full), Cancel Edit, Update Post, PRG, Clear Form, preserve uploads
// Additional: rename slug + html + images folder when title changed,
// image delete (per-image), thumbnail separate and replace, sync posts.json & HTML paths.

ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

$baseDir = realpath(__DIR__ . '/../');
$postsDir = $baseDir . '/assets/posts';
$imagesBase = $baseDir . '/assets/images';
$jsonFile = $baseDir . '/posts.json';
$locationDefault = 'Hosh Issa, Beheira, Egypt';

$baseUrl = '/blog'; // 🔥 Main prefix for all absolute URLs

$message = '';
$errors = [];

// UTIL: slugify title
function slugify($text){
    $text = mb_strtolower(trim($text), 'UTF-8');
    $text = preg_replace('/[\s_]+/', '-', $text);
    $text = preg_replace('/[^a-z0-9\-]/', '', $text);
    $text = preg_replace('/-+/', '-', $text);
    return trim($text, '-');
}

// Ensure posts.json exists
if(!file_exists($jsonFile)) file_put_contents($jsonFile, json_encode([], JSON_PRETTY_PRINT));

// Load posts array early
$postsArr = json_decode(file_get_contents($jsonFile), true);
if(!is_array($postsArr)) $postsArr = [];

/**
 * Helper: find post index by slug
 */
function findPostIndexBySlug($arr, $slug){
    foreach($arr as $i => $p){
        $basename = pathinfo($p['file'] ?? '', PATHINFO_FILENAME);
        if($basename === $slug) return $i;
    }
    return false;
}

/**
 * Helper: read images list from images folder for a slug
 * returns web paths prefixed with $baseUrl (e.g. /blog/assets/images/slug/filename)
 */
function getImagesForSlug($imagesBase, $slug, $baseUrl){
    $dir = rtrim($imagesBase, '/\\') . '/' . $slug;
    $list = [];
    if(is_dir($dir)){
        $files = array_values(array_filter(glob($dir . '/*'), 'is_file'));
        sort($files);
        foreach($files as $f){
            $web = str_replace('\\','/', $f);
            $pos = strpos($web, '/assets/images');
            if($pos !== false){
                $rel = substr($web, $pos); // starts with /assets/images/...
                $list[] = rtrim($baseUrl, '/') . $rel;
            } else {
                $list[] = rtrim($baseUrl, '/') . '/assets/images/' . $slug . '/' . basename($f);
            }
        }
    }
    return $list;
}

/**
 * Helper: make unique slug among posts (excluding $excludeIndex if provided)
 */
function makeUniqueSlug($postsArr, $base, $excludeIndex = null){
    $slug = $base;
    $suffix = 1;
    $exists = true;
    while($exists){
        $exists = false;
        foreach($postsArr as $i => $p){
            if($excludeIndex !== null && $i === $excludeIndex) continue;
            $basename = pathinfo($p['file'] ?? '', PATHINFO_FILENAME);
            if($basename === $slug){
                $exists = true;
                break;
            }
        }
        if($exists){
            $suffix++;
            $slug = $base . '-' . $suffix;
        }
    }
    return $slug;
}

/**
 * Helper: safely rename directory (handles cases where target exists)
 */
function safeRenameDir($old, $new){
    if($old === $new) return true;
    if(!is_dir($old)) return false;
    if(is_dir($new)){
        // merge contents to new then remove old
        $files = glob(rtrim($old, '/\\') . '/*');
        foreach($files as $f){
            $dest = rtrim($new, '/\\') . '/' . basename($f);
            if(is_file($f)){
                @rename($f, $dest);
            } elseif(is_dir($f)){
                // recursive move (simple)
                safeRenameDir($f, $dest);
            }
        }
        // attempt to remove old dir
        @rmdir($old);
        return true;
    } else {
        return @rename($old, $new);
    }
}

/**
 * Helper: update image paths inside HTML content when slug changes
 */
function replaceSlugInHtml($htmlPath, $oldSlug, $newSlug, $baseUrl){
    if(!file_exists($htmlPath)) return;
    $html = file_get_contents($htmlPath);
    $oldPrefix = rtrim($baseUrl, '/') . '/assets/images/' . $oldSlug . '/';
    $newPrefix = rtrim($baseUrl, '/') . '/assets/images/' . $newSlug . '/';
    $html = str_replace($oldPrefix, $newPrefix, $html);
    // Also update posts link if present (file path)
    $oldPostPath = rtrim($baseUrl, '/') . '/assets/posts/' . $oldSlug . '.html';
    $newPostPath = rtrim($baseUrl, '/') . '/assets/posts/' . $newSlug . '.html';
    $html = str_replace($oldPostPath, $newPostPath, $html);
    file_put_contents($htmlPath, $html);
}

/**
 * Helper: rebuild images block HTML for a post from images folder and any manual URLs
 */
function buildImagesHTML($imagesList){
    $imagesHTML = '';
    foreach($imagesList as $imgRel){
        $imgRel = str_replace(' ', '-', $imgRel);
        $imagesHTML .= '<img src="' . htmlspecialchars($imgRel, ENT_QUOTES) . '" alt="Image">' . "\n";
    }
    return $imagesHTML;
}

/**
 * If ?clear=1 was requested (manual Clear Form), clear saved session form_data and redirect.
 */
if(isset($_GET['clear']) && $_GET['clear'] == '1'){
    unset($_SESSION['form_data']);
    header("Location: " . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

/**
 * Cancel edit: if ?cancel=1 present, we just redirect to base page (removes ?edit=slug)
 */
if(isset($_GET['cancel']) && $_GET['cancel'] == '1'){
    header("Location: " . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// === DELETE POST (full delete) ===
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_post']) && !empty($_POST['delete_post'])){
    $slugToDelete = $_POST['delete_post'];

    $foundIndex = findPostIndexBySlug($postsArr, $slugToDelete);
    if($foundIndex === false){
        $errors[] = "Post not found: $slugToDelete";
    } else {
        // Delete HTML file
        $postFilePath = $postsDir . '/' . $slugToDelete . '.html';
        if(file_exists($postFilePath)) @unlink($postFilePath);

        // Delete post images folder
        $postImagesDir = $imagesBase . '/' . $slugToDelete;
        if(is_dir($postImagesDir)){
            $files = glob($postImagesDir . '/*');
            foreach($files as $f) if(is_file($f)) @unlink($f);
            @rmdir($postImagesDir);
        }

        // Remove from posts.json
        array_splice($postsArr, $foundIndex, 1);
        file_put_contents($jsonFile, json_encode($postsArr, JSON_PRETTY_PRINT));

        if(isset($_POST['retain_form_after_action']) && $_POST['retain_form_after_action'] == '0'){
            unset($_SESSION['form_data']);
        }

        $message = "🗑️ Post '$slugToDelete' deleted successfully!";
        header("Location: " . $_SERVER['PHP_SELF'] . "?msg=" . urlencode($message));
        exit;
    }
}

// === CREATE OR UPDATE POST ===
if($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete_post']) && count($errors) === 0){
    // collect inputs
    $title = trim($_POST['title'] ?? '');
    $date = trim($_POST['date'] ?? date('Y-m-d'));
    $time = trim($_POST['time'] ?? date('H:i'));
    $location = trim($_POST['location'] ?? $locationDefault);
    $bio = trim($_POST['bio'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $imagesurls = trim($_POST['images'] ?? '');
    $imagesUrlList = array_filter(array_map('trim', explode(',', $imagesurls)));

    // Save form inputs into session so they persist after redirect and the form doesn't auto-clear
    $_SESSION['form_data'] = [
        'title' => $title,
        'date' => $date,
        'time' => $time,
        'location' => $location,
        'bio' => $bio,
        'content' => $content,
        'images' => $imagesurls
    ];

    if($title === '') $errors[] = "Title is required.";

    if(count($errors) === 0){
        // Load posts array fresh
        $postsArr = json_decode(file_get_contents($jsonFile), true);
        if(!is_array($postsArr)) $postsArr = [];

        // === UPDATE EXISTING POST ===
        if(isset($_POST['edit_slug']) && $_POST['edit_slug'] !== ''){
            $origSlug = $_POST['edit_slug'];
            $idx = findPostIndexBySlug($postsArr, $origSlug);
            if($idx === false){
                $errors[] = "Post not found for editing: $origSlug";
            } else {
                // determine new slug from new title (unique)
                $newBase = slugify($title);
                if($newBase === '') $newBase = 'post';
                $newSlug = makeUniqueSlug($postsArr, $newBase, $idx);

                $oldPostFilePath = $postsDir . '/' . $origSlug . '.html';
                $oldImagesDir = $imagesBase . '/' . $origSlug;
                $newPostFilePath = $postsDir . '/' . $newSlug . '.html';
                $newImagesDir = $imagesBase . '/' . $newSlug;

                // If slug changed, rename files/folders and update HTML inside file to new image paths
                if($newSlug !== $origSlug){
                    // Ensure postsDir exists
                    if(!is_dir($postsDir)) mkdir($postsDir, 0755, true);
                    // rename images dir (safe)
                    if(is_dir($oldImagesDir)){
                        safeRenameDir($oldImagesDir, $newImagesDir);
                    } else {
                        // ensure new dir exists
                        if(!is_dir($newImagesDir)) mkdir($newImagesDir, 0755, true);
                    }

                    // rename post html (if exists) and patch image paths inside
                    if(file_exists($oldPostFilePath)){
                        // first update paths inside old file to new slug
                        replaceSlugInHtml($oldPostFilePath, $origSlug, $newSlug, $baseUrl);
                        // then rename html file to new slug name
                        @rename($oldPostFilePath, $newPostFilePath);
                    } else {
                        // if old file doesn't exist, we'll create later
                    }

                    // Update postsArr entry paths for this item
                    $postsArr[$idx]['file'] = rtrim($baseUrl, '/') . '/assets/posts/' . $newSlug . '.html';
                    // update thumbnail path if exists
                    if(!empty($postsArr[$idx]['thumbnail'])){
                        $postsArr[$idx]['thumbnail'] = str_replace('/assets/images/' . $origSlug . '/', '/assets/images/' . $newSlug . '/', $postsArr[$idx]['thumbnail']);
                    }
                } else {
                    // slug didn't change — ensure images dir exists
                    if(!is_dir($oldImagesDir)) mkdir($oldImagesDir, 0755, true);
                    $newImagesDir = $oldImagesDir;
                    $newPostFilePath = $oldPostFilePath;
                }

                // Ensure images dir exists for subsequent uploads
                if(!is_dir($newImagesDir)) mkdir($newImagesDir, 0755, true);

                // Load current uploaded images (after potential rename)
                $existingImages = getImagesForSlug($imagesBase, $newSlug, $baseUrl);

                // === Handle deletion of selected existing images ===
                if(isset($_POST['delete_images']) && is_array($_POST['delete_images'])){
                    foreach($_POST['delete_images'] as $delRel){
                        // delRel expected as basename or full web path. Normalize to local file and delete.
                        $delRel = trim($delRel);
                        if($delRel === '') continue;
                        // If full web path, extract filename
                        $fname = basename(parse_url($delRel, PHP_URL_PATH));
                        $localPath = rtrim($newImagesDir, '/\\') . '/' . $fname;
                        if(file_exists($localPath)) @unlink($localPath);
                    }
                    // refresh existingImages list
                    $existingImages = getImagesForSlug($imagesBase, $newSlug, $baseUrl);
                }

                // === Handle thumbnail deletion ===
                $thumbnailPathRel = $postsArr[$idx]['thumbnail'] ?? '';
                if(isset($_POST['delete_thumbnail']) && $_POST['delete_thumbnail'] == '1'){
                    // delete thumbnail file if exists in images folder
                    if(!empty($thumbnailPathRel)){
                        $thumbName = basename(parse_url($thumbnailPathRel, PHP_URL_PATH));
                        $thumbLocal = rtrim($newImagesDir, '/\\') . '/' . $thumbName;
                        if(file_exists($thumbLocal)) @unlink($thumbLocal);
                        $thumbnailPathRel = '';
                    }
                }

                // === Thumbnail upload (replace if new uploaded) ===
                if(isset($_FILES['thumbnail']) && isset($_FILES['thumbnail']['name']) && $_FILES['thumbnail']['name'] !== ''){
                    $ext = strtolower(pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION)) ?: 'jpg';
                    $thumbTarget = $newImagesDir . '/thumbnail.' . $ext;
                    if(move_uploaded_file($_FILES['thumbnail']['tmp_name'], $thumbTarget)){
                        $thumbnailPathRel = rtrim($baseUrl, '/') . '/assets/images/' . $newSlug . '/thumbnail.' . $ext;
                    } else {
                        $errors[] = "Failed uploading thumbnail.";
                    }
                }

                // === Multiple image uploads (append new images) ===
                if(isset($_FILES['upload_images'])){
                    $names = $_FILES['upload_images']['name'];
                    $tmps = $_FILES['upload_images']['tmp_name'];
                    for($i=0;$i<count($names);$i++){
                        $n = $names[$i];
                        $t = $tmps[$i];
                        if(empty($n) || empty($t)) continue;
                        $ext = strtolower(pathinfo($n, PATHINFO_EXTENSION)) ?: 'jpg';
                        // set target name based on count of files currently in folder
                        $existingFiles = array_values(array_filter(glob($newImagesDir . '/image*')));
                        $nextIndex = count($existingFiles) + 1;
                        $imgTarget = $newImagesDir . '/image' . $nextIndex . '.' . $ext;
                        $k = 1;
                        while(file_exists($imgTarget)){
                            $k++;
                            $imgTarget = $newImagesDir . '/image' . $nextIndex . '-' . $k . '.' . $ext;
                        }
                        if(move_uploaded_file($t, $imgTarget)){
                            $existingImages[] = rtrim($baseUrl, '/') . '/assets/images/' . $newSlug . '/' . basename($imgTarget);
                        } else {
                            $errors[] = "Failed uploading image: $n";
                        }
                    }
                }

                // === Image URLs appended ===
                foreach($imagesUrlList as $url){
                    if($url === '') continue;
                    if(!preg_match('#^https?://#', $url)){
                        $url = rtrim($baseUrl, '/') . '/' . ltrim($url, '/');
                    }
                    $existingImages[] = str_replace(' ', '-', $url);
                }

                // Rebuild imagesHTML from actual folder images and manual URLs
                // prefer actual folder images first
                $folderImages = getImagesForSlug($imagesBase, $newSlug, $baseUrl);
                $allImages = $folderImages;
                // append non-folder URLs (imagesUrlList) that are not file in folder
                foreach($imagesUrlList as $url){
                    if($url === '') continue;
                    $u = $url;
                    if(!preg_match('#^https?://#', $u)){
                        $u = rtrim($baseUrl, '/') . '/' . ltrim($u, '/');
                    }
                    // avoid duplicates
                    if(!in_array($u, $allImages, true)){
                        $allImages[] = $u;
                    }
                }

                // If still empty, add examples
                if(empty($allImages)){
                    $allImages = [
                        rtrim($baseUrl, '/') . '/assets/images/example1.jpg',
                        rtrim($baseUrl, '/') . '/assets/images/example2.jpg'
                    ];
                }

                $imagesHTML = buildImagesHTML($allImages);

                // Update or rebuild post HTML file
                $postFilePath = $postsDir . '/' . $newSlug . '.html';
                $displayTitle = htmlspecialchars($title);
                $displayDate = htmlspecialchars($date);
                $displayTime = htmlspecialchars($time);
                $displayLocation = htmlspecialchars($location);
                $displayBio = nl2br(htmlspecialchars($bio));
                $displayContent = $content;

                // If file exists, patch content blocks, else create new
                if(file_exists($postFilePath)){
                    $html = file_get_contents($postFilePath);
                    $html = preg_replace('#<h1>.*?</h1>#is', '<h1>' . htmlspecialchars($title) . '</h1>', $html);
                    $html = preg_replace('#<span id="date">.*?</span>#is', '<span id="date">'.htmlspecialchars($date).'</span>', $html);
                    $html = preg_replace('#<span id="time">.*?</span>#is', '<span id="time">'.htmlspecialchars($time).'</span>', $html);
                    $html = preg_replace('#<span id="location">.*?</span>#is', '<span id="location">'.htmlspecialchars($location).'</span>', $html);
                    $html = preg_replace('#<div class="bio">.*?</div>#is', '<div class="bio">'.nl2br(htmlspecialchars($bio)).'</div>', $html);
                    $html = preg_replace('#<div class="content">.*?</div>#is', '<div class="content">'.$content.'</div>', $html);
                    $html = preg_replace('#<div class="images">.*?</div>#is', '<div class="images">'.$imagesHTML.'</div>', $html);
                    file_put_contents($postFilePath, $html);
                } else {
                    // create fresh post html based on template used in original
                    $postHTML = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>{$displayTitle}</title>
<style>
body{font-family:"Poppins",sans-serif;background:#0f0f0f;color:#eaeaea;margin:0;padding:0;}
.container{max-width:800px;margin:40px auto;background:#181818;border-radius:15px;padding:30px;box-shadow:0 4px 20px rgba(0,0,0,0.4);}
h1{color:#00ff99;margin-bottom:10px;}
.meta{font-size:0.9rem;color:#aaa;margin-bottom:20px;}
.bio{font-style:italic;color:#ccc;margin-bottom:25px;border-left:3px solid #00ff99;padding-left:10px;}
.content{line-height:1.7;color:#ddd;}
.images{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;margin-top:25px;}
.images img{width:100%;border-radius:10px;transition:0.3s;cursor:pointer;}
.images img:hover{transform:scale(1.03);opacity:0.9;}
footer{margin-top:30px;text-align:center;font-size:0.9rem;color:#666;}
a.back{color:#00ff99;text-decoration:none;display:inline-block;margin-top:15px;}
.popup{position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.9);display:flex;align-items:center;justify-content:center;opacity:0;pointer-events:none;transition:opacity .3s ease;z-index:9999;}
.popup.active{opacity:1;pointer-events:all;}
.popup img{max-width:90%;max-height:90%;border-radius:12px;box-shadow:0 0 25px rgba(0,0,0,0.5);}
.popup span{position:absolute;top:30px;right:40px;font-size:2rem;color:#fff;cursor:pointer;user-select:none;}
</style>
</head>
<body>
<div class="container">
<h1>{$displayTitle}</h1>
<div class="meta">
📅 <span id="date">{$displayDate}</span> |
🕓 <span id="time">{$displayTime}</span> |
📍 <span id="location">{$displayLocation}</span>
</div>
<div class="bio">{$displayBio}</div>
<div class="content">{$displayContent}</div>
<div class="images">{$imagesHTML}</div>
<footer><a href="{$baseUrl}/" class="back">← Back to Blog Root</a></footer>
</div>
<div class="popup" id="popup"><span id="close">&times;</span><img src="" alt="popup image" id="popup-img"></div>
<script>
const popup=document.getElementById('popup');
const popupImg=document.getElementById('popup-img');
const close=document.getElementById('close');
document.querySelectorAll('.images img').forEach(img=>{
 img.addEventListener('click',()=>{
  popup.classList.add('active');
  popupImg.src=img.src;
 });
});
close.addEventListener('click',()=>popup.classList.remove('active'));
popup.addEventListener('click',e=>{if(e.target===popup)popup.classList.remove('active');});
</script>
</body>
</html>
HTML;
                    file_put_contents($postFilePath, $postHTML);
                }

                // Update postsArr entry
                $postsArr[$idx]['title'] = $title;
                $postsArr[$idx]['date'] = $date . ' ' . $time;
                $postsArr[$idx]['desc'] = $bio;
                $postsArr[$idx]['location'] = $location;
                if(!empty($thumbnailPathRel)) $postsArr[$idx]['thumbnail'] = $thumbnailPathRel;
                else $postsArr[$idx]['thumbnail'] = $postsArr[$idx]['thumbnail'] ?? '';

                // If slug changed, also update 'file' key above already set; ensure correct
                $postsArr[$idx]['file'] = rtrim($baseUrl, '/') . '/assets/posts/' . $newSlug . '.html';

                // Save posts.json
                file_put_contents($jsonFile, json_encode($postsArr, JSON_PRETTY_PRINT));

                $message = "✏️ Post '" . ($newSlug) . "' updated successfully!";
                header("Location: " . $_SERVER['PHP_SELF'] . "?msg=" . urlencode($message) . "&edit=" . urlencode($newSlug));
                exit;
            }
        } // end edit branch

        // === CREATE NEW POST ===
        else {
            // generate unique slug
            $slugBase = slugify($title);
            if($slugBase === '') $slugBase = 'post';
            $slug = makeUniqueSlug($postsArr, $slugBase, null);

            $postImagesDir = $imagesBase . '/' . $slug;
            if(!is_dir($postImagesDir)){
                if(!mkdir($postImagesDir, 0755, true)){
                    $errors[] = "Could not create post images folder: $postImagesDir";
                }
            }

            $uploadedImages = [];
            $thumbnailPathRel = '';

            // === Thumbnail upload ===
            if(isset($_FILES['thumbnail']) && isset($_FILES['thumbnail']['name']) && $_FILES['thumbnail']['name'] !== ''){
                $ext = strtolower(pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION)) ?: 'jpg';
                $thumbTarget = $postImagesDir . '/thumbnail.' . $ext;
                if(move_uploaded_file($_FILES['thumbnail']['tmp_name'], $thumbTarget)){
                    $thumbnailPathRel = rtrim($baseUrl, '/') . '/assets/images/' . $slug . '/thumbnail.' . $ext;
                } else {
                    $errors[] = "Failed uploading thumbnail.";
                }
            }

            // === Multiple image uploads ===
            if(isset($_FILES['upload_images'])){
                $names = $_FILES['upload_images']['name'];
                $tmps = $_FILES['upload_images']['tmp_name'];
                for($i=0;$i<count($names);$i++){
                    $n = $names[$i];
                    $t = $tmps[$i];
                    if(empty($n) || empty($t)) continue;
                    $ext = strtolower(pathinfo($n, PATHINFO_EXTENSION)) ?: 'jpg';
                    $existingFiles = array_values(array_filter(glob($postImagesDir . '/image*')));
                    $nextIndex = count($existingFiles) + 1;
                    $imgTarget = $postImagesDir . '/image' . $nextIndex . '.' . $ext;
                    $k = 1;
                    while(file_exists($imgTarget)){
                        $k++;
                        $imgTarget = $postImagesDir . '/image' . $nextIndex . '-' . $k . '.' . $ext;
                    }
                    if(move_uploaded_file($t, $imgTarget)){
                        $uploadedImages[] = rtrim($baseUrl, '/') . '/assets/images/' . $slug . '/' . basename($imgTarget);
                    } else {
                        $errors[] = "Failed uploading image: $n";
                    }
                }
            }

            // === Use first image as thumbnail if none uploaded ===
            if($thumbnailPathRel === '' && count($uploadedImages) > 0){
                $firstImgAbs = $baseDir . '/' . str_replace($baseUrl . '/', '', $uploadedImages[0]);
                $ext = strtolower(pathinfo($firstImgAbs, PATHINFO_EXTENSION));
                $thumbTargetAbs = $postImagesDir . '/thumbnail.' . $ext;
                if(@copy($firstImgAbs, $thumbTargetAbs)){
                    $thumbnailPathRel = rtrim($baseUrl, '/') . '/assets/images/' . $slug . '/thumbnail.' . $ext;
                } else {
                    $thumbnailPathRel = $uploadedImages[0];
                }
            }

            // === Image URLs (external or manual) ===
            foreach($imagesUrlList as $url){
                if($url === '') continue;
                if(!preg_match('#^https?://#', $url)){
                    $url = rtrim($baseUrl, '/') . '/' . ltrim($url, '/');
                }
                $uploadedImages[] = str_replace(' ', '-', $url);
            }

            // If no images, use examples
            $imagesHTML = '';
            $imagesToUse = $uploadedImages;
            if(empty($imagesToUse)){
                $imagesToUse = [
                    rtrim($baseUrl, '/') . '/assets/images/example1.jpg',
                    rtrim($baseUrl, '/') . '/assets/images/example2.jpg'
                ];
            }
            $imagesHTML = buildImagesHTML($imagesToUse);

            // Build post HTML
            $postFileName = $slug . '.html';
            $postFilePath = $postsDir . '/' . $postFileName;
            $displayDate = htmlspecialchars($date);
            $displayTime = htmlspecialchars($time);
            $displayLocation = htmlspecialchars($location);
            $displayTitle = htmlspecialchars($title);
            $displayBio = nl2br(htmlspecialchars($bio));
            $displayContent = $content;

            $postHTML = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>{$displayTitle}</title>
<style>
body{font-family:"Poppins",sans-serif;background:#0f0f0f;color:#eaeaea;margin:0;padding:0;}
.container{max-width:800px;margin:40px auto;background:#181818;border-radius:15px;padding:30px;box-shadow:0 4px 20px rgba(0,0,0,0.4);}
h1{color:#00ff99;margin-bottom:10px;}
.meta{font-size:0.9rem;color:#aaa;margin-bottom:20px;}
.bio{font-style:italic;color:#ccc;margin-bottom:25px;border-left:3px solid #00ff99;padding-left:10px;}
.content{line-height:1.7;color:#ddd;}
.images{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;margin-top:25px;}
.images img{width:100%;border-radius:10px;transition:0.3s;cursor:pointer;}
.images img:hover{transform:scale(1.03);opacity:0.9;}
footer{margin-top:30px;text-align:center;font-size:0.9rem;color:#666;}
a.back{color:#00ff99;text-decoration:none;display:inline-block;margin-top:15px;}
.popup{position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.9);display:flex;align-items:center;justify-content:center;opacity:0;pointer-events:none;transition:opacity .3s ease;z-index:9999;}
.popup.active{opacity:1;pointer-events:all;}
.popup img{max-width:90%;max-height:90%;border-radius:12px;box-shadow:0 0 25px rgba(0,0,0,0.5);}
.popup span{position:absolute;top:30px;right:40px;font-size:2rem;color:#fff;cursor:pointer;user-select:none;}
</style>
</head>
<body>
<div class="container">
<h1>{$displayTitle}</h1>
<div class="meta">
📅 <span id="date">{$displayDate}</span> |
🕓 <span id="time">{$displayTime}</span> |
📍 <span id="location">{$displayLocation}</span>
</div>
<div class="bio">{$displayBio}</div>
<div class="content">{$displayContent}</div>
<div class="images">{$imagesHTML}</div>
<footer><a href="{$baseUrl}/" class="back">← Back to Blog Root</a></footer>
</div>
<div class="popup" id="popup"><span id="close">&times;</span><img src="" alt="popup image" id="popup-img"></div>
<script>
const popup=document.getElementById('popup');
const popupImg=document.getElementById('popup-img');
const close=document.getElementById('close');
document.querySelectorAll('.images img').forEach(img=>{
 img.addEventListener('click',()=>{
  popup.classList.add('active');
  popupImg.src=img.src;
 });
});
close.addEventListener('click',()=>popup.classList.remove('active'));
popup.addEventListener('click',e=>{if(e.target===popup)popup.classList.remove('active');});
</script>
</body>
</html>
HTML;

            if(file_put_contents($postFilePath, $postHTML)){
                $postsArr[] = [
                    "title" => $title,
                    "date" => $date . ' ' . $time,
                    "thumbnail" => $thumbnailPathRel ?: ($uploadedImages[0] ?? ''),
                    "file" => rtrim($baseUrl, '/') . '/assets/posts/' . $postFileName,
                    "desc" => $bio,
                    "location" => $location
                ];
                file_put_contents($jsonFile, json_encode($postsArr, JSON_PRETTY_PRINT));
                $message = "✅ Post created successfully: " . rtrim($baseUrl, '/') . "/assets/posts/{$postFileName}";
                header("Location: " . $_SERVER['PHP_SELF'] . "?msg=" . urlencode($message));
                exit;
            } else {
                $errors[] = "Failed writing post file.";
            }
        } // end create
    } // end if no errors
} // end POST handling

// === LOAD EDIT MODE PRE-FILL from GET param ?edit=slug ===
$editData = null;
if(isset($_GET['edit']) && $_GET['edit'] !== ''){
    $slugEdit = $_GET['edit'];
    $idx = findPostIndexBySlug($postsArr, $slugEdit);
    if($idx !== false){
        $p = $postsArr[$idx];
        $editData = $p;
        // read HTML to get bio and content if available
        $htmlPath = $postsDir . '/' . $slugEdit . '.html';
        if(file_exists($htmlPath)){
            $html = file_get_contents($htmlPath);
            if(preg_match('#<div class="bio">(.*?)</div>#is', $html, $m)) {
                $bioRaw = strip_tags($m[1], '<br><br/>');
                $bioRaw = str_replace(['<br/>','<br>'], "\n", $bioRaw);
                $editData['desc'] = trim($bioRaw);
            }
            if(preg_match('#<div class="content">(.*?)</div>#is', $html, $m2)){
                $editData['content'] = trim($m2[1]);
            } else {
                $editData['content'] = '';
            }
        } else {
            $editData['desc'] = $p['desc'] ?? '';
            $editData['content'] = '';
        }

        // load images list & thumbnail from folder
        $editData['images_list'] = getImagesForSlug($imagesBase, $slugEdit, $baseUrl);
        $editData['thumbnail'] = $p['thumbnail'] ?? '';
    }
}

// If there's saved form_data in session and we're NOT in edit mode, prefill form with that
$formData = [
    'title' => '',
    'date' => date('Y-m-d'),
    'time' => date('H:i'),
    'location' => $locationDefault,
    'bio' => '',
    'content' => '',
    'images' => ''
];
if(!isset($editData) || $editData === null){
    if(isset($_SESSION['form_data']) && is_array($_SESSION['form_data'])){
        $sd = $_SESSION['form_data'];
        $formData = array_merge($formData, $sd);
    }
}

// Load any message from GET (after PRG)
if(isset($_GET['msg'])) $message = htmlspecialchars($_GET['msg']);

// Start HTML output
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>CMS</title>
<link rel="stylesheet" href="cms.css">
</head>
<body>
<div class="container">
<h1>Post Maker</h1>
<?php
if(count($errors)>0){
 echo '<div class="error"><b>Errors:</b><ul>';
 foreach($errors as $e) echo "<li>".htmlspecialchars($e)."</li>";
 echo '</ul></div>';
}elseif($message!==''){
 echo '<div class="message">'.htmlspecialchars($message).'</div>';
}
?>

<form method="POST" enctype="multipart/form-data" id="postForm">
<?php if($editData): ?>
<input type="hidden" name="edit_slug" value="<?php echo htmlspecialchars(pathinfo($editData['file'], PATHINFO_FILENAME)); ?>">
<?php endif; ?>

<label>Post Title</label>
<input type="text" name="title" required placeholder="My crazy post title" value="<?php echo htmlspecialchars($editData['title'] ?? $formData['title']); ?>">

<label>Date</label>
<input type="date" name="date" value="<?php echo htmlspecialchars($editData['date'] ? explode(' ', $editData['date'])[0] : $formData['date']); ?>" required>

<label>Time</label>
<input type="time" name="time" value="<?php echo htmlspecialchars($editData['date'] ? (explode(' ', $editData['date'])[1] ?? $formData['time']) : $formData['time']); ?>" required>

<label>Location</label>
<input type="text" name="location" placeholder="Enter location (e.g. Hosh Issa, Beheira, Egypt)" required value="<?php echo htmlspecialchars($editData['location'] ?? $formData['location']); ?>">

<label>Post Bio / Summary</label>
<textarea name="bio" rows="2" placeholder="Short description or bio" required><?php echo htmlspecialchars($editData['desc'] ?? $formData['bio']); ?></textarea>

<label>Post Content (raw HTML allowed)</label>
<textarea name="content" rows="8" placeholder="Write full content here (HTML allowed)"><?php echo htmlspecialchars($editData['content'] ?? $formData['content']); ?></textarea>

<?php if(isset($editData) && $editData !== null): ?>
    <div>
      <label>Thumbnail (existing)</label>
      <?php if(!empty($editData['thumbnail'])): ?>
        <img src="<?php echo htmlspecialchars($editData['thumbnail']); ?>" class="thumb-preview" alt="thumbnail">
        <div>
          <label><input type="checkbox" name="delete_thumbnail" value="1"> Delete current thumbnail</label>
        </div>
      <?php else: ?>
        <small>No thumbnail yet.</small>
      <?php endif; ?>
    </div>

    <div>
      <label>Existing Images (click checkbox to delete)</label>
      <div class="images-preview">
      <?php
        if(!empty($editData['images_list'])){
            foreach($editData['images_list'] as $img){
                $bn = basename(parse_url($img, PHP_URL_PATH));
                echo '<div class="img-item">';
                echo '<img src="'.htmlspecialchars($img).'" alt="img">';
                echo '<label class="delete-check"><input type="checkbox" name="delete_images[]" value="'.htmlspecialchars($img).'"> Delete</label>';
                echo '</div>';
            }
        } else {
            echo '<small>No images folder or images for this post yet.</small>';
        }
      ?>
      </div>
    </div>
<?php endif; ?>

<label>Thumbnail (upload)</label>
<input type="file" name="thumbnail" accept="image/*">

<label>Other Images (multiple)</label>
<input type="file" name="upload_images[]" accept="image/*" multiple>

<label>Optional: image URLs (comma separated)</label>
<input type="text" name="images" placeholder="/blog/assets/images/...jpg, https://..." value="<?php echo htmlspecialchars($formData['images'] ?? ''); ?>">

<div style="margin-top:12px;">
    <button type="submit"><?php echo $editData ? 'Update Post' : 'Create Post'; ?></button>
    <button type="button" onclick="clearForm()">Clear Form</button>
    <?php if($editData): ?>
        <a class="smallbtn" href="<?php echo strtok($_SERVER['REQUEST_URI'], '?'); ?>?cancel=1">Cancel Edit</a>
    <?php endif; ?>
</div>
</form>

<hr>
<h2>Existing Posts</h2>
<?php
// Reload for display in case the POST failed and postsArr was modified inside
$postsArr = json_decode(file_get_contents($jsonFile), true);
if(is_array($postsArr) && count($postsArr) > 0){
    echo '<ul>';
    foreach($postsArr as $p){
        $slug = pathinfo($p['file'], PATHINFO_FILENAME);
        $title = htmlspecialchars($p['title']);
        echo '<li style="margin-bottom:6px;">';
        echo "$title ";
        // Edit link
        echo ' <a href="?edit='.$slug.'" style="background:#0099ff;color:#fff;padding:5px 10px;border-radius:4px;text-decoration:none;margin-left:8px;">Edit</a> ';
        echo '<form method="POST" style="display:inline;margin-left:6px;" onsubmit="return confirm(\'Delete this post?\');">';
        echo '<input type="hidden" name="delete_post" value="'.$slug.'">';
        echo '<button type="submit" class="danger">Delete</button>';
        echo '</form></li>';
    }
    echo '</ul>';
}else{
    echo '<p>No posts yet.</p>';
}
?>

<hr>
<small>Enjoy Making Great Things</small>
</div>

<script src="cms.js"></script>
</body>
</html>
