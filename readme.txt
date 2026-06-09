=== Woo Product Image Resizer ===
Contributors: godlike
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
WC requires at least: 7.0
Stable tag: 1.1.0
License: GPLv2 or later

Resize WooCommerce product images to a consistent size.

== Description ==

Woo Product Image Resizer is an independent WooCommerce extension that resizes product featured images and gallery images.

Features:

* Resize existing product images in batches.
* Resize existing product images by product category.
* Resize newly assigned product images.
* Choose exact crop, fit within maximum dimensions, or fit on a fixed canvas.
* Set target width and height.
* Set canvas background color.
* Set JPEG/WebP quality.
* Skip images already processed with the current settings.
* Regenerate WordPress attachment metadata after resizing.

The plugin overwrites the media file used by the product image. Back up your uploads before running a large batch on a live shop.

== Installation ==

1. Upload this folder to wp-content/plugins/woo-product-image-resizer.
2. Activate the plugin in WordPress.
3. Go to WooCommerce > Image Resizer.
4. Save your preferred settings.
5. Run the existing product image batch if needed.

== Changelog ==

= 1.1.0 =
Added category-based batch processing for existing product images.

= 1.0.1 =
Improved batch reliability and visible error reporting.

= 1.0.0 =
Initial release.
