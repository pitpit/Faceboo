<?php

namespace Faceboo;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Facebook as FacebookBase;

use Silex\SilexEvents;

class Facebook extends FacebookBase
{
    protected $request;
    protected $session;
    protected $logger;
    protected $parameters;
    
    const APP_BASE_URL = 'apps.facebook.com';
    
    /**
     * store a debug trace
     * 
     * @param string $message 
     */
    protected  function debugLog($message)
    {
        if (null !== $this->logger) {
            $this->logger->addDebug($message);
        }
    }

    /**
     * Constructor
     * 
     * 
     * @param Symfony\Component\HttpFoundation\Request $request
     * @param array $parameters
     */
    public function __construct(Session $session, EventDispatcher $dispatcher, array $parameters = array(), $logger = null)
    {
        $this->session = $session;
        $this->dispatcher = $dispatcher;
        $this->logger = $logger;

        $this->parameters = array_merge($this->getDefaultParameters(), $parameters);

        if ($this->hasParameter('timeout')) {
             self::$CURL_OPTS[CURLOPT_TIMEOUT] = $this->getParameter('timeout');
        }
        
        if ($this->hasParameter('connect_timeout')) {
             self::$CURL_OPTS[CURLOPT_CONNECTTIMEOUT] = $this->getParameter('connect_timeout');
        }
        
        if ($this->hasParameter('proxy')) {
             self::$CURL_OPTS[CURLOPT_PROXY] = $this->getParameter('proxy');
        }
        
        $baseParameters = array(
            'appId' => isset($this->parameters['app_id'])?$this->parameters['app_id']:null,
            'secret' => isset($this->parameters['secret'])?$this->parameters['secret']:null,
        );

        //we want to avoir the session_start in parent::__construct()
        \BaseFacebook::__construct($baseParameters);
    }
    
    public function setRequest(Request $request)
    {
        $this->request = $request;
    }
    
    public function getDefaultParameters()
    {
        return array(
            'canvas' => true,
            'permissions' => array(),
            'redirect' => true
        );
    }
    
    public function getParameter($name)
    {
        if (!isset($this->parameters[$name])) {
            throw new \Exception(sprintf('Undefined parameters parameter "%s"', $name));
        }
        
        return $this->parameters[$name];
    }
    
    public function hasParameter($name)
    {
        return (isset($this->parameters[$name]));
    }
    
    /**
     * Get the permissions that the user did not provide
     * 
     * @return array
     */
    public function getMissingPermissions()
    {
        $needed = $this->getParameter('permissions');

        $userId = $this->getUser();
        if (!$userId) {
            
            if ($this->request && $this->request->query->get('state')) {
                //something goes wrong 
                //we get an authorisation but we are unable to get the user id
                //website mode : because the app is not reachable from facebook
                //canvas mode : because the app is in sandbox mode
                throw new \Exception('Unable to get the user id');
            }
            $this->debugLog(__METHOD__.'()| could not retrieve the user id');
             
            return $needed;
        }
        
        $data = $this->api('/' . $userId . '/permissions');
        
        if (!$data || !isset($data['data'][0])) {
            throw new \Exception(sprintf('Unable to get permissions of user %s', $userId));
        }
        
        $current = array_keys($data['data'][0]);
        $missing = array_diff($needed, $current);
        
        return $missing;
    }
    
    /**
     * @api
     */
    public function redirect()
    {
        if ($this->getParameter('canvas') && $this->getParameter('redirect')) {
            
            $facebook = $this;
            $this->dispatcher->addListener(SilexEvents::BEFORE, function (GetResponseEvent $event) use ($facebook) {

                $request = $event->getRequest();
                $facebook->setRequest($request);

                //if we are in canvas mode (iframe), but we tried to access the 
                //server directly
                 $pattern = '/^https?\:\/\/' . preg_quote($facebook::APP_BASE_URL). '/';

                 if (!$request->server->has('HTTP_REFERER')
                     || !preg_match($pattern, $request->server->get('HTTP_REFERER'))) {

                    $url = $facebook->getCurrentAppUrl();
                    $response = new RedirectResponse($url, 302);
                    $event->setResponse($response);
                }
            }, 1);
        }
    }
    
    /**
     * @api
     * @param array $routes
     */
    public function auth(Request $request, $redirectUri = null)
    {
        $this->setRequest($request);
        $missing = $this->getMissingPermissions();
        
        if (count($missing) > 0) {
            $params = array(
                'client_id' => $this->getParameter('app_id'),
                'scope' => implode(',', $missing)
            );

            //if we are in canvas mode (iframe), we need to redirect the parent
            if ($this->getParameter('canvas')) {

                $params['redirect_uri'] = $redirectUri?$redirectUri:$this->getCurrentAppUrl();
                $url = $this->getLoginUrl($params);

$html = <<< EOD
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<title>Restricted Area</title>
<script type="text/javascript" >
top.location.href = "$url";
</script>
</head>
<body></body>
</html>
EOD;
                return new Response($html, 403);

            } else {
                throw new \Exception('Not implemented yet');
            }
        }
        
        return null;
    }
    
    /**
     * Is the user fan of the facebook fan page where the app run (in a tab)
     * 
     * @api
     */
    public function isFan()
    {
        if (!$this->hasParameter('secret')) {
            throw new \Exception('You need to set the "secret" parameter');
        }

        $signedRequest = $this->getSignedRequest();

        if (null === $signedRequest || !isset($signedRequest['page']['liked'])) {
            $this->debugLog(__METHOD__.'()| The app have not been ran from from a page tab');
            
            return false;
        }
        
        return $signedRequest['page']['liked'];
    }
    
    /**
     * Does the user admin the fan page where the app run (in a tab)
     * 
     * @api
     * @return string
     */
    public function isFanPageAdmin()
    {
        if (!$this->hasParameter('secret')) {
            throw new \Exception('You need to set the "secret" parameter');
        }

        $signedRequest = $this->getSignedRequest();
        if (null === $signedRequest || !isset($signedRequest['page']['admin'])) {
            $this->debugLog(__METHOD__.'()| The app have not been ran from from a page tab');
            
            return false;
        }
        
        return $signedRequest['page']['admin'];
    }
    
    /**
     * Get the facebook fan page id where the run (in a tab)
     * 
     * @api
     * @return string|null
     */
    public function getFanPageId()
    {
        if (!$this->hasParameter('secret')) {
            throw new \Exception('You need to set the "secret" parameter');
        }

        $signedRequest = $this->getSignedRequest();
        if (null === $signedRequest || !isset($signedRequest['page']['id'])) {
            $this->debugLog(__METHOD__.'()| The app have not been ran from from a page tab');
            
            return null;
        }
        
        return $signedRequest['page']['id'];
    }
    
    /**
     * Get the relative URL (without scheme, hostname and port)
     * 
     * @return string
     */
    public function getRelativeUrl()
    {
        $qs = $this->request->getQueryString();

        $query = '';
        if (null !== $qs) {
          // drop known fb params
          $params = explode('&', $qs);
          $retainedParams = array();
          foreach ($params as $param) {
            if ($this->shouldRetainParam($param)) {
              $retainedParams[] = $param;
            }
          }

          if (!empty($retainedParams)) {
            $query = '?'.implode($retainedParams, '&');
          }
        }
        
        return $this->request->getBaseUrl().$this->request->getPathInfo().$query;
    }
    
    /**
     * In canvas mode, get the full URL (prefixed with the canvas URL)
     * 
     * @return string 
     */
    public function getCurrentAppUrl()
    {
        $url = $this->request->getScheme().'://' . self::APP_BASE_URL . '/' . $this->getParameter('namespace') . $this->getRelativeUrl();
                
        $this->debugLog(__METHOD__.'()| url='.$url);
        
        return $url;
    }
    
    /**
     * @see FacebookBase
     */
    public function getCurrentUrl()
    {   
        $url = $this->request->getScheme().'://'.$this->request->getHttpHost().$this->getRelativeUrl();

        $this->debugLog(__METHOD__.'()| url='.$url);

        return $url;
    }
    
    /**
     * @see FacebookBase
     */
    public function getLoginUrl($params = array())
    {
        $url = parent::getLoginUrl($params);
        
        $this->debugLog(__METHOD__.'()| url='.$url);
        
        return $url;
    }
    
    /**
    * Get the UID of the connected user, or 0
    * if the Facebook user is not connected.
    *
    * @return string the UID if available.
    */
    public function getUser()
    {
        $user = parent::getUser();
        
        $this->debugLog(__METHOD__.'()| user='.$user);
        
        return $user;
    }
    
    /**
     * @see FacebookBase
     */
    protected function setPersistentData($key, $value)
    {
        if (!in_array($key, self::$kSupportedKeys)) {
            throw new \Exception('Unsupported key passed to setPersistentData.');
        }

        $sessionVarName = $this->constructSessionVariableName($key);
        $this->session->set($sessionVarName, $value);
    }

    /**
     * @see FacebookBase
     */
    protected function getPersistentData($key, $default = false)
    {
        if (!in_array($key, self::$kSupportedKeys)) {
            throw new \Exception('Unsupported key passed to getPersistentData.');
        }

        $sessionVarName = $this->constructSessionVariableName($key);
        
        return ($this->session->has($sessionVarName))?$this->session->get($sessionVarName):$default;
    }

    /**
     * @see FacebookBase
     */
    protected function clearPersistentData($key)
    {
        if (!in_array($key, self::$kSupportedKeys)) {
            throw new \Exception('Unsupported key passed to clearPersistentData.');
        }

        $sessionVarName = $this->constructSessionVariableName($key);
        $this->session->remove($sessionVarName);
    } 
}
