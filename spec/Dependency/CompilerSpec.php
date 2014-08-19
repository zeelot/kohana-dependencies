<?php
/**
 * @author    Andrew Coulton <andrew@ingenerator.com>
 * @copyright 2014 inGenerator Ltd
 * @licence   BSD
 */
namespace spec;
use org\bovigo\vfs\vfsStream;
use PhpSpec\Exception\Example\FailureException;
use PhpSpec\ObjectBehavior;

require_once __DIR__.'/../../koharness_bootstrap.php';

/**
 *
 * @see \Dependency_Compiler
 */
class Dependency_CompilerSpec extends ObjectBehavior
{
	/**
	 * $this->subject is an alias of $this, but allows for proper type hinting of the subject class.
	 *
	 * @var \Dependency_Compiler
	 */
	protected $subject;

	public function __construct()
	{
		$this->subject = $this;
	}

	function let()
	{
		vfsStream::setup('compiled');
	}

	function it_is_initializable()
	{
		$this->subject->shouldHaveType('\Dependency_Compiler');
	}

	function it_creates_file_at_requested_path()
	{
		$file = $this->given_compiled_to_temp_file(uniqid('services'), new \Dependency_Definition_List);
		expect(file_exists($file))->toBe(TRUE);
	}

	function it_compiles_class_definition_for_requested_class_name()
	{
		$this->given_compiled_to_unique_class(new \Dependency_Definition_List);
	}

    function its_compiled_class_extends_kohana_dependency_container_if_classname_conflicts()
    {
        $file = $this->given_compiled_to_temp_file('SomeClass', new \Dependency_Definition_List);
        expect(file_get_contents($file))->toMatch('/class SomeClass extends \\\\Dependency_Container/');
    }


    function its_compiled_class_is_an_instantiable_dependency_container()
	{
		$dependencies = new \Dependency_Definition_List;
		$class = $this->given_compiled_to_unique_class(new \Dependency_Definition_List);
		expect(new $class($dependencies))->toBeAnInstanceOf($class);
		expect(new $class($dependencies))->toBeAnInstanceOf('\Dependency_Container');
	}

	function it_adds_getter_for_each_defined_service()
	{
		$class = $this->given_compiled_to_unique_class(\Dependency_Definition_List::factory()
				->from_array(array(
					'date' => array('_settings' => array('class' => '\DateTime')),
					'array' => array('_settings' => array('class' => '\ArrayObject')),
				))
		);

		expect(method_exists($class, 'get_date'))->toBe(TRUE);
		expect(method_exists($class, 'get_array'))->toBe(TRUE);
	}

	function it_adds_getter_with_underscores_for_nested_services()
	{
		$class = $this->given_compiled_to_unique_class(\Dependency_Definition_List::factory()
				->from_array(array(
					'date' => array(
						'time' => array('_settings' => array('class' => '\DateTime'))
					),
				))
		);

		expect(method_exists($class, 'get_date_time'))->toBe(TRUE);
	}

	function its_generated_service_container_can_create_services()
	{
		$definitions = \Dependency_Definition_List::factory()
			->from_array(array('date' => array('_settings' => array('class' => '\DateTime'))));

		$class = $this->given_compiled_to_unique_class($definitions);
		$container = new $class($definitions);
		expect($container->get_date())->toBeAnInstanceOf('\DateTime');
	}

	function it_declares_correct_getter_phpdoc_return_type_with_for_simple_service()
	{
		$class = $this->given_compiled_to_unique_class(\Dependency_Definition_List::factory()
				->from_array(array('date' => array('_settings' => array('class' => '\DateTime'))))
		);
		expect($class)->toDeclareMethodReturnType('get_date', '\DateTime');
	}

	function it_declares_correct_getter_phpdoc_return_type_for_service_provided_by_static_factory_with_phpdoc()
	{
		$class = $this->given_compiled_to_unique_class(\Dependency_Definition_List::factory()
			->from_array(array(
				'array' => array(
					'_settings' => array(
						'class' => __NAMESPACE__ . '\DocumentedArrayObjectFactory',
						'constructor' => 'factory'
					)
				)
			)));

		expect($class)->toDeclareMethodReturnType('get_array', '\ArrayObject');
	}

	function it_declares_mixed_return_type_for_service_provided_by_static_factory_with_no_phpdoc()
	{
		$class = $this->given_compiled_to_unique_class(\Dependency_Definition_List::factory()
			->from_array(array(
				'array' => array(
					'_settings' => array(
						'class' => __NAMESPACE__ . '\UndocumentedFactory',
						'constructor' => 'factory'
					)
				)
			)));

		expect($class)->toDeclareMethodReturnType('get_array', 'mixed');
	}

	function it_alpha_sorts_getters()
	{
		$file = $this->given_compiled_to_temp_file(uniqid('services'), \Dependency_Definition_List::factory()
				->from_array(array(
					'date' => array('_settings' => array('class' => '\DateTime')),
					'array' => array('_settings' => array('class' => '\ArrayObject')),
				))
		);

		expect(file_get_contents($file))->toMatch('/get_array.+?get_date/s');
	}

	function it_throws_if_service_returns_unexpected_type()
	{
		try {
			$this->given_compiled_to_unique_class(\Dependency_Definition_List::factory()
				->from_array(array(
					'date' => array(
						'_settings' => array(
							'class' => __NAMESPACE__ . '\BadlyDocumentedFactory',
							'constructor' => 'factory'
						)
					)
				)));
			throw new FailureException("Expected exception, none thrown");
		} catch (\InvalidArgumentException $e) {
			expect($e->getMessage())->toMatch('/date/');
		}
	}

	function it_throws_if_service_refers_to_undefined_class()
	{
		try {
			$this->given_compiled_to_unique_class(\Dependency_Definition_List::factory()
				->from_array(array(
					'undefined' => array('_settings' => array('class' => __NAMESPACE__ . '\\UndefinedClass'))
				)));
			throw new FailureException("Expected exception, none thrown");
		} catch (\InvalidArgumentException $e) {
			expect($e->getMessage())->toMatch('/undefined/');
		}
	}

	function it_throws_if_service_dependencies_cannot_be_met_from_the_container()
	{
		try {
			$this->given_compiled_to_unique_class(\Dependency_Definition_List::factory()
				->from_array(array(
					'invalid' => array('_settings' => array('class' => '\DateTime', 'arguments' => array('%undefined%')))
				)));
			throw new FailureException("Expected exception, none thrown");
		} catch (\InvalidArgumentException $e) {
			expect($e->getMessage())->toMatch('/invalid/');
		}
	}

	/**
	 * @param string $class
	 * @param \Dependency_Definition_List $definitions
	 * @return string the filename
	 */
	function given_compiled_to_temp_file($class, \Dependency_Definition_List $definitions)
	{
		$file = vfsStream::url('compiled/'.$class.'.php');
		$this->subject->compile($class, $file, $definitions);
		return $file;
	}

	/**
	 * @param \Dependency_Definition_List $definitions
	 *
	 * @return object
	 */
	protected function given_compiled_to_unique_class(\Dependency_Definition_List $definitions)
	{
		$class = uniqid('services');
		expect($class)->notToBeDefinedClassName();
		$this->given_compiled_to_temp_file($class, $definitions);
		expect($class)->toBeDefinedClassName();
		return $class;
	}

	public function getMatchers()
	{
		$matchers = parent::getMatchers();

		$matchers['beDefinedClassName'] = function ($name) {
			return class_exists($name, FALSE);
		};

		$matchers['declareMethodReturnType'] = function ($class, $method_name, $expect_type) {
			$reflection = new \ReflectionClass($class);
			$method     = $reflection->getMethod($method_name);
			$comment    = $method->getDocComment();

			if ( ! preg_match('/^\s+\* @return (.+?)$/m', $comment, $matches)) {
				throw new FailureException("Expected $method_name doc comment to define @return tag but it does not: " . $comment);
			}
			if ($expect_type !== $matches[1]) {
				var_dump($matches[1]);
				throw new FailureException("Expected $method_name @return of " . $expect_type . ", got " . $matches[1]);
			}
			return TRUE;
		};
		return $matchers;
	}

}

class DocumentedArrayObjectFactory {
	/**
	 * @return \ArrayObject
	 */
	public static function factory()
	{
		return new \ArrayObject;
	}
}

class UndocumentedFactory {

	public static function factory()
	{
		return new \ArrayObject;
	}
}

class BadlyDocumentedFactory {

	/**
	 * @return \DateTime
	 */
	public static function factory()
	{
		return new \ArrayObject;
	}
}

class ClassWithDependencies
{
	public function __construct(\DateTime $time)
	{

	}
}
