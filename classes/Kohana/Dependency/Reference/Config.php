<?php defined('SYSPATH') or die('No direct script access.');

class Kohana_Dependency_Reference_Config extends Dependency_Reference {

	public function resolve(Dependency_Container $container)
	{
		return Kohana::$config->load($this->_key);
	}

}
