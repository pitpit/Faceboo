<?php

namespace Faceboo\FacebooBundle\EventListener;

use Faceboo\Facebook;

use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;

/**
 * FacebooListener
 *
 * @author Damien Pitard <damien.pitard@gmail.com>
 */
class FacebooListener
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
