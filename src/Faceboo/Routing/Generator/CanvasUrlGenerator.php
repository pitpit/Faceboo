<?php

namespace Faceboo\Routing\Generator;

use Symfony\Component\Routing\Generator\UrlGenerator;

class CanvasUrlGenerator extends UrlGenerator
{
    public function generate($name, array $parameters = array(), $absolute = false)
    {
        return parent::generate($name, $parameters, $absolute);
    }
}
