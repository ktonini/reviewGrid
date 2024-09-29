<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

$user_ip = $_SERVER['REMOTE_ADDR'];
$usersFile = $dataDir . 'data.json';

// Load or initialize users data
$data = file_exists($usersFile) ? json_decode(file_get_contents($usersFile), true) : [];

if (!isset($data[$user_ip])) {
    $data[$user_ip] = [
        'name' => $user_ip,
        'starred_images' => []
    ];
    file_put_contents($usersFile, json_encode($data));
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_name'])) {
        handleUpdateName();
    } elseif (isset($_POST['toggle_star'])) {
        handleToggleStar();
    } elseif (isset($_POST['add_comment'])) {
        handleAddComment();
    }
    exit;
}

// Handle GET requests for updates
if (isset($_GET['check_updates'])) {
    handleCheckUpdates();
    exit;
}

// Get all images in the script directory
$images = glob($scriptDir . '*.{jpg,jpeg,png,gif}', GLOB_BRACE);

// Remove the script itself from the images array
$images = array_filter($images, function ($key) {
    return basename($key) !== basename($_SERVER['SCRIPT_NAME']);
}, ARRAY_FILTER_USE_KEY);

// Create thumbnails if they don't exist
foreach ($images as $image) {
    $filename = basename($image);
    $thumbPath = $thumbsDir . $filename;

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
    $tmp = imagecreatetruecolor($width, $height);
    imagecopyresampled($tmp, $image, 0, 0, (int)$x, 0, $width, $height, $w, $h);

    switch (strtolower(pathinfo($source, PATHINFO_EXTENSION))) {
        case 'jpeg':
        case 'jpg':
            imagejpeg($tmp, $destination, 100);
            break;
        case 'png':
            imagepng($tmp, $destination, 9);
            break;
        case 'gif':
            imagegif($tmp, $destination);
            break;
    }

    imagedestroy($image);
    imagedestroy($tmp);
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
    if (!empty($comment)) {
        $data[$user_ip]['comments'][$image] = $comment;
    } else {
        unset($data[$user_ip]['comments'][$image]);
    }
    $result = file_put_contents($usersFile, json_encode($data));
    echo json_encode([
        'success' => ($result !== false),
        'comment' => $comment,
        'name' => $data[$user_ip]['name']
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
function generateImageContainer($image, $subDir, $dataDir, $starredImages, $data, $user_ip, $baseUrl, $relativeThumbsUrl)
{
    $filename = basename($image);
    $thumbPath = $relativeThumbsUrl . '/' . $filename;
    $fullImagePath = ($subDir ? '/' . $subDir : '') . '/' . $filename;
    $isStarred = in_array($filename, $starredImages);
    $title = generateTitle($filename);

    ob_start();
?>
    <div class="image-container <?php echo $isStarred ? 'starred' : ''; ?>" data-image="<?php echo $filename; ?>">
        <img src="<?php echo $thumbPath; ?>" alt="<?php echo $title; ?>" data-full-image="<?php echo $fullImagePath; ?>">
        <div class="image-info">
            <h3 class="image-title ellipsis" title="<?php echo $title; ?>"><?php echo $title; ?></h3>
            <div class="comment-container">
                <?php generateCommentBoxes($filename, $data, $user_ip); ?>
                <div class="comment-placeholder">Comment</div>
            </div>
            <div class="button-container">
                <button class="star-button" title="Star" aria-label="Star image">★</button>
                <a href="<?php echo $fullImagePath; ?>" download class="download-button" title="Download" aria-label="Download image">⬇</a>
            </div>
        </div>
    </div>
<?php
    return ob_get_clean();
}

// Function to generate comment boxes
function generateCommentBoxes($filename, $data, $current_user_ip)
{
    foreach ($data as $ip => $userData) {
        if (isset($userData['comments'][$filename])) {
            $userName = htmlspecialchars($userData['name']);
            $comment = htmlspecialchars($userData['comments'][$filename]);
            $isCurrentUser = $ip === $current_user_ip;
            echo "<div class='comment-box" . ($isCurrentUser ? " current-user" : "") . "' data-user-ip='$ip'>";
            echo "<span class='comment-user' data-user-ip='$ip'>$userName:</span>";
            echo "<span class='comment'>$comment</span>";
            echo "</div>";
        }
    }
}

// After loading the $data array, add this:
$userStarredImages = [];
foreach ($data as $ip => $userData) {
    if (!empty($userData['starred_images'])) {
        $userStarredImages[$ip] = $userData;
    }
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
            --button-color: #888;
            --star-color: #ffa768;
            --copy-button-bg: #009dff;
            --copy-button-hover: #81cfff;
            --scrollbar-bg: rgba(255, 255, 255, 0.1);
            --scrollbar-thumb: rgba(255, 255, 255, 0.3);
            --scrollbar-thumb-hover: rgba(255, 255, 255, 0.5);
            --footer-shadow: 0 -20px 100px rgba(0, 0, 0, 0.4);
        }

        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: var(--text-color);
            background-color: var(--bg-color);
            margin: 0;
            padding-top: 1.25em;
        }

        h1 {
            text-align: center;
            margin-bottom: 1.25em;
        }

        .ellipsis {
            text-overflow: ellipsis;
            white-space: nowrap;
            overflow: hidden;
        }

        .gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(15.625em, 1fr));
            gap: 1.25em;
            padding: 1.25em;
        }

        .image-container {
            background-color: var(--card-bg);
            border-radius: 0.5em;
            overflow: hidden;
            box-shadow: 0 0.25em 0.375em rgba(0, 0, 0, 0.3);
            transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease, border 0.2s ease;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            border: 0.25em solid transparent;
        }

        .image-container:hover {
            /* transform: translateY(-0.3125em); */
            box-shadow: 0 0.375em 0.5em rgba(0, 0, 0, 0.4);
        }

        .image-container.starred {
            background-color: var(--starred-bg);
            box-shadow: 0 0.375em 0.75em rgba(0, 0, 0, 0.5);
            border: 0.25em solid var(--star-color);
        }

        .image-container.starred:hover {
            /* transform: translateY(-0.3125em); */
            box-shadow: 0 0.5em 1em rgba(0, 0, 0, 0.6);
        }

        .image-container img {
            width: 100%;
            height: auto;
            display: block;
            cursor: zoom-in;
        }

        .image-info {
            padding: 0.25em;
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }

        .image-title {
            margin: 0 0 0.625em 0;
            font-size: 1em;
            font-weight: bold;
            color: var(--text-color);
            text-align: center;
        }

        .button-container {
            display: flex;
            justify-content: space-between;
            margin-top: auto;
            padding-top: 0.625em;
        }

        .download-button:hover,
        .star-button:hover {
            background-color: #444;
        }

        .star-button,
        .download-button {
            display: flex;
            justify-content: center;
            align-items: center;
            width: 2em;
            height: 2em;
            overflow: hidden;
            border: none;
            background-color: #333;
            border-radius: 0.25em;
            font-size: 1.5em;
            cursor: pointer;
            color: var(--button-color);
            transition: color 0.2s ease, transform 0.2s ease, background-color 0.2s ease;
        }

        .star-button.starred,
        .starred .star-button {
            color: var(--star-color);
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
            backdrop-filter: blur(20px);
        }

        .modal-content {
            display: grid;
            grid-template-rows: auto 1fr auto auto auto;
            grid-template-columns: 3em 1fr 3em;
            gap: 10px;
            width: 100%;
            height: 100%;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            padding: 20px;
            border-radius: 10px;
        }

        .close {
            grid-column: 3;
            grid-row: 1;
            font-size: 2em;
            cursor: pointer;
            align-self: center;
            justify-self: center;
        }

        .prev {
            grid-column: 1;
            grid-row: 2;
            align-self: center;
            justify-self: center;
        }

        .next {
            grid-column: 3;
            grid-row: 2;
            align-self: center;
            justify-self: center;
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
            grid-column: 1 / -1;
            grid-row: 3;
            text-align: center;
            margin: 0;
        }

        .comment-container {
            grid-column: 2 / -2;
            grid-row: 4;
            overflow-y: auto;
            max-height: 150px;
        }

        .button-container {
            grid-column: 1 / -1;
            grid-row: 5;
            display: flex;
            justify-content: space-between;
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
            background-color: rgba(44, 44, 44, 0.8);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            padding-bottom: 0.625em;
        }

        .starred-footer {
            display: grid;
            grid-template-columns: auto 5em 1fr auto auto;
            grid-template-areas: "minimize user list download copy";
            align-items: center;
            padding: 0.625em;
            color: #fff;
            gap: 0.625em;
        }

        .minimize-button {
            grid-area: minimize;
            background: none;
            border: none;
            color: var(--button-color);
            cursor: pointer;
            font-size: 0.8em;
            padding: 0.3125em;
            transition: transform 0.2s ease;
        }

        .user-name {
            grid-area: user;
            flex-shrink: 0;
            min-width: 5em;
        }

        .starred-list-container {
            grid-area: list;
            overflow-x: auto;
            margin-bottom: -1em;
        }

        .footer-buttons {
            grid-area: download / download / copy / copy;
            display: flex;
            gap: 0.625em;
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
            gap: 0.625em;
        }

        .starred-thumbnail {
            position: relative;
            width: 5em;
            height: 5em;
            cursor: pointer;
        }

        .starred-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 0.625em;
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
            gap: 0.25em;
            margin: 0.25em;
        }

        .starred-thumbnail:hover .thumbnail-buttons {
            opacity: 1;
        }

        .thumbnail-buttons button {
            border: none;
            color: #fff;
            font-size: 1em;
            cursor: pointer;
            padding: 0.125em;
            margin: 0 0.125em;
            background-color: rgba(0, 0, 0, 0.5);
            width: 100%;
            height: 100%;
            border-radius: 0.25em;
            backdrop-filter: blur(0.3125em);
        }

        #toast {
            visibility: hidden;
            min-width: 15.625em;
            margin-left: -7.8125em;
            background-color: #333;
            color: #fff;
            text-align: center;
            border-radius: 0.125em;
            padding: 1em;
            position: fixed;
            z-index: 1001;
            left: 50%;
            bottom: 1.875em;
            font-size: 1.0625em;
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
                bottom: 1.875em;
                opacity: 1;
            }
        }

        @keyframes fadein {
            from {
                bottom: 0;
                opacity: 0;
            }

            to {
                bottom: 1.875em;
                opacity: 1;
            }
        }

        @-webkit-keyframes fadeout {
            from {
                bottom: 1.875em;
                opacity: 1;
            }

            to {
                bottom: 0;
                opacity: 0;
            }
        }

        @keyframes fadeout {
            from {
                bottom: 1.875em;
                opacity: 1;
            }

            to {
                bottom: 0;
                opacity: 0;
            }
        }

        #nameModal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.9);
        }

        #nameModal .modal-content {
            margin: auto;
            display: block;
            max-width: 90%;
            max-height: 80%;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        #nameInput {
            width: 100%;
            padding: 0.625em;
            margin-bottom: 0.625em;
        }

        .footer-buttons {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-left: 0.625em;
        }

        .footer-button {
            background: none;
            border: none;
            color: var(--button-color);
            cursor: pointer;
            transition: color 0.2s ease, transform 0.2s ease;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-left: 0.625em;
        }

        .footer-button:hover {
            transform: scale(1.2);
        }

        .footer-button svg {
            width: 2em;
            height: 2em;
        }

        .user-name-input {
            background-color: var(--bg-color);
            color: var(--text-color);
            border: 1px solid var(--text-color);
            padding: 0.3125em;
            border-radius: 0.25em;
            font-size: 1em;
            width: 12em;
        }

        .user-name.editing {
            display: none;
        }

        /* Scrollbar styles */
        ::-webkit-scrollbar {
            width: 0.5em;
            height: 0.5em;
        }

        ::-webkit-scrollbar-track {
            background: var(--scrollbar-bg);
            border-radius: 0.25em;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--scrollbar-thumb);
            border-radius: 0.25em;
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
            margin-top: 0.625em;
            padding: 0.5em;
            background-color: #2a2a2a;
            border-radius: 0.5em;
            box-shadow: 0 0.0625em 0.1875em rgba(0, 0, 0, 0.3);
            width: calc(100% - 0.9375em);
            font-size: 0.8em;
            display: flex;
            flex-direction: column;
            gap: 0.5em;
        }

        .comment-box {
            padding: 0.375em 0.5em;
            background-color: #333333;
            border-radius: 0.375em;
            box-shadow: 0 0.0625em 0.125em rgba(0, 0, 0, 0.2);
            display: flex;
            align-items: baseline;
        }

        .comment-user {
            font-weight: bold;
            color: #b0b0b0;
            margin-right: 0.3125em;
            white-space: nowrap;
        }

        .comment-placeholder {
            color: #808080;
            font-style: italic;
            cursor: pointer;
            padding: 0.375em 0.5em;
        }

        textarea {
            width: 100%;
            padding: 0;
            border-radius: 0.25em;
            resize: vertical;
            min-height: 3.75em;
            background-color: #2a2a2a;
            color: #d0d0d0;
        }

        .current-user .comment-user {
            color: #e0e0e0;
        }

        .current-user {
            background-color: #3c3c3c;
        }

        .comment-box,
        .comment-placeholder {
            transition: background-color 0.2s ease;
        }

        .more-link {
            color: #888;
            text-decoration: none;
            cursor: pointer;
        }

        .more-link:hover {
            text-decoration: underline;
        }

        .minimize-button {
            background: none;
            border: none;
            color: var(--button-color);
            cursor: pointer;
            font-size: 16px;
            padding: 5px;
            transition: transform 0.2s ease;
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
            padding: 0.3125em 0.625em;
        }

        .star-button:focus {
            outline: none;
        }

        #top-bar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 60px;
            /* Set a fixed height */
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 0.5em;
            background-color: rgba(44, 44, 44, 0.8);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            z-index: 1000;
            box-shadow: 0 0 0 rgba(0, 0, 0, 0);
        }

        #top-bar.scrolled {
            box-shadow: 0 20px 10px rgba(0, 0, 0, 0.4);
        }

        #logo-container {
            position: absolute;
            left: 0.5em;
            top: 50%;
            transform: translateY(-50%);
        }

        #logo-container svg {
            fill: var(--text-color);
            transition: fill 0.3s ease;
            width: 40px;
            /* Adjust as needed */
            height: 40px;
            /* Adjust as needed */
        }

        #logo-container:hover svg {
            fill: var(--star-color);
        }

        #page-title {
            text-align: center;
            margin: 0;
            font-size: 1.5em;
            color: var(--text-color);
        }

        #user-name-container {
            position: absolute;
            right: 0.5em;
            top: 50%;
            transform: translateY(-50%);
            display: flex;
            align-items: center;
            background-color: #444;
            color: #fff;
            padding: 0.5em 1em;
            border-radius: 20px;
            border: 1px solid #555;
        }

        .edit-icon {
            width: 16px;
            height: 16px;
            margin-left: 5px;
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
            font-size: 0.9em;
            margin-right: 5px;
        }

        #top-user-name:hover {
            background-color: #555;
        }

        .user-name-input {
            background-color: #444;
            color: #fff;
            border: 1px solid #555;
            padding: 0.5em 1em;
            border-radius: 20px;
            font-size: 0.9em;
            width: auto;
            min-width: 150px;
        }

        .user-name-input:focus {
            outline: none;
            border-color: #777;
            box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.1);
        }

        /* Add some padding to the body to account for the fixed top bar */
        body {
            padding-top: 60px;
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
        let currentFullImagePath = '';

        let userStarredImages = <?php echo json_encode($userStarredImages); ?>;


        let modal, modalImg, modalTitle, closeBtn, prevBtn, nextBtn, modalStarButton, modalDownloadButton;
        let currentImageIndex = -1;
        let images = [];

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
            } else {
                console.error('Modal element not found in the DOM');
            }
        }

        function updateModal() {
            console.debug("Updating modal, currentImageIndex:", currentImageIndex);
            if (currentImageIndex >= 0) {
                const currentImage = images[currentImageIndex];
                const imageSrc = currentImage.querySelector('img').dataset.fullImage;
                const imageTitle = currentImage.querySelector('.image-title').textContent;
                const isStarred = currentImage.classList.contains('starred');

                modalImg.src = imageSrc;
                modalImg.alt = imageTitle;
                modalTitle.textContent = imageTitle;
                updateStarButton(modalStarButton, isStarred);
                modalDownloadButton.href = imageSrc;
                modalDownloadButton.download = imageSrc.split('/').pop();
                currentFullImagePath = imageSrc.split('/').pop(); // Store only the filename

                // Update comments
                updateModalComments(currentImage);

                // Show navigation buttons
                prevBtn.style.display = 'block';
                nextBtn.style.display = 'block';
            } else {
                // Handle case for images not in the current view (e.g., from footer)
                const imageTitle = currentFullImagePath.split('/').pop();

                modalImg.src = `${baseUrl}${subDir ? '/' + subDir : ''}/${currentFullImagePath}`;
                modalImg.alt = imageTitle;
                modalTitle.textContent = imageTitle;
                updateStarButton(modalStarButton, initialStarredImages.includes(currentFullImagePath));
                modalDownloadButton.href = modalImg.src;
                modalDownloadButton.download = imageTitle;

                // Clear comments for footer images
                const commentContainer = modal.querySelector('.comment-container');
                commentContainer.innerHTML = ''; // Clear existing comments
                const commentPlaceholder = document.createElement('div');
                commentPlaceholder.className = 'comment-placeholder';
                commentPlaceholder.textContent = 'Comment';
                commentPlaceholder.addEventListener('click', () => editModalComment(currentFullImagePath));
                commentContainer.appendChild(commentPlaceholder);

                // Hide navigation buttons for footer images
                prevBtn.style.display = 'none';
                nextBtn.style.display = 'none';
            }
            console.debug("currentFullImagePath set to:", currentFullImagePath);
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
                imageName = imageContainerOrName.replace(/^\//, '');
                imageContainer = document.querySelector(`.image-container[data-image="${imageName}"]`);
                starButton = imageContainer ? imageContainer.querySelector('.star-button') : modalStarButton;
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
            updateStarButton(starButton, newStarredState);
            if (imageContainer) {
                imageContainer.classList.toggle('starred', newStarredState);
            }
            updateStarButton(modalStarButton, newStarredState);
            updateStarredFooters();

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

        function updateCommentUI(container, newComments, isModal = false) {
            let commentContainer = container.querySelector('.comment-container') || createCommentContainer(container);
            commentContainer.innerHTML = '';

            Object.entries(newComments).forEach(([userIp, userData]) => {
                if (userData.comment && userData.comment.trim() !== '') {
                    const commentBox = createCommentBox(userIp, userData);
                    commentContainer.appendChild(commentBox);
                }
            });

            if (!commentContainer.querySelector('.comment-box.current-user')) {
                const commentPlaceholder = createCommentPlaceholder(container, isModal);
                commentContainer.appendChild(commentPlaceholder);
            }
        }

        function createCommentContainer(container) {
            const commentContainer = document.createElement('div');
            commentContainer.className = 'comment-container';
            container.appendChild(commentContainer);
            return commentContainer;
        }

        function createCommentBox(userIp, userData) {
            const commentBox = document.createElement('div');
            commentBox.className = 'comment-box';
            commentBox.dataset.userIp = userIp;
            commentBox.innerHTML = `
            <span class="comment-user">${userData.name || userIp}:</span>
            <span class="comment">${userData.comment}</span>
        `;

            if (userIp === currentUserIp) {
                commentBox.classList.add('current-user');
                commentBox.addEventListener('click', () => editComment(commentBox.closest('.image-container')));
            }

            return commentBox;
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
            input.rows = 3;
            input.style.width = '100%';

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
                        updateSingleComment(imageName, currentUserIp, comment);
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

        function updateSingleComment(imageName, userIp, comment) {
            const container = document.querySelector(`.image-container[data-image="${imageName}"]`);
            const modalContainer = document.getElementById('imageModal');

            if (container) {
                updateCommentUIForContainer(container, userIp, comment, false);
            }

            if (modalContainer.style.display === 'block' && currentFullImagePath === imageName) {
                updateCommentUIForContainer(modalContainer, userIp, comment, true);
            }
        }

        function updateCommentUIForContainer(container, userIp, comment, isModal) {
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
            } else {
                if (!userCommentBox) {
                    userCommentBox = document.createElement('div');
                    userCommentBox.className = 'comment-box current-user';
                    userCommentBox.dataset.userIp = userIp;
                    commentContainer.appendChild(userCommentBox);
                }

                userCommentBox.innerHTML = `
                <span class="comment-user"><?php echo htmlspecialchars($data[$user_ip]['name']); ?>:</span>
                <span class="comment">${comment}</span>
            `;

                userCommentBox.addEventListener('click', function() {
                    editComment(container, isModal);
                });
            }

            const existingPlaceholder = commentContainer.querySelector('.comment-placeholder');
            if (existingPlaceholder) {
                existingPlaceholder.remove();
            }

            if (!commentContainer.querySelector('.comment-box.current-user')) {
                const commentPlaceholder = createCommentPlaceholder(container, isModal);
                commentContainer.appendChild(commentPlaceholder);
            }
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

            minimizeButton.addEventListener('click', () => {
                footer.classList.toggle('minimized');
                minimizeButton.textContent = footer.classList.contains('minimized') ? '▲' : '▼';
            });

            thumbnails.forEach(thumbnail => {
                const imageName = thumbnail.dataset.fullImage.split('/').pop();

                // Add click event to open modal for all users
                thumbnail.addEventListener('click', (event) => {
                    if (event.target === thumbnail || event.target.tagName === 'IMG') {
                        openModal(imageName);
                    }
                });

                if (isCurrentUserFooter) {
                    const unstarButton = thumbnail.querySelector('button[title="Unstar"]');
                    const downloadButton = thumbnail.querySelector('button[title="Download"]');

                    if (unstarButton) {
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
                    // Remove buttons for other users' footers
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
                        copyText += `${imageUrl}\n`;

                        // Find the corresponding image container in the main gallery
                        const imageName = imageUrl.split('/').pop();
                        const imageContainer = document.querySelector(`.image-container[data-image="${imageName}"]`);
                        if (imageContainer) {
                            const comments = imageContainer.querySelectorAll('.comment-box');
                            comments.forEach(comment => {
                                const userName = comment.querySelector('.comment-user').textContent.trim();
                                const commentText = comment.querySelector('.comment').textContent.trim();
                                copyText += `    ${userName} ${commentText}\n`;
                            });
                        }
                        copyText += '\n'; // Add an extra newline between images
                    });

                    navigator.clipboard.writeText(copyText.trim()).then(() => {
                        showToast('Image URLs and comments copied to clipboard');
                    }).catch(err => {
                        console.error('Failed to copy image URLs and comments: ', err);
                        showToast('Failed to copy image URLs and comments');
                    });
                });
            }

        }

        function updateStarredFooters() {
            let starredFootersContainer = document.getElementById('starred-footers-container');
            if (!starredFootersContainer) {
                starredFootersContainer = document.createElement('div');
                starredFootersContainer.id = 'starred-footers-container';
                document.body.appendChild(starredFootersContainer);
            }

            // Update current user's footer
            updateUserFooter(currentUserIp, initialStarredImages, true);

            // Update other users' footers
            if (userStarredImages) {
                Object.keys(userStarredImages).forEach(userIp => {
                    if (userIp !== currentUserIp) {
                        updateUserFooter(userIp, userStarredImages[userIp].starred_images, false);
                    }
                });
            }

            updateFooterSpacerHeight();
        }

        function updateUserFooter(userIp, starredImages, isCurrentUser) {
            const starredFootersContainer = document.getElementById('starred-footers-container');
            let footer = document.querySelector(`.starred-footer[data-user-ip="${userIp}"]`);

            if (starredImages.length > 0) {
                if (!footer) {
                    footer = createStarredFooter(userIp, starredImages, isCurrentUser);
                    starredFootersContainer.appendChild(footer);
                } else {
                    const starredList = footer.querySelector('.starred-list');
                    starredList.innerHTML = '';
                    starredImages.forEach(imageName => {
                        const thumbnail = createThumbnail(imageName, isCurrentUser);
                        starredList.appendChild(thumbnail);
                    });
                }
                addFooterEventListeners(footer);
                footer.style.display = 'grid';
            } else if (footer) {
                footer.style.display = 'none';
            }
        }

        function createStarredFooter(userIp, starredImages, isCurrentUser) {
            const footer = document.createElement('div');
            footer.className = 'starred-footer';
            footer.dataset.userIp = userIp;

            const minimizeButton = document.createElement('button');
            minimizeButton.className = 'minimize-button';
            minimizeButton.title = 'Minimize';
            minimizeButton.textContent = '▼';
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
                starredList.appendChild(thumbnail);
            });
            starredListContainer.appendChild(starredList);
            footer.appendChild(starredListContainer);

            if (isCurrentUser) {
                const footerButtons = document.createElement('div');
                footerButtons.className = 'footer-buttons';
                footerButtons.innerHTML = `
            <button class="footer-button download-all-button" title="Download All">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-download">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="7 10 12 15 17 10"></polyline>
                    <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
            </button>
            <button class="footer-button copy-names-button" title="Copy Names">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-copy">
                    <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                </svg>
            </button>
        `;
                footer.appendChild(footerButtons);
            }

            return footer;
        }

        function createThumbnail(imageName, isCurrentUser) {
            const thumbnail = document.createElement('div');
            thumbnail.className = 'starred-thumbnail';
            thumbnail.dataset.fullImage = `${baseUrl}${subDir ? '/' + subDir : ''}/${imageName}`;

            const img = document.createElement('img');
            img.src = `${relativeThumbsUrl}/${imageName}`;
            img.alt = imageName;

            thumbnail.appendChild(img);

            if (isCurrentUser) {
                const buttonsContainer = document.createElement('div');
                buttonsContainer.className = 'thumbnail-buttons';

                const unstarButton = document.createElement('button');
                unstarButton.title = 'Unstar';
                unstarButton.textContent = '★';
                buttonsContainer.appendChild(unstarButton);

                const downloadButton = document.createElement('button');
                downloadButton.title = 'Download';
                downloadButton.textContent = '⬇️';
                buttonsContainer.appendChild(downloadButton);

                thumbnail.appendChild(buttonsContainer);
            }

            return thumbnail;
        }

        function updateFooterSpacerHeight() {
            const footerHeight = document.getElementById('starred-footers-container').offsetHeight;
            document.getElementById('footer-spacer').style.height = footerHeight + 'px';
        }

        function updateUI(data) {
            const allComments = {};
            Object.keys(data).forEach(userIp => {
                if (data[userIp].comments) {
                    Object.keys(data[userIp].comments).forEach(imageName => {
                        if (!allComments[imageName]) {
                            allComments[imageName] = {};
                        }
                        allComments[imageName][userIp] = {
                            name: data[userIp].name || userIp,
                            comment: data[userIp].comments[imageName]
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
                        updateOtherUserStarredImages(userIp, data[userIp].starred_images);
                    }
                }
                if (data[userIp].name) {
                    updateUserName(userIp, data[userIp].name);
                }
            });

            updateMainGalleryStars(data[currentUserIp].starred_images);
        }

        function updateOtherUserStarredImages(userIp, starredImages) {
            const userFooter = document.querySelector(`.starred-footer[data-user-ip="${userIp}"]`);
            if (userFooter) {
                const starredList = userFooter.querySelector('.starred-list');
                starredList.innerHTML = '';
                starredImages.forEach(imageName => {
                    const thumbnail = createThumbnail(imageName, false);
                    starredList.appendChild(thumbnail);
                });
                userFooter.style.display = starredImages.length > 0 ? 'flex' : 'none';
            }
        }

        function updateUserName(userIp, newName) {
            const userNameElements = document.querySelectorAll(`.user-name[data-user-ip="${userIp}"]`);
            userNameElements.forEach(el => {
                el.textContent = newName;
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
                currentImageIndex = -1; // Indicate that this is a footer image
            } else {
                imageName = containerOrImageName.dataset.image;
                currentImageIndex = images.indexOf(containerOrImageName);
            }

            currentFullImagePath = imageName;
            console.info("CurrentFullImagePath set to:", currentFullImagePath);
            updateModal();
            modal.style.display = 'block';
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
            imageUrls.forEach((url, index) => {
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
                input.rows = 3;
                input.style.width = '100%';

                function saveModalComment() {
                    const newComment = input.value.trim();

                    // Immediately update the UI
                    updateCommentUIForContainer(modal, currentUserIp, newComment, true);

                    // Then update the server and refresh
                    updateComment(currentFullImagePath, newComment);

                    // Refresh the comments for the main gallery image
                    if (currentImageIndex >= 0) {
                        const currentImage = images[currentImageIndex];
                        updateCommentUIForContainer(currentImage, currentUserIp, newComment, false);
                    }
                }

                input.addEventListener('blur', saveModalComment);
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
                    console.warn("Navigation not available for this image.");
                    return;
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

            // Initialize the page
            initializeStarredImages();

            // Add event listeners
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
                    if (e.key === 'ArrowLeft') {
                        navigateModal(-1);
                    } else if (e.key === 'ArrowRight') {
                        navigateModal(1);
                    } else if (e.key === 'Escape') {
                        modal.style.display = 'none';
                    }
                }
            });

            const logo = document.querySelector('#logo-container svg');
            logo.addEventListener('click', function() {
                window.location.href = '/';
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

                if (e.target.tagName === 'IMG') {
                    openModal(container);
                } else if (e.target.classList.contains('star-button')) {
                    toggleStar(container);
                } else if (e.target.closest('.comment-box.current-user') || e.target.closest('.comment-placeholder')) {
                    editComment(container);
                }
            });

            const topBar = document.getElementById('top-bar');
            const starredFootersContainer = document.getElementById('starred-footers-container');
            const maxScrollForShadow = 100; // Maximum scroll position for shadow effect

            function handleScroll() {
                const scrollPosition = window.scrollY;
                const maxScroll = document.documentElement.scrollHeight - window.innerHeight;

                // Top bar shadow
                const topShadowOpacity = Math.min(scrollPosition / maxScrollForShadow, 1);
                topBar.style.boxShadow = `0 20px 10px rgba(0, 0, 0, ${topShadowOpacity * 0.4})`;

                // Footer shadow
                const bottomScrollPosition = maxScroll - scrollPosition;
                const footerShadowOpacity = Math.min(bottomScrollPosition / maxScrollForShadow, 1);
                starredFootersContainer.style.boxShadow = `0 -20px 10px rgba(0, 0, 0, ${footerShadowOpacity * 0.4})`;
            }

            window.addEventListener('scroll', handleScroll);

            // Initial check in case the page is loaded scrolled
            handleScroll();

            // Add this after the existing modal event listeners
            const modalCommentContainer = document.querySelector('#imageModal .comment-container');
            modalCommentContainer.addEventListener('click', function(e) {
                if (e.target.closest('.comment-box.current-user') || e.target.closest('.comment-placeholder')) {
                    editModalComment();
                }
            });

            // Start the update check
            checkForUpdates();

            // Add event listener for minimize buttons
            document.querySelectorAll('.minimize-button').forEach(button => {
                button.addEventListener('click', function() {
                    const footer = this.closest('.starred-footer');
                    footer.classList.toggle('minimized');

                    // Update button text based on minimized state
                    this.textContent = footer.classList.contains('minimized') ? '▲' : '▼';

                    // Prevent the click event from bubbling up to the footer
                    event.stopPropagation();
                });
            });

            // Add event listener for starred footers to maximize when clicked if minimized
            document.querySelectorAll('.starred-footer').forEach(footer => {
                footer.addEventListener('click', function() {
                    if (this.classList.contains('minimized')) {
                        this.classList.remove('minimized');
                        const minimizeButton = this.querySelector('.minimize-button');
                        if (minimizeButton) {
                            minimizeButton.textContent = '▼';
                        }
                    }
                });
            });
        });
    </script>
</head>

<body>
    <div id="top-bar">
        <div id="logo-container">
            <svg version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px"
                viewBox="0 0 153.16 144.09" style="enable-background:new 0 0 153.16 144.09;" xml:space="preserve" width="40" height="40">
                <g>
                    <path d="M153.16,0v21.8H22.6v122.29H0V0H153.16z" />
                </g>
                <g>
                    <path d="M45.6,43.8h107v21.8H68.2V122h62.6v-18H92.4V82h60.2v61.8h-21.8H45.6V43.8z" />
                </g>
            </svg>
        </div>
        <h1 id="page-title"><?php echo htmlspecialchars($title); ?></h1>
        <div id="user-name-container">
            <span id="top-user-name" class="user-name editable" data-user-ip="<?php echo $user_ip; ?>"><?php echo htmlspecialchars($data[$user_ip]['name']); ?></span>
            <svg class="edit-icon" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 24 24" xml:space="preserve">
                <path class="st0" d="M11,4H4C2.9,4,2,4.9,2,6v14c0,1.1,0.9,2,2,2h14c1.1,0,2-0.9,2-2v-7" />
                <path class="st0" d="M18.5,2.5c0.83-0.83,2.17-0.83,3,0s0.83,2.17,0,3L12,15l-4,1l1-4L18.5,2.5z" />
            </svg>
        </div>
    </div>
    <div class="gallery">
        <?php
        if (empty($images)) {
            echo "<p>No images found in the directory.</p>";
        } else {
            foreach ($images as $image) {
                echo generateImageContainer($image, $subDir, $dataDir, $starredImages, $data, $user_ip, $baseUrl, $relativeThumbsUrl);
            }
        }
        ?>
    </div>

    <div id="footer-spacer"></div>

    <div id="imageModal" class="modal">
        <div class="modal-content">
            <span class="close" aria-label="Close modal">&times;</span>
            <span class="prev" aria-label="Previous image">&#10094;</span>
            <div class="modal-image-container">
                <img id="modalImage" alt="Modal image">
            </div>
            <span class="next" aria-label="Next image">&#10095;</span>
            <h3 id="modalTitle"></h3>
            <div class="comment-container">
                <!-- Comments will be dynamically inserted here -->
            </div>
            <div class="button-container">
                <button id="modalStarButton" class="star-button" title="Star" aria-label="Star image">★</button>
                <a id="modalDownloadButton" href="#" download class="download-button" title="Download" aria-label="Download image">⬇</a>
            </div>
        </div>
    </div>

    <div id="starred-footers-container">
        <?php if (!empty($userStarredImages)): ?>
            <?php foreach ($userStarredImages as $ip => $userData): ?>
                <div class="starred-footer" data-user-ip="<?php echo $ip; ?>">
                    <button class="minimize-button" title="Minimize">▼</button>
                    <span class="user-name ellipsis" data-user-ip="<?php echo $ip; ?>"><?php echo htmlspecialchars($userData['name']); ?></span>
                    <div class="starred-list-container">
                        <div class="starred-list">
                            <?php foreach ($userData['starred_images'] as $image):
                                $thumbPath = $relativeThumbsUrl . '/' . $image;
                                $fullImagePath = ($subDir ? '/' . $subDir : '') . '/' . $image;
                            ?>
                                <div class="starred-thumbnail" data-full-image="<?php echo $fullImagePath; ?>">
                                    <img src="<?php echo $thumbPath; ?>" alt="<?php echo $image; ?>">
                                    <?php if ($ip === $user_ip): ?>
                                        <div class="thumbnail-buttons">
                                            <button title="Unstar">★</button>
                                            <button title="Download">⬇️</button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="footer-buttons">
                        <button class="footer-button download-all-button" title="Download All">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-download">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                <polyline points="7 10 12 15 17 10"></polyline>
                                <line x1="12" y1="15" x2="12" y2="3"></line>
                            </svg>
                        </button>
                        <button class="footer-button copy-names-button" title="Copy Names">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-copy">
                                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div id="toast">Image filenames copied to clipboard!</div>
</body>

</html>