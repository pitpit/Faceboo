<?php

namespace Faceboo\Routing\Generator;

use Symfony\Component\Routing\Generator\UrlGenerator as BaseUrlGenerator;
use Faceboo\Facebook;

class UrlGenerator extends BaseUrlGenerator
{
    protected $namespace;
    
    public function setNamespace($namespace)
    {
        $this->namespace = $namespace;
    }
    
    public function getBaseUrl()
    {
        return $this->context->getScheme().'://' . Facebook::APP_BASE_URL . '/' . $this->namespace;
    }
    
    public function generate($name, array $parameters = array(), $absolute = false)
    {
        if (null === $this->namespace || !$absolute) {
            return parent::generate($name, $parameters, $absolute);
        } else {
            return $this->getBaseUrl() . parent::generate($name, $parameters, false);
        }
    }
}
