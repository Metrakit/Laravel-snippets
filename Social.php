<?php

class Social {

	private $timeout, $idTime, $url;

	/**
	 * Construct the social class
	 * @param Integer $timeout  Timeout
	 */
	function __construct($url, $idCache = "home", $timeout = 10) 
	{
		$this->url = rawurlencode($url);
		$this->idCache = $idCache;		
		$this->timeout = $timeout;
	}

	/**
	 * Get the tweets number
	 * @return Integer
	 */
	function tweetCount() 
	{
		return Cache::remember('twitter_count_' . $this->idCache, Config::get('cache.socialCount'), function()
		{
			$json_string = $this->file_get_contents_curl('http://urls.api.twitter.com/1/urls/count.json?url=' . $this->url);
			$json = json_decode($json_string, true);
			return isset($json['count'])?intval($json['count']) : 0;
		});
	}

	/**
	 * Get the LinkedIn shares number
	 * @return Integer
	 */
	function linkedInCount() 
	{
		return Cache::remember('linkedIn_count_' . $this->idCache, Config::get('cache.socialCount'), function()
		{		
			$json_string = $this->file_get_contents_curl("http://www.linkedin.com/countserv/count/share?url=$this->url&format=json");
			$json = json_decode($json_string, true);
			return isset($json['count'])?intval($json['count']) : 0;
		});
	}

	/**
	 * Get the Facebook shares number
	 * @return Integer
	 */
	function facebookCount() 
	{
		return Cache::remember('facebook_count_' . $this->idCache, Config::get('cache.socialCount'), function()
		{		
			$json_string = $this->file_get_contents_curl('http://api.facebook.com/restserver.php?method=links.getStats&format=json&urls='.$this->url);
			$json = json_decode($json_string, true);			
			return isset($json[0]['share_count']) ? intval($json[0]['share_count']) : 0;
		});
	}

	/**
	 * Get the Viadeo shares number
	 * @return Integer
	 */
	function viadeoCount() 
	{
		return Cache::remember('viadeo_count_' . $this->idCache, Config::get('cache.socialCount'), function()
		{	
			$json_string = $this->file_get_contents_curl('https://api.viadeo.com/recommend?url='.$this->url);
			$json = json_decode($json_string, true);
			return isset($json['count']) ? intval($json['count']) : 0;
		});
	}

	/**
	 * Get the Google shares number
	 * @return Integer
	 */
	function googleCount()  
	{
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, "https://clients6.google.com/rpc");
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_POSTFIELDS, '[{"method":"pos.plusones.get","id":"p","params":{"nolog":true,"id":"'.$this->url.'","source":"widget","userId":"@viewer","groupId":"@self"},"jsonrpc":"2.0","key":"p","apiVersion":"v1"}]');
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-type: application/json'));
		$curl_results = curl_exec ($curl);
		curl_close($curl);
		$json = json_decode($curl_results, true);
		return isset($json[0]['result']['metadata']['globalCounts']['count']) ? intval($json[0]['result']['metadata']['globalCounts']['count']) : 0;
	}

	/**
	 * Get URL content
	 * @return Integer
	 */
	private function file_get_contents_curl($url)
	{
		$ch=curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
		curl_setopt($ch, CURLOPT_FAILONERROR, 1);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
		curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
		$cont = curl_exec($ch);
		if (curl_error($ch)) {
			die(curl_error($ch));
		}
		return $cont;
	}


     /**
     * Share on Facebook
     */
    public static function facebookShare($params = null)
    { 

        // Init url
        $url = "https://www.facebook.com/dialog/feed?app_id=";

        // App ID
        $url .= (isset($params['app_id']) ? $params['app_id'] : Config::get('API.facebook.appId')) . "&link=";

        // Incremente the url
        $url .= (isset($params['url']) ? $params['url'] : Request::url()) . "&redirect_uri=";

        // Redirect URL
        $url .= isset($params['redirect_uri']) ? $params['redirect_uri'] : URL::route('social/close');

        // Title
        if (isset($params['name'])) {
             $url .= '&name=' . urlencode($params['name']);
        }

        // Description
        if (isset($params['description'])) {
             $url .= '&description=' . urlencode($params['description']);
        }
     
        // Caption
        if (isset($params['caption'])) {
             $url .= '&caption=' . urlencode($params['caption']);
        }       

        // Picture
        if (isset($params['picture'])) {
             $url .= '&picture=' . $params['picture'];
        }       

        return $url;
    }


     /**
     * Share on Twitter
     */
    public static function twitterShare($params = null)
    { 

        // Use only for count the characters (max: 140)
        $message = "";   	

        // Init url
        $url = "http://twitter.com/intent/tweet?via=";

        $via = isset($params['via']) ? $params['via'] : Config::get('API.twitter.accountName');

        // Via twitter account
        $url .= $via;

        // Text
        if (isset($params['text'])) {
             $url .= '&text=' . urlencode($params['text']);
             $message .= $params['text'];
        }   

        // Text
        if (isset($params['url'])) {
             $url .= '&url=' . urlencode($params['url']);
             $message .= " " . $params['url'];
        }   

        // Twitter account related
        if (isset($params['related'])) {
             $url .= '&related=' . urlencode($params['related']);
        }   

  		// Hashtags
        if (isset($params['hashtags'])) {
             $url .= '&hashtags=' . urlencode($params['hashtags']);
             $hashtags = explode(',', $params['hashtags']);
             foreach ($hashtags as $hashtag) {
             	$message .= " #$hashtag";
             }   
        }

        $message .= " @" . $via;

        if (strlen($message) > 140) {
        	Log::info(Lang::get('message.error.TweetTooLong', array('tweet' => $message)));
        }

        return $url;
    }


     /**
     * Share on Google
     */
    public static function googleShare($params = null)
    { 

        // Init url
        $url = "https://plus.google.com/share?url=";

        // Incremente the url
        $url .= (isset($params['url']) ? $params['url'] : Request::url());

        // Incremente the lang
        if (isset($params['lang'])) {
             $url .= '&hl=' . urlencode($params['lang']);
        }    

        return $url;
    }

     /**
     * Share on Google
     */
    public static function linkedINShare($params = null)
    { 

        // Init url
        $url = "http://www.linkedin.com/shareArticle??mini=true";

        // Incremente the url
        if (isset($params['url'])) {
             $url .= '&url=' . $params['url'];
        }    

        // Incremente the title
        if (isset($params['title'])) {
             $url .= '&title=' . urlencode($params['title']);
        }    

        // Incremente the source
        if (isset($params['source'])) {
             $url .= '&source=' . urlencode($params['source']);
        }  

        // Increment the description
        if (isset($params['text'])) {
             $url .= '&summary=' . urlencode($params['text']);
        }   

        return $url;
    }


     /**
     * Share on Viadeo
     */
    public static function viadeoShare($params = null)
    { 

        // Init url
        $url = "http://www.viadeo.com/shareit/share?url=";

        // Incremente the url
        $url .= (isset($params['url']) ? $params['url'] : Request::url());

        // Incremente the lang
        if (isset($params['title'])) {
             $url .= '&title=' . urlencode($params['title']);
        }    

        return $url;
    }


}
