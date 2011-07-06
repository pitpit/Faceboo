Faceboo
=======

Integrate Facebook SDK into Silex micro-framework

Requirements
------------

Installation
------------

Get the sources:

	cd vendor
	git clone https://dpitard@github.com/dpitard/Faceboo.git faceboo
	git clone https://github.com/facebook/php-sdk.git php-sdk

Usage
-----

Register the namespace and the extension, in top of index.php:

	$app['autoloader']->registerNamespace('Faceboo', __DIR__.'/../vendor/faceboo/src');
	$app->register(new Faceboo\Extension\FacebooExtension(), array(
		'fb.class_path' => __DIR__.'/../vendor/php-sdk/src',
		'fb.app_id' => 'YOUR_APP_ID',
		'fb.secret' => 'YOUR_APP_SECRET',
		//'fb.canvas' => 'http://apps.facebook.com/your_app_canvas_url'
	));

Get the current facebook user:

	$app['facebook']->getUser();
	
Ask for facebook authorization:

	$app->get('/', $closure = function () use ($app) {

		//if user is not authenticated, redirect to the autorization page
		//but dont ask authorization to the facebook scraper
		if (!$app['facebook']->getUser()
		&& (!$app['request']->server->has('HTTP_USER_AGENT')|| 0 !== strpos($app['request']->server->get('HTTP_USER_AGENT'), "facebookexternalhit"))) {

			//if access has been denied, provide an error message (should not happen in canvas mode)
			if ($app['request']->get('error') === "access_denied") {
				$response = new Response('Forbidden', 403);
			} else {
				$response = $app['facebook']->getLoginResponse(array('scope'=>'publish_stream')); //add here the autorization you need
			}
		}
		
		return $response;
		
		//...
	});