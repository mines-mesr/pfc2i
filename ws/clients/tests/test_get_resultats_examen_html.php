<?php
require_once ('../classes/c2i_soapserver.php');

$client=new c2i_soapserver();
require_once ('../auth.php');
/**test code for get_resultats_examen_html
* @param int $client
* @param string $sesskey
* @param string $userid
* @param string $idfield
* @param string $id_examen
* @return  string
*/

$lr=$client->login(LOGIN,PASSWORD);
$res=$client->get_resultats_examen_html($lr->getClient(),$lr->getSessionKey(),'','','');
print($res);
$client->logout($lr->getClient(),$lr->getSessionKey());

?>
