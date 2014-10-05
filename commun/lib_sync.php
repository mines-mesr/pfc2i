<?php
/**
 * @author Patrick Pollet
 * @version $Id: lib_sync.php 1298 2012-07-25 15:57:53Z ppollet $
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package c2ipf
 */

/**
 * biblioth�que de synchronisation locale nationale

* rev 906 Passage en ref�rences non recommand� PHP
*   voir http://c2i.education.fr/forum-c2i-1/viewtopic.php?t=83
*
* rev 978 acc�laration de la synchro des questions
* rev 995 bug a nouveau avec nusoap dans la synchro rapide des questions
* 
* rev 986 traitement par un wrapper des erreurs avec php_soap 5.3 qui n�gocie mal
* le telechargement du WSDL 
* 
**/

 require_once ($chemin."/commun/lib_rapport.php");


if (!class_exists('SoapClient')){
   erreur_fatale("err_extension_phpsoap_non_installe");
}

require_once ($CFG->chemin."/ws/clients/classes/c2i_soapserver.php");
/**
 * fonction ajout�e rev 919
 * s'occupe de toute la tripaille web service '->
 */

//important cette bibliotheqque est perim�e 
$CFG->synchro_nat_avec_nusoap=0;

// revision 986 bug avec php 5.3.x et curl sous https
$CFG->curl_force_ssl3=1;



/**
 * 
 * cette classe force php_soap a declarer la version de ssl =3
 * ce que ne semble plus faire certaines versions de php_curl
 * cf http://stackoverflow.com/questions/4721788/making-soap-call-in-php-and-setting-ssl-version
 * pour l'utiliser activer $CFG->curl_force_ssl3=1;

 * @author ppollet
 *
 */

class MonSoapClient extends SoapClient {
    
        
    protected function callCurl($url, $data) {
        $handle   = curl_init();
        curl_setopt($handle, CURLOPT_URL, $url);
        curl_setopt($handle, CURLOPT_HTTPHEADER, Array("Content-Type: text/xml") );
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_POSTFIELDS, $data);
        curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, false);
        // la ligne qui manque dans php_soap !!!!
        curl_setopt($handle, CURLOPT_SSLVERSION, 3);
        $response = curl_exec($handle);
        if (empty($response)) {
            throw new SoapFault('CURL error: '.curl_error($handle),curl_errno($handle));
        }
        curl_close($handle);
        return $response;
    }
    
    public function __doRequest($request,$location,$action,$version,$one_way = 0) {
        return $this->callCurl($location,$request);
    }
}


class mon_c2i_soapserver extends c2i_soapserver{
    /**
    * Constructor method
    * @param string $wsdl URL of the WSDL
    * @param string $uri
    * @param string[] $options  Soap Client options array (see PHP5 documentation)
    * @return c2i_soapserver
    */
    public function mon_c2i_soapserver($wsdl = "http://localhost/c2i/V1.5/ws/wsdl.php", $uri=null, $options = array()) {
        
        global $CFG; 
        
        if($uri != null) {
            $this->uri = $uri;
        }
        // Btw. if it fails at the downloading the WSDL file part, then download the WSDL manually (with curl for example), and use that file locally. 
        // IMHO __doRequest is not called while in the WSDL downloading stage.      
        $session = curl_init();
        curl_setopt($session, CURLOPT_URL, $wsdl);
        curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($session, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($session, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($session,CURLOPT_SSLVERSION,3);
        $vn = curl_exec($session);
        curl_close($session);
                
        file_put_contents($CFG->chemin_ressources.'/tmp/server.wsdl',$vn); 
        $this->client = new MonSoapClient($CFG->chemin_ressources.'/tmp/server.wsdl', $options);
    }
    
    
  
}


function connect_to_nationale() {
    
    if(!extension_loaded('soap')) {
        die ('extension php soap non installée');
    }
    
    
	global $CFG;
	//$CFG->adresse_pl_nationale="https://c2i.education.fr/pfv2/";
    $wsdl=$CFG->adresse_pl_nationale."ws/wsdl.php";
    $uri=$CFG->adresse_pl_nationale."ws/wsdl";

    $optionssoap=array('encoding'=>$CFG->encodage);
//rev 905 ajout parametres optionels proxy cf http://c2i.education.fr/forum-c2i-1/viewtopic.php?t=80
    if (!empty($CFG->proxy_host)) $optionssoap['proxy_host' ]=$CFG->proxy_host;
    // rev 984 bug avec certains PHP cf http://www.thedeveloperday.com/php-soapclient-proxy/
    // donc on force proxyort en int
    if (!empty($CFG->proxy_port)) $optionssoap['proxy_port' ]=(int)($CFG->proxy_port);
    if (!empty($CFG->proxy_login)) $optionssoap['proxy_login' ]=$CFG->proxy_login;
    if (!empty($CFG->proxy_password)) $optionssoap['proxy_password' ]=$CFG->proxy_password;


    if (empty($CFG->synchro_nat_avec_nusoap)) { // rev 890
      if (empty($CFG->curl_force_ssl3))
            $c2i=new c2i_soapserver($wsdl,$uri,$optionssoap); //normal
      else 
          $c2i=new mon_c2i_soapserver($wsdl,$uri,$optionssoap); //via le wrapper ci-dessus
    }
    else  {
        erreur_fatale("err_nusoap_plus_supporte");
    }

    //print_r($c2i);
	return $c2i;
}

/**
 *
 *@param $c2i instance d'un soapClient
 *@param $lr cl�s
 *@param options
 *@param $ide etablissement concern�
 *
 */

 function synchro_nationale ($c2i,$lr,$options,$ide=false) {
    global $CFG,$USER;
   if (!$ide) $ide=$USER->id_etab_perso;
   $resultats=array(); //�tats des op�rations
   espion2("debut_synchro_nationale","",$ide);
   set_ok (traduction ("info_connecte_nationale",false,$CFG->adresse_pl_nationale,$lr->getClient(),$lr->getSessionKey()),$resultats);
   /*
   if ($CFG->synchro_nat_avec_nusoap)
        set_ok ("synchro avec NuSoap",$resultats);
    else
        set_ok ("synchro avec phpclient",$resultats);
    */
   if(empty($CFG->curl_force_ssl3)) 
       set_ok ("synchro standard avec soapClient",$resultats);   
    else 
        set_ok ("synchro standard avec soapClient personnalisé",$resultats);
   $test=false;
   //la nationale est elle en UTF8 ?
   $version_nationale=$c2i->get_version($lr->getClient(),$lr->getSessionKey());
   //maj d'une globale pour la suite 
   $CFG->synchro_conversion_utf8=$version_nationale < 1.6;
   //MERCI au protocole SOAP qui convertit automatiquement si nécessaire d'iso en utf8 ;-) 
  
   foreach ($options as $option=>$doit) {
	   if ($doit) {
		   set_ok (traduction("debut_de",true,traduction("sync_$option")),$resultats);
		   switch ($option) {
			   case 0:$test=true;break;
			   case 1:synchro_etablissements($c2i,$lr,$ide,$test,$resultats); break;
			   case 2:synchro_familles($c2i,$lr,$ide,$test,$resultats); break;
			  // case 3:synchro_notions($c2i,$lr,$ide,$test,$resultats); break;
			   case 3:synchro_ressources($ide,$test,$resultats); break;
			   case 4:synchro_questions_fast($c2i,$lr,$ide,$test,$resultats); break;
			   case 5:synchro_questions_obsoletes($c2i,$lr,$ide,$test,$resultats); break;
               case 6:synchro_referentiel($c2i,$lr,$ide,$test,$resultats); break;


		   }
		   set_ok (traduction("fin_de",true,traduction("sync_$option")),$resultats);
	   }
   }
   espion2("fin_synchro_nationale","",$ide);
   return $resultats;

}


function synchro_etablissements ($c2i,$lr,$ide,$test,&$resultats) {
    global $CFG,$USER;
    //liste des �tablissements connus de la pf nationale (uniquement ceux du niveau 1, pas les composantes)
    $res=$c2i->get_etablissements($lr->getClient(),$lr->getSessionKey(),1);
    set_ok (traduction("nb_items_recus",false,count($res)),$resultats);
    $mes_etab=get_composantes(1); //ceux de niveau 1 que je connais maintenant
    set_ok (traduction("nb_items_presents",false,count($mes_etab)),$resultats);

    $table=array();
    foreach ($mes_etab as $etab)
        $table[$etab->id_etab]=$etab->nom_etab;

    $quoi=traduction ("etablissement");
    //modification
    $i=0;$nb=0;

    foreach ($res as $etab) {
        $etab=(object)$etab;   //nusoap rempli des tableaux pas des classes rev 945
        //V2 il FAUT récuperer l'établissement courant lors de la synchro initiale !
		    if (!$etab->error) {
			    unset($etab->error); //important
			    if (!empty( $table[$etab->id_etab])) { // rev 835
				    set_ok(traduction("maj_item",false,$quoi,$etab->id_etab,addslashes($etab->nom_etab )), $resultats);
				    unset($table[$etab->id_etab]);
				    //attention update_record vire l'attribut cl� (id_etab)
				    if (!$test){
					    update_record("etablissement",$etab,'id_etab','');
					    $nb++;
				    }
				    unset($res[$i]);
			    }
		    } else unset($res[$i]); //ne pas garder les records avec error set pour la suite
	    $i++;
    }
    set_ok (traduction("nb_items_maj",false,$nb),$resultats);

    //ajout
    $i=0;
    foreach ($res as $etab) {
        $etab=(object)$etab;   //nusoap rempli des tableaux pas des classes rev 945
        unset($etab->error);
        set_ok(traduction("ajout_item",false,$quoi,$etab->id_etab,$etab->nom_etab ), $resultats);
        if (!$test) {
                $id=insert_record("etablissement",$etab,true,'');
                if ($id !=$etab->id_etab)
                   set_erreur(traduction("pb_insertion_item",false,$quoi,$id,addslashes($etab->id_etab)),$resultats);
                $i++;
        }
    }
    set_ok (traduction("nb_items_ajoutes",false,$i),$resultats);

    //supression
    $i=0;
    foreach ($table as $id=>$nom) {
        set_ok (traduction("suppression_item",false,$quoi,$id,$nom),$resultats);
        if (!$test) {
                supprime_etablissement($id,$test);
                $i++;
        }
    }
    set_ok (traduction("nb_items_supprimes",false,$i),$resultats);
}

/**
 * introduite rev 977 (Sept. 2010)pour support du ref. V2 qui peut encore �voluer
 * plus simple : on supprime tout et on ajoute ceux recus de la nationale
 *
 */


function synchro_referentiel ($c2i,$lr,$ide,$test,&$resultats) {
     global $CFG,$USER;

    $tableSQL='referentiel';
    $res=$c2i->get_referentiels($lr->getClient(),$lr->getSessionKey());
    set_ok (traduction("nb_items_recus",false,count($res)),$resultats);
    //print_r($res);
    $mes=get_referentiels('referentielc2i',false);//pas d'erreur si aucun (install initiale)
    if (!$test) {
        delete_records($tableSQL,'');
        set_ok (traduction("nb_items_supprimes",false,count($mes)),$resultats);
    }

    //ajout
    $i=0;
    $quoi=traduction('referentiel');
     foreach ($res as $ref) {
        $ref=(object)$ref;   //nusoap rempli des tableaux pas des classes rev 945
        unset($ref->error);
        set_ok(traduction("ajout_item",false,$quoi,$ref->referentielc2i,$ref->domaine ), $resultats);
        if (!$test) {
                $id=insert_record($tableSQL,$ref,false,'',false);
                //set_erreur('id='.$id .' rid='.$ref->referentielc2i,$resultats);
                if (!$id)
                   set_erreur(traduction("pb_insertion_item",false,$quoi,$id,addslashes($ref->referentielc2i)),$resultats);
                $i++;
        }
    }
    set_ok (traduction("nb_items_ajoutes",false,$i),$resultats);


     $tableSQL='alinea';
     $res=$c2i->get_alineas($lr->getClient(),$lr->getSessionKey(),false); //toutes les comp�tences

    set_ok (traduction("nb_items_recus",false,count($res)),$resultats);
    $mes=get_alineas(false,false,false);  //tous
     if (!$test) {
        delete_records($tableSQL,'');
        set_ok (traduction("nb_items_supprimes",false,count($mes)),$resultats);
    }

    //ajout
    $i=0;
    $quoi=traduction('alinea');
     foreach ($res as $alin) {
        $alin=(object)$alin;   //nusoap rempli des tableaux pas des classes rev 945
        unset($alin->error);
        set_ok(traduction("ajout_item",false,$quoi,$alin->referentielc2i.'.'.$alin->alinea,$alin->aptitude ), $resultats);
        if (!$test) {
                $id=insert_record($tableSQL,$alin,true,'',false);
                //set_erreur('id='.$id .' aid='.$alin->id,$resultats);
                if ($id !=$alin->id)
                   set_erreur(traduction("pb_insertion_item",false,$quoi,$id,addslashes($alin->aptitude)),$resultats);
                $i++;
        }
    }
    set_ok (traduction("nb_items_ajoutes",false,$i),$resultats);




    //die();

}


function synchro_familles ($c2i,$lr,$ide,$test,&$resultats) {
     global $CFG,$USER;
    //liste des familles connues de la pf nationale
    $res=$c2i->get_familles($lr->getClient(),$lr->getSessionKey());
    set_ok (traduction("nb_items_recus",false,count($res)),$resultats);
     $mes=get_familles('idf',false); //ceux que je connais maintenant
    set_ok (traduction("nb_items_presents",false,count($mes)),$resultats);

    $table=array();
    foreach ($mes as $mon)
        $table[$mon->idf]=$mon->famille;
    $quoi=traduction("famille");
     //modification
    $i=0;$nb=0;
   // print_object('',$res);die();

    foreach ($res as $famille) {
          $famille=(object)$famille;   //nusoap rempli des tableaux pas des classes rev 945
          
          
          
	    if (!$famille->error) {
		    unset($famille->error); //important
		    if (! empty($table[$famille->idf])) { // rev 835
			    set_ok(traduction("maj_item",false,$quoi,$famille->idf,addslashes($famille->famille) ), $resultats);
			    unset($table[$famille->idf]);
			    //attention update_record vire l'attribut cl� (id_etab)
			    if (!$test) {
                    update_record("familles",$famille,'idf','');
                    $nb++;
                }
			    unset($res[$i]);

		    }
	    } else unset($res[$i]); //ne pas garder les records avec error set pour la suite
        $i++;
    }

    set_ok (traduction("nb_items_maj",false,$nb),$resultats);

      //ajout
    $i=0;
    foreach ($res as $famille) {
         $famille=(object)$famille;   //nusoap rempli des tableaux pas des classes rev 945
        set_ok(traduction("ajout_item",false,$quoi,$famille->idf,addslashes($famille->famille) ), $resultats);
        unset($famille->error);
        if (!$test) {
                $id=insert_record("familles",$famille,true,'');
                if ($id !=$famille->idf)
                    set_erreur(traduction("pb_insertion_item",false,$quoi,$id,$famille->idf),$resultats);
                 $i++;
        }
    }
    set_ok (traduction("nb_items_ajoutes",false,$i),$resultats);

     //supression
    $i=0;
    foreach ($table as $id=>$nom) {
        set_ok (traduction("suppression_item",false,$quoi,$id,$nom),$resultats);
        if (!$test) {
             delete_records("familles","idf=$id");
            $i++;
        }
    }
    set_ok (traduction("nb_items_supprimes",false,$i),$resultats);


   //set_erreur("synchro familles non implement�",$resultats);

}






/**
 * revision 958
 * recoit un tableau de qcmItemRecord avec pour chacun
 * la question
 * les r�ponses
 * les eventuels documents
 */
function synchro_questions_fast ($c2i,$lr,$ide,$test,&$resultats) {
	global $CFG,$USER;


	//une seule requete WS
	$res=$c2i->get_toutes_questions_et_reponses($lr->getClient(),$lr->getSessionKey(),$USER->type_plateforme);
	//print_object( "",$res);
	//die();
	set_ok (traduction("nb_items_recus",false,count($res)),$resultats);
	$mes=get_toutes_questions(false,false);// 22/05/2009 aussi les invalid�es localement !
	//print_r($mes);
	set_ok (traduction("nb_items_presents",false,count($mes)),$resultats);

	$table=array();
	foreach ($mes as $mon)
	$table[$mon->qid]=$mon->titre;
	$quoi=traduction("question");
	$quoi2=traduction("reponse");

	//modification
	$i=0;$nb=0;$nb2=0;
	foreach ($res as $question) {
		// print_r($question); die();
		if ($CFG->synchro_nat_avec_nusoap) { // rev 964
			$q=(object)($question['question']);   //nusoap rempli des tableaux pas des classes rev 945
			$reponses=$question['reponses'];
			$documents=$question['documents'];
		}
		else {
			$q=$question->question;
			$reponses=$question->reponses;
			$documents=$question->documents;
		}
		//double referentiel plus supporté
		if (!empty($q->alineaV2))
		    $q->alinea = $q->alineaV2;
		if (!empty($q->referentielc2iV2))
		    $q->referentielc2i = $q->referentielc2iV2;
		
		unset($q->alineaV2);
		unset($q->referentielc2iV2);
		//changement de contenu 
		$q->etat=QUESTION_VALIDEE;
		
		if (empty($q->error)) {
			unset($q->error); //important
			if (!empty($table[$q->qid])) { // rev 835
				if ( !est_utilise_examen($q->id,$q->id_etab) || $CFG->force_synchro_questions_utilisees) {  // rev 1000
					set_ok(traduction("maj_item",false,$quoi,$q->qid,addslashes($q->titre) ), $resultats);
					unset($table[$q->qid]);
					if (!$test) {
						//TODO les r�ponses
						// print_object("",$reponses);
						delete_records("reponses","id=$q->id and id_etab=$q->id_etab");
						foreach($reponses as $rep) {
							$rep=(object)$rep; //nusoap rempli des tableaux pas des classes rev 946
							if (!$rep->error) {
								unset($rep->error); //important
								unset($rep->qid); //important
                                unset($rep->num); //important rev 986
								$rep->bonne=$rep->bonne?'OUI':'NON';  // bonne est un boolean dans le WSDL !
								$idrep=insert_record("reponses",$rep,true,'');
								if (!$idrep)
									set_erreur(traduction("pb_insertion_item",false,$quoi2,$q->qid,$rep->reponse),$resultats);
								else
									set_ok(traduction("maj_item",false,$quoi2,$q->qid, $rep->reponse),$resultats);
							}
						}
						//rev 956 reception des documents
						delete_records("questionsdocuments","id=$q->id and id_etab=$q->id_etab");
						foreach($documents as  $doc) {
							$doc=(object)$doc; //nusoap rempli des tableaux pas des classes rev 946
							if (!$doc->error) {
								$idf=$doc->id_doc.".".$doc->extension;
								if (decode_document( $idf, $doc->base64,$q->id,$q->id_etab )) {
									unset($doc->base64);
									unset($doc->qid);
									unset($doc->error); //rev 960
									insert_record("questionsdocuments",$doc,false,false,false);

								}
							}
						}
						// test local pour voir si la question est refus�e localement , auquel cas on ne la revalide pas,
						//elle sera juste mise � jour
						// en V 1.6 on devrait introduire la notion de filtrage local des questions
						$qold=get_question ($q->id,$q->id_etab);
						//if (! est_validee($qold))  non ne gere pas le cas en attente , non examin�e
						if (est_refusee($qold)) // rev 848
							unset($q->etat); // ne pas changer l'�tat dans la  BD locale ...

						unset($q->qid); //attribut non en BD
						//attention update_record vire les attributs cl� (id id_etab) donc a faire en dernier
						update_record("questions",$q,'id','id_etab');
						$nb++;
					}
				}else  {
					//rev 956 reception des documents  temporairement dans les deux cas
					delete_records("questionsdocuments","id=$q->id and id_etab=$q->id_etab");
					foreach($documents as  $doc) {
						$doc=(object)$doc; //nusoap rempli des tableaux pas des classes rev 946
						if (!$doc->error) {
							$idf=$doc->id_doc.".".$doc->extension;
							if (decode_document( $idf, $doc->base64,$q->id,$q->id_etab )) {
								unset($doc->base64);
								unset($doc->qid);
								unset($doc->error); //rev 960
								insert_record("questionsdocuments",$doc,false,false,false);
								set_ok ("document $idf recu pour la question {$q->id}",$resultats);

							}else
								set_erreur ("document manquant de la nationale non recu pour la question {$q->id}",$resultats);
						}
						// il n'y a pas de documents on laisse tomber
					}


					// rev 855
					// depuis que certaines questions sont desormais en positionnement ET certification
					// il faut le faire savoir aux locales (sinon elles vont perdre les  'nouvelles'
					// questions de positionnement qui auraient d�ja �t� utilis�es localement en certification
					// ou l'inverse
					set_erreur(traduction ("question_utilisee",false, $q->qid),$resultats);
					set_ok(traduction("maj_item_etat",false,$quoi,$q->qid,addslashes($q->titre) ), $resultats);
					unset($table[$q->qid]);
					if (! $test) {
						$qold=get_question ($q->id,$q->id_etab);
						//if (! est_validee($qold))  non ne gere pas le cas en attente , non examin�e
						if (est_refusee($qold)) // rev 848
							unset($q->etat); // ne pas changer l'�tat dans la  BD locale ...
						unset($q->titre); // ne pas changer le texte
						unset($q->ts_dateutilisation); //ni les stats locales
						unset($q->ts_datecreation);
						//en gros on ne change que les drapeaux posit/certif, les familles, auteurs, mail , date derniere modif

						unset($q->qid); //attribut pas en BD
						//attention update_record vire les attributs cl� (id id_etab) donc a faire en dernier
						update_record("questions",$q,'id','id_etab');
						$nb2++;
					}
				}
				unset($res[$i]);
			}
		} else {
			unset($res[$i]); //ne pas garder les records avec error set pour la suite
			set_erreur("erreur nationale ".$q->error,$resultats);
		}
		$i++;
	}
	set_ok (traduction("nb_items_maj",false,$nb),$resultats);
	set_ok (traduction("nb_items_maj_etat",false,$nb2),$resultats);
	//ajout

	$i=0;
    //print_object( "",$res);
    //die();

	foreach ($res as $question) {
        if ($CFG->synchro_nat_avec_nusoap) { // rev 964
			$q=(object)($question['question']);   //nusoap rempli des tableaux pas des classes rev 945
			$reponses=$question['reponses'];
			$documents=$question['documents'];
		}
		else {
			$q=$question->question;
			$reponses=$question->reponses;
			$documents=$question->documents;
		}
		unset($q->error); //important
		//double referentiel plus supporté
		if (!empty($q->alineaV2))
		    $q->alinea = $q->alineaV2;
		if (!empty($q->referentielc2iV2))
		    $q->referentielc2i = $q->referentielc2iV2;
		unset($q->alineaV2);
		unset($q->referentielc2iV2);
		
		//changement de contenu
		$q->etat=QUESTION_VALIDEE;
		
		set_ok(traduction("ajout_item",false,$quoi,$q->qid,addslashes($q->titre) ), $resultats);
		unset($table[$q->qid]);
		if (!$test) {
			// reponses  attention on va virer $q->qid ensuite
			// rev 1102 notices bizarres en migration
			foreach($reponses as $rep) {
				$rep=(object)$rep; //nusoap rempli des tableaux pas des classes rev 946
				if (!$rep->error) {
					unset($rep->error); //important
					unset($rep->qid); //important
					$rep->bonne=$rep->bonne?'OUI':'NON';
					$idrep=insert_record("reponses",$rep,true,'');
					if ($idrep !=$rep->num)
						set_erreur(traduction("pb_insertion_item",false,$quoi2,$idrep,$rep->num),$resultats);
					else
						set_ok(traduction("maj_item",false,$quoi2 ." ".$q->qid,$idrep, $rep->num),$resultats);
				}
			}

			//manquait les documents pour les nouvelles questions !!!!
			foreach($documents as  $doc) {
				$doc=(object)$doc; //nusoap rempli des tableaux pas des classes rev 946
				if (!$doc->error) {
					$idf=$doc->id_doc.".".$doc->extension;
					if (decode_document( $idf, $doc->base64,$q->id,$q->id_etab )) {
						unset($doc->base64);
						unset($doc->qid);
						unset($doc->error); //rev 960
						insert_record("questionsdocuments",$doc,false,false,false);
						set_ok ("document $idf recu pour la question {$q->id}",$resultats);

					}else
						set_erreur ("document manquant de la nationale non recu pour la question {$q->id}",$resultats);
				}
				// il n'y a pas de documents on laisse tomber
			}

			unset($q->qid);  //important
			$id=insert_record("questions",$q,true,'');
			if ($id !=$q->id)
				set_erreur(traduction("pb_insertion_item",false,$quoi,$id,$q->id),$resultats);
			$i++;
		}
	}

	set_ok (traduction("nb_items_ajoutes",false,$i),$resultats);
	//on ne supprime jamais des questions locales !!!
	$i=0;
	foreach ($table as $id=>$nom) {
		$q=get_question_byidnat($id,false);
		if ($q && $q->{$USER->type_plateforme}=='OUI') {
			set_ok (traduction("devrait_suppression_item",false,$quoi,$id),$resultats);
			if (!$test) {
				//delete_records("familles","idf=$id");
				$i++;
			}
		}
	}
	set_ok (traduction("nb_questions_pas_nationale",false,$i),$resultats);

	//set_erreur("synchro questions non finie",$resultats);
}



function synchro_questions_obsoletes ($c2i,$lr,$ide,$test,&$resultats) {
	global $CFG,$USER;
	//liste des questions obsoletes connus de la pf nationale
	$res=$c2i->get_questions_obsoletes($lr->getClient(),$lr->getSessionKey(),$USER->type_plateforme);
   // print_r($res);
      set_ok (traduction("nb_items_recus",false,count($res)),$resultats);
	//$ligne=new StdClass();
    $i=0;
	foreach ($res as $ligne) {
         $ligne=(object)$ligne;   //nusoap rempli des tableaux pas des classes rev 945
       if(!$ligne->error)  //si aucune question a envoy� un record avec error non vide
		if ($localversion=get_question($ligne->id,$ligne->id_etab,false)) {
			if (!$test) {
				if (est_validee($localversion)) { //rev 978 diminue impact dans le tracking
				 	set_ok (traduction("question_invalidee",false,$ligne->qid),$resultats);
                	invalide_question($ligne->id,$ligne->id_etab,false);
		        	$i++;
				}
            }
        }
	}
     set_ok (traduction("nb_items_maj",false,$i),$resultats);
	//set_erreur("synchro questions obsol�tes non implement�",$resultats);
}

function envoi_mes_questions ($c2i,$lr,$ide,$test,$mes_questions) {
	global $CFG,$USER;

	if (!$ide) $ide=$USER->id_etab_perso;
	$resultats=array(); //�tats des op�rations
	espion2("debut_envoi_questions_locales","",$ide);
	set_ok (traduction ("info_connecte_nationale",false,$lr->getClient(),$lr->getSessionKey()),$resultats);

	$quoi=traduction("questions");
	set_ok (traduction("nb_items_a_envoyer",false,count($mes_questions),$quoi),$resultats);

	$questions=array();
	//print_r($mes_questions);
	foreach ($mes_questions as $qid=>$tmp) {
		$q=new StdClass(); //important !
		$q->question=get_question_byidnat($qid);
        $q->question->error=''; // rev 984 champ requis par l'encodage SOAP
        $q->question->qid=$qid;
		$q->reponses=get_reponses($q->question->id,$q->question->id_etab,false,false);
        foreach ($q->reponses as $rep) {
            $rep->error=''; // rev 984 champ requis par l'encodage SOAP
            $rep->qid=$qid;
            // rev 986 le champ bonne est un boolean dans le WSDL !!!
            $rep->bonne= $rep->bonne=='OUI';
        }
		// rev 956
        $q->documents=array();
        $docs=get_documents($q->question->id,$q->question->id_etab,false);
        foreach ($docs as $doc) {
            unset($doc->url);
            if ($b64=  encode_document($doc->id_doc.'.'.$doc->extension, $doc->id,$doc->id_etab )) {
                $doc->base64=$b64;
                $doc->error=''; // rev 984 champ requis par l'encodage SOAP
                $doc->qid=$qid;
                $q->documents[]=$doc;
            } else
               set_erreur("document {$doc->id_doc}.{$doc->extension} manquant pour question $qid",$resultats) ;
        }
        // rev 979 si on veut que les experts les voient
        // elles doivent �tre en certification
        $q->question->certification='OUI';
        // mais surtout pas dans les examens de positionnement sur la nationale (normalement c'est jamais car pas valid�e)
        $q->question->positionnement='NON';
        $questions[]=$q;

	}
	if (!$test) {
        //print_object("qq",$questions);
		$res=$c2i->envoi_questions($lr->getClient(),$lr->getSessionKey(),$questions);
		foreach($res as $q){
               $q=(object)$q; //nusoap rempli des tableaux pas des classes rev 946
			if (!$q->error) {
				set_field ("questions","ts_dateenvoi",time(),"id=$q->id and id_etab=$q->id_etab");
				set_ok(traduction("info_question_soumise_ok",false,$q->qid),$resultats);
			} else
				set_erreur($q->error,$resultats) ;
		}
	}else {
		foreach($questions as $q)
			set_ok ("simulation envoi de ".print_r($q,true),$resultats);
	}



	// set_erreur("envoi mes questions non implement�",$resultats);
	espion2("fin_envoi_questions_locales","",$ide);
	return $resultats;
}

function envoi_mes_examens ($c2i,$lr,$ide,$test,$mes_examens) {
	global $CFG,$USER;

	if (!$ide) $ide=$USER->id_etab_perso;
	$resultats=array(); //�tats des op�rations
	espion2("debut_envoi_examens_locaux","",$ide);
	set_ok (traduction ("info_connecte_nationale",false,$lr->getClient(),$lr->getSessionKey()),$resultats);

	$quoi=traduction("examens");
	set_ok (traduction("nb_items_a_envoyer",false,count($mes_examens),$quoi),$resultats);

	foreach ($mes_examens as $eid=>$tmp) {
		$examen=get_examen_byidnat($eid);

		$copies=get_records("resultatsexamens","examen='".$eid."'",false);
		$details=array();
		for ($i=0; $i<count($copies); $i++){
			$login=$copies[$i]->login;
			$login_ano=$ide.".".$copies[$i]->id; //numero �tablissement + num�ro auto de copie, donc unique
			$copies[$i]->login=$login_ano;
			$critere="examen='".$eid."' and login='".addslashes($login)."'";
			$tmp=get_records("resultatsdetailles",$critere,"question",false);
			for ($j=0;$j<count($tmp);$j++) {
				$tmp[$j]->login=$login_ano;
				$details[]=$tmp[$j];
			}
			unset($tmp);
		}
		$type_pf=$examen->certification=='OUI'?'certification':'positionnement';
		if (!$test) {
			if ($c2i->envoi_examen($lr->getClient(),$lr->getSessionKey(),$eid,$type_pf,$copies,$details)){
				set_field ("examens","ts_dateenvoi",time(),"id_examen=$examen->id_examen and id_etab=$examen->id_etab");
				set_ok(traduction("info_examen_soumis_ok",false,$eid),$resultats);
			}else
				set_erreur(traduction("info_examen_soumis_ko",false,$eid),$resultats) ;
		}else
			set_ok ("simulation envoi de ".print_r($copies,true).'\n'.print_r($details,true),$resultats);
	}
	// set_erreur("envoi mes questions non implement�",$resultats);
	espion2("fin_envoi_examens_locaux","",$ide);
	return $resultats;
}

/** 
 * 
 * accede le web service public pour cette information 
 */

function synchro_ressources($ide,$test,&$resultats) {
    global $CFG;
    $registrationurl=$CFG->adresse_serveur_public_c2i;
    
    set_ok (traduction ("info_connecte_public",false,$CFG->adresse_serveur_public_c2i),$resultats);
    $data = array(
          'wsfunction'=>'c2i_get_all_ressources',
          'wsformatout'=>'php',
          'c2i'=>$CFG->c2i,
    //'domaine'=>'D1'
    
    );
    $request = array(
    CURLOPT_URL        => $registrationurl,
    CURLOPT_POST       => 1,
    CURLOPT_POSTFIELDS => $data,
    );
    
    $res=c2i_http_request($request);
    
    //print_r($res);
    if ($res->errno==0) {
        $ressources=unserialize($res->data);

        if (is_array($ressources)){
            set_ok (traduction("nb_items_recus",false,count($ressources)),$resultats);
            foreach ($ressources as $ressource) { //ex pas de ressources pour un C2I
                if(!is_object($ressource) || !empty($ressource->error))
					continue;
                unset($ressource->url); //tempo
                unset($ressource->error);
                $ressource->id_etab=1;
                $ressource->modifiable=0;
                $ressource->ts_datemodification=time();
    
                // gros pb avec l'ordre mal renvoy� par la nationale 'domaine,competence,ordre'
                // et elles ne sont pas dans l'ordre D1,D2 ....' ...
                if ($old=get_record('ressources','id='.$ressource->id.' and id_etab=1',false)) {
                    set_ok ("maj ressource ".$ressource->id,$resultats); //avant
                    if (!$test)
                        update_record('ressources', $ressource,'id','id_etab');
                }
    
                else  {
                    $ressource->ts_datecreation=time();
                    if (!$test)
                        $id=insert_record('ressources',$ressource);
                    else 
                        $id='simulation';
                    set_ok ("ajout ressource ".$ressource->titre.' '.$id,$resultats);
                }
            }
        } else {
            //message d'erreur renvoy� par CURL ( not found) ou par le WS
            set_erreur($res->data,$resultats);
        }
  
    }
    else {
        set_erreur('erreur curl '.$res->errno.' '.$res->error,$resultats);
    
    }
}

/**
 * lecture des referentiels connus sur le serveur public C2i
 * @return referentielRecord[]
 */

function c2i_get_referentiels (&$resultats) {
    global $CFG;
    
    global $CFG;
    $registrationurl=$CFG->adresse_serveur_public_c2i;
    
    set_ok (traduction ("info_connecte_public",false,$CFG->adresse_serveur_public_c2i),$resultats);
    $data = array(
              'wsfunction'=>'c2i_get_referentiels',
              'wsformatout'=>'php',
             
    
    );
    $request = array(
    CURLOPT_URL        => $registrationurl,
    CURLOPT_POST       => 1,
    CURLOPT_POSTFIELDS => $data,
    );
    
    $res=c2i_http_request($request);
    
    //print_r($res);
    if ($res->errno==0) {
        $ressources=unserialize($res->data);
        // print_r($ressources);
        if (is_array($ressources)){
            $ret=array();
            set_ok (traduction("nb_items_recus",false,count($ressources)),$resultats);
            foreach ($ressources as $num=>$ressource) {
                $ressource->c2i=strtolower($ressource->c2i);
                if ($ressource->c2i==='c2i2e')
                    unset($ressources[$num]);
             }
            // ksort($ressources);
            //print_r($ressources); die();
             return $ressources;
        } else {
            //message d'erreur renvoy� par CURL ( not found) ou par le WS
            set_erreur($res->data,$resultats);
            return array();
        }    
        
    } else {
        set_erreur('erreur curl '.$res->errno.' '.$res->error,$resultats);
        return array();
    }    
}






/**
*
* excute une requete HTTP en mode REST via curl ajoutée rev 986
* pour l'instant utilisée seulement vers https://c2i.education.fr/c2iws/service.php
* pour obtenir les ressources
* @param array $config
* @param boolean $quiet
* @return StdClass
*/

function c2i_http_request($config, $quiet=false,$id_objet='') {
    global $CFG;

    if(!extension_loaded('curl')) {
        if ($quiet)
        return false;
        else
        die ('extension php curl non installée');
    }


    $ch = curl_init();

    // standard curl_setopt stuff; configs passed to the function can override these
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    // requis avec certains php recents contre un site https  
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch,CURLOPT_SSLVERSION,3);
    
    
    if (!ini_get('open_basedir')) {
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    }
    curl_setopt_array($ch, $config);

    if (!empty($CFG->proxy_host)) curl_setopt($ch,CURLOPT_PROXY,$CFG->proxy_host);
    // rev 984 bug avec certains PHP cf http://www.thedeveloperday.com/php-soapclient-proxy/
    // donc on force proxyort en int
    if (!empty($CFG->proxy_port)) curl_setopt($ch,CURLOPT_PROXYPORT, (int)$CFG->proxy_port);
    if (!empty($CFG->proxy_login) && !empty($CFG->proxy_password))
    curl_setopt($ch,CURLOPT_PROXYUSERPWD,$CFG->proxy_login.':'.$CFG->proxy_password);


    $result = new StdClass();
    // peut contenir un simple texte HTMl avec erreur 404
    $result->data = curl_exec($ch);
    $result->info = curl_getinfo($ch);

    $result->error = curl_error($ch);
    $result->errno = curl_errno($ch);

    if ($result->errno) {
        if ($quiet) {
            // When doing something unimportant like fetching rss feeds, some errors should not pollute the logs.
            $dontcare = array(
            CURLE_COULDNT_RESOLVE_HOST, CURLE_COULDNT_CONNECT, CURLE_PARTIAL_FILE, CURLE_OPERATION_TIMEOUTED,
            CURLE_GOT_NOTHING,

            );
            $quiet = in_array($result->errno, $dontcare);
        }
        if (!$quiet) {
            // log_warn('Curl error: ' . $result->errno . ': ' . $result->error);
            // espion3('erreur', 'curl', 'synchro nationale',$id_objet, $result);
        }
    }

    curl_close($ch);

    return $result;
}


