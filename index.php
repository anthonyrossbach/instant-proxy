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

//If the source is not present then give the user a message, else save and redirect to site root
if (!file_exists("source.txt")) {
	if (!isset($_POST["domain"])){
		echo "<html><head></head><body><h1 style='text-align:center;margin-top:150px;'>Thanks for installing, now you just need to setup your source url for example https://theusername.example.com by entering the source here without a trailing slash.<BR><BR><form action=\"index.php\" method=\"post\"><input type=\"text\" name=\"domain\"><br><input type=\"submit\"></form></h1></body></html>";
		exit();
	}else{
		$source=$_POST["domain"];
		file_put_contents("source.txt", $source);
		echo "<h1>Success! This domain will now proxy traffic</h1>";
		header("Location: https://".$_SERVER['HTTP_HOST']."");
		exit();
	}
}

//Auto redirect to HTTPS first if not already using HTTPS
//AKA If the domain we are on now has a valid certificate we should send the user to the HTTPS version of our site...
$httpprefix="http://";
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') {
    $httpprefix = "https://";
}
if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == "https" || !empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] == "on") {
    $httpprefix = "https://";
}
if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == "http" || !empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] == "off") {
    $httpprefix = "http://";
}
if (!empty($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] == "http"){
	$httpprefix="http://";
}

if($httpprefix=="http://"){
	if (has_ssl($_SERVER['HTTP_HOST'])==true){
		//Only redirect if we have a valid ssl cert first
		$redirect = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
		header('HTTP/1.1 301 Moved Permanently');
		header('Location: ' . $redirect);
		exit();
	}else{
		$httpprefix="http://";
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
$source=file_get_contents("source.txt");

//The URL to request, if you want to use a sub domain insted use this
//$url = 'https://'.$mydomain.'.example.com' . $link_request;
//If you dont want to load based on say a username just use this
//$url = 'https://theusername.example.com' . $link_request;
$url = ''.$source.'' .$link_request;

//Send request, with the current visitors useragent
$ch = curl_init();
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_REFERER, $url);

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
$contenttype = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
header("Content-type: $contenttype");
header("Access-Control-Allow-Origin: ".$httpprefix."".$_SERVER['HTTP_HOST']."", false);

//Close connection
curl_close($ch);

//Check type here and change URL to new one for links and source content!
if (strpos($contenttype, 'text/html') !== false || strpos($contenttype, 'text/css') !== false || strpos($contenttype, 'application/javascript') !== false){
	$response = str_replace($source, "".$httpprefix."".$_SERVER['HTTP_HOST']."", $response);
}

//Send the data to the browser and we are done
echo $response;

?>
