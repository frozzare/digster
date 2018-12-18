<?php

namespace Frozzare\Digster;

use Closure;
use Frozzare\Digster\Engines\Engine;
use Frozzare\Digster\Finder;
use Frozzare\Tank\Container;

class Factory {

	/**
	 * The engine in use.
	 *
	 * @var \Frozzare\Digster\Engines\Engine
	 */
	protected $engine;

	/**
	 * Factory container.
	 *
	 * @var \Frozzare\Tank\Container
	 */
	protected $container;

	/**
	 * The view composers.
	 *
	 * @var array
	 */
	protected $composers = [
		'*' => []
	];

	/**
	 * Shared data for the views.
	 *
	 * @var array
	 */
	protected $shared = [];

	/**
	 * The constructor.
	 */
	public function __construct() {
		$this->container = new Container;
	}

	/**
	 * Set engine.
	 *
	 * @param \Frozzare\Digster\Engine $engine
	 */
	public function setEngine(Engine $engine) {
		$this->engine = $engine;
	}

	/**
	 * Add shared data to the environment.
	 *
	 * @param  array|string $key
	 * @param  mixed        $value
	 *
	 * @return $this
	 */
	public function share( $key, $value = null ) {
		if ( ! is_array( $key ) ) {
			$this->shared[$key] = $value;
			return $this;
		}

		foreach ( $key as $innerKey => $innerValue ) {
			$this->share( $innerKey, $innerValue );
		}

		return $this;
	}

	/**
	 * Register preprocess with views.
	 *
	 * @param  array|string $views
	 * @param  \Closure     $callback
	 *
	 * @return $this
	 */
	public function composer( $views, $callback ) {
		foreach ( (array) $views as $view ) {
			if ( $callback instanceof Closure === false ) {
				$callback = function () use ( $callback ) {
					return $callback;
				};
			}

			if ( $view === '*' ) {
				$this->composers['*'][] = $callback;
				continue;
			}

			$view = $this->view( $view );

			if ( ! isset( $this->composers[$view] ) ) {
				$this->composers[$view] = [];
			}

			$this->composers[$view][] = $callback;
		}

		return $this;
	}

	/**
	 * Get the view engine.
	 *
	 * @return \Frozzare\Digster\Engine
	 */
	public function engine() {
		return $this->engine;
	}

	/**
	 * Determine if a given view exists.
	 *
	 * @param  string $view
	 *
	 * @return bool
	 */
	public function exists( $view ) {
		return $this->engine()->view_exists( $this->view( $view ) );
	}

	/**
	 * Add extension to the view string if it don't exists.
	 *
	 * @param  string $view
	 *
	 * @return string
	 */
	protected function extension( $view ) {
		$extensions = $this->extensions();

		// Return if a valid extension exists in the template string.
		$ext_reg = '/(' . implode( '|', $extensions ) . ')+$/';
		if ( preg_match( $ext_reg, $view ) ) {
			return $template;
		}

		// Add extension to template string if it don't exists.
		return substr( $view, -strlen( $extensions[0] ) ) === $extensions[0]
			? $view : $view . $extensions[0];
	}

	/**
	 * Get the extensions from the engine.
	 *
	 * @return array
	 */
	protected function extensions() {
		return $this->engine->extensions();
	}

	/**
	 * Create view data.
	 *
	 * @param  mixed $data
	 *
	 * @return array
	 */
	public function create_data( $data = [] ) {
		if ( is_object( $data ) && method_exists( $data, 'to_array' )  ) {
			return $data->to_array();
		}

		return is_array( $data ) ? $data : [];
	}

	/**
	 * Gather the data that should be used when render.
	 *
	 * @param  \Frozzare\Digster\View $view
	 *
	 * @return array
	 */
	public function gather_data( View $view ) {
		$data = [];

		if ( $this->engine->bound( 'data' ) ) {
			$data = $this->engine->make( 'data' );
		}

		$data = array_merge( $view->get_data(), $data );
		$data = array_merge( $this->get_composer( $view ), $data );
		$data = array_merge( $this->get_wildcard_composer(), $data );

		foreach ( $data as $key => $value ) {
			if ( is_callable( $value ) ) {
				$data[$key] = call_user_func( $value, $view );
			}
		}

		$this->engine->bind( 'data', $data );
		$this->reset_composer( $view );
		$this->reset_wildcard_composer();

		return $data;
	}

	/**
	 * Call composer.
	 *
	 * @param  \Frozzare\Digster\View $view
	 *
	 * @return string
	 */
	public function get_composer( View $view ) {
		$view_name = $view->get_name();

		if ( isset( $this->composers[$view_name] ) ) {
			return $this->composers[$view_name];
		}

		return [];
	}

	/**
	 * Get all of the shared data for the views.
	 *
	 * @return array
	 */
	public function get_shared() {
		return $this->shared;
	}

	/**
	 * Get wildcard composer.
	 *
	 * @return array
	 */
	public function get_wildcard_composer() {
		return $this->composers['*'];
	}

	/**
	 * Get the evaluated view contents for the given view.
	 *
	 * @param  string       $view
	 * @param  array|object $data
	 *
	 * @return \Frozzare\Digster\View
	 */
	public function make( $view, $data = [] ) {
		return new View( $this, $this->engine, $this->view( $view ), $this->create_data( $data ) );
	}

	/**
	 * Get the right view string from dot view or view that missing extension.
	 *
	 * @param  string $view
	 *
	 * @return string
	 */
	protected function view( $view ) {
		if ( preg_match( '/\.\w+$/', $view, $matches ) && in_array( $matches[0], $this->extensions() ) ) {
			return str_replace( '.', '/', preg_replace( '/' . $matches[0] . '$/', '', $view ) ) . $matches[0];
		}

		return $this->extension( str_replace( '.', '/', $view ) );
	}

	/**
	 * Reset view composer.
	 *
	 * @param  \Frozzare\Digster\View $view
	 */
	protected function reset_composer( View $view ) {
		$view_name = $view->get_name();

		if ( isset( $this->composers[$view_name] ) ) {
			unset( $this->composers[$view_name] );
		}
	}

	/**
	 * Reset wildcard composer.
	 */
	public function reset_wildcard_composer() {
		$this->composers['*'] = [];
	}
}
