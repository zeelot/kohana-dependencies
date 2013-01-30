<?php defined('SYSPATH') or die('No direct script access.');

class Kohana_Dependency_Definition {

	public static function factory()
	{
		return new Dependency_Definition;
	}

	protected $_class       = NULL;
	protected $_path        = NULL;
	protected $_constructor = NULL;
	protected $_arguments   = array();
	protected $_shared      = FALSE;
	protected $_methods     = array();

	public function from_array(array $settings)
	{
		// Remove all unneeded items
		$allowed_keys = array('class', 'path', 'constructor', 'arguments', 'shared', 'methods');
		$settings = array_filter(Arr::extract($settings, $allowed_keys));

		// Loop through and use the class's setter methods
		foreach ($settings as $key => $value)
		{
			// Decide which setter method to use
			$set = 'set_'.$key;

			$this->$set($value);
		}

		return $this;
	}

	public function set_class($class)
	{
		$this->_class = $class;

		return $this;
	}

	public function set_path($path)
	{
		// Make sure the path is a string
		if ( ! is_string($path))
		{
			$path = '';
		}

		// Make sure the path exists
		$file_path = NULL;
		if (strpos($path, '/') !== FALSE)
		{
			list($directory, $file) = explode('/', $path, 2);
			$file_path = Kohana::find_file($directory, $file);

			if (empty($file_path))
				throw new Dependency_Exception('Could not construct the dependency definition. An invalid path was provided.');
		}

		$this->_path = $file_path;

		return $this;
	}

	public function set_constructor($method)
	{
		$this->_constructor = $method;

		return $this;
	}

	public function set_arguments(array $arguments)
	{
		$this->_arguments = array();
		foreach ($arguments as $argument)
		{
			$this->add_argument($argument);
		}

		return $this;
	}

	public function set_shared($shared)
	{
		$this->_shared = (bool) $shared;

		return $this;
	}

	public function set_methods(array $methods)
	{
		$this->_methods = array();
		foreach ($methods as $method)
		{
			$method_name = Arr::get($method, 0);
			$arguments   = Arr::get($method, 1, array());
			$this->add_method($method_name, $arguments);
		}

		return $this;
	}

	public function add_argument($argument)
	{
		$this->_arguments[] = $this->_handle_reference($argument);

		return $this;
	}

	public function add_method($method, array $arguments = array())
	{
		foreach ($arguments as & $argument)
		{
			$argument = $this->_handle_reference($argument);
		}

		$this->_methods[$method] = $arguments;

		return $this;
	}

	public function is_shared()
	{
		return (bool) $this->_shared;
	}

	public function merge_with(Dependency_Definition $right)
	{
		$left = clone $this;
		
		foreach (get_object_vars($this) as $key => $value)
		{
			if ( ! empty($right->$key))
			{
				$left->$key = $right->$key;
			}
		}

		return $left;
	}

	public function __get($property)
	{
		if (property_exists($this, '_'.$property))
			return $this->{'_'.$property};
		else
			return NULL;
	}
	
	public function __isset($property)
	{
		return (bool) property_exists($this, '_'.$property);
	}

	public function as_array()
	{
		$properties = array();
		foreach(get_object_vars($this) as $key => $value)
		{
			$key = ltrim($key, '_');
			$properties[$key] = $value;
		}

		return $properties;
	}

	protected function _handle_reference($argument)
	{
		if ( ! empty($argument) AND is_string($argument) AND in_array($argument[0], array('%', '@')))
		{
			$argument = Dependency_Reference::factory($argument);
		}

		return $argument;
	}
}
