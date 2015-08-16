<?php

namespace Digster;

use Digster\Engines\Twig_Engine;

final class Digster {

    /**
     * The view factory instance.
     *
     * @var \Digster\Factory
     */
    private $factory;

    /**
     * The instance of Plugin loader class.
     *
     * @var object
     */
    private static $instance;

    /**
     * Get Plugin boilerplate loader instance.
     *
     * @return object
     */
    public static function instance() {
        if ( ! isset( self::$instance ) ) {
            self::$instance = new self;
            self::$instance->boot();
            self::$instance->setup_actions();
        }

        return self::$instance;
    }

    /**
     * Boot the view factory.
     */
    private function boot() {
        $this->factory = new Factory( new Twig_Engine );
    }

    /**
     * Register composer with template engine.
     *
     * @param array|string $views
     * @param \Closure $callback
     */
    public static function composer( $views, $callback ) {
        self::factory()->composer( $views, $callback );
    }

    /**
     * Get or set configuration value.
     *
     * @param array|string $key
     * @param mixed $value
     *
     * @return mixed
     */
    public static function config( $key, $value = null ) {
        return self::factory()->engine()->config( $key, $value );
    }

    /**
     * Get the view factory instance.
     *
     * @return \Digster\Factory
     */
    public static function factory() {
        return self::instance()->factory;
    }

    /**
     * Fetch rendered view string.
     *
     * @param string $view
     * @param array $data
     *
     * @return string
     */
    public static function fetch( $view, array $data = [] ) {
        return self::factory()->make( $view, $data )->render();
    }

    /**
     * Load Digster extensions.
     */
    public function load_extensions() {
        $extensions = [
            new \Digster\Extensions\Filter_Extensions(),
            new \Digster\Extensions\Function_Extensions(),
            new \Digster\Extensions\Global_Extensions()
        ];

        $extensions = apply_filters( 'digster/extensions', $extensions );

        $this->factory->engine()->register_extensions( $extensions );
    }

    /**
     * Reigster extensions with view engine.
     */
    public static function register_extensions() {
        self::factory()->engine()->register_extensions( func_get_args() );
    }

    /**
     * Render the view.
     *
     * @param string $view
     * @param array $data
     *
     * @return string
     */
    public static function render( $view, array $data = [] ) {
        echo self::factory()->make( $view, $data );
    }

    /**
     * Setup actions.
     */
    private function setup_actions() {
        add_action( 'after_setup_theme', [$this, 'load_extensions'] );
    }

}