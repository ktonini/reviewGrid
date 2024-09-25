# ReviewGrid

ReviewGrid is a simple, PHP-based image gallery with review features. It allows users to view, star, and download images in a responsive grid layout.

## Features

- Responsive grid layout for image display
- Image starring functionality
- Full-size image viewing in a modal
- Download functionality for individual images
- Thumbnail generation for faster loading
- Persistent storage of starred images
- Copy filenames of starred images

## Requirements

- PHP 7.2 or higher
- GD Library for PHP (for thumbnail generation)
- Web server (e.g., Apache, Nginx)

## Installation

1. Clone the repository:
`git clone https://github.com/ktonini/reviewGrid.git`

2. Ensure your web server is configured to serve PHP files.

3. Place your images in the root directory of the project.

4. Make sure the `_data` directory and its subdirectories are writable by the web server:
`chmod -R 755 _data`

5. Access the gallery through your web browser (e.g., `http://localhost/reviewGrid`).

## Usage

- Click on an image to view it in full size.
- Use the star button to mark favorite images.
- Use the download button to save individual images.
- Navigate through images in the full-size view using arrow keys or on-screen arrows.
- Use the "Copy Names" button in the footer to copy the filenames of all starred images.

## Customization

You can customize the gallery by modifying the following in `index.php`:

- `$thumbWidth` and `$thumbHeight`: Change thumbnail dimensions
- CSS styles: Modify the appearance of the gallery

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

<!-- ## License

This project is open source and available under the [MIT License](LICENSE).

## Author

Keith Tonini -->