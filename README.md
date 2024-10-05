# ReviewGrid

ReviewGrid is a lightweight, single-file PHP application designed for quick and easy image gallery creation. It's built to be ephemeral and portable, allowing users to instantly create an interactive gallery from any folder of images.


## Features

- Minimal setup: Just place the PHP file in any directory with images
- Self-contained: All generated data is stored within a `_data` folder for easy management
- Instant gallery creation: Automatically generates a gallery from images in the directory
- JSON-based data storage: Uses a simple JSON file as a lightweight database
- User identification by IP: Allows for persistent user actions without login
- Interactive features: Commenting and starring functionality
- Responsive design: Adapts to various screen sizes for optimal viewing
- Easy cleanup: Remove the PHP file and `_data` folder to revert to the original image directory

ReviewGrid is perfect for quickly sharing and reviewing sets of images, whether for personal use, client presentations, or collaborative projects. Its self-contained nature means you can easily move, copy, or delete the gallery without complex setup or teardown procedures.

## Installation

1. Clone the repository:
   ```
   git clone https://github.com/ktonini/reviewGrid.git
   ```
2. Ensure you have PHP installed on your server (PHP 7.0 or higher recommended).
3. Place the `index.php` file in the directory containing the images you want to display.
4. Ensure the web server has write permissions for the directory where `index.php` is located.
5. Access the `index.php` file through your web browser.

Note: The script will automatically create a `_data` folder to store thumbnails and user interaction data. This folder and its contents can be safely deleted to reset the gallery state.

## Usage

1. Open the application in a web browser.
2. Browse through the image gallery.
3. Click on an image to open it in a modal view.
4. Use the star button to mark favorite images.
5. Add or edit comments on images.
6. Use the download button to save images locally.

Note: Your actions (comments, stars) are associated with your IP address and will persist across sessions.

## File Structure

- `index.php`: Main application file
- `_data/`: Directory for storing image data
  - `images/`: Contains all gallery images
  - `thumbs/`: Contains thumbnail versions of images
  - `data.json`: Stores image metadata, user interactions, and IP-based user data

## Configuration

The `.gitignore` file is set up to ignore all files except for:
- `.gitignore`
- `index.php`
- `_data/` directory (excluding `thumbs/*` and `starred_images.txt`)

## User Identification

ReviewGrid uses IP addresses to identify users:
- Each unique IP address is treated as a separate user
- This allows for persistent actions without requiring user accounts but naturally comes with a few caveats.
  