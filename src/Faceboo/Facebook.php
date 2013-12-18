<?php

namespace Faceboo;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Facebook as FacebookBase;

/**
 * Facebook
 *
 * @author Damien Pitard <damien.pitard@gmail.com>
 */
class Facebook extends FacebookBase
{
    protected $session;
    protected $logger;
    protected $parameters;
    protected $request;

  /**
   * List of query parameters that get automatically dropped when rebuilding
   * the current URL.
   */
  protected static $DROP_QUERY_PARAMS = array(
    'code',
    'state',
    'signed_request',
    'url'
  );

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
    public function __construct(array $parameters = array(), SessionInterface $session, $logger = null)
    {
        $this->session = $session;
        $this->logger = $logger;

        $this->parameters = array_merge($this->getDefaultParameters(), $parameters);

        if (!$this->hasParameter('app_id')) {
            throw new \Exception('Missing "app_id" parameter');
        }

        if (!$this->hasParameter('secret')) {
            throw new \Exception('Missing "secret" parameter');
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
            'canvas' => false,
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

    public function getMissingPermissions()
    {
        $userId = $this->getUser();
        if (!$userId) {
            if ($this->request->query->get('state') && $this->request->query->get('error') !== 'access_denied' ) {
                $this->clearAllPersistentData();
                //something goes wrong
                //we get an authorisation but we are unable to get the user id
                //canvas mode : because the app is in sandbox mode
                throw new \Exception("Unable to get the facebook user id. Perhaps your app is in sandbox mode or maybe the access-token is expired. If your not in canvas mode, please load the Javascript-SDK to create a signed cookie.");
            }
        }
        $needed = $this->getParameter('permissions');

        if (!$userId) {

            return $needed;
        }

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
    public function auth($params = array(), $force = false)
    {
        // $userId = $this->getUser();
        // if (!$userId) {
        //     if ($this->request->query->get('state') && $this->request->query->get('error') !== 'access_denied' ) {
        //         $this->clearAllPersistentData();
        //         //something goes wrong
        //         //we get an authorisation but we are unable to get the user id
        //         //canvas mode : because the app is in sandbox mode
        //         throw new \Exception("Unable to get the facebook user id. Perhaps your app is in sandbox mode or maybe the access-token is expired. If your not in canvas mode, please load the Javascript-SDK to create a signed cookie.");
        //     }
        // }

        $missing = $this->getMissingPermissions();
        $needAuth = (count($missing) > 0);
        if ($needAuth && !$force && $this->request->query->get('state')) {
            $needAuth = false;
        }

        if ($needAuth) {
            $params = array_merge(array(
                'client_id' => $this->getParameter('app_id'),
                'scope' => implode(',', $missing),
            ), $params);

            //if we are in canvas mode (iframe), we need to redirect the parent
            if ($this->getParameter('canvas')) {

                if (!isset($params['redirect_uri'])) {
                    $params['redirect_uri'] = $this->getCurrentAppUrl();
                }

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
                if (!isset($params['redirect_uri'])) {
                    $params['redirect_uri'] = $this->getCurrentUrl();
                }

                $url = $this->getLoginUrl($params);

                return new RedirectResponse($url, 302);
            }
        }

        return null;
    }

    public function feed($caption, $link, $params = array())
    {
        $url = $this->getUrl(
            'www',
            'dialog/feed',
            array_merge(
                array(
                    'app_id' => $this->getAppId(),
                    'link' => $link, // possibly overwritten
                    'caption' => $caption,
                    'display' => 'page'
                ),
                $params
            )
        );

        return new RedirectResponse($url, 302);
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
        foreach ($pageIds as $pageId) {
            $requests[] =  array('method' => 'GET', 'relative_url' => "/$pageId");
        }

        $response = $this->api('/', 'POST', array(
            'batch' => json_encode($requests)
        ));

        if (!$response) {
            throw new \Exception('Unable to get the result of batch request');
        }

        $collection = array();
        foreach ($response as $result) {
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
        foreach ($pageIds as $pageId) {
            $requests[] =  array('method' => 'GET', 'relative_url' => "/$pageId/posts");
        }

        $response = $this->api('/', 'POST', array(
            'batch' => json_encode($requests)
        ));

        if (!$response) {
            throw new \Exception('Unable to get the result of batch request');
        }

        $collection = array();
        foreach ($response as $result) {
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
   * Returns true if and only if the key or key/value pair should
   * be retained as part of the query string.  This amounts to
   * a brute-force search of the very small list of Facebook-specific
   * params that should be stripped out.
   *
   * @param string $param A key or key/value pair within a URL's query (e.g.
   *                     'foo=a', 'foo=', or 'foo'.
   *
   * @return boolean
   */
  protected function shouldRetainParam($param) {
        $this->debugLog($param);
    foreach (self::$DROP_QUERY_PARAMS as $drop_query_param) {

        $this->debugLog($drop_query_param);

      if ($param === $drop_query_param ||
          strpos($param, $drop_query_param.'=') === 0) {
        return false;
      }
    }

    return true;
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
        // return $this->getRequest()->getBaseUrl().$this->getRequest()->getPathInfo();
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

    protected function getAccessTokenFromCode($code, $redirect_uri = null) {
        if (empty($code)) {
          return false;
        }

        if ($redirect_uri === null) {
          $redirect_uri = $this->getCurrentUrl();
        }

        try {
          // need to circumvent json_decode by calling _oauthRequest
          // directly, since response isn't JSON format.
          $access_token_response =
            $this->_oauthRequest(
              $this->getUrl('graph', '/oauth/access_token'),
              $params = array('client_id' => $this->getAppId(),
                              'client_secret' => $this->getAppSecret(),
                              'redirect_uri' => $redirect_uri,
                              'code' => $code));
        } catch (FacebookApiException $e) {
          // most likely that user very recently revoked authorization.
          // In any event, we don't have an access token, so say so.
          return false;
        }

        if (empty($access_token_response)) {
          return false;
        }

        $response_params = array();
        parse_str($access_token_response, $response_params);
        if (!isset($response_params['access_token'])) {

            $this->debugLog(__METHOD__.' - failed to get access token: ' .$access_token_response);
          return false;
        }

        return $response_params['access_token'];
    }

}
