<?php

namespace Faceboo\FacebookBundle\EventListener;

use Faceboo\Facebook;

use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;

/**
 * @author Damien Pitard <dpitard at digitas.fr>
 */
class FacebookListener
{
    public function __construct(Facebook $facebook)
    {
        $this->facebook = $facebook;
    }

    public function onKernelRequest(GetResponseEvent $event)
    {
        if (HttpKernelInterface::MASTER_REQUEST !== $event->getRequestType()) {
            return;
        }

        $request = $event->getRequest();
        $this->facebook->setRequest($request);
    }
}
