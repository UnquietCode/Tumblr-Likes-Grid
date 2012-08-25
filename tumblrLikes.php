<?php

# Arvin Castro, arvin@sudocode.net
# http://sudocode.net/article/351/authenticating-with-tumblr-using-oauth-in-php
# January 28, 2011

$consumer_token  = 'YOUR CONSUMER TOKEN;
$consumer_secret = 'YOUR CONSUMER SECRET';
$callbackURL     = 'URL OF THIS PAGE';

$requestTokenURL = 'http://www.tumblr.com/oauth/request_token';
$authorizeURL    = 'http://www.tumblr.com/oauth/authorize';
$accessTokenURL  = 'http://www.tumblr.com/oauth/access_token';
$tumblr_get_likes_api = "http://api.tumblr.com/v2/user/likes";
$tumblr_get_user_info_api = "http://api.tumblr.com/v2/user/info";

require 'class-xhttp-php/class.xhttp.php'; # uncomment if you don't use autoloading

session_name('tumblroauth');
session_start();

xhttp::load('profile,oauth');
$tumblr = new xhttp_profile();
$tumblr->oauth($consumer_token, $consumer_secret);
$tumblr->oauth_method('get'); // For compatability, OAuth values are sent as GET data

if(isset($_GET['logout'])) {
    $_SESSION = array();
    session_destroy();
    echo '(Thanks! You have successfully logged out.)<br/><br/><br/>';
}

if(isset($_GET['signin']) and !$_SESSION['loggedin']) {

    # STEP 2: Application gets a Request Token from Tumblr
    $data = array();
    $data['post']['oauth_callback'] = $callbackURL;
    $response = $tumblr->fetch($requestTokenURL, $data);

    if($response['successful']) {
        $var = xhttp::toQueryArray($response['body']);
        $_SESSION['oauth_token']        = $var['oauth_token'];
        $_SESSION['oauth_token_secret'] = $var['oauth_token_secret'];

        # STEP 3: Application redirects the user to Tumblr for authorization.
        header('Location: '.$authorizeURL.'?oauth_token='.$_SESSION['oauth_token'], true, 303);
        die();
		# STEP 4: (Hidden from Application)
		# User gets redirected to Tumblr.
		# Tumblr asks if she wants to allow the application to have access to her account.
		# She clicks on the "Allow" button.

    } else {
        echo 'Could not get token.<br><br>';
    }
}

# STEP 5: User gets redirected back to the application. Some GET variables are set by Tumblr
if($_GET['oauth_token'] == $_SESSION['oauth_token'] and $_GET['oauth_verifier'] and !$_SESSION['loggedin']) {

    # STEP 6: Application contacts Tumblr to exchange Request Token for an Access Token.
    $data = array();
    $data['get']['oauth_verifier'] = $_GET['oauth_verifier'];

    $tumblr->set_token($_SESSION['oauth_token'], $_SESSION['oauth_token_secret']);
    $response = $tumblr->fetch($accessTokenURL, $data);

    if($response['successful']) {

	    # STEP 7: Application now has access to the user's data,
	    # for reading protected entries, sending a post updates.
        $var = xhttp::toQueryArray($response['body']);

        $_SESSION['oauth_token'] = $var['oauth_token'];
        $_SESSION['oauth_token_secret'] = $var['oauth_token_secret'];
        $_SESSION['loggedin'] = true;
        
        // get rid of those nasty get variables from Tumblr
        header('Location: '.$callbackURL);
        
    } else {
       echo 'Unable to sign you in with Tumblr. Please try again later.<br><br>';
       // echo $response['body'];
    }
}

if(isset($_GET['getData']) and $_SESSION['loggedin']) {

    # Set access token
    $tumblr->set_token($_SESSION['oauth_token'], $_SESSION['oauth_token_secret']);
    $offset = filter_var($_GET['offset'], FILTER_SANITIZE_NUMBER_INT);

    $data = array();
    $data['get'] = array(
    	'offset'   => $offset,
    );

    $response = $tumblr->fetch($tumblr_get_likes_api, $data);

   if($response['successful']) {
     echo($response['body']); 
   } else {
     echo "{ failed : true }";
   }

   die();
}

if(isset($_GET['getUserName']) and $_SESSION['loggedin']) {
    # Set access token
    $tumblr->set_token($_SESSION['oauth_token'], $_SESSION['oauth_token_secret']);
    $offset = $_GET['offset'];

    $data = array();
    $data['post'] = array();

    $response = $tumblr->fetch($tumblr_get_user_info_api, $data);

   if($response['successful']) {
     echo($response['body']); 
   } else {
     echo "{ failed : true }";
   }

   die();
}

if($_SESSION['loggedin']) {
    include("likes.html");
} else {
  # STEP 1: User goes to a web application that she wants to use. She clicks on the "Sign in with Tumblr" button. */
    
   include("start.html"); 
}
