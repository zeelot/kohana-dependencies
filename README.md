# Dependency Injection Made Easy in Kohana

A simple dependency injection container for Kohana 3.3.x

- **Author**: Jeremy Lindblom ([jeremeamia](https://github.com/jeremeamia))
- **Version**: 0.7

## About Dependency Injection and Dependency Injection Containers

Dependency injection is a method used follow the [Inversion of Control](http://en.wikipedia.org/wiki/Inversion_of_control) (IoC), or Dependency Inversion, principle. A dependency injection container (DIC) is a component used to manage dependencies and make dependency injection easier to maintain. Some of the main arguments for using dependency injection are that it:

- Makes unit testing and mocking easier (or possible)
- Decouples object instantiation from usage
- Allows for better separation of concerns and higher object cohesion
- Reduces the usage and need for singleton classes which are [considered](http://misko.hevery.com/code-reviewers-guide/flaw-brittle-global-state-singletons) [bad](http://gooh.posterous.com/singletons-in-php) by [many](http://blogs.sitepoint.com/whats-so-bad-about-the-singleton) [people](http://sebastian-bergmann.de/archives/882-Testing-Code-That-Uses-Singletons.html)

For information about dependency injection, you should read from the following articles:

- <http://martinfowler.com/articles/injection.html>
- <http://fabien.potencier.org/article/11/what-is-dependency-injection>
- <http://misko.hevery.com/2008/07/08/how-to-think-about-the-new-operator>
- <http://misko.hevery.com/2008/09/10/where-have-all-the-new-operators-gone>
- <http://misko.hevery.com/2008/09/30/to-new-or-not-to-new>  *(awesome)*

## Using this Dependency Injection Container

The container has a `->get($key)` method that is used to get an instance of an object identified by a `$key`. The container uses the dependency definitions you setup to create the object instance in the proper way, with all of its necessary dependencies.

### Usage Examples

Assuming you are in the context of a controller, and it has an instance of the container, you could do...

	$mailer = $this->container->get('swift.mailer');

What does this get you? Well, assuming you have some killer dependency definitions (shown later below) setup, it would return a fully
initialized instance of a SwiftMailer object with all dependencies and configurations applied. If you have worked with
SwiftMailer before, you know that creating an instance manually is a pain.

Or... how would you like to instantiate a User Model (using any ORM-like library or database driver) that requires the
Session and an Event Dispatcher whilst maintaining proper Inversion of Control, but without the headache you are about
to have instantiating it manually? You would? Great! Once configured, it could probably look something like:

	$user = $this->container->get('model.user')->find($this->request->param('user_id));

You are only limited by your imagination... and PHP.

## Dependency Definition Settings

- `class`:        The name of the class that is to be created.
- `path`:         The path to the file containing the class. Will try to autoload the class if none is provided.
- `constructor`:  The method used to create the class. Will use `__construct()` if none is provided.
- `arguments`:    The arguments to be passed to the constructor method.
- `shared`:       The shared setting determines if the object will be cached. This is `FALSE` by default.
- `methods`:      Additional methods (and their arguments) that need to be called on the created object.

## Creating a Container

You can create a container from an array of dependency definitions (i.e. from a config file)

	// Creation Code
	$definitions = Dependency_Definition_List::factory()
		->from_array(Kohana::config('dependencies')->as_array());
	$container = new Dependency_Container($definitions);

Here's a sample config file:

	// Config File
	return array(
		'session' => array(
			'_settings' => array(
				'class'       => 'Session',
				'constructor' => 'instance',
				'arguments'   => array('@session.driver@'),
				'shared'      => TRUE,
			),
		),
		'model' => array(
			'_settings' => array(
				'class'       => 'Model',
				'constructor' => 'factory',
			),

			'user' => array(
				'_settings' => array(
					'arguments' => array('user'),
					'methods'   => array(
						array('set_session', array('%session%')),
					),
				),
			),
		),
		'swift' => array(
			'transport' => array(
				'_settings' => array(
					'class'     => 'Swift_SmtpTransport',
					'path'      => 'vendor/swiftmailer/lib/classes/Swift/SmtpTransport',
					'arguments' => array('@email.host@', '@email.port@'),
					'methods'   => array(
						array('setEncryption', array('@email.encryption@')),
					),
				),
			),
			'mailer' => array(
				'_settings' => array(
					'class'     => 'Swift_Mailer',
					'path'      => 'vendor/swiftmailer/lib/classes/Swift/Mailer',
					'arguments' => array('%swift.transport%'),
					'shared'    => TRUE,
				),
			),
		),
	);

You can also create a container by using the programmatic API.

	// Creation Code
	$container = new Dependency_Container(Dependency_Definition_List::factory()
		->add('session', Dependency_Definition::factory()
			->set_class('Session')
			->set_constructor('instance')
			->add_argument(new Dependency_Reference_Config('session.driver'))
			->set_shared(TRUE)
		)
		->add('model', Dependency_Definition::factory()
			->set_class('Model')
			->set_constructor('factory')
		)
		->add('model.user', Dependency_Definition::factory()
			->add_argument('user')
			->add_method('set_session', array(new Dependency_Reference_Container('session')))
		)
		->add('swift.transport', Dependency_Definition::factory()
			->set_class('Swift_SmtpTransport')
			->set_path('vendor/swiftmailer/lib/classes/Swift/SmtpTransport')
			->add_argument(new Dependency_Reference_Config('email.host'))
			->add_argument(new Dependency_Reference_Config('email.port'))
			->add_method('setEncryption', array(new Dependency_Reference_Config('email.encryption')))
		)
		->add('swift.mailer', Dependency_Definition::factory()
			->set_class('Swift_Mailer')
			->set_path('vendor/swiftmailer/lib/classes/Swift/Mailer')
			->add_argument(new Dependency_Reference_Container('swift.transport'))
			->set_shared(TRUE)
		)
	);

## Compiling a dependency container

By default, you access dependencies by their service path. If you want, you can compile a container class that exposes
each service as a typehinted method.

In other words:

```php
// You get this
$services->get_swift_mailer()->send($message);

// Instead of this
$services->get('swift.mailer').send($message);
```

This provides several benefits:

* IDE autocompletion of available services
* IDE autocompletion and usage detection of the methods on the services themselves
* Clear, maintainable, definitions of which implementation of an interface is actually in use for easier debugging
* Compile-time validation of your service configurations

Compile your dependencies with the provided [compile:dependencies](classes/Task/Compile/Dependencies.php) minion task.
During the task, the compiler will create every service in your definition list, and fail with an error if any service
cannot be instantiated.

The recommended use is to place this minion task within your build/deploy task, so that the container is compiled fresh
for every deployment and fails early if there are any undetected breaking changes in your dependencies.
