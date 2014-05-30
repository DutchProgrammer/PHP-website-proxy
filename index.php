<?php
ini_set('zlib.output_compression_level', 1);
ob_start('ob_gzhandler');

// seconds, minutes, hours, days
$expires = 1209600;//60*60*24*14;
header("Pragma: public");
header("Cache-Control: maxage=".$expires);
header('Expires: ' . gmdate('D, d M Y H:i:s', time()+$expires) . ' GMT');
header('Server: ');
header('X-Powered-By: ');

$domain          = 'mydomain.com';

$proxyDomain     = 'mytargetdomain.com';
$proxyDomainPath = '';
$proxyParams     = '';

//Check if servername meets the requirement
if ($_SERVER['SERVER_NAME'] === 'www.'.$domain) {
	// 301 Moved
	header('Location: http://'.$domain.$_SERVER['REQUEST_URI'],TRUE,301);
	exit;
}

require_once('includes/template.php');

echo new template($proxyDomain, $proxyDomainPath, $proxyParams);

ob_flush();
exit;
?>
