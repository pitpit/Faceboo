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

/**
 * FacebooServiceProvider
 *
 * @author Damien Pitard <damien.pitard@gmail.com>
 */
class FacebooServiceProvider implements ServiceProviderInterface
{
    /**
     * {@inheritdoc}
     */
    public function register(Application $app)
    {
        $app['url_generator'] = $app->share(function () use ($app) {
            $urlGenerator = new UrlGenerator($app['routes'], $app['request_context']);

            if (isset($app['faceboo.canvas']) && $app['faceboo.canvas'] && isset($app['faceboo.namespace'])) {
                $urlGenerator->setNamespace($app['faceboo.namespace']);
            }

            return $urlGenerator;
        });

        $app['faceboo'] = $app->share(function () use ($app) {

            if (!isset($app['session'])) {
                $app->register(new SessionServiceProvider());
            }

            $parameters = array('app_id', 'secret', 'namespace', 'canvas', 'proxy', 'timeout', 'connect_timeout', 'permissions', 'protect');
            $config = array();
            foreach ($parameters as $parameter) {
                if (isset($app['faceboo.'.$parameter])) {
                    $config[$parameter] = $app['faceboo.'.$parameter];
                }
            }

            return new Facebook(
                $config,
                $app['session'],
                isset($app['monolog'])?$app['monolog']:null
            );
        });

        $app->before(function($request) use ($app) {
            $app['faceboo']->setRequest($request);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function boot(Application $app)
    {
    }
}
