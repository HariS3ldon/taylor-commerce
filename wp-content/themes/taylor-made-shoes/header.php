<?php
/**
 * Header template.
 *
 * @package TaylorMadeShoes
 */
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<div class="site">
    <header>
        <h1><a href="<?php echo esc_url(home_url('/')); ?>"><?php bloginfo('name'); ?></a></h1>
        <?php
        wp_nav_menu(
            [
                'theme_location' => 'primary',
                'fallback_cb'    => false,
            ]
        );
        ?>
    </header>
    <main>
