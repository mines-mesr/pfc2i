<?php
/**
 * @author Patrick Pollet
 * @version $Id: index.php 1262 2011-09-09 11:27:26Z ppollet $
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package c2ipf
 */

/**
 * rev 983 correction mauvaise initialisation de CFG->wwwroot
 */

$chemin = '..';
$chemin_commun = $chemin."/commun";
$fichier_langue_defaut="fr_utf8.php";
$fichier_langue_plateforme="c2i1_utf8.php";

require_once($chemin_commun."/weblib.php");
require_once ($chemin_commun."/fonctions_session.php");
require_once ($chemin_commun."/fonctions_divers.php");
require_once ($chemin_commun."/lib_langues.php");
require_once ($chemin_commun."/lib_tracking.php");


$serveur_bdd=optional_param("serveur_bdd","",PARAM_RAW);
$nom_bdd=optional_param("nom_bdd","",PARAM_RAW);
$pass_bdd=optional_param("pass_bdd","",PARAM_RAW);
$user_bdd=optional_param("user_bdd","",PARAM_RAW);

$tester=optional_param("tester","",PARAM_INT);

$c2i=optional_param("c2i","c2i",PARAM_RAW);
$prefix=optional_param("prefix",$c2i,PARAM_RAW); //non éditable pour l'instant
$wwwroot=optional_param("wwwroot","",PARAM_RAW);
//print_r($_SERVER);
//calcul de l'URL de la plateforme au 1er appel
if (empty($wwwroot) && isset($_SERVER['HTTP_REFERER'])) {
	$pu=parse_url($_SERVER['HTTP_REFERER']);
	print_r($pu);
	$tmp=add_slash_url($chemin);   // ../..  --> ../../
	$base=dirname($pu['path']);
	while (strpos($tmp,"../")===0) {
		$base=dirname($base);
		$tmp=substr($tmp,3);
		// print $base."<br/>".$tmp;
	}
	$wwwroot=$pu['scheme']."://".$pu['host'].$base;
}

$locale_url_univ=$wwwroot;

require_once("mini_config.php");
require_once ($chemin_commun."/lib_sync.php");

$dataroot=optional_param("dataroot",$CFG->chemin_ressources,PARAM_RAW);

require_once("lib_install.php");

require_once ($chemin . "/templates/class.TemplatePower.inc.php"); //inclusion de moteur de templates
//mise en page v1.6 sans tables
$tpl = new C2IPrincipale($CFG->chemin."/templates2/popup.tpl"); //cr�er une instance



$fiche=<<<EOT

<div class="information_gauche " id="tests" >
<fieldset>
<legend>{tests_parametres}</legend>

Veuillez régler les problèmes signalés en rouge avant de continuer l'installation
{contenu_direct}

</fieldset>
</div>
 <form class="normale" id="monform" name="nomform" action="installer.php"  method="post">
<fieldset>
<legend>{parametres_connexion}</legend>
<p class="double">

<label for="serveur_bdd">{form_serveur_bdd}<span class="info">{ex_form_serveur_bdd}</span></label>

<input type="text" name="serveur_bdd" id="serveur_bdd" value="{serveur_bdd}" size="40" class="saisie required" title="{js_valeur_manquante}"/>
</p>

<p class="double">
<label for="nom_bdd">{form_nom_bdd}<span class="info"></span></label>
<input type="text" name="nom_bdd"  id="nom_bdd" value="{nom_bdd}" size="40" class="saisie required" title="{js_valeur_manquante}"/>
</p>

<p class="double">
<label for="user_bdd">{form_user_bdd}<span class="info"></span></label>
<input type="text" name="user_bdd"  id="user_bdd" value="{user_bdd}" size="40" class="saisie required" title="{js_valeur_manquante}"/>
</p>

<p class="double">
<label for="pass_bdd">{form_pass_bdd}<span class="info"></span></label>
<input type="password" name="pass_bdd"  id="pass_bdd" value="{pass_bdd}" size="40" class="saisie required" title="{js_valeur_manquante}"/>
</p>
<!--
<p class="double">
<label for="prefix">{form_prefix}<span class="info"></span></label>
<input type="text" name="prefix"  id="prefix" value="{prefix}" size="40" class="saisie required" title="{js_valeur_manquante}"/>
</p>
-->
<input type="hidden" name="prefix"  id="prefix" value="c2i" />

</fieldset>


<fieldset>
<legend>{parametres_installation}</legend>
<p class="double">

<label for="wwwroot">{form_repertoire_installation}<span class="info">{ex_form_ri}</span></label>
<input type="text" name="wwwroot" id="wwwroot" value="{wwwroot}" size="40" class="saisie required" title="{js_valeur_url_incorrecte}"/>
</p>

<p class="double">
<label for="dataroot">{form_repertoire_ressources}<span class="info">{ex_form_re}</span></label>
<input type="text" name="dataroot" id="dataroot" value="{dataroot}" size="40" class="saisie required" title="{js_valeur_chemin_incorrecte}"/>
</p>

<p class="double">
<label for="c2i">{form_type_c2i}</label>
{c2i}
</p>

</fieldset>





<p class="simple">

{bouton_tester}  &nbsp; {bouton_continuer}
</p>

<input type='hidden' name='tester' value='1'/>
</form>

<!-- START BLOCK : pas_continuer -->
<script type="text/javascript">
//<![CDATA[

document.getElementById('bouton_continuer').disabled=true;
//]]>
</script>
<!-- END BLOCK : pas_continuer -->
EOT;




$options=array (
	"corps_byvar"=>$fiche
);


$tpl->prepare($chemin,$options);

$CFG->utiliser_prototype_js=1;
$CFG->utiliser_validation_js=1;


$tpl->gotoBlock("_ROOT");

$tpl->traduit ("titre_popup","installation1");

$nbErr = 0;
ob_start();
if (!$tester) {
	$nbErr = test_config($dataroot,$chemin_commun);
}else {

    // creation du fichier constantes.php
    $nbErr = test_config($dataroot,$chemin_commun);
    
    $modele="constantes_dist_v2.php";   
    $tmptpl= new subTemplatePower($chemin_commun."/".$modele);
    $tmptpl->prepare($chemin);
    
    $tmptpl->assign("serveur_bdd",$serveur_bdd);
    $tmptpl->assign("nom_bdd",$nom_bdd);
    $tmptpl->assign("user_bdd",$user_bdd);
    $tmptpl->assign("pass_bdd",$pass_bdd);
    $tmptpl->assign("chemin_ressources",$dataroot);
    $tmptpl->assign("locale_url_univ",$wwwroot);
    $tmptpl->assign("prefix",$prefix);
    $tmptpl->assign("c2i",$c2i);
    
    $cible=realpath($chemin_commun."/constantes.php");
    //$cible=realpath("/tmp/constantes.php");
    intituleTests("Essai de création du fichier $cible " );
    
    $fp = @fopen($cible, "w");
    if ($fp) {
        fwrite ($fp,$tmptpl->getOutputContent());
        fclose ($fp);
        succesTests( "");
    }else $nbErr += echecTests("erreur écriture ");
    
    $nbErr += test_bd($serveur_bdd,$nom_bdd,$user_bdd,$pass_bdd);
    
}
$content = ob_get_contents();
ob_end_clean();
$tpl->assign("_ROOT.contenu_direct",$content);


$tpl->assign("serveur_bdd",$serveur_bdd);
$tpl->assign("nom_bdd",$nom_bdd);
$tpl->assign("user_bdd",$user_bdd);
$tpl->assign("pass_bdd",$pass_bdd);
$tpl->assign("wwwroot",$wwwroot);
$tpl->assign("dataroot",$dataroot);
$tpl->assign("prefix",$prefix);
$tpl->assign("c2i",$c2i);

//print_bouton($tpl,"bouton_tester","tester","javascript:majDiv(\"tests\",\"index.php\",false,\"monform\");","","button" );

print_bouton($tpl,"bouton_tester","tester","javascript:document.forms[0].action=\"index.php\";","","submit" );

print_bouton($tpl,"bouton_continuer","continuer","","","submit" );

if ($nbErr) {
	$tpl->newBlock('pas_continuer'); 
	$tpl->gotoBlock("_ROOT");//important
}
//selection du c2i visé depuis les c2i connus au niveau nationale
$resultats=array();
$refs=c2i_get_referentiels($resultats);
//ajouter l'option 'sans réferentiel' = nouvelle plate-forme à parametrer entierement
$tmp=new StdClass();
$tmp->c2i='xx';
$tmp->titre='Sans réferentiel';
$refs[]=$tmp;
print_select_from_table($tpl, 'c2i', $refs, 'c2i','saisie', '', 'c2i','titre', false, $c2i);

$CFG->c2i=$c2i;

$tpl->printToScreen(); //affichage
?>

