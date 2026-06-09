<?php
/**
 * Plugin Name: Woo Product Image Resizer
 * Description: Resizes WooCommerce product images to a consistent size, including new product images and batch processing for existing products.
 * Version: 1.1.0
 * Author: Godlike
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 * Text Domain: woo-product-image-resizer
 */

if (!defined('ABSPATH')) {
    exit;
}

final class WPIR_Product_Image_Resizer
{
    private const OPTION = 'wpir_product_image_resizer_settings';
    private const META_HASH = '_wpir_resize_hash';
    private const NONCE_ACTION = 'wpir_batch_resize';

    private static ?self $instance = null;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_ajax_wpir_batch_resize', [$this, 'ajax_batch_resize']);

        add_action('set_post_thumbnail', [$this, 'process_thumbnail_assignment'], 10, 2);
        add_action('added_post_meta', [$this, 'process_product_gallery_meta'], 10, 4);
        add_action('updated_post_meta', [$this, 'process_product_gallery_meta'], 10, 4);
    }

    public function add_admin_menu(): void
    {
        add_submenu_page(
            'woocommerce',
            __('Product Image Resizer', 'woo-product-image-resizer'),
            __('Image Resizer', 'woo-product-image-resizer'),
            'manage_woocommerce',
            'wpir-product-image-resizer',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings(): void
    {
        register_setting('wpir_settings', self::OPTION, [$this, 'sanitize_settings']);
    }

    public function enqueue_admin_assets(string $hook): void
    {
        if ($hook !== 'woocommerce_page_wpir-product-image-resizer') {
            return;
        }

        wp_enqueue_style(
            'wpir-admin',
            plugin_dir_url(__FILE__) . 'assets/admin.css',
            [],
            '1.1.0'
        );
        wp_enqueue_script(
            'wpir-admin',
            plugin_dir_url(__FILE__) . 'assets/admin.js',
            ['jquery'],
            '1.1.0',
            true
        );
        wp_localize_script('wpir-admin', 'wpirAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce(self::NONCE_ACTION),
            'i18n' => [
                'running' => __('Processing images...', 'woo-product-image-resizer'),
                'done' => __('Done.', 'woo-product-image-resizer'),
                'failed' => __('The batch process failed.', 'woo-product-image-resizer'),
            ],
        ]);
    }

    public function render_settings_page(): void
    {
        if (!class_exists('WooCommerce')) {
            echo '<div class="notice notice-error"><p>' . esc_html__('WooCommerce must be active to use this plugin.', 'woo-product-image-resizer') . '</p></div>';
        }

        $settings = $this->settings();
        ?>
        <div class="wrap wpir-wrap">
            <h1><?php esc_html_e('Woo Product Image Resizer', 'woo-product-image-resizer'); ?></h1>

            <form method="post" action="options.php" class="wpir-settings">
                <?php settings_fields('wpir_settings'); ?>
                <table class="form-table" role="presentation">
                    <tbody>
                    <tr>
                        <th scope="row"><?php esc_html_e('New product images', 'woo-product-image-resizer'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[auto_resize]" value="1" <?php checked($settings['auto_resize']); ?>>
                                <?php esc_html_e('Resize images when they are assigned to a product.', 'woo-product-image-resizer'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Target size', 'woo-product-image-resizer'); ?></th>
                        <td>
                            <input type="number" min="100" max="5000" step="1" name="<?php echo esc_attr(self::OPTION); ?>[width]" value="<?php echo esc_attr($settings['width']); ?>"> px
                            &times;
                            <input type="number" min="100" max="5000" step="1" name="<?php echo esc_attr(self::OPTION); ?>[height]" value="<?php echo esc_attr($settings['height']); ?>"> px
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Resize mode', 'woo-product-image-resizer'); ?></th>
                        <td>
                            <select name="<?php echo esc_attr(self::OPTION); ?>[mode]">
                                <option value="pad" <?php selected($settings['mode'], 'pad'); ?>><?php esc_html_e('Fit on canvas with background', 'woo-product-image-resizer'); ?></option>
                                <option value="crop" <?php selected($settings['mode'], 'crop'); ?>><?php esc_html_e('Crop to fill exact size', 'woo-product-image-resizer'); ?></option>
                                <option value="fit" <?php selected($settings['mode'], 'fit'); ?>><?php esc_html_e('Fit inside maximum size', 'woo-product-image-resizer'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Background color', 'woo-product-image-resizer'); ?></th>
                        <td>
                            <input type="text" pattern="#[a-fA-F0-9]{6}" name="<?php echo esc_attr(self::OPTION); ?>[background]" value="<?php echo esc_attr($settings['background']); ?>">
                            <p class="description"><?php esc_html_e('Used by canvas mode. Example: #ffffff.', 'woo-product-image-resizer'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('JPEG quality', 'woo-product-image-resizer'); ?></th>
                        <td>
                            <input type="number" min="40" max="100" step="1" name="<?php echo esc_attr(self::OPTION); ?>[quality]" value="<?php echo esc_attr($settings['quality']); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Skip processed images', 'woo-product-image-resizer'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[skip_processed]" value="1" <?php checked($settings['skip_processed']); ?>>
                                <?php esc_html_e('Do not process images again unless settings change.', 'woo-product-image-resizer'); ?>
                            </label>
                        </td>
                    </tr>
                    </tbody>
                </table>
                <?php submit_button(__('Save settings', 'woo-product-image-resizer')); ?>
            </form>

            <hr>

            <section class="wpir-batch">
                <h2><?php esc_html_e('Existing products', 'woo-product-image-resizer'); ?></h2>
                <p><?php esc_html_e('Process featured images and gallery images for existing WooCommerce products. This overwrites the current media files and regenerates attachment metadata.', 'woo-product-image-resizer'); ?></p>
                <label for="wpir-product-category">
                    <strong><?php esc_html_e('Product category', 'woo-product-image-resizer'); ?></strong>
                </label>
                <?php
                wp_dropdown_categories([
                    'taxonomy' => 'product_cat',
                    'hide_empty' => false,
                    'hierarchical' => true,
                    'orderby' => 'name',
                    'show_option_all' => __('All product categories', 'woo-product-image-resizer'),
                    'name' => 'wpir_product_category',
                    'id' => 'wpir-product-category',
                    'class' => 'wpir-category-select',
                    'value_field' => 'term_id',
                    'selected' => 0,
                ]);
                ?>
                <p class="description"><?php esc_html_e('Choose one category to process a smaller group of existing products.', 'woo-product-image-resizer'); ?></p>
                <p>
                    <button type="button" class="button button-primary" id="wpir-start-batch">
                        <?php esc_html_e('Resize existing product images', 'woo-product-image-resizer'); ?>
                    </button>
                </p>
                <div class="wpir-progress" hidden>
                    <div class="wpir-progress-bar"><span></span></div>
                    <p class="wpir-status"></p>
                </div>
            </section>
        </div>
        <?php
    }

    public function sanitize_settings(array $input): array
    {
        $defaults = $this->defaults();
        $mode = isset($input['mode']) && in_array($input['mode'], ['pad', 'crop', 'fit'], true) ? $input['mode'] : $defaults['mode'];
        $background = isset($input['background']) && preg_match('/^#[a-fA-F0-9]{6}$/', $input['background']) ? strtolower($input['background']) : $defaults['background'];

        return [
            'auto_resize' => !empty($input['auto_resize']),
            'width' => min(5000, max(100, absint($input['width'] ?? $defaults['width']))),
            'height' => min(5000, max(100, absint($input['height'] ?? $defaults['height']))),
            'mode' => $mode,
            'background' => $background,
            'quality' => min(100, max(40, absint($input['quality'] ?? $defaults['quality']))),
            'skip_processed' => !empty($input['skip_processed']),
        ];
    }

    public function process_thumbnail_assignment(int $post_id, int $thumbnail_id): void
    {
        if (!$this->should_auto_process($post_id)) {
            return;
        }

        $this->process_attachment($thumbnail_id);
    }

    public function process_product_gallery_meta(int $meta_id, int $post_id, string $meta_key, $meta_value): void
    {
        if ($meta_key !== '_product_image_gallery' || !$this->should_auto_process($post_id)) {
            return;
        }

        foreach ($this->parse_gallery_ids((string) $meta_value) as $attachment_id) {
            $this->process_attachment($attachment_id);
        }
    }

    public function ajax_batch_resize(): void
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(30);
        }

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Permission denied.', 'woo-product-image-resizer')], 403);
        }

        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        $page = max(1, absint($_POST['page'] ?? 1));
        $category_id = max(0, absint($_POST['categoryId'] ?? 0));
        $per_page = (int) apply_filters('wpir_batch_products_per_request', 2);
        $per_page = max(1, min(10, $per_page));

        if ($category_id > 0 && !term_exists($category_id, 'product_cat')) {
            wp_send_json_error(['message' => __('The selected product category does not exist.', 'woo-product-image-resizer')], 400);
        }

        $query_args = [
            'post_type' => 'product',
            'post_status' => ['publish', 'private', 'draft', 'pending'],
            'fields' => 'ids',
            'posts_per_page' => $per_page,
            'paged' => $page,
            'no_found_rows' => false,
        ];

        if ($category_id > 0) {
            $query_args['tax_query'] = [
                [
                    'taxonomy' => 'product_cat',
                    'field' => 'term_id',
                    'terms' => [$category_id],
                    'include_children' => true,
                ],
            ];
        }

        $query = new WP_Query($query_args);

        $attachment_ids = [];
        foreach ($query->posts as $product_id) {
            $thumbnail_id = get_post_thumbnail_id($product_id);
            if ($thumbnail_id) {
                $attachment_ids[] = (int) $thumbnail_id;
            }

            $gallery = (string) get_post_meta($product_id, '_product_image_gallery', true);
            $attachment_ids = array_merge($attachment_ids, $this->parse_gallery_ids($gallery));
        }

        $attachment_ids = array_values(array_unique(array_filter($attachment_ids)));
        $processed = 0;
        $skipped = 0;
        $errors = [];

        foreach ($attachment_ids as $attachment_id) {
            try {
                $result = $this->process_attachment((int) $attachment_id);
            } catch (Throwable $e) {
                $result = new WP_Error('wpir_unhandled_error', $e->getMessage());
            }

            if (is_wp_error($result)) {
                $errors[] = [
                    'id' => $attachment_id,
                    'message' => $result->get_error_message(),
                ];
            } elseif ($result === 'skipped') {
                $skipped++;
            } else {
                $processed++;
            }
        }

        wp_send_json_success([
            'page' => $page,
            'nextPage' => $page + 1,
            'totalPages' => (int) $query->max_num_pages,
            'categoryId' => $category_id,
            'processed' => $processed,
            'skipped' => $skipped,
            'errors' => $errors,
            'done' => $page >= (int) $query->max_num_pages,
        ]);
    }

    private function should_auto_process(int $post_id): bool
    {
        $settings = $this->settings();

        return $settings['auto_resize'] && get_post_type($post_id) === 'product';
    }

    /**
     * @return true|'skipped'|WP_Error
     */
    private function process_attachment(int $attachment_id)
    {
        if (!$attachment_id || !wp_attachment_is_image($attachment_id)) {
            return new WP_Error('wpir_not_image', __('Attachment is not an image.', 'woo-product-image-resizer'));
        }

        $settings = $this->settings();
        $hash = $this->settings_hash($settings);

        if ($settings['skip_processed'] && get_post_meta($attachment_id, self::META_HASH, true) === $hash) {
            return 'skipped';
        }

        $file = get_attached_file($attachment_id);
        if (!$file || !file_exists($file) || !is_writable($file)) {
            return new WP_Error('wpir_file_unavailable', __('Image file is missing or not writable.', 'woo-product-image-resizer'));
        }

        $mime = get_post_mime_type($attachment_id);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return new WP_Error('wpir_unsupported_mime', __('Only JPEG, PNG and WebP images are supported.', 'woo-product-image-resizer'));
        }

        $result = $settings['mode'] === 'pad'
            ? $this->resize_with_canvas($file, $settings, $mime)
            : $this->resize_with_wp_editor($file, $settings, $settings['mode'] === 'crop');

        if (is_wp_error($result)) {
            return $result;
        }

        update_post_meta($attachment_id, self::META_HASH, $hash);

        require_once ABSPATH . 'wp-admin/includes/image.php';
        $metadata = wp_generate_attachment_metadata($attachment_id, $file);
        if (!is_wp_error($metadata) && !empty($metadata)) {
            wp_update_attachment_metadata($attachment_id, $metadata);
        }

        clean_post_cache($attachment_id);

        return true;
    }

    private function resize_with_wp_editor(string $file, array $settings, bool $crop)
    {
        $editor = wp_get_image_editor($file);
        if (is_wp_error($editor)) {
            return $editor;
        }

        $editor->set_quality((int) $settings['quality']);
        $resized = $editor->resize((int) $settings['width'], (int) $settings['height'], $crop);
        if (is_wp_error($resized)) {
            return $resized;
        }

        $saved = $editor->save($file);

        return is_wp_error($saved) ? $saved : true;
    }

    private function resize_with_canvas(string $file, array $settings, string $mime)
    {
        if (!function_exists('imagecreatetruecolor')) {
            return new WP_Error('wpir_gd_missing', __('Canvas mode requires the GD PHP extension.', 'woo-product-image-resizer'));
        }

        $source = $this->load_gd_image($file, $mime);
        if (!$source) {
            return new WP_Error('wpir_image_load_failed', __('Could not load source image.', 'woo-product-image-resizer'));
        }

        $source_width = imagesx($source);
        $source_height = imagesy($source);
        $target_width = (int) $settings['width'];
        $target_height = (int) $settings['height'];
        $scale = min($target_width / $source_width, $target_height / $source_height);
        $new_width = max(1, (int) round($source_width * $scale));
        $new_height = max(1, (int) round($source_height * $scale));
        $x = (int) floor(($target_width - $new_width) / 2);
        $y = (int) floor(($target_height - $new_height) / 2);

        $canvas = imagecreatetruecolor($target_width, $target_height);
        [$red, $green, $blue] = $this->hex_to_rgb($settings['background']);
        $background = imagecolorallocate($canvas, $red, $green, $blue);
        imagefill($canvas, 0, 0, $background);
        imagecopyresampled($canvas, $source, $x, $y, 0, 0, $new_width, $new_height, $source_width, $source_height);

        $saved = $this->save_gd_image($canvas, $file, $mime, (int) $settings['quality']);
        imagedestroy($source);
        imagedestroy($canvas);

        return $saved ? true : new WP_Error('wpir_image_save_failed', __('Could not save resized image.', 'woo-product-image-resizer'));
    }

    private function load_gd_image(string $file, string $mime)
    {
        if ($mime === 'image/jpeg' && function_exists('imagecreatefromjpeg')) {
            return imagecreatefromjpeg($file);
        }

        if ($mime === 'image/png' && function_exists('imagecreatefrompng')) {
            return imagecreatefrompng($file);
        }

        if ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) {
            return imagecreatefromwebp($file);
        }

        return false;
    }

    private function save_gd_image($image, string $file, string $mime, int $quality): bool
    {
        if ($mime === 'image/jpeg' && function_exists('imagejpeg')) {
            return imagejpeg($image, $file, $quality);
        }

        if ($mime === 'image/png' && function_exists('imagepng')) {
            $png_quality = 9 - (int) round(($quality / 100) * 9);
            return imagepng($image, $file, max(0, min(9, $png_quality)));
        }

        if ($mime === 'image/webp' && function_exists('imagewebp')) {
            return imagewebp($image, $file, $quality);
        }

        return false;
    }

    private function parse_gallery_ids(string $gallery): array
    {
        if ($gallery === '') {
            return [];
        }

        return array_values(array_filter(array_map('absint', explode(',', $gallery))));
    }

    private function hex_to_rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    private function settings_hash(array $settings): string
    {
        unset($settings['auto_resize'], $settings['skip_processed']);

        return md5(wp_json_encode($settings));
    }

    private function settings(): array
    {
        $settings = get_option(self::OPTION, []);

        return wp_parse_args(is_array($settings) ? $settings : [], $this->defaults());
    }

    private function defaults(): array
    {
        return [
            'auto_resize' => true,
            'width' => 1000,
            'height' => 1000,
            'mode' => 'pad',
            'background' => '#ffffff',
            'quality' => 90,
            'skip_processed' => true,
        ];
    }
}

add_action('plugins_loaded', static function (): void {
    WPIR_Product_Image_Resizer::instance();
});
