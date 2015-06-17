<?php

/**
 * Compiles dependency definitions to a single dependency container class that provides type hinted methods for each
 * defined service.
 *
 * @copyright  2014 inGenerator Ltd
 * @licence    BSD
 * @see        spec\Dependency_CompilerSpec
 */
class Dependency_Compiler
{

	const CLASS_HEADER = <<<'PHP'
<?php
/**
 * Auto-generated from your services configuration
 * [!!] Changes will be overwritten
 */
class :class_name extends :parent_class_name {


PHP;

	const GETTER_METHOD = <<<'PHP'
	/**
	 * @return :return_type
	 */
	public function :method_name() {
		return $this->get(':service_key');
	}


PHP;

	const CLASS_FOOTER = <<<'PHP'
}

PHP;

	/**
	 * @var \Dependency_Definition_List
	 */
	protected $definitions;

	/**
	 * @var array
	 */
	protected $getters;

	/**
	 * @var string
	 */
	protected $class_name;

	/**
	 * @var string
	 */
	protected $filename;

	/**
	 * @param string                     $class_name  the class name to produce
	 * @param string                     $filename    absolute path where the compiled class should be stored
	 * @param Dependency_Definition_List $definitions definitions to compile into a service class
	 *
	 * @return void
	 */
	public function compile($class_name, $filename, \Dependency_Definition_List $definitions = NULL)
	{
		$this->definitions = $definitions;
		$this->class_name = $class_name;
		$this->filename = $filename;

		$this->build_getters();
		$this->write_class_definition();
		$this->validate_service_definitions($class_name, $filename);
	}

	/**
	 * @return void
	 */
	protected function build_getters()
	{
		$this->getters = array();
		foreach ($this->definitions as $name => $definition) {
			$this->build_getter($name, $definition);
		}
	}

	/**
	 * @param string                $service_name
	 * @param Dependency_Definition $definition
	 *
	 * @return void
	 */
	protected function build_getter($service_name, Dependency_Definition $definition)
	{
		$this->getters[$service_name] = array(
			':method_name' => 'get_' . str_replace('.', '_', $service_name),
			':service_key' => $service_name,
			':return_type' => $this->find_service_type($definition)
		);
	}

    /**
	 * @param Dependency_Definition $definition
	 * @return string
	 */
	protected function find_service_type(Dependency_Definition $definition)
	{
		if ( ! $definition->constructor) {
			return $definition->class;
		}

		$reflection = new \ReflectionClass($definition->class);
		$documentation = $reflection->getMethod($definition->constructor)->getDocComment();

		if (preg_match('/\s+\* @return\s+([^\s]+)/', $documentation, $matches)) {
			return $matches[1];
		}

		// Type is not known without actually creating an instance
		return 'mixed';
	}

    /**
	 * @return void
	 */
	protected function write_class_definition()
	{
        $content = $this->build_class_header();
        ksort($this->getters);
		foreach ($this->getters as $getter) {
			$content .= strtr(self::GETTER_METHOD, $getter);
		}
		$content .= self::CLASS_FOOTER;
		file_put_contents($this->filename, $content);
	}

    /**
     * @return string
     */
    protected function build_class_header()
    {
        $params = array(':class_name' => $this->class_name);
        if ($this->class_name === 'Dependency_Container') {
            $params[':parent_class_name'] = '\Kohana_Dependency_Container';
        } else {
            $params[':parent_class_name'] = '\Dependency_Container';
        }

        return strtr(self::CLASS_HEADER, $params);
    }

    /**
	 * @throws InvalidArgumentException if any service definitions are not valid
	 */
	protected function validate_service_definitions()
	{
		require_once $this->filename;
		$container = new $this->class_name($this->definitions);
		$errors = array();
		foreach ($this->getters as $getter) {
			try {
				$service = call_user_func(array($container, $getter[':method_name']));
				if ( ! $this->is_expected_service_type($service, $getter[':return_type'])) {
					$errors[] = sprintf(
						'%s: expected %s, got instance of %s',
						$getter[':service_key'],
						$getter[':return_type'],
						get_class($service)
					);
				}
			} catch (\Dependency_Exception $e) {
				$errors[] = sprintf('%s: ' . $e->getMessage(), $getter[':service_key']);
			} catch (\ReflectionException $e) {
				// Usually an undefined class exception
				$errors[] = sprintf('%s: ' . $e->getMessage(), $getter[':service_key']);
			}
		}

		if ($errors) {
			throw new \InvalidArgumentException("Your service container configuration is not valid:" . PHP_EOL . ' - ' . implode(PHP_EOL . ' - ', $errors));
		}
	}

    /**
	 * @param object $service
	 * @param string $declared_type
	 *
	 * @return bool
	 */
	protected function is_expected_service_type($service, $declared_type)
	{
		if ($declared_type === 'mixed') {
			return TRUE;
		}

		return ($service instanceof $declared_type);
	}

}
