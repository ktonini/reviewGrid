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
        echo json_encode(['success' => false, 'error' => 'Name cannot be empty']);
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
    
    file_put_contents($usersFile, json_encode($data));
    exit;
}

// Get all images in the script directory
$images = glob($scriptDir . '*.{jpg,jpeg,png,gif}', GLOB_BRACE);

// Remove the script itself from the images array
$images = array_filter($images, function($key) {
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
function createThumbnail($source, $destination, $width, $height) {
    if (!function_exists('imagecreatetruecolor')) {
        die("GD Library is not enabled.");
    }
    
    list($w, $h) = getimagesize($source);
    $ratio = max($width/$w, $height/$h);
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
function generateTitle($filename) {
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
            --star-color: #ffd700;
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
            padding: 20px;
            padding-bottom: 60px;
            padding-top: 20px;
        }
        h1 {
            text-align: center;
            margin-bottom: 20px;
        }
        .gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            padding: 20px;
        }
        .image-container {
            background-color: var(--card-bg);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
            transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease, border 0.2s ease;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            border: 3px solid transparent;
        }
        .image-container:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 8px rgba(0, 0, 0, 0.4);
        }
        .image-container.starred {
            background-color: var(--starred-bg);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.5);
            border: 3px solid #ffffff;
        }
        .image-container.starred:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.6);
        }
        .image-container img {
            width: 100%;
            height: auto;
            display: block;
            cursor: zoom-in;
        }
        .image-info {
            padding: 15px;
            display: flex;
            flex-direction: column;
            align-items: center;
            flex-grow: 1;
        }
        .image-title {
            margin: 0 0 10px 0;
            font-size: 16px;
            font-weight: bold;
            color: var(--text-color);
            text-align: center;
        }
        .button-container {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 10px;
        }
        .star-button, .download-button {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--button-color);
            transition: color 0.2s ease, transform 0.2s ease;
        }
        .star-button:hover, .download-button:hover {
            transform: scale(1.2);
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
            background-color: rgba(0,0,0,0.9);
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
            bottom: 20px;
            left: 0;
            right: 0;
            text-align: center;
            color: #fff;
            background-color: rgba(0,0,0,0.7);
            padding: 10px;
        }
        .modal-info .star-button, .modal-info .download-button {
            font-size: 32px;
            margin-top: 10px;
        }
        .close, .prev, .next {
            position: absolute;
            color: #f1f1f1;
            font-size: 40px;
            font-weight: bold;
            transition: 0.2s;
            cursor: pointer;
        }
        .close {
            top: 15px;
            right: 35px;
        }
        .prev {
            top: 50%;
            left: 35px;
        }
        .next {
            top: 50%;
            right: 35px;
        }
        .close:hover, .close:focus,
        .prev:hover, .prev:focus,
        .next:hover, .next:focus {
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
            max-height: 50vh; /* Limit the height to half of the viewport */
            overflow-y: auto;
        }
        .starred-footer {
            background-color: rgba(0,0,0,0.8);
            color: #fff;
            padding: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between; /* This will space out the child elements */
            backdrop-filter: blur(10px);
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        .starred-footer .user-name {
            min-width: 100px;
            font-weight: bold;
            margin-right: 10px;
            padding: 5px;
            border-radius: 4px;
            transition: background-color 0.3s;
            position: relative;
        }
        .starred-footer .user-name.editable {
            cursor: pointer;
        }
        .starred-footer .user-name.editable:hover::after {
            content: '✎';
            right: -20px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 0.8em;
            opacity: 0.7;
            padding-left: 5px;
        }
        .starred-list-container {
            flex-grow: 1;
            overflow-x: auto;
            margin: 0 10px;
            border-radius: 4px;
            height: 80px;
        }
        .starred-list {
            display: inline-flex;
            gap: 10px;
        }
        .starred-thumbnail {
            position: relative;
            width: 80px;
            height: 80px;
            cursor: pointer;
            margin: 2px;
        }
        .starred-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 10px;
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
            gap: 4px;
            margin: 4px;
        }
        .starred-thumbnail:hover .thumbnail-buttons {
            opacity: 1;
        }
        .thumbnail-buttons button {
            border: none;
            color: #fff;
            font-size: 16px;
            cursor: pointer;
            padding: 2px;
            margin: 0 2px;
            background-color: rgba(0, 0, 0, 0.5);
            width: 100%;
            height: 100%;
            border-radius: 4px;
            backdrop-filter: blur(5px);
        }
        .copy-button {
            background: none;
            border: none;
            color: var(--button-color);
            cursor: pointer;
            transition: color 0.2s ease, transform 0.2s ease;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .copy-button:hover {
            transform: scale(1.2);
        }
        .copy-button svg {
            width: 24px;
            height: 24px;
        }
        body {
            padding-bottom: 60px;
        }
        #toast {
            visibility: hidden;
            min-width: 250px;
            margin-left: -125px;
            background-color: #333;
            color: #fff;
            text-align: center;
            border-radius: 2px;
            padding: 16px;
            position: fixed;
            z-index: 1001;
            left: 50%;
            bottom: 30px;
            font-size: 17px;
        }
        #toast.show {
            visibility: visible;
            -webkit-animation: fadein 0.5s, fadeout 0.5s 2.5s;
            animation: fadein 0.5s, fadeout 0.5s 2.5s;
        }
        @-webkit-keyframes fadein {
            from {bottom: 0; opacity: 0;} 
            to {bottom: 30px; opacity: 1;}
        }
        @keyframes fadein {
            from {bottom: 0; opacity: 0;}
            to {bottom: 30px; opacity: 1;}
        }
        @-webkit-keyframes fadeout {
            from {bottom: 30px; opacity: 1;} 
            to {bottom: 0; opacity: 0;}
        }
        @keyframes fadeout {
            from {bottom: 30px; opacity: 1;}
            to {bottom: 0; opacity: 0;}
        }
        #nameModal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.9);
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
            padding: 10px;
            margin-bottom: 10px;
        }
        .footer-button {
            background-color: var(--copy-button-bg);
            border: none;
            color: white;
            padding: 10px 20px;
            text-align: center;
            text-decoration: none;
            display: inline-block;
            font-size: 2em;
            margin: 4px 2px;
            cursor: pointer;
            border-radius: 4px;
        }
        .footer-button:hover {
            background-color: var(--copy-button-hover);
        }
        .user-name-input {
            background-color: var(--bg-color);
            color: var(--text-color);
            border: 1px solid var(--text-color);
            padding: 5px;
            border-radius: 4px;
            font-size: 1em;
            width: 200px;
        }
        /* Scrollbar styles */
        /* For Webkit browsers (Chrome, Safari) */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--scrollbar-bg);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--scrollbar-thumb);
            border-radius: 4px;
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
    </style>
</head>
<body>
    <h1><?php echo $title; ?></h1>
    <div class="gallery">
    <?php 
    if (empty($images)) {
        echo "<p>No images found in the directory.</p>";
    } else {
        foreach ($images as $image): 
            $filename = basename($image);
            $isStarred = in_array($filename, $starredImages);
            $thumbPath = ($subDir ? '/' . $subDir : '') . '/_data/thumbs/' . $filename;
            $fullImagePath = ($subDir ? '/' . $subDir : '') . '/' . $filename;
            $title = generateTitle($filename);
    ?>
        <div class="image-container <?php echo $isStarred ? 'starred' : ''; ?>" data-image="<?php echo $filename; ?>">
            <img src="<?php echo $thumbPath; ?>" alt="<?php echo $title; ?>" loading="lazy" data-full-image="<?php echo $fullImagePath; ?>">
            <div class="image-info">
                <h3 class="image-title"><?php echo $title; ?></h3>
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
                <span class="user-name"><?php echo htmlspecialchars($userData['name']); ?></span>
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
                <button class="copy-button" title="Copy Names">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-copy"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                </button>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div id="toast">Image filenames copied to clipboard!</div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const gallery = document.querySelector('.gallery');
        const modal = document.getElementById('imageModal');
        const modalImg = document.getElementById('modalImage');
        const modalTitle = document.getElementById('modalTitle');
        const modalStarButton = document.getElementById('modalStarButton');
        const modalDownloadButton = document.getElementById('modalDownloadButton');
        const closeBtn = document.querySelector('.close');
        const prevBtn = document.querySelector('.prev');
        const nextBtn = document.querySelector('.next');
        const starredFootersContainer = document.getElementById('starred-footers-container');
        const starredFooters = starredFootersContainer.querySelectorAll('.starred-footer');
        let currentImageIndex = 0;
        const images = Array.from(document.querySelectorAll('.image-container'));

        gallery.addEventListener('click', function(e) {
            const container = e.target.closest('.image-container');
            if (!container) return;

            if (e.target.tagName === 'IMG') {
                openModal(container);
            } else if (e.target.classList.contains('star-button')) {
                toggleStar(container);
            }
        });

        function openModal(container) {
            currentImageIndex = images.indexOf(container);
            updateModal();
            modal.style.display = 'block';
        }

        function updateModal() {
            const currentContainer = images[currentImageIndex];
            const img = currentContainer.querySelector('img');
            modalImg.src = img.dataset.fullImage;
            modalTitle.textContent = currentContainer.querySelector('.image-title').textContent;
            updateModalStarButton(currentContainer);
            modalDownloadButton.href = img.dataset.fullImage;
            modalDownloadButton.download = img.dataset.fullImage.split('/').pop();
        }

        function updateModalStarButton(container) {
            modalStarButton.classList.toggle('starred', container.classList.contains('starred'));
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

        async function toggleStar(container) {
            const image = container.dataset.image;
            try {
                const response = await fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `toggle_star=${encodeURIComponent(image)}`
                });
                if (response.ok) {
                    container.classList.toggle('starred');
                    if (modal.style.display === 'block' && images[currentImageIndex] === container) {
                        updateModalStarButton(container);
                    }
                    updateStarredFooters();
                }
            } catch (error) {
                console.error('Error toggling star:', error);
            }
        }

        function updateStarredFooters() {
            const currentUserIp = '<?php echo $user_ip; ?>';
            const starredFootersContainer = document.getElementById('starred-footers-container');
            const starredFooters = starredFootersContainer.querySelectorAll('.starred-footer');
            const starredImages = Array.from(document.querySelectorAll('.image-container.starred'));

            starredFooters.forEach(footer => {
                const userIp = footer.dataset.userIp;
                const starredList = footer.querySelector('.starred-list');
                const existingThumbnails = Array.from(starredList.querySelectorAll('.starred-thumbnail'));

                if (userIp === currentUserIp) {
                    // Update current user's footer
                    existingThumbnails.forEach(thumb => {
                        const matchingContainer = starredImages.find(container => 
                            container.querySelector('img').dataset.fullImage === thumb.dataset.fullImage
                        );
                        if (!matchingContainer) {
                            thumb.remove();
                        }
                    });

                    starredImages.forEach(container => {
                        const img = container.querySelector('img');
                        const existingThumb = starredList.querySelector(`.starred-thumbnail[data-full-image="${img.dataset.fullImage}"]`);
                        if (!existingThumb) {
                            const newThumb = createStarredThumbnail(img.src, img.dataset.fullImage, true);
                            starredList.appendChild(newThumb);
                        }
                    });
                }

                // Show/hide footer based on whether there are any starred images
                footer.style.display = starredList.children.length > 0 ? 'flex' : 'none';
            });

            // Add a new footer for the current user if it doesn't exist
            if (!starredFootersContainer.querySelector(`.starred-footer[data-user-ip="${currentUserIp}"]`) && starredImages.length > 0) {
                const newFooter = createStarredFooter(currentUserIp, '<?php echo htmlspecialchars($data[$user_ip]['name']); ?>');
                starredFootersContainer.appendChild(newFooter);
                updateStarredFooters(); // Recursive call to update the newly added footer
            }

            // Add this line at the end of the function
            updateFooterSpacerHeight();

            const currentUserFooter = document.querySelector(`.starred-footer[data-user-ip="<?php echo $user_ip; ?>"]`);
            if (currentUserFooter) {
                const nameSpan = currentUserFooter.querySelector('.user-name');
                if (nameSpan) {
                    console.log('Adding click event listener to:', nameSpan);
                    nameSpan.classList.add('editable');
                    nameSpan.addEventListener('click', function() {
                        console.log('Name clicked');
                        makeNameEditable(this);
                    });
                } else {
                    console.log('Name span not found');
                }
            } else {
                console.log('Current user footer not found');
            }
        }

        function createStarredFooter(userIp, userName) {
            const footer = document.createElement('div');
            footer.className = 'starred-footer';
            footer.dataset.userIp = userIp;
            footer.innerHTML = `
                <span class="user-name">${userName}</span>
                <div class="starred-list-container">
                    <div class="starred-list"></div>
                </div>
                <button class="copy-button" title="Copy Names">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-copy"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                </button>
            `;
            return footer;
        }

        function createStarredThumbnail(src, fullImagePath, isCurrentUser) {
            const thumbContainer = document.createElement('div');
            thumbContainer.className = 'starred-thumbnail';
            thumbContainer.dataset.fullImage = fullImagePath;

            const thumbImg = document.createElement('img');
            thumbImg.src = src;
            thumbImg.alt = fullImagePath.split('/').pop();

            thumbContainer.appendChild(thumbImg);

            if (isCurrentUser) {
                const buttonsContainer = document.createElement('div');
                buttonsContainer.className = 'thumbnail-buttons';
                buttonsContainer.innerHTML = `
                    <button title="Unstar">★</button>
                    <button title="Download">⬇️</button>
                `;
                thumbContainer.appendChild(buttonsContainer);
            }

            return thumbContainer;
        }

        document.body.addEventListener('click', function(e) {
            const thumbContainer = e.target.closest('.starred-thumbnail');
            if (!thumbContainer) return;

            if (e.target.matches('.thumbnail-buttons button')) {
                e.stopPropagation();
                if (e.target.title === 'Unstar') {
                    const matchingContainer = Array.from(document.querySelectorAll('.image-container')).find(container => 
                        container.querySelector('img').dataset.fullImage === thumbContainer.dataset.fullImage
                    );
                    if (matchingContainer) {
                        toggleStar(matchingContainer);
                    }
                } else if (e.target.title === 'Download') {
                    const link = document.createElement('a');
                    link.href = thumbContainer.dataset.fullImage;
                    link.download = link.href.split('/').pop();
                    link.click();
                }
            } else {
                const matchingContainer = Array.from(document.querySelectorAll('.image-container')).find(container => 
                    container.querySelector('img').dataset.fullImage === thumbContainer.dataset.fullImage
                );
                if (matchingContainer) {
                    openModal(matchingContainer);
                }
            }
        });

        function showToast(message) {
            const toast = document.getElementById("toast");
            toast.textContent = message;
            toast.className = "show";
            setTimeout(() => { toast.className = toast.className.replace("show", ""); }, 3000);
        }

        document.body.addEventListener('click', function(e) {
            if (e.target.closest('.copy-button')) {
                const copyButton = e.target.closest('.copy-button');
                const footer = copyButton.closest('.starred-footer');
                const starredList = footer.querySelector('.starred-list');
                const thumbnails = starredList.querySelectorAll('.starred-thumbnail');
                const imageNames = Array.from(thumbnails).map(thumb => thumb.dataset.fullImage.split('/').pop());
                const namesText = imageNames.join('\n');
                
                navigator.clipboard.writeText(namesText).then(function() {
                    showToast('Image filenames copied to clipboard!');
                    const originalContent = copyButton.innerHTML;
                    copyButton.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-check"><polyline points="20 6 9 17 4 12"></polyline></svg>';
                    setTimeout(() => {
                        copyButton.innerHTML = originalContent;
                    }, 2000);
                }, function(err) {
                    console.error('Could not copy text: ', err);
                    showToast('Failed to copy image filenames.');
                });
            }
        });

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
                if (newName && newName !== currentName) {
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
                            nameSpan.textContent = result.name;
                            showToast('Name updated successfully!');
                        } else {
                            showToast(result.error || 'Failed to update name.');
                            nameSpan.textContent = currentName;
                        }
                    })
                    .catch(error => {
                        console.error('Error updating name:', error);
                        showToast('Failed to update name.');
                        nameSpan.textContent = currentName;
                    });
                } else {
                    nameSpan.textContent = currentName;
                }
                nameSpan.style.display = '';
                input.remove(); // Remove the input field
            }

            input.addEventListener('blur', saveNameChange);
            input.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    this.blur();
                }
            });

            nameSpan.style.display = 'none';
            nameSpan.parentNode.insertBefore(input, nameSpan);
            input.focus();
        }

        // Add this function to update the footer spacer height
        function updateFooterSpacerHeight() {
            const footerHeight = document.getElementById('starred-footers-container').offsetHeight;
            document.getElementById('footer-spacer').style.height = footerHeight + 'px';
        }

        // Call this function initially and whenever the starred footers are updated
        updateFooterSpacerHeight();
    });
    </script>
</body>
</html>