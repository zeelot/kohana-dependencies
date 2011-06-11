<?php defined('SYSPATH') or die('No direct script access.');

class Kohana_Dependency_Injection_Container {

	protected $_cache;
	protected $_config;
	protected $_definitions;

	public function __construct(Config $config)
	{
		$this->_cache  = array();
		$this->_config = $config;
	}
	
	public function get($key)
	{
		// Get an instance from the cache if it's there
		if ($instance = $this->_cache($key))
			return $instance;

		// Build a new definition
		if ( ! isset($this->_definitions[$key]))
		{
			$definition = new Dependency_Definition($key, $this->_config);
			$this->_definitions[$key] = $definition;
		}

		// Create an instance of the class
		$instance = $this->_build($definition);
		
		// Cache the instance if it is shared
		if ($definition->shared)
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
	
	protected function _build(Dependency_Definition $definition)
	{
		// Make sure the class exists
		if ( ! empty($definition->path) AND ! class_exists($definition->class))
		{
			$this->_include_path($definition->path);
		}

		// Reflect the class and prepare the arguments
		$class     = new ReflectionClass($definition->class);
		$arguments = $this->_resolve_arguments($definition->arguments);

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
			$reflected_instance = new ReflectionClass($instance);
			foreach ($definition->methods as $method)
			{
				list($method, $args) = $method;
				$args = $this->_resolve_arguments($args);
				$reflected_instance->getMethod($method)->invokeArgs($instance, $args);
			}
		}
		catch (ReflectionException $ex)
		{
			throw new Dependency_Exception('There was a problem instantiating the :class class in the DI_Container.', array(
				':class' => $definition->class,
			));
		}
		
		return $instance;
	}
	
	protected function _include_path($path)
	{
		$file = NULL;
		if (strpos($path, '/') !== FALSE)
		{
			list($directory, $filepath) = explode('/', $path, 2);
			$file = Kohana::find_file($directory, $filepath);
		}
		
		if (empty($file))
			throw new Dependency_Exception('Could not find the path to include for the dependency definition.');
		
		require_once $file;
	}
	
	protected function _resolve_arguments(array $arguments)
	{
		foreach ($arguments as & $argument)
		{
			if (is_string($argument))
			{
				if (preg_match('/\%.+\%/', $argument))
				{
					$argument = $this->get(trim($argument, '%'));
				}
				elseif (preg_match('/\@.+\@/', $argument))
				{
					$argument = trim($argument, '@');
					$group = $path = NULL;
					if (strpos($argument, '.') !== FALSE)
					{
						list($group, $path) = explode('.', $argument, 2);
					}
					$argument = Arr::path($this->_config->load($group), $path);
				}
			}
		}
		
		return $arguments;
	}
}