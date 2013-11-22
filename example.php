<?php

require 'sTwitter.php';


/** Set access tokens here - see: https://dev.twitter.com/apps/ **/
$settings = array(
    'oauth_access_token'        => "",
    'oauth_access_token_secret' => "",
    'consumer_key'              => "",
    'consumer_secret'           => "",
    'me'                        => '',
);


$myAccount = new sTwitter($settings);

if (!$myAccount->isFollowing('mac_hour')) {
    try {
        $myAccount->follow('mac_hour');
    } catch (Exception $e) {
        echo 'Unexisting account, or blocked from following' . "\n";
    }
} else {
    $myAccount->unfollow('mac_hour');    
}
