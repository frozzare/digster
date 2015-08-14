<?php

namespace Digster\Engines;

use Digster\Container;

/**
 * Engine.
 *
 * @package Digster
 */
abstract class Engine extends Container {

	/**
	 * Engines composers.
	 *
	 * @var array
	 */
	protected $composers = [];

	/**
	 * The default extension (empty string).
	 *
	 * @var string
	 */
	protected $extensions = ['html'];

	/**
	 * The View instance.
	 *
	 * @var \Digster\Engine
	 */
	private static $instance = null;

	/**
	 * The config locations key.
	 *
	 * @var string
	 */
	protected $locations_key = 'locations';

	/**
	 * The wildcard composer key that all template uses.
	 *
	 * @var string
	 */
	protected $wildcard_composer_key = '*';

	/**
	 * Get or set configuration values.
	 *
	 * @param array|string $key
	 * @param mixed $value
	 *
	 * @return mixed
	 */
	public function config( $key, $value = null ) {
		if ( is_array( $key ) ) {
			foreach ( $key as $id => $val ) {
				$this->config( $id, $val );
			}
		} else {
			if ( ! is_null( $value ) ) {
				return $this->bind( $key, $value );
			}

			if ( $this->exists( $key ) ) {
				return $this->make( $key );
			} else {
				$default = $this->get_default_config();
				return isset( $default[$key] ) ? $default[$key] : null;
			}
		}
	}

	/**
	 * Add extension to the template string if it don't exists.
	 *
	 * @param string $template
	 *
	 * @return string
	 */
	public function extension( $template ) {
		// Fix extension for dot template strings that replaces all '.' to '/'.
		$end_slash_regex = '/\/(\w+)+$/';
		preg_match( $end_slash_regex, $template, $matches );

		if ( count( $matches ) > 1 ) {
			foreach ( $this->extensions as $ext ) {
				if ( substr( $ext, 1 ) === $matches[1] ) {
					$template = preg_replace( $end_slash_regex, $ext, $template );
					break;
				}
			}
		}

		// Return if a valid extension exists in the template string.
		$ext_reg = '/(' . implode( '|', $this->extensions ) . ')+$/';
		if ( preg_match( $ext_reg, $template ) ) {
			return $template;
		}

		// Add extension to template string if it don't exists.
		return substr( $template, -strlen( $this->extensions[0] ) ) === $this->extensions[0]
			? $template : $template . $this->extensions[0];
	}

	/**
	 * Get the Engine instance.
	 *
	 * @return \Digster\Engine
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new static;
		}

		return self::$instance;
	}

	/**
	 * Get composer by template.
	 *
	 * @param string $template
	 *
	 * @return array
	 */
	protected function get_composer( $template ) {
		if ( is_array( $template ) ) {
			$template = array_shift( $template );
		}

		$composers = [];
		$template  = $this->extension( $template );

		if ( isset( $this->composers[$template] ) ) {
			$composers = array_merge( $composers, $this->composers[$template] );
		}

		if ( isset( $this->composers[$this->wildcard_composer_key] ) ) {
			$composers = array_merge( $composers, $this->composers[$this->wildcard_composer_key] );
		}

		return $composers;
	}

	/**
	 * Get default configuration.
	 *
	 * @return array
	 */
	protected function get_default_config() {
		$config = [];

		$config[$this->locations_key] = [
			get_template_directory() . '/views'
		];

		return $config;
	}

	/**
	 * Get engine config.
	 *
	 * @return array
	 */
	protected function get_engine_config() {
		$config    = $this->prepare_engine_config();
		$locations = $config[$this->locations_key];

		unset( $config[$this->locations_key] );

		$locations = array_filter( (array) $locations, function ( $location ) {
			return file_exists( $location );
		} );

		return [$locations, $config];
	}

	/**
	 * Prepare template data with preprocesses.
	 *
	 * @param string $template
	 * @param array $data
	 *
	 * @return array
	 */
	protected function prepare_data( $template, $data ) {
		$data         = (array) $data;
		$preprocesses = $this->composer( $template );

		foreach ( $preprocesses as $fn ) {
			if ( is_callable( $fn ) ) {
				$data = array_merge( $data, call_user_func( $fn, $data ) );
			}
		}

		return $data;
	}

	/**
	 * Render template with given data.
	 *
	 * @param string $template
	 * @param array $data
	 *
	 * @return string
	 */
	abstract public function render( $template, $data );

	/**
	 * Register extensions.
	 */
	abstract public function register_extensions();

	/**
	 * Register preprocess with templates.
	 *
	 * @param array|string $template
	 * @param callable $fn
	 */
	public function composer( $template, $fn = null ) {
		if ( is_null( $fn ) ) {
			return $this->get_composer( $template );
		}

		$template = (array) $template;

		foreach ( $template as $tmpl ) {
			if ( $tmpl !== $this->wildcard_composer_key ) {
				$tmpl = $this->extension( $tmpl );
			}

			if ( ! isset( $this->composers[$tmpl] ) ) {
				$this->composers[$tmpl] = [];
			}

			$this->composers[$tmpl][] = $fn;
		}
	}

	/**
	 * Prepare the template engines real configuration.
	 *
	 * @param array $arr
	 *
	 * @return array
	 */
	protected function prepare_config( $arr ) {
		$result = [];

		if ( ! is_array( $arr ) ) {
			return $result;
		}

		$arr = array_merge( $this->get_default_config(), $arr );

		foreach ( $arr as $key => $value ) {
			$res          = $this->config( $key );
			$result[$key] = is_null( $res ) ? $value : $res;
		}

		return apply_filters( 'digster/config', $result );
	}

	/**
	 * Register extension.
	 */
	abstract public function prepare_engine_config();

	/**
	 * Get the right template string that should be loaded.
	 *
	 * @param string $template
	 *
	 * @return string
	 */
	public function template( $template ) {
		return $this->extension( str_replace( '.', '/', $template ) );
	}

}
