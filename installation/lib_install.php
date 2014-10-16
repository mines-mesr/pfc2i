<?php

/**
 * @author Patrick Pollet
 * @version $Id: lib_install.php 1300 2012-09-11 14:01:01Z ppollet $
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package c2ipf
 */


/**
 * 
 * classe d'installation du contenu d'une base de données
 * a partir d'un fihier SQL obtenu par export de phpmyadmin
 * 
 *
 */
class InstallSqlLoader
{
    /**
     * @var connexion
     */
    protected $connexion;

    /**
     * @var array List of keywords which will be replaced in queries
     */
    protected $metadata = array();

    /**
     * @var array List of errors during last parsing
     */
    protected $errors = array();

    protected $queries = array();

    /**
     * @param $connexion
     */
    public function __construct($connexion)
    {
     
        $this->connexion = $connexion;
    }

    /**
     * Set a list of keywords which will be replaced in queries
     *
     * @param array $data
     */
    public function setMetaData(array $data)
    {
        foreach ($data as $k => $v)
        $this->metadata[$k] = $v;
    }

    /**
     * Parse a SQL file and execute queries
     *
     * @param string $filename
     * @param bool $stop_when_fail
     */
    public function parse_file($filename, $stop_when_fail = true)
    {
        if (!file_exists($filename))
        throw new Exception("File $filename not found");

        return $this->parse(file_get_contents($filename), $stop_when_fail);
    }

    /**
     * Parse and execute a list of SQL queries
     *
     * @param string $content
     * @param bool $stop_when_fail
     */
    public function parse($content, $stop_when_fail = true)
    {
        $this->errors = array();

        $content = str_replace(array_keys($this->metadata), array_values($this->metadata), $content);
        //point-virgule suivi eventuellement d'espaces ET d'au moins un saut de ligne
        $this->queries = preg_split('#;\s*[\r\n]+#', $content);
        foreach ($this->queries as $query) {
            $query = trim($query);
            if (!$query)
            continue;
            if (!mysqli_query($this->connexion,$query)){
                $this->errors[] = array(
            'errno' => mysqli_errno($this->connexion),
            'error' => mysqli_error($this->connexion),
            'query' => $query,
                );

            if ($stop_when_fail)
            return false;
            }
        
        }

        return count($this->errors) ? false : true;
    }

    /**
     * Get list of errors from last parsing
     *
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }
    public function getQueries()
    {
        return $this->queries;
    }
}





function enteteTests($var){
echo '<div style="background:#c0C0C0"><p>';
echo traduction_cond( $var) ;
echo '</p></div>';
}

function intituleTests($var){
    echo traduction_cond( $var) ;
    echo " : " ;
}

function succesTests($var=''){
    echo "<span class='vert'>&nbsp;".traduction("succes")." " .$var."</span>" ;
    echo '<br/>';
    return 0;
}

function echecTests($var=''){
    global $configuration_est_ok;
    echo "<span class='rouge'>&nbsp;".traduction ("echec")." ".$var.'</span>';
    $configuration_est_ok = 0;
    echo '<br/>';
    return 1;
}

function moyenTests($var){
echo "<span class='orange'>&nbsp;".traduction("non_critique")."  ".$var."</span>" ;
echo '<br/>';
return 0;
}




/**
 * v 2 retourne le nombre d'erreur pour MAJ ddu bouton 'continuer'
 * @param unknown_type $dataroot
 * @param unknown_type $chemin_commun
 */

function test_config($dataroot,$chemin_commun) {

    global $CFG;
    $nbErr = 0; 
    enteteTests("test de la version php");
    intituleTests("vous utilisez php version ");echo (phpversion ());
    if (phpversion() >=5)
        succesTests();
    else
        $nbErr += moyenTests('vous devriez envisager de migrer en php 5');

    // test des librairies n�cessaires
    enteteTests("test des librairies php");

    intituleTests("Test de la librairie MYSQLi");
    if (function_exists('mysqli_query')){
        succesTests();
    } else {
        $nbErr += echecTests();
    }
    
    intituleTests("Test de la librairie curl");
    if(extension_loaded('curl')) {
        succesTests();
    } else {
        $nbErr += echecTests();
    }


    intituleTests("Test de la librairie zlib");
    if (function_exists('gzread')){
        succesTests();
    } else {
        $nbErr += echecTests();
    }

    intituleTests("Test de la librairie mb_string (sera requise en V 1.6 pour le support UTF8");
    if (function_exists('mb_check_encoding')){
        succesTests();
    } else {
        $nbErr += moyenTests("cette fonctionnalité sera requise en version 1.6 (utf8)");
    }
    intituleTests("Test de la librairie phpsoap (requise pour utiliser les web services)");
    if (class_exists('SoapServer')){
        succesTests();
    } else {
        $nbErr += echecTests("vous ne pourrez pas utiliser les WebServices");
    }


    intituleTests("Test de la librairie LDAP (pour synchroniser la plateforme avec votre annuaire");
    if (function_exists('ldap_bind')){
        succesTests();
    } else {
        $nbErr += moyenTests('Echec (si vous n\'utilisez pas d\'annuaire LDAP ce n\'est pas important)');
    }

    // test des encodages de caract�res:
    /** 
     * supprim� revision 986 car  les navigateurs n'envoient plus cette information:
"browsers started to stop sending this header in each request,
starting with Internet Explorer 8, Safari 5, Opera 11 and Firefox 10.
In the absence of Accept-Charset:, servers can simply assume that
UTF-8 and the most common characters sets are understood by the
client."
*/
/********
    enteteTests("test du type de caract�res autoris�s par le serveur");
    intituleTests("test du type de caract�res autoris�s par le serveur ");
    if  ((strpos($_SERVER["HTTP_ACCEPT_CHARSET"], "ISO") === false) && (strpos($_SERVER["HTTP_ACCEPT_CHARSET"], "*") === false))
        echecTests("(le serveur doit accepter le charset ISO) ".$_SERVER["HTTP_ACCEPT_CHARSET"]);
    else succesTests();
 ******/
     enteteTests("test de votre environnement");
     intituleTests("recherche du compresseur zip ");
     $res=exec ("which zip");
     echo  $res ;
     if (strstr($res,"/zip")) succesTests();
      else $nbErr += moyenTests ("vous devriez spécifier dans la table c2iconfig le chemin vers la commande zip");


    // test des droits d'�criture sur les dossiers :
    enteteTests("test des droits d'écriture dans le dossier ressources");
    $liste_dossiers = array();
    $liste_dossiers[] = $dataroot;
   // $liste_dossiers[] = $chemin_commun;  plus en version 1.5 !

    foreach($liste_dossiers as $dossier){
        intituleTests("Test d'écriture dans le dossier ".realpath( $dossier));
        if (is_writable($dossier)) {
            succesTests();
        } else {
            $nbErr += echecTests();
        }
    }
    enteteTests("test des droits d'écriture sur les fichiers");
    $liste_fichiers = array();
     //envoy� par la nationale a personnaliser
     // il a �t� mis en lmode 0666 dans l'archive zip, mais a-t-il �t� decompress� ainsi ?
     // ca depends de la plateforme locale ...
    $liste_fichiers[] = $chemin_commun."/constantes.php";
    foreach($liste_fichiers as $fichier){
        intituleTests("Test d'écriture du fichier ".realpath( $fichier));
        if (is_writable($fichier)) {
            succesTests();
        } else {
            $nbErr += echecTests();
        }
    }
    return $nbErr; 

}


/**
 * v 2 retourne le nombre d'erreur pour MAJ ddu bouton 'continuer'
 * @param unknown_type $dataroot
 * @param unknown_type $chemin_commun
 */
function test_bd($serveur_bdd,$nom_bdd,$user_bdd,$pass_bdd) {
	global $CFG;
	$nbErr = 0;
	
	enteteTests("test de la connexion à la base de données");
	if ($serveur_bdd &&  $user_bdd && $pass_bdd) {
		intituleTests("Test de connexion au serveur ".$user_bdd."@".$serveur_bdd );
		$connexion = @mysqli_connect($serveur_bdd, $user_bdd, $pass_bdd);
		if (!$connexion) {
			$nbErr += echecTests( mysqli_connect_error());
		}
		else  {
			succesTests( "");
			intituleTests("Test d'existence de la base ".$nom_bdd);
			$sql= "SHOW DATABASES LIKE '$nom_bdd'";
			$res = @mysqli_query($connexion,$sql);
			if (!empty($res) && mysqli_num_rows($res)==1) {
				succesTests( "");
				intituleTests("Test des droits d'accès à la base ".$nom_bdd);
				if (!@mysqli_select_db($connexion,$nom_bdd)) {
					$nbErr += echecTests(mysqli_error($connexion));
				}else {
					succesTests( "");
					intituleTests("Test du droit 'CREATE TABLE " );
					$sql="CREATE TABLE c2itest(test varchar(5) NOT NULL)";
					if ( @mysqli_query($connexion,$sql)) {
						succesTests( "");
						intituleTests("Test du droit 'DROP TABLE " );
						$sql="drop table c2itest";
						if ( @mysqli_query($connexion,$sql))
							succesTests( "");
					}
					else  $nbErr += echecTests( mysqli_error($connexion));
				}

			} else {
				$nbErr += echecTests("base de donnée $nom_bdd inconnue");
			}
		}
	}else {
		$nbErr += echecTests("Paramétres d'accès à la base de données incorrects");
	}    
	return $nbErr; 

}
