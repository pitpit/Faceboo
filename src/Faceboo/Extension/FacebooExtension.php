<?php

namespace Faceboo\Extension;

use Silex\Application;
use Silex\ExtensionInterface;
use Silex\Extension\SessionExtension;
use Symfony\Component\ClassLoader\MapFileClassLoader;
use Faceboo\Routing\Generator\UrlGenerator;
use Faceboo\Facebook;;

class FaceBooExtension implements ExtensionInterface
{
    public function register(Application $app)
    {
        $app['url_generator'] = $app->share(function () use ($app) {
            $app->flush();
            
            $urlGenerator = new UrlGenerator($app['routes'], $app['request_context']);
            
            if (isset($app['fb.canvas']) && $app['fb.canvas'] && isset($app['fb.namespace'])) {
                $urlGenerator->setNamespace($app['fb.namespace']);
            }
            
            return $urlGenerator;
        });
        
        if (!isset($app['fb.class_path'])) {
            $app['fb.class_path'] = __DIR__ . '/../../../vendor/php-sdk/src';
        }
        
        require_once $app['fb.class_path'] . '/facebook.php';

        $app['facebook'] = $app->share(function () use ($app) {
            
            if (!isset($app['session'])) {
                $app->register(new SessionExtension());
            }
            $app->flush();
            
            $parameters = array('app_id', 'secret', 'namespace', 'canvas', 'proxy', 'permissions', 'redirect');
            $config = array();
            foreach($parameters as $parameter) {
                if (isset($app['fb.'.$parameter])) {

                    $config[$parameter] = $app['fb.'.$parameter];
                }
            }

            return new Facebook(
                    $app['session'],
                    $app['dispatcher'],
                    $config,
                    isset($app['monolog'])?$app['monolog']:null
                );
        });
    }
}
