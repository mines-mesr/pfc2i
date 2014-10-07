<?php

/**
 * @author Patrick Pollet
 * @version $Id: c2i_params.php 1299 2012-07-25 22:39:18Z ppollet $
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package c2ipf
 */


/*
 * d�but compat V2
 * en V2 les globales seront dans ces deux objets
 */
/*
if(defined('E_STRICT')){

    error_reporting(E_ALL & ~E_DEPRECATED);   
}
*/


if (!defined ('NO_HEADERS'))  //rev 889 une notice de moins ...
	define ('NO_HEADERS',1);  // V 1.5 on ne modifie plus les headers avec cache=0 !!!


/**
 * Used by library scripts to check they are being called by Moodle
 * tout script qui ne peut pas etre appel� direcrement devrait avoir au d�but
 * defined('C2I_INTERNAL') || die();
*/
if (!defined('C2I_INTERNAL')) { // necessary because cli installer has to define it earlier
    define('C2I_INTERNAL', true);
}


$USER=new StdClass();  //renseign� ici
$CFG=new StdClass();   // renseign� dans constantes.php et lib_config.php

// certaines pages se terminent par une redirection, eventuellement en envoyant un fichier
// elles doivent definir cette valeur avant d'inclure c2i_params

if (!defined('NO_HEADERS')){
	// rev 1.41 important pour les pages d'accueil fonctionnent sans aucune notice PHP
	if (!headers_sent ()) {
		$start_session=true;
		// pourquoi ce code qui ralenit consid�rablement les ouvertures de popup ????
		header("Pragma: no-cache");
		header("Expires: 0");
		header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
		header("Cache-Control: no-cache, must-revalidate");
	} else $start_session=false;
} else $start_session=true;


//echo "SS=".$start_session;

/// code extrait de Moodle 1.9x
/// A hack to get around magic_quotes_gpc being turned off
/// It is strongly recommended to enable "magic_quotes_gpc"!

/// note PP avec Moodle 2.0 qui a une autre biblioth�que DB, c'est l'inverse

function ini_get_bool($ini_get_arg) {
    $temp = ini_get($ini_get_arg);

    if ($temp == '1' or strtolower($temp) == 'on') {
        return true;
    }
    return false;
}


    if (!ini_get_bool('magic_quotes_gpc')) {
        function addslashes_deep($value) {
            $value = is_array($value) ?
                    array_map('addslashes_deep', $value) :
                    addslashes($value);
            return $value;
        }
        $_POST = array_map('addslashes_deep', $_POST);
        $_GET = array_map('addslashes_deep', $_GET);
        $_COOKIE = array_map('addslashes_deep', $_COOKIE);
        $_REQUEST = array_map('addslashes_deep', $_REQUEST);
        if (!empty($_SERVER['REQUEST_URI'])) {
            $_SERVER['REQUEST_URI'] = addslashes($_SERVER['REQUEST_URI']);
        }
        if (!empty($_SERVER['QUERY_STRING'])) {
            $_SERVER['QUERY_STRING'] = addslashes($_SERVER['QUERY_STRING']);
        }
        if (!empty($_SERVER['HTTP_REFERER'])) {
            $_SERVER['HTTP_REFERER'] = addslashes($_SERVER['HTTP_REFERER']);
        }
       if (!empty($_SERVER['PATH_INFO'])) {
            $_SERVER['PATH_INFO'] = addslashes($_SERVER['PATH_INFO']);
        }
        if (!empty($_SERVER['PHP_SELF'])) {
            $_SERVER['PHP_SELF'] = addslashes($_SERVER['PHP_SELF']);
        }
        if (!empty($_SERVER['PATH_TRANSLATED'])) {
            $_SERVER['PATH_TRANSLATED'] = addslashes($_SERVER['PATH_TRANSLATED']);
        }
    }



/**
 * essai de calcul auto du chemin
 * non encore utilis� ...
 */

$CFG->abspath=dirname(__FILE__) ;
$CFG->cheminwww="..";
while ($CFG->abspath !='/' && ! file_exists($CFG->abspath."/commun/constantes.php")) {
   // print $CFG->abspath."<br/>";
    $CFG->abspath=dirname($CFG->abspath);
    $CFG->cheminwww.="/..";
}
/**
 * normalement � ce point $CFG->abspath = realname($chemin)
 * attention : chemin est aussi utilis� pour les chemins www (relatifs)
 */

// $chemin correspond au chemin � parcourir pour aller de la page appelante � la racine d'ines
// cette variable doit �tre d�finie dans chaque page principale
if (!isset($chemin)) $chemin = "..";
if (!isset($chemin_commun)) $chemin_commun = $chemin."/commun";
if (!isset($chemin_images)) $chemin_images = $chemin."/images";
require_once ($chemin_commun."/constantes.php");




require_once($chemin_commun."/weblib.php");
require_once ($chemin_commun."/fonctions_session.php");
require_once ($chemin_commun."/fonctions_divers.php");

require_once ($chemin_commun."/lib_langues.php");
require_once ($chemin_commun."/lib_dates.php");

//error_reporting(DEBUG_ALL);

if ($start_session){ // ouvrir une session
	ouvrir_session();
    get_user_data();  //maj globale $USER

}


require_once ($chemin_commun."/lib_bd.php");
require_once ($chemin_commun."/lib_config.php");


// selection de l'�tablissement de la personne connect�e (pour l'insertion d'item)
// v 1.41 seulement ici !  attention positionnement anonyme et 1ere connexion boucle infinie sur erreur_fatale !
if (!empty( $USER->id_user)) {
   //if ($USER->id_user=='ppollet') $USER->id_user='zzong';
    $USER->id_etab_perso=etab($USER->id_user);
} else {
    $USER->id_etab_perso=0;
}

// mise � jour selon que le client utilise ou non un proxy
$REMOTE_ADDR=getremoteaddr();
//voir pour un test avec $USEr->adresse_ip ( IP de la 1ere connexion)
$USER->ip=$REMOTE_ADDR;





require_once ($chemin_commun."/lib_tracking.php");
require_once ($chemin_commun."/lib_etablissements.php");

/*
 * maintenant qu'on la la config on peut lire les preferences et les droits
 * ce qui pose un pb pour le test dans lib_config (is_admin() !
 * on ne le sait pas encore ...) et on peut lire les droits avant
 * car on ne sait pas comment se "prefixent" ls tables ...

 *attention a lib_acces quui peut modifier la bd des profils/droits
 * donc a charger avant le lecture des droits (pour l'instant)')
 *  */



if (!empty( $USER->id_user)) {
    //compat V 1.4
   // ainsi il sera vu de partout (principale et popup)
   $indice_ecart=optional_param("indice_ecart",0,PARAM_INT);
    if (!$indice_ecart || $indice_ecart<=1)
        $indice_ecart = config_nb_item($USER->id_user); // nombre d'items affich�es dans la multipagination
   $indice_deb=optional_param("indice_deb",0,PARAM_INT);
   $num_page=optional_param("num_page",0,PARAM_INT);
   if ($num_page>0) $indice_deb = $indice_ecart * ($num_page -1);
   $indice_fin = $indice_deb + $indice_ecart -1; // indice de fin d'affichage

    // V 1.5

    $USER->indice_ecart=$indice_ecart;
    $USER->preferences=get_preferences ($USER->id_user);
    require_once ($chemin_commun."/lib_acces.php");
    lecture_droits($USER->id_etab_perso);

} else {
    $USER->indice_ecart=10;
    $USER->preferences=array();
    $USER->droits=array();
    require_once ($chemin_commun."/lib_acces.php");

}

// rev 813 fichier de config locale permis (jamais comit�)
// rev 977 la config locale est lue AVANT les lib_xxxx pour permettre

// il faut le faire apr�s la lecture des droits
//print $CFG->universite_serveur;
if (isset($CFG->universite_serveur) && $CFG->universite_serveur == 1) {
    $file=$CFG->chemin."/codes/nationale/config.php";
} else {
    $file=$CFG->chemin."/codes/locale/config.php";
}
//print $file;
if (file_exists($file))
    require_once($file);


require_once ($chemin_commun."/lib_auth.php");
require_once ($chemin_commun."/lib_fichiers.php");
require_once ($chemin_commun."/lib_questions.php");
require_once ($chemin_commun."/lib_examens.php");
require_once ($chemin_commun."/lib_events.php"); //apr�s la lecture des droits



//voir pour les charger � la demande
//rev 984 attention sur une nationale . Les web services proposent les notions
//meme si desactiv� sur cette nationale
if ((isset($CFG->utiliser_notions_parcours) && $CFG->utiliser_notions_parcours) || (isset($CFG->universite_serveur) && $CFG->universite_serveur == 1)) {
  //require_once ($chemin_commun."/lib_notions.php");
  require_once ($chemin_commun."/lib_ressources.php");
}

//print_r(get_notions());
//invalide_question(2489,1);
//valide_question(998,1);

