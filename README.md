# WooCommerce Image Resizer

A lightweight WooCommerce extension that resizes product featured images and gallery images to a consistent size.

## Features

- Resize existing product images in batches.
- Filter batch resizing by product category.
- Resize newly assigned product images.
- Choose exact crop, fit within maximum dimensions, or fit on a fixed canvas.
- Set target width and height.
- Set canvas background color.
- Set JPEG, PNG, and WebP quality.
- Skip images already processed with the current settings.
- Regenerate WordPress attachment metadata after resizing.

## Requirements

- WordPress 6.0 or newer.
- WooCommerce 7.0 or newer.
- PHP 7.4 or newer.
- GD extension for canvas mode.

## Installation

1. Upload this folder to `wp-content/plugins/woo-product-image-resizer`.
2. Activate the plugin in WordPress.
3. Go to `WooCommerce > Image Resizer`.
4. Save your preferred settings.
5. Run the existing product image batch if needed.

## Important

The plugin overwrites the media file used by each product image. Back up your uploads before running a large batch on a live shop.

## Development

```bash
php -l woo-product-image-resizer.php
```

## License

GPLv2 or later.
