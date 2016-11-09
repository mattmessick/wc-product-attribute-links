<?php
/**
 * Plugin Name: Product Attribute Links
 * Description: Creates links to WooCommerce products using product attributes.
 * Version: 1.0.0
 * Author: Matt Messick
 * Author URI: http://mattmessick.com/
 *
 * @package WC_Product_Attribute_Links
 * @author Matt Messick
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Check if WooCommerce is active
 **/
if (! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    return;
}


/**
 * Product Attribute Links
 */
class WC_Product_Attribute_Links
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        add_action('woocommerce_product_option_terms', array(&$this, 'setup'));
        add_action('woocommerce_process_product_meta', array(&$this, 'save'));
        add_action('woocommerce_simple_add_to_cart', array(&$this, 'render'));
    }

    /**
     * Show setup on WooCommerce product attributes tab.
     *
     * @param object $attribute_taxonomy
     *
     * @return void
     */
    public function setup($attribute_taxonomy)
    {
        global $post;

        ?>

            </td>
        </tr>

        <tr>
            <td>
                <strong>Links</strong><?php echo wc_help_tip('Select product(s) you want to link to for this attribute.'); ?>
            </td>
            <td>
                <div class="options_group show_if_simple">
                    <input type="hidden"
                        class="wc-product-search"
                        name="product_attribute_links[<?php echo wc_attribute_taxonomy_name($attribute_taxonomy->attribute_name); ?>]"
                        data-placeholder="<?php esc_attr_e('Search for a product&hellip;'); ?>"
                        data-action="woocommerce_json_search_products"
                        data-multiple="true"
                        data-exclude="<?php echo intval($post->ID); ?>"
                        data-selected="<?php
                                        $attribute_links = get_post_meta($post->ID, '_product_attribute_links', true);
                                        $attribute_taxonomy_name = wc_attribute_taxonomy_name($attribute_taxonomy->attribute_name);
                                        $product_ids = array_filter(array_map('absint', (array) $attribute_links[$attribute_taxonomy_name]));
                                        $json_ids = array();

                                        foreach ($product_ids as $product_id) {
                                            $product = wc_get_product($product_id);
                                            if (is_object($product)) {
                                                $json_ids[$product_id] = wp_kses_post(html_entity_decode($product->get_formatted_name(), ENT_QUOTES, get_bloginfo('charset')));
                                            }
                                        }

                                        echo esc_attr(json_encode($json_ids));
                                    ?>"
                        value="<?php echo implode(',', array_keys($json_ids)); ?>"
                        >
                </div>
            </td>
        </tr>

        <style>
            /* Remove rowspan attr to display properly */
            #product_attributes .wc-metabox table td[rowspan] {
                display: block;
            }
        </style>
        
        <?php
    }

    /**
     * Save metadata when product updates.
     *
     * @param int $product_id
     *
     * @return void
     */
    public function save($product_id)
    {
        if (! isset($_POST['product_attribute_links']) || ! is_array($_POST['product_attribute_links'])) {
            return;
        }

        foreach ($_POST['product_attribute_links'] as $attribute_name => $product_ids) {
            if (empty($product_ids)) {
                continue;
            }

            $product_attribute_links[$attribute_name] = array_filter(array_map('intval', explode(',', $product_ids)));
        }

        if (isset($product_attribute_links)) {
            update_post_meta($product_id, '_product_attribute_links', $product_attribute_links);
        } else {
            delete_post_meta($product_id, '_product_attribute_links');
        }
    }

    /**
     * Render html when viewing single product.
     *
     * @return void
     */
    public function render()
    {
        global $post;

        $attribute_links = get_post_meta($post->ID, '_product_attribute_links', true);

        if (empty($attribute_links)) {
            return;
        }

        foreach ($attribute_links as $attribute_name => $product_ids)
        {
            $products = get_posts(array(
                'post_type'           => 'product',
                'ignore_sticky_posts' => 1,
                'no_found_rows'       => 1,
                'posts_per_page'      => -1,
                'post__in'            => $product_ids,
                'post__not_in'        => array($post->id)
            ));

            $current_product_attributes = get_the_terms($post, $attribute_name);

            if (! empty($products) && ! empty($current_product_attributes) && ! is_wp_error($current_product_attributes)) {
                ?>
                    <table class="wc_product_attribute_links" cellspacing="0">
                        <tbody>
                            <tr>
                                <td class="label">
                                    <label for="<?php echo esc_attr($attribute_name); ?>">
                                        <?php echo wc_attribute_label($attribute_name); ?>
                                    </label>
                                </td>

                                <td>
                                    <select id="<?php echo esc_attr($attribute_name); ?>" onchange="javascript:location.href = this.value;">
                                        <option value="<?php echo esc_attr($post->guid); ?>" selected>
                                            <?php echo esc_html($current_product_attributes[0]->name); ?>
                                        </option>

                                        <?php foreach ($products as $product) : ?>
                                            <?php $product_attributes = get_the_terms($product, $attribute_name); ?>
                                            <?php if (! empty($product_attributes) && ! is_wp_error($product_attributes)) : ?>
                                                <option value="<?php echo esc_attr($product->guid); ?>">
                                                    <?php echo esc_html($product_attributes[0]->name); ?>
                                                </option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                <?php
            }
        }
    }
}

$wc_product_attribute_link = new WC_Product_Attribute_Links();