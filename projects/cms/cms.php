<?php
// cms.php
// Simple file-based CMS backend for Projects + Posts
// WARNING: For production, add auth, harden uploads, and sanitize more strictly.
// This file expects to sit inside /projects/cms/ and will use parent folder for data.

header('Content-Type: application/json; charset=utf-8');

$BASE = realpath(__DIR__ . '/..');            // parent dir e.g. /projects
$ASSETS = $BASE . '/assets';
$PROJECTS_JSON = $BASE . '/projects.json';
$POSTS_JSON = $BASE . '/posts.json';

// helper: return JSON and exit
function respond($data) {
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// basic sanitize for filenames (keeps letters, numbers, -, _)
function slugify($s) {
    $s = trim(mb_strtolower($s));
    $s = preg_replace('/[^\p{L}\p{N}\-_ ]+/u', '', $s); // remove weird chars
    $s = preg_replace('/\s+/', '-', $s);
    $s = trim($s, '-_');
    if ($s === '') return 'item-' . bin2hex(random_bytes(3));
    return $s;
}

function read_json($file) {
    if (!file_exists($file)) return [];
    $txt = file_get_contents($file);
    $arr = json_decode($txt, true);
    if (!is_array($arr)) return [];
    return $arr;
}

function save_json($file, $arr) {
    file_put_contents($file, json_encode($arr, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

// ensure assets dir exists
if (!is_dir($ASSETS)) {
    mkdir($ASSETS, 0777, true);
}

// Allow CORS for dev (adjust or remove in production)
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    respond(['status'=>'ok']);
}

// read action
$action = $_REQUEST['action'] ?? 'status';

switch ($action) {
    case 'status':
        respond(['status' => 'ok', 'msg' => 'cms ready']);
        break;

    /* ========================
       PROJECTS CRUD
       ======================== */

    case 'listProjects':
        $projects = read_json($PROJECTS_JSON);
        respond(['status'=>'ok', 'projects'=>$projects]);
        break;

    case 'createProject':
        // expected: title, bio, date (optional), thumbnail file optional
        $title = trim($_POST['title'] ?? '');
        if ($title === '') respond(['status'=>'error','msg'=>'title required']);

        $bio = $_POST['bio'] ?? '';
        $date = $_POST['date'] ?? date('Y-m-d');
        $slug = slugify($title);
        $projectDir = "$ASSETS/$slug";

        if (!is_dir($projectDir)) {
            if (!mkdir("$projectDir/images", 0777, true) || !mkdir("$projectDir/posts", 0777, true)) {
                respond(['status'=>'error','msg'=>'failed to create project folders']);
            }
        }

        // handle thumbnail upload
        $thumbnailFilename = $slug; // base name; extension optional
        if (!empty($_FILES['thumbnail']['name'])) {
            $tmp = $_FILES['thumbnail']['tmp_name'];
            $ext = pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION);
            $ext = preg_replace('/[^a-zA-Z0-9]/', '', $ext);
            $dest = "$projectDir/images/{$thumbnailFilename}." . ($ext ?: 'jpg');
            if (!move_uploaded_file($tmp, $dest)) {
                respond(['status'=>'error','msg'=>'thumbnail upload failed']);
            }
            $thumbnailPath = "assets/$slug/images/" . basename($dest);
        } else {
            $thumbnailPath = "assets/$slug/images/{$thumbnailFilename}.jpg"; // default path (may not exist)
        }

        // save into projects.json
        $projects = read_json($PROJECTS_JSON);
        // ensure unique title or allow duplicates? We'll just push.
        $projects[] = [
            'title' => $title,
            'bio' => $bio,
            'thumbnail' => $thumbnailPath,
            'slug' => $slug,
            'date' => $date
        ];
        save_json($PROJECTS_JSON, $projects);
        respond(['status'=>'ok','msg'=>'project created','project'=>end($projects)]);
        break;

    case 'updateProject':
        // expects slug (existing), new title/bio/date, optional thumbnail
        $slug = $_POST['slug'] ?? '';
        if ($slug === '') respond(['status'=>'error','msg'=>'slug required']);
        $projects = read_json($PROJECTS_JSON);
        $found = false;
        foreach ($projects as $i => $p) {
            if (($p['slug'] ?? '') === $slug) {
                $found = true;
                $title = $_POST['title'] ?? $p['title'];
                $bio = $_POST['bio'] ?? $p['bio'];
                $date = $_POST['date'] ?? $p['date'];
                // rename slug if title changed? We'll allow renaming (create new slug + move folder)
                $newSlug = slugify($title);
                $oldDir = "$ASSETS/$slug";
                $newDir = "$ASSETS/$newSlug";
                if ($newSlug !== $slug) {
                    if (!is_dir($oldDir)) {
                        // nothing to move, but still update slug
                        mkdir($newDir . '/images', 0777, true);
                        mkdir($newDir . '/posts', 0777, true);
                    } else {
                        // attempt rename
                        if (!rename($oldDir, $newDir)) {
                            respond(['status'=>'error','msg'=>'failed to rename project folder']);
                        }
                    }
                    // update any posts entries in posts.json that referenced old slug as project
                    $posts = read_json($POSTS_JSON);
                    foreach ($posts as $pi => $post) {
                        if (($post['project'] ?? '') === ($p['title'] ?? '')) {
                            // keep project title string; do nothing
                        }
                    }
                }
                // handle thumbnail upload
                $thumbnailPath = $p['thumbnail'] ?? "assets/$newSlug/images/$newSlug.jpg";
                if (!empty($_FILES['thumbnail']['name'])) {
                    $tmp = $_FILES['thumbnail']['tmp_name'];
                    $ext = pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION);
                    $ext = preg_replace('/[^a-zA-Z0-9]/', '', $ext);
                    $dest = "$ASSETS/$newSlug/images/{$newSlug}." . ($ext ?: 'jpg');
                    if (!move_uploaded_file($tmp, $dest)) {
                        respond(['status'=>'error','msg'=>'thumbnail upload failed']);
                    }
                    $thumbnailPath = "assets/$newSlug/images/" . basename($dest);
                }
                // update
                $projects[$i]['title'] = $title;
                $projects[$i]['bio'] = $bio;
                $projects[$i]['date'] = $date;
                $projects[$i]['slug'] = $newSlug;
                $projects[$i]['thumbnail'] = $thumbnailPath;
                save_json($PROJECTS_JSON, $projects);
                respond(['status'=>'ok','msg'=>'project updated','project'=>$projects[$i]]);
            }
        }
        if (!$found) respond(['status'=>'error','msg'=>'project not found']);
        break;

    case 'deleteProject':
        $slug = $_POST['slug'] ?? '';
        if ($slug === '') respond(['status'=>'error','msg'=>'slug required']);
        $projects = read_json($PROJECTS_JSON);
        $new = [];
        $deleted = null;
        foreach ($projects as $p) {
            if (($p['slug'] ?? '') === $slug) { $deleted = $p; continue; }
            $new[] = $p;
        }
        if ($deleted === null) respond(['status'=>'error','msg'=>'project not found']);
        save_json($PROJECTS_JSON, $new);
        // remove posts that belong to this project (by title matching project title)
        $posts = read_json($POSTS_JSON);
        $kept = [];
        foreach ($posts as $post) {
            if (($post['project'] ?? '') === ($deleted['title'] ?? '')) {
                // delete post html file and images if present
                $postSlug = slugify($post['title']);
                $postHtml = "$ASSETS/{$slug}/posts/{$postSlug}.html";
                if (file_exists($postHtml)) @unlink($postHtml);
                // delete potential images
                $imgDir = "$ASSETS/{$slug}/images/";
                if (is_dir($imgDir)) {
                    foreach (glob($imgDir . "{$postSlug}*") as $f) @unlink($f);
                }
                continue;
            }
            $kept[] = $post;
        }
        save_json($POSTS_JSON, $kept);
        // delete project folder recursively
        $prDir = "$ASSETS/$slug";
        if (is_dir($prDir)) {
            // recursive delete
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($prDir, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($it as $file) {
                if ($file->isDir()) rmdir($file->getRealPath());
                else unlink($file->getRealPath());
            }
            rmdir($prDir);
        }
        respond(['status'=>'ok','msg'=>'project deleted','project'=>$deleted]);
        break;

    /* ========================
       POSTS CRUD
       ======================== */

    case 'listPosts':
        $posts = read_json($POSTS_JSON);
        respond(['status'=>'ok','posts'=>$posts]);
        break;

    case 'createPost':
        // expected: project (slug or title?), title, bio, date, content, files images[] thumbnail
        $project = $_POST['project'] ?? '';
        $title = trim($_POST['title'] ?? '');
        if ($project === '' || $title === '') respond(['status'=>'error','msg'=>'project and title required']);

        // find project slug by matching project title or slug in projects.json
        $projects = read_json($PROJECTS_JSON);
        $projSlug = '';
        $projTitle = '';
        foreach ($projects as $p) {
            if (($p['slug'] ?? '') === $project || ($p['title'] ?? '') === $project) {
                $projSlug = $p['slug'];
                $projTitle = $p['title'];
                break;
            }
        }
        if ($projSlug === '') {
            // fallback: assume project is already a slug and directory exists
            $projSlug = slugify($project);
            $projTitle = $project;
            if (!is_dir("$ASSETS/$projSlug")) {
                // create folder
                mkdir("$ASSETS/$projSlug/images", 0777, true);
                mkdir("$ASSETS/$projSlug/posts", 0777, true);
            }
        }

        $postSlug = slugify($title);
        $date = $_POST['date'] ?? date('Y-m-d');
        $bio = $_POST['bio'] ?? '';
        $content = $_POST['content'] ?? '';

        // save post HTML using post template
        $postDir = "$ASSETS/$projSlug/posts";
        if (!is_dir($postDir)) mkdir($postDir, 0777, true);

        // Build HTML content using a safe-ish template (no external template engine)
        $htmlSafeTitle = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $htmlSafeBio = nl2br(htmlspecialchars($bio, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        $htmlContent = $content; // assume content is HTML or sanitized by the user in CMS

        $postFile = "$postDir/{$postSlug}.html";
        $postRelativePath = "assets/$projSlug/posts/{$postSlug}.html";

        $postHtml = "<!doctype html>\n<html lang=\"en\">\n<head>\n<meta charset=\"utf-8\">\n<meta name=\"viewport\" content=\"width=device-width,initial-scale=1\">\n<title>{$htmlSafeTitle}</title>\n<link rel=\"stylesheet\" href=\"/style.css\">\n</head>\n<body>\n<div class=\"container\">\n<h1>{$htmlSafeTitle}</h1>\n<div class=\"meta\">📅 {$date} | 📍 {$projTitle}</div>\n<div class=\"bio\">{$htmlSafeBio}</div>\n<div class=\"content\">{$htmlContent}</div>\n</div>\n</body>\n</html>";

        if (file_put_contents($postFile, $postHtml) === false) {
            respond(['status'=>'error','msg'=>'failed to write post html']);
        }

        // upload images
        $imageDir = "$ASSETS/$projSlug/images";
        if (!is_dir($imageDir)) mkdir($imageDir, 0777, true);

        $thumbnailPath = "assets/$projSlug/images/{$postSlug}-thumbnail.jpg";
        if (!empty($_FILES['thumbnail']['name'])) {
            $tmp = $_FILES['thumbnail']['tmp_name'];
            $ext = pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION);
            $ext = preg_replace('/[^a-zA-Z0-9]/', '', $ext);
            $dest = "$imageDir/{$postSlug}-thumbnail." . ($ext ?: 'jpg');
            if (!move_uploaded_file($tmp, $dest)) {
                // continue but warn
                // respond(['status'=>'error','msg'=>'thumbnail upload failed']);
            } else {
                $thumbnailPath = "assets/$projSlug/images/" . basename($dest);
            }
        }

        // multiple images
        if (!empty($_FILES['images']['name'][0])) {
            foreach ($_FILES['images']['name'] as $idx => $nm) {
                $tmp = $_FILES['images']['tmp_name'][$idx];
                if ($tmp && is_uploaded_file($tmp)) {
                    $ext = pathinfo($nm, PATHINFO_EXTENSION);
                    $ext = preg_replace('/[^a-zA-Z0-9]/', '', $ext);
                    $dest = "$imageDir/{$postSlug}-image" . ($idx+1) . "." . ($ext ?: 'jpg');
                    move_uploaded_file($tmp, $dest);
                }
            }
        }

        // record in posts.json
        $posts = read_json($POSTS_JSON);
        $posts[] = [
            'title' => $title,
            'bio' => $bio,
            'thumbnail' => $thumbnailPath,
            'project' => $projTitle,
            'project_slug' => $projSlug,
            'date' => $date,
            'path' => $postRelativePath
        ];
        save_json($POSTS_JSON, $posts);

        respond(['status'=>'ok','msg'=>'post created','post'=>end($posts)]);
        break;

    case 'updatePost':
        // expects original title or path to identify post; we'll use index in posts.json or title+project
        $originalTitle = $_POST['orig_title'] ?? '';
        if ($originalTitle === '') respond(['status'=>'error','msg'=>'orig_title required']);
        $posts = read_json($POSTS_JSON);
        $found = false;
        foreach ($posts as $i => $p) {
            if (($p['title'] ?? '') === $originalTitle) {
                $found = true;
                $newTitle = $_POST['title'] ?? $p['title'];
                $newBio = $_POST['bio'] ?? $p['bio'];
                $newDate = $_POST['date'] ?? $p['date'];
                $newContent = $_POST['content'] ?? null; // if provided, update html
                $projSlug = $p['project_slug'] ?? slugify($p['project'] ?? '');
                $postSlugOld = slugify($p['title']);
                $postSlugNew = slugify($newTitle);

                $postDir = "$ASSETS/$projSlug/posts";
                $oldFile = "$postDir/{$postSlugOld}.html";
                $newFile = "$postDir/{$postSlugNew}.html";

                // update html file
                if ($newContent !== null) {
                    $htmlSafeTitle = htmlspecialchars($newTitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    $htmlSafeBio = nl2br(htmlspecialchars($newBio, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
                    $postHtml = "<!doctype html>\n<html lang=\"en\">\n<head>\n<meta charset=\"utf-8\">\n<meta name=\"viewport\" content=\"width=device-width,initial-scale=1\">\n<title>{$htmlSafeTitle}</title>\n<link rel=\"stylesheet\" href=\"/style.css\">\n</head>\n<body>\n<div class=\"container\">\n<h1>{$htmlSafeTitle}</h1>\n<div class=\"meta\">📅 {$newDate} | 📍 {$p['project']}</div>\n<div class=\"bio\">{$htmlSafeBio}</div>\n<div class=\"content\">{$newContent}</div>\n</div>\n</body>\n</html>";
                    file_put_contents($newFile, $postHtml);
                    // remove old file if slug changed
                    if ($newFile !== $oldFile && file_exists($oldFile)) @unlink($oldFile);
                } else {
                    // maybe rename file if title changed
                    if ($postSlugOld !== $postSlugNew && file_exists($oldFile)) {
                        rename($oldFile, $newFile);
                    }
                }

                // handle images upload (optional thumbnail/images)
                $imageDir = "$ASSETS/$projSlug/images";
                if (!is_dir($imageDir)) mkdir($imageDir, 0777, true);

                if (!empty($_FILES['thumbnail']['name'])) {
                    $tmp = $_FILES['thumbnail']['tmp_name'];
                    $ext = pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION);
                    $dest = "$imageDir/{$postSlugNew}-thumbnail." . ($ext ?: 'jpg');
                    move_uploaded_file($tmp, $dest);
                    $posts[$i]['thumbnail'] = "assets/$projSlug/images/" . basename($dest);
                }

                if (!empty($_FILES['images']['name'][0])) {
                    foreach ($_FILES['images']['name'] as $idx => $nm) {
                        $tmp = $_FILES['images']['tmp_name'][$idx];
                        if ($tmp && is_uploaded_file($tmp)) {
                            $ext = pathinfo($nm, PATHINFO_EXTENSION);
                            $dest = "$imageDir/{$postSlugNew}-image" . ($idx+1) . "." . ($ext ?: 'jpg');
                            move_uploaded_file($tmp, $dest);
                        }
                    }
                }

                // update posts.json entry
                $posts[$i]['title'] = $newTitle;
                $posts[$i]['bio'] = $newBio;
                $posts[$i]['date'] = $newDate;
                $posts[$i]['path'] = "assets/$projSlug/posts/{$postSlugNew}.html";

                save_json($POSTS_JSON, $posts);
                respond(['status'=>'ok','msg'=>'post updated','post'=>$posts[$i]]);
            }
        }
        if (!$found) respond(['status'=>'error','msg'=>'post not found']);
        break;

    case 'deletePost':
        $title = $_POST['title'] ?? '';
        if ($title === '') respond(['status'=>'error','msg'=>'title required']);
        $posts = read_json($POSTS_JSON);
        $new = [];
        $deleted = null;
        foreach ($posts as $p) {
            if ($p['title'] === $title) { $deleted = $p; continue; }
            $new[] = $p;
        }
        if ($deleted === null) respond(['status'=>'error','msg'=>'post not found']);
        save_json($POSTS_JSON, $new);
        // delete files
        $projSlug = $deleted['project_slug'] ?? slugify($deleted['project'] ?? '');
        $postSlug = slugify($deleted['title']);
        $postFile = "$ASSETS/$projSlug/posts/{$postSlug}.html";
        if (file_exists($postFile)) @unlink($postFile);
        $imgDir = "$ASSETS/$projSlug/images";
        if (is_dir($imgDir)) {
            foreach (glob($imgDir . "{$postSlug}*") as $f) @unlink($f);
        }
        respond(['status'=>'ok','msg'=>'post deleted','post'=>$deleted]);
        break;

    default:
        respond(['status'=>'error','msg'=>'unknown action']);
}
