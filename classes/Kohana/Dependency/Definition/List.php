<?php defined('SYSPATH') or die('No direct script access.');

class Kohana_Dependency_Definition_List implements Iterator, Countable, ArrayAccess {

	public static function factory()
	{
		return new Dependency_Definition_List;
	}

	protected $_definitions = array();

	public function add($key, Dependency_Definition $definition)
	{
		if ( ! is_string($key))
			throw new Dependency_Exception('A dependency definition must be identified with a string key.');

		$this->_definitions[$key] = $definition;

		return $this;
	}

	public function get($key)
	{
		if ( ! is_string($key))
			throw new Dependency_Exception('Could not find the dependency definition. An invalid key was provided.');

		// Get all of the relevant definitions
		$relevant_definitions = array();
		$current_path = '';
		foreach (explode('.', $key) as $sub_key)
		{
			$current_path = trim($current_path.'.'.$sub_key, '.');
			if ($definition = Arr::path($this->_definitions, $current_path))
			{
				$relevant_definitions[] = $definition;
			}
		}

		if (empty($relevant_definitions))
			throw new Dependency_Exception('Could not find the dependency definition based on the provided key.');

		// Merge the relevant definitions into a single definition that will be used to construct the object
		$definition = array_shift($relevant_definitions);
		foreach ($relevant_definitions as $relevant_definition)
		{
			$definition = $definition->merge_with($relevant_definition);
		}

		return $definition;
	}

	public function from_array(array $array, $parent_key = '')
	{
		foreach ($array as $key => $sub_array)
		{
			if ( ! is_array($sub_array))
				throw new Dependency_Exception('Could not load dependency definitions from the array.');

			$full_key = trim($parent_key.'.'.$key, '.');

			if ($settings = Arr::get($sub_array, '_settings'))
			{
				// Create the definition and add it to the list
				$definition = new Dependency_Definition;
				$this->add($full_key, $definition->from_array($settings));

				// Remove the settings from the array so we can look at the sub arrays only
				unset($sub_array['_settings']);
			}

			// Recursively call this method with the sub array (if not empty) to get more definitions
			if ( ! empty($sub_array))
			{
				$this->from_array($sub_array, $full_key);
			}
		}

		return $this;
	}

	public function as_array()
	{
		return $this->_definitions;
	}

	public function count()
	{
		return count($this->_definitions);
	}

	public function current()
	{
		return current($this->_definitions);
	}

	public function key()
	{
		return key($this->_definitions);
	}

	public function next()
	{
		next($this->_definitions);
	}

	public function rewind()
	{
		reset($this->_definitions);
	}

	public function valid()
	{
		return (current($this->_definitions) !== FALSE);
	}

	public function offsetExists($key)
	{
		return isset($this->_definitions[$key]);
	}

	public function offsetGet($key)
	{
		return $this->get($key);
	}

	public function offsetSet($key, $value)
	{
		return $this->add($key, $value);
	}

	public function offsetUnset($key)
	{
		unset($this->_definitions[$key]);
	}

}
