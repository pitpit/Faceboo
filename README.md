Faceboo
=======

Integrate Facebook SDK into Silex micro-framework (FacebookServiceProvider) or Symfony2 (FacebooBundle).

Provide several methods to do common tasks with Facebook.
* authentification
* permissions management
* fan-gate management


Installation
------------

Add faceboo to your dependencies using composer:

    php composer.phar require "pitpit/faceboo":"2.0.*@dev"

To use Faceboo with Silex <= 1.0, please use:

    php composer.phar require "pitpit/faceboo":"1.0.*@dev"

Parameters
----------

* app_id: App ID
* secret: App Secret
* permissions: array of facebook [oAuth permissions](http://developers.facebook.com/docs/reference/api/permissions) needed for the app
* namespace: App namespace
* canvas: true if the app is called through facebook iframe
* proxy: to make facebook requests work behind non-transparent proxy
* timeout: ...
* connect_timeout: ...
* protect: true|false, disable the redirection when accessing the server, in canvas mode

Usage
-----

### Silex

Register the namespace and the extension, in top of index.php:

    $app->register(new Faceboo\Provider\FacebooServiceProvider(), array(
        'faceboo.app_id' => 'xxx',
        'faceboo.secret' => 'xxx'
    ));

> See above for a [complete list of avalaible parameters](#parameters).

Login and ask user for [Facebook oAuth permissions](http://developers.facebook.com/docs/reference/api/permissions):

    $app['faceboo.permissions'] = array();

    $app->match('/', function () use ($app) {

        if ($response = $app['faceboo']->auth()) return $response;

        //...
    });

In canvas mode, protect your canvas app from direct access to the source server:

    $app->before(function(Request $request) use ($app) {
        if ($response = $app['faceboo']->protect()) return $response;
    });

    * do not rely on it, it's based on HTTP_REFERER so it's not really secured

In a fan page tab, is the current user admin of the fan page :

    $app->match('/', function () use ($app) {

        $isAdmin = $app['faceboo']->isFanPageAdmin();

        //...
    }

    * you need to define "secret" parameter

In a fan page tab, what is the fan page id :

    $app->match('/', function () use ($app) {

        $pageId = $app['faceboo']->getFanPageId();

        //...
    }

    * you need to define "secret" parameter

In a fan page tab, does the current user like the fan page :

    $app->match('/', function () use ($app) {

        $isFan = $app['faceboo']->isFan();

        //...
    }

    * you need to define "secret" parameter

Get the current facebook user id:

    $app['faceboo']->getUser();

Call the Facebook api:

    $data =  $app['faceboo']->api('/me);

### Symfony2

Register the bundle in app/AppKernel.php:

        $bundles = array(
            //...
            new Faceboo\FacebooBundle\FacebooFacebooBundle(),
        );

Add the following in app/config/config.yml:

    faceboo:
        app_id: 297720976910223
        secret: b151a27351e91dab2ee18986d8c47052

> See above for a [complete list of avalaible parameters](#parameters).

Login and ask user for permissions if needed:

    public function indexAction()
    {
        if ($response = $this->get('faceboo')->auth()) return $response;

        //...
    }

TODO
----

* developp permissions authorization on website mode
* get rid of SilexEvent dependency to make it work with Symfony
* In canvas mode, override UrlGenerator to have the canvas URL when generate() is called with $absolute = true
* fan page
    * does the user like the fan page ?
    * route according to local


[![Bitdeli Badge](https://d2weczhvl823v0.cloudfront.net/pitpit/faceboo/trend.png)](https://bitdeli.com/free "Bitdeli Badge")

