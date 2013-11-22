<?php

/**
 * Twitter API
 *
 * Forked from http://github.com/j7mbo/twitter-api-php
 *
 * @author   Mehdi Achour <@mac_hour>
 */
class sTwitter
{
    /**
     * oAuth settings
     * 
     * @var array
     */
    private $settings;
    private $postfields;
    private $getfield;
    protected $oauth;
    public $url;

    /**
     * Create the API access object. Requires an array of settings::
     * oauth access token, oauth access token secret, consumer key, consumer secret
     * These are all available by creating your own application on dev.twitter.com
     * 
     * Requires the cURL library
     * 
     * @param array $settings
     */
    public function __construct($settings)
    {   
        if (!function_exists('curl_init')) throw new Exception('You need to install cURL (http://php.net/curl)');
        if (!isset($settings['oauth_access_token'], $settings['oauth_access_token_secret'], $settings['consumer_key'], $settings['consumer_secret'])) {
            throw new Exception('Make sure you are passing in the correct parameters');
        }
        $this->settings = $settings;
    }

    private function _getUrl($controller, $action) 
    {
        return sprintf('https://api.twitter.com/1.1/%s/%s.json', $controller, $action);
    }

    public function tweet($status) 
    {
        return $this->performRequest('statuses', 'update', array(
                'status' => $status
            ), 'POST');
    }

    public function isFollowing($target) {
        return $this->performRequest('friendships', 'show', array(
                'target_screen_name' => $target,
            ))->relationship->source->following;
    }

    public function follow($target) {
        return $this->performRequest('friendships', 'create', array(
                'screen_name' => $target,
            ), 'POST');
    }

    public function unfollow($target) {
        return $this->performRequest('friendships', 'destroy', array(
                'screen_name' => $target,
            ), 'POST');
    }
    
    /**
     * Build the Oauth object using params set in construct and additionals
     * passed to this method. For v1.1, see: https://dev.twitter.com/docs/api/1.1
     * 
     * @param string $url The API url to use. Example: https://api.twitter.com/1.1/search/tweets.json
     * @param string $requestMethod Either POST or GET
     * @return \TwitterAPIExchange Instance of self for method chaining
     */
    public function buildOauth($url, $requestMethod)
    {
        if (!in_array(strtolower($requestMethod), array('post', 'get')))
        {
            throw new Exception('Request method must be either POST or GET');
        }
        
        $oauth = array( 
            'oauth_consumer_key'     => $this->settings['consumer_key'],
            'oauth_nonce'            => time(),
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_token'            => $this->settings['oauth_access_token'],
            'oauth_timestamp'        => time(),
            'oauth_version'          => '1.0'
        );
        

        if (!is_null($this->getfield)) {
            $gFields = str_replace('?', '', explode('&', $this->getfield));
            foreach ($gFields as $g) {
                $split = explode('=', $g);
                $oauth[$split[0]] = $split[1];
            }
        }
        
        $base_info = $this->buildBaseString($url, $requestMethod, $oauth);
        $composite_key = rawurlencode($this->settings['consumer_secret']) . '&' . rawurlencode($this->settings['oauth_access_token_secret']);
        $oauth_signature = base64_encode(hash_hmac('sha1', $base_info, $composite_key, true));
        $oauth['oauth_signature'] = $oauth_signature;
        
        $this->url   = $url;
        $this->oauth = $oauth;
        
        return $this;
    }
    
    /**
     * Perform the actual data retrieval from the API
     * 
     * @param boolean $return If true, returns data.
     * 
     * @return string json If $return param is true, returns json data.
     */
    public function performRequest($controller, $action, $params, $method = 'GET')
    {
        $method == 'POST' ? $this->setPostfields($params) : $this->setGetfield($params);

        $this->buildOauth($this->_getUrl($controller, $action), $method);
        
        $header = array($this->buildAuthorizationHeader($this->oauth), 'Expect:');

        $options = array( 
            CURLOPT_HTTPHEADER     => $header,
            CURLOPT_HEADER         => false,
            CURLOPT_URL            => $this->url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
        );

        if ($method == 'POST') {
            $options[CURLOPT_POSTFIELDS] = $this->postfields;
        } elseif ($this->getfield !== '') {
            $options[CURLOPT_URL] .= $this->getfield;
        }

        $curl = curl_init();
        curl_setopt_array($curl, $options);
        $json = curl_exec($curl);
        curl_close($curl);

        $this->postfields = $this->getfield = null;

        $response = json_decode($json);
        //var_dump($response);
        if (isset($response->errors)) {
            throw new \Exception($response->errors[0]->message, $response->errors[0]->code);
        }

        return $response;
    }
    
    /**
     * Private method to generate the base string used by cURL
     * 
     * @param string $baseURI
     * @param string $method
     * @param array $params
     * 
     * @return string Built base string
     */
    private function buildBaseString($baseURI, $method, $params) 
    {
        $return = array();
        ksort($params);
        
        foreach($params as $key=>$value)
        {
            $return[] = "$key=" . $value;
        }
        
        return $method . "&" . rawurlencode($baseURI) . '&' . rawurlencode(implode('&', $return)); 
    }
    
    /**
     * Private method to generate authorization header used by cURL
     * 
     * @param array $oauth Array of oauth data generated by buildOauth()
     * 
     * @return string $return Header used by cURL for request
     */    
    private function buildAuthorizationHeader($oauth) 
    {

        $values = array();
        
        foreach ($oauth as $key => $value) {
            $values[] = "$key=\"" . rawurlencode($value) . "\"";
        }
        
        return 'Authorization: OAuth ' . implode(', ', $values);
    }

    /**
     * Set postfields array, example: array('screen_name' => 'J7mbo')
     * 
     * @param array $array Array of parameters to send to API
     * 
     * @return TwitterAPIExchange Instance of self for method chaining
     */
    private function setPostfields(array $array)
    {
        
        if (isset($array['status']) && substr($array['status'], 0, 1) === '@') {
            $array['status'] = sprintf("\0%s", $array['status']);
        }
        
        $this->postfields = $array;
        
        return $this;
    }
    
    /**
     * Set getfield string, example: '?screen_name=J7mbo'
     * 
     * @param string $string Get key and value pairs as string
     * 
     * @return \TwitterAPIExchange Instance of self for method chaining
     */
    private function setGetfield($string)
    {
        $string = '?' . http_build_query($string);
        $search  = array('#', ',', '+', ':');
        $replace = array('%23', '%2C', '%2B', '%3A');
        $string  = str_replace($search, $replace, $string);  
        
        $this->getfield = $string;
        
        return $this;
    }

}
