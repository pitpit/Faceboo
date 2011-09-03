Faceboo
=======

Integrate Facebook SDK into Silex micro-framework


Features
--------
* provide an automated authorization mechanism (based on facebook oauth)
* work behind a non transparent proxy
* allow to use facebook api in Silex

Installation
------------

Get the sources:

    cd vendor
    git clone https://github.com/dpitard/Faceboo.git

Usage
-----

Register the namespace and the extension, in top of index.php:

    $app['autoloader']->registerNamespace('Faceboo', __DIR__.'/../vendor/Faceboo/src');

    $app->register(new Faceboo\Extension\FacebooExtension(), array(
        'fb.app_id' => 'YOUR_APP_ID'
    ));

Parameters:

* fb.app_id: you app id
* fb.secret: your app secret
* fb.permissions: array of facebook permissions needed to access the app
* fb.namespace: your app namespace
* fb.mode:
    * canvas: default mode, your app work under facebook iframe
    * website: not supported yet
* fb.class_path: define another path to reach Facebook PHP SDK
* fb.proxy: to make facebook api work behind non-transparent proxy
* fb.redirect: true|false, disable the redirection when accessing the server, in canvas mode
    
Protect every routes and ask user for permissions:
    
    $app['facebook']->auth();

    $app->match('/', function () use ($app) {
        //...
    });

In canvas mode, protect your canvas app from direct access to the server:

    $app['facebook']->redirect();

    * do not rely on it for security, it's based on HTTP refered so it's not safe

Get the missing permissions to access you app ():

    $app['facebook']->getMissingPermissions()

    * you need to define "permissions" parameter

Get the current facebook user id:

    $app['facebook']->getUser();

Call the Facebook api:

    $data =  $app['facebook']->api('/me);

Todo
----
* developp permissions authorization on website mode
* get rid of SilexEvent dependency to make it work with Symfony
* In canvas mode, override UrlGenerator to have the canvas URL when generate() is called with $absolute = true
* fan page
    * does the user like the fan page ?
    * route according to local
* custome auth on routes

    Protect only some routes and ask user for permissions:

        $app['facebook']->auth(array('test'));

        $app->match('/', function () use ($app) {
            //...
        });

        $app->match('/test', function () use ($app) {
            //...
        })->bind('test');

Changelog
---------
* added getMissingPermissions(), auth(), redirect()
* updated FacebooExtension
* added Facebook PHP SDK sources as vendor lib