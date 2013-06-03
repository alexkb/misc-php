<?php

/**
 * Dynamic DNS Updater
 * 
 * Very simple php script to do dynamic DNS updates to linode's free DNS service. 
 * The script uses no third party externsions or plugins. All that is required is 
 * php5-cli & php5-curl. Please make sure the initial DNS entry has been manually 
 * created at the linode end.
 * 
 * LICENSE: GNU GENERAL PUBLIC LICENSE http://www.gnu.org/licenses/gpl.html
 * 
 * @credit: written by alex.bergin@gmail.com, but based partially on 
 * http://blog.pathennessy.org/2009/05/11/linode-dynamic-dns-bash-script/
 */

// Set your variables here
$api_url = "https://api.linode.com/api/";
$api_key = "./.linode-apikey";
$last_ip_file = "/tmp/lastip";
$domain = "example.com";
$subdomain = "homeip";
$ip_source = "http://api.externalip.net/ip/"; // free service to determine external IP address.
// End of variables

// Get the api_key
$api_key_value = file_get_contents($api_key);
$default_curl_arguments = array(
  CURLOPT_URL => $api_url,
  CURLOPT_POST => TRUE,
  CURLOPT_SSL_VERIFYPEER => FALSE, // turn off SSL check, as php might not have the allowed certs
  CURLOPT_RETURNTRANSFER => true, // tell curl to return the contents rather than to STDOUT
);

// Get your current IP and determine if we should proceed.
$ip = file_get_contents($ip_source);
$last_ip = false;
if (file_exists($last_ip_file)) {
  $last_ip = file_get_contents($last_ip_file);
}
if ($ip == $last_ip) {
  exit; // don't update as nothing has changed.
}

/**
 * Get the linode DOMAIN ID dynamically.
 */
$fields = array(
  'api_key' => $api_key_value,
  'action' => 'domain.list',
);

$fields_string = http_build_query($fields);

$ch = curl_init();
curl_setopt_array($ch, $default_curl_arguments);
curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);

$json_result = curl_exec($ch);

curl_close($ch);

$json = json_decode($json_result);

if (!is_array($json->DATA)) {
  echo "Error: no json data returned from linode during domain list request.";
  exit;
}

$DOMAINID = false;
while (!$DOMAINID && list(,$item) = each($json->DATA)) {
  if ($item->DOMAIN == $domain) {
    $DOMAINID = $item->DOMAINID;
  }
}

/**
 * Get the linode RESOURCE ID dynamically.
 */
$fields = array(
  'api_key' => $api_key_value,
  'action' => 'domain.resource.list',
  'DomainID' => $DOMAINID
);

$fields_string = http_build_query($fields);

$ch = curl_init();
curl_setopt_array($ch, $default_curl_arguments);
curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);

$json_result = curl_exec($ch); 

curl_close($ch);

$json = json_decode($json_result);

if (!is_array($json->DATA)) {
  echo "Error: no json data returned from linode during resourse list request.";
  exit;
}

$RESOURCEID = false;
while (!$RESOURCEID && list(,$item) = each($json->DATA)) {
  if ($item->NAME == $subdomain) {
    $RESOURCEID = $item->RESOURCEID;
  }
}

/**
 * Set the dynamic IP
 */
$fields = array(
  'api_key' => $api_key_value,
  'action' => 'domain.resource.update',
  'DomainID' => $DOMAINID,
  'ResourceID' => $RESOURCEID,
  'Target' => $ip,
);

$fields_string = http_build_query($fields);

$ch = curl_init();
curl_setopt_array($ch, $default_curl_arguments);
curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);

$json_result = curl_exec($ch);

curl_close($ch); 

$json = json_decode($json_result);

if (!isset($json->DATA->ResourceID)) {
  echo "Error: no json data returned from linode during domain.resource.update.";
  exit;
}

// If the update was successful, store the ip in the last ip file.
file_put_contents($last_ip_file, $ip);
