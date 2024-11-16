<?php
/**
 * ReviewGrid - Interactive Image Gallery with Collaboration Features
 * 
 * @package   ReviewGrid
 * @version   1.0.1
 * @author    ktonini
 * @license   GPL-3.0
 * @link      https://github.com/ktonini/reviewGrid
 * 
 * A self-contained, single-file PHP application that transforms any directory 
 * of images into an interactive gallery. Features include:
 * - Real-time commenting and starring
 * - Automatic thumbnail generation
 * - Light/dark theme support
 * - IP-based user identification
 * - No database required
 * 
 * Requirements:
 * - PHP 8.0+
 * - GD extension with JPEG, PNG, and WebP support
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

// // Debug information
// echo "<pre style='background-color: #f0f0f0; padding: 10px; margin-bottom: 20px;'>";
// echo "PHP Version: " . phpversion() . "\n";
// echo "Server Software: " . $_SERVER['SERVER_SOFTWARE'] . "\n";
// echo "Server Name: " . $_SERVER['SERVER_NAME'] . "\n";
// echo "Server Address: " . $_SERVER['SERVER_ADDR'] . "\n";
// echo "Detected Client IP: " . getClientIP() . "\n";
// echo "REMOTE_ADDR: " . (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'Not set') . "\n";
// echo "HTTP X-Forwarded-For: " . (isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : 'Not set') . "\n";
// echo "HTTP Client IP: " . (isset($_SERVER['HTTP_CLIENT_IP']) ? $_SERVER['HTTP_CLIENT_IP'] : 'Not set') . "\n";
// echo "</pre>";

// Configuration
$baseUrl = rtrim((!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'], '/');
$scriptDir = dirname($_SERVER['SCRIPT_FILENAME']) . '/';
$dataDir = $scriptDir . '_data/';
$thumbsDir = $dataDir . 'thumbs/';
$subDir = trim(dirname($_SERVER['PHP_SELF']), '/');
$columns = 4;
$thumbWidth = 250;
$thumbHeight = 250;

// Create relative URLs for use in HTML
$relativeDataUrl = $subDir ? "/$subDir/_data" : '/_data';
$relativeThumbsUrl = $relativeDataUrl . '/thumbs';

// Get the directory name for the title
$dirName = basename($scriptDir);
$title = str_replace('_', ' ', $dirName);

// Create data directory if it doesn't exist
if (!file_exists($dataDir)) {
    mkdir($dataDir, 0755, true);
}

// Create thumbs directory if it doesn't exist
if (!file_exists($thumbsDir)) {
    mkdir($thumbsDir, 0755, true);
}

function getClientIP()
{
    $ip_keys = array('HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR');
    foreach ($ip_keys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                    return $ip;
                }
            }
        }
    }
    return 'UNKNOWN';
}

$user_ip = getClientIP();
$usersFile = $dataDir . 'data.json';

// Load or initialize users data
$data = file_exists($usersFile) ? json_decode(file_get_contents($usersFile), true) : [];

if (!isset($data[$user_ip])) {
    $data[$user_ip] = [
        'name' => $user_ip,
        'starred_images' => [],
        'footer_minimized' => false,
        'theme' => 'dark'  // Add this line
    ];
    file_put_contents($usersFile, json_encode($data));
}

// Ensure we have a name for the user (use IP if no name is set)
$user_name = isset($data[$user_ip]['name']) ? $data[$user_ip]['name'] : $user_ip;

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_name'])) {
        handleUpdateName();
    } elseif (isset($_POST['toggle_star'])) {
        handleToggleStar();
    } elseif (isset($_POST['add_comment'])) {
        handleAddComment();
    } elseif (isset($_POST['update_footer_state'])) {  // Add this condition
        handleFooterStateUpdate();
    } elseif (isset($_POST['update_theme'])) {
        handleThemeUpdate();
    }
    exit;
}

// Add this new function
function handleFooterStateUpdate()
{
    global $data, $user_ip, $usersFile;
    $minimized = $_POST['minimized'] === 'true';
    $data[$user_ip]['footer_minimized'] = $minimized;
    file_put_contents($usersFile, json_encode($data));
    echo json_encode(['success' => true]);
}

// Add a new function to handle theme updates
function handleThemeUpdate()
{
    global $data, $user_ip, $usersFile;
    $theme = $_POST['theme'];
    $data[$user_ip]['theme'] = $theme;
    file_put_contents($usersFile, json_encode($data));
    echo json_encode(['success' => true, 'theme' => $theme]);
}

// Handle GET requests for updates
if (isset($_GET['check_updates'])) {
    handleCheckUpdates();
    exit;
}

// Get all images in the script directory
$images = glob($scriptDir . '*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE);

// Remove the script itself from the images array
$images = array_filter($images, function ($key) {
    return basename($key) !== basename($_SERVER['SCRIPT_NAME']);
}, ARRAY_FILTER_USE_KEY);

// Add this near the top of the file, after loading the images
$validImages = array_map('basename', $images);

// Create thumbnails if they don't exist
foreach ($images as $image) {
    $filename = basename($image);
    $thumbFilename = preg_replace('/\.(jpg|jpeg|png|gif|webp)$/i', '.webp', $filename);
    $thumbPath = $thumbsDir . $thumbFilename;

    if (!file_exists($thumbPath)) {
        createThumbnail($image, $thumbPath, $thumbWidth, $thumbHeight);
    }
}

$starredImages = $data[$user_ip]['starred_images'];

// Function to create thumbnail
function createThumbnail($source, $destination, $width, $height)
{
    if (!function_exists('imagecreatetruecolor')) {
        die("GD Library is not enabled.");
    }

    list($w, $h) = getimagesize($source);
    $ratio = max($width / $w, $height / $h);
    $h = ceil($height / $ratio);
    $x = ($w - $width / $ratio) / 2;
    $w = ceil($width / $ratio);

    $imgString = file_get_contents($source);
    $image = imagecreatefromstring($imgString);

    if (!$image) {
        // If imagecreatefromstring fails, try to create from WebP
        if (function_exists('imagecreatefromwebp')) {
            $image = imagecreatefromwebp($source);
        }
    }

    if (!$image) {
        die("Unable to create image from source.");
    }

    $tmp = imagecreatetruecolor($width, $height);
    imagecopyresampled($tmp, $image, 0, 0, (int)$x, 0, $width, $height, $w, $h);

    $webpDestination = preg_replace('/\.(jpg|jpeg|png|gif|webp)$/i', '.webp', $destination);

    if (function_exists('imagewebp')) {
        // Create WebP thumbnail
        imagewebp($tmp, $webpDestination, 80); // 80 is the quality (0-100)
        $thumbnailName = basename($webpDestination);
    } else {
        // Fallback to original format if WebP is not supported
        switch (strtolower(pathinfo($source, PATHINFO_EXTENSION))) {
            case 'jpeg':
            case 'jpg':
                imagejpeg($tmp, $destination, 90);
                break;
            case 'png':
                imagepng($tmp, $destination, 9);
                break;
            case 'gif':
                imagegif($tmp, $destination);
                break;
        }
        $thumbnailName = basename($destination);
    }

    imagedestroy($image);
    imagedestroy($tmp);

    return $thumbnailName; // Return the name of the created thumbnail
}

// Function to generate a title from filename
function generateTitle($filename)
{
    $title = pathinfo($filename, PATHINFO_FILENAME);
    $title = preg_replace('/[_-]/', ' ', $title);
    $title = preg_replace('/\s+/', ' ', $title);
    return $title;
}

// Function to handle name update
function handleUpdateName()
{
    global $data, $user_ip, $usersFile;
    $new_name = trim($_POST['update_name']);
    if (!empty($new_name)) {
        $data[$user_ip]['name'] = $new_name;
    } else {
        $data[$user_ip]['name'] = $user_ip;
    }
    file_put_contents($usersFile, json_encode($data));
    echo json_encode(['success' => true, 'name' => $data[$user_ip]['name']]);
}

// Function to handle star toggling
function handleToggleStar()
{
    global $data, $user_ip, $usersFile;
    $image = $_POST['toggle_star'];
    $index = array_search($image, $data[$user_ip]['starred_images']);
    if ($index === false) {
        $data[$user_ip]['starred_images'][] = $image;
    } else {
        unset($data[$user_ip]['starred_images'][$index]);
        $data[$user_ip]['starred_images'] = array_values($data[$user_ip]['starred_images']);
    }
    $result = file_put_contents($usersFile, json_encode($data));
    echo json_encode(['success' => ($result !== false)]);
}

// Function to handle comment addition
function handleAddComment()
{
    global $data, $user_ip, $usersFile;
    $image = $_POST['image'];
    $comment = trim($_POST['comment']);
    if (!isset($data[$user_ip]['comments'])) {
        $data[$user_ip]['comments'] = [];
    }
    if (!isset($data[$user_ip]['comment_timestamps'])) {
        $data[$user_ip]['comment_timestamps'] = [];
    }
    if (!empty($comment)) {
        $data[$user_ip]['comments'][$image] = $comment;
        $data[$user_ip]['comment_timestamps'][$image] = time();
    } else {
        unset($data[$user_ip]['comments'][$image]);
        unset($data[$user_ip]['comment_timestamps'][$image]);
    }
    $result = file_put_contents($usersFile, json_encode($data));
    echo json_encode([
        'success' => ($result !== false),
        'comment' => $comment,
        'name' => $data[$user_ip]['name'],
        'timestamp' => $data[$user_ip]['comment_timestamps'][$image] ?? time()
    ]);
}

// Function to handle update checks
function handleCheckUpdates()
{
    global $usersFile;
    $lastModified = isset($_GET['lastModified']) ? (int)$_GET['lastModified'] : 0;
    $currentModified = filemtime($usersFile);
    if ($currentModified > $lastModified) {
        $data = json_decode(file_get_contents($usersFile), true);
        echo json_encode([
            'updated' => true,
            'data' => $data,
            'lastModified' => $currentModified
        ]);
    } else {
        echo json_encode(['updated' => false, 'lastModified' => $currentModified]);
    }
}

// Function to generate HTML for an image container
function generateImageContainer($image, $subDir, $dataDir, $starredImages, $data, $user_ip, $baseUrl, $relativeThumbsUrl, $validImages)
{
    $imageName = basename($image);
    if (!in_array($imageName, $validImages)) {
        return '';
    }
    $filename = basename($image);
    $thumbFilename = preg_replace('/\.(jpg|jpeg|png|gif|webp)$/i', '.webp', $filename);
    $thumbPath = $relativeThumbsUrl . '/' . $thumbFilename;
    $fullImagePath = ($subDir ? '/' . $subDir : '') . '/' . $filename;
    $isStarred = in_array($filename, $starredImages);
    $title = generateTitle($filename);

    // Get image information
    $imageSize = filesize($image);
    $imageDimensions = getimagesize($image);
    $imageFileType = $imageDimensions['mime'];
    $imageInfo = [
        'size' => $imageSize < 1024 * 1024 ? number_format($imageSize / 1024, 2) . " KB" : ($imageSize < 1024 * 1024 * 1024 ? number_format($imageSize / (1024 * 1024), 2) . " MB" :
            number_format($imageSize / (1024 * 1024 * 1024), 2) . " GB"),
        'dimensions' => $imageDimensions[0] . " x " . $imageDimensions[1],
        'type' => $imageFileType
    ];

    ob_start();
?>
    <div class="image-container <?php echo $isStarred ? 'starred' : ''; ?>" data-image="<?php echo $filename; ?>" data-image-info='<?php echo htmlspecialchars(json_encode($imageInfo), ENT_QUOTES, 'UTF-8'); ?>'>
        <div class="image-wrapper">
            <img src="<?php echo $thumbPath; ?>" alt="<?php echo $title; ?>" data-full-image="<?php echo $fullImagePath; ?>">
            <button class="button star-button" title="Star" aria-label="Star image">★</button>
            <div class="hover-buttons">
                <a href="<?php echo $fullImagePath; ?>" download class="button download-button" title="Download" aria-label="Download image"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-download">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="7 10 12 15 17 10"></polyline>
                        <line x1="12" y1="15" x2="12" y2="3"></line>
                    </svg></a>
            </div>
            <div class="image-info-overlay">
                <span><?php echo $imageInfo['size']; ?></span><span><?php echo $imageInfo['dimensions']; ?></span><span><?php echo strtoupper(str_replace('image/', '', $imageInfo['type'])); ?></span>
            </div>
        </div>
        <div class="image-info">
            <h3 class="image-title ellipsis" title="<?php echo $title; ?>"><?php echo $title; ?></h3>
            <div class="comment-container-wrapper">
                <div class="comment-container">
                    <?php generateCommentBoxes($filename, $data, $user_ip); ?>
                    <div class="comment-placeholder">Comment</div>
                </div>
                <div class="comment-shadow"></div>
            </div>
        </div>
    </div>
<?php
    return ob_get_clean();
}

// Function to generate comment boxes
function generateCommentBoxes($filename, $data, $current_user_ip)
{
    $comments = [];
    foreach ($data as $ip => $userData) {
        if (isset($userData['comments'][$filename])) {
            $comments[] = [
                'ip' => $ip,
                'name' => htmlspecialchars($userData['name']),
                'comment' => htmlspecialchars($userData['comments'][$filename]),
                'timestamp' => $userData['comment_timestamps'][$filename] ?? 0
            ];
        }
    }

    // Sort comments by timestamp, oldest first
    usort($comments, function ($a, $b) {
        return $a['timestamp'] - $b['timestamp'];
    });

    foreach ($comments as $comment) {
        $isCurrentUser = $comment['ip'] === $current_user_ip;
        $userColor = generateColorFromIP($comment['ip']);
        echo "<div class='comment-box" . ($isCurrentUser ? " current-user" : "") . "' data-user-ip='{$comment['ip']}'>";
        echo "<span class='comment-user' data-user-ip='{$comment['ip']}' style='color: $userColor;'>{$comment['name']}:</span>";
        echo "<span class='comment'>{$comment['comment']}</span>";
        echo "</div>";
    }
}

// Function to generate a color from an IP address
function generateColorFromIP($ip)
{
    $hash = md5($ip);
    $color = substr($hash, 0, 6);

    // Ensure the color is not too dark
    $r = hexdec(substr($color, 0, 2));
    $g = hexdec(substr($color, 2, 2));
    $b = hexdec(substr($color, 4, 2));

    while ($r + $g + $b < 382) {  // 382 is arbitrary, adjust for desired brightness
        $r = min(255, $r + 30);
        $g = min(255, $g + 30);
        $b = min(255, $b + 30);
    }

    return sprintf("#%02x%02x%02x", $r, $g, $b);
}

// After loading the $data array, add this:
$userStarredImages = [];
foreach ($data as $ip => $userData) {
    if (!empty($userData['starred_images'])) {
        $userStarredImages[$ip] = $userData;
    }
}

// Add this after loading the images and before generating the HTML
$validImages = array_map('basename', $images);

foreach ($userStarredImages as $ip => $userData) {
    $userStarredImages[$ip]['starred_images'] = array_filter($userData['starred_images'], function ($image) use ($validImages) {
        return in_array($image, $validImages);
    });
}

// Modify the generateStarredFooter function
function generateStarredFooter($userStarredImages, $baseUrl, $relativeThumbsUrl, $validImages)
{
    $footer = '<div class="starred-footer">';
    $footer .= '<div class="starred-list">';
    foreach ($userStarredImages as $ip => $userData) {
        $footer .= '<div class="user-starred" data-ip="' . $ip . '">';
        $footer .= '<span class="user-name">' . htmlspecialchars($userData['name']) . '</span>';
        $footer .= '<div class="starred-thumbnails">';
        foreach ($userData['starred_images'] as $image) {
            if (in_array($image, $validImages)) {
                $thumbnailUrl = $baseUrl . $relativeThumbsUrl . '/' . $image;
                $fullImageUrl = $baseUrl . 'images/' . $image;
                $footer .= '<div class="starred-thumbnail" data-full-image="' . $fullImageUrl . '">';
                $footer .= '<img src="' . $thumbnailUrl . '" alt="' . $image . '">';
                $footer .= '</div>';
            }
        }
        $footer .= '</div>';
        $footer .= '</div>';
    }
    $footer .= '</div>';
    $footer .= '</div>';
    return $footer;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz4KPHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZlcnNpb249IjEuMSIgdmlld0JveD0iMCAwIDE1My4xNiAxNDQuMDkiPgogIDwhLS0gR2VuZXJhdG9yOiBBZG9iZSBJbGx1c3RyYXRvciAyOC43LjEsIFNWRyBFeHBvcnQgUGx1Zy1JbiAuIFNWRyBWZXJzaW9uOiAxLjIuMCBCdWlsZCAxNDIpICAtLT4KICA8Zz4KICAgIDxnIGlkPSJMYXllcl8xIj4KICAgICAgPHBhdGggZD0iTTE1My4xNiwwdjIxLjhIMjIuNnYxMjIuMjlIMFYwaDE1My4xNloiLz4KICAgICAgPHBhdGggZD0iTTQ1LjYsNDMuOGgxMDd2MjEuOGgtODQuNHY1Ni40aDYyLjZ2LTE4aC0zOC40di0yMmg2MC4ydjYxLjhINDUuNlY0My44WiIvPgogICAgPC9nPgogIDwvZz4KPC9zdmc+" />
    <title><?php echo $title; ?></title>
    <style>
        :root {
            --bg-color: #2c2c2c;
            --text-color: #e0e0e0;
            --card-bg: #3c3c3c;
            --starred-bg: #4a4a4a;
            --button-color: #fff;
            --star-color: #ffa768;
            --copy-button-bg: #009dff;
            --copy-button-hover: #81cfff;
            --scrollbar-bg: rgba(255, 255, 255, 0.1);
            --scrollbar-thumb: rgba(255, 255, 255, 0.3);
            --scrollbar-thumb-hover: rgba(255, 255, 255, 0.5);
            --footer-shadow: 0 -1.25rem 6.25rem rgba(0, 0, 0, 0.4);
            --footer-bg: rgba(44, 44, 44, 0.8);
            --footer-text: #e0e0e0;
            --footer-button: #fff;
            --footer-button-hover: #ffa768;
            --footer-border: rgba(255, 255, 255, 0.1);
        }

        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: var(--text-color);
            background-color: var(--bg-color);
            margin: 0;
            padding-top: 1.25rem;
        }

        h1 {
            text-align: center;
            margin-bottom: 1.25rem;
        }

        #page-title {
            cursor: pointer;
            color: var(--text-color);
            text-decoration: none;
        }

        #page-title:hover {
            color: var(--star-color);
        }

        .ellipsis {
            text-overflow: ellipsis;
            white-space: nowrap;
            overflow: hidden;
        }

        .gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(15.5rem, 1fr));
            gap: 1.25rem;
            padding: 1.25rem;
        }

        .image-container {
            background-color: var(--card-bg);
            border-radius: 0.5rem;
            overflow: hidden;
            transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease, border 0.2s ease;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            border: 0.25rem solid transparent;
            position: relative;
        }

        .image-container.starred {
            background-color: var(--starred-bg);
            box-shadow: 0 0.375rem 0.75rem rgba(0, 0, 0, 0.5);
            transform: scale(1.02);
        }

        .image-wrapper {
            position: relative;
            overflow: hidden;
        }

        .image-wrapper::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(0, 0, 0, 0.5) 0%, rgba(0, 0, 0, 0) 50%);
            opacity: 0;
            transition: opacity 0.2s ease;
            pointer-events: none;
            z-index: 1;
        }

        .image-container.starred .image-wrapper::before {
            opacity: 1;
        }

        .image-container img {
            width: 100%;
            height: auto;
            display: block;
            cursor: zoom-in;
            transition: transform 0.2s ease;
        }

        .image-container:hover img {
            transform: scale(1.05);
        }

        .star-button {
            position: absolute;
            top: 0.25rem;
            left: 0.25rem;
            font-size: 1.5rem;
            display: flex;
            justify-content: center;
            align-items: center;
            border: none;
            background: transparent;
            color: white;
            opacity: 0;
        }

        .star-button,
        .download-button {
            transition: opacity 0.2s ease, color 0.2s ease, transform 0.2s ease, background-color 0.2s ease;
        }

        .modal-content .star-button {
            position: relative;
            top: 0;
            left: 0;
            opacity: 1;
        }

        .image-container:hover .star-button,
        .image-container.starred .star-button {
            opacity: 1;
        }

        .image-container.starred .star-button {
            color: #fff;
            background: transparent;
            text-shadow:
                /* White glow */
                0 0 7px #fff,
                /* 0 0 10px #fff, */
                0 0 21px #fff,
                /* Green glow */
                0 0 42px var(--star-color),
                0 0 82px var(--star-color),
                0 0 92px var(--star-color),
                0 0 102px var(--star-color),
                0 0 151px var(--star-color);
            overflow: visible;
        }

        .image-container:hover .star-button {
            background-color: rgba(0, 0, 0, 0.5);
        }

        .image-container.starred:hover .star-button,
        .image-container:hover .star-button.starred {
            color: #fff;
            text-shadow: 0 0 15px var(--star-color);
        }

        .hover-buttons {
            position: absolute;
            top: 0.25rem;
            right: 0.25rem;
            display: flex;
            justify-content: flex-end;
            align-items: center;
            opacity: 0;
            transition: opacity 0.2s ease;
        }

        .image-container:hover .hover-buttons {
            opacity: 1;
        }

        .star-button {
            width: auto;
            height: auto;
            padding: 0.5rem;
        }

        .button:hover {
            transform: scale(1.1);
        }

        .image-info {
            padding: 0.25rem;
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }

        .image-title {
            margin: 0 0 0.5rem 0;
            font-size: 1rem;
            font-weight: bold;
            color: var(--text-color);
            text-align: center;
        }

        .star-button.starred,
        .starred .star-button {
            color: #fff;
            background: transparent;
            text-shadow:
                /* White glow */
                0 0 7px #fff,
                /* 0 0 10px #fff, */
                0 0 21px #fff,
                /* Green glow */
                0 0 42px var(--star-color),
                0 0 82px var(--star-color),
                0 0 92px var(--star-color),
                0 0 102px var(--star-color),
                0 0 151px var(--star-color);
            overflow: visible;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(44, 44, 44, 0.8);
            backdrop-filter: blur(1.25rem);
        }

        .modal-content {
            display: grid;
            grid-template-columns: 3rem 1fr 3rem;
            grid-template-rows: auto auto 1fr auto;
            gap: 0.5rem;
            width: 100%;
            height: 100%;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            padding: 1.25rem;
            border-radius: 0.5rem;
        }

        .button {
            display: flex;
            justify-content: center;
            align-items: center;
            width: 1.5em;
            height: 1.5em;
            overflow: hidden;
            border: none;
            background-color: rgba(0, 0, 0, 0.5);
            border-radius: 0.25rem;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--button-color);
        }

        .button {
            display: flex;
            justify-content: center;
            align-items: center;
            width: 1.5em;
            height: 1.5em;
            overflow: hidden;
            border: none;
            /* background-color: #333; */
            font-size: 2rem;
            cursor: pointer;
            align-self: center;
            justify-self: center;
        }

        .close {
            grid-column: 3;
            grid-row: 1;
            justify-self: end;
        }

        .prev {
            grid-column: 1;
            grid-row: 1 / 4;
            align-self: center;
        }

        .modal-image-container {
            grid-column: 2;
            grid-row: 2;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
        }

        #modalImage {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        #modalTitle {
            grid-column: 2;
            grid-row: 1;
            text-align: center;
            margin: 0;
        }

        .next {
            grid-column: 3;
            grid-row: 1 / 4;
            align-self: center;
        }

        #modalStarButton {
            grid-column: 1;
            grid-row: 3;
            justify-self: start;
            align-self: end;
        }

        #modalDownloadButton {
            grid-column: 3;
            grid-row: 3;
            justify-self: end;
            align-self: end;
        }

        .comment-container {
            grid-column: 2 / -2;
            grid-row: 3;
            overflow-y: auto;
            max-height: 30vh;
        }

        .modal-content .comment-container {
            min-width: 15rem;
            padding: 1rem;
            margin: 1rem;
            background-color: rgba(0, 0, 0, 0.25);
        }

        .close:hover,
        .close:focus,
        .prev:hover,
        .prev:focus,
        .next:hover,
        .next:focus {
            color: #bbb;
            text-decoration: none;
        }

        #starred-footers-container {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            display: flex;
            flex-direction: column;
            max-height: 50vh;
            overflow-y: hidden;
            box-shadow: var(--footer-shadow);
            background-color: var(--footer-bg);
            color: var(--footer-text);
            backdrop-filter: blur(1.25rem);
            -webkit-backdrop-filter: blur(1.25rem);
            z-index: 1000;
        }

        .starred-footer {
            display: grid;
            grid-template-columns: auto 5rem 1fr auto auto;
            grid-template-areas: "minimize user list download copy";
            align-items: center;
            padding: 0.5rem;
            padding-bottom: 0;
            gap: 0.5rem;
        }

        .minimize-button {
            grid-area: minimize;
            background: none;
            border: none;
            color: var(--footer-button);
            cursor: pointer;
            font-size: 0.8rem;
            padding: 0.3125rem;
            transition: transform 0.2s ease;
        }

        .user-name {
            grid-area: user;
            flex-shrink: 0;
            min-width: 5rem;
        }

        .starred-list-container {
            grid-area: list;
            overflow-x: auto;
        }

        .footer-buttons {
            grid-area: download / download / copy / copy;
            display: flex;
            gap: 0.5rem;
        }

        .download-all-button {
            grid-area: download;
        }

        .copy-names-button {
            grid-area: copy;
        }

        .starred-footer.minimized {
            grid-template-columns: auto 1fr;
            grid-template-areas: "minimize user";
        }

        .starred-footer.minimized .starred-list-container,
        .starred-footer.minimized .footer-buttons {
            display: none;
        }

        .starred-list {
            display: inline-flex;
            gap: 0.5rem;
        }

        .starred-thumbnail {
            position: relative;
            width: clamp(2.5rem, 8vw, 5rem);
            height: clamp(2.5rem, 8vw, 5rem);
            cursor: pointer;
        }

        .starred-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 0.5rem;
        }

        .starred-thumbnail .thumbnail-buttons {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            display: grid;
            grid-template-columns: 1fr 1fr;
            grid-template-rows: 1fr;
            height: 50%;
            justify-items: center;
            align-items: center;
            opacity: 0;
            transition: opacity 0.2s ease;
            gap: 0.25rem;
            margin: 0.25rem;
        }

        .starred-thumbnail:hover .thumbnail-buttons {
            opacity: 1;
        }

        .thumbnail-buttons button {
            border: none;
            color: #fff;
            font-size: 1rem;
            cursor: pointer;
            padding: 0.125rem;
            margin: 0 0.125rem;
            background-color: rgba(0, 0, 0, 0.5);
            width: 100%;
            height: 100%;
            border-radius: 0.25rem;
            backdrop-filter: blur(0.3125rem);
        }

        #toast {
            visibility: hidden;
            min-width: 15.5rem;
            margin-left: -7.8125rem;
            background-color: #333;
            color: #fff;
            text-align: center;
            border-radius: 0.125rem;
            padding: 1rem;
            position: fixed;
            z-index: 2001;
            left: 50%;
            bottom: 1.875rem;
            font-size: 1.0625rem;
        }

        #toast.show {
            visibility: visible;
            -webkit-animation: fadein 0.5s, fadeout 0.5s 2.5s;
            animation: fadein 0.5s, fadeout 0.5s 2.5s;
        }

        @-webkit-keyframes fadein {
            from {
                bottom: 0;
                opacity: 0;
            }

            to {
                bottom: 1.875rem;
                opacity: 1;
            }
        }

        @keyframes fadein {
            from {
                bottom: 0;
                opacity: 0;
            }

            to {
                bottom: 1.875rem;
                opacity: 1;
            }
        }

        @-webkit-keyframes fadeout {
            from {
                bottom: 1.875rem;
                opacity: 1;
            }

            to {
                bottom: 0;
                opacity: 0;
            }
        }

        @keyframes fadeout {
            from {
                bottom: 1.875rem;
                opacity: 1;
            }

            to {
                bottom: 0;
                opacity: 0;
            }
        }

        #nameInput {
            width: 100%;
            padding: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .footer-buttons {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-left: 0.5rem;
        }

        .footer-button {
            background: none;
            border: none;
            color: var(--footer-button);
            cursor: pointer;
            transition: color 0.2s ease, transform 0.2s ease;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-left: 0.5rem;
        }

        .footer-button:hover {
            transform: scale(1.2);
        }

        .footer-button svg {
            width: 2rem;
            height: 2rem;
        }

        .user-name-input {
            background-color: var(--bg-color);
            color: var(--text-color);
            border: 1px solid var(--text-color);
            padding: 0.3125rem;
            border-radius: 0.25rem;
            font-size: 1rem;
            width: 12rem;
        }

        .user-name.editing {
            display: none;
        }

        /* Scrollbar styles */
        ::-webkit-scrollbar {
            width: 0.5rem;
            height: 0.5rem;
        }

        ::-webkit-scrollbar-track {
            background: var(--scrollbar-bg);
            border-radius: 0.25rem;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--scrollbar-thumb);
            border-radius: 0.25rem;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--scrollbar-thumb-hover);
        }

        /* For Firefox */
        * {
            scrollbar-width: thin;
            scrollbar-color: var(--scrollbar-thumb) var(--scrollbar-bg);
        }

        /* Make sure the body has a scrollbar */
        body {
            overflow-y: scroll;
        }

        .comment-container {
            border-radius: 0.5rem;
            font-size: 0.8rem;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .comment-box {
            padding: 0.375rem 0.5rem;
            background-color: #1f1f1f;
            border-radius: 0.375rem;
            align-items: baseline;
        }

        .comment-user {
            font-weight: bold;
            margin-right: 0.3125rem;
            white-space: nowrap;
            color: var(--star-color);
        }

        .comment-placeholder {
            color: #808080;
            font-style: italic;
            cursor: pointer;
            padding: 0.375rem 0.5rem;
            border: 1px dashed #808080;
            border-radius: 0.5rem;
        }

        textarea {
            width: 100%;
            padding: 0;
            border-radius: 0.25rem;
            resize: vertical;
            min-height: 3.75rem;
            background-color: #2a2a2a;
            color: #d0d0d0;
        }

        .current-user .comment-user {
            color: #81cfff;
        }

        .comment-box,
        .comment-placeholder {
            transition: background-color 0.2s ease;
        }

        .minimize-button:hover {
            transform: scale(1.2);
        }

        .starred-footer.minimized .starred-thumbnail {
            display: none;
        }

        .starred-footer.minimized .minimize-button {
            transform: rotate(90deg);
        }

        .starred-footer.minimized {
            padding: 0.3125rem 0.5rem;
        }

        .star-button:focus {
            outline: none;
        }

        #top-bar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 3.75rem;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 0.5rem;
            background-color: rgba(44, 44, 44, 0.8);
            backdrop-filter: blur(1.25rem);
            -webkit-backdrop-filter: blur(1.25rem);
            z-index: 1000;
            box-shadow: 0 0 0 rgba(0, 0, 0, 0);
        }

        #top-bar.scrolled {
            box-shadow: 0 1.25rem 0.5rem rgba(0, 0, 0, 0.4);
        }

        #logo-container {
            position: absolute;
            left: 0.5rem;
            top: 50%;
            transform: translateY(-50%);
            width: 2.5rem;
            height: 2.5rem;
            cursor: pointer;
            display: block;
            margin-left: 0.5rem;
        }

        #logo-container svg {
            fill: var(--text-color);
            transition: fill 0.2s ease;
            width: 2.5rem;
            height: 2.5rem;
        }

        #logo-container:hover svg {
            fill: var(--star-color);
        }

        #page-title {
            text-align: center;
            margin: 0;
            font-size: 1.5rem;
            color: var(--text-color);
            text-decoration: none;
        }

        #user-name-container {
            position: absolute;
            right: 3rem;
            top: 50%;
            transform: translateY(-50%);
            display: flex;
            align-items: center;
            justify-content: flex-end;
            color: #fff;
            cursor: pointer;
        }

        .edit-icon {
            width: 1.5rem;
            height: 1.5rem;
            margin-left: 0.3125rem;
            cursor: pointer;
            vertical-align: middle;
        }

        .edit-icon .st0 {
            fill: none;
            stroke: var(--text-color);
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        #top-user-name {
            cursor: pointer;
            transition: background-color 0.2s ease;
            font-size: 0.9rem;
            margin-right: 0.5rem;
            text-align: right;
        }

        #user-name-container:hover {
            color: var(--star-color);
        }

        .user-name-input {
            color: #fff;
            border: 1px solid #555;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.9rem;
            width: auto;
            min-width: 9.375rem;
            background-color: rgba(0, 0, 0, 0.1);
        }

        .user-name-input:focus {
            outline: none;
            border-color: #777;
        }

        @media screen and (max-width: 675px) {

            #top-bar {
                justify-content: flex-start;
                /* Align title to the left */
                padding-left: 3.5rem;
                /* Make space for the logo */
            }

            #page-title {
                font-size: 1.2rem;
                /* Reduce font size for smaller screens */
                max-width: calc(100% - 7rem);
                /* Limit width to prevent overlap */
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                margin-left: 0.5rem;
            }
        }

        body {
            padding-top: 3.75rem;
        }

        .starred-footer.minimized .starred-list-container,
        .starred-footer.minimized .footer-buttons {
            display: none;
        }

        .starred-footer.minimized {
            grid-template-columns: auto 1fr;
            grid-template-areas: "minimize user";
        }

        .image-container .comment-container-wrapper {
            position: relative;
            max-height: 9.375rem;
            /* margin-bottom: 0.5rem; */
            border-radius: 0.5rem;
            overflow: hidden;
        }

        .image-container .comment-container {
            max-height: 8.125rem;
            overflow-y: auto;
            border-radius: 0.5rem;
            font-size: 0.8rem;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            box-sizing: border-box;
        }

        .comment-shadow {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 1.25rem;
            background: linear-gradient(to bottom, transparent, rgba(44, 44, 44, 0.9));
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.2s ease;
            border-bottom-left-radius: 0.5rem;
            border-bottom-right-radius: 0.5rem;
        }

        /* Add this new rule for light mode */
        body.light-mode .comment-shadow {
            background: linear-gradient(to bottom, transparent, rgba(0, 0, 0, 0.3));
        }

        .image-container .comment-container-wrapper.scrollable .comment-shadow {
            opacity: 1;
        }

        /* Modify this rule to limit opacity in light mode */
        body.light-mode .image-container .comment-container-wrapper.scrollable .comment-shadow {
            opacity: 0.5;
        }

        .image-container .comment-box {
            overflow: visible;
        }

        .image-container .comment-container::-webkit-scrollbar {
            width: 0.375rem;
        }

        .image-container .comment-container::-webkit-scrollbar-thumb {
            background-color: rgba(255, 255, 255, 0.2);
            border-radius: 0.1875rem;
        }

        .image-container .comment-container::-webkit-scrollbar-track {
            background-color: transparent;
        }

        @media screen and (min-width: 1820px) {
            .modal-content {
                grid-template-columns: 3rem auto 1fr 3rem;
                grid-template-rows: auto 1fr auto;
            }

            #modalTitle {
                grid-column: 2 / 4;
            }

            .close {
                grid-column: 4;
            }

            .modal-image-container {
                grid-column: 2;
                grid-row: 2;
            }

            .prev {
                grid-column: 1;
                grid-row: 2;
            }

            .next {
                grid-column: 4;
                grid-row: 2;
            }

            #modalStarButton {
                grid-column: 1;
                grid-row: 3;
                justify-self: start;
            }

            #modalDownloadButton {
                grid-column: 4;
                grid-row: 3;
                justify-self: end;
            }

            .comment-container {
                grid-column: 3;
                grid-row: 2 / 4;
                max-height: none;
            }
        }

        /* Adjust the modal image size for smaller screens */
        @media screen and (max-width: 1819px) {
            #modalImage {
                max-height: 60vh;
                width: auto;
                margin: 0 auto;
            }
        }

        #top-bar {
            background-color: rgba(44, 44, 44, 0.8);
            -webkit-backdrop-filter: blur(1.25rem);
            backdrop-filter: blur(1.25rem);
        }

        #starred-footers-container {
            background-color: rgba(44, 44, 44, 0.8);
            -webkit-backdrop-filter: blur(1.25rem);
            backdrop-filter: blur(1.25rem);
        }

        .modal {
            background-color: rgba(44, 44, 44, 0.8);
            -webkit-backdrop-filter: blur(1.25rem);
            backdrop-filter: blur(1.25rem);
            z-index: 2000;
        }

        .image-wrapper {
            position: relative;
            overflow: hidden;
        }

        .image-wrapper::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg,
                    rgba(0, 0, 0, 0.8) 0%,
                    rgba(0, 0, 0, 0.7) 5%,
                    rgba(0, 0, 0, 0.6) 10%,
                    rgba(0, 0, 0, 0.5) 15%,
                    rgba(0, 0, 0, 0.4) 20%,
                    rgba(0, 0, 0, 0.3) 25%,
                    rgba(0, 0, 0, 0.2) 30%,
                    rgba(0, 0, 0, 0.1) 35%,
                    rgba(0, 0, 0, 0) 40%);
            opacity: 0;
            transition: opacity 0.2s ease;
            pointer-events: none;
            z-index: 1;
        }

        .image-container.starred .image-wrapper::before {
            opacity: 1;
        }

        .star-button {
            position: absolute;
            top: 0.25rem;
            left: 0.25rem;
            z-index: 2;
        }

        #other-users-toggle {
            display: flex;
            align-items: center;
            padding: 0.5rem;
            cursor: pointer;
            background-color: rgba(0, 0, 0, 0.2);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        #other-users-toggle .minimize-button {
            margin-right: 0.5rem;
        }

        #other-users-toggle .user-count-badge {
            background-color: #646464;
            color: #fff;
            border-radius: 50%;
            padding: 0 0.4rem;
            font-size: 0.8rem;
            margin-left: 0.5rem;
            text-align: center;
            display: inline-block;
            font-weight: bold;
            border: 2px solid #a3a3a3;
            ;
        }

        #other-users-container {
            background-color: rgba(0, 0, 0, 0.1);
        }

        #other-users-container .starred-footer {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .starred-thumbnail {
            position: relative;
            width: clamp(2.5rem, 8vw, 5rem);
            height: clamp(2.5rem, 8vw, 5rem);
            cursor: pointer;
        }

        .starred-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 0.5rem;
        }

        .starred-thumbnail .thumbnail-buttons {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            display: grid;
            grid-template-columns: 1fr 1fr;
            grid-template-rows: 1fr;
            height: 50%;
            justify-items: center;
            align-items: center;
            opacity: 0;
            transition: opacity 0.2s ease;
            gap: 0.25rem;
            margin: 0.25rem;
        }

        .starred-thumbnail:hover .thumbnail-buttons {
            opacity: 1;
        }

        @media screen and (max-width: 1000px) {
            .starred-footer[data-user-ip="<?php echo $user_ip; ?>"] .starred-thumbnail .thumbnail-buttons {
                display: none;
            }

            .starred-footer[data-user-ip="<?php echo $user_ip; ?>"] .starred-thumbnail:hover .thumbnail-buttons {
                opacity: 0;
            }
        }

        .image-info-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: linear-gradient(to top, rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0) 25%, rgba(0, 0, 0, 0));
            color: white;
            display: flex;
            padding-right: 0.5rem;
            padding-left: 0.5rem;
            justify-content: space-between;
            align-items: end;
            gap: 1rem;
            opacity: 0;
            transition: opacity 0.2s ease;
            pointer-events: none;
        }

        .image-wrapper:hover .image-info-overlay {
            opacity: 1;
        }

        .image-info-overlay p {
            margin: 5px 0;
            font-size: 14px;
        }

        /* Theme toggle button styles */
        #theme-toggle-container {
            position: absolute;
            right: 0.5rem;
        }

        #theme-toggle {
            background: none;
            border: none;
            color: var(--text-color);
            cursor: pointer;
            transition: color 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
        }

        #theme-toggle:hover {
            color: var(--star-color);
        }

        /* Light mode styles */
        body.light-mode {
            --bg-color: #bbbbbb;
            --text-color: #333;
            --card-bg: #eaeaea;
            --starred-bg: #fff;
            --button-color: #333;
            --star-color: #ff9900;
            --copy-button-bg: #007bff;
            --copy-button-hover: #0056b3;
            --scrollbar-bg: rgba(0, 0, 0, 0.1);
            --scrollbar-thumb: rgba(0, 0, 0, 0.3);
            --scrollbar-thumb-hover: rgba(0, 0, 0, 0.5);
            --footer-shadow: 0 -1.25rem 6.25rem rgba(0, 0, 0, 0.1);

            /* New light mode variables */
            --top-bar-bg: rgba(187, 187, 187, 0.8);
            --footer-bg: rgba(187, 187, 187, 0.8);
            --footer-text: #333;
            --footer-button: #555;
            --footer-button-hover: #777;
            --footer-border: rgba(0, 0, 0, 0.1);
            --modal-bg: #f5f5f5;
            --modal-header-bg: #e0e0e0;
            --input-bg: #fff;
            --input-border: #ccc;
        }

        /* Apply light mode styles */
        body.light-mode #top-bar,
        body.light-mode #footer {
            background-color: var(--top-bar-bg);
        }

        body.light-mode .modal-content {
            background-color: var(--modal-bg);
        }

        body.light-mode .modal-header {
            background-color: var(--modal-header-bg);
        }

        body.light-mode input[type="text"],
        body.light-mode textarea {
            background-color: var(--input-bg);
            border-color: var(--input-border);
            color: var(--text-color);
        }

        body.light-mode .comment-box {
            background-color: #bbbbbb;
        }

        body.light-mode #footer-content {
            background-color: var(--footer-bg);
        }

        /* Adjust other elements as needed */
        body.light-mode .button {
            background-color: var(--button-color);
            color: var(--bg-color);
        }

        body.light-mode .button:hover {
            background-color: #555;
        }

        body.light-mode #theme-toggle {
            color: var(--text-color);
        }

        body.light-mode #theme-toggle:hover {
            color: var(--star-color);
        }
    </style>
    <script>
        let initialStarredImages = <?php echo json_encode($data[$user_ip]['starred_images']); ?>;
        const currentUserIp = '<?php echo $user_ip; ?>';
        let lastModified = 0;

        let baseUrl = '<?php echo $baseUrl; ?>';
        let relativeThumbsUrl = '<?php echo $relativeThumbsUrl; ?>';
        let subDir = '<?php echo $subDir; ?>';

        // Add this near the top of your script, outside any functions
        let footerMinimizedStates = {};

        // Add this near the top of your script, outside any functions
        let currentFullImagePath = '';

        let userStarredImages = <?php echo json_encode($userStarredImages); ?>;


        let modal, modalImg, modalTitle, closeBtn, prevBtn, nextBtn, modalStarButton, modalDownloadButton;
        let currentImageIndex = -1;
        let images = [];

        const DOWNLOAD_SVG = `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-download"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>`;

        function initializeModalElements() {
            modal = document.getElementById('imageModal');
            if (modal) {
                modalImg = document.getElementById('modalImage');
                modalTitle = document.getElementById('modalTitle');
                closeBtn = modal.querySelector('.close');
                prevBtn = modal.querySelector('.prev');
                nextBtn = modal.querySelector('.next');
                modalStarButton = document.getElementById('modalStarButton');
                modalDownloadButton = document.getElementById('modalDownloadButton');

                // Add this new code
                const pageTitle = document.getElementById('page-title');
                if (pageTitle) {
                    pageTitle.addEventListener('click', function(e) {
                        e.preventDefault();
                        window.location.href = window.location.pathname;
                    });
                }
            } else {
                console.error('Modal element not found in the DOM');
            }
        }


        function updateModalComments(currentImage) {
            const commentContainer = modal.querySelector('.comment-container');
            commentContainer.innerHTML = ''; // Clear existing comments

            const imageComments = currentImage.querySelectorAll('.comment-box');
            imageComments.forEach(comment => {
                const clonedComment = comment.cloneNode(true);
                commentContainer.appendChild(clonedComment);

                if (clonedComment.classList.contains('current-user')) {
                    clonedComment.addEventListener('click', () => editModalComment(currentImage.dataset.image));
                }
            });

            if (!commentContainer.querySelector('.comment-box.current-user')) {
                const commentPlaceholder = document.createElement('div');
                commentPlaceholder.className = 'comment-placeholder';
                commentPlaceholder.textContent = 'Comment';
                commentPlaceholder.addEventListener('click', () => editModalComment(currentImage.dataset.image));
                commentContainer.appendChild(commentPlaceholder);
            }
        }

        function updateStarButton(button, isStarred) {
            button.classList.toggle('starred', isStarred);
            button.textContent = isStarred ? '★' : '★';
            button.title = isStarred ? 'Unstar' : 'Star';
        }

        function showToast(message) {
            const toast = document.getElementById("toast");
            toast.textContent = message;
            toast.className = "show";
            setTimeout(() => {
                toast.className = toast.className.replace("show", "");
            }, 3000);
        }

        function toggleStar(imageContainerOrName) {
            console.debug("toggleStar called with:", imageContainerOrName);

            let imageName, imageContainer, starButton;

            if (typeof imageContainerOrName === 'string') {
                imageName = imageContainerOrName;
                imageContainer = document.querySelector(`.image-container[data-image="${imageName}"]`);
                starButton = imageContainer ? imageContainer.querySelector('.star-button') : null;
                console.debug("String input - imageName:", imageName);
            } else {
                imageContainer = imageContainerOrName;
                imageName = imageContainer.dataset.image;
                starButton = imageContainer.querySelector('.star-button');
                console.debug("Container input - imageName:", imageName);
            }

            console.debug("Current initialStarredImages:", initialStarredImages);
            const isCurrentlyStarred = initialStarredImages.includes(imageName);
            console.debug("Is currently starred:", isCurrentlyStarred);
            const newStarredState = !isCurrentlyStarred;
            console.debug("New starred state:", newStarredState);

            updateStarredState(imageName, newStarredState);
            if (starButton) {
                updateStarButton(starButton, newStarredState);
            }
            if (imageContainer) {
                imageContainer.classList.toggle('starred', newStarredState);
            }
            if (modalStarButton && currentFullImagePath === imageName) {
                updateStarButton(modalStarButton, newStarredState);
            }
            updateStarredFooters();

            // If the modal is open and showing this image, update it
            if (modal.style.display === 'block' && currentFullImagePath === imageName) {
                updateModalContent();
            }

            console.debug("Sending fetch request for:", imageName);
            fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `toggle_star=${encodeURIComponent(imageName)}`
                })
                .then(response => response.json())
                .then(data => {
                    console.debug("Server response:", data);
                    if (!data.success) throw new Error('Server indicated failure');
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Failed to update star status. Please try again.');
                    updateStarredState(imageName, isCurrentlyStarred);
                });
        }

        function updateStarredState(imageName, isStarred) {
            const index = initialStarredImages.indexOf(imageName);
            if (isStarred && index === -1) {
                initialStarredImages.push(imageName);
            } else if (!isStarred && index > -1) {
                initialStarredImages.splice(index, 1);
            }
            updateStarredFooters();
        }

        // Add this variable at the top of your script, with your other state variables
        let isOtherUsersMinimized = true;

        // Modify the toggleOtherUsers function
        function toggleOtherUsers() {
            const otherUsersToggle = document.getElementById('other-users-toggle');
            const otherUsersContainer = document.getElementById('other-users-container');
            isOtherUsersMinimized = !isOtherUsersMinimized;

            otherUsersToggle.classList.toggle('minimized', isOtherUsersMinimized);
            otherUsersContainer.style.display = isOtherUsersMinimized ? 'none' : 'block';

            const toggleButton = otherUsersToggle.querySelector('.minimize-button');
            toggleButton.textContent = isOtherUsersMinimized ? '▲' : '▼';

            updateFooterSpacerHeight();
        }

        // Modify the updateUserFooter function
        function updateUserFooter(userIp, starredImages, isCurrentUser) {
            const container = isCurrentUser ?
                document.getElementById('starred-footers-container') :
                document.getElementById('other-users-container') || document.getElementById('starred-footers-container');

            if (!container) return;

            let footer = container.querySelector(`.starred-footer[data-user-ip="${userIp}"]`);
            const wasMinimized = footerMinimizedStates[userIp] !== undefined ? footerMinimizedStates[userIp] : !isCurrentUser;

            // Filter out invalid images
            const validStarredImages = starredImages.filter(image => validImages.includes(image));

            if (validStarredImages.length > 0) {
                if (!footer) {
                    footer = createStarredFooter(userIp, validStarredImages, isCurrentUser);
                    container.appendChild(footer);
                } else {
                    addFooterEventListeners(footer);
                    const starredList = footer.querySelector('.starred-list');
                    starredList.innerHTML = '';
                    validStarredImages.forEach(imageName => {
                        const thumbnail = createThumbnail(imageName, isCurrentUser);
                        starredList.appendChild(thumbnail);
                    });
                }

                footer.classList.toggle('minimized', wasMinimized);
                const minimizeButton = footer.querySelector('.minimize-button');
                if (minimizeButton) {
                    minimizeButton.textContent = wasMinimized ? '▲' : '▼';
                }

                footer.style.display = 'grid';
            } else if (footer) {
                footer.remove();
            }

            footerMinimizedStates[userIp] = wasMinimized;
        }

        function updateCommentUI(container, newComments, isModal = false) {
            let commentContainerWrapper = container.querySelector('.comment-container-wrapper');
            if (!commentContainerWrapper) {
                commentContainerWrapper = document.createElement('div');
                commentContainerWrapper.className = 'comment-container-wrapper';
                container.querySelector('.image-info').appendChild(commentContainerWrapper);
            }

            let commentContainer = commentContainerWrapper.querySelector('.comment-container');
            if (!commentContainer) {
                commentContainer = document.createElement('div');
                commentContainer.className = 'comment-container';
                commentContainerWrapper.appendChild(commentContainer);
            }

            commentContainer.innerHTML = '';

            let comments = Object.entries(newComments).map(([userIp, userData]) => ({
                userIp,
                ...userData,
                timestamp: userData.timestamp || 0
            }));

            // Sort comments by timestamp, oldest first
            comments.sort((a, b) => a.timestamp - b.timestamp);

            comments.forEach(({
                userIp,
                name,
                comment
            }) => {
                if (comment && comment.trim() !== '') {
                    const commentBox = createCommentBox(userIp, {
                        name,
                        comment
                    });
                    commentContainer.appendChild(commentBox);
                }
            });

            if (!commentContainer.querySelector('.comment-box.current-user')) {
                const commentPlaceholder = createCommentPlaceholder(container, isModal);
                commentContainer.appendChild(commentPlaceholder);
            }

            // Add shadow element if it doesn't exist
            let shadowElement = commentContainerWrapper.querySelector('.comment-shadow');
            if (!shadowElement) {
                shadowElement = document.createElement('div');
                shadowElement.className = 'comment-shadow';
                commentContainerWrapper.appendChild(shadowElement);
            }

            // Check if the comment container is scrollable
            if (commentContainer.scrollHeight > commentContainer.clientHeight) {
                commentContainerWrapper.classList.add('scrollable');
            } else {
                commentContainerWrapper.classList.remove('scrollable');
            }

            // Add scroll event listener to show/hide shadow based on scroll position
            commentContainer.addEventListener('scroll', function() {
                if (this.scrollHeight > this.clientHeight) {
                    const scrollPercentage = this.scrollTop / (this.scrollHeight - this.clientHeight);
                    shadowElement.style.opacity = scrollPercentage < 0.95 ? '1' : '0';
                }
            });

            // Trigger initial scroll event to set correct shadow state
            commentContainer.dispatchEvent(new Event('scroll'));
        }

        function createCommentBox(userIp, userData) {
            const commentBox = document.createElement('div');
            commentBox.className = 'comment-box';
            commentBox.dataset.userIp = userIp;
            const userColor = generateColorFromIP(userIp);
            commentBox.innerHTML = `
            <span class="comment-user" style="color: ${userColor};" data-user-ip="${userIp}">${userData.name || userIp}:</span>
            <span class="comment">${userData.comment}</span>
        `;

            if (userIp === currentUserIp) {
                commentBox.classList.add('current-user');
                commentBox.addEventListener('click', function() {
                    editComment(commentBox.closest('.image-container'));
                });
            }

            return commentBox;
        }

        function generateColorFromIP(ip) {
            // Simple hash function to replace md5
            function simpleHash(str) {
                let hash = 0;
                for (let i = 0; i < str.length; i++) {
                    const char = str.charCodeAt(i);
                    hash = ((hash << 5) - hash) + char;
                    hash = hash & hash; // Convert to 32-bit integer
                }
                return Math.abs(hash).toString(16).padStart(6, '0');
            }

            const hash = simpleHash(ip);
            let color = hash.substr(0, 6);

            // Convert to HSL for easier manipulation
            let r = parseInt(color.substr(0, 2), 16);
            let g = parseInt(color.substr(2, 2), 16);
            let b = parseInt(color.substr(4, 2), 16);

            // Convert RGB to HSL
            r /= 255;
            g /= 255;
            b /= 255;
            const max = Math.max(r, g, b),
                min = Math.min(r, g, b);
            let h, s, l = (max + min) / 2;

            if (max === min) {
                h = s = 0; // achromatic
            } else {
                const d = max - min;
                s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
                switch (max) {
                    case r:
                        h = (g - b) / d + (g < b ? 6 : 0);
                        break;
                    case g:
                        h = (b - r) / d + 2;
                        break;
                    case b:
                        h = (r - g) / d + 4;
                        break;
                }
                h /= 6;
            }

            const isLightMode = document.body.classList.contains('light-mode');
            if (isLightMode) {
                // For light mode, reduce lightness to make colors darker
                l = Math.min(l * 100, 30); // Cap lightness at 50%
            } else {
                // For dark mode, keep the existing logic
                l = Math.max(l * 100, 70); // Increase lightness
            }

            s = Math.min(s * 100, 60); // Reduce saturation (keep this the same for both modes)

            // Convert back to RGB
            const c = (1 - Math.abs(2 * l / 100 - 1)) * s / 100;
            const x = c * (1 - Math.abs((h * 6) % 2 - 1));
            const m = l / 100 - c / 2;
            let [r1, g1, b1] = [0, 0, 0];

            if (0 <= h && h < 1 / 6) {
                [r1, g1, b1] = [c, x, 0];
            } else if (1 / 6 <= h && h < 2 / 6) {
                [r1, g1, b1] = [x, c, 0];
            } else if (2 / 6 <= h && h < 3 / 6) {
                [r1, g1, b1] = [0, c, x];
            } else if (3 / 6 <= h && h < 4 / 6) {
                [r1, g1, b1] = [0, x, c];
            } else if (4 / 6 <= h && h < 5 / 6) {
                [r1, g1, b1] = [x, 0, c];
            } else {
                [r1, g1, b1] = [c, 0, x];
            }

            r = Math.round((r1 + m) * 255);
            g = Math.round((g1 + m) * 255);
            b = Math.round((b1 + m) * 255);

            return `#${r.toString(16).padStart(2, '0')}${g.toString(16).padStart(2, '0')}${b.toString(16).padStart(2, '0')}`;
        }

        function setTopUserNameColor() {
            const topUserName = document.getElementById('top-user-name');
            if (topUserName) {
                const userIp = topUserName.dataset.userIp;
                const userColor = generateColorFromIP(userIp);
                topUserName.style.color = userColor;
            }
        }

        // Add this to your theme toggle function
        function toggleTheme() {
            document.body.classList.toggle('light-mode');
            // ... other theme toggle logic ...
            setTopUserNameColor(); // Re-apply the color after theme change
            updateAllUserColors(); // Update colors for all user names
        }

        // Add this new function to update all user name colors
        function updateAllUserColors() {
            const userNames = document.querySelectorAll('.comment-user');
            userNames.forEach(userName => {
                const userIp = userName.dataset.userIp;
                const userColor = generateColorFromIP(userIp);
                userName.style.color = userColor;
            });
        }

        function createCommentPlaceholder(container, isModal) {
            const commentPlaceholder = document.createElement('div');
            commentPlaceholder.className = 'comment-placeholder';
            commentPlaceholder.textContent = 'Comment';
            commentPlaceholder.addEventListener('click', () => editComment(container, isModal));
            return commentPlaceholder;
        }

        function editComment(container, isModal = false) {
            const commentContainer = container.querySelector('.comment-container');
            if (!commentContainer) return;

            const currentUserComment = commentContainer.querySelector('.comment-box.current-user');
            const commentPlaceholder = commentContainer.querySelector('.comment-placeholder');
            let currentComment = '';

            if (currentUserComment) {
                const commentSpan = currentUserComment.querySelector('.comment');
                if (commentSpan) {
                    currentComment = commentSpan.textContent.trim();
                }
            }

            if (commentContainer.querySelector('textarea')) return;

            const input = document.createElement('textarea');
            input.value = currentComment;
            input.style.width = '100%';
            input.style.boxSizing = 'border-box';
            input.style.minHeight = '3em';

            // Set initial height based on content
            input.style.height = 'auto';
            input.style.height = input.scrollHeight + 'px';

            let isSaving = false;

            function saveComment() {
                if (isSaving) return;
                isSaving = true;

                const newComment = input.value.trim();
                const image = isModal ? currentFullImagePath : container.dataset.image;

                const tempCommentBox = commentContainer.querySelector('.comment-box.temp-edit');
                if (tempCommentBox) {
                    tempCommentBox.remove();
                }

                updateComment(image, newComment);
            }

            input.addEventListener('blur', saveComment);
            input.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = this.scrollHeight + 'px';
            });
            input.addEventListener('keypress', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    input.blur();
                }
            });

            if (currentUserComment) {
                currentUserComment.innerHTML = '';
                currentUserComment.appendChild(input);
            } else {
                if (commentPlaceholder) {
                    commentPlaceholder.style.display = 'none';
                }
                const newCommentBox = document.createElement('div');
                newCommentBox.className = 'comment-box current-user temp-edit';
                newCommentBox.appendChild(input);
                commentContainer.appendChild(newCommentBox);
            }
            input.focus();

            // Adjust height after focusing
            input.style.height = 'auto';
            input.style.height = input.scrollHeight + 'px';
        }

        function updateComment(imageName, comment) {
            fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `add_comment=1&image=${encodeURIComponent(imageName)}&comment=${encodeURIComponent(comment)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateSingleComment(imageName, currentUserIp, comment, data.timestamp);
                        if (typeof checkForUpdates === 'function') {
                            checkForUpdates();
                        }
                        // Refresh modal comments if the modal is open and showing this image
                        if (modal && modal.style && modal.style.display === 'block' && currentFullImagePath === imageName) {
                            if (currentImageIndex >= 0 && images && images[currentImageIndex]) {
                                updateModalComments(images[currentImageIndex]);
                            } else {
                                updateModal(); // For footer images
                            }
                        }
                    } else {
                        console.error('Failed to update comment:', data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
        }

        function updateSingleComment(imageName, userIp, comment, timestamp) {
            const container = document.querySelector(`.image-container[data-image="${imageName}"]`);
            const modalContainer = document.getElementById('imageModal');

            if (container) {
                updateCommentUIForContainer(container, userIp, comment, timestamp, false);
            }

            if (modalContainer.style.display === 'block' && currentFullImagePath === imageName) {
                updateCommentUIForContainer(modalContainer, userIp, comment, timestamp, true);
            }
        }

        function updateCommentUIForContainer(container, userIp, comment, timestamp, isModal) {
            let commentContainer = container.querySelector('.comment-container');
            if (!commentContainer) {
                commentContainer = createCommentContainer(container);
            }

            const existingTextarea = commentContainer.querySelector('textarea');
            if (existingTextarea) {
                existingTextarea.remove();
            }

            let userCommentBox = commentContainer.querySelector(`.comment-box[data-user-ip="${userIp}"]`);

            if (comment.trim() === '') {
                if (userCommentBox) {
                    userCommentBox.remove();
                }
                // Add comment placeholder if it doesn't exist and there are no other comments
                if (!commentContainer.querySelector('.comment-box')) {
                    const commentPlaceholder = createCommentPlaceholder(container, isModal);
                    commentContainer.appendChild(commentPlaceholder);
                }
            } else {
                if (!userCommentBox) {
                    userCommentBox = document.createElement('div');
                    userCommentBox.className = 'comment-box';
                    userCommentBox.dataset.userIp = userIp;
                    commentContainer.appendChild(userCommentBox);
                }

                const userColor = generateColorFromIP(userIp);
                userCommentBox.innerHTML = `
            <span class="comment-user" style="color: ${userColor};" data-user-ip="${userIp}">${getUserName(userIp)}:</span>
            <span class="comment">${comment}</span>
        `;

                if (userIp === currentUserIp) {
                    userCommentBox.classList.add('current-user');
                    userCommentBox.addEventListener('click', function() {
                        editComment(container, isModal);
                    });
                }

                userCommentBox.dataset.timestamp = timestamp;
            }

            // Sort comments by timestamp
            const commentBoxes = Array.from(commentContainer.querySelectorAll('.comment-box'));
            commentBoxes.sort((a, b) => parseInt(a.dataset.timestamp) - parseInt(b.dataset.timestamp));
            commentBoxes.forEach(box => commentContainer.appendChild(box));

            // Remove comment placeholder if there are comments
            const existingPlaceholder = commentContainer.querySelector('.comment-placeholder');
            if (existingPlaceholder && commentContainer.querySelector('.comment-box')) {
                existingPlaceholder.remove();
            }

            // Ensure there's always a way to add a comment for the current user
            if (!commentContainer.querySelector('.comment-box.current-user') && !commentContainer.querySelector('.comment-placeholder')) {
                const commentPlaceholder = createCommentPlaceholder(container, isModal);
                commentContainer.appendChild(commentPlaceholder);
            }
        }

        function getUserName(userIp) {
            const userNameElement = document.querySelector(`.user-name[data-user-ip="${userIp}"]`);
            return userNameElement ? userNameElement.textContent : userIp;
        }

        function checkForUpdates() {
            fetch(`<?php echo $_SERVER['PHP_SELF']; ?>?check_updates=1&lastModified=${lastModified}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(result => {
                    if (result.updated && result.data) {
                        updateUI(result.data);
                        lastModified = result.lastModified;
                    } else if (result.lastModified) {
                        lastModified = result.lastModified;
                    }
                })
                .catch(error => {
                    console.error('Error checking for updates:', error);
                })
                .finally(() => {
                    setTimeout(checkForUpdates, 2000);
                });
        }

        function addFooterEventListeners(footer) {
            const minimizeButton = footer.querySelector('.minimize-button');
            const thumbnails = footer.querySelectorAll('.starred-thumbnail');
            const downloadAllButton = footer.querySelector('.download-all-button');
            const copyNamesButton = footer.querySelector('.copy-names-button');
            const isCurrentUserFooter = footer.dataset.userIp === currentUserIp;
            const userIp = footer.dataset.userIp;

            if (minimizeButton && !minimizeButton.hasEventListener) {
                minimizeButton.hasEventListener = true;
                minimizeButton.addEventListener('click', function(event) {
                    event.stopPropagation();
                    footer.classList.toggle('minimized');
                    const isMinimized = footer.classList.contains('minimized');
                    this.textContent = isMinimized ? '▲' : '▼';
                    footerMinimizedStates[userIp] = isMinimized;
                    updateFooterSpacerHeight();
                });
            }

            thumbnails.forEach(thumbnail => {
                const imageName = thumbnail.dataset.fullImage.split('/').pop();

                thumbnail.addEventListener('click', (event) => {
                    if (event.target === thumbnail || event.target.tagName === 'IMG') {
                        openModal(imageName);
                    }
                });

                if (isCurrentUserFooter) {
                    const unstarButton = thumbnail.querySelector('button[title="Unstar"]');
                    const downloadButton = thumbnail.querySelector('button[title="Download"]');

                    if (unstarButton && !unstarButton.hasEventListener) {
                        unstarButton.hasEventListener = true;
                        unstarButton.addEventListener('click', (event) => {
                            event.stopPropagation();
                            toggleStar(imageName);
                        });
                    }

                    if (downloadButton) {
                        downloadButton.addEventListener('click', (event) => {
                            event.stopPropagation();
                            downloadImage(thumbnail.dataset.fullImage);
                        });
                    }
                } else {
                    thumbnail.querySelectorAll('.thumbnail-buttons').forEach(buttons => buttons.remove());
                }
            });

            if (downloadAllButton) {
                downloadAllButton.addEventListener('click', () => {
                    const starredImages = Array.from(thumbnails).map(thumbnail => thumbnail.dataset.fullImage);
                    downloadImages(starredImages);
                });
            }

            if (copyNamesButton) {
                copyNamesButton.addEventListener('click', () => {
                    const starredList = footer.querySelector('.starred-list');
                    let copyText = '';

                    starredList.querySelectorAll('.starred-thumbnail').forEach(thumbnail => {
                        const imageUrl = thumbnail.dataset.fullImage;
                        const imageName = imageUrl.split('/').pop();
                        if (validImages.includes(imageName)) {
                            copyText += `${imageUrl}\n`;

                            const imageContainer = document.querySelector(`.image-container[data-image="${imageName}"]`);
                            if (imageContainer) {
                                const comments = imageContainer.querySelectorAll('.comment-box');
                                comments.forEach(comment => {
                                    const userName = comment.querySelector('.comment-user').textContent.trim();
                                    const commentText = comment.querySelector('.comment').textContent.trim();
                                    copyText += `    ${userName} ${commentText}\n`;
                                });
                            }
                            copyText += '\n';
                        }
                    });

                    navigator.clipboard.writeText(copyText.trim()).then(() => {
                        showToast('Image URLs and comments copied to clipboard');
                    }).catch(err => {
                        console.error('Failed to copy valid image URLs and comments: ', err);
                        showToast('Failed to copy valid image URLs and comments');
                    });
                });
            }
        }
        let validImages = <?php echo json_encode($validImages); ?>;

        function updateStarredFooters() {
            console.debug("updateStarredFooters called. Current userStarredImages:", JSON.parse(JSON.stringify(userStarredImages)));
            let starredFootersContainer = document.getElementById('starred-footers-container');
            if (!starredFootersContainer) {
                starredFootersContainer = document.createElement('div');
                starredFootersContainer.id = 'starred-footers-container';
                document.body.appendChild(starredFootersContainer);
            }

            // Save current states before updating
            document.querySelectorAll('.starred-footer').forEach(footer => {
                const userIp = footer.dataset.userIp;
                footerMinimizedStates[userIp] = footer.classList.contains('minimized');
            });

            // Update current user's footer
            const currentUserValidImages = initialStarredImages.filter(image => validImages.includes(image));
            updateUserFooter(currentUserIp, currentUserValidImages, true);

            // Count other users with valid images
            const otherUsers = Object.keys(userStarredImages).filter(ip => {
                if (ip === currentUserIp) return false;
                const userImages = userStarredImages[ip].starred_images;
                const validUserImages = Array.isArray(userImages) 
                    ? userImages.filter(image => validImages.includes(image))
                    : Object.values(userImages).filter(image => validImages.includes(image));
                console.debug(`User ${ip}: validUserImages=${JSON.stringify(validUserImages)}`);
                return validUserImages.length > 0;
            });
            console.debug("Filtered otherUsers:", otherUsers);
            const otherUsersCount = otherUsers.length;
            console.debug("otherUsersCount:", otherUsersCount);

            // Remove existing other users container and toggle if they exist
            const existingOtherUsersContainer = document.getElementById('other-users-container');
            const existingOtherUsersToggle = document.getElementById('other-users-toggle');
            if (existingOtherUsersContainer) existingOtherUsersContainer.remove();
            if (existingOtherUsersToggle) existingOtherUsersToggle.remove();

            if (otherUsersCount > 0) {
                // Create submenu for multiple other users
                const otherUsersToggle = document.createElement('div');
                otherUsersToggle.id = 'other-users-toggle';
                otherUsersToggle.className = isOtherUsersMinimized ? 'minimized' : '';
                otherUsersToggle.innerHTML = `
                    <button class="minimize-button" title="Toggle Other Users">${isOtherUsersMinimized ? '▲' : '▼'}</button>
                    <span>Other Users</span>
                    <span class="user-count-badge">${otherUsersCount}</span>
                `;
                otherUsersToggle.addEventListener('click', toggleOtherUsers);
                starredFootersContainer.appendChild(otherUsersToggle);

                const otherUsersContainer = document.createElement('div');
                otherUsersContainer.id = 'other-users-container';
                otherUsersContainer.style.display = isOtherUsersMinimized ? 'none' : 'block';
                starredFootersContainer.appendChild(otherUsersContainer);

                // Update other users' footers in the submenu
                otherUsers.forEach(userIp => {
                    const userImages = userStarredImages[userIp].starred_images;
                    const validStarredImages = Array.isArray(userImages)
                        ? userImages.filter(image => validImages.includes(image))
                        : Object.values(userImages).filter(image => validImages.includes(image));
                    updateUserFooter(userIp, validStarredImages, false);
                });
            }

            updateFooterSpacerHeight();
        }

        function createStarredFooter(userIp, starredImages, isCurrentUser) {
            const footer = document.createElement('div');
            footer.className = 'starred-footer';
            if (!isCurrentUser) {
                footer.classList.add('minimized');
            }
            footer.dataset.userIp = userIp;

            const minimizeButton = document.createElement('button');
            minimizeButton.className = 'minimize-button';
            minimizeButton.title = 'Minimize';
            minimizeButton.textContent = isCurrentUser ? '▼' : '▲';
            footer.appendChild(minimizeButton);

            const userName = document.createElement('span');
            userName.className = 'user-name ellipsis';
            userName.dataset.userIp = userIp;
            userName.textContent = isCurrentUser ?
                document.getElementById('top-user-name').textContent :
                (userStarredImages && userStarredImages[userIp] ? userStarredImages[userIp].name : userIp);
            footer.appendChild(userName);

            const starredListContainer = document.createElement('div');
            starredListContainer.className = 'starred-list-container';
            const starredList = document.createElement('div');
            starredList.className = 'starred-list';
            starredImages.forEach(imageName => {
                const thumbnail = createThumbnail(imageName, isCurrentUser);
                if (thumbnail) {
                    starredList.appendChild(thumbnail);
                }
            });
            starredListContainer.appendChild(starredList);
            footer.appendChild(starredListContainer);

            const footerButtons = document.createElement('div');
            footerButtons.className = 'footer-buttons';
            footerButtons.innerHTML = `
        <button class="footer-button download-all-button" title="Download All">
            ${DOWNLOAD_SVG}
        </button>
        <button class="footer-button copy-names-button" title="Copy Names">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-copy">
                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
            </svg>
        </button>
    `;
            footer.appendChild(footerButtons);

            return footer;
        }

        function createThumbnail(imageName, isCurrentUser) {
            if (!validImages.includes(imageName)) {
                return null;
            }

            const thumbnail = document.createElement('div');
            thumbnail.className = 'starred-thumbnail';
            thumbnail.dataset.fullImage = `${baseUrl}${subDir ? '/' + subDir : ''}/${imageName}`;

            const img = document.createElement('img');
            const webpImageName = imageName.replace(/\.(jpg|jpeg|png|gif|webp)$/i, '.webp');
            img.src = `${relativeThumbsUrl}/${webpImageName}`;
            img.alt = imageName;

            thumbnail.appendChild(img);

            // Add click event to open modal for all users
            thumbnail.addEventListener('click', (event) => {
                if (event.target === thumbnail || event.target.tagName === 'IMG') {
                    openModal(imageName);
                }
            });

            if (isCurrentUser) {
                const buttonsContainer = document.createElement('div');
                buttonsContainer.className = 'thumbnail-buttons';

                const unstarButton = document.createElement('button');
                unstarButton.title = 'Unstar';
                unstarButton.textContent = '★';
                unstarButton.addEventListener('click', (event) => {
                    event.stopPropagation();
                    toggleStar(imageName);
                });
                buttonsContainer.appendChild(unstarButton);

                const downloadButton = document.createElement('button');
                downloadButton.title = 'Download';
                downloadButton.innerHTML = DOWNLOAD_SVG;
                downloadButton.addEventListener('click', (event) => {
                    event.stopPropagation();
                    downloadImage(`${baseUrl}${subDir ? '/' + subDir : ''}/${imageName}`);
                });
                buttonsContainer.appendChild(downloadButton);

                thumbnail.appendChild(buttonsContainer);
            }

            return thumbnail;
        }

        // Make sure this function is defined
        function downloadImage(imageUrl) {
            const link = document.createElement('a');
            link.href = imageUrl;
            link.download = imageUrl.split('/').pop();
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        function updateFooterSpacerHeight() {
            const footerSpacerElement = document.getElementById('footer-spacer');
            const starredFootersContainer = document.getElementById('starred-footers-container');
            const footers = Array.from(starredFootersContainer.querySelectorAll('.starred-footer'));
            const otherUsersToggle = document.getElementById('other-users-toggle');

            const isScrolledToBottom = window.innerHeight + window.pageYOffset >= document.body.offsetHeight - 1;

            let totalHeight = 0;
            footers.forEach(footer => {
                totalHeight += footer.offsetHeight;
            });

            // Add height of other users toggle and container if they exist
            if (otherUsersToggle) {
                totalHeight += otherUsersToggle.offsetHeight;
            }

            footerSpacerElement.style.height = `${totalHeight}px`;

            if (isScrolledToBottom) {
                window.scrollTo(0, document.body.scrollHeight);
            }
        }

        function updateUI(data) {
            console.debug("updateUI called with data:", JSON.parse(JSON.stringify(data)));
            const allComments = {};
            Object.keys(data).forEach(userIp => {
                if (data[userIp].comments) {
                    Object.keys(data[userIp].comments).forEach(imageName => {
                        if (!allComments[imageName]) {
                            allComments[imageName] = {};
                        }
                        allComments[imageName][userIp] = {
                            name: data[userIp].name || userIp,
                            comment: data[userIp].comments[imageName],
                            timestamp: data[userIp].comment_timestamps[imageName] || 0
                        };
                    });
                }
            });

            Object.keys(allComments).forEach(imageName => {
                const container = document.querySelector(`.image-container[data-image="${imageName}"]`);
                if (container) {
                    updateCommentUI(container, allComments[imageName], false);
                }
            });

            Object.keys(data).forEach(userIp => {
                if (data[userIp].starred_images) {
                    if (userIp === currentUserIp) {
                        initialStarredImages.length = 0;
                        initialStarredImages.push(...data[userIp].starred_images);
                        updateStarredFooters();
                    } else {
                        console.debug(`Updating starred images for user ${userIp}:`, data[userIp].starred_images);
                        updateOtherUserStarredImages(userIp, data[userIp].starred_images);
                    }
                }
                if (data[userIp].name) {
                    updateUserName(userIp, data[userIp].name);
                }
            });

            updateMainGalleryStars(data[currentUserIp].starred_images);

            // Update modal if it's open
            if (modal.style.display === 'block') {
                updateModalContent();
            }
        }

        function updateModalContent() {
            if (currentFullImagePath) {
                const imageName = currentFullImagePath.split('/').pop();
                const imageContainer = document.querySelector(`.image-container[data-image="${imageName}"]`);

                if (imageContainer) {
                    // Update star status
                    const isStarred = imageContainer.classList.contains('starred');
                    updateStarButton(modalStarButton, isStarred);

                    // Update comments
                    const commentContainer = modal.querySelector('.comment-container');
                    const imageComments = imageContainer.querySelector('.comment-container');
                    if (commentContainer && imageComments) {
                        commentContainer.innerHTML = imageComments.innerHTML;
                    }

                    // Update title
                    const modalTitle = document.getElementById('modalTitle');
                    const imageTitle = imageContainer.querySelector('.image-title');
                    if (modalTitle && imageTitle) {
                        modalTitle.textContent = imageTitle.textContent;
                    }
                }
            }
        }

        function updateOtherUserStarredImages(userIp, starredImages) {
            console.debug("Updating starred images for user:", userIp, "Images:", starredImages);
            const userFooter = document.querySelector(`.starred-footer[data-user-ip="${userIp}"]`);
            const wasMinimized = footerMinimizedStates[userIp] !== undefined ? footerMinimizedStates[userIp] : true;

            // Ensure starredImages is an array
            starredImages = Array.isArray(starredImages) ? starredImages : [];

            if (userFooter) {
                const starredList = userFooter.querySelector('.starred-list');
                starredList.innerHTML = '';
                starredImages.forEach(imageName => {
                    const thumbnail = createThumbnail(imageName, false);
                    if (thumbnail) {
                        starredList.appendChild(thumbnail);
                    }
                });
                userFooter.style.display = starredImages.length > 0 ? 'grid' : 'none';
            } else {
                // If the footer doesn't exist, create it
                updateUserFooter(userIp, starredImages, false);
            }

            // Update the userStarredImages object
            if (!userStarredImages[userIp]) {
                userStarredImages[userIp] = {};
            }
            userStarredImages[userIp].starred_images = starredImages;
            console.debug("Updated userStarredImages:", JSON.parse(JSON.stringify(userStarredImages)));

            // Preserve the minimized state
            const updatedFooter = document.querySelector(`.starred-footer[data-user-ip="${userIp}"]`);
            if (updatedFooter) {
                updatedFooter.classList.toggle('minimized', wasMinimized);
                const minimizeButton = updatedFooter.querySelector('.minimize-button');
                if (minimizeButton) {
                    minimizeButton.textContent = wasMinimized ? '▲' : '▼';
                }
                addFooterEventListeners(updatedFooter);
            }

            footerMinimizedStates[userIp] = wasMinimized;
        }

        function updateUserName(userIp, newName) {
            const userNameElements = document.querySelectorAll(`.user-name[data-user-ip="${userIp}"]`);
            userNameElements.forEach(el => {
                el.textContent = newName;
                if (el.id === 'top-user-name') {
                    const userColor = generateColorFromIP(userIp);
                    el.style.color = userColor;
                }
            });
        }

        function updateMainGalleryStars(starredImages) {
            document.querySelectorAll('.image-container').forEach(container => {
                const imageName = container.dataset.image;
                const starButton = container.querySelector('.star-button');
                const isStarred = starredImages.includes(imageName);
                updateStarButton(starButton, isStarred);
                container.classList.toggle('starred', isStarred);
            });
        }

        function openModal(containerOrImageName) {
            console.debug("Opening modal for:", containerOrImageName);
            let imageName;

            if (typeof containerOrImageName === 'string') {
                imageName = containerOrImageName;
                currentImageIndex = -1;
            } else {
                imageName = containerOrImageName.dataset.image;
                currentImageIndex = images.indexOf(containerOrImageName);
            }

            currentFullImagePath = imageName;
            console.info("CurrentFullImagePath set to:", currentFullImagePath);
            updateModal();
            modal.style.display = 'block';

            // Force layout recalculation
            modal.offsetHeight;
        }

        function updateModal() {
            console.debug("Updating modal, currentImageIndex:", currentImageIndex);
            const imageName = currentFullImagePath.split('/').pop();
            const imageContainer = document.querySelector(`.image-container[data-image="${imageName}"]`);

            // Clear existing content
            modalImg.src = '';
            modalImg.alt = '';
            modalTitle.textContent = '';
            const commentContainer = modal.querySelector('.comment-container');
            commentContainer.innerHTML = '';

            // Remove any existing loading indicators
            const existingLoadingIndicators = modal.querySelectorAll('.loading-indicator');
            existingLoadingIndicators.forEach(indicator => indicator.remove());

            // Set loading state
            modalImg.style.opacity = '0';
            const loadingIndicator = document.createElement('div');
            loadingIndicator.textContent = 'Loading...';
            loadingIndicator.className = 'loading-indicator';
            loadingIndicator.style.position = 'absolute';
            loadingIndicator.style.top = '50%';
            loadingIndicator.style.left = '50%';
            loadingIndicator.style.transform = 'translate(-50%, -50%)';
            modal.querySelector('.modal-image-container').appendChild(loadingIndicator);

            if (imageContainer) {
                // Image is in the main gallery
                const imageSrc = imageContainer.querySelector('img').dataset.fullImage;
                const imageTitle = imageContainer.querySelector('.image-title').textContent;
                const isStarred = imageContainer.classList.contains('starred');

                modalImg.onload = function() {
                    modalImg.style.opacity = '1';
                    loadingIndicator.remove();
                };
                modalImg.src = imageSrc;
                modalImg.alt = imageTitle;
                modalTitle.textContent = imageTitle;
                updateStarButton(modalStarButton, isStarred);
                modalDownloadButton.href = imageSrc;
                modalDownloadButton.download = imageName;

                updateModalComments(imageContainer);

                // Show navigation buttons for main gallery images
                prevBtn.style.display = 'flex';
                nextBtn.style.display = 'flex';
            } else {
                // Image is from footer
                const imageSrc = `${baseUrl}${subDir ? '/' + subDir : ''}/${imageName}`;

                modalImg.onload = function() {
                    modalImg.style.opacity = '1';
                    loadingIndicator.remove();
                };
                modalImg.src = imageSrc;
                modalImg.alt = imageName;
                modalTitle.textContent = imageName;
                updateStarButton(modalStarButton, initialStarredImages.includes(imageName));
                modalDownloadButton.href = imageSrc;
                modalDownloadButton.download = imageName;

                // Clear comments for footer images
                const commentPlaceholder = document.createElement('div');
                commentPlaceholder.className = 'comment-placeholder';
                commentPlaceholder.textContent = 'Comment';
                commentPlaceholder.addEventListener('click', () => editModalComment(imageName));
                commentContainer.appendChild(commentPlaceholder);

                // Hide navigation buttons for footer images
                prevBtn.style.display = 'none';
                nextBtn.style.display = 'none';
            }

            // Ensure modal content fits within the screen
            modalImg.style.maxWidth = '100%';
            modalImg.style.height = 'auto';
            modalImg.style.maxHeight = '60vh';

            // Force layout recalculation
            modal.offsetHeight;
        }

        function downloadImage(imageName) {
            const link = document.createElement('a');
            link.href = imageName;
            link.download = imageName.split('/').pop();
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        function downloadImages(imageUrls) {
            const validImageUrls = imageUrls.filter(url => validImages.includes(url.split('/').pop()));
            validImageUrls.forEach((url, index) => {
                setTimeout(() => {
                    downloadImage(url);
                }, index * 1000);
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            const gallery = document.querySelector('.gallery');
            initializeModalElements();
            images = Array.from(document.querySelectorAll('.image-container'));

            function editModalComment() {
                const commentContainer = modal.querySelector('.comment-container');
                const currentUserComment = commentContainer.querySelector('.comment-box.current-user');
                const commentPlaceholder = commentContainer.querySelector('.comment-placeholder');
                let currentComment = '';

                if (currentUserComment) {
                    const commentSpan = currentUserComment.querySelector('.comment');
                    if (commentSpan) {
                        currentComment = commentSpan.textContent.trim();
                    }
                }

                if (commentContainer.querySelector('textarea')) return;

                const input = document.createElement('textarea');
                input.value = currentComment;
                input.style.width = '100%';
                input.style.boxSizing = 'border-box';
                input.style.minHeight = '3em';

                input.style.height = 'auto';
                input.style.height = input.scrollHeight + 'px';

                let isSaving = false;

                function saveModalComment() {
                    if (isSaving) return;
                    isSaving = true;

                    const newComment = input.value.trim();

                    updateCommentUIForContainer(modal, currentUserIp, newComment, true);

                    updateComment(currentFullImagePath, newComment);

                    if (currentImageIndex >= 0) {
                        const currentImage = images[currentImageIndex];
                        updateCommentUIForContainer(currentImage, currentUserIp, newComment, false);
                    }
                }

                input.addEventListener('blur', saveModalComment);
                input.addEventListener('input', function() {
                    this.style.height = 'auto';
                    this.style.height = this.scrollHeight + 'px';
                });
                input.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        input.blur();
                    }
                });

                if (currentUserComment) {
                    currentUserComment.innerHTML = '';
                    currentUserComment.appendChild(input);
                } else {
                    if (commentPlaceholder) {
                        commentPlaceholder.style.display = 'none';
                    }
                    const newCommentBox = document.createElement('div');
                    newCommentBox.className = 'comment-box current-user temp-edit';
                    newCommentBox.appendChild(input);
                    commentContainer.appendChild(newCommentBox);
                }
                input.focus();

                input.style.height = 'auto';
                input.style.height = input.scrollHeight + 'px';
            }

            function initializeStarredImages() {
                document.querySelectorAll('.image-container').forEach(container => {
                    const starButton = container.querySelector('.star-button');
                    const imageName = container.dataset.image;
                    const isStarred = initialStarredImages.includes(imageName);
                    updateStarButton(starButton, isStarred);
                    container.classList.toggle('starred', isStarred);
                });
                updateStarredFooters();
            }

            function navigateModal(direction) {
                console.debug("Navigating modal, direction:", direction);
                if (currentImageIndex >= 0) {
                    currentImageIndex = (currentImageIndex + direction + images.length) % images.length;
                    const newImage = images[currentImageIndex];
                    currentFullImagePath = newImage.dataset.image;
                } else {
                    const allImages = Array.from(document.querySelectorAll('.image-container'));
                    const currentIndex = allImages.findIndex(img => img.dataset.image === currentFullImagePath);
                    if (currentIndex !== -1) {
                        const newIndex = (currentIndex + direction + allImages.length) % allImages.length;
                        const newImage = allImages[newIndex];
                        currentFullImagePath = newImage.dataset.image;
                        currentImageIndex = images.indexOf(newImage);
                    } else {
                        console.warn("Navigation not available for this image.");
                        return;
                    }
                }
                updateModal();
            }

            function makeNameEditable(nameSpan) {
                const currentName = nameSpan.textContent;
                const input = document.createElement('input');
                input.type = 'text';
                input.value = currentName;
                input.className = 'user-name-input';

                function saveNameChange() {
                    const newName = input.value.trim();
                    updateAllUserNames(newName);

                    fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `update_name=${encodeURIComponent(newName)}`
                        })
                        .then(response => response.json())
                        .then(result => {
                            if (result.success) {
                                showToast('Name updated successfully!');
                                updateAllUserNames(result.name);
                            } else {
                                throw new Error(result.error || 'Failed to update name.');
                            }
                        })
                        .catch(error => {
                            console.error('Error updating name:', error);
                            showToast(error.message);
                            updateAllUserNames(currentName);
                        })
                        .finally(() => {
                            nameSpan.classList.remove('editing');
                            input.remove();
                        });
                }

                input.addEventListener('blur', saveNameChange);
                input.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        this.blur();
                    }
                });

                nameSpan.classList.add('editing');
                nameSpan.parentNode.insertBefore(input, nameSpan);
                input.focus();
            }

            function updateAllUserNames(newName) {
                const userNameElements = document.querySelectorAll(`.user-name[data-user-ip="${currentUserIp}"]`);
                userNameElements.forEach(el => {
                    el.textContent = newName;
                });

                const commentUserElements = document.querySelectorAll(`.comment-user[data-user-ip="${currentUserIp}"]`);
                commentUserElements.forEach(el => {
                    el.textContent = newName + ':';
                });

                const topUserName = document.getElementById('top-user-name');
                if (topUserName) {
                    topUserName.textContent = newName;
                    const userColor = generateColorFromIP(currentUserIp);
                    topUserName.style.color = userColor;
                }

                const footerUserName = document.querySelector(`.starred-footer[data-user-ip="${currentUserIp}"] .user-name`);
                if (footerUserName) {
                    footerUserName.textContent = newName;
                }

                const modalUserNameElements = document.querySelectorAll('#imageModal .comment-user');
                modalUserNameElements.forEach(el => {
                    if (el.dataset.userIp === currentUserIp) {
                        el.textContent = newName + ':';
                    }
                });

                const allCommentBoxes = document.querySelectorAll('.comment-box');
                allCommentBoxes.forEach(box => {
                    if (box.dataset.userIp === currentUserIp) {
                        const commentUserSpan = box.querySelector('.comment-user');
                        if (commentUserSpan) {
                            commentUserSpan.textContent = newName + ':';
                        }
                    }
                });
            }

            initializeStarredImages();

            closeBtn.onclick = function() {
                modal.style.display = 'none';
            }

            prevBtn.onclick = function() {
                navigateModal(-1);
            }

            nextBtn.onclick = function() {
                navigateModal(1);
            }

            modalStarButton.onclick = function() {
                console.debug("Toggling star for image:", currentFullImagePath);
                toggleStar(currentFullImagePath);
            }

            window.onclick = function(e) {
                if (e.target == modal) {
                    modal.style.display = 'none';
                }
            }

            document.addEventListener('keydown', function(e) {
                if (modal.style.display === 'block') {
                    switch (e.key) {
                        case 'ArrowLeft':
                        case 'ArrowUp':
                            e.preventDefault();
                            navigateModal(-1);
                            break;
                        case 'ArrowRight':
                        case 'ArrowDown':
                            e.preventDefault();
                            navigateModal(1);
                            break;
                        case 'Escape':
                            e.preventDefault();
                            modal.style.display = 'none';
                            break;
                    }
                }
            });

            const modalImageContainer = document.querySelector('.modal-image-container');
            modalImageContainer.addEventListener('wheel', function(e) {
                e.preventDefault();
                if (e.deltaY > 0) {
                    navigateModal(1);
                } else {
                    navigateModal(-1);
                }
            });

            const pageTitle = document.querySelector('#page-title');
            pageTitle.addEventListener('click', function() {
                window.location.href = window.location.pathname;
            });

            const userNameContainer = document.getElementById('user-name-container');
            userNameContainer.addEventListener('click', function(e) {
                const topUserName = document.getElementById('top-user-name');
                if (e.target === topUserName || e.target.closest('.edit-icon')) {
                    makeNameEditable(topUserName);
                }
            });

            gallery.addEventListener('click', function(e) {
                const container = e.target.closest('.image-container');
                if (!container) return;

                if (e.target.closest('.image-wrapper img')) {
                    openModal(container);
                } else if (e.target.classList.contains('star-button')) {
                    e.preventDefault();
                    e.stopPropagation();
                    toggleStar(container);
                } else if (e.target.closest('.comment-box.current-user') || e.target.closest('.comment-placeholder')) {
                    editComment(container);
                }
            });

            const topBar = document.getElementById('top-bar');
            const starredFootersContainer = document.getElementById('starred-footers-container');
            const maxScrollForShadow = 100;

            function handleScroll() {
                const scrollPosition = window.scrollY;
                const maxScroll = document.documentElement.scrollHeight - window.innerHeight;

                const topShadowOpacity = Math.min(scrollPosition / maxScrollForShadow, 1);
                topBar.style.boxShadow = `0 20px 10px rgba(0, 0, 0, ${topShadowOpacity * 0.4})`;

                const bottomScrollPosition = maxScroll - scrollPosition;
                const footerShadowOpacity = Math.min(bottomScrollPosition / maxScrollForShadow, 1);
                starredFootersContainer.style.boxShadow = `0 -20px 10px rgba(0, 0, 0, ${footerShadowOpacity * 0.4})`;
            }

            window.addEventListener('scroll', handleScroll);

            handleScroll();

            const modalCommentContainer = document.querySelector('#imageModal .comment-container');
            modalCommentContainer.addEventListener('click', function(e) {
                if (e.target.closest('.comment-box.current-user') || e.target.closest('.comment-placeholder')) {
                    editModalComment();
                }
            });

            checkForUpdates();

            starredFootersContainer.addEventListener('click', function(event) {
                const target = event.target;
                const footer = target.closest('.starred-footer');

                if (!footer) return;

                if (target.classList.contains('minimize-button')) {
                    const isMinimized = footer.classList.contains('minimized');
                    footer.classList.toggle('minimized');
                    target.textContent = isMinimized ? '▼' : '▲';

                    // Update the server about the footer state
                    updateFooterState(footer.dataset.userIp, !isMinimized);

                    setTimeout(() => {
                        updateFooterSpacerHeight();
                    }, 0);

                    event.stopPropagation();
                } else if (footer.classList.contains('minimized')) {
                    footer.classList.remove('minimized');
                    const minimizeButton = footer.querySelector('.minimize-button');
                    if (minimizeButton) {
                        minimizeButton.textContent = '▼';
                    }

                    // Update the server about the footer state
                    updateFooterState(footer.dataset.userIp, false);

                    setTimeout(() => {
                        updateFooterSpacerHeight();
                    }, 0);
                }
            });

            function updateFooterState(userIp, isMinimized) {
                fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `update_footer_state=1&user_ip=${encodeURIComponent(userIp)}&minimized=${isMinimized}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (!data.success) {
                            console.error('Failed to update footer state');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                    });
            }
            updateFooterSpacerHeight();

            setTopUserNameColor();

            const otherUsersToggle = document.getElementById('other-users-toggle');
            if (otherUsersToggle) {
                otherUsersToggle.addEventListener('click', toggleOtherUsers);
            }

            // Theme toggle functionality
            const themeToggle = document.getElementById('theme-toggle');
            const body = document.body;
            const moonIcon = `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-moon"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>`;
            const sunIcon = `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-sun"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>`;

            // Set initial theme
            const initialTheme = '<?php echo $data[$user_ip]['theme']; ?>';
            setTheme(initialTheme);

            themeToggle.addEventListener('click', () => {
                const newTheme = body.classList.contains('light-mode') ? 'dark' : 'light';
                setTheme(newTheme);
                saveThemePreference(newTheme);
            });

            function setTheme(theme) {
                if (theme === 'light') {
                    body.classList.add('light-mode');
                    themeToggle.innerHTML = moonIcon;
                } else {
                    body.classList.remove('light-mode');
                    themeToggle.innerHTML = sunIcon;
                }

                // Update footer styles
                const footer = document.getElementById('starred-footers-container');
                if (footer) {
                    footer.style.backgroundColor = getComputedStyle(body).getPropertyValue('--footer-bg');
                    footer.style.color = getComputedStyle(body).getPropertyValue('--footer-text');
                }

                // Update other users toggle and container
                const otherUsersToggle = document.getElementById('other-users-toggle');
                const otherUsersContainer = document.getElementById('other-users-container');
                if (otherUsersToggle) {
                    otherUsersToggle.style.backgroundColor = getComputedStyle(body).getPropertyValue('--footer-bg');
                    otherUsersToggle.style.borderTopColor = getComputedStyle(body).getPropertyValue('--footer-border');
                }
                if (otherUsersContainer) {
                    otherUsersContainer.style.backgroundColor = getComputedStyle(body).getPropertyValue('--footer-bg');
                }

                // Update minimize buttons and footer buttons
                const buttons = document.querySelectorAll('.minimize-button, .footer-button');
                buttons.forEach(button => {
                    button.style.color = getComputedStyle(body).getPropertyValue('--footer-button');
                });

                updateUserNameColors();
            }

            function saveThemePreference(theme) {
                fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `update_theme=1&theme=${theme}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            console.debug('Theme preference saved');
                        } else {
                            console.error('Failed to save theme preference');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                    });
            }

            function updateUserNameColors() {
                document.querySelectorAll('.comment-user, #top-user-name').forEach(el => {
                    const userIp = el.dataset.userIp;
                    if (userIp) {
                        const userColor = generateColorFromIP(userIp);
                        el.style.color = userColor;
                    }
                });
            }

            setTheme(initialTheme);
        });
    </script>
</head>

<body>
    <div id="top-bar">
        <a href="https://github.com/ktonini/reviewGrid" id="logo-container" title="View on GitHub">
            <svg id="Layer_1" xmlns="http://www.w3.org/2000/svg" version="1.1" viewBox="0 0 157.24 157.67" width="40" height="40">
                <path d="M157.24,5.63v11.81c0,3.04-2.46,5.5-5.5,5.49l-123.64-.13c-3.04,0-5.5,2.46-5.5,5.49v123.88c0,3.03-2.46,5.49-5.49,5.49H5.49c-3.03,0-5.49-2.46-5.49-5.49V5.49C0,2.46,2.46,0,5.5,0l146.26.13c3.03,0,5.49,2.46,5.49,5.49Z" />
                <path d="M50.58,45.31l101.18.13c3.03,0,5.49,2.46,5.49,5.49v11.81c0,3.04-2.47,5.5-5.5,5.49l-78.56-.13c-3.04,0-5.5,2.46-5.5,5.49l.1,56.63c0,3.03,2.45,5.49,5.48,5.49l56.57.12c3.04,0,5.51-2.45,5.51-5.49l-.04-11.6c0-3.03-2.46-5.49-5.49-5.49h-33.97c-3.03,0-5.55-2.48-5.55-5.51v-11.61c0-3.03,2.45-5.49,5.48-5.49h55.88c3.03,0,5.49,2.46,5.49,5.49l.1,56.03c0,3.03-2.46,5.49-5.49,5.49h-17.12s-83.97-.13-83.97-.13c-3.03,0-5.48-2.46-5.48-5.49l-.1-101.23c0-3.04,2.46-5.5,5.5-5.49Z" />
            </svg>
        </a>
        <h1>
            <a href="<?php echo $_SERVER['REQUEST_URI']; ?>" id="page-title"><?php echo $title; ?></a>
        </h1>
        <div id="user-name-container">
            <span id="top-user-name" class="user-name editable" data-user-ip="<?php echo $user_ip; ?>"><?php echo htmlspecialchars($data[$user_ip]['name']); ?></span>
            <svg class="edit-icon" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 24 24" xml:space="preserve">
                <path class="st0" d="M11,4H4C2.9,4,2,4.9,2,6v14c0,1.1,0.9,2,2,2h14c1.1,0,2-0.9,2-2v-7" />
                <path class="st0" d="M18.5,2.5c0.83-0.83,2.17-0.83,3,0s0.83,2.17,0,3L12,15l-4,1l1-4L18.5,2.5z" />
            </svg>
        </div>
        <div id="theme-toggle-container">
            <button id="theme-toggle" aria-label="Toggle theme">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-moon">
                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                </svg>
            </button>
        </div>
    </div>
    <div class="gallery">
        <?php
        if (empty($images)) {
            echo "<p>No images found in the directory.</p>";
        } else {
            foreach ($images as $image) {
                echo generateImageContainer($image, $subDir, $dataDir, $starredImages, $data, $user_ip, $baseUrl, $relativeThumbsUrl, $validImages);
            }
        }
        ?>
    </div>

    <div id="footer-spacer"></div>

    <div id="imageModal" class="modal">
        <div class="modal-content">
            <h2 id="modalTitle"></h2>
            <button class="button close" title="Close">×</button>
            <button class="button prev" title="Previous">❮</button>
            <div class="modal-image-container">
                <img id="modalImage" src="" alt="">
            </div>
            <button class="button next" title="Next">❯</button>
            <button id="modalStarButton" class="button star-button" title="Star">★</button>
            <a id="modalDownloadButton" class="button download-button" title="Download" download>
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-download">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="7 10 12 15 17 10"></polyline>
                    <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
            </a>
            <div class="comment-container"></div>
        </div>
    </div>

    <div id="toast">Image filenames copied to clipboard!</div>
</body>

</html>