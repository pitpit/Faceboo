<?php

namespace Faceboo\Extension;

use Silex\Application;
use Silex\ExtensionInterface;

use Symfony\Component\ClassLoader\MapFileClassLoader;
use Faceboo\Routing\Generator\CanvasUrlGenerator;
use Faceboo\Facebook;

class FaceBooExtension implements ExtensionInterface
{
    public function register(Application $app)
    {
        $app['canvas_url_generator'] = $app->share(function () use ($app) {
            $app->flush();
            
            $context = clone $app['request_context'];
            
            if (isset($app['fb.canvas']) && $app['fb.canvas']) {
                $success = preg_match('/^(https?):\/\/([^\/]*)(\/[^\/]*)\/?$/', $app['fb.canvas'], $matches);
                
                if (false === $success) {
                    throw new \Exception("Invalid Facebook canvas URL. Check the value of \$app['fb.canvas']");
                }
                
                $context->setScheme($matches[1]);
                $context->setHost($matches[2]);
                $context->setBaseUrl($matches[3] );
            }
            
            return new CanvasUrlGenerator($app['routes'], $context);
        });
        
        if (!isset($app['fb.class_path'])) {
            throw new \Exception("Please set \$app['fb.class_path'] to the Facebook PHP SDK dir (https://github.com/facebook/php-sdk).");
        }
        
        require_once $app['fb.class_path'] . '/facebook.php';

        $app['facebook'] = $app->share(function () use ($app) {
            $app->register(new \Silex\Extension\SessionExtension());
            $app->flush();
            
            return new Facebook($app);
        });
    }
}
