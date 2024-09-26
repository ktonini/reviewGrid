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

// Replace the session_start() and user_id generation with:
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

// Add this near the top of the file, after other POST handlers
if (isset($_POST['update_name'])) {
    $new_name = trim($_POST['update_name']);
    if (!empty($new_name)) {
        $data[$user_ip]['name'] = $new_name;
        file_put_contents($usersFile, json_encode($data));
        echo json_encode(['success' => true, 'name' => $new_name]);
    } else {
        $data[$user_ip]['name'] = $user_ip; // Revert to IP address if name is blank
        file_put_contents($usersFile, json_encode($data));
        echo json_encode(['success' => true, 'name' => $user_ip]);
    }
    exit;
}

// Handle starring/unstarring
if (isset($_POST['toggle_star'])) {
    $image = $_POST['toggle_star'];

    $index = array_search($image, $data[$user_ip]['starred_images']);
    if ($index === false) {
        $data[$user_ip]['starred_images'][] = $image;
    } else {
        unset($data[$user_ip]['starred_images'][$index]);
        $data[$user_ip]['starred_images'] = array_values($data[$user_ip]['starred_images']);
    }

    $result = file_put_contents($usersFile, json_encode($data));
    if ($result === false) {
        echo json_encode(['success' => false, 'error' => 'Failed to write to file']);
    } else {
        echo json_encode(['success' => true]);
    }
    exit;
}

// Update the comment handling POST request
if (isset($_POST['add_comment'])) {
    try {
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
        if ($result === false) {
            throw new Exception("Failed to write to file");
        }
        echo json_encode(['success' => true, 'comment' => $comment, 'name' => $data[$user_ip]['name']]);
    } catch (Exception $e) {
        error_log("Error updating comment: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
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

// Replace the starred images loading with:
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

// After loading the $data array, add this:
$userStarredImages = [];
foreach ($data as $ip => $userData) {
    if (!empty($userData['starred_images'])) {
        $userStarredImages[$ip] = $userData;
    }
}

// HTML output
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
            padding: 1.25em;
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
            margin-right: 0.625em;
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
        }

        .comment-box {
            margin-bottom: 0.5em;
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
    </style>
    <script>
        const initialStarredImages = <?php echo json_encode($data[$user_ip]['starred_images']); ?>;
        const currentUserIp = '<?php echo $user_ip; ?>';
    </script>
</head>

<body>
    <script>
        function updateCommentUI(container, newComments, isModal = false) {
            let commentContainer = container.querySelector('.comment-container');
            if (!commentContainer) {
                commentContainer = document.createElement('div');
                commentContainer.className = 'comment-container';
                container.appendChild(commentContainer);
            }
            let commentPlaceholder = commentContainer.querySelector('.comment-placeholder');
            if (!commentPlaceholder) {
                commentPlaceholder = document.createElement('div');
                commentPlaceholder.className = 'comment-placeholder';
                commentPlaceholder.textContent = 'Comment';
                commentContainer.appendChild(commentPlaceholder);
            }

            // Collect existing comments
            const existingComments = {};
            commentContainer.querySelectorAll('.comment-box').forEach(box => {
                const userIp = box.dataset.userIp;
                const userName = box.querySelector('.comment-user').textContent.replace(':', '');
                const comment = box.querySelector('.comment').textContent;
                existingComments[userIp] = {
                    name: userName,
                    comment: comment
                };
            });

            // Merge new comments with existing comments
            const allComments = {
                ...existingComments,
                ...newComments
            };

            // Clear existing comment boxes
            commentContainer.querySelectorAll('.comment-box').forEach(box => box.remove());

            // Add all comments
            if (Object.keys(allComments).length > 0) {
                Object.entries(allComments).forEach(([userIp, commentData]) => {
                    if (userIp && commentData.name && commentData.comment) {
                        let newCommentBox = document.createElement('div');
                        newCommentBox.className = 'comment-box';
                        newCommentBox.dataset.userIp = userIp;
                        if (userIp === '<?php echo $user_ip; ?>') {
                            newCommentBox.classList.add('current-user');
                        }
                        newCommentBox.innerHTML = `
                            <span class="comment-user" data-user-ip="${userIp}">${commentData.name}:</span>
                            <span class="comment">${commentData.comment}</span>
                        `;
                        commentContainer.insertBefore(newCommentBox, commentPlaceholder);
                    }
                });
                commentPlaceholder.style.display = 'none';
            } else {
                commentPlaceholder.style.display = 'block';
            }

            // Add click event listener for editing (only for current user's comment)
            const currentUserComment = commentContainer.querySelector('.comment-box.current-user');
            if (currentUserComment) {
                currentUserComment.addEventListener('click', () => editComment(container, isModal));
            }
            commentPlaceholder.addEventListener('click', () => editComment(container, isModal));
        }

        function editComment(container, isModal = false) {
            const commentContainer = container.querySelector('.comment-container');
            if (!commentContainer) return;

            const currentUserComment = commentContainer.querySelector('.comment-box.current-user');
            const commentPlaceholder = commentContainer.querySelector('.comment-placeholder');
            let commentSpan, currentComment;

            if (currentUserComment) {
                commentSpan = currentUserComment.querySelector('.comment');
                currentComment = commentSpan ? commentSpan.textContent.trim() : '';
            } else {
                currentComment = '';
            }

            // Check if we're already editing
            if (commentContainer.querySelector('textarea')) {
                return; // Exit if we're already editing
            }

            const input = document.createElement('textarea');
            input.value = currentComment;
            input.rows = 3;
            input.style.width = '100%';

            function saveComment() {
                const newComment = input.value.trim();
                const image = container.dataset.image;

                fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `add_comment=1&image=${encodeURIComponent(image)}&comment=${encodeURIComponent(newComment)}`
                    })
                    .then(response => response.json())
                    .then(result => {
                        if (result.success) {
                            if (currentUserComment) {
                                commentSpan.textContent = newComment;
                                currentUserComment.replaceChild(commentSpan, input);
                            } else {
                                updateCommentUI(container, {
                                    '<?php echo $user_ip; ?>': {
                                        name: '<?php echo htmlspecialchars($data[$user_ip]['name']); ?>',
                                        comment: newComment
                                    }
                                }, isModal);
                            }
                            if (!isModal) {
                                updateModal();
                            } else {
                                const galleryContainer = document.querySelector(`.image-container[data-image="${image}"]`);
                                if (galleryContainer) {
                                    updateCommentUI(galleryContainer, {
                                        '<?php echo $user_ip; ?>': {
                                            name: '<?php echo htmlspecialchars($data[$user_ip]['name']); ?>',
                                            comment: newComment
                                        }
                                    });
                                }
                            }
                            showToast('Comment updated successfully!');
                        } else {
                            throw new Error(result.error || 'Unknown error occurred');
                        }
                    })
                    .catch(error => {
                        console.error('Error updating comment:', error);
                        showToast(`Failed to update comment: ${error.message}`);
                    })
                    .finally(() => {
                        if (commentContainer.contains(input)) {
                            if (currentUserComment) {
                                currentUserComment.replaceChild(commentSpan, input);
                            } else {
                                input.remove();
                                commentPlaceholder.style.display = 'block';
                            }
                        }
                    });
            }

            input.addEventListener('blur', saveComment);
            input.addEventListener('keypress', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    this.blur();
                }
            });

            if (currentUserComment && commentSpan) {
                currentUserComment.replaceChild(input, commentSpan);
            } else {
                if (commentPlaceholder) {
                    commentPlaceholder.style.display = 'none';
                }
                commentContainer.insertBefore(input, commentPlaceholder);
            }
            input.focus();
        }
    </script>

    <!-- Rest of your HTML content -->

    <h1><?php echo $title; ?></h1>
    <!-- Add this new div for the user name in the top right -->
    <div id="user-name-container" style="position: fixed; top: 10px; right: 10px; z-index: 1000;">
        <span id="top-user-name" class="user-name editable" data-user-ip="<?php echo $user_ip; ?>"><?php echo htmlspecialchars($data[$user_ip]['name']); ?></span>
    </div>
    <div class="gallery">
        <?php
        if (empty($images)) {
            echo "<p>No images found in the directory.</p>";
        } else {
            foreach ($images as $image):
                $filename = basename($image);
                $thumbPath = ($subDir ? '/' . $subDir : '') . '/_data/thumbs/' . $filename;
                $fullImagePath = ($subDir ? '/' . $subDir : '') . '/' . $filename;
                $isStarred = in_array($filename, $starredImages);
                $title = generateTitle($filename);
        ?>
                <div class="image-container <?php echo $isStarred ? 'starred' : ''; ?>" data-image="<?php echo $filename; ?>">
                    <img src="<?php echo $thumbPath; ?>" alt="<?php echo $title; ?>" data-full-image="<?php echo $fullImagePath; ?>">
                    <div class="image-info">
                        <h3 class="image-title"><?php echo $title; ?></h3>
                        <div class="comment-container">
                            <?php
                            foreach ($data as $ip => $userData) {
                                if (isset($userData['comments'][$filename])) {
                                    $userName = htmlspecialchars($userData['name']);
                                    $comment = htmlspecialchars($userData['comments'][$filename]);
                                    $isCurrentUser = $ip === $user_ip;
                                    echo "<div class='comment-box" . ($isCurrentUser ? " current-user" : "") . "' data-user-ip='$ip'>";
                                    echo "<span class='comment-user' data-user-ip='$ip'>$userName:</span>";
                                    echo "<span class='comment'>$comment</span>";
                                    echo "</div>";
                                }
                            }
                            ?>
                            <div class="comment-placeholder">Comment</div>
                        </div>
                        <div class="button-container">
                            <button class="star-button" title="Star" aria-label="Star image">★</button>
                            <a href="<?php echo $fullImagePath; ?>" download class="download-button" title="Download" aria-label="Download image">⬇</a>
                        </div>
                    </div>
                </div>
        <?php
            endforeach;
        }
        ?>
    </div>

    <!-- Add this spacer div after the gallery -->
    <div id="footer-spacer"></div>

    <div id="imageModal" class="modal">
        <span class="close" aria-label="Close modal">&times;</span>
        <span class="prev" aria-label="Previous image">&#10094;</span>
        <span class="next" aria-label="Next image">&#10095;</span>
        <img class="modal-content" id="modalImage">
        <div class="modal-info">
            <h3 id="modalTitle"></h3>
            <div class="comment-container">
                <div id="modalCommentBox" class="comment-box" style="display: none;">
                    <span id="modalCommentUser" class="comment-user"></span>
                    <span id="modalComment" class="comment"></span>
                </div>
                <div id="modalCommentPlaceholder" class="comment-placeholder">Comment</div>
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

    <script>
        // Determine the base URL automatically
        const imageBaseUrl = (() => {
            const pathArray = window.location.pathname.split('/');
            pathArray.pop(); // Remove the current file name (or last directory) from the path
            return window.location.origin + pathArray.join('/') + '/images/'; // Adjust '/images/' if your image directory is named differently
        })();

        function updateModal() {
            if (typeof currentImageIndex === 'undefined' || !images || !images[currentImageIndex]) {
                console.error('Current image not found');
                return;
            }

            const currentContainer = images[currentImageIndex];
            const img = currentContainer.querySelector('img');
            if (!img) {
                console.error('Image not found in container');
                return;
            }

            modalImg.src = img.dataset.fullImage;
            const titleElement = currentContainer.querySelector('.image-title');
            modalTitle.textContent = titleElement ? titleElement.textContent : '';
            updateModalStarButton(currentContainer);
            modalDownloadButton.href = img.dataset.fullImage;
            modalDownloadButton.download = img.dataset.fullImage.split('/').pop();

            // Update comments in modal
            const modalCommentContainer = document.querySelector('.modal-info');
            if (!modalCommentContainer) {
                console.error('Modal info container not found');
                return;
            }
            const galleryCommentBoxes = currentContainer.querySelectorAll('.comment-box');
            const comments = {};
            galleryCommentBoxes.forEach(box => {
                const userIp = box.dataset.userIp;
                const userNameElement = box.querySelector('.comment-user');
                const commentElement = box.querySelector('.comment');
                if (userIp && userNameElement && commentElement) {
                    const userName = userNameElement.textContent.replace(':', '').trim();
                    const comment = commentElement.textContent.trim();
                    if (userName && comment) {
                        comments[userIp] = {
                            name: userName,
                            comment: comment
                        };
                    }
                }
            });
            updateCommentUI(modalCommentContainer, comments, true);
        }
        const images = Array.from(document.querySelectorAll('.image-container'));

        function showToast(message) {
            const toast = document.getElementById("toast");
            toast.textContent = message;
            toast.className = "show";
            setTimeout(() => {
                toast.className = toast.className.replace("show", "");
            }, 3000);
        }

        let currentImageIndex = 0;
        const modalImg = document.getElementById('modalImage');

        function updateModalStarButton(container) {
            modalStarButton.classList.toggle('starred', container.classList.contains('starred'));
        }

        document.addEventListener('DOMContentLoaded', function() {
            function initializeStarredImages() {
                // Initialize star buttons in the main gallery
                document.querySelectorAll('.image-container').forEach(container => {
                    const starButton = container.querySelector('.star-button');
                    const imageName = container.dataset.image;
                    const isStarred = initialStarredImages.includes(imageName);
                    updateStarButton(starButton, isStarred);
                    container.classList.toggle('starred', isStarred);
                });

                // Initialize footer
                updateStarredFooters();
            }

            function updateStarButton(starButton, isStarred) {
                starButton.classList.toggle('starred', isStarred);
                starButton.innerHTML = isStarred ? '★' : '★';
            }

            // Initialize star buttons and footer
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
            const starredFooters = starredFootersContainer.querySelectorAll('.starred-footer');

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

            let currentImageIndex = 0;
            let currentFullImagePath = ''; // Declare this variable


            function openModal(container) {
                currentImageIndex = Array.from(images).indexOf(container);
                currentFullImagePath = container.dataset.image;
                console.log('Opening modal with image:', currentFullImagePath); // Add this for debugging
                updateModal();
                modal.style.display = 'block';
            }

            function navigateModal(direction) {
                currentImageIndex = (currentImageIndex + direction + images.length) % images.length;
                currentFullImagePath = images[currentImageIndex].dataset.image; // Update the full image path
                updateModal();
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
                toggleStar(images[currentImageIndex]);
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

            function toggleStar(imageContainer) {
                const starButton = imageContainer.querySelector('.star-button');
                const imageName = imageContainer.dataset.image;
                const isCurrentlyStarred = starButton.classList.contains('starred');
                const newStarredState = !isCurrentlyStarred;

                updateStarButton(starButton, newStarredState);
                imageContainer.classList.toggle('starred', newStarredState);

                // Update local state
                const index = initialStarredImages.indexOf(imageName);
                if (newStarredState && index === -1) {
                    initialStarredImages.push(imageName);
                } else if (!newStarredState && index > -1) {
                    initialStarredImages.splice(index, 1);
                }

                // Update the footer lists immediately
                updateStarredFooters();

                // Update the server-side state
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
                        // Revert the star state if there was an error
                        revertStarState(imageContainer, isCurrentlyStarred);
                        showToast('Failed to update star status. Please try again.');
                    });

                // Update modal star button if open
                if (modal.style.display === 'block' && images[currentImageIndex].dataset.image === imageName) {
                    updateStarButton(modalStarButton, newStarredState);
                }
            }

            function updateStarredFooters() {
                const footers = document.querySelectorAll('.starred-footer');
                const currentUserIp = '<?php echo $user_ip; ?>';

                footers.forEach(footer => {
                    if (footer.dataset.userIp === currentUserIp) {
                        const starredList = footer.querySelector('.starred-list');
                        starredList.innerHTML = ''; // Clear existing thumbnails

                        initialStarredImages.forEach(imageName => {
                            const thumbnail = createThumbnail(imageName);
                            starredList.appendChild(thumbnail);
                        });

                        // Update footer visibility
                        footer.style.display = starredList.children.length > 0 ? 'flex' : 'none';
                    }
                });

                updateFooterSpacerHeight();
            }

            function createThumbnail(imageName) {
                const thumbnail = document.createElement('div');
                thumbnail.className = 'starred-thumbnail';
                thumbnail.dataset.fullImage = imageName;
                thumbnail.innerHTML = `
                    <img src="${imageName}" alt="${imageName.split('/').pop()}">
                    <div class="thumbnail-buttons">
                        <button title="Unstar">★</button>
                        <button title="Download">⬇️</button>
                    </div>
                `;
                return thumbnail;
            }

            function revertStarState(imageContainer, originalState) {
                const starButton = imageContainer.querySelector('.star-button');
                const imageName = imageContainer.dataset.image;

                updateStarButton(starButton, originalState);
                imageContainer.classList.toggle('starred', originalState);

                // Update local state
                const index = initialStarredImages.indexOf(imageName);
                if (originalState && index === -1) {
                    initialStarredImages.push(imageName);
                } else if (!originalState && index > -1) {
                    initialStarredImages.splice(index, 1);
                }

                // Update the footer lists
                updateStarredFooters();
            }

            document.body.addEventListener('click', function(e) {
                if (e.target.closest('.download-all-button')) {
                    e.preventDefault();
                    const footer = e.target.closest('.starred-footer');
                    const starredImages = Array.from(footer.querySelectorAll('.starred-thumbnail')).map(thumb => {
                        let imagePath = thumb.dataset.fullImage;
                        console.log('Original image path:', imagePath);

                        // Remove leading slashes
                        imagePath = imagePath.replace(/^\/+/, '');

                        // Construct the full URL
                        const fullUrl = new URL(imagePath, window.location.origin + '/').href;
                        console.log('Full URL:', fullUrl);
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
                    console.log('Attempting to download:', url);
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

            // Call updateStarredFooters on page load
            updateStarredFooters();

            function makeNameEditable(nameSpan) {
                console.log('makeNameEditable called');
                const currentName = nameSpan.textContent;
                const input = document.createElement('input');
                input.type = 'text';
                input.value = currentName;
                input.className = 'user-name-input';

                function saveNameChange() {
                    const newName = input.value.trim();
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
                                updateAllUserNames(result.name);
                                showToast('Name updated successfully!');
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
                            input.remove(); // Remove the input field
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

            // Updated function to update only the current user's name
            function updateAllUserNames(newName) {
                const currentUserIp = '<?php echo $user_ip; ?>';
                const userNameElements = document.querySelectorAll(`.user-name[data-user-ip="${currentUserIp}"]`);
                userNameElements.forEach(el => {
                    el.textContent = newName;
                });

                // Update comment user names
                const commentUserElements = document.querySelectorAll(`.comment-user[data-user-ip="${currentUserIp}"]`);
                commentUserElements.forEach(el => {
                    el.textContent = newName + ':';
                });

                // Update the top user name
                const topUserName = document.getElementById('top-user-name');
                if (topUserName) {
                    topUserName.textContent = newName;
                }

                // Update the footer user name
                const footerUserName = document.querySelector(`.starred-footer[data-user-ip="${currentUserIp}"] .user-name`);
                if (footerUserName) {
                    footerUserName.textContent = newName;
                }

                console.log('Updated all user names to:', newName);
            }

            // Add click event listener to the top user name
            const topUserName = document.getElementById('top-user-name');
            topUserName.addEventListener('click', function() {
                makeNameEditable(this);
            });

            // Add this function to update the footer spacer height
            function updateFooterSpacerHeight() {
                const footerHeight = document.getElementById('starred-footers-container').offsetHeight;
                document.getElementById('footer-spacer').style.height = footerHeight + 'px';
            }

            // Call this function initially and whenever the starred footers are updated
            updateFooterSpacerHeight();

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
                const currentUserIp = '<?php echo $user_ip; ?>';
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

            // Add click event listener for minimize buttons
            document.body.addEventListener('click', function(e) {
                if (e.target.classList.contains('minimize-button')) {
                    const footer = e.target.closest('.starred-footer');
                    toggleFooterMinimize(footer);
                }
            });

            // Minimize other footers on page load
            minimizeOtherFooters();

            // Add event listener for footer thumbnail buttons
            document.body.addEventListener('click', function(e) {
                const thumbnail = e.target.closest('.starred-thumbnail');
                if (!thumbnail) return;

                const starButton = e.target.closest('.star-button');
                const downloadButton = e.target.closest('button[title="Download"]');

                if (starButton) {
                    e.preventDefault();
                    e.stopPropagation();
                    const imageName = thumbnail.dataset.fullImage;
                    const imageContainer = document.querySelector(`.image-container[data-image="${imageName}"]`);
                    if (imageContainer) {
                        toggleStar(imageContainer);
                    } else {
                        // If the image is not in the current view, create a temporary container
                        const tempContainer = document.createElement('div');
                        tempContainer.className = 'image-container';
                        tempContainer.dataset.image = imageName;
                        tempContainer.innerHTML = `
                <div class="image-title">${imageName.split('/').pop()}</div>
                <button class="star-button ${starButton.classList.contains('starred') ? 'starred' : ''}">${starButton.textContent}</button>
            `;
                        toggleStar(tempContainer);
                    }
                } else if (downloadButton) {
                    e.preventDefault();
                    e.stopPropagation();
                    const imageName = thumbnail.dataset.fullImage;
                    downloadImage(imageName);
                } else if (e.target === thumbnail || e.target.tagName === 'IMG') {
                    // Open modal only if the click was directly on the thumbnail or its image
                    const imageName = thumbnail.dataset.fullImage;
                    const imageContainer = document.querySelector(`.image-container[data-image="${imageName}"]`);
                    if (imageContainer) {
                        openModal(imageContainer);
                    } else {
                        // If the image is not in the current view, create a temporary container
                        const tempContainer = document.createElement('div');
                        tempContainer.className = 'image-container';
                        tempContainer.dataset.image = imageName;
                        tempContainer.innerHTML = `<div class="image-title">${imageName.split('/').pop()}</div>`;
                        openModal(tempContainer);
                    }
                }
            });

            function unstarImage(imageName) {
                const imageContainer = document.querySelector(`.image-container[data-image="${imageName}"]`);
                if (imageContainer) {
                    toggleStar(imageContainer);
                } else {
                    // If the image is not in the current view, manually update the state
                    const index = initialStarredImages.indexOf(imageName);
                    if (index > -1) {
                        initialStarredImages.splice(index, 1);

                        // Remove the thumbnail from the footer
                        const footers = document.querySelectorAll('.starred-footer');
                        footers.forEach(footer => {
                            const thumbnail = footer.querySelector(`.starred-thumbnail[data-full-image="${imageName}"]`);
                            if (thumbnail) {
                                thumbnail.remove();
                            }
                        });

                        // Update footer visibility
                        updateFooterVisibility();

                        // Update server
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
                                // If there's an error, add the image back to the starred list
                                initialStarredImages.push(imageName);
                                updateStarredFooters();
                            });
                    }
                }
            }

            // Add this new function to update footer visibility
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

            // Update the event listener for footer clicks
            document.body.addEventListener('click', function(e) {
                const thumbnail = e.target.closest('.starred-thumbnail');
                if (!thumbnail) return;

                const button = e.target.closest('button');
                if (button) {
                    // Handle button clicks as before
                    const imageName = thumbnail.dataset.fullImage;
                    if (button.title === 'Unstar') {
                        unstarImage(imageName);
                    } else if (button.title === 'Download') {
                        downloadImage(imageName);
                    }
                } else {
                    // Open modal for the clicked image
                    const imageName = thumbnail.dataset.fullImage;
                    const imageContainer = document.querySelector(`.image-container[data-image="${imageName}"]`);
                    if (imageContainer) {
                        openModal(imageContainer);
                    } else {
                        // If the image is not in the current view, create a temporary container
                        const tempContainer = document.createElement('div');
                        tempContainer.className = 'image-container';
                        tempContainer.dataset.image = imageName;
                        tempContainer.innerHTML = `<div class="image-title">${imageName.split('/').pop()}</div>`;
                        openModal(tempContainer);
                    }
                }
            });

            function openModal(container) {
                currentImageIndex = Array.from(images).indexOf(container);
                updateModal();
                modal.style.display = 'block';
            }

            function updateModal() {
                const currentImage = images[currentImageIndex];
                const imageSrc = currentFullImagePath || currentImage.dataset.image; // Use fullImagePath or fallback to dataset
                const imageTitle = currentImage.querySelector('.image-title').textContent;
                const isStarred = currentImage.classList.contains('starred');

                const modalImage = document.getElementById('modalImage');
                modalImage.src = imageSrc;
                modalImage.alt = imageTitle; // Add alt text for accessibility

                modalTitle.textContent = imageTitle;
                updateStarButton(modalStarButton, isStarred);

                // Update download button
                const modalDownloadButton = document.getElementById('modalDownloadButton');
                modalDownloadButton.href = imageSrc;
                modalDownloadButton.download = imageSrc.split('/').pop();

                // Update comment if exists
                const commentBox = currentImage.querySelector('.comment-box');
                const modalCommentBox = document.getElementById('modalCommentBox');
                const modalCommentPlaceholder = document.getElementById('modalCommentPlaceholder');

                if (commentBox && commentBox.textContent.trim()) {
                    modalCommentBox.innerHTML = commentBox.innerHTML;
                    modalCommentBox.style.display = 'block';
                    modalCommentPlaceholder.style.display = 'none';
                } else {
                    modalCommentBox.style.display = 'none';
                    modalCommentPlaceholder.style.display = 'block';
                }

                console.log('Modal updated with image:', imageSrc); // Add this for debugging
            }
        });
    </script>
</body>
document.addEventListener('DOMContentLoaded', function() {
function initializeStarredImages() {
// Initialize star buttons in the main gallery
document.querySelectorAll('.image-container').forEach(container => {
const starButton = container.querySelector('.star-button');
const imageName = container.dataset.image;
const isStarred = initialStarredImages.includes(imageName);
updateStarButton(starButton, isStarred);
container.classList.toggle('starred', isStarred);
});

// Initialize footer
updateStarredFooters();
}

function updateStarButton(starButton, isStarred) {
starButton.classList.toggle('starred', isStarred);
starButton.innerHTML = isStarred ? '★' : '☆';
}

// Initialize star buttons and footer
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
const starredFooters = starredFootersContainer.querySelectorAll('.starred-footer');

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

function openModal(container) {
currentImageIndex = images.indexOf(container);
updateModal();
modal.style.display = 'block';
}

function navigateModal(direction) {
currentImageIndex = (currentImageIndex + direction + images.length) % images.length;
updateModal();
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
toggleStar(images[currentImageIndex]);
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

function toggleStar(imageContainer) {
const starButton = imageContainer.querySelector('.star-button');
const imageName = imageContainer.dataset.image;
const isCurrentlyStarred = starButton.classList.contains('starred');
const newStarredState = !isCurrentlyStarred;

updateStarButton(starButton, newStarredState);
imageContainer.classList.toggle('starred', newStarredState);

// Update local state
if (newStarredState) {
if (!initialStarredImages.includes(imageName)) {
initialStarredImages.push(imageName);
}
} else {
const index = initialStarredImages.indexOf(imageName);
if (index > -1) {
initialStarredImages.splice(index, 1);
}
}

// Update the footer lists immediately
updateStarredFooters();

// Update the server-side state
fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
method: 'POST',
headers: {
'Content-Type': 'application/x-www-form-urlencoded',
},
body: `toggle_star=${encodeURIComponent(imageName)}`
})
.then(response => {
if (!response.ok) {
throw new Error(`HTTP error! status: ${response.status}`);
}
return response.json();
})
.then(data => {
if (!data.success) {
throw new Error('Server indicated failure');
}
})
.catch(error => {
console.error('Error:', error);
// Revert the star state if there was an error
revertStarState(imageContainer, isCurrentlyStarred);
showToast('Failed to update star status. Please try again.');
});
}

function updateStarredFooters() {
const footers = document.querySelectorAll('.starred-footer');

footers.forEach(footer => {
if (footer.dataset.userIp === currentUserIp) {
const starredList = footer.querySelector('.starred-list');

// Clear existing thumbnails
starredList.innerHTML = '';

// Add thumbnails for all starred images
initialStarredImages.forEach(imageName => {
const thumbnail = createThumbnail(imageName);
starredList.appendChild(thumbnail);
});

// Update footer visibility
footer.style.display = starredList.children.length > 0 ? 'flex' : 'none';
}
});

// Update footer spacer height
updateFooterSpacerHeight();
}

function createThumbnail(imageName) {
const thumbnail = document.createElement('div');
thumbnail.className = 'starred-thumbnail';
thumbnail.dataset.fullImage = imageName;
thumbnail.innerHTML = `
<img src="${imageName}" alt="${imageName.split('/').pop()}">
<div class="thumbnail-buttons">
    <button class="star-button starred" title="Unstar">★</button>
    <button title="Download">⬇️</button>
</div>
`;
return thumbnail;
}

function revertStarState(imageContainer, originalState) {
const starButton = imageContainer.querySelector('.star-button');
const imageName = imageContainer.dataset.image;

updateStarButton(starButton, originalState);
imageContainer.classList.toggle('starred', originalState);

// Update local state
const index = initialStarredImages.indexOf(imageName);
if (originalState && index === -1) {
initialStarredImages.push(imageName);
} else if (!originalState && index > -1) {
initialStarredImages.splice(index, 1);
}

// Update the footer lists
updateStarredFooters();
}

document.body.addEventListener('click', function(e) {
if (e.target.closest('.download-all-button')) {
e.preventDefault();
const footer = e.target.closest('.starred-footer');
const starredImages = Array.from(footer.querySelectorAll('.starred-thumbnail')).map(thumb => {
let imagePath = thumb.dataset.fullImage;
console.log('Original image path:', imagePath);

// Remove leading slashes
imagePath = imagePath.replace(/^\/+/, '');

// Construct the full URL
const fullUrl = new URL(imagePath, window.location.origin + '/').href;
console.log('Full URL:', fullUrl);
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
console.log('Attempting to download:', url);
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
return `${imageName}${comments.length > 0 ? '\n ' + comments.join('\n ') : ''}`;
});
const infoText = imageInfo.join('\n');

navigator.clipboard.writeText(infoText).then(function() {
showToast('Image filenames and comments copied to clipboard!');
}, function(err) {
console.error('Could not copy text: ', err);
showToast('Failed to copy image filenames and comments.');
});
}

// Call updateStarredFooters on page load
updateStarredFooters();

function makeNameEditable(nameSpan) {
console.log('makeNameEditable called');
const currentName = nameSpan.textContent;
const input = document.createElement('input');
input.type = 'text';
input.value = currentName;
input.className = 'user-name-input';

function saveNameChange() {
const newName = input.value.trim();
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
updateAllUserNames(result.name);
showToast('Name updated successfully!');
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
input.remove(); // Remove the input field
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

// Updated function to update only the current user's name
function updateAllUserNames(newName) {
const currentUserIp = '<?php echo $user_ip; ?>';
const userNameElements = document.querySelectorAll(`.user-name[data-user-ip="${currentUserIp}"]`);
userNameElements.forEach(el => {
el.textContent = newName;
});

// Update comment user names
const commentUserElements = document.querySelectorAll(`.comment-user[data-user-ip="${currentUserIp}"]`);
commentUserElements.forEach(el => {
el.textContent = newName + ':';
});

// Update the top user name
const topUserName = document.getElementById('top-user-name');
if (topUserName) {
topUserName.textContent = newName;
}

// Update the footer user name
const footerUserName = document.querySelector(`.starred-footer[data-user-ip="${currentUserIp}"] .user-name`);
if (footerUserName) {
footerUserName.textContent = newName;
}

console.log('Updated all user names to:', newName);
}

// Add click event listener to the top user name
const topUserName = document.getElementById('top-user-name');
topUserName.addEventListener('click', function() {
makeNameEditable(this);
});

// Add this function to update the footer spacer height
function updateFooterSpacerHeight() {
const footerHeight = document.getElementById('starred-footers-container').offsetHeight;
document.getElementById('footer-spacer').style.height = footerHeight + 'px';
}

// Call this function initially and whenever the starred footers are updated
updateFooterSpacerHeight();

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
const currentUserIp = '<?php echo $user_ip; ?>';
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

// Add click event listener for minimize buttons
document.body.addEventListener('click', function(e) {
if (e.target.classList.contains('minimize-button')) {
const footer = e.target.closest('.starred-footer');
toggleFooterMinimize(footer);
}
});

// Minimize other footers on page load
minimizeOtherFooters();

// Add event listener for footer thumbnail buttons
document.body.addEventListener('click', function(e) {
const button = e.target.closest('.starred-thumbnail button');
if (!button) return;

const thumbnail = button.closest('.starred-thumbnail');
const imageName = thumbnail.dataset.fullImage;

if (button.title === 'Unstar') {
unstarImage(imageName);
} else if (button.title === 'Download') {
downloadImage(imageName);
}
});

function unstarImage(imageName) {
const imageContainer = document.querySelector(`.image-container[data-image="${imageName}"]`);
if (imageContainer) {
toggleStar(imageContainer);
} else {
// If the image is not in the current view, manually update the state
const index = initialStarredImages.indexOf(imageName);
if (index > -1) {
initialStarredImages.splice(index, 1);
updateStarredFooters();
// Update server
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
});
}
}
}

function downloadImage(imageName) {
const link = document.createElement('a');
link.href = imageName;
link.download = imageName.split('/').pop();
document.body.appendChild(link);
link.click();
document.body.removeChild(link);
}
});
</script>
</body>

</html>