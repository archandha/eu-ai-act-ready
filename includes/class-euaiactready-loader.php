<?php
/**
 * EU AI Act Ready - Registers all WordPress hooks.
 *
 * @package EUAIACTREADY
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Collects and registers actions and filters.
 */
class EUAIACTREADY_Loader {

	/**
	 * The array of actions registered with WordPress.
	 *
	 * @var array
	 */
	protected $actions;

	/**
	 * The array of filters registered with WordPress.
	 *
	 * @var array
	 */
	protected $filters;

	/**
	 * Initialize the collections used to maintain the actions and filters.
	 */
	public function __construct() {
		$this->actions = array();
		$this->filters = array();
	}

	/**
	 * Add a new action to the collection to be registered with WordPress.
	 *
	 * @param string   $hook          WordPress hook name.
	 * @param object   $component     The instance containing the callback.
	 * @param callable $callback      Method to execute when the hook fires.
	 * @param int      $priority      Hook priority.
	 * @param int      $accepted_args Number of accepted arguments.
	 */
	public function add_action( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->actions = $this->add( $this->actions, $hook, $component, $callback, $priority, $accepted_args );
	}

	/**
	 * Add a new filter to the collection to be registered with WordPress.
	 *
	 * @param string   $hook          WordPress filter name.
	 * @param object   $component     The instance containing the callback.
	 * @param callable $callback      Method to execute when the hook fires.
	 * @param int      $priority      Hook priority.
	 * @param int      $accepted_args Number of accepted arguments.
	 */
	public function add_filter( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->filters = $this->add( $this->filters, $hook, $component, $callback, $priority, $accepted_args );
	}

	/**
	 * A utility function used to register the actions and filters into a single collection.
	 *
	 * @param array    $hooks         Existing hooks collection.
	 * @param string   $hook          Hook name.
	 * @param object   $component     Component instance.
	 * @param callable $callback      Callback method.
	 * @param int      $priority      Hook priority.
	 * @param int      $accepted_args Accepted arguments.
	 * @return array Updated hooks collection.
	 */
	private function add( $hooks, $hook, $component, $callback, $priority, $accepted_args ) {
		$hooks[] = array(
			'hook'          => $hook,
			'component'     => $component,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);

		return $hooks;
	}

	/**
	 * Register the filters and actions with WordPress.
	 */
	public function run() {
		foreach ( $this->filters as $hook ) {
			add_filter( $hook['hook'], array( $hook['component'], $hook['callback'] ), $hook['priority'], $hook['accepted_args'] );
		}

		foreach ( $this->actions as $hook ) {
			add_action( $hook['hook'], array( $hook['component'], $hook['callback'] ), $hook['priority'], $hook['accepted_args'] );
		}
	}
}
