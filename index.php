<?php

//Function that when given a domain will validate if it has a SSL certificate
function has_ssl($domain) {
	$res = false;
	$orignal_parse = $domain;
	$stream = @stream_context_create( array( 'ssl' => array( 'capture_peer_cert' => true ) ) );
	$socket = @stream_socket_client( 'ssl://' . $orignal_parse . ':443', $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $stream );
	
	// If we got a ssl certificate we check here, if the certificate domain
	// matches the website domain.
	if ( $socket ){
		$cont = stream_context_get_params( $socket );
		$cert_ressource = $cont['options']['ssl']['peer_certificate'];
		$cert = openssl_x509_parse( $cert_ressource );
		$listdomains=explode(',', $cert["extensions"]["subjectAltName"]);
	
		foreach ($listdomains as $v) {
			if (strpos($v, $orignal_parse) !== false) {
				$res=true;
			}
		}
	}
	return $res;
}

//Auto redirect to HTTPS first if not already using HTTPS
//AKA If the domain we are on now has a valid certificate we should send the user to the HTTPS version of our site...
if(empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] == "off"){
	if (has_ssl($_SERVER['HTTP_HOST'])==true){
		//Only redirect if we have a valid ssl cert first
		$redirect = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
		header('HTTP/1.1 301 Moved Permanently');
		header('Location: ' . $redirect);
		exit();
	}
}

//Build page URL
//If the request is for / aka the index page we change it to nothing so the request looks like
//https://example.com/domain/example.com
//Without the / at the end, if you want it to load a specific index file you can change it inside $link_request
//Full link is $actual_link = "http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
$link_request=$_SERVER[REQUEST_URI];
if ($link_request=="/"){
	$link_request="";
}

//Remove www. from our request to the backend server.
$mydomain = str_replace("www.", "", $_SERVER['HTTP_HOST']);

//The URL to request, if you want to use a sub domain insted use this
//$url = 'https://'.$mydomain.'.example.com' . $link_request;
//If you dont want to load based on say a username just use this
//$url = 'https://theusername.example.com' . $link_request;
$url = 'https://example.com/domain/'.$mydomain.'' . $link_request;

//Send request, with the current visitors useragent
$ch = curl_init();
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

//If this reuqest is a post request forward on the POST data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $_POST);
}

//Run request and get response
$response = curl_exec ($ch);

//Check if response failed, if so give the user a error message
if (curl_error($ch)) {
	echo "<html><head></head><body><h1 style='text-align:center;margin-top:150px;'>We are unable to run this requst</h1></body></html>";
	exit();
}

//Set browser content type for the file and send that along so it renders as it should
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
header("Content-type: $contentType");

//Close connection
curl_close($ch);

//Send the data to the browser and we are done
echo $response;

?>
