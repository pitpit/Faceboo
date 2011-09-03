<?php

use Symfony\Component\ClassLoader\UniversalClassLoader;

$loader = new UniversalClassLoader();
$loader->registerNamespaces(array(
    'Faceboo' => __DIR__.'/src',
    'Symfony'   => __DIR__.'/vendor',
));
$loader->register();