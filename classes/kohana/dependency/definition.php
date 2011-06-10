<?php defined('SYSPATH') or die('No direct script access.');

class Kohana_Dependency_Definition {

	public static function defaults()
	{
		return array
		(
			'class'       => NULL,    // The class that is to be created.
			'path'        => NULL,    // The path to the file containing the class. Will try to autoload the class if no path is provided. Assumes ".php" extension.
			'constructor' => NULL,    // The method used to create the class. Will use "__construct()" if none is provided.
			'arguments'   => NULL,    // The arguments to be passed to the constructor method.
			'shared'      => FALSE,   // The shared setting determines if the object will be cached.
			'methods'     => array(), // Additional methods (and their arguments) that need to be called on the created object.
		);
	}
	
	protected $_settings;
	
	public function __construct($key, array $dependencies)
	{
		// Merge all relevant dependency definitions into one collection of settings
		$this->_settings = Dependency_Definition::defaults();
		$current_path = '';
		foreach (explode('.', $key) as $sub_key)
		{
			$current_path = trim($current_path.'.'.$sub_key, '.');
			$path_settings = Arr::path($dependencies, $current_path.'.settings', array());
			$this->_settings = Arr::overwrite($this->_settings, $path_settings);
		}
		
		// Make sure the "class" setting is valid
		if (empty($this->_settings['class']))
		{
			throw new Dependency_Exception('Cannot determine which class to load based on the dependency definition.');
		}
		
		// Make sure the "path" setting is valid
		if ( ! is_string($this->_settings['path']))
		{
			$this->_settings['path'] = NULL;
		}
		
		// Make sure the "constructor" setting is valid
		if ( ! is_string($this->_settings['constructor']))
		{
			$this->_settings['constructor'] = NULL;
		}
		
		// Make sure the "arguments" setting is valid
		if ( ! is_array($this->_settings['arguments']))
		{
			$this->_settings['arguments'] = array();
		}
		
		// Make sure the "shared" setting is valid
		$this->_settings['shared'] = (bool) $this->_settings['shared'];
		
		// Make sure the "methods" setting is valid
		if (is_array($this->_settings['methods']))
		{
			$methods = array();
			foreach ($this->_settings['methods'] as $method)
			{
				$method_name = (isset($method[0]) AND is_string($method[0])) ? $method[0] : NULL;
				$arguments   = (isset($method[1]) AND is_array($method[1])) ? $method[1] : NULL;
				$methods[]   = array($method_name, $arguments);
			}
			
			$this->_settings['methods'] = $methods;
		}
		else
		{
			$this->_settings['methods'] = array();
		}
	}
	
	public function __get($setting)
	{
		if (array_key_exists($setting, $this->_settings))
			return $this->_settings[$setting];
		else
			return NULL;
	}
	
	public function __isset($setting)
	{
		return (bool) array_key_exists($setting, $this->_settings);
	}
}
