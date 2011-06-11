<?php defined('SYSPATH') or die('No direct script access.');
/** 
 * These are the settings and what they do:
 *
 * class:        The class that is to be created.
 * path:         The path to the file containing the class. Will try to autoload the class if none is provided. Assumes ".php" extension.
 * constructor:  The method used to create the class. Will use "__construct()" if none is provided.
 * arguments:    The arguments to be passed to the constructor method.
 * shared:       The shared setting determines if the object will be cached.
 * methods:      Additional methods (and their arguments) that need to be called on the created object.
 */
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
