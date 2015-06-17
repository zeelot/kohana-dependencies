<?php
/**
 * Compiles your service definitions into a single class with getters for each service, validating that all declared
 * services can be created.
 *
 * Options:
 *
 *  * --class-name        - the name of the class to generate (default: Dependency_Container)
 *  * --path              - path to output the resulting class file (default: APPPATH/classes/Dependency/Container.php)
 *  * --config-group      - config group to load dependencies from (default: dependencies)
 *
 * If your service definitions are invalid, the task will fail with an error. The recommended use is to exclude the
 * compiled class from your version control system, and compile it fresh for every build/deploy. This ensures it is
 * up to date with all your modules and related configuration, and that your build will fail fast if the service
 * configuration is not valid.
 *
 * @author    Andrew Coulton <andrew@ingenerator.com>
 * @copyright 2014 inGenerator Ltd
 * @licence   BSD
 */
class Task_Compile_Dependencies extends Minion_Task {

	public function __construct()
	{
		$this->_options = array(
			'class-name'   => 'Dependency_Container',
			'path'         => APPPATH.'/classes/Dependency/Container.php',
			'config-group' => 'dependencies'
		);
		parent::__construct();
	}

	/**
	 * @param  \Validation $validation
	 * @return \Validation
	 */
	public function build_validation(Validation $validation)
	{
		return parent::build_validation($validation)
			->rule('class-name', 'not_empty')
			->rule('path', 'not_empty')
			->rule('path', array($this, '_valid_path'))
			->rule('config-group', 'not_empty');
	}

	/**
	 * @param array $params
	 *
	 * @return void
	 */
	protected function _execute(array $params)
	{
		\Minion_CLI::write('Loading dependency list from config');
		$definitions = Dependency_Definition_List::factory()
			->from_array(Kohana::$config->load($params['config-group'])->as_array());

		\Minion_CLI::write('Compiling dependencies to '.$params['class-name'].' in '.$params['path']);
		$compiler = new Dependency_Compiler;
		$compiler->compile($params['class-name'], $params['path'], $definitions);

		\Minion_CLI::write('Done');
	}

	/**
	 * Check if the class can be written to the specified absolute path
	 *
	 * @param string $path
	 *
	 * @return bool
	 */
	public function _valid_path($path)
	{
		if (file_exists($path)) {
			return is_writable($path);
		}

		$dir = dirname($path);
		if ( ! is_dir($dir)) {
			mkdir($dir, 0755, TRUE);
		}
		return is_writable($dir);
	}

}
