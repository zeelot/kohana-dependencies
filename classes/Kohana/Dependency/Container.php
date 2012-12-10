<?php defined('SYSPATH') or die('No direct script access.');

class Kohana_Dependency_Container {

	protected $_cache;
	protected $_definitions;

	public function __construct(Dependency_Definition_List $definitions)
	{
		$this->_cache       = array();
		$this->_definitions = $definitions;
	}

	public function get($key)
	{
		// Get an instance from the cache if it's there
		if ($instance = $this->_cache($key))
			return $instance;

		// Get the dependency definition from the definition list
		$definition = $this->_definitions->get($key);

		// Create an instance of the class using the definition
		$instance = $this->_get_instance($definition);

		// Cache the instance if it is shared
		if ($definition->is_shared())
		{
			$this->_cache($key, $instance);
		}

		return $instance;
	}

	protected function _cache($key, $instance = NULL)
	{
		// Setter
		if (is_object($instance))
		{
			$this->_cache[$key] = $instance;
			return;
		}

		// Getter
		if (isset($this->_cache[$key]))
			return $this->_cache[$key];
		else
			return NULL;
	}

	protected function _get_instance(Dependency_Definition $definition)
	{
		// Make sure the class exists
		if ( ! class_exists($definition->class) AND ! empty($definition->path))
		{
			include_once $definition->path;
		}

		// Reflect the class and prepare the arguments
		$class     = new ReflectionClass($definition->class);
		$arguments = array_map(array($this, '_resolve_argument'), $definition->arguments);

		try
		{
			// Get an instance of the class
			if (empty($definition->constructor))
			{
				$instance = $class->newInstanceArgs($arguments);
			}
			else
			{
				$instance = $class->getMethod($definition->constructor)->invokeArgs(NULL, $arguments);
			}

			// Run any additional methods required to prepare the object
			foreach ($definition->methods as $method => $args)
			{
				$args = array_map(array($this, '_resolve_argument'), $args);
				call_user_func_array(array($instance, $method), $args);
			}
		}
		catch (ReflectionException $e)
		{
			throw new Dependency_Exception('There was a problem instantiating the :class class in the DI_Container.', array(
				':class' => $definition->class,
			));
		}

		return $instance;
	}

	protected function _resolve_argument($argument)
	{
		if ($argument instanceof Dependency_Reference)
		{
			$argument = $argument->resolve($this);
		}

		return $argument;
	}
}