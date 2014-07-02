<?php
require_once('oauth-php/library/OAuthStore.php');
require_once('oauth-php/library/OAuthRequester.php');
require_once('oauth-php/library/OAuthRequestLogger.php');
require_once('nice-json.php');

DEFINE('OAUTH_LOG_REQUEST', true);

$connection = array('server' => 'localhost', 
    'username' => 'root', 
    'password' => '', 
    'database' => 'oauth_consumer');

OAuthStore::instance('MySQL', $connection);

DEFINE('SERVER_BASE', 'http://localhost/xibo/1.6/server-162/server/');
DEFINE('CONSUMER_KEY', 'e982575d2ab70546923b92e50c5b96ca053b407a8');
DEFINE('CONSUMER_SECRET', 'a891f97e69985230a2e0e869b9f875e3');
//DEFINE('SERVER_BASE', 'http://unittest2.xibo.org.uk/api/');
//DEFINE('CONSUMER_KEY', '201798cda77e4e82e0488d0c8c2e43ae0519d180f');
//DEFINE('CONSUMER_SECRET', '9eb4aa8a51e4a393b3fb5ad6f1a75bae');
//

// $RESPONSE = 'xml';
define('RESPONSE', 'json');

switch((isset($_GET['action']) ? $_GET['action'] : ''))
{
    case 'AddServer':
        AddServerToOAuth();
        break;

    case 'ObtainAccess':
        ObtainAccessToAServer();
        break;

    case 'Exchange':
        ExchangeRequestForAccess();
        break;

    case 'Request':
        MakeSignedRequest();
        break;

    case '':
        die('No action');

    default:
        $action = $_GET['action'];
        $action();
}

function AddServerToOAuth()
{
    // Get the id of the current user (must be an int)
    $user_id = 1;
    $store = OAuthStore::instance();

    // The server description
    $server = array(
        'consumer_key' => CONSUMER_KEY,
        'consumer_secret' => CONSUMER_SECRET,
        'signature_methods' => array('HMAC-SHA1', 'PLAINTEXT'),
        'server_uri' => SERVER_BASE . 'services.php?service=rest',
        'request_token_uri' => SERVER_BASE . 'services.php?service=oauth&method=request_token',
        'authorize_uri' => SERVER_BASE . 'index.php?p=oauth&q=authorize',
        'access_token_uri' => SERVER_BASE . 'services.php?service=oauth&method=access_token'
    );

    // Save the server in the the OAuthStore
    $consumer_key = $store->updateServer($server, $user_id);

    echo('Server Added');
}

function ObtainAccessToAServer()
{
    // You request servers using their consumer key
    $user_id = 1;

    // Obtain a request token from the server
    try
    {
        $token = OAuthRequester::requestRequestToken(CONSUMER_KEY, $user_id);
    }
    catch (OAuthException $e)
    {
        echo $e->getMessage();
        die('Failed');
    }

    // Callback to our (consumer) site, will be called when the user finished the authorization at the server
    $callback_uri = '?action=Exchange&consumer_key='.rawurlencode(CONSUMER_KEY).'&usr_id='.intval($user_id);

    // Now redirect to the autorization uri and get us authorized
    if (!empty($token['authorize_uri']))
    {
        // Redirect to the server, add a callback to our server
        if (strpos($token['authorize_uri'], '?'))
        {
            $uri = $token['authorize_uri'] . '&';
        }
        else
        {
            $uri = $token['authorize_uri'] . '?';
        }
        $uri .= 'oauth_token='.rawurlencode($token['token']).'&oauth_callback='.rawurlencode($callback_uri);
    }
    else
    {
        // No authorization uri, assume we are authorized, exchange request token for access token
       $uri = $callback_uri . '&oauth_token='.rawurlencode($token['token']);
    }

    header('Location: '.$uri);
    exit();
}

function ExchangeRequestForAccess()
{
    // Request parameters are oauth_token, consumer_key and usr_id.
    $consumer_key = $_GET['consumer_key'];
    $oauth_token = $_GET['oauth_token'];
    $user_id = $_GET['usr_id'];

    try
    {
        OAuthRequester::requestAccessToken($consumer_key, $oauth_token, $user_id);
    }
    catch (OAuthException $e)
    {
        // Something wrong with the oauth_token.
        // Could be:
        // 1. Was already ok
        // 2. We were not authorized
        die($e->getMessage());
    }
    
    echo 'Authorization Given. <a href="index.php?action=Request">Click to make a signed request</a>.';
}

function MakeSignedRequest()
{
    // The request uri being called.
    $user_id = 1;
    $request_uri = SERVER_BASE . 'services.php';

    // Parameters, appended to the request depending on the request method.
    // Will become the POST body or the GET query string.
    $params = array(
               'service' => 'rest',
               'method' => 'Version',
               'response' => RESPONSE
         );

    // Obtain a request object for the request we want to make
    $req = new OAuthRequester($request_uri, 'GET', $params);

    // Sign the request, perform a curl request and return the results, throws OAuthException exception on an error
    $result = $req->doRequest($user_id);

    // $result is an array of the form: array ('code'=>int, 'headers'=>array(), 'body'=>string)
    var_dump($result);
    echo $result['body'];
}

function LayoutList()
{
    // The request uri being called.
    $user_id = 1;
    $request_uri = SERVER_BASE . 'services.php';

    // Parameters, appended to the request depending on the request method.
    // Will become the POST body or the GET query string.
    $params = array(
               'service' => 'rest',
               'method' => 'LayoutList',
               'response' => RESPONSE
         );

    // Obtain a request object for the request we want to make
    $req = new OAuthRequester($request_uri, 'GET', $params);

    // Sign the request, perform a curl request and return the results, throws OAuthException exception on an error
    $result = $req->doRequest($user_id);

    // $result is an array of the form: array ('code'=>int, 'headers'=>array(), 'body'=>string)
    var_dump($result['code']);
    var_dump($result['headers']);
    var_dump($result['body']);

    echo $result['body'];

    $xml = new DOMDocument();
    $xml->loadXML($result['body']);
    
    foreach($xml->getElementsByTagName('layout') as $layout) {
        echo 'Title: ' . $layout->getAttribute('layout') . '<br/>';
        echo 'Description: ' . $layout->getAttribute('description') . '<br/>';
    }
}

function LayoutRegionList()
{
    // The request uri being called.
    $user_id = 1;
    $request_uri = SERVER_BASE . 'services.php';

    // Parameters, appended to the request depending on the request method.
    // Will become the POST body or the GET query string.
    $params = array(
               'service' => 'rest',
               'method' => 'LayoutRegionList',
               'response' => RESPONSE,
               'layoutid' => 11
         );

    // Obtain a request object for the request we want to make
    $req = new OAuthRequester($request_uri, 'GET', $params);

    // Sign the request, perform a curl request and return the results, throws OAuthException exception on an error
    $result = $req->doRequest($user_id);

    // $result is an array of the form: array ('code'=>int, 'headers'=>array(), 'body'=>string)
    var_dump($result['code']);
    var_dump($result['headers']);
    var_dump($result['body']);

    echo $result['body'];

    $xml = new DOMDocument();
    $xml->loadXML($result['body']);
    
    foreach($xml->getElementsByTagName('layout') as $layout) {
        echo 'Title: ' . $layout->getAttribute('layout') . '<br/>';
        echo 'Description: ' . $layout->getAttribute('description') . '<br/>';
    }
}

function LayoutAdd()
{
    $params = array(
               'service' => 'rest',
               'method' => 'LayoutAdd',
               'response' => RESPONSE,
               'layout' => 'API test'
         );

    callService($params, true);
}

function LayoutRegionAdd() {

    $params = array(
               'service' => 'rest',
               'method' => 'LayoutRegionAdd',
               'response' => RESPONSE,
               'layoutid' => 11,
               'top' => 102,
               'name' => 'apitest'
         );

    callService($params, true);
}

function LayoutRegionEdit() {

    
    $params = array(
               'service' => 'rest',
               'method' => 'LayoutRegionEdit',
               'response' => RESPONSE,
               'layoutid' => 124,
               'regionid' => '519d199c5cb50',
               'width' => 400,
               'height' => 400,
               'left' => 50,
               'top' => 53,
               'name' => 'apitest'
         );

    callService($params, true);
}

function LayoutRegionDelete() {

    
    $params = array(
               'service' => 'rest',
               'method' => 'LayoutRegionDelete',
               'response' => RESPONSE,
               'layoutid' => 124,
               'regionid' => '519d1bb00e7a9'
         );

    callService($params, true);
}

function LayoutRegionTimelineList() {
    $params = array(
               'service' => 'rest',
               'method' => 'LayoutRegionTimelineList',
               'response' => RESPONSE,
               'layoutid' => 11,
               'regionid' => '519d211ded076'
         );

    callService($params, true);
}

function LayoutRegionMediaAdd() {

    $params = array(
               'service' => 'rest',
               'method' => 'LayoutRegionMediaAdd',
               'response' => RESPONSE,
               'layoutid' => 11,
               'regionid' => '519d211ded076',
               'type' => 'webpage',
               'xlf' => '<?xml version="1.0"?>
<media id="5107af8fa6b6b0ca09cfab938a6bed19" type="webpage" duration="30" schemaVersion="1">
        <options><uri>https%3A%2F%2Fwww.gust.edu.kw%2F</uri><scaling>100</scaling><transparency>0</transparency><offsetLeft>0</offsetLeft><offsetTop>0</offsetTop></options>
        <raw/>
</media>'
         );

    callService($params, true);
}

function LayoutRegionMediaDetails() {

    $params = array(
               'service' => 'rest',
               'method' => 'LayoutRegionMediaDetails',
               'response' => RESPONSE,
               'layoutid' => 11,
               'regionid' => '519d211ded076',
               'mediaid' => 'b2036df53ae2bdcbb5322a183709afbc',
               'type' => 'webpage'
         );

    callService($params, true);

}

function LayoutRegionMediaEdit() {
    
    $params = array(
               'service' => 'rest',
               'method' => 'LayoutRegionMediaEdit',
               'response' => RESPONSE,
               'layoutid' => 11,
               'regionid' => '519d211ded076',
               'type' => 'webpage',
               'mediaid' => 'b2036df53ae2bdcbb5322a183709afbc',
               'xlf' => '<?xml version="1.0"?>
<media id="b2036df53ae2bdcbb5322a183709afbc" type="webpage" duration="50" schemaVersion="1" userId="1">
        <options><uri>https%3A%2F%2Fwww.gust.edu.kw%2F</uri><scaling>100</scaling><transparency>0</transparency><offsetLeft>0</offsetLeft><offsetTop>0</offsetTop></options>
        <raw/>
</media>'
         );

    callService($params, true);
}

function DataSetList() {
    $params = array(
            'service' => 'rest',
            'method' => 'DataSetList',
            'response' => RESPONSE
        );

    callService($params, true);
}

function DataSetAdd() {
    $params = array(
            'service' => 'rest',
            'method' => 'DataSetAdd',
            'response' => RESPONSE,
            'dataSet' => 'API Test',
            'description' => 'A test description.'
        );

    callService($params, true);
}

function DataSetEdit() {
    $params = array(
            'service' => 'rest',
            'method' => 'DataSetEdit',
            'response' => RESPONSE,
            'dataSetId' => 3,
            'dataSet' => 'API Test',
            'description' => 'A test description.'
        );

    callService($params, true);
}

function DataSetDelete() {
    $params = array(
            'service' => 'rest',
            'method' => 'DataSetDelete',
            'response' => RESPONSE,
            'dataSetId' => 3
        );

    callService($params, true);
}

function callService($params, $echo = false) {
    // The request uri being called.
    $user_id = 1;
    $request_uri = SERVER_BASE . 'services.php';

    // Obtain a request object for the request we want to make
    $req = new OAuthRequester($request_uri, 'GET', $params);

    // Sign the request, perform a curl request and return the results, throws OAuthException exception on an error
    $return = $req->doRequest($user_id);
    
    if ($echo) {
        var_dump($return);

        if (RESPONSE == 'json')
            echo json_format($return['body']);
        else
            echo $return['body'];
    }

    return $return;
}
?>
