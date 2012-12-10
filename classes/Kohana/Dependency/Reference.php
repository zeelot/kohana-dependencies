<?php defined('SYSPATH') or die('No direct script access.');

abstract class Kohana_Dependency_Reference {

	const KEY_FORMAT = '[0-9a-zA-Z_-]+(\.[0-9a-zA-Z_-]+)*';

	public static function factory($argument)
	{
		if (preg_match('/^\%'.self::KEY_FORMAT.'\%$/D', $argument))
		{
			$argument = new Dependency_Reference_Container(trim($argument, '%'));
		}
		elseif (preg_match('/^\@'.self::KEY_FORMAT.'\@$/D', $argument))
		{
			$argument = new Dependency_Reference_Config(trim($argument, '@'));
		}

		return $argument;
	}

	protected $_key;

	public function __construct($key)
	{
		$this->_key = $key;
	}

	abstract public function resolve(Dependency_Container $container);

}
