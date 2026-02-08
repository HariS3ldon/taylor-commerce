<?php
/**
 * Fallback template.
 *
 * @package TaylorMadeShoes
 */

get_header();

if (have_posts()) {
    while (have_posts()) {
        the_post();
        the_title('<h2>', '</h2>');
        the_content();
    }
} else {
    echo '<p>' . esc_html__('Nessun contenuto disponibile.', 'taylor-made-shoes') . '</p>';
}

get_footer();
