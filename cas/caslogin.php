<?php

/**
 * @author Patrick Pollet
 * @version $Id: caslogin.php 1174 2010-12-10 13:16:25Z ppollet $
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package c2ipf
 */



// si ce fichier est inclus par positionnement et certification
// le chemin est donc deja la racine du site

if (!isset($chemin))
    $chemin = '..';
$chemin_commun = $chemin."/commun";
$chemin_images = $chemin."/images";


if (!isset($type_p)) {  // caslogin n' pas inclus (cas non forc�)  mais appel� comme action d'un formulaire
	$type_p=isset($_POST["type_p"])? $_POST["type_p"]:"";
}
// rev 978
if (!isset($id_examen))
    $id_examen=!empty($_POST['id_examen'])?$_POST['id_examen']:0;
if (!isset($id_etab))
    $id_etab=!empty($_POST['id_etab'])?$_POST['id_etab']:0;

require_once($chemin_commun."/constantes.php");
//important phpcas ouvre une session et il faut que ce soit MON nom !
require_once($chemin_commun."/fonctions_session.php");
ouvrir_session();

// ces trois varibales ont requises par enter.php
// au 1er qppel de caslogin elles  sont soient difinies soit dans le postet on les met en session
// au retour du serveur CAS elles sont reprises de la session pour �tre pass�e a enter.php
if (!empty($type_p)) var_register_session('type_p',$type_p);
else $type_p= var_get_session('type_p');
if (!empty($id_examen)) var_register_session('id_examen',$id_examen);
else $id_examen= var_get_session('id_examen');
if (!empty($id_etab)) var_register_session('id_etab',$id_etab);
else $id_etab= var_get_session('id_etab');



// import phpCAS lib
// initialize phpCAS

if (empty($cas_proxycas)) $cas_proxycas=0;  // drapeau non document�
if (empty($cas_version)) $cas_version='2.0';

require_once('CAS/CAS.php');
// mode proxy CAS
if ( !is_object($PHPCAS_CLIENT) ) {
	// Make sure phpCAS doesn't try to start a new PHP session when connecting to the CAS server.
	if  ($cas_proxycas) {  // nouveau rev 978
		phpCAS::proxy($cas_version, $cas_url, (int) $cas_port, $cas_service, false);
	}
	// mode client CAS normal
	else {
		phpCAS::client($cas_version, $cas_url, (int) $cas_port, $cas_service, false);
	}
}

// cr�e une trace dans /tmp/phpcas.log
// phpCAS::setDebug();



// check CAS authentication
// Don't try to validate the server SSL credentials
phpCAS::setNoCasServerValidation();
if (!phpCAS::isAuthenticated()) {
	phpCAS::forceAuthentication();
}

// at this step, the user has been authenticated by the CAS server
// and the user's login name can be read with phpCAS::getUser().
// PAS de retour ici tantq que l'authentification n'a pas r�ussi !


$verif="ent";
$page_origine_explode = explode("?",basename($_SERVER['REQUEST_URI']));
$page_origine=$page_origine_explode[0];
$identifiant=phpCAS::getUser();
$passe="cas"; //juste pour qu'il ne soit pas vide (isset() test� dans entrer.php)
$premiereFois=1;

include("$chemin/codes/entrer.php");
