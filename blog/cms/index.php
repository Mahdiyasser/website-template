<?php
// Post Maker - Absolute /blog/ Path Edition
// by Mahdi & ChatGPT

ini_set('display_errors', 1);
error_reporting(E_ALL);

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

// Basic checks
if(!is_dir($postsDir)) $errors[] = "Posts folder missing: $postsDir";
if(!is_dir($imagesBase)) $errors[] = "Images base folder missing: $imagesBase";
if(!file_exists($jsonFile)) $errors[] = "JSON file missing: $jsonFile";

if(count($errors) === 0 && !is_writable($postsDir)) $errors[] = "Posts folder not writable: $postsDir";
if(count($errors) === 0 && !is_writable($imagesBase)) $errors[] = "Images base folder not writable: $imagesBase";
if(count($errors) === 0 && !is_writable($jsonFile)) $errors[] = "JSON file not writable: $jsonFile";

if($_SERVER['REQUEST_METHOD'] === 'POST' && count($errors) === 0){
    $title = trim($_POST['title'] ?? '');
    $date = trim($_POST['date'] ?? date('Y-m-d'));
    $time = trim($_POST['time'] ?? date('H:i'));
    $location = trim($_POST['location'] ?? $locationDefault);
    $bio = trim($_POST['bio'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $imagesurls = trim($_POST['images'] ?? '');
    $imagesUrlList = array_filter(array_map('trim', explode(',', $imagesurls)));

    if($title === '') $errors[] = "Title is required.";

    if(count($errors) === 0){
        $postsJsonRaw = file_get_contents($jsonFile);
        $postsArr = json_decode($postsJsonRaw, true);
        if(!is_array($postsArr)) $postsArr = [];

        $slugBase = slugify($title);
        if($slugBase === '') $slugBase = 'post';
        $slug = $slugBase;
        $suffix = 1;
        $exists = true;
        while($exists){
            $exists = false;
            foreach($postsArr as $p){
                $basename = pathinfo($p['file'] ?? '', PATHINFO_FILENAME);
                if($basename === $slug){
                    $exists = true;
                    break;
                }
            }
            if($exists){
                $suffix++;
                $slug = $slugBase . '-' . $suffix;
            }
        }

        $postImagesDir = $imagesBase . '/' . $slug;
        if(!is_dir($postImagesDir)){
            if(!mkdir($postImagesDir, 0755, true)){
                $errors[] = "Could not create post images folder: $postImagesDir";
            }
        }

        $uploadedImages = [];
        $thumbnailPathRel = '';

        // === Thumbnail upload ===
        if(isset($_FILES['thumbnail']) && $_FILES['thumbnail']['name'] !== ''){
            $ext = strtolower(pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION)) ?: 'jpg';
            $thumbTarget = $postImagesDir . '/thumbnail.' . $ext;
            if(move_uploaded_file($_FILES['thumbnail']['tmp_name'], $thumbTarget)){
                $thumbnailPathRel = "$baseUrl/assets/images/$slug/thumbnail.$ext";
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
                $imgTarget = $postImagesDir . '/image' . (count($uploadedImages) + 1) . '.' . $ext;
                $k = 1;
                while(file_exists($imgTarget)){
                    $k++;
                    $imgTarget = $postImagesDir . '/image' . (count($uploadedImages) + 1) . '-' . $k . '.' . $ext;
                }
                if(move_uploaded_file($t, $imgTarget)){
                    $uploadedImages[] = "$baseUrl/assets/images/$slug/" . basename($imgTarget);
                } else {
                    $errors[] = "Failed uploading image: $n";
                }
            }
        }

        // === Use first image as thumbnail if none uploaded ===
        if($thumbnailPathRel === '' && count($uploadedImages) > 0){
            $firstImgAbs = $baseDir . '/' . str_replace("$baseUrl/", '', $uploadedImages[0]);
            $ext = strtolower(pathinfo($firstImgAbs, PATHINFO_EXTENSION));
            $thumbTargetAbs = $postImagesDir . '/thumbnail.' . $ext;
            if(copy($firstImgAbs, $thumbTargetAbs)){
                $thumbnailPathRel = "$baseUrl/assets/images/$slug/thumbnail.$ext";
            } else {
                $thumbnailPathRel = $uploadedImages[0];
            }
        }

        // === Image URLs (external or manual) ===
        foreach($imagesUrlList as $url){
            if($url === '') continue;
            if(!preg_match('#^https?://#', $url)){
                $url = "$baseUrl/" . ltrim($url, '/');
            }
            $uploadedImages[] = str_replace(' ', '-', $url);
        }

        // === Build post HTML ===
        $postFileName = $slug . '.html';
        $postFilePath = $postsDir . '/' . $postFileName;
        $displayDate = htmlspecialchars($date);
        $displayTime = htmlspecialchars($time);
        $displayLocation = htmlspecialchars($location);
        $displayTitle = htmlspecialchars($title);
        $displayBio = nl2br(htmlspecialchars($bio));
        $displayContent = $content;
        $imagesHTML = '';

        foreach($uploadedImages as $imgRel){
            $imgRel = str_replace(' ', '-', $imgRel);
            $imagesHTML .= '<img src="' . $imgRel . '" alt="Image">' . "\n";
        }

        if($imagesHTML === ''){
            $imagesHTML = '<img src="' . $baseUrl . '/assets/images/example1.jpg" alt="Example image 1">' . "\n" .
                          '<img src="' . $baseUrl . '/assets/images/example2.jpg" alt="Example image 2">' . "\n";
        }

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
                "file" => "$baseUrl/assets/posts/" . $postFileName,
                "desc" => $bio,
                "location" => $location
            ];
            file_put_contents($jsonFile, json_encode($postsArr, JSON_PRETTY_PRINT));
            $message = "✅ Post created successfully: $baseUrl/assets/posts/{$postFileName}";
        } else {
            $errors[] = "Failed writing post file.";
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Fucked Up CMS</title>
<style>
body{font-family:'Poppins',sans-serif;background:#0f0f0f;color:#eaeaea;padding:18px;}
.container{max-width:900px;margin:0 auto;}
input,textarea{width:100%;padding:8px;margin:8px 0;border-radius:6px;border:none;}
label{font-weight:bold;margin-top:10px;display:block;}
button{background:#00ff99;color:#000;border:none;padding:10px 16px;border-radius:6px;cursor:pointer;font-weight:700;}
.message{background:#0b3119;color:#bff6d9;padding:10px;border-radius:6px;margin:12px 0;}
.error{background:#321313;color:#ffbdbd;padding:10px;border-radius:6px;margin:12px 0;}
small{color:#aaa;}
</style>
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
<form method="POST" enctype="multipart/form-data">
<label>Post Title</label>
<input type="text" name="title" required placeholder="My crazy post title">

<label>Date</label>
<input type="date" name="date" value="<?php echo date('Y-m-d'); ?>" required>

<label>Time</label>
<input type="time" name="time" value="<?php echo date('H:i'); ?>" required>

<label>Location</label>
<input type="text" name="location" placeholder="Enter location (e.g. Hosh Issa, Beheira, Egypt)" required>

<label>Post Bio / Summary</label>
<textarea name="bio" rows="2" placeholder="Short description or bio" required></textarea>

<label>Post Content (raw HTML allowed)</label>
<textarea name="content" rows="8" placeholder="Write full content here (HTML allowed)"></textarea>

<label>Thumbnail (upload)</label>
<input type="file" name="thumbnail" accept="image/*">

<label>Other Images (multiple)</label>
<input type="file" name="upload_images[]" accept="image/*" multiple>

<label>Optional: image URLs (comma separated)</label>
<input type="text" name="images" placeholder="/blog/assets/images/...jpg, https://...">

<button type="submit">Create Post</button>
</form>
<hr>
<small>Enjoy Making Great Things</small>
</div>
</body>
</html>

