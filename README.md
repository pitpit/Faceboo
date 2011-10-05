Faceboo
=======

Integrate Facebook SDK into Silex micro-framework (FacebookServiceProvider) or Symfony2 (FacebooBundle).

Installation
------------

Get the sources:

    cd vendor
    git clone https://github.com/dpitard/Faceboo.git faceboo
    cd faceboo
    git submodule update --init

Usage
-----

### Silex

Register the namespace and the extension, in top of index.php:

    $app['autoloader']->registerNamespace('Faceboo', __DIR__.'/../vendor/faceboo/src');

    $app->register(new Faceboo\Provider\FacebookServiceProvider(), array(
        'facebook.app_id' => 'YOUR_APP_ID'
    ));

Parameters:

* facebook.app_id: you app id
* facebook.secret: your app secret
* facebook.permissions: array of facebook permissions needed to access the app
    * http://developers.facebook.com/docs/reference/api/permissions/
* facebook.namespace: your app namespace
* facebook.mode:
    * canvas: default mode, your app work under facebook iframe
    * website: not supported yet
* facebook.class_path: define another path to reach Facebook PHP SDK
* facebook.proxy: to make facebook api work behind non-transparent proxy
* facebook.redirect: true|false, disable the redirection when accessing the server, in canvas mode

Login and ask user for permissions if needed:
    
    $app['facebook.permissions'] = array();

    $app->match('/', function () use ($app) {

        if ($response = $app['facebook']->auth()) return $response;

        //...
    });

In canvas mode, protect your canvas app from direct access to the source server:

    $app->before(function(Request $request) use ($app) {
        if ($response = $app['facebook']->restrict()) return $response;
    });

    * do not rely on it for security, it's based on HTTP_REFERER so it's not safe

In a fan page tab, is the current user admin of the fan page :

    $app->match('/', function () use ($app) {

        $isAdmin = $app['facebook']->isFanPageAdmin();
        
        //...
    }

    * you need to define "secret" parameter

In a fan page tab, what is the fan page id :

    $app->match('/', function () use ($app) {

        $pageId = $app['facebook']->getFanPageId();
        
        //...
    }

    * you need to define "secret" parameter

In a fan page tab, does the current user like the fan page :

    $app->match('/', function () use ($app) {

        $isFan = $app['facebook']->isFan();
        
        //...
    }

    * you need to define "secret" parameter

Get the current facebook user id:

    $app['facebook']->getUser();

Call the Facebook api:

    $data =  $app['facebook']->api('/me);

### Symfony2

Register the autoload in app/autoload.php:

    $loader->registerNamespaces(array(
        //...
        'Faceboo'        => __DIR__.'/../vendor/faceboo/src'
    ));

    //...

    require_once __DIR__.'/../. /vendor/php-sdk/src/facebook.php';

Register the bundle in app/AppKernel.php:

        $bundles = array(
            //...
            new Faceboo\FacebookBundle\FacebooFacebookBundle(),
        );

Login and ask user for permissions if needed:
    
    public function indexAction()
    {   
        if ($response = $this->get('facebook')->auth()) return $response;
        
        //...
    }

Todo
----
* developp permissions authorization on website mode
* get rid of SilexEvent dependency to make it work with Symfony
* In canvas mode, override UrlGenerator to have the canvas URL when generate() is called with $absolute = true
* fan page
    * does the user like the fan page ?
    * route according to local

Changelog
---------
* app_id and secret are now mandatory
* updated to last version of Silex
* updated parameter prefix (now "facebook")