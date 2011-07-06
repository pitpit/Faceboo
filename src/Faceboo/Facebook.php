<?php

namespace Faceboo;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Facebook as BaseFacebook;
use Silex\Application;

class Facebook extends BaseFacebook
{
    protected $app;
    
    protected  function debugLog($message)
    {
        if (isset($this->app['monolog'])) {
            $this->app['monolog']->addDebug($message);
        }
    }
    
    /**
     * Constructor
     * 
     * @param type $config 
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
        
        if (!isset($app['fb.app_id'])) {
            throw new \Exception("You must set \$app['fb.app_id']");
        }

        if (!isset($app['fb.secret'])) {
            throw new \Exception("You must set \$app['fb.secret']");
        }

        if (isset($app['fb.proxy'])) {
             self::$CURL_OPTS[CURLOPT_PROXY] = $app['fb.proxy'];
        }
              
        $config = array(
            'appId'  => $app['fb.app_id'],
            'secret' => $app['fb.secret'],
        );
        
        parent::__construct($config);
    }
    
    public function getCurrentUrl()
    {
        $qs = $this->app['request']->getQueryString();

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
        
        if (isset($this->app['fb.canvas']) && $this->app['fb.canvas']) {
            $url = $this->app['fb.canvas'] . $this->app['request']->getBaseUrl().$this->app['request']->getPathInfo().$query;
        } else {
            $url = $this->app['request']->getScheme().'://'.$this->app['request']->getHttpHost().$this->app['request']->getBaseUrl().$this->app['request']->getPathInfo().$query;
        }

        $this->debugLog(__METHOD__.'()| url='.$url);

        return $url;
    }
    
    public function getLoginUrl(array $params = array())
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
     * Get a response to redirect user to FB oAuth page (login and request perms)
     *
     * - works with javascript redirection in canvas mode
     * - works with 301 redirection in 3rd website mode
     * 
     * @return Response 
     */
    public function getLoginResponse(array $params)
    {
        $url = $this->getLoginUrl($params);
        
        if (isset($this->app['fb.canvas']) && $this->app['fb.canvas']) {

$html = <<< EOD
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <title></title>
        <script type="text/javascript" >
            top.location.href = "$url";
        </script>
    </head>
    <body></body>
</html>
EOD;

            $this->debugLog(__METHOD__.'()| canvas mode');

            return new Response($html);
        } else {
            
            $this->debugLog(__METHOD__.'()| full mode');
            
            return new RedirectResponse($url);
        }
    }
}
