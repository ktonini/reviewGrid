<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configuration
$scriptDir = dirname($_SERVER['SCRIPT_FILENAME']) . '/';
$dataDir = $scriptDir . '_data/';
$thumbsDir = $dataDir . 'thumbs/';
$columns = 4;
$thumbWidth = 250;
$thumbHeight = 250;

// Get the directory name for the title
$dirName = basename($scriptDir);
$title = str_replace('_', ' ', $dirName);

// Get the subdirectory path relative to the web root
$subDir = rtrim(str_replace($_SERVER['DOCUMENT_ROOT'], '', $scriptDir), '/');

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
function generateImageContainer($image, $subDir, $dataDir, $starredImages, $data, $user_ip)
{
    $filename = basename($image);
    $thumbPath = ($subDir ? '/' . $subDir : '') . '/_data/thumbs/' . $filename;
    $fullImagePath = ($subDir ? '/' . $subDir : '') . '/' . $filename;
    $isStarred = in_array($filename, $starredImages);
    $title = generateTitle($filename);

    ob_start();
?>
    <div class="image-container <?php echo $isStarred ? 'starred' : ''; ?>" data-image="<?php echo $filename; ?>">
        <img src="<?php echo $thumbPath; ?>" alt="<?php echo $title; ?>" data-full-image="<?php echo $fullImagePath; ?>">
        <div class="image-info">
            <h3 class="image-title"><?php echo $title; ?></h3>
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
        }

        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: var(--text-color);
            background-color: var(--bg-color);
            margin: 0;
            padding-bottom: 3.75em;
            padding-top: 1.25em;
        }

        h1 {
            text-align: center;
            margin-bottom: 1.25em;
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
            transform: translateY(-0.3125em);
            box-shadow: 0 0.375em 0.5em rgba(0, 0, 0, 0.4);
        }

        .image-container.starred {
            background-color: var(--starred-bg);
            box-shadow: 0 0.375em 0.75em rgba(0, 0, 0, 0.5);
            border: 0.25em solid var(--star-color);
        }

        .image-container.starred:hover {
            transform: translateY(-0.3125em);
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
            background-color: rgba(0, 0, 0, 0.9);
        }

        .modal-content {
            margin: auto;
            display: block;
            max-width: 90%;
            max-height: 80%;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        .modal-info {
            position: absolute;
            bottom: 1.25em;
            left: 0;
            right: 0;
            text-align: center;
            color: #fff;
            background-color: rgba(0, 0, 0, 0.7);
            padding: 0.625em;
        }

        .modal-info .star-button,
        .modal-info .download-button {
            font-size: 2em;
            margin-top: 0.625em;
        }

        .close,
        .prev,
        .next {
            position: absolute;
            color: #f1f1f1;
            font-size: 2em;
            font-weight: bold;
            transition: 0.2s;
            cursor: pointer;
        }

        .close {
            top: 0.9375em;
            right: 2.1875em;
        }

        .prev {
            top: 50%;
            left: 2.1875em;
        }

        .next {
            top: 50%;
            right: 2.1875em;
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
            overflow-y: auto;
            box-shadow: 0 -10px 40px rgba(0, 0, 0, 0.4);
        }

        .starred-footer {
            display: flex;
            align-items: center;
            padding: 0.625em;
            background-color: rgba(0, 0, 0, 0.8);
            color: #fff;
            backdrop-filter: blur(1em);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .minimize-button {
            background: none;
            border: none;
            color: var(--button-color);
            cursor: pointer;
            font-size: 0.8em;
            padding: 0.3125em 0.625em;
            transition: transform 0.2s ease;
            margin-right: 0.625em;
        }

        .minimize-button:hover {
            transform: scale(1.2);
        }

        .user-name {
            flex-shrink: 0;
            min-width: 5em;
        }

        .starred-list-container {
            flex-grow: 1;
            overflow-x: auto;
            margin: 0 0.625em;
        }

        .copy-button {
            margin-left: auto;
        }

        .starred-footer.minimized .minimize-button {
            transform: rotate(180deg);
        }

        .starred-footer.minimized {
            padding: 0.3125em 0.625em;
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
            transform: rotate(180deg);
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
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5em;
            background-color: rgba(44, 44, 44, 0.8);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            z-index: 1000;
            box-shadow: 0 20px 10px rgba(0, 0, 0, 0.4);
        }

        #logo-container,
        #user-name-container {
            flex: 0 0 auto;
        }

        #logo-container svg {
            fill: var(--text-color);
            transition: fill 0.3s ease;
        }

        #logo-container:hover svg {
            fill: var(--star-color);
        }

        #page-title {
            flex: 1;
            text-align: center;
            margin: 0;
            font-size: 1.5em;
            color: var(--text-color);
        }

        #top-user-name {
            cursor: pointer;
            transition: color 0.3s ease;
        }

        #top-user-name:hover {
            color: var(--star-color);
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
        let images;
        let modalImg;

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
            let imageName, imageContainer, starButton;

            if (typeof imageContainerOrName === 'string') {
                imageName = imageContainerOrName;
                imageContainer = document.querySelector(`.image-container[data-image="${imageName}"]`);
                starButton = imageContainer ? imageContainer.querySelector('.star-button') : modalStarButton;
            } else {
                imageContainer = imageContainerOrName;
                imageName = imageContainer.dataset.image;
                starButton = imageContainer.querySelector('.star-button');
            }

            const isCurrentlyStarred = initialStarredImages.includes(imageName);
            const newStarredState = !isCurrentlyStarred;

            updateStarredState(imageName, newStarredState);
            updateStarButton(starButton, newStarredState);
            if (imageContainer) {
                imageContainer.classList.toggle('starred', newStarredState);
            }
            updateStarButton(modalStarButton, newStarredState);

            fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `toggle_star=${encodeURIComponent(imageName)}`
                })
                .then(response => response.json())
                .then(data => {
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
                const image = container.dataset.image;

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

            if (modalContainer.style.display === 'block') {
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

        function updateStarredFooters() {
            const currentUserFooter = document.querySelector(`.starred-footer[data-user-ip="${currentUserIp}"]`);
            if (currentUserFooter) {
                const starredList = currentUserFooter.querySelector('.starred-list');
                starredList.innerHTML = '';
                initialStarredImages.forEach(imageName => {
                    const thumbnail = createThumbnail(imageName);
                    starredList.appendChild(thumbnail);
                });
                currentUserFooter.style.display = starredList.children.length > 0 ? 'flex' : 'none';
            }
            updateFooterSpacerHeight();
        }

        function createThumbnail(imageName) {
            const thumbnail = document.createElement('div');
            thumbnail.className = 'starred-thumbnail';
            thumbnail.dataset.fullImage = imageName;

            const img = document.createElement('img');
            img.src = `<?php echo $subDir; ?>/_data/thumbs/${imageName}`;
            img.alt = imageName;

            thumbnail.appendChild(img);

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

            return thumbnail;
        }

        function updateFooterSpacerHeight() {
            const footerHeight = document.getElementById('starred-footers-container').offsetHeight;
            document.getElementById('footer-spacer').style.height = footerHeight + 'px';
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
                    const thumbnail = createThumbnail(imageName);
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

        document.addEventListener('DOMContentLoaded', function() {
            initializeStarredImages();
            const gallery = document.querySelector('.gallery');
            const modal = document.getElementById('imageModal');
            const modalTitle = document.getElementById('modalTitle');
            const modalStarButton = document.getElementById('modalStarButton');
            const modalDownloadButton = document.getElementById('modalDownloadButton');
            const closeBtn = document.querySelector('.close');
            const prevBtn = document.querySelector('.prev');
            const nextBtn = document.querySelector('.next');
            const starredFootersContainer = document.getElementById('starred-footers-container');

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

            let currentImageIndex = 0;
            let currentFullImagePath = '';

            images = Array.from(document.querySelectorAll('.image-container'));

            modalImg = document.getElementById('modalImage');

            function navigateModal(direction) {
                if (currentImageIndex >= 0) {
                    // We're viewing a gallery image
                    currentImageIndex = (currentImageIndex + direction + images.length) % images.length;
                    const newImage = images[currentImageIndex];
                    currentFullImagePath = newImage.dataset.image;
                } else {
                    // We're viewing a footer image, so we can't navigate
                    // You might want to show a message to the user here
                    console.log("Navigation not available for this image.");
                    return;
                }
                updateModal();
            }

            function openModal(containerOrImageName) {
                let imageContainer;
                let imageName;

                if (typeof containerOrImageName === 'string') {
                    // If a string (image name) is passed (from footer)
                    imageName = containerOrImageName;
                    imageContainer = document.querySelector(`.image-container[data-image="${imageName}"]`);
                } else {
                    // If an element (container) is passed (from main gallery)
                    imageContainer = containerOrImageName;
                    imageName = imageContainer.dataset.image;
                }

                if (imageContainer) {
                    currentImageIndex = images.indexOf(imageContainer);
                } else {
                    // If the image is not in the current view, set index to -1
                    currentImageIndex = -1;
                }

                currentFullImagePath = imageName;
                updateModal();
                modal.style.display = 'block';
            }

            function updateModal() {
                if (currentImageIndex >= 0) {
                    const currentImage = images[currentImageIndex];
                    const imageSrc = currentFullImagePath || currentImage.dataset.image;
                    const imageTitle = currentImage.querySelector('.image-title').textContent;
                    const isStarred = currentImage.classList.contains('starred');

                    modalImg.src = imageSrc;
                    modalImg.alt = imageTitle;
                    modalTitle.textContent = imageTitle;
                    updateStarButton(modalStarButton, isStarred);
                    modalDownloadButton.href = imageSrc;
                    modalDownloadButton.download = imageSrc.split('/').pop();

                    // Update comments
                    updateModalComments(currentImage);

                    // Show navigation buttons
                    prevBtn.style.display = 'block';
                    nextBtn.style.display = 'block';
                } else {
                    // Handle case for images not in the current view (e.g., from footer)
                    const imageSrc = currentFullImagePath;
                    const imageTitle = imageSrc.split('/').pop();

                    modalImg.src = imageSrc;
                    modalImg.alt = imageTitle;
                    modalTitle.textContent = imageTitle;
                    updateStarButton(modalStarButton, true); // Assume starred since it's in the footer
                    modalDownloadButton.href = imageSrc;
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

            function editModalComment(imageName) {
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
                    updateComment(imageName, newComment);

                    // Refresh the modal content after saving the comment
                    if (currentImageIndex >= 0) {
                        updateModalComments(images[currentImageIndex]);
                    } else {
                        updateModal(); // For footer images
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

            const topUserName = document.getElementById('top-user-name');
            topUserName.addEventListener('click', function() {
                makeNameEditable(this);
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

            function toggleFooterMinimize(footer) {
                footer.classList.toggle('minimized');
                const minimizeButton = footer.querySelector('.minimize-button');
                if (footer.classList.contains('minimized')) {
                    minimizeButton.textContent = '▼';
                    minimizeButton.title = 'Maximize';
                } else {
                    minimizeButton.textContent = '▲';
                    minimizeButton.title = 'Minimize';
                }
                updateFooterSpacerHeight();
            }

            function minimizeOtherFooters() {
                const footers = document.querySelectorAll('.starred-footer');
                footers.forEach(footer => {
                    if (footer.dataset.userIp !== currentUserIp) {
                        footer.classList.add('minimized');
                        const minimizeButton = footer.querySelector('.minimize-button');
                        minimizeButton.textContent = '▼';
                        minimizeButton.title = 'Maximize';
                    }
                });
                updateFooterSpacerHeight();
            }

            document.body.addEventListener('click', function(e) {
                if (e.target.classList.contains('minimize-button')) {
                    const footer = e.target.closest('.starred-footer');
                    toggleFooterMinimize(footer);
                }
            });

            minimizeOtherFooters();

            document.body.addEventListener('click', function(e) {
                const thumbnail = e.target.closest('.starred-thumbnail');
                if (!thumbnail) return;

                const button = e.target.closest('button');
                if (button) {
                    e.preventDefault();
                    e.stopPropagation();
                    const imageName = thumbnail.dataset.fullImage;
                    if (button.title === 'Unstar') {
                        unstarImage(imageName);
                    } else if (button.title === 'Download') {
                        downloadImage(imageName);
                    }
                } else {
                    const imageName = thumbnail.dataset.fullImage;
                    openModal(imageName);
                }
            });

            function unstarImage(imageName) {
                const imageContainer = document.querySelector(`.image-container[data-image="${imageName}"]`);
                if (imageContainer) {
                    toggleStar(imageContainer);
                } else {
                    const index = initialStarredImages.indexOf(imageName);
                    if (index > -1) {
                        initialStarredImages.splice(index, 1);

                        const footers = document.querySelectorAll('.starred-footer');
                        footers.forEach(footer => {
                            const thumbnail = footer.querySelector(`.starred-thumbnail[data-full-image="${imageName}"]`);
                            if (thumbnail) {
                                thumbnail.remove();
                            }
                        });

                        updateFooterVisibility();

                        fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                },
                                body: `toggle_star=${encodeURIComponent(imageName)}`
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (!data.success) {
                                    throw new Error('Server indicated failure');
                                }
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                showToast('Failed to update star status. Please try again.');
                                initialStarredImages.push(imageName);
                                updateStarredFooters();
                            });
                    }
                }
            }

            function updateFooterVisibility() {
                const footers = document.querySelectorAll('.starred-footer');
                footers.forEach(footer => {
                    const starredList = footer.querySelector('.starred-list');
                    footer.style.display = starredList.children.length > 0 ? 'flex' : 'none';
                });
                updateFooterSpacerHeight();
            }

            function downloadImage(imageName) {
                const link = document.createElement('a');
                link.href = imageName;
                link.download = imageName.split('/').pop();
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }

            document.body.addEventListener('click', function(e) {
                if (e.target.closest('.download-all-button')) {
                    e.preventDefault();
                    const footer = e.target.closest('.starred-footer');
                    const starredImages = Array.from(footer.querySelectorAll('.starred-thumbnail')).map(thumb => {
                        let imagePath = thumb.dataset.fullImage;
                        imagePath = imagePath.replace(/^\/+/, '');
                        const fullUrl = new URL(imagePath, window.location.origin + '/').href;
                        return fullUrl;
                    });
                    downloadAll(starredImages);
                } else if (e.target.closest('.copy-names-button')) {
                    const footer = e.target.closest('.starred-footer');
                    copyImageNames(footer);
                }
            });

            function downloadAll(urls) {
                urls.forEach((url, index) => {
                    setTimeout(() => {
                        fetch(url)
                            .then(response => {
                                if (!response.ok) {
                                    throw new Error(`HTTP error! status: ${response.status}`);
                                }
                                return response.blob();
                            })
                            .then(blob => {
                                const link = document.createElement('a');
                                link.href = URL.createObjectURL(blob);
                                link.download = url.split('/').pop();
                                document.body.appendChild(link);
                                link.click();
                                document.body.removeChild(link);
                                URL.revokeObjectURL(link.href);
                            })
                            .catch(error => console.error('Download failed:', url, error));
                    }, index * 1000);
                });
            }

            function copyImageNames(footer) {
                const starredList = footer.querySelector('.starred-list');
                const thumbnails = starredList.querySelectorAll('.starred-thumbnail');
                const imageInfo = Array.from(thumbnails).map(thumb => {
                    const fullImage = thumb.dataset.fullImage;
                    const imageName = fullImage.split('/').pop();
                    const commentBoxes = document.querySelectorAll(`.image-container[data-image="${imageName}"] .comment-box`);
                    const comments = Array.from(commentBoxes).map(box => {
                        const userName = box.querySelector('.comment-user').textContent.trim().replace(':', '');
                        const comment = box.querySelector('.comment').textContent.trim();
                        return `${userName}: ${comment}`;
                    });
                    return `${imageName}${comments.length > 0 ? '\n    ' + comments.join('\n    ') : ''}`;
                });
                const infoText = imageInfo.join('\n');

                navigator.clipboard.writeText(infoText).then(function() {
                    showToast('Image filenames and comments copied to clipboard!');
                }, function(err) {
                    console.error('Could not copy text: ', err);
                    showToast('Failed to copy image filenames and comments.');
                });
            }

            let lastModified = 0;
            checkForUpdates();
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
        </div>
    </div>
    <div class="gallery">
        <?php
        if (empty($images)) {
            echo "<p>No images found in the directory.</p>";
        } else {
            foreach ($images as $image) {
                echo generateImageContainer($image, $subDir, $dataDir, $starredImages, $data, $user_ip);
            }
        }
        ?>
    </div>

    <div id="footer-spacer"></div>

    <div id="imageModal" class="modal">
        <span class="close" aria-label="Close modal">&times;</span>
        <span class="prev" aria-label="Previous image">&#10094;</span>
        <span class="next" aria-label="Next image">&#10095;</span><img class="modal-content" id="modalImage">
        <div class="modal-info">
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
                    <span class="user-name" data-user-ip="<?php echo $ip; ?>"><?php echo htmlspecialchars($userData['name']); ?></span>
                    <div class="starred-list-container">
                        <div class="starred-list">
                            <?php foreach ($userData['starred_images'] as $image):
                                $thumbPath = ($subDir ? '/' . $subDir : '') . '/_data/thumbs/' . $image;
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