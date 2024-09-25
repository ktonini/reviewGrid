<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Configuration
$scriptDir = dirname($_SERVER['SCRIPT_FILENAME']) . '/';
$dataDir = $scriptDir . '_data/';
$thumbsDir = $dataDir . 'thumbs/';
$dbFile = $dataDir . 'starred_images.txt';
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

// Generate a unique user ID if not set
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = uniqid();
}

// Handle starring/unstarring
if (isset($_POST['toggle_star'])) {
    $image = $_POST['toggle_star'];
    $db = file_exists($dbFile) ? json_decode(file_get_contents($dbFile), true) : [];
    
    if (!isset($db[$image])) {
        $db[$image] = [];
    }
    
    $index = array_search($_SESSION['user_id'], $db[$image]);
    if ($index === false) {
        $db[$image][] = $_SESSION['user_id'];
    } else {
        unset($db[$image][$index]);
    }
    
    file_put_contents($dbFile, json_encode($db));
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

// Load starred images
$starredImages = file_exists($dbFile) ? json_decode(file_get_contents($dbFile), true) : [];

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
        }
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: var(--text-color);
            background-color: var(--bg-color);
            margin: 0;
            padding: 20px;
            padding-bottom: 60px;
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
        #starred-footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background-color: rgba(0,0,0,0.8);
            color: #fff;
            padding: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            backdrop-filter: blur(10px);
        }
        #starred-list-container {
            flex-grow: 1;
            overflow-x: auto;
            margin-right: 10px;
            height: 100px;
            overflow-y: hidden;
        }
        #starred-list {
            display: inline-flex;
            gap: 10px;
        }
        #copy-button {
            position: sticky;
            right: 10px;
            flex-shrink: 0;
        }
        #starred-footer span {
            margin-right: 10px;
        }
        .starred-thumbnail {
            position: relative;
            width: 100px;
            height: 100px;
            cursor: pointer;
        }
        .starred-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 4px;
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
            $isStarred = isset($starredImages[$filename]) && in_array($_SESSION['user_id'], $starredImages[$filename]);
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

    <div id="starred-footer">
        <div id="starred-list-container">
            <div id="starred-list"></div>
        </div>
        <button id="copy-button" class="footer-button">Copy<br/>Names</button>
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
        const starredFooter = document.getElementById('starred-footer');
        const starredList = document.getElementById('starred-list');
        const copyButton = document.getElementById('copy-button');
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
                    updateStarredFooter();
                }
            } catch (error) {
                console.error('Error toggling star:', error);
            }
        }

        function updateStarredFooter() {
            starredList.innerHTML = '';
            const starredImages = Array.from(document.querySelectorAll('.image-container.starred'));
            starredImages.forEach(container => {
                const img = container.querySelector('img');
                const thumbContainer = document.createElement('div');
                thumbContainer.className = 'starred-thumbnail';
                thumbContainer.imageContainer = container;
                
                const thumbImg = document.createElement('img');
                thumbImg.src = img.src;
                thumbImg.alt = img.alt;
                
                const buttonsContainer = document.createElement('div');
                buttonsContainer.className = 'thumbnail-buttons';
                
                const unstarButton = document.createElement('button');
                unstarButton.textContent = '★';
                unstarButton.title = 'Unstar';
                unstarButton.setAttribute('aria-label', 'Unstar image');
                
                const downloadButton = document.createElement('button');
                downloadButton.textContent = '⬇️';
                downloadButton.title = 'Download';
                downloadButton.setAttribute('aria-label', 'Download image');
                
                buttonsContainer.appendChild(unstarButton);
                buttonsContainer.appendChild(downloadButton);
                
                thumbContainer.appendChild(thumbImg);
                thumbContainer.appendChild(buttonsContainer);
                starredList.appendChild(thumbContainer);
            });
            starredFooter.style.display = starredImages.length > 0 ? 'flex' : 'none';
            copyButton.style.display = starredImages.length > 0 ? 'inline-block' : 'none';
        }

        starredList.addEventListener('click', function(e) {
            const thumbContainer = e.target.closest('.starred-thumbnail');
            if (!thumbContainer) return;

            if (e.target.matches('.thumbnail-buttons button')) {
                e.stopPropagation();
                if (e.target.title === 'Unstar') {
                    toggleStar(thumbContainer.imageContainer);
                } else if (e.target.title === 'Download') {
                    const link = document.createElement('a');
                    link.href = thumbContainer.imageContainer.querySelector('img').dataset.fullImage;
                    link.download = link.href.split('/').pop();
                    link.click();
                }
            } else {
                openModal(thumbContainer.imageContainer);
            }
        });

        function showToast(message) {
            const toast = document.getElementById("toast");
            toast.textContent = message;
            toast.className = "show";
            setTimeout(() => { toast.className = toast.className.replace("show", ""); }, 3000);
        }

        copyButton.addEventListener('click', function() {
            const starredImages = Array.from(document.querySelectorAll('.image-container.starred'));
            const imageNames = starredImages.map(container => container.dataset.image);
            const namesText = imageNames.join('\n');
            
            navigator.clipboard.writeText(namesText).then(function() {
                showToast('Image filenames copied to clipboard!');
            }, function(err) {
                console.error('Could not copy text: ', err);
                showToast('Failed to copy image filenames.');
            });
        });

        // Initial update of starred footer
        updateStarredFooter();
    });
    </script>
</body>
</html>