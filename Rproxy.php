<?php
error_reporting(E_ALL & ~E_NOTICE);
// URLs to intercept :
$url_tointercept = array(
    "/\/chemin_url$/"  => "parametre",  // POST
    
    "/\/autre_chemin_url\?/"  => "parametre",  // GET
);

$url_host=(isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
$url_path=$_SERVER[REQUEST_URI];
$url_called="$url_host"."$url_path";
$method=$_SERVER['REQUEST_METHOD'];

# On s’assure qu’au moins un paramètre est présent, sinon on relaie bêtement l’URL
If (($method=="GET") && (preg_match("/\?/",$url_path)) # Get avec ? et les paramètres
    || ($method=="POST")) # POST sans paramètres, est-ce possible ?
{
  # On initialise les variables et on cherche si l’URL est dans la liste des « à traiter »
  $parameter_value="";
  $parameter="";
  foreach($url_tointercept as $key => $value)
  {
    if (preg_match($key,$url_path))
    { Debug(0,"      MATCH: $key ($url_path)"); $parameter=$value; }
  }

  if (!empty($parameter))
  {
    if     ($method=="GET")  { $parameter_value=$_GET["$parameter"]; }
    elseif ($method=="POST") { $parameter_value=$_POST["$parameter"]; }
  }
  # Valide le mot de passe. Si échec, renvoie la page .html associée en réponse
  if (!isGoodParameterValue($parameter_value))
  {
    # Extraction de l’URL pour construire le nom du fichier .html
    $filename=$url_path;
    
    # retrait du / et du ? si GET
    $filename=preg_replace("/^\//","",$filename);
    $filename=preg_replace("/\?$/","",$filename);
    
    # un coup de plumeau
    $filename=preg_replace("/[\\\^\.\$\|\(\)\[\]\*\+\?\{\}\,\?'`&<>;\"]/","_",$filename);
    
    if (file_exists("$filename.html"))
    {
      $output=file_get_contents("$filename.html");
      if (preg_match("/%%SPECIFIC_KEYWORD%%/",$output))
      {
        $output=preg_replace("/%%SPECIFIC_KEYWORD%%/",
  "Something_else",$output);
      }
      print $output;
    }
    else { print("Unknown error, please report to email_address"); }
    
    exit;
  }
}
else { Debug(0,"    Pas de paramètres avec GET ou POST, proxying URL..."); }
$app_site="https://monvraiserveur";

$url_tocall="$app_site$_SERVER[REQUEST_URI]";

Debug(0,"    Proxying to ($method) $url_tocall");
# Preparing URL call
$ch=curl_init();
curl_setopt($ch, CURLOPT_URL,$url_tocall);
# On va ignorer les certificats de l’application cible
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,false);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,false);

# en cas de timeout…
curl_setopt($ch, CURLOPT_TIMEOUT,10); // 10 seconds PMR timeout
curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
if ($method=="GET")
{   Debug(0,"    GET data : ".print_r($_GET,true)); }
    
# URL sans argument ou POST sans fichier
elseif ($method=="PUT" || $method=="PATCH" || ($method=="POST" && empty($_FILES)))
{
  Debug(0,"    POST data : ".print_r($_POST,true));
  $data_str=file_get_contents('php://input');
  curl_setopt($ch, CURLOPT_POSTFIELDS,$data_str);
}
# POST avec un fichier
elseif ($method=="POST" && !empty($_FILES))
{
  Debug(0,"    POST data (with file) : ".print_r($_POST,true));

  $data_str=array();
  if(!empty($_FILES))
  {
    Debug(0,"    POST files:\n".print_r($_FILES,true));
    foreach ($_FILES as $key => $value)
    {
      $full_path=realpath($_FILES[$key]['tmp_name']);
      $data_str[$key]='@'.$full_path;
    }
  }
  curl_setopt($ch, CURLOPT_POSTFIELDS,$data_str+$_POST);
}
# méthode maison
else
{  curl_setopt($ch, CURLOPT_CUSTOMREQUEST,$method); }
if (!function_exists('getallheaders'))
{
  Debug(0,"    WARNING: getallheaders() does not exist !!");
  
  function getallheaders()
  {
    $headers = [];
    
    foreach ($_SERVER as $name => $value)
    {
      if (substr($name, 0, 5) == 'HTTP_')
      { $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ',
        substr($name, 5)))))] = $value; }
    }
    return $headers;
  }
}

# On définit les entetes pour le relayage, sans Host: 
$headers=getallheaders();

$headers_str=[];
foreach ($headers as $key => $value)
{
  if ($key=='Host') continue;
  $headers_str[]=$key.": ".$value;
}

# on définit host: selon la cible
$headers_str[]="Host: ".preg_replace("/.*$","",preg_replace("^.*//","",$app_site)); 

curl_setopt($ch, CURLOPT_HTTPHEADER,$headers_str);
Debug(0,"    URL headers: ".print_r($headers_str,true));

# pas d’entête de réponse dans la réponse, Apache fait le travail
curl_setopt($ch, CURLOPT_HEADER,false);
# Appel de l’URL
Debug(0,"    Calling URL...");
if (!$response_data=curl_exec($ch))
{ var_dump($response_data); trigger_error(curl_error($ch)); Debug(0,"    curl_exec failed ($response_data)"); }
else { Debug(0,"    success..."); }

print $response_data;

Debug(0,"    Full response data:\n".preg_replace("/\r\n|\r|\n/", "\n", $response_data));

curl_close($ch);

exit;
function Debug($level,$text)
{
  return;
  
  $fd_log=fopen("/tmp/proxy.log","a+");
  fwrite($fd_log,$text."\n");
  fclose($fd_log);
}
function isGoodParameterValue($parameter_value)
{
  if (true) { return(true); } # good
  
  return(false); # bad
}

?>
</HTML>
