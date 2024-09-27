# ReviewGrid

ReviewGrid is a PHP-based image gallery application that allows users to view, comment on, and star images. It features a unique user identification system based on IP addresses.

## Features

- Image gallery with responsive grid layout
- Modal view for individual images
- Commenting system for images
- Star/unstar functionality for favorite images
- Download option for images
- Responsive design for various screen sizes
- User identification and assignment based on IP address allows for persistent user actions (comments, stars) without login

## Installation

1. Clone the repository:
   ```
   git clone https://github.com/ktonini/reviewGrid.git
   ```
2. Ensure you have PHP installed on your server.
3. Place your images in the `_data/images/` directory.
4. Configure your web server to serve the project directory.

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
- This allows for persistent actions without requiring user accounts