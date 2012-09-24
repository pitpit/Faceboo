<?php

namespace Faceboo;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Facebook as FacebookBase;


class Facebook extends FacebookBase
{
    protected $session;
    protected $logger;
    protected $parameters;
    protected $request;
    
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
     */
    public function __construct(array $parameters = array(), Session $session, $logger = null)
    {
        $this->session = $session;
        $this->logger = $logger;

        $this->parameters = array_merge($this->getDefaultParameters(), $parameters);
        
        if (!$this->hasParameter('app_id')) {
            throw new \Exception('You need to set the "app_id" parameter');
        }
        
        if (!$this->hasParameter('secret')) {
            throw new \Exception('You need to set the "secret" parameter');
        }

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

        $this->session->start();

        //we want to avoir the session_start in parent::__construct()
        \BaseFacebook::__construct($baseParameters);
    }
    
    public function getRequest()
    {
        if (null === $this->request) {
            throw new \Exception('Request is undefined. Use setRequest()');
        }
        
        return $this->request;
    }
    
    public function setRequest(Request $request)
    {
        $this->request = $request;
    }
    
    protected function getDefaultParameters()
    {
        return array(
            'canvas' => true,
            'permissions' => array(),
            'protect' => true
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
     * @api
     */
    public function protect()
    {
        if ($this->getParameter('canvas') && $this->getParameter('protect')) {
            
            //if we are in canvas mode (iframe), but we tried to access the 
            //server directly

        	$pattern = '/^https?\:\/\/' . preg_quote(self::APP_BASE_URL). '/';

             if (!$this->getRequest()->server->has('HTTP_REFERER')
                 || !preg_match($pattern, $this->getRequest()->server->get('HTTP_REFERER'))) {

                $url = self::getCurrentAppUrl();
                
                return new RedirectResponse($url, 302);
            }
        }
        
        return null;
    }
    
    public function getMissingPermissions($userId)
    {
        $needed = $this->getParameter('permissions');

        try {
            $data = $this->api('/' . $userId . '/permissions');

            if (!$data || !isset($data['data'][0])) {
                throw new \Exception(sprintf('Unable to get permissions of user %s', $userId));
            }
        } catch(\FacebookApiException $e) {
            //user has revoked all the permissions
            if ($e->getType() === 'OAuthException') {
                return $needed;
            } else {
                throw $e;
            }
        }

        $current = array_keys($data['data'][0]);
        $missing = array_diff($needed, $current);

        return $missing;
    }

    
    /**
     * @api
     * @param array $routes
     */
    public function auth($redirectUri = null)
    {
        $auth = false;
        $userId = $this->getUser();
        if (!$userId) {
            
            if ($this->request->query->get('state') && $this->request->query->get('error') !== 'access_denied' ) {
                //something goes wrong
                //we get an authorisation but we are unable to get the user id
                //canvas mode : because the app is in sandbox mode
                throw new \Exception("Unable to get the facebook user id. Perhaps your app is in sandbox mode or maybe the access-token is expired. If your not in canvas mode, please load the Javascript-SDK to create a signed cookie.");
            }
            $auth = true;
            $missing = $this->getParameter('permissions');
        } else {
            $missing = $this->getMissingPermissions($userId);
            if (count($missing)>0) {
                $auth = true;
            }
        }
        
        if ($auth) {
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
                $params['redirect_uri'] = $redirectUri?$redirectUri:$this->getCurrentUrl();
                $url = $this->getLoginUrl($params);
                
                return new RedirectResponse($url, 302);
            }
        }
        
        return null;
    }
    
    /**
     * Is the user fan of the facebook fan page where the app run.
     * - tabbed app only
     * 
     * @api
     */
    public function isFan()
    {
        $signedRequest = $this->getSignedRequest();

        if (null === $signedRequest || !isset($signedRequest['page']['liked'])) {
            $this->debugLog(__METHOD__.'()| The app have not been ran from from a page tab');
            
            return false;
        }
        
        return $signedRequest['page']['liked'];
    }
    
    /**
     * Does the user admin the fan page where the app run.
     * - tabbed app only
     * 
     * @api
     * @return string
     */
    public function isPageAdmin()
    {
        $signedRequest = $this->getSignedRequest();
        if (null === $signedRequest || !isset($signedRequest['page']['admin'])) {
            $this->debugLog(__METHOD__.'()| The app have not been ran from from a page tab');
            
            return false;
        }
        
        return $signedRequest['page']['admin'];
    }
    
    /**
     * Get the facebook fan page id where the app run.
     * - tabbed app only
     * 
     * @api
     * @return string|null
     */
    public function getPageId()
    {
        $signedRequest = $this->getSignedRequest();
        if (null === $signedRequest || !isset($signedRequest['page']['id'])) {
            $this->debugLog(__METHOD__.'()| The app have not been ran from from a page tab');
            
            return null;
        }
        
        return $signedRequest['page']['id'];
    }

        /**
     * Get every posts of a page of multiple pages
     * 
     * 
     * @param string $pageId
     */
    public function getPage($pageId)
    {
        $response = $this->api("/$pageId");

        if (!$response) {
            throw new \Exception(sprintf('Unable to get permissions of user %s', $userId));
        }
        
        return $response;
    }
    
    /**
     * Get every posts of a page of multiple pages
     * 
     * 
     * @param array $pageIds 
     */
    public function getMultiPages(array $pageIds)
    {
        if (count($pageIds) === 0) {
            return array();
        } else if (count($pageIds) === 1) {
            return array($this->getPage($pageIds[0]));
        }
        
        $requests = array();
        foreach($pageIds as $pageId) {
            $requests[] =  array('method' => 'GET', 'relative_url' => "/$pageId");
        }
        
        $response = $this->api('/', 'POST', array(
            'batch' => json_encode($requests)
        ));
        
        if (!$response) {
            throw new \Exception('Unable to get the result of batch request');
        }
        
        $collection = array();
        foreach($response as $result) {
            if ($result['code'] == 200 && isset($result['body'])) {
                $data = json_decode($result['body'], true);
                if (!$data) {
                    throw new \Exception('Unable decode json');
                }
                $collection = array_merge($collection, array($data));
            } else {
                throw new \Exception('Unable to process one response of the batch');
            }
        }
        
        return $collection;
    }
    
    /**
     * Get every posts of a page of multiple pages
     * 
     * 
     * @param string $pageId
     */
    public function getPagePosts($pageId)
    {
        $response = $this->api("/$pageId/posts");

        if (!$response || !isset($response['data'])) {
            throw new \Exception(sprintf('Unable to get permissions of user %s', $userId));
        }
        
        return $response['data'];
    }
    
    /**
     * Get every posts of a page of multiple pages
     * 
     * 
     * @param array $pageIds 
     */
    public function getMultiPagesPosts(array $pageIds)
    {
        if (count($pageIds) === 0) {
            return array();
        } else if (count($pageIds) === 1) {
            return array($this->getPagePosts($pageIds[0]));
        }
        
        $requests = array();
        foreach($pageIds as $pageId) {
            $requests[] =  array('method' => 'GET', 'relative_url' => "/$pageId/posts");
        }
        
        $response = $this->api('/', 'POST', array(
            'batch' => json_encode($requests)
        ));
        
        if (!$response) {
            throw new \Exception('Unable to get the result of batch request');
        }
        
        $collection = array();
        foreach($response as $result) {
            if ($result['code'] == 200 && isset($result['body'])) {
                $data = json_decode($result['body'], true);
                if (!$data) {
                    throw new \Exception('Unable decode json');
                }
                $collection = array_merge($collection, $data['data']);
            } else {
                throw new \Exception('Unable to process one response of the batch');
            }
        }

        usort($collection, function($a, $b) {
            return ($a['created_time'] < $b['created_time'])?1:-1;
        });
        
        return $collection;
    }
    
    /**
     * Get the relative URL (without scheme, hostname and port)
     * 
     * @return string
     */
    public function getRelativeUrl()
    {
        $qs = $this->getRequest()->getQueryString();

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
        
        return $this->getRequest()->getBaseUrl().$this->getRequest()->getPathInfo().$query;
    }
    
    /**
     * In canvas mode, get the full URL (prefixed with the canvas URL)
     * 
     * @return string 
     */
    public function getCurrentAppUrl()
    {
        if (!$this->hasParameter('namespace') && $this->getParameter('canvas') && isset($_SERVER['HTTP_REFERER'])) {
            $url = rtrim($_SERVER['HTTP_REFERER'], '/');
        } else {
            $url = $this->getRequest()->getScheme().'://' . self::APP_BASE_URL . '/' . $this->getParameter('namespace') . $this->getRelativeUrl();
        }
        
        $this->debugLog(__METHOD__.'()| url='.$url);
        
        return $url;
    }
    
    /**
     * @see FacebookBase
     */
    public function getCurrentUrl()
    {   
        $url = $this->getRequest()->getScheme().'://'.$this->getRequest()->getHttpHost().$this->getRelativeUrl();

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
        
        $this->debugLog(__METHOD__.'setPersistentData()| key='.$key.', value='.$value);
        
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
        
        $value = ($this->session->has($sessionVarName))?$this->session->get($sessionVarName):$default;
         
        $this->debugLog(__METHOD__.'setPersistentData()| key='.$key.', value='.$value);

        return $value;
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
