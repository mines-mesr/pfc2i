<?php
/**
 * @author Patrick Pollet
 * @version $Id: majbase.php 1224 2011-03-16 11:53:54Z ppollet $
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package c2ipf V2
 * Synchronisation initilae d'une plateforme fraichement installée avec sa nationale
 */

$chemin = '..';
$chemin_commun = $chemin."/commun";
$chemin_images = $chemin."/images";

require_once($chemin_commun."/c2i_params.php");					//fichier de param�tres
require_once($chemin_commun."/lib_sync.php");


set_time_limit(0); //important

$ide=optional_param("ide",$USER->id_etab_perso,PARAM_INT); //�tab de l'examen, d�faut = ici '

$login_nat=optional_param("login_nat","",PARAM_RAW);
$pass_nat=optional_param("pass_nat","",PARAM_RAW);

$options=optional_param("option",array(),PARAM_RAW);


require_once( $chemin."/templates/class.TemplatePower.inc.php");    //inclusion de moteur de templates
//mise en page v1.6 sans tables
$tpl = new C2IPrincipale($CFG->chemin."/templates2/popup.tpl"); //cr�er une instance

//inclure d'autre block de templates

$forme1=<<<EOF

<div id="maj_ajax">
<form class="normale" id="monform" method="post" action="majbase.php">
<fieldset>
<legend>{synchronisation_plateforme} </legend>

<div class="commentaire1">{info_synchronisation_plateforme} </div>
<p class="double">
<label for="login_nat">{identifiant_national} </label>
<input type="text" name="login_nat" id="login_nat" value="" size="20"/>
</p>
<p class="double">
<label for="pass_nat">{mot_de_passe_national}  </label>
<input type="password" name="pass_nat" id="pass_nat" value="" size="20"/>
</p>


<input name="ide" type="hidden" value="{ide}"/>
<input type="hidden" name="retour_fiche" value="{retour_fiche}" />
<!-- START BLOCK : id_session -->
<input name="{session_nom}" type="hidden" value="{session_id}" />
<!-- END BLOCK : id_session -->

<!-- START BLOCK : option -->
<p class="double">
<label for="option_{num}">{val_option} </label>
<input type ="checkbox" value="1" name="option[{num}]" {checked} id="option_{num}" />
</p>

<!-- END BLOCK : option -->

<div class="centre">

{bouton:ok}
</div>

</fieldset>

</form>
</div>

EOF;

$forme2=<<<EOF

{resultats_op}

<div class="information">
{info_compte_cree}
</div>
EOF;

//pas encore de login connu
$USER= new StdClass();
$USER->type_plateforme='certification';
$USER->id_user='admin1';

if ($login_nat && $pass_nat) {

	require_once ($chemin."/commun/lib_rapport.php");
	$c2i=connect_to_nationale();
	try {
		$lr=$c2i->login($login_nat,$pass_nat);
		// print_r($lr);
		$tpl->assignInclude("corps",$forme2,T_BYVAR);
		$tpl->prepare($chemin);

		//recuperer ses infos personnelles
		$user=$c2i->get_personnel($lr->getClient(),$lr->getSessionKey(),$login_nat,'login');
		//important pour la synchro
		$USER->id_etab_perso=$user->etablissement;
		
		$resultats=synchro_nationale($c2i,$lr,$options);
		$c2i->logout($lr->getClient(),$lr->getSessionKey());
		set_ok(traduction("info_deconnecte_nationale",false,$CFG->adresse_pl_nationale),$resultats);
		if (count($resultats))
			$tpl->assign("resultats_op",print_details($resultats,20));
		else
			$tpl->assign("resultats_op","");
		//creer le compte du correspondant C2I en local
		//remettre le MDP qui n'a pas été envoyé par la nationale
		$user->password=md5($pass_nat);
		unset($user->profils); // donnée eronnée envoyée par la nationale V1.5
		unset($user->id); // donnée eronnée envoyée par la nationale V1.5
		unset($user->error); // donnée envoyée par la nationale V1.5
		$user->est_admin_univ='O'; //important
		//print_r($user);
		 
		if (cree_utilisateur($user,$user->etablissement)) {
			// avec ses droits
			$data=new StdClass();
			$data->login=$login_nat;
			$data->id_profil=1; // admin
			insert_record('droits',$data,false,false,false);
			
			//mettre a jour le compte admin
			$admin=new StdClass();
			$admin->login='admin1';
			$admin->etablissement=$user->etablissement;		
			update_utilisateur($admin,true);
			
			// et la locale est desermais bien configurée
			set_config('pfc2i','universite_serveur',$user->etablissement);
			
			
			
			
			
		}else {
			set_erreur('info_err_creation_utilisateur', $resultats);
		}
		
		


		$tpl->assign("_ROOT.titre_popup",traduction("synchronisation_plateforme"));

		$tpl->gotoBlock("_ROOT");
		$tpl->print_boutons_fermeture();
		$tpl->printToScreen();
		die();
	} catch (Exception $e) {
		print_r($e);
	}
}




$tpl->assignInclude("corps",$forme1,T_BYVAR);
$tpl->prepare($chemin);

$tpl->assign("_ROOT.titre_popup",traduction("synchronisation_plateforme"));
$tpl->assign("ide", $ide);


// mode simulation
$tpl->newBlock("option");
$tpl->assign ("num",0);
$tpl->assign ("val_option",traduction("sync_0"));
$tpl->setChecked (false,"checked");

// synchro referentiel
$tpl->newBlock("option");
$tpl->assign ("num",6);
$tpl->assign ("val_option",traduction("sync_6"));
$tpl->setChecked (true,"checked");

for ($i=1; $i <=15; $i++) {
	if ($i==6) continue; //synchro referentiel d�ja affich�
	if ($i==3 && (isset($CFG->utiliser_notions_parcours) && !$CFG->utiliser_notions_parcours)) continue;  //cette PF n'utilise pas les notions
	if (est_traduite("sync_$i")) {
		$tpl->newBlock("option");
		$tpl->assign ("num",$i);
		$tpl->assign ("val_option",traduction("sync_".$i));
		$tpl->setChecked (true,"checked");
	}

}


$tpl->gotoBlock("_ROOT");
$tpl->print_boutons_fermeture();


$tpl->printToScreen();										//affichage
?>

