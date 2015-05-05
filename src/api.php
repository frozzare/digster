<?php

/**
 * Digister - Twig templates for WordPress.
 *
 * @package Digster
 */

use Digster\View;

/**
 * Register composer with template engine.
 *
 * @param string|array $template
 * @param callable $fn
 */

function digster_composer( $template, $fn ) {
    View::composer( $template, $fn );
}

/**
 * Fetch rendered template string.
 *
 * @param string $template
 * @param array $data
 *
 * @return string
 */

function digster_fetch( $template, $data = array() ) {
    return View::fetch( $template, $data );
}

/**
 * Reigster extension with template engine.
 */

function digster_register_extension() {
    View::registerExtension( func_get_args() );
}

/**
 * Render the view.
 *
 * @param string $template
 * @param array $data
 */

function digster_render( $template, $data = array() ) {
    View::render( $template, $data );
}
