<?php
// Post Maker - Absolute
// by Mahdi & ChatGPT
// Upgraded: Project/Tag Management, Project-based folder structure, Project CRUD.
// LATEST CHANGE: Dynamic "Back to Project" link using history.back()

ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

$baseDir = realpath(__DIR__ . '/../');
$projectBaseDir = $baseDir . '/assets'; // NEW base for all projects
$jsonFile = $baseDir . '/posts.json';
$projectsJsonFile = $baseDir . '/projects.json'; // NEW
$locationDefault = 'Hosh Issa, Beheira, Egypt';

$baseUrl = '/projects'; // 🔥 Main prefix for all absolute URLs

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

// Ensure posts.json and projects.json exist
if(!file_exists($jsonFile)) file_put_contents($jsonFile, json_encode([], JSON_PRETTY_PRINT));
if(!file_exists($projectsJsonFile)) file_put_contents($projectsJsonFile, json_encode([], JSON_PRETTY_PRINT));

// Load posts array early
$postsArr = json_decode(file_get_contents($jsonFile), true);
if(!is_array($postsArr)) $postsArr = [];

// Load projects array early
$projectsArr = json_decode(file_get_contents($projectsJsonFile), true);
if(!is_array($projectsArr)) $projectsArr = [];

/**
 * Helper: find post index by slug (filename)
 */
function findPostIndexBySlug($arr, $slug){
    foreach($arr as $i => $p){
        $basename = pathinfo($p['file'] ?? '', PATHINFO_FILENAME);
        if($basename === $slug) return $i;
    }
    return false;
}

/**
 * Helper: find project index by tag slug
 */
function findProjectIndexBySlug($arr, $slug){
    foreach($arr as $i => $p){
        if(slugify($p['name'] ?? '') === $slug) return $i;
    }
    return false;
}

/**
 * Helper: Safely get project thumbnail path (web path) based on naming convention
 */
function getProjectThumbnailPath($projectBaseDir, $tagSlug, $baseUrl){
    $dir = rtrim($projectBaseDir, '/\\') . '/' . $tagSlug . '/images/';
    // Search for <tagSlug>-thumbnail.*
    $files = glob($dir . $tagSlug . '-thumbnail.*');
    if(!empty($files)){
        $web = str_replace('\\','/', $files[0]);
        $pos = strpos($web, '/assets/' . $tagSlug . '/images/');
        if($pos !== false){
            return rtrim($baseUrl, '/') . substr($web, $pos);
        }
    }
    return ''; // No thumbnail found
}

/**
 * Helper: read images list from images folder for a slug within a project
 * returns web paths prefixed with $baseUrl (e.g. /projects/assets/tag-slug/images/post-slug/filename)
 */
function getImagesForSlug($projectBaseDir, $tagSlug, $postSlug, $baseUrl){
    $dir = rtrim($projectBaseDir, '/\\') . '/' . $tagSlug . '/images/' . $postSlug; // NEW PATH
    $list = [];
    if(is_dir($dir)){
        $files = array_values(array_filter(glob($dir . '/*'), 'is_file'));
        sort($files);
        foreach($files as $f){
            $web = str_replace('\\','/', $f);
            $pos = strpos($web, '/assets/' . $tagSlug . '/images/' . $postSlug);
            if($pos !== false){
                $rel = substr($web, $pos); // starts with /assets/tag-slug/images/post-slug/...
                $list[] = rtrim($baseUrl, '/') . $rel;
            } else {
                // Fallback
                $list[] = rtrim($baseUrl, '/') . '/assets/' . $tagSlug . '/images/' . $postSlug . '/' . basename($f);
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
 * Helper: update image paths inside HTML content when slug changes or tag changes
 */
function replaceSlugInHtml($htmlPath, $oldTagSlug, $newTagSlug, $oldPostSlug, $newPostSlug, $baseUrl){
    if(!file_exists($htmlPath)) return;
    $html = file_get_contents($htmlPath);

    // 1. Update image paths
    $oldPrefix = rtrim($baseUrl, '/') . '/assets/' . $oldTagSlug . '/images/' . $oldPostSlug . '/';
    $newPrefix = rtrim($baseUrl, '/') . '/assets/' . $newTagSlug . '/images/' . $newPostSlug . '/';
    $html = str_replace($oldPrefix, $newPrefix, $html);

    // 2. Update posts link (file path)
    $oldPostPath = rtrim($baseUrl, '/') . '/assets/' . $oldTagSlug . '/posts/' . $oldPostSlug . '.html';
    $newPostPath = rtrim($baseUrl, '/') . '/assets/' . $newTagSlug . '/posts/' . $newPostSlug . '.html';
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

// === PROJECT MANAGEMENT HANDLER (NEW) ===
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['project_action'])){
    $projectName = trim($_POST['project_name'] ?? '');
    $tagSlug = slugify($projectName);
    $bio = trim($_POST['project_bio'] ?? '');

    // Reload projects array inside POST logic
    $projectsArr = json_decode(file_get_contents($projectsJsonFile), true);
    if(!is_array($projectsArr)) $projectsArr = [];

    if($projectName === ''){
        $errors[] = "Project Name is required.";
    } elseif ($tagSlug === ''){
        $errors[] = "Project Name generates an empty slug.";
    }

    $idx = findProjectIndexBySlug($projectsArr, $tagSlug);
    $isEditing = ($idx !== false);

    if($_POST['project_action'] === 'save' && count($errors) === 0){
        $message = $isEditing ? "Project '$projectName' updated successfully." : "Project '$projectName' created successfully.";

        // Define paths
        $projectBaseDirForThumb = $projectBaseDir . '/' . $tagSlug;
        $projectImagesDir = $projectBaseDirForThumb . '/images';
        $projectPostsDir = $projectBaseDirForThumb . '/posts';

        // Ensure directories exist
        if(!is_dir($projectImagesDir)) mkdir($projectImagesDir, 0755, true);
        if(!is_dir($projectPostsDir)) mkdir($projectPostsDir, 0755, true);

        $thumbnailPathRel = $projectsArr[$idx]['thumbnail'] ?? '';

        // === Handle thumbnail deletion ===
        if($isEditing && isset($_POST['delete_thumbnail']) && $_POST['delete_thumbnail'] == '1'){
            if(!empty($thumbnailPathRel)){
                // Search for the file based on the required naming convention
                $filesToDelete = glob($projectImagesDir . '/' . $tagSlug . '-thumbnail.*');
                foreach($filesToDelete as $f) if(is_file($f)) @unlink($f);
                $thumbnailPathRel = '';
            }
        }

        // === Thumbnail upload/replace ===
        if(isset($_FILES['project_thumbnail']) && $_FILES['project_thumbnail']['name'] !== ''){
            $ext = strtolower(pathinfo($_FILES['project_thumbnail']['name'], PATHINFO_EXTENSION)) ?: 'jpg';
            $thumbTargetBase = $tagSlug . '-thumbnail';
            $thumbTarget = $projectImagesDir . '/' . $thumbTargetBase . '.' . $ext;

            // Clean up old thumbnail files in case the extension changed
            $oldFiles = glob($projectImagesDir . '/' . $thumbTargetBase . '.*');
            foreach($oldFiles as $f) if(is_file($f)) @unlink($f);

            if(move_uploaded_file($_FILES['project_thumbnail']['tmp_name'], $thumbTarget)){
                // Path must be /assets/<project-name>/images/<project-name>-thumbnail.ext
                $thumbnailPathRel = rtrim($baseUrl, '/') . '/assets/' . $tagSlug . '/images/' . $thumbTargetBase . '.' . $ext;
            } else {
                $errors[] = "Failed uploading project thumbnail.";
            }
        } else if ($isEditing && isset($_POST['delete_thumbnail']) && $_POST['delete_thumbnail'] == '0'){
            // If editing and no new upload, check if a file exists on disk based on naming rule
            $thumbnailPathRel = getProjectThumbnailPath($projectBaseDir, $tagSlug, $baseUrl);
        }

        $projectData = [
            "name" => $projectName,
            "bio" => $bio,
            "thumbnail" => $thumbnailPathRel,
            "slug" => $tagSlug // Store slug for easy lookup
        ];

        if($isEditing){
            $projectsArr[$idx] = $projectData;
        } else {
            // Only add if it doesn't exist (prevents adding if slug already matched)
            if(findProjectIndexBySlug($projectsArr, $tagSlug) === false){
                 $projectsArr[] = $projectData;
            } else {
                 $errors[] = "Project with name '$projectName' already exists.";
            }
        }

        if(count($errors) === 0){
            file_put_contents($projectsJsonFile, json_encode($projectsArr, JSON_PRETTY_PRINT));
            header("Location: " . $_SERVER['PHP_SELF'] . "?msg=" . urlencode($message) . "&edit_project=" . urlencode($tagSlug));
            exit;
        }

    } elseif($_POST['project_action'] === 'delete' && count($errors) === 0){
        $origProjectName = $_POST['project_name'];
        if($idx === false){
            $errors[] = "Project not found for deletion.";
        } else {
            // Check for existing posts in this project
            $postsInProject = array_filter($postsArr, function($p) use ($origProjectName) {
                return ($p['tag'] ?? '') === $origProjectName;
            });
            if(!empty($postsInProject)){
                $errors[] = "Cannot delete project '$origProjectName'. It still contains " . count($postsInProject) . " posts.";
            } else {
                // Delete project directory structure
                $projectBaseDirForDelete = $projectBaseDir . '/' . $tagSlug;

                $deleteDirContents = function($dir) use (&$deleteDirContents) {
                    if (!is_dir($dir)) return;
                    $items = glob($dir . '/*');
                    foreach($items as $item) {
                        if (is_dir($item)) {
                            $deleteDirContents($item);
                            @rmdir($item);
                        } else {
                            @unlink($item);
                        }
                    }
                };

                // Delete contents and the main project folder
                $deleteDirContents($projectBaseDirForDelete);
                @rmdir($projectBaseDirForDelete);

                array_splice($projectsArr, $idx, 1);
                file_put_contents($projectsJsonFile, json_encode($projectsArr, JSON_PRETTY_PRINT));
                $message = "🗑️ Project '$origProjectName' deleted successfully!";
                header("Location: " . $_SERVER['PHP_SELF'] . "?msg=" . urlencode($message));
                exit;
            }
        }
    }
}
// END PROJECT MANAGEMENT HANDLER

// === DELETE POST (full delete) ===
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_post']) && !empty($_POST['delete_post']) && !isset($_POST['project_action'])){
    $slugToDelete = $_POST['delete_post'];
    $tagSlugToDelete = $_POST['delete_tag_slug']; // NEW

    $foundIndex = findPostIndexBySlug($postsArr, $slugToDelete);
    if($foundIndex === false){
        $errors[] = "Post not found: $slugToDelete";
    } else {
        // Delete HTML file
        $postFilePath = $projectBaseDir . '/' . $tagSlugToDelete . '/posts/' . $slugToDelete . '.html'; // UPDATED PATH
        if(file_exists($postFilePath)) @unlink($postFilePath);

        // Delete post images folder
        $postImagesDir = $projectBaseDir . '/' . $tagSlugToDelete . '/images/' . $slugToDelete; // UPDATED PATH
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
if($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete_post']) && !isset($_POST['project_action']) && count($errors) === 0){
    // collect inputs
    $title = trim($_POST['title'] ?? '');
    $date = trim($_POST['date'] ?? date('Y-m-d'));
    $time = trim($_POST['time'] ?? date('H:i'));
    $location = trim($_POST['location'] ?? $locationDefault);
    $bio = trim($_POST['bio'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $imagesurls = trim($_POST['images'] ?? '');
    $imagesUrlList = array_filter(array_map('trim', explode(',', $imagesurls)));

    // Project tag inputs (NEW)
    $tag = trim($_POST['tag_hidden'] ?? ''); // Use the hidden field
    if($tag === '') $errors[] = "Project Tag is required for the post.";
    $tagSlug = slugify($tag); // Slugify the final tag

    // Save form inputs into session so they persist after redirect and the form doesn't auto-clear
    $_SESSION['form_data'] = [
        'title' => $title,
        'date' => $date,
        'time' => $time,
        'location' => $location,
        'bio' => $bio,
        'content' => $content,
        'images' => $imagesurls,
        'tag' => $tag
    ];

    if($title === '') $errors[] = "Title is required.";

    if(count($errors) === 0){
        // Load posts array fresh
        $postsArr = json_decode(file_get_contents($jsonFile), true);
        if(!is_array($postsArr)) $postsArr = [];

        // === UPDATE EXISTING POST ===
        if(isset($_POST['edit_slug']) && $_POST['edit_slug'] !== ''){
            $origSlug = $_POST['edit_slug'];
            $origTagSlug = $_POST['orig_tag_slug']; // NEW
            $idx = findPostIndexBySlug($postsArr, $origSlug);

            if($idx === false){
                $errors[] = "Post not found for editing: $origSlug";
            } else {
                // determine new slug from new title (unique)
                $newBase = slugify($title);
                if($newBase === '') $newBase = 'post';
                $newSlug = makeUniqueSlug($postsArr, $newBase, $idx);

                // Define NEW file paths based on new tag and slug
                $newTagSlug = $tagSlug;

                $oldPostFilePath = $projectBaseDir . '/' . $origTagSlug . '/posts/' . $origSlug . '.html';
                $oldImagesDir = $projectBaseDir . '/' . $origTagSlug . '/images/' . $origSlug;
                $newPostFilePath = $projectBaseDir . '/' . $newTagSlug . '/posts/' . $newSlug . '.html';
                $newImagesDir = $projectBaseDir . '/' . $newTagSlug . '/images/' . $newSlug;
                $newPostsDir = $projectBaseDir . '/' . $newTagSlug . '/posts';
                $newProjectImagesDir = $projectBaseDir . '/' . $newTagSlug . '/images';

                // If slug or tag changed, handle file movement
                if($newSlug !== $origSlug || $newTagSlug !== $origTagSlug){
                    // Ensure new project folders exist
                    if(!is_dir($newPostsDir)) mkdir($newPostsDir, 0755, true);
                    if(!is_dir($newProjectImagesDir)) mkdir($newProjectImagesDir, 0755, true);

                    // rename images dir (safe)
                    if(is_dir($oldImagesDir)){
                        safeRenameDir($oldImagesDir, $newImagesDir);
                    } else {
                        if(!is_dir($newImagesDir)) mkdir($newImagesDir, 0755, true);
                    }

                    // rename post html (if exists) and patch image paths inside
                    if(file_exists($oldPostFilePath)){
                        // first update paths inside old file to new slug AND new tag
                        replaceSlugInHtml($oldPostFilePath, $origTagSlug, $newTagSlug, $origSlug, $newSlug, $baseUrl);
                        // then rename/move html file
                        @rename($oldPostFilePath, $newPostFilePath);
                    }

                    // Update postsArr entry paths for this item
                    $postsArr[$idx]['tag'] = $tag;
                    $postsArr[$idx]['file'] = rtrim($baseUrl, '/') . '/assets/' . $newTagSlug . '/posts/' . $newSlug . '.html';
                    // update thumbnail path if exists
                    if(!empty($postsArr[$idx]['thumbnail'])){
                        $oldThumbPrefix = '/assets/' . $origTagSlug . '/images/' . $origSlug . '/';
                        $newThumbPrefix = '/assets/' . $newTagSlug . '/images/' . $newSlug . '/';
                        $postsArr[$idx]['thumbnail'] = str_replace($oldThumbPrefix, $newThumbPrefix, $postsArr[$idx]['thumbnail']);
                    }
                } else {
                    // slug and tag didn't change — ensure images dir exists
                    $newImagesDir = $oldImagesDir;
                    $newPostFilePath = $oldPostFilePath;
                    if(!is_dir($newImagesDir)) mkdir($newImagesDir, 0755, true);
                }

                // Load current uploaded images (after potential rename)
                $existingImages = getImagesForSlug($projectBaseDir, $newTagSlug, $newSlug, $baseUrl);

                // === Handle deletion of selected existing images ===
                if(isset($_POST['delete_images']) && is_array($_POST['delete_images'])){
                    foreach($_POST['delete_images'] as $delRel){
                        $delRel = trim($delRel);
                        if($delRel === '') continue;
                        $fname = basename(parse_url($delRel, PHP_URL_PATH));
                        $localPath = rtrim($newImagesDir, '/\\') . '/' . $fname;
                        if(file_exists($localPath)) @unlink($localPath);
                    }
                    $existingImages = getImagesForSlug($projectBaseDir, $newTagSlug, $newSlug, $baseUrl);
                }

                // === Handle thumbnail deletion ===
                $thumbnailPathRel = $postsArr[$idx]['thumbnail'] ?? '';
                if(isset($_POST['delete_thumbnail']) && $_POST['delete_thumbnail'] == '1'){
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
                        $thumbnailPathRel = rtrim($baseUrl, '/') . '/assets/' . $newTagSlug . '/images/' . $newSlug . '/thumbnail.' . $ext;
                    } else {
                        $errors[] = "Failed uploading thumbnail.";
                    }
                }

                // === Multiple image uploads (append new images) ===
                if(isset($_FILES['upload_images'])){
                    $names = $_FILES['upload_images']['name'];
                    $tmps = $_FILES['upload_images']['tmp_name'];
                    for($i=0;$i<count($names);$i++){
                        $n = $names[$i]; $t = $tmps[$i];
                        if(empty($n) || empty($t)) continue;
                        $ext = strtolower(pathinfo($n, PATHINFO_EXTENSION)) ?: 'jpg';
                        $existingFiles = array_values(array_filter(glob($newImagesDir . '/image*')));
                        $nextIndex = count($existingFiles) + 1;
                        $imgTarget = $newImagesDir . '/image' . $nextIndex . '.' . $ext;
                        $k = 1;
                        while(file_exists($imgTarget)){
                            $k++;
                            $imgTarget = $newImagesDir . '/image' . $nextIndex . '-' . $k . '.' . $ext;
                        }
                        if(move_uploaded_file($t, $imgTarget)){
                            $existingImages[] = rtrim($baseUrl, '/') . '/assets/' . $newTagSlug . '/images/' . $newSlug . '/' . basename($imgTarget);
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
                $folderImages = getImagesForSlug($projectBaseDir, $newTagSlug, $newSlug, $baseUrl);
                $allImages = $folderImages;
                foreach($imagesUrlList as $url){
                    if($url === '') continue;
                    $u = $url;
                    if(!preg_match('#^https?://#', $u)){
                        $u = rtrim($baseUrl, '/') . '/' . ltrim($u, '/');
                    }
                    if(!in_array($u, $allImages, true)){
                        $allImages[] = $u;
                    }
                }

                

                $imagesHTML = buildImagesHTML($allImages);

                // Update or rebuild post HTML file
                $postFilePath = $newPostFilePath;
                $displayTitle = htmlspecialchars($title);
                $displayDate = htmlspecialchars($date);
                $displayTime = htmlspecialchars($time);
                $displayLocation = htmlspecialchars($location);
                $displayBio = nl2br(htmlspecialchars($bio));
                $displayContent = $content;

                // ** New Footer Variables **
                $displayProjectName = htmlspecialchars($tag); // The Project Name

                if(file_exists($postFilePath)){
                    $html = file_get_contents($postFilePath);
                    $html = preg_replace('#<h1>.*?</h1>#is', '<h1>' . htmlspecialchars($title) . '</h1>', $html);
                    $html = preg_replace('#<span id="date">.*?</span>#is', '<span id="date">'.htmlspecialchars($date).'</span>', $html);
                    $html = preg_replace('#<span id="time">.*?</span>#is', '<span id="time">'.htmlspecialchars($time).'</span>', $html);
                    $html = preg_replace('#<span id="location">.*?</span>#is', '<span id="location">'.htmlspecialchars($location).'</span>', $html);
                    $html = preg_replace('#<div class="bio">.*?</div>#is', '<div class="bio">'.nl2br(htmlspecialchars($bio)).'</div>', $html);
                    $html = preg_replace('#<div class="content">.*?</div>#is', '<div class="content">'.$content.'</div>', $html);
                    $html = preg_replace('#<div class="images">.*?</div>#is', '<div class="images">'.$imagesHTML.'</div>', $html);
                    // 💥 UPDATE FOOTER LINK TEXT AND HREF (Patching for existing file)
                    $html = preg_replace(
                        '#<footer>.*?</footer>#is',
                        '<footer><a href="javascript:history.back()" class="back">← Back to ' . $displayProjectName . '</a></footer>',
                        $html
                    );
                    file_put_contents($postFilePath, $html);
                } else {
                    // create fresh post html based on template
                    // === START FUTURISTIC TEMPLATE INSERTION 1/2 (REPLACED) ===
                    $postHTML = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>{$displayTitle}</title>
<style>
/*
 * Post Page Styles - Futuristic Dark Theme
 */
:root {
    --bg-primary: #12121e; /* Deep Space Blue/Black */
    --bg-secondary: #1a1a2b; /* Slightly lighter card background */
    --text-color: #e0e0ff; /* Light, slightly blue text */
    --text-secondary: #9aa8b5;
    --accent-color-1: #00e5ff; /* Electric Blue (Primary) */
    --accent-color-2: #ff00ff; /* Neon Magenta (Secondary Accent) */
    --card-shadow: 0 8px 30px rgba(0, 229, 255, 0.15); /* Light blue glow shadow */
    --border-color: #2c2c40;
}
body {
    font-family: 'Space Mono', monospace, sans-serif; /* Techy font stack */
    background: var(--bg-primary);
    color: var(--text-color);
    /* Changed padding to 0 to remove side margins on mobile */
    padding: 0;
    line-height: 1.6;
    min-height: 100vh;
    margin: 0; /* Ensures no default body margin */
    box-sizing: border-box; /* Good practice for responsive design */
}

/* Updated: Increased border-radius for smoother main borders */
.container {
    max-width: 900px;
    /* Adjusted margin-top/bottom and set margin-left/right to auto */
    margin: 40px auto;
    background: var(--bg-secondary);
    border: 2px solid var(--border-color);
    border-radius: 30px; /* Increased for smoother corners */
    padding: 40px;
    box-shadow: var(--card-shadow);
    /* Ensures padding is included in the width */
    box-sizing: border-box;
}

/* --- Title Size Adjustment & Rounded Tips --- */
h1 {
    color: var(--accent-color-1);
    font-size: 2.0em;
    font-weight: 700;
    margin-bottom: 10px;
    /* Adding a subtle curve to the bottom border line */
    border-bottom: 2px solid var(--border-color);
    border-radius: 0 0 4px 4px; /* Curved bottom tips */
    padding-bottom: 15px;
}
/* --- Meta Data Size Adjustment --- */
.meta {
    font-size: 0.8em;
    color: var(--text-secondary);
    margin-bottom: 25px;
    display: block;
}

/* --- Images Stacking (Desktop/Default) --- */
.images {
    display: grid;
    /* 徴 CHANGE: Forced single column (vertical stack) on all screen sizes */
    grid-template-columns: 1fr;
    gap: 20px;
    margin-top: 40px;
}
.images img {
    width: 100%;
    /* Increased image border radius slightly for a softer look */
    border-radius: 14px;
    border: 1px solid var(--border-color);
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.5);
    transition: transform 0.3s, opacity 0.3s;
    cursor: pointer;
}
.images img:hover {
    transform: scale(1.02);
    opacity: 1;
    border-color: var(--accent-color-1);
}

/* New: Media query for mobile-specific styles */
@media (max-width: 768px) {
    body {
        /* Adjusted vertical padding for mobile */
        padding-top: 20px;
        padding-bottom: 20px;
    }
    .container {
        /* Set to 100% width on smaller screens, removing the side margins */
        max-width: 100%;
        width: 100%;
        margin: 0 auto; /* Remove margin on the sides */
        border-radius: 0; /* Keep border-radius 0 for a seamless mobile look */
        border-left: none; /* Remove side borders */
        border-right: none;
        padding: 20px 15px; /* Reduced padding for smaller screens */
    }
    h1 {
        font-size: 1.6em; /* Slightly smaller heading for mobile view */
    }
    /* The .images grid-template-columns: 1fr; is already applied from the general style above. */
    .images {
        gap: 15px; /* Slightly reduced gap on mobile */
    }
    .images img {
        /* Ensure images take full width of their container */
        width: 100%;
    }
}


.bio {
    font-style: italic;
    color: var(--text-secondary);
    margin-bottom: 30px;
    /* Added border-radius to the left border for a rounded tip */
    border-left: 3px solid var(--accent-color-2); /* Neon magenta accent */
    border-radius: 0 0 0 4px; /* subtle curve on the bottom left tip */
    padding-left: 15px;
    line-height: 1.4;
}
.content {
    line-height: 1.8;
    color: var(--text-color);
    font-size: 1em;
}

footer {
    margin-top: 40px;
    padding-top: 20px;
    border-top: 1px solid var(--border-color);
    text-align: center;
    font-size: 0.9rem;
    color: var(--text-secondary);
}
a.back {
    color: var(--accent-color-1);
    text-decoration: none;
    font-weight: 600;
    display: inline-block;
    border-bottom: 1px dashed var(--accent-color-1);
    transition: color 0.2s, border-bottom-color 0.2s;
}
a.back:hover {
    color: var(--accent-color-2);
    border-bottom-color: var(--accent-color-2);
}
/* Popup styles for image viewing */
.popup{position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.95);display:flex;align-items:center;justify-content:center;opacity:0;pointer-events:none;transition:opacity .3s ease;z-index:9999;}
.popup.active{opacity:1;pointer-events:all;}
.popup img{max-width:90%;max-height:90%;border-radius:12px;box-shadow:0 0 25px rgba(0, 229, 255, 0.4);}
.popup span{position:absolute;top:30px;right:40px;font-size:2rem;color:var(--accent-color-1);cursor:pointer;user-select:none;}
/* New: Adjust popup close button for mobile */
@media (max-width: 768px) {
    .popup span {
        top: 20px;
        right: 20px;
        font-size: 1.8rem;
    }
}
</style>
</head>
<body>
<div class="container">
<h1>{$displayTitle}</h1>
<div class="meta">
🗓️ <span id="date">{$displayDate}</span> |
⏰ <span id="time">{$displayTime}</span> |
📍 <span id="location">{$displayLocation}</span>
</div>
<div class="bio">{$displayBio}</div>
<div class="content">{$displayContent}</div>
<div class="images">{$imagesHTML}</div>
<footer><a href="javascript:history.back()" class="back">← Back to {$displayProjectName}</a></footer>
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
                    // === END FUTURISTIC TEMPLATE INSERTION 1/2 (REPLACED) ===
                    file_put_contents($postFilePath, $postHTML);
                }

                // Update postsArr entry
                $postsArr[$idx]['tag'] = $tag;
                $postsArr[$idx]['title'] = $title;
                $postsArr[$idx]['date'] = $date . ' ' . $time;
                $postsArr[$idx]['desc'] = $bio;
                $postsArr[$idx]['location'] = $location;
                if(!empty($thumbnailPathRel)) $postsArr[$idx]['thumbnail'] = $thumbnailPathRel;
                else $postsArr[$idx]['thumbnail'] = $postsArr[$idx]['thumbnail'] ?? '';

                $postsArr[$idx]['file'] = rtrim($baseUrl, '/') . '/assets/' . $newTagSlug . '/posts/' . $newSlug . '.html';

                // Save posts.json
                file_put_contents($jsonFile, json_encode($postsArr, JSON_PRETTY_PRINT));

                $message = "✏️ Post '" . ($newSlug) . "' updated successfully!";
                header("Location: " . $_SERVER['PHP_SELF'] . "?msg=" . urlencode($message) . "&edit=" . urlencode($newSlug) . "&tag=" . urlencode($tag));
                exit;
            }
        } // end edit branch

        // === CREATE NEW POST ===
        else {
            $slugBase = slugify($title);
            if($slugBase === '') $slugBase = 'post';
            $slug = makeUniqueSlug($postsArr, $slugBase, null);

            $postImagesDir = $projectBaseDir . '/' . $tagSlug . '/images/' . $slug; // UPDATED PATH
            $postPostsDir = $projectBaseDir . '/' . $tagSlug . '/posts'; // UPDATED PATH

            if(!is_dir($postImagesDir)) mkdir($postImagesDir, 0755, true);
            if(!is_dir($postPostsDir)) mkdir($postPostsDir, 0755, true);

            $uploadedImages = [];
            $thumbnailPathRel = '';

            // === Thumbnail upload ===
            if(isset($_FILES['thumbnail']) && isset($_FILES['thumbnail']['name']) && $_FILES['thumbnail']['name'] !== ''){
                $ext = strtolower(pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION)) ?: 'jpg';
                $thumbTarget = $postImagesDir . '/thumbnail.' . $ext;
                if(move_uploaded_file($_FILES['thumbnail']['tmp_name'], $thumbTarget)){
                    $thumbnailPathRel = rtrim($baseUrl, '/') . '/assets/' . $tagSlug . '/images/' . $slug . '/thumbnail.' . $ext;
                } else {
                    $errors[] = "Failed uploading thumbnail.";
                }
            }

            // === Multiple image uploads ===
            if(isset($_FILES['upload_images'])){
                $names = $_FILES['upload_images']['name'];
                $tmps = $_FILES['upload_images']['tmp_name'];
                for($i=0;$i<count($names);$i++){
                    $n = $names[$i]; $t = $tmps[$i];
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
                        $uploadedImages[] = rtrim($baseUrl, '/') . '/assets/' . $tagSlug . '/images/' . $slug . '/' . basename($imgTarget);
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
                    $thumbnailPathRel = rtrim($baseUrl, '/') . '/assets/' . $tagSlug . '/images/' . $slug . '/thumbnail.' . $ext;
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

            
            $imagesHTML = buildImagesHTML($imagesToUse);

            // Build post HTML
            $postFileName = $slug . '.html';
            $postFilePath = $postPostsDir . '/' . $postFileName;
            $displayDate = htmlspecialchars($date);
            $displayTime = htmlspecialchars($time);
            $displayLocation = htmlspecialchars($location);
            $displayTitle = htmlspecialchars($title);
            $displayBio = nl2br(htmlspecialchars($bio));
            $displayContent = $content;

            // ** New Footer Variables **
            $displayProjectName = htmlspecialchars($tag); // The Project Name

            // === START FUTURISTIC TEMPLATE INSERTION 2/2 (REPLACED) ===
            $postHTML = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>{$displayTitle}</title>
<style>
/*
 * Post Page Styles - Futuristic Dark Theme
 */
:root {
    --bg-primary: #12121e; /* Deep Space Blue/Black */
    --bg-secondary: #1a1a2b; /* Slightly lighter card background */
    --text-color: #e0e0ff; /* Light, slightly blue text */
    --text-secondary: #9aa8b5;
    --accent-color-1: #00e5ff; /* Electric Blue (Primary) */
    --accent-color-2: #ff00ff; /* Neon Magenta (Secondary Accent) */
    --card-shadow: 0 8px 30px rgba(0, 229, 255, 0.15); /* Light blue glow shadow */
    --border-color: #2c2c40;
}
body {
    font-family: 'Space Mono', monospace, sans-serif; /* Techy font stack */
    background: var(--bg-primary);
    color: var(--text-color);
    /* Changed padding to 0 to remove side margins on mobile */
    padding: 0;
    line-height: 1.6;
    min-height: 100vh;
    margin: 0; /* Ensures no default body margin */
    box-sizing: border-box; /* Good practice for responsive design */
}

/* Updated: Increased border-radius for smoother main borders */
.container {
    max-width: 900px;
    /* Adjusted margin-top/bottom and set margin-left/right to auto */
    margin: 40px auto;
    background: var(--bg-secondary);
    border: 2px solid var(--border-color);
    border-radius: 30px; /* Increased for smoother corners */
    padding: 40px;
    box-shadow: var(--card-shadow);
    /* Ensures padding is included in the width */
    box-sizing: border-box;
}

/* --- Title Size Adjustment & Rounded Tips --- */
h1 {
    color: var(--accent-color-1);
    font-size: 2.0em;
    font-weight: 700;
    margin-bottom: 10px;
    /* Adding a subtle curve to the bottom border line */
    border-bottom: 2px solid var(--border-color);
    border-radius: 0 0 4px 4px; /* Curved bottom tips */
    padding-bottom: 15px;
}
/* --- Meta Data Size Adjustment --- */
.meta {
    font-size: 0.8em;
    color: var(--text-secondary);
    margin-bottom: 25px;
    display: block;
}

/* --- Images Stacking (Desktop/Default) --- */
.images {
    display: grid;
    /* 徴 CHANGE: Forced single column (vertical stack) on all screen sizes */
    grid-template-columns: 1fr;
    gap: 20px;
    margin-top: 40px;
}
.images img {
    width: 100%;
    /* Increased image border radius slightly for a softer look */
    border-radius: 14px;
    border: 1px solid var(--border-color);
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.5);
    transition: transform 0.3s, opacity 0.3s;
    cursor: pointer;
}
.images img:hover {
    transform: scale(1.02);
    opacity: 1;
    border-color: var(--accent-color-1);
}

/* New: Media query for mobile-specific styles */
@media (max-width: 768px) {
    body {
        /* Adjusted vertical padding for mobile */
        padding-top: 20px;
        padding-bottom: 20px;
    }
    .container {
        /* Set to 100% width on smaller screens, removing the side margins */
        max-width: 100%;
        width: 100%;
        margin: 0 auto; /* Remove margin on the sides */
        border-radius: 0; /* Keep border-radius 0 for a seamless mobile look */
        border-left: none; /* Remove side borders */
        border-right: none;
        padding: 20px 15px; /* Reduced padding for smaller screens */
    }
    h1 {
        font-size: 1.6em; /* Slightly smaller heading for mobile view */
    }
    /* The .images grid-template-columns: 1fr; is already applied from the general style above. */
    .images {
        gap: 15px; /* Slightly reduced gap on mobile */
    }
    .images img {
        /* Ensure images take full width of their container */
        width: 100%;
    }
}


.bio {
    font-style: italic;
    color: var(--text-secondary);
    margin-bottom: 30px;
    /* Added border-radius to the left border for a rounded tip */
    border-left: 3px solid var(--accent-color-2); /* Neon magenta accent */
    border-radius: 0 0 0 4px; /* subtle curve on the bottom left tip */
    padding-left: 15px;
    line-height: 1.4;
}
.content {
    line-height: 1.8;
    color: var(--text-color);
    font-size: 1em;
}

footer {
    margin-top: 40px;
    padding-top: 20px;
    border-top: 1px solid var(--border-color);
    text-align: center;
    font-size: 0.9rem;
    color: var(--text-secondary);
}
a.back {
    color: var(--accent-color-1);
    text-decoration: none;
    font-weight: 600;
    display: inline-block;
    border-bottom: 1px dashed var(--accent-color-1);
    transition: color 0.2s, border-bottom-color 0.2s;
}
a.back:hover {
    color: var(--accent-color-2);
    border-bottom-color: var(--accent-color-2);
}
/* Popup styles for image viewing */
.popup{position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.95);display:flex;align-items:center;justify-content:center;opacity:0;pointer-events:none;transition:opacity .3s ease;z-index:9999;}
.popup.active{opacity:1;pointer-events:all;}
.popup img{max-width:90%;max-height:90%;border-radius:12px;box-shadow:0 0 25px rgba(0, 229, 255, 0.4);}
.popup span{position:absolute;top:30px;right:40px;font-size:2rem;color:var(--accent-color-1);cursor:pointer;user-select:none;}
/* New: Adjust popup close button for mobile */
@media (max-width: 768px) {
    .popup span {
        top: 20px;
        right: 20px;
        font-size: 1.8rem;
    }
}
</style>
</head>
<body>
<div class="container">
<h1>{$displayTitle}</h1>
<div class="meta">
🗓️ <span id="date">{$displayDate}</span> |
⏰ <span id="time">{$displayTime}</span> |
📍 <span id="location">{$displayLocation}</span>
</div>
<div class="bio">{$displayBio}</div>
<div class="content">{$displayContent}</div>
<div class="images">{$imagesHTML}</div>
<footer><a href="javascript:history.back()" class="back">← Back to {$displayProjectName}</a></footer>
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
            // === END FUTURISTIC TEMPLATE INSERTION 2/2 (REPLACED) ===

            if(file_put_contents($postFilePath, $postHTML)){
                $postsArr[] = [
                    "tag" => $tag,
                    "title" => $title,
                    "date" => $date . ' ' . $time,
                    "thumbnail" => $thumbnailPathRel ?: ($uploadedImages[0] ?? ''),
                    "file" => rtrim($baseUrl, '/') . '/assets/' . $tagSlug . '/posts/' . $postFileName,
                    "desc" => $bio,
                    "location" => $location
                ];
                file_put_contents($jsonFile, json_encode($postsArr, JSON_PRETTY_PRINT));
                $message = "✅ Post created successfully: " . rtrim($baseUrl, '/') . "/assets/{$tagSlug}/posts/{$postFileName}";
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

        // Get tag from JSON or URL (if editing an older post)
        $tagEdit = $p['tag'] ?? (isset($_GET['tag']) ? $_GET['tag'] : 'default');
        $tagSlug = slugify($tagEdit);

        $editData['tag'] = $tagEdit;
        $editData['orig_tag_slug'] = $tagSlug;

        // read HTML to get bio and content if available
        $htmlPath = $projectBaseDir . '/' . $tagSlug . '/posts/' . $slugEdit . '.html';
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
        $editData['images_list'] = getImagesForSlug($projectBaseDir, $tagSlug, $slugEdit, $baseUrl);
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
    'images' => '',
    'tag' => '' // NEW
];
if(!isset($editData) || $editData === null){
    if(isset($_SESSION['form_data']) && is_array($_SESSION['form_data'])){
        $sd = $_SESSION['form_data'];
        $formData = array_merge($formData, $sd);
    }
}

// Load any message from GET (after PRG)
if(isset($_GET['msg'])) $message = htmlspecialchars($_GET['msg']);

// Load projects list for the dropdown
$projectsList = [];
foreach($projectsArr as $p){
    $projectsList[slugify($p['name'])] = $p['name'];
}
$currentTag = htmlspecialchars($editData['tag'] ?? ($formData['tag'] ?? ''));
$currentTagSlug = slugify($currentTag);

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
<input type="hidden" name="orig_tag_slug" value="<?php echo htmlspecialchars($editData['orig_tag_slug'] ?? $currentTagSlug); ?>">
<?php endif; ?>

<label>Post Title</label>
<input type="text" name="title" required placeholder="My crazy post title" value="<?php echo htmlspecialchars($editData['title'] ?? $formData['title']); ?>">

<label>Tag / Project Name</label>
<select name="tag_select" id="tag_select" onchange="document.getElementById('tag_new').value = this.value === 'new' ? '' : this.options[this.selectedIndex].text; document.getElementById('tag_new').style.display = this.value === 'new' ? 'block' : 'none';">
    <option value="">-- Select Existing Project --</option>
    <?php foreach($projectsList as $slug => $name): ?>
        <option value="<?php echo htmlspecialchars($slug); ?>" <?php if($currentTagSlug === $slug) echo 'selected'; ?>>
            <?php echo htmlspecialchars($name); ?>
        </option>
    <?php endforeach; ?>
    <option value="new" <?php if($currentTag === '' || (!empty($currentTag) && !isset($projectsList[$currentTagSlug]))) echo 'selected'; ?>>-- New Project --</option>
</select>
<input type="text" name="tag_new" id="tag_new" placeholder="Type New Project Name"
    value="<?php echo htmlspecialchars($currentTag); ?>"
    style="margin-top: 5px; <?php if($currentTag === '' || !isset($projectsList[$currentTagSlug])) echo 'display:block;'; else echo 'display:none;'; ?>">
<input type="hidden" name="tag_hidden" id="tag_hidden" value="<?php echo htmlspecialchars($currentTag); ?>">
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const select = document.getElementById('tag_select');
        const textInput = document.getElementById('tag_new');

        // Initial setup for the text input display
        if (select.value && select.value !== 'new' && select.value !== '') {
            textInput.style.display = 'none';
        } else {
            textInput.style.display = 'block';
        }

        document.getElementById('postForm').addEventListener('submit', function() {
            const selectedSlug = select.value;
            // Set tag_hidden to the value in the text box if 'New' or nothing is selected, otherwise use the selected project name.
            if (selectedSlug === 'new' || selectedSlug === '') {
                document.getElementById('tag_hidden').value = textInput.value;
            } else {
                document.getElementById('tag_hidden').value = select.options[select.selectedIndex].text;
            }
        });
    });
</script>

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
        $tag = htmlspecialchars($p['tag'] ?? 'Untagged');
        $tagSlug = slugify($p['tag'] ?? 'Untagged');
        $title = htmlspecialchars($p['title']);
        echo '<li style="margin-bottom:6px;">';
        echo "[$tag] $title ";
        // Edit link
        echo ' <a href="?edit='.$slug.'&tag='.urlencode($tag).'" style="background:#0099ff;color:#fff;padding:5px 10px;border-radius:4px;text-decoration:none;margin-left:8px;">Edit</a> ';
        echo '<form method="POST" style="display:inline;margin-left:6px;" onsubmit="return confirm(\'Delete this post?\');">';
        echo '<input type="hidden" name="delete_post" value="'.$slug.'">';
        echo '<input type="hidden" name="delete_tag_slug" value="'.$tagSlug.'">';
        echo '<button type="submit" class="danger">Delete</button>';
        echo '</form></li>';
    }
    echo '</ul>';
}else{
    echo '<p>No posts yet.</p>';
}
?>

<hr>
<h2>Project Management</h2>

<?php
$projectEditData = null;
if(isset($_GET['edit_project']) && $_GET['edit_project'] !== ''){
    $slugEdit = $_GET['edit_project'];
    $idx = findProjectIndexBySlug($projectsArr, $slugEdit);
    if($idx !== false){
        $p = $projectsArr[$idx];
        $projectEditData = $p;
        $projectEditData['thumbnail'] = getProjectThumbnailPath($projectBaseDir, $slugEdit, $baseUrl);
    }
}

$pFormData = [
    'name' => '',
    'bio' => ''
];
if($projectEditData){
    $pFormData['name'] = $projectEditData['name'];
    $pFormData['bio'] = $projectEditData['bio'];
}

?>

<form method="POST" enctype="multipart/form-data" id="projectForm">
    <input type="hidden" name="project_action" value="save">

    <label>Project Name</label>
    <input type="text" name="project_name" required placeholder="My Amazing Project" value="<?php echo htmlspecialchars($pFormData['name']); ?>">

    <label>Project Bio / Summary</label>
    <textarea name="project_bio" rows="2" placeholder="Short description or bio" required><?php echo htmlspecialchars($pFormData['bio']); ?></textarea>

    <?php if(isset($projectEditData) && $projectEditData !== null): ?>
    <div>
        <label>Thumbnail (existing)</label>
        <?php if(!empty($projectEditData['thumbnail'])): ?>
            <img src="<?php echo htmlspecialchars($projectEditData['thumbnail']); ?>" class="thumb-preview" alt="thumbnail">
            <div>
                <label><input type="checkbox" name="delete_thumbnail" value="1"> Delete current thumbnail</label>
            </div>
        <?php else: ?>
            <small>No thumbnail yet.</small>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <label>Project Thumbnail</label>
    <input type="file" name="project_thumbnail" accept="image/*">

    <div style="margin-top:12px;">
        <button type="submit"><?php echo $projectEditData ? 'Update Project' : 'Create Project'; ?></button>
        <?php if($projectEditData): ?>
            <a class="smallbtn" href="<?php echo strtok($_SERVER['REQUEST_URI'], '?'); ?>">Cancel Edit</a>
        <?php endif; ?>
    </div>
</form>

<h3>Current Projects</h3>
<?php
// Reload projects array in case project actions happened above
$projectsArr = json_decode(file_get_contents($projectsJsonFile), true);
if(is_array($projectsArr) && count($projectsArr) > 0){
    echo '<ul>';
    foreach($projectsArr as $p){
        $tag = htmlspecialchars($p['name']);
        $slug = slugify($p['name']);
        $thumb = getProjectThumbnailPath($projectBaseDir, $slug, $baseUrl);
        echo '<li style="margin-bottom:6px;">';
        echo ($thumb ? '<img src="'.htmlspecialchars($thumb).'" style="max-width:30px;vertical-align:middle;margin-right:8px;"/> ' : '') . $tag;
        // Edit link
        echo ' <a href="?edit_project='.$slug.'" style="background:#0099ff;color:#fff;padding:5px 10px;border-radius:4px;text-decoration:none;margin-left:8px;">Edit</a> ';
        echo '<form method="POST" style="display:inline;margin-left:6px;" onsubmit="return confirm(\'WARNING: Deleting a project will NOT delete its posts. You must delete all posts in this project first. Are you sure you want to delete the project structure?\');">';
        echo '<input type="hidden" name="project_action" value="delete">';
        echo '<input type="hidden" name="project_name" value="'.$tag.'">';
        echo '<button type="submit" class="danger">Delete</button>';
        echo '</form></li>';
    }
    echo '</ul>';
}else{
    echo '<p>No projects yet.</p>';
}
?>

<hr>
<small>Enjoy Making Great Things</small>
</div>

<script src="cms.js"></script>
</body>
</html>
