<?php
/*
 *  Author: Robert Danklefsen
 *  Website: http://www.catchylabs.com
 *  Email: bobby@catchylabs.com
 *	
 *  This simple widget returns a simple twitter feed using standard OAuth now 
 *  required by Twitter's 1.1 API.
 *
 *  To use this, you must first set up an app in the developer tools at http://dev.twitter.com/apps
 *  Just sign in with your twitter account.
 *  Use the website that is going to access the feed as the app name and website.
 *  Once created, create Token keys and insert them in the appropriate fields at line 61.
 */

//function takes the time from the tweet and computes a "time ago"
function time_elapsed_string($ptime) {

    $etime = time() - $ptime;

    if ($etime < 1) {
        return '0 seconds';
    }

    $a = array( 12 * 30 * 24 * 60 * 60  =>  'year',
                30 * 24 * 60 * 60       =>  'month',
                24 * 60 * 60            =>  'day',
                60 * 60                 =>  'hour',
                60                      =>  'minute',
                1                       =>  'second'
                );

    foreach ($a as $secs => $str) {
        $d = $etime / $secs;
        if ($d >= 1) {
            $r = round($d);
            return $r . ' ' . $str . ($r > 1 ? 's' : '') . ' ago';
        }
    }
}

//makes urls out of links, @usernames, and hash tags.
function makeURLs($text) {
	// Match URLs
	$text = preg_replace('`\b(([\w-]+://?|www[.])[^\s()<>]+(?:\([\w\d]+\)|([^[:punct:]\s]|/)))`', '<a href="$0">$0</a>', $text);

	// Match @name
	$text = preg_replace('/(@)([a-zA-Z0-9\_]+)/', '@<a href="https://twitter.com/$2">$2</a>', $text);
							
	// Match #hashtag
	$text = preg_replace('/(#)([a-zA-Z0-9\_]+)/', '<a href="https://twitter.com/search/?q=%23$2">#$2</a>', $text);
	
	return $text;
	
}

//the magic. Returns the feed in a unorder listed.
//to use - echo getTwitterFeed();
function getTwitterFeed () {
	//need to create an app in twitter dev and get OAuth codes. Put them here
	$token = 'ACCESS TOKEN';
	$token_secret = 'ACCESS TOKEN SECRET';
	$consumer_key = 'CONSUMER KEY';
	$consumer_secret = 'CONSUMER SECRET KEY';
	
	$host = 'api.twitter.com';
	$method = 'GET';
	$path = '/1.1/statuses/user_timeline.json'; // api call path
	
	//edit these too.
	$query = array( // query parameters
	    'screen_name' => 'USERNAME',
	    'count' => '5' //0 returns all (limit is 200 i think)
	);
	
	$oauth = array(
	    'oauth_consumer_key' => $consumer_key,
	    'oauth_token' => $token,
	    'oauth_nonce' => (string)mt_rand(), // a stronger nonce is recommended
	    'oauth_timestamp' => time(),
	    'oauth_signature_method' => 'HMAC-SHA1',
	    'oauth_version' => '1.0'
	);
	
	$oauth = array_map("rawurlencode", $oauth); // must be encoded before sorting
	$query = array_map("rawurlencode", $query);
	
	$arr = array_merge($oauth, $query); // combine the values THEN sort
	
	asort($arr); // secondary sort (value)
	ksort($arr); // primary sort (key)
	
	// http_build_query automatically encodes, but our parameters
	// are already encoded, and must be by this point, so we undo
	// the encoding step
	$querystring = urldecode(http_build_query($arr, '', '&'));
	
	$url = "https://$host$path";
	
	// mash everything together for the text to hash
	$base_string = $method."&".rawurlencode($url)."&".rawurlencode($querystring);
	
	// same with the key
	$key = rawurlencode($consumer_secret)."&".rawurlencode($token_secret);
	
	// generate the hash
	$signature = rawurlencode(base64_encode(hash_hmac('sha1', $base_string, $key, true)));
	
	// this time we're using a normal GET query, and we're only encoding the query params
	// (without the oauth params)
	$url .= "?".http_build_query($query);
	$url=str_replace("&amp;","&",$url); //Patch by @Frewuill
	
	//echo $url;
	
	$oauth['oauth_signature'] = $signature; // don't want to abandon all that work!
	ksort($oauth); // probably not necessary, but twitter's demo does it
	
	// also not necessary, but twitter's demo does this too
	function add_quotes($str) { return '"'.$str.'"'; }
	$oauth = array_map("add_quotes", $oauth);
	
	// this is the full value of the Authorization line
	$auth = "OAuth " . urldecode(http_build_query($oauth, '', ', '));
	
	// if you're doing post, you need to skip the GET building above
	// and instead supply query parameters to CURLOPT_POSTFIELDS
	$options = array( CURLOPT_HTTPHEADER => array("Authorization: $auth"),
	                  //CURLOPT_POSTFIELDS => $postfields,
	                  CURLOPT_HEADER => false,
	                  CURLOPT_URL => $url,
	                  CURLOPT_RETURNTRANSFER => true,
	                  CURLOPT_SSL_VERIFYPEER => false);
	
	// do our business
	$feed = curl_init();
	curl_setopt_array($feed, $options);
	$json = curl_exec($feed);
	curl_close($feed);
	
	$twitter_data = json_decode($json,true);
	
		//print_r($twitter_data);
	
	if (empty($twitter_data)) {
		$code = 'There was an error';
	} else {
		$code = '<ul id="twitterFeed">';
		
		foreach($twitter_data as $tweets){
			$text = makeURLs($tweets['text']);
			$time = strtotime($tweets['created_at']);
			$url = 'http://twitter.com/'.$tweets['user']['screen_name'].'/status/'.$tweets['id'];
			$agoTime = time_elapsed_string($time);
			$code .= '<li>';
			$code .= '<span class="twitter-date"><a href="'.$url.'">'.$agoTime .'</a></span>';
			$code .= '<br />';
			$code .= '<span class="twitter-text">'. $text . '</span>';
			$code .= '</li>';

		}
		
		$code .= '</ul>';
	}
	
	return $code;

}

//for testing
//echo getTwitterFeed();

?>