<?php

/**
 * @author Patrick Pollet
 * @version $Id: lib_config.php 1312 2012-11-14 12:50:49Z ppollet $
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package c2ipf
 */

/*
 * bibliotheque de manipulations de la configuration et des preferences
 */

//valeur non relues depuis la BD car changeant a chaque fois
$CFG->chemin=$chemin; //relatif
$CFG->chemin_templates=$CFG->chemin."/templates2";
$CFG->chemin_commun=$chemin."/commun";

//pas de th�me peut �tre valeur par d�faut
// rev 981 le theme par d�faut est le v15
$CFG->chemin_theme=$chemin."/themes/v15";
$CFG->chemin_images=$CFG->chemin_theme."/images";

// rev 1.41 on travaille en absolu !
//valeur par d�faut si pas chang�e dans c2iconfig
$CFG->chemin_ressources=dirname(realpath($chemin."/index.php"))."/ressources";

//chemin absolu vers la base de l'installation
$CFG->dirroot=realpath($chemin);

// cecode ne fonctionne pas toujours (selon version de php ?)
/*****************************************************
if (isset($_SERVER['HTTP_REFERER'])) {
	//calcul de l'URL de la plateforme  NON FIABLE
	$pu=parse_url($_SERVER['HTTP_REFERER']);
 //  print_r($pu);
	$tmp=add_slash_url($chemin);   // ../..  --> ../../
	$base=dirname($pu['path']);
	while (strpos($tmp,"../")===0) {
		$base=dirname($base);
		$tmp=substr($tmp,3);
       // print $base."<br/>".$tmp;
	}
	$CFG->wwwroot=$pu['scheme']."://".$pu['host'].$base;

}else {
//TODO page recharg�e ou acc�s direct ...on relit constantes.php
    $CFG->wwwroot=$locale_url_univ;
}
*******************************************************/

$CFG->wwwroot=$locale_url_univ;

$pu=parse_url($CFG->wwwroot);
// rev 980 pour liens images relatifs au serveur  sans le slah de fin
//$CFG->serverroot=$pu['scheme']."://".$pu['host'];


$CFG->encodage="UTF-8";
if (isset($prefix)) {
	$CFG->prefix=$prefix;  // attention la table c2iconfig DOIT avoir ce prefixe !
}else{
	$CFG->prefix='';
}

$version=return_version_pf(true);  //lecture fichier version.txt

$CFG->version=$version;

$CFG->session_nom="c2i";

/**
 * partie retouche BD
 * rev 1.4--> 1.41
 */
/**
 * voir c2i_params pour une description du pb
 * a ce niveau on n'a pas encore lu les droits en base
 * et c'est justement lib_config qui va nous dire comment se prefixent
 * les tables ... (toutes SAUF c2iconfig)'
 * donc on met � jour a chaque fois
 * en principe rien � faire ... donc ne ralentira pas
 if( is_admin())
    maj_bd_config();
 else print "rat�";
***/

//attention lors de la mise � jour d'un V 1.4 la table n'existe pas !
if (mysql_table_exists('config')) {
    maj_bd_config();
    lecture_config();
}

function maj_bd_config() {
    global $CFG;

 
    /**
     * developpeurs
     * si vous avez besoin d'une nouvelle variable de config mettez une ligne
     * comme celles-ci une fois ici (l'entrée sera créée dans la table c2iconfig au prochain
     * chargement d'une page et utilisez la avec $CFG->nom_de_la_variable dans vos scripts
     * puis commentez la (ou virez la) et  transférez la � la fin de la fonction
     * maj_config_14_15() dans installation/lib_maj14_15.php
     * pour que les mises à jour des PF locales fonctionnent.
     */


/***********************
    if (get_config('activer_filtre_latex',false)) {
            require_once($CFG->dirroot.'/commun/filtres/tex/lib.php');
            tex_filter_maj_config();
     }
***********************/

}


//relire la table c2iconfig ici
function lecture_config() {
    global $CFG,$version,$chemin;

    //$lignes=get_records_sql("select * from c2iconfig order by cle",1,"err_lecture_config","");
    // surtout pas ca ! c2iconfig ne peut pas avoir de prefix !
    $lignes=get_records("config",null,"cle",null,null,1,"err_lecture_config","");
    //print_r($lignes);
    if ($lignes)
    foreach($lignes as $ligne) {
        $cle=$ligne->cle;
          $CFG->$cle=$ligne->valeur;
    }
    else  erreur_fatale ("err_lecture_config");
     //important sinon va raconter n'importe quoi a la 1ERE erreur fatale suivante'
     // (le dossier de resources n'est pas accessible ...')


//comparer $CFG->version_release en base avec mes valeurs et mettre � jour la BD au fur et a mesure
	if ($CFG->version !=$version) {
		set_config('pfc2i','version',$version,0);
	}


//rev 987 adresses variables des listes de feedback
// a calculer ici car on a besoin de connaitre le type de c2i 
//$adfq="qcm-".$CFG->c2i."@education.gouv.fr";
//add_config('questions','adresse_feedback_questions',$adfq,$adfq,'adresse des experts validateurs des questions',0);

// rev 1013  simplifie les tests plus tards
$CFG->unicodedb= strtoupper( $CFG->encodage) != "ISO-8859-1";
/****
 if (get_config_item('activer_filtre_latex',false)) {
            require_once($CFG->dirroot.'/commun/filtres/tex/lib.php');
            tex_filter_maj_bd();
     }
*****/


// important apr�s relecture du theme dans la config
// il y a encore quelques acc�s direct a CFG->chemin_images dans weblib.php
$CFG->chemin_theme=$chemin."/themes/".$CFG->theme;
$CFG->chemin_images=$CFG->chemin_theme.'/images';
//rev 981
$CFG->utiliser_form_actions=1;  // rev 981 simplification forte des liens openPopup ....

//seulement sur les nationales
$CFG->utiliser_commentaires_reponses=$CFG->universite_serveur==1; 

//version 2.0 éviter un double slash dans ces chemins absolus
// donc ajouter systématiquement un '/' dans les concanténations  
// verifié le 27/04/2014 par 
//  grep -Rin  chemin_ressources * |grep CFG   (98 occurences)
// grep -Rin  wwwroot * | grep CFG (383 occurences)

$CFG->chemin_ressources = remove_slash_url($CFG->chemin_ressources);
$CFG->wwwroot = remove_slash_url($CFG->wwwroot);
//echo "pp".$CFG->chemin_ressources;


}



/**
 * renvoie la valeur d'une clé 
 */
function get_config_item ($cle,$die=1) {
        global $CFG;
   $sql =<<<EOR
SELECT * FROM {$CFG->prefix}config
WHERE cle='$cle'
EOR;

   if ($res=get_record_sql($sql,$die,"err_cle_config_non_trouve",$cle))
    return $res;
   else if ($die)
        erreur_fatale("err_config_parametre_inconnu", $cle);
   else  return false;
}



function get_config ($cle,$die=0) {
if ($res=get_config_item($cle,$die)){
    return $res->valeur;
}
    else return false;

}


//TODO passer par set_field !
function set_config ($categorie,$cle,$valeur,$modifiable=1,$die=0) {
        global $CFG;

        //attention aux parentheses ET au triple �gal (certaines valeurs de config sont =0 !!!!)
        if (($old=get_config($cle,false)) !==false){ //d�ja en base (cle est une valeur unique !)
          if ($old !=$valeur) {
        	$sql="update {$CFG->prefix}config set valeur='".addslashes($valeur)."' where cle ='".$cle."' ";

        	ExecRequete($sql,false,$die);
          } else return $old;
        }else {
            add_config($categorie,$cle,$valeur,$valeur,'',$modifiable);
        }
        //garde cette nouvelle valeur pour la suite
		$CFG->$cle=$valeur;
		return $valeur;
}
/**
 * @param   cat�gorie categorie de configuration
 * @param   cl� valeur de la cl� de config, doit �tre unique !
 * @param   valeur valeur de la cl�
 * @param   defaut valeur par d�faut
 * @param   description texte explicatif apparaissant dans l'�cran de config
 * @param   modifiable  1 si accessible dans l'�cran de configuartion avanac�e, 0 sinon
 * @param   validation (optionnel) : type de validation � r�aliser en saisie : d�faut required
 * @param   drapeau  (optionnel) : drapeau supplementaire (d�faut 1 : transmissible aux locales)
 * @param   die : que faire en cas d'erreur base de donn�es: d�faut : rien !)
 */

function add_config ($categorie,$cle,$valeur,$defaut,$description='',$modifiable=0,$validation="required",$drapeau=1,$die=1) {
	global $CFG;
	// rev 986 accelere un peu 
	// attention on teste bien FALSE et pas 0 
	if (get_config($cle,false)!==false ) return; 
    //TODO utiliser insert_record (plus de addslashes ....
	$valeur_esc=addslashes($valeur);
	$defaut=addslashes($defaut);
	$description=addslashes($description);
	$sql=<<<EOS
	insert into {$CFG->prefix}config
	(categorie,cle,valeur,defaut,description,modifiable,validation,drapeau)
	values ('$categorie','$cle','$valeur_esc','$defaut','$description','$modifiable','$validation','$drapeau')
EOS;
	//print $sql."<br/>";
	ExecRequete($sql,false,$die);
	return $valeur;
}


/**
 * test config en menu
 */


function config_en_menu () {
    global $CFG;


     $icon         = 'folder.gif';
    $expandedIcon = 'folder-expanded.gif';

    $menu = new HTML_TreeMenu();

    $sql =<<<EOS
    select distinct categorie from {$CFG->prefix}config order by categorie
EOS;
    $cats=get_records_sql($sql);
    foreach ($cats as $cat) {

        $noderef = new HTML_TreeNode(array('text' => "<b>".clean($cat->categorie)."</b>",
            'icon' => $icon, 'expandedIcon' => $expandedIcon,
            'expanded' => true));
        $menu->addItem($noderef);

        $sql2=<<<EOS
            select * from {$CFG->prefix}config
            where categorie='$cat->categorie' and modifiable=1
            order by cle
EOS;
        $items=get_records_sql($sql2,false);
        foreach ($items as $item) {
           // $item->description=addslashes($item->description);
           $len=strlen($item->valeur)+3;
           $id_def=$item->cle."_def";
           //$id_oldv=$item->cle."_old";
           $def_link="<a href=\"javascript:setDefaut('$item->cle','$id_def');\">".
              traduction('valeur_defaut',false,$item->defaut).
              "</a>";

          if ($item->defaut!="") {
           if ($item->validation=="required validate-digits")
            $title=traduction ("js_valeur_numerique_attendue");
           else  $title=traduction ("js_valeur_non_vide_attendue");
          }else {
            $title="";
            $item->validation='saisie';

          }
            $html=<<<EOH

                <span>
                $item->cle
                </span>
                <input id="$item->cle" name="cles[$item->cle]" id="$item->cle" size="$len" value="$item->valeur" title="$title" class="$item->validation" />
                <input type="hidden" id="$id_def" value="$item->defaut"/>
                <input type="hidden" name="olds[$item->cle]" value="$item->valeur"/>
                $def_link

                <span class="commentaire1"> $item->description </span>


                </span>


EOH;
            $nodealin   = new HTML_TreeNode(array('text' => clean ($html,999),
                'icon' => 'document2.png',  'expanded' => true));
            $noderef->addItem($nodealin);
        }

    }
    return $menu;
}



/**
 * renvoie toutes ses pr�ferences
 */
function get_preferences ($login=false) {
	global $USER;
	if (empty($login)) $login=$USER->id_user;
    //pas grave si ca ne donne rien
    if ($lignes=get_records("preferences","login='".addslashes($login)."'","categorie,cle",null,null,0,"",""))
        return $lignes;
    else return array(); //rien
}


function get_preference ($categorie,$cle,$login="",$die=0) {
        global $CFG,$USER;
	if (empty($login)) $login=$USER->id_user;
   		if ($login==$USER->id_user)   // dans le cache ????
   			if (isset ($USER->prefs[$categorie][$cle]))
        		return $USER->prefs[$categorie][$cle];
   $loginsl=addslashes($login); // rev 984
   $sql =<<<EOR
SELECT * FROM {$CFG->prefix}preferences
WHERE cle='$cle' and login='$loginsl' and categorie='$categorie'
EOR;
   if ($res=get_record_sql($sql,$die,"err_preference_non_trouve",$cle."@".$login))
     $val=$res->valeur;
   else $val= false;
    	if ($login==$USER->id_user)   // dans le cache ????
   			$USER->prefs[$categorie][$cle]=$val;
   	return $val;
}

function set_preference ($categorie,$cle,$valeur,$login="",$die=0) {
        global $CFG,$USER;
        if (empty($login)) $login=$USER->id_user;
        $valeur_esc=addslashes($valeur);
        if ($old=get_preference($categorie,$cle,$login,0)){ //d�ja en base (cle est une valeur unique !)
            if ($old->valeur !=$valeur) {
                $loginsl=addslashes($login); // rev 984
                $sql=<<<EOS
	               update {$CFG->prefix}preferences
				    set valeur='$valeur_esc'
				    where cle ='$cle'
				    and categorie='$categorie' and login='$loginsl'
EOS;
            ExecRequete($sql,false,$die);
            } else $valeur= $old->valeur;
        }else {
            add_preference($login,$categorie,$cle,$valeur,$valeur,'',$die);
        }
        //garde cette nouvelle valeur pour la suite dans USER
        $USER->$cle=$valeur;
        return $valeur;
}


function add_preference ($login,$categorie,$cle,$valeur,$defaut,$description='',$die=0) {
    global $CFG;
    if (empty($login)) return;
    $login=addslashes($login); // rev 984
    $valeur=addslashes($valeur);
    $defaut=addslashes($defaut);
    $description=addslashes($description);
    $sql=<<<EOS
    insert into c2ipreferences
    (login,categorie,cle,valeur,defaut,description)
    values ('$login','$categorie','$cle','$valeur','$defaut','$description')
EOS;
    //print $sql."<br/>";
    ExecRequete($sql,false,$die);
    return $valeur;

}

//set_config('','nombre_reponses_mini',4,1);
//set_config('','examen_anonyme',"1","1");
//set_config('','afficher_lien_mail_liste_qcm',"0","0");
//set_config('','peut_dupliquer_question',0,1);
