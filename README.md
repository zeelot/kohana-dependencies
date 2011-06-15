# Dependency Injection Made Easy

## Using the Container

Assuming you are in the context of a controller, and it has an instance of the container, you could do...

	$mailer = $this->container('swift.mailer');

What does this get you? Well, assuming you have some killer dependency definitions setup, it would return a fully
initialized instance of a SwiftMailer object with all dependencies and configurations applied. If you have worked with
SwiftMailer before, you know that creating an instance manually is a pain.

Or... how would you like to instantiate a User Model (using any ORM-like library or database driver) that requires the
Session and an Event Dispatcher whilst maintaining proper Inversion of Control, but without the headache you are about
to have instantiating it manually? You would? Great! Once configured, it could probably look something like:

	$user = $this->container('model.user')->find($this->request->param('user_id));

You are only limited by your imagination... and PHP.

## Dependency Definition Settings

- class:        The name of the class that is to be created.
- path:         The path to the file containing the class. Will try to autoload the class if none is provided.
- constructor:  The method used to create the class. Will use `__construct()` if none is provided.
- arguments:    The arguments to be passed to the constructor method.
- shared:       The shared setting determines if the object will be cached. This is `FALSE` by default.
- methods:      Additional methods (and their arguments) that need to be called on the created object.

## Creating a Container

You can create a container from an array of dependency definitions (i.e. from a config file)

	// Creation Code
	$definitions = Dependency_Definition_List::factory()
		->from_array(Kohana::config('dependencies')->as_array());
	$container = new Dependency_Container($definitions);

	// Config File
	return array
	(
		'session' => array
		(
			'settings' => array
			(
				'class'       => 'Session',
				'constructor' => 'instance',
				'arguments'   => array('native'),
				'shared'      => TRUE,
			),
		),
		'model' => array
		(
			'settings' => array
			(
				'class'       => 'Model',
				'constructor' => 'factory',
			),

			'user' => array
			(
				'settings' => array
				(
					'arguments' => array('user'),
					'methods'   => array
					(
						array('set_session', array('%session%')),
					),
				),
			),
		),
		'swift' => array
		(
			'transport' => array
			(
				'settings' => array
				(
					'class'     => 'Swift_SmtpTransport',
					'path'      => 'vendor/swiftmailer/lib/classes/Swift/SmtpTransport',
					'arguments' => array('@email.host@', '@email.port@'),
					'methods'   => array
					(
						array('setEncryption', array('@email.encryption@')),
					),
				),
			),
			'mailer' => array
			(
				'settings' => array
				(
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
			->add_argument('native')
			->set_shared(TRUE)
		)
		->add('model', Dependency_Definition::factory()
			->set_class('Model')
			->set_constructor('factory')
		)
		->add('model.user', Dependency_Definition::factory()
			->add_argument('user')
			->add_method('set_session', array('%session%'))
		)
		->add('swift.transport', Dependency_Definition::factory()
			->set_class('Swift_SmtpTransport')
			->set_path('vendor/swiftmailer/lib/classes/Swift/SmtpTransport')
			->add_argument('@email.host@')
			->add_argument('@email.port@')
			->add_method('setEncryption', array('@email.encryption@'))
		)
		->add('swift.mailer', Dependency_Definition::factory()
			->set_class('Swift_Mailer')
			->set_path('vendor/swiftmailer/lib/classes/Swift/Mailer')
			->add_argument('%swift.transport%')
			->set_shared(TRUE)
		)
	);
