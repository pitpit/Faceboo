<?php

namespace Faceboo\Provider;

use Silex\Application;
use Silex\ServiceProviderInterface;
use Silex\Provider\SessionServiceProvider;
use Silex\SilexEvents;
use Symfony\Component\ClassLoader\MapFileClassLoader;
use Faceboo\Routing\Generator\UrlGenerator;
use Faceboo\Facebook;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;

class FacebookServiceProvider implements ServiceProviderInterface
{
    public function register(Application $app)
    {
        $app['url_generator'] = $app->share(function () use ($app) {
            $urlGenerator = new UrlGenerator($app['routes'], $app['request_context']);
            
            if (isset($app['facebook.canvas']) && $app['facebook.canvas'] && isset($app['facebook.namespace'])) {
                $urlGenerator->setNamespace($app['facebook.namespace']);
            }
            
            return $urlGenerator;
        });
        
        if (!isset($app['facebook.class_path'])) {
            $app['facebook.class_path'] = __DIR__ . '/../../../vendor/facebook-php-sdk/src';
        }
        
        require_once $app['facebook.class_path'] . '/facebook.php';

        $app['facebook'] = $app->share(function () use ($app) {
            
            if (!isset($app['session'])) {
                $app->register(new SessionServiceProvider());
            }
            
            $parameters = array('app_id', 'secret', 'namespace', 'canvas', 'proxy', 'timeout', 'connect_timeout', 'permissions', 'protect');
            $config = array();
            foreach($parameters as $parameter) {
                if (isset($app['facebook.'.$parameter])) {
                    $config[$parameter] = $app['facebook.'.$parameter];
                }
            }

            return new Facebook(
                    $config,
                    $app['session'],
                    isset($app['monolog'])?$app['monolog']:null);
        });
        
        $app->before(function($request) use ($app) {
            $app['facebook']->setRequest($request);
        });
    }
    public function boot(Application $app)
    {
    }
}
