<?php
/**
 * @author Patrick Pollet
 * @version $Id: lib_examens.php 1298 2012-07-25 15:57:53Z ppollet $
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package c2ipf
 */



 define ('EXAMEN_CERTIFICATION'     ,0x1);
 define ('EXAMEN_POSITIONNEMENT'    ,0x2);
 define ('EXAMEN_ANONYME'           ,0x4);

 define ('EXAMEN_TIRAGE_MANUEL'     ,"manuel");
 define ('EXAMEN_TIRAGE_ALEATOIRE'  ,"aleatoire");
 define ('EXAMEN_TIRAGE_PASSAGE'    ,"passage");

 define ('EXAMEN_ORDRE_Q_ALEATOIRE'  ,"aleatoire");
 define ('EXAMEN_ORDRE_R_ALEATOIRE'  ,"aleatoire");
 
 define ('EXAMEN_ORDRE_Q_FIXE'  ,"fixe");
 define ('EXAMEN_ORDRE_R_FIXE'  ,"fixe");

 define ('EXAMEN_CORRECTION'  ,0x100);
 define ('EXAMEN_CHRONO'  ,0x200);
 define ('EXAMEN_ENVOI_RESULTATS'  ,0x400);

 define ('EXAMEN_EST_POOL'  ,0x800);







/*
 * bibliotheque de manipulations de l'entité examen
 */

//rev 980 tirages de questions déplacés dans ce script
 require_once($CFG->chemin_commun."/lib_tirages.php");

if ($USER->type_plateforme=='certification' || (isset($CFG->pool_en_positionnement) && $CFG->pool_en_positionnement)) {
    require_once($CFG->chemin_commun."/lib_pool.php");
}

 if (is_admin()) {   //utilisateur courant uniquement
    maj_bd_examens();
 }

 function maj_bd_examens () {
	 global $CFG,$USER;
  
 }


/*
 * mode d'afficahge d'un QCM
 * voir fonction imprime_examen
 */
define ('QCM_PREVISUALISATION',0);
define ('QCM_NORMAL',1);             //passage avec reprise réponses et enregistre en ajax
define ('QCM_CORRECTION',2);         // correction et réponses de l'étudiant
define ('QCM_CORRIGE',3);            // corrigé (pas les réponses étudiant)
define ('QCM_TEST',4);               // passage par un prof (pas de memo résultats ni reprise
define ('QCM_PASSAGE',5);             //passage SANS reprise réponses ni enregistre en ajax (positionnement lors du passage)


/**
 * @param int $id_examen numéro de l'examen
 * @param int $id_etab numéro de l'établissement
 * @param string $die 	si vrai declenche une erreur fatale en cas d'erreur sinon return null
 * @return objet 		la ligne extraite de la BD ou null en cas d'erreur (et $die=0)
 *  rev 979 ajout sécurisatio des paramétres comme cette fonction est un point de passage obligé
 */
function get_examen ($id_examen,$id_etab,$die=1) {
	return get_record("examens","id_examen=".(int)$id_examen." and id_etab=".(int)$id_etab,$die,"err_examen_inconnu",$id_etab.".".$id_examen);
}



/**
 * renvoie un examen identifiée par son identifiant national
 * je ne sais pas pourquoi j'ai eu l'idée de mettre des underscores dans les tables resultats !
 */
function get_examen_byidnat($id_nat,$die=1) {
	$tmp=explode("_", $id_nat);
    if (sizeof($tmp) == 2)
       return get_examen($tmp[1],$tmp[0],$die);
    // rev 930 considerer aussi la notation avec un point !
    $tmp=explode(".", $id_nat);
    if (sizeof($tmp) == 2)
       return get_examen($tmp[1],$tmp[0],$die);
    else if ($die) return get_examen(-1,-1,true);
    else return false;
}


/**
 * fonction utilisée sur la nationale our retrouver/creer un examen de stats
 * rev 977 il faut créer un autre examen avec le referentiel V2 !
 */

function get_examen_remontee($type_pf) {
	global $CFG;

	if ($type_pf=="positionnement")$nom=traduction("examen_remonte_posit");
	elseif ($type_pf=="certification")$nom=traduction("examen_remonte_certif");
	else return false;


	if ($examen=get_record("examens","nom_examen='".$nom."'",false)) return $examen;

	$examen=new StdClass();
	$examen->nom_examen=$nom;
	$examen->id_etab=$CFG->universite_serveur;
	$examen->ts_datedebut=1;
	$examen->ts_datefin=time()+YEARSECS*5; //dans 5 ans ...
	 $examen->positionnement=$examen->certification='NON';
    if ($type_pf=="positionnement"){
    	 $examen->positionnement='OUI';
    } else {
    	$examen->certification='OUI';
    }
	$examen->type_tirage=EXAMEN_TIRAGE_MANUEL; //important !!!


	if ($id=cree_examen($examen,$CFG->universite_serveur))
		return get_examen($id,$CFG->universite_serveur);
	else return false;

}

/**
 * renvoie les examens de l'établissement $id_etab du type
 * de la plateforme en cours
 * rev 979 ajout parametre $rec (recursif) pour les composantes (appel via le WS)
 */

function get_examens ($id_etab='',$tri='',$rec=false,$die=0) {
    global $USER,$CFG;
    $and = $id_etab ? " and id_etab=".$id_etab : "";
    $tri=$tri? $tri :"nom_examen";
    $sql=<<<EOS
select concat(id_etab,'.',id_examen) as id ,{$CFG->prefix}examens.*
from {$CFG->prefix}examens where {$USER->type_plateforme}='OUI' $and
EOS;
    if ($tri) $sql .=" order by $tri";
    $ret= get_records_sql($sql,$die);
    if ($rec) {
        $composantes=get_composantes($id_etab,'id_etab');
        foreach ($composantes as $composante) {
            if ($sub=get_examens($composante->id_etab,$tri,true,false))
            $ret=array_merge($ret,$sub);
        }
    }
    return $ret;
}

function get_examens_locaux($id_etab,$tri='',$die=false){
	  global $USER,$CFG;
	  $now=time();
      $tri=$tri? $tri :"ts_datedebut DESC";
      $sql=<<<EOS
select concat(id_etab,'_',id_examen) as id ,{$CFG->prefix}examens.*
from {$CFG->prefix}examens where ts_dateenvoi=0 and ts_datefin <$now and id_etab=$id_etab 
EOS;
    if ($tri) $sql .=" order by $tri";
    return get_records_sql($sql,$die);
}



function get_examens_bytags($tags,$sort='',$page=0, $recordsperpage=-1, &$totalcount=null) {
    return search_table_bytags('examens',$tags, $sort, $page, $recordsperpage,$totalcount);
}

function cree_examen($examen,$ide=false) {
	global $USER,$CFG;
	if (!$ide) $ide=$USER->id_etab_perso;
	if(empty($examen->id_etab))
		$examen->id_etab=$ide;

	$examen->ts_datecreation=$examen->ts_datemodification=time();

	$examen->ordre_q=empty($examen->ordre_q)?$CFG->examen_ordre_questions_defaut:$examen->ordre_q;
	$examen->ordre_r=empty($examen->ordre_r)?$CFG->examen_ordre_reponses_defaut:$examen->ordre_r;
	$examen->resultat_mini = empty($examen->resultat_mini)?$CFG->examen_seuil_validation:$examen->resultat_mini;
	$examen->type_tirage=empty($examen->type_tirage)?$CFG->examen_type_tirage_defaut:$examen->type_tirage;

	if (empty($examen->ts_datedebut))
		$examen->ts_datedebut=mktime( $CFG->examen_heure_debut_defaut, $CFG->examen_minute_debut_defaut,0, (int) date('m'), (int) date('d') + $CFG->examen_date_defaut, (int) date('Y'));

	if(empty($examen->ts_datefin))
		$examen->ts_datefin=$examen->ts_datedebut+3600*$CFG->examen_duree_defaut;

   // 1 heure par défaut

   // rev 978 22/12/2010
   if (!isset( $examen->ts_dureelimitepassage))
   		 $examen->ts_dureelimitepassage=$CFG->limite_temps_passage;


    // rev 978 03/01/2011  examen créé par le web service sans affiche_chrono (WSDL version 1)
    $examen->affiche_chrono=isset($examen->affiche_chrono)? $examen->affiche_chrono: $examen->ts_dureelimitepassage >0;


    $examen->template_resultat=''; // rev 957 pb MySQL a UPMC (Error: 1101 SQLSTATE: 42000  (ER_BLOB_CANT_HAVE_DEFAULT)

	if( $id=insert_record("examens",$examen,true,'id_examen',false)) {
		if ($examen->type_tirage == 'aleatoire')
			tirage_questions ($id,$examen->id_etab);
		espion3("ajout","examen",$examen->id_etab . "." . $id,$examen);
	}
	return $id;
}

/**
* renvoie la liste des examens  ou est incrit l'utilisateur
* @param login: string
* attention la table ts_datefin,from_unixtime(ts_datefin)cm n'a pas de clé unique (id) !
* il FAUT donc ajouter au DEBUT du select une "clé" calculée
* qui servira pour le parcours du tableau d'objet !
*/

function get_examens_inscrits($login,$tri='',$die=0) {
	global $CFG,$USER;
	if (!$tri) $tri='id' ;
    $loginsl=addslashes($login); // rev 984
	$sql=<<<EOS
select concat({$CFG->prefix}qcm.id_etab,'.',{$CFG->prefix}qcm.id_examen) as id ,{$CFG->prefix}examens.*
from {$CFG->prefix}qcm,{$CFG->prefix}examens where login='$loginsl' and $USER->type_plateforme='OUI'
and {$CFG->prefix}qcm.id_examen={$CFG->prefix}examens.id_examen
and {$CFG->prefix}qcm.id_etab={$CFG->prefix}examens.id_etab
order by $tri
EOS;
	return get_records_sql($sql,$die);
}



/**
* renvoie la liste des inscrits a un examen
* @param string $id_examen
* @param string $id_etab
* @return un tableau de records
* TODO virer les anonymes ...
*/
function get_inscrits14 ($id_examen,$id_etab,$tri='',$die=0) {
	global $CFG;
	// la 1ere colonne be la table inscrits est le login, la clé
	// donc OK
    if (empty($tri)) $tri='nom,prenom asc';
	$sql =<<<EOS
		select i.*,'E' as genre
		from {$CFG->prefix}inscrits i ,{$CFG->prefix}qcm e
		where i.login=e.login
		and e.id_examen=$id_examen
		and e.id_etab=$id_etab
		order by $tri
EOS;
	return get_records_sql($sql,$die);
}

/**
 * rev 1.5 934 dans le cas ou un personnel peut passer un examen
 * aller aussi dans la table c2iutilisateurs
 * rev 944 ajout du genre pour webservice ET filtrage PWD dans liste inscrits
 *
 */
function get_inscrits($id_examen,$id_etab,$tri='',$die=0) {
    global $CFG;
    if (! $CFG->prof_peut_passer_qcm)
        return get_inscrits14 ($id_examen,$id_etab,$tri,$die);
    if (empty($tri)) $tri='nom,prenom asc';
    $sql1=<<<EO1
        select i.login,password,nom,prenom,etablissement,email,auth,numetudiant,'E' as genre
        from {$CFG->prefix}inscrits i ,{$CFG->prefix}qcm e
        where i.login=e.login
        and e.id_examen=$id_examen
        and e.id_etab=$id_etab
EO1;
    $sql2=<<<EO2
        select i.login,password,nom,prenom,etablissement,email,auth,numetudiant,'P' as genre
        from {$CFG->prefix}utilisateurs i ,{$CFG->prefix}qcm e
        where i.login=e.login
        and e.id_examen=$id_examen
        and e.id_etab=$id_etab
EO2;
    $sql="($sql1) UNION ($sql2) order by $tri";
    return get_records_sql($sql,$die);


}


/**
* renvoie la liste des non inscrits a un examen
* @param string $id_examen
* @param string $id_etab
* @param string criteres un bout de where supplementaie (ex nom like 'toto%' and prenom like '%a')
* @return un tableau de records
* TODO virer les anonymes ...
*/
function get_candidats_non_inscrits14 ($id_examen,$id_etab,$critere=false,$tri='',$die=false) {
    global $CFG;
    // la 1ere colonne be la table inscrits est le login, la clé
    // donc OK
    if (empty($tri)) $tri='nom,prenom asc';
    $and =" and login not like 'ANONYME%' ";
    if (!empty($critere)) $and.= " and ".$critere ;
    $sql =<<<EOS
        select i.*
        from {$CFG->prefix}inscrits i
        where login not in
            (select login from {$CFG->prefix}qcm
                where id_examen=$id_examen
                and id_etab=$id_etab
            )
        $and
        order by $tri
        limit 0,$CFG->nombre_maxcandidats
EOS;
    return get_records_sql($sql,$die);
}


/**
 * rev 1.5 934 dans le cas ou un personnel peut passer un examen
 * aller aussi dans la table c2iutilisateurs
 *
 */
function get_candidats_non_inscrits ($id_examen,$id_etab,$critere=false,$tri='',$die=false) {

  global $CFG;
  if (! $CFG->prof_peut_passer_qcm)
    return get_candidats_non_inscrits14 ($id_examen,$id_etab,$critere,$tri,$die);
    if (empty($tri)) $tri='nom,prenom asc';
    $and =" and login not like 'ANONYME%' ";
    if (!empty($critere)) $and.= " and ".$critere ;
   $sql1 =<<<EO1
        select i.login,password,nom,prenom,etablissement,email,auth,numetudiant
        from {$CFG->prefix}inscrits i
        where login not in
            (select login from {$CFG->prefix}qcm
                where id_examen=$id_examen
                and id_etab=$id_etab
            )
        $and
EO1;
   $sql2 =<<<EO2
        select i.login,password,nom,prenom,etablissement,email,auth,numetudiant
        from {$CFG->prefix}utilisateurs i
        where login not in
            (select login from {$CFG->prefix}qcm
                where id_examen=$id_examen
                and id_etab=$id_etab
            )
        $and
EO2;
 $sql="($sql1) UNION ($sql2) order by $tri";
    return get_records_sql($sql,$die);


}



/** la fonction est_inscrit_examen vérifie que l'étudiant $login est inscrit ou non é l'examen $ide.$idq
* retourne 1 si oui, 0 si non
* @param    $idq        id_examen
* @param    $ide        id_etab
* @ si vides, renvoie le nombre d'inscriptions a n'importe quel examen n'importe quelle plateforme
* @param    $log   login
*/
function est_inscrit_examen($idq,$ide,$login){
    global $CFG;
    $where="login='".addslashes($login)."'";  // rev 984
    if ($idq && $ide) $where.= " and id_examen=".$idq. " and id_etab=".$ide;
    return count_records("qcm",$where);
}

/**
 * rev 962 ajout d'un tag associé a l'inscription et de la date d'inscription en TS Unix
 */
function inscrit_candidat($idq,$ide,$login,$tags='') {
	if ($login && $idq && $ide) {
		$ligne=new stdClass();
		$ligne->login=$login;
		$ligne->id_examen=$idq;
		$ligne->id_etab=$ide;
        $ligne->tags=$tags;
        $ligne->date=time();
		if (insert_record("qcm",$ligne,"",false,false)) //attention au rechargement de page pas fatal !
            espion3("inscription","examen",$login." ".$ide.".".$idq,$ligne);
	}
}


/**
 * probléme signalé sur le forum
 * http://www.c2i.education.fr/forum/viewtopic.php?f=4&t=128
 * si on recupere un examen qui a déja eu des passages et que l'on desinscrive
 * ses anciens candidats, leurs résultats restent en base, docn les stats sur le
 * nombre de passages sont fausses (nbpassages >nbinscrits !) '
 */

function desinscrit_candidat($idq,$ide,$login) {
	global $CFG;
	// trois criteres
    $loginsl=addslashes($login); // rev 984
	if ($login && $idq && $ide) {
		$critere =<<<EOS
			login='$loginsl'
     		and id_examen=$idq
			and id_etab=$ide
EOS;
		delete_records("qcm",$critere,false);  // attention au rechargement de page pas fatal !
	           espion3("desinscription","examen",$login." ".$ide.".".$idq,null);

	    // rev 974
	    // on garde historique comme ca si on le reinscrit on pourra toujours le renoter !
	    require_once($CFG->chemin.'/commun/lib_resultats.php');
	    purge_resultats_inscrit($idq,$ide,$login,false);
    }
}



/**
* PP rev 1.3.1
* compte les inscrits a l'examen
* rev 1.41 ne prends que ceux qui sont toujours dans la table des inscrits
* rev 934 tient compte des eventuels profs si permis
*/

function compte_inscrits($id_examen,$id_etab) {
	global $CFG;

	if (! $CFG->prof_peut_passer_qcm) {  //cas simple
		$sql=<<<EOS
			select count({$CFG->prefix}qcm.login) as nb
			from {$CFG->prefix}qcm inner join {$CFG->prefix}inscrits on {$CFG->prefix}qcm.login={$CFG->prefix}inscrits.login
				where id_etab=$id_etab
					and id_examen=$id_examen
EOS;
			$res=get_record_sql($sql,false);
			if ($res) return $res->nb;
			else return 0;
	}
    // cas ou les utilisateurs peuevent passer le QCM
	$tmp=get_inscrits($id_examen,$id_etab,'login',false);
	return count($tmp);

}


/**
 * rev 986 sql plus simple ( plus de doublons)
 */
function get_examens_auteur($email) {
    global $CFG;

    if (empty($email)) return array(); // pas moyen de le retrouver
 /***
    $sql=<<<EOS
    select E.*
    from {$CFG->prefix}examens E inner join {$CFG->prefix}utilisateurs P
    on E.auteur_mail=P.email
    where P.email='$email'
    order by E.id_etab,E.id_examen
EOS;
***/

    $sql=<<<EOS
    select E.*
    from {$CFG->prefix}examens E
    where E.auteur_mail='$email'
    order by E.id_etab,E.id_examen

EOS;

    return get_records_sql($sql,false);  //pas fatale !

}


/*
* rev 1.41
*  - ajout du parametre optionel $login pour compter les passages d'un compte
* - ne prends que ceux qui sont toujours dans la table des inscrits
* TODO V 1.5 passer par la table c2iresutatsexamens ( si noté alors passé !)
* c2itracking sert toujors a voir combien de fois il l'a passé
* renvoie le nombre de passages REELS (peut etre >1 si autoisé é repasser l'examen)
* ne fonctionne pas pour un pool pére (renvoie 0)
* FONCTION NON UTILISEE


function compte_passages_reels($id_examen,$id_etab,$login="") {

       global $CFG;
       $and=$login? "and {$CFG->prefix}inscrits.login='".addslashes($login)."'" :"";
       // bizarrement il arrive qu'il y a ait plus de passages que d'inscrits ????
       // select count(etat) ne marche pas !
        $sql=<<<EOS
            select distinct {$CFG->prefix}inscrits.login
            from {$CFG->prefix}tracking inner join {$CFG->prefix}inscrits on {$CFG->prefix}tracking.login={$CFG->prefix}inscrits.login
            where id_objet='$id_etab.$id_examen'
            and etat='succes'
            and objet='qcm'
            $and
	    and action='passage'
EOS;
       $res=get_records_sql($sql,false);
     return count($res);
}
*/


/**
 * rev 1.5
 * comme les passages sont toujours notés en fin on ne regarde plus c2itracking mais c2iresultatsexamens)
 * qui contient une ligne par etudiant/examen (les profs ne comptent pas car pas notés en vrai
 *
 * @param  id_examen
 * @parm id_etab
 * @param login , si vide compte tous les passages de l'examen
 * note : ne peut pas renvoyer le nombres de passages effectifs (si on l'a autorisé é le repasser)
 * rev svn 808 (25/05/2009) fonctionne pour un pool pére en renvoyant la somme des passages des fils

 * pour inclure les passages non notés (fermeture intempestive) utiliser get_passages ...
 * rev 934 : compter aussi les eventuels passages de profs
 * */

if (0) {

    function compte_passages($id_examen,$id_etab,$login="") {

        global $CFG;

        $examen=get_examen($id_examen,$id_etab);
        if ($examen->est_pool) {
            $ret=0;
            $fils=liste_groupe_pool($id_examen, $id_etab);
            foreach($fils as $f)
            $ret += compte_passages($f->id_examen,$f->id_etab,$login);
            return $ret;
        }
        else {
            $cle=$id_etab."_".$id_examen;
            $and=$login? "and I.login='".addslashes($login)."'" :"";  //tous les passages ou juste celui la // rev 984
            $sql=<<<EOS
                select P.login
                from {$CFG->prefix}resultatsexamens P inner join {$CFG->prefix}inscrits I on I.login=P.login
                    where P.examen='$cle'
                        $and
EOS;

                $res=get_records_sql($sql,false);
                if ($CFG->prof_peut_passer_qcm) {
                    $sql2=<<<EO2
                        select P.login
                        from {$CFG->prefix}resultatsexamens P inner join {$CFG->prefix}utilisateurs I on I.login=P.login
                            where P.examen='$cle'
                                $and
EO2;
                        $res2=get_records_sql($sql2,false);
                } else
                    $res2=array();
                return count($res)+count($res2);
        }
    }

}

/**
 * revision 974
 * on ne compte que ceux qui sont TOUJOURS inscrits
 *
 */
function compte_passages($id_examen,$id_etab,$login="") {

    global $CFG;

    $examen=get_examen($id_examen,$id_etab);
    if ($examen->est_pool) {
        $ret=0;
        $fils=liste_groupe_pool($id_examen, $id_etab);
        foreach($fils as $f)
        $ret += compte_passages($f->id_examen,$f->id_etab,$login);
        return $ret;
    }
    else {
        $cle=$id_etab."_".$id_examen;
        $and=$login? "and I.login='".addslashes($login)."'" :"";  //tous les passages ou juste celui la // rev 984
        $sql=<<<EOS
            select P.login
            from {$CFG->prefix}resultatsexamens P inner join {$CFG->prefix}qcm I on I.login=P.login
                where P.examen='$cle' and I.id_examen=$id_examen and I.id_etab=$id_etab
                    $and
EOS;
           // print ($sql);
            $res=get_records_sql($sql,false);
           // print_object("resultats",$res);
           // die();

            return count($res);
    }

}


/**
 * renvoie la liste de ceux qui on passé l'examen (notés ou pas )
 * en principe tous ont été notés si ils ont fermé la fenetre correctement
 * mais bon ...
 * rev 819 gere les pools !!!
 */
function get_passages ($id_examen,$id_etab,$tri='') {
        global $CFG;
     $examen=get_examen($id_examen,$id_etab);
    if (!$examen->est_pool)
        return __get_passages_normal($id_examen,$id_etab,$tri);
    else
        return __get_passages_pool($id_examen,$id_etab,$tri);

}

/**
 * renvoie la liste de ceux qui on passé l'examen (notés ou pas )
 * en principe tous ont été notés si ils ont fermé la fenetre correctement
 * mais bon ...
 * TODO pb avec import AMC, il n'y a rien dans c2iresultats !
 */
function __get_passages_normal14 ($id_examen,$id_etab,$tri='') {
        global $CFG;
    if (empty($tri)) $tri='nom,prenom asc';
    $cle=$id_etab.".".$id_examen;  //arghh c'esun point depuis la V1.3 pas un tiret bas ...
        $sql=<<<EOS
     select I.*
     from {$CFG->prefix}resultats P inner join {$CFG->prefix}inscrits I on I.login=P.login
     where P.examen='$cle'
     group by P.login
     order by $tri
EOS;
     $res=get_records_sql($sql,false);
      return $res;
}

/**
 * rev 934
 */
function __get_passages_normal ($id_examen,$id_etab,$tri='') {
        global $CFG;

   if (!$CFG->prof_peut_passer_qcm)
        return __get_passages_normal14 ($id_examen,$id_etab,$tri);

    if (empty($tri)) $tri='nom,prenom asc';
    $cle=$id_etab.".".$id_examen;  //arghh c'est un point depuis la V1.3 pas un tiret bas ...
    $sql1=<<<EO1
     select  I.login,password,nom,prenom,etablissement,email,auth,numetudiant
     from {$CFG->prefix}resultats P inner join {$CFG->prefix}inscrits I on I.login=P.login
     where P.examen='$cle'
     group by P.login
EO1;
    $sql2=<<<EO2
     select  I.login,password,nom,prenom,etablissement,email,auth,numetudiant
     from {$CFG->prefix}resultats P inner join {$CFG->prefix}utilisateurs I on I.login=P.login
     where P.examen='$cle'
     group by P.login
EO2;
     $sql="($sql1) UNION ($sql2) order by $tri";
     $res=get_records_sql($sql,false);
      return $res;
}



function __get_passages_pool14($id_examen,$id_etab,$tri='') {
     global $CFG;
     /* NON car ne respecte pas l'ordre de tri demandé pour TOUS les inscrits !!!
    $fils=liste_groupe_pool($id_examen,$id_etab);
    $ret=array();
    foreach ($fils as $f) {
        $tmp=__get_passages_normal($f->id_examen,$f->id_etab,$tri);
        if (count($tmp))
              $ret= array_merge($ret,$tmp);
    }
    return $ret;
    */
   if (empty($tri)) $tri='nom,prenom asc';
       $sql=<<<EOS
    select I.*
    FROM {$CFG->prefix}examens E,{$CFG->prefix}resultats P,{$CFG->prefix}inscrits I
    where I.login=P.login
    and E.id_etab=$id_etab and E.pool_pere=$id_examen
    and concat(E.id_etab,".",E.id_examen)=P.examen
    group by P.login
    order by $tri
EOS;
    $res=get_records_sql($sql,false);
      return $res;

}



/**
 * rev 934, tenir compte des eventuels passages de utilisteurs personnels
 */
function __get_passages_pool ($id_examen,$id_etab,$tri='') {
        global $CFG;

   if (!$CFG->prof_peut_passer_qcm)
        return __get_passages_pool14 ($id_examen,$id_etab,$tri);

    if (empty($tri)) $tri='nom,prenom asc';

    $sql1=<<<EO1
    select I.login,password,nom,prenom,etablissement,email,auth,numetudiant
    FROM {$CFG->prefix}examens E,{$CFG->prefix}resultats P,{$CFG->prefix}inscrits I
    where I.login=P.login
    and E.id_etab=$id_etab and E.pool_pere=$id_examen
    and concat(E.id_etab,".",E.id_examen)=P.examen
    group by P.login
EO1;
    $sql2=<<<EO2
    select I.login,password,nom,prenom,etablissement,email,auth,numetudiant
    FROM {$CFG->prefix}examens E,{$CFG->prefix}resultats P,{$CFG->prefix}utilisateurs I
    where I.login=P.login
    and E.id_etab=$id_etab and E.pool_pere=$id_examen
    and concat(E.id_etab,".",E.id_examen)=P.examen
    group by P.login
EO2;



     $sql="($sql1) UNION ($sql2) order by $tri";
     $res=get_records_sql($sql,false);
      return $res;
}





/**
* @login
* @param  $idexamen = code examen = idetab.idexam (les deux !)
* @param  $idquestion = code question =idetab.idquestion
* @param  $numReponse = No de la reponse de 1 é 5...
* NB le code d'etablissement peut étre differents ...
* les idexamen et idquestion sont des No auto, mais locaux a chaque PF!
* donc non uniques nationalement, d'ou la concatenation.
* @ return false si pas de reponse

*/

function get_reponse_etudiant ($login,$id_question,$id_etabquestion,
     $id_examen, $id_etabexamen, $numReponse) {

     global $CFG;

     $cleExam=$id_etabexamen.".".$id_examen;
     $cleQuestion=$id_etabquestion.".".$id_question;
     $login=addslashes($login); // rev 984
     $sql =<<<EOS
select * from {$CFG->prefix}resultats
where examen='$cleExam'
and login='$login'
and question='$cleQuestion'
and reponse=$numReponse
EOS;
	return get_record_sql($sql,false); //renvoie false en cas de non réponse

}

/**
 * rev 978 renvoie le timestamp du 1er passage de l'examen par ce candidat
 * on cherche la date de la 1ere case qui a été cochée (pas terrible mais
 * tant que l'on n'a pas une table des tentatives en cours ...)
 * renvoie maintenant  pour un passage anonyme ou un examen generé lors du passage
 * pour lequels il n'y a pas de mémorisation des cases cochées.
 * @see web_lib.php print_timer()
 */
 function get_date_premier_passage($login,$id_examen, $id_etabexamen) {
       global $CFG;

     $cleExam=$id_etabexamen.".".$id_examen;
     $login=addslashes($login); // rev 984
     $sql=<<<EOS
        select min(ts_date) as mini
        from {$CFG->prefix}resultats
        where examen='$cleExam'
        and login='$login'
EOS;

      //attention cette requete avec un min ne renvoie jamais false !
      $res=get_record_sql($sql,false);

     // print_r($res);
      if ($res && $res->mini) return $res->mini;
      else return time();
 }


/**
* renvoie dans un tableau trié sur le Né de la réponse
* les réponses de l'étudiant é une certaine question d'un certain examen
*/

function get_reponses_etudiant ($login,$id_question,$id_etabquestion,
     $id_examen, $id_etabexamen) {

	$reponses=get_reponses ($id_question,$id_etabquestion,0);
	$ret=array();
	foreach ($reponses as $rep) {
		if ($rep_etudiant=get_reponse_etudiant($login,$id_question,$id_etabquestion,$id_examen, $id_etabexamen,$rep->num))
			$ret[]=$rep_etudiant;
	}
	return $ret;
}

/**
 * renvoie vrai si un examen est en cours
 * @param ligne objet un objet recu de get_examen
 * @param $now la date : si vide maintenant (utiliser une autre date pour des tests)
 */
function examen_en_cours($ligne,$now=false) {
    if (!$now) $now=time();
	return $now>=$ligne->ts_datedebut  && $ligne->ts_datefin >=$now;
}

function examen_passe($ligne,$now=false) {
 	  if (!$now) $now=time();
	return $ligne->ts_datefin < $now;
}



function examen_a_venir($ligne,$now=false) {
	  if (!$now) $now=time();
	return $ligne->ts_datedebut >$now;
}


function etat_examen($ligne,$now=false){
     if (!$now) $now=time();
   if ($ligne->ts_datefin < $now ) return -1;	//terminé
   else if ($ligne->ts_datedebut>$now) return 1; // avenir
   else return 0; //en cours
}



/**
 * retour les examens disponibles (a venir ou en cours)
 * utilise les vrais, pas les membres d'un pool'
 */
function get_examens_disponibles ($id_etab=false){
    global $USER,$CFG;
    if (!$id_etab) $id_etab=$USER->id_etab_perso;

    if (is_super_admin()) $chfin="";
    else $chfin=" and id_etab=".$id_etab;
    $res=array();
    // examens disponibles

    $resu=get_records("examens","pool_pere=0 ".$chfin,"id_etab,id_examen");
    foreach ($resu as $ligne){
        if (examen_a_venir($ligne) || examen_en_cours($ligne))
            $res[]=$ligne;
    }
    return $res;
}

/**
 * ajoute la question a cet examen
 */
function ajoute_question_examen($idq,$idqe,$idex,$idexe,$die=1) {
    $ligne=new StdClass();
    $ligne->id_examen=$idex;
    $ligne->id_examen_etab=$idexe;
    $ligne->id=$idq;
    $ligne->id_etab=$idqe;
    $ligne->ts_dateselection=time();  // V 1.5 date de selection si <> date examen a été changée
    if (insert_record("questionsexamen",$ligne,false,"",$die)) {
        set_field("questions","ts_dateutilisation",time(),"id=$idq and id_etab=$idqe"); //maj date utilisation
        return true;
    } else return false;

}

/**
 * il y a 4 critéres !!! on ne peut pas encore utiliser delete_record !!!!
 */
function supprime_question_examen ($idq,$ide,$idex,$idexe,$die=1) {
    global $CFG;
    $sql =<<<EOS
    delete from {$CFG->prefix}questionsexamen
    where id_examen=$idex and id_examen_etab=$idexe and id=$idq and id_etab=$ide
EOS;
    if ($resultat = ExecRequete ($sql, false,$die)) {
        //tracking :
        espion2("suppression", "tirage", $idexe.".".$idex);
        return true;
    } else return false;
}



/**
 * rev 944 (examen par demaine)
 * supprime d'un examen toutes les questions reliées é un referentiel donné
 * (utilisé lors de la modif d'un examen si on change les referentiels traités)
 */
function supprime_questions_referentiel_examen ($ref,$idex,$idexe,$die=1) {
    global $CFG;
 $questions=get_questions($idex,$idexe);
 $fait=0;
   // rev 978  dans quel champ de la question doit-on regarder ?
    $nomref='referentielc2i';

 foreach ($questions as $q) {
    if ($q->$nomref==$ref) {
        supprime_question_examen($q->id,$q->id_etab,$idex,$idexe,$die);
        $fait++;
    }
 }
 return $fait;
}


/**
 * rev 874 conversion d'une valeur en BD en une chaine affichable
 * dans l'attente d'un changement de structure BD pour virer 'aléatoire' (accent !)
 */
function type_tirage ($examen) {
    if ($examen->pool_pere!=0) return traduction('pool');
   switch ($examen->type_tirage) {
        case 'manuel': return traduction ('form_manuel'); break;
        case 'aleatoire': return traduction ('form_aleatoire'); break;
        case 'passage': return traduction ('form_passage_aleatoire');break;
        default: return '???';

   }
}


/**
 * rev 874 conversion d'une valeur en BD en une chaine affichable
 * dans l'attente d'un changement de structure BD pour virer 'aléatoire' (accent !)
*/
function ordre_affichage($valeur) {
    if ($valeur==EXAMEN_ORDRE_Q_ALEATOIRE) 
        return traduction ('form_aleatoire');
    else 
        return traduction ('form_fixe') ;
}


/**
 * rev 874 conversion d'une valeur en BD en une chaine affichable
 * dans l'attente d'un changement de structure BD pour virer 'aléatoire' (accent !)
 */
function algo_tirage ($examen) {
    global $CFG;
    if ($examen->pool_pere!=0) return '';
    switch ($examen->type_tirage) {
        case EXAMEN_TIRAGE_ALEATOIRE: 
            if ($CFG->algo_tirage==1)
                  return traduction ('algo_tirage_1');
            else  if ($CFG->algo_tirage==2)
                  return traduction ('algo_tirage_2');
            else 
                  return traduction ('algo_tirage_3');
        break;
        case EXAMEN_TIRAGE_MANUEL: return '';break;
        case EXAMEN_TIRAGE_PASSAGE: return '' ;break;
        default: return '';

   }
}



/** renvoie le tableau des questions depuis la liste générée lors de la construction de l'examen
 *  necessaire pour les examens de type "tirage lors du passage" pour proposer le corrigé de cet examen
 * (qui n'a pas été stocké en bd) ...). cette lgne a été deposée dans le formulaire envoyé
 * format <input type="hidden" value="1.1785_1.1786_1.1787_1.1789_1.1790_1.1792_1.1793_...9.1863_69.1876" name="questions"/>
 */

function retrouve_questions ($liste_questions) {

    $ret=array();
    $liste = explode("_", $liste_questions);
    if (sizeof($liste) > 0) {
        foreach ($liste as $question) {
            $items = explode(".", $question);
            if (count($items==2) )
                $ret[]=get_question  ($items[1],$items[0]);
            else erreur_fatale ("DEV:err_liste_questions_invalide",$liste_questions);
        }
        return $ret;
    } else erreur_fatale ("DEV:err_liste_questions_invalide",$liste_questions);
}





/**
 * affiche le nom de l'examen et ses dates de passages en clair
 * @param $ligne  un objet extrait de la table c2iexamen par get_record
 * TODO passer en userdate court !
 *
 */
function nom_complet_examen ($ligne) {

    if ($ligne->ts_datefin -$ligne->ts_datedebut >DAYSECS) // sur plusieurs jours ?
        return  traduction ("date_heure_jours",false,$ligne->nom_examen,
                                   userdate($ligne->ts_datedebut,'strftimedatetime'),
                                   userdate($ligne->ts_datefin,'strftimedatetime'));

  else
     return  traduction ("date_heure_jour",false,$ligne->nom_examen,
                                   userdate($ligne->ts_datedebut,'strftimedate'),
                                   userdate($ligne->ts_datedebut,'strftimetime'),
                                   userdate($ligne->ts_datefin,'strftimetime'));
}


/**
 * rev 1005
 */
function date_examen($ligne) {
        if ($ligne->ts_datefin -$ligne->ts_datedebut >DAYSECS) // sur plusieurs jours ?
        return  traduction ("date_heure_jours",false,'',
                                   userdate($ligne->ts_datedebut,'strftimedatetime'),
                                   userdate($ligne->ts_datefin,'strftimedatetime'));

  else
     return  traduction ("date_heure_jour",false,'',
                                   userdate($ligne->ts_datedebut,'strftimedate'),
                                   userdate($ligne->ts_datedebut,'strftimetime'),
                                   userdate($ligne->ts_datefin,'strftimetime'));
}

function duree_examen($ligne) {
      if ($ligne->ts_datefin -$ligne->ts_datedebut >DAYSECS) // sur plusieurs jours ?
      return '';
      return ceil(($ligne->ts_datefin-$ligne->ts_datedebut)/MINSECS);
}



/**
 * renvoie les stats sur un examen comme un objet contenant
 * nb, mini,maxi et moyenne
 * si jamais evalué renvoie un objet avec nb=0
 * rev 809 fonctionne aussi pour un pool
 */

function get_stats_examen ($idq,$ide) {
	global $CFG;

    $e=get_examen($idq,$ide);
    if ($e->est_pool) return get_stats_examen_pool ($idq,$ide);

	$cle=$ide."_".$idq;
	$tables="{$CFG->prefix}resultatsexamens R";
	$where="R.examen='$cle'";
	$sql=<<<EOS
	select count(R.score) as nb, avg(R.score) as moyenne, stddev(R.score) as stddev,min(R.score) as mini, max(R.score)as maxi
	from $tables
	where $where
EOS;
   // print($sql);
	if (!$ret= get_record_sql($sql,false))  {
		$ret=new StdClass();
		$ret->nb=0;
		$ret->mini=$ret->maxi=$ret->moyenne=$ret->stddev=""; //facilite l'affichage ;-)'
	}else {
		 $ret->moyenne=sprintf("%.2f",$ret->moyenne);
		 $ret->stddev=sprintf("%.2f",$ret->stddev);
	}
	return $ret;
}

/**
 * revision 809 cas des pools
 * renvoie les stats cumulées des fils
 */
function get_stats_examen_pool ($idq,$ide) {
    global $CFG;

     $e=get_examen($idq,$ide);
    if (! $e->est_pool) return get_stats_examen ($idq,$ide);

    $sql=<<<EOS
    select count(R.score) as nb, avg(R.score) as moyenne, stddev(R.score) as stddev,min(R.score) as mini, max(R.score)as maxi
    FROM {$CFG->prefix}examens E,{$CFG->prefix}resultatsexamens R
    where E.id_etab=$ide and E.pool_pere=$idq
    and concat(E.id_etab,"_",E.id_examen)=R.examen

EOS;
    if (!$ret= get_record_sql($sql,false))  {
        $ret=new StdClass();
        $ret->nb=0;
        $ret->mini=$ret->maxi=$ret->moyenne=$ret->stddev=""; //facilite l'affichage ;-)'
    } else {
    	 $ret->moyenne=sprintf("%.2f",$ret->moyenne);
		  $ret->stddev=sprintf("%.2f",$ret->stddev);
    }
    return $ret;
}



// Gestion pair_impair pour impression deux colonnes
function setImprimable ($tpl,$num_q,$version_imprimable) {
	if ($version_imprimable) {
		if ($num_q % 2)
			$tpl->assign ("p_i","_paire");
		 else
		    $tpl->assign ("p_i","_impaire");
	}else
		$tpl->assign("p_i","");
}

/**
 * imprime une question jolie
 * sortie de imprime_examen pour étre utilisée ailleurs (bilan question)
 *
 * @param $montreref 0 non 1 oui 3  néquestion  4 les 2
 */
function imprime_question ($num_q,$ligne_e,$ligne_q,
$melange_questions,$melange_reponses,$version_imprimable,$montre_ref,$mode,$login,$avec_commentaires=false) {
    global $CFG;

    $modele=<<<EOM
       <tr class="question_entete{p_i}">
           <td class="question_entete"> {question} {num_q} {id_q}
           <!-- START BLOCK : ref -->
             &nbsp; {referentiel}
           <!-- END BLOCK : ref -->
           <i>
           <!-- START BLOCK : note1 -->
            score : {note_stockee}
            <!-- END BLOCK : note1 -->

            <!-- START BLOCK : note2 -->

           detail : {recalcul}  recalcul :{note_recalculee}

            <!-- END BLOCK : note2 -->
            </i>
            </td>
        </tr>
        <tr class="question{p_i}">
           <td class="question"> {intitule_q} </td>
        </tr>



<!-- START BLOCK : docs -->
           <tr class="docs{p_i}">
            <td><ul>
               <!-- START BLOCK : doc -->
                <li  class="doc">{url_doc}</li>
                <!-- END BLOCK : doc -->
            </ul>
            </td></tr>
<!-- END BLOCK : docs -->
 <!-- START BLOCK : reponses -->
             <tr class="reponses{p_i}">
             <td>
             <ul class="reponses">
 <!-- START BLOCK : reponse -->
                <li class="reponse">
                    <!-- START BLOCK : checkbox -->
                        {corrige}
                                <input type="checkbox" name="r[{numrep}]" value="1" {checked}
                                    <!-- START BLOCK : onclick_ch -->
                                        onclick="enregistre_reponse('{user_id}','{idexe}.{idex}','{numrep}','{session_ch}')"
                                    <!-- END BLOCK : onclick_ch -->
                                />
                    <!-- END BLOCK : checkbox -->
                    <!-- START BLOCK : img -->
                        {corrige} <img src='{url_image}' alt=""/>
                    <!-- END BLOCK : img -->

                {reponse} {num_r}  : {intitule_r}
                 <!-- START BLOCK : rep_comm -->
            		<span class="commentaire2"> {comm}</span>
            	 <!-- END BLOCK : rep_comm -->
                
                </li>
<!-- END BLOCK : reponse -->
              </ul>
             </td>
            </tr>
 <!-- END BLOCK : reponses -->
EOM;


                $tpl= new SubTemplatePower($modele,T_BYVAR);    //créer une instance

                $tpl->prepare($CFG->chemin);

                setImprimable($tpl,$num_q,$version_imprimable);

                $tpl->assign("num_q", $num_q);
                //identifiant unique de la question (pour controle)
                if ($mode==QCM_CORRECTION) {
                    $tpl->assign ("id_q"," [".$ligne_q->id_etab.".".$ligne_q->id."]");
                }
                else
                     $tpl->assign ("id_q","");
                $tpl->assign("intitule_q", affiche_texte_question ($ligne_q->titre));
                 if ($montre_ref) {

                    //$referentiel = trim($ligne_q->referentielc2i) . "." . trim($ligne_q->alinea);
                    $referentiel=get_domaine_traite($ligne_q);
                    $id=$ligne_q->id_etab.".".$ligne_q->id;
	                $tpl->newBlock("ref");
                    switch ($montre_ref) {
                        case 1: $tpl->assign("referentiel", "($referentiel)"); break;
                        case 2: $tpl->assign("referentiel", $id); break;
                        case 3: $tpl->assign("referentiel", "($referentiel)  <b>".$id."</b>"); break;
                    }
                }

                //rev 904 affichage score obtenu
                // rev 911 si login vide, appel depuis bilan questions, evidemment pas de rec
                if ($login && $mode==QCM_CORRECTION) {
                     $clee=$ligne_e->id_etab."_".$ligne_e->id_examen;
                        $cleq=$ligne_q->id_etab.".".$ligne_q->id;
                        $critere="examen='".$clee."' and question='".$cleq."' and login='".addslashes($login)."'"; //rev 984
                    if (!empty($CFG->afficher_score_question)) {
                        $tpl->newBlock("note1");

                       if ($score=get_record("resultatsdetailles",$critere,false))
                         $tpl->assign("note_stockee",$score->score);
                       else $tpl->assign("note_stockee",""); // cas de l'autorisation de repasser
                    }

                    if (!empty($CFG->recalculer_score_question)) {
                        //pour accés a la classe resultat et noteuse
                        require_once($CFG->chemin_commun."/noteuse.class.php");
                        $noteuse =new noteuseTiragePassage($ligne_e->id_examen,$ligne_e->id_etab,$cleq);
                        $res=$noteuse->note_etudiant(get_inscrit($login));
                        //print_r($res);
                        if ($res->score_global !=-1) {  //attention au repassage
                            $tpl->newBlock("note2");
                            $tpl->assign("recalcul",$res->tab_debug[$cleq]);
                            $tpl->assign("note_recalculee",$res->tab_points[$cleq]);
                        }
                    }



                  }

                $docs=get_documents($ligne_q->id,$ligne_q->id_etab,false);
                if (count($docs)>0) {
	                $tpl->newBlock("docs");
	                setImprimable($tpl,$num_q,$version_imprimable);
	                foreach ($docs as $doc){
		                $tpl->newBlock("doc");
		                $tpl->assign("url_doc",$doc->url);
	                }
                }

                // réponses
                $melange_reponses=$melange_reponses && $ligne_e->ordre_r !='fixe';
                $tpl->newBlock("reponses");
                setImprimable($tpl,$num_q,$version_imprimable);
                $reps=get_reponses($ligne_q->id,$ligne_q->id_etab,$melange_reponses,false );

                	$num_r = 0;
                foreach($reps as $ligne_r) {
	                $num_r++;

	                $tpl->newBlock("reponse");
	                // rev 963 numérotation des réponses en chiffres ou lettres
	                 if ($CFG->numerotation_reponses==1)
	                		$tpl->assign("num_r", $num_r);
	                else
	                	$tpl->assign("num_r", chr(ord('A')+$num_r-1));
	                $tpl->assign("intitule_r", affiche_texte_reponse($ligne_r->reponse));
	                
	                if ($avec_commentaires && !empty($ligne_r->commentaires)) {
	                    $tpl->newBlock('rep_comm');
	                    $tpl->assign ('comm','('.$ligne_r->commentaires.')');
	                }
	                
	                
	                
	                $numrep= $ligne_q->id_etab . "_" . $ligne_q->id . "_" . $ligne_r->num;

	                if ($mode==QCM_PREVISUALISATION) {
		                $tpl->newBlock("img");
		                $tpl->assign ("corrige","");
		                $tpl->assign("url_image",$CFG->chemin_images."/case0.gif"); //simple image vide
	                }

	                if ($mode==QCM_TEST) {  //passage par un prof SANS enregistrement détaillés dans c2iresultats
		                $tpl->newBlock("checkbox");
		                $tpl->assign ("corrige","");
		                $tpl->assign("checked",""); // pas d'ancienne réponse
		                $tpl->assign ("numrep",$numrep);  // indice dans le tableau des réponses envoyés
	                }

	                if ($mode==QCM_NORMAL || $mode==QCM_PASSAGE) {    //passage normal
		                $rep=get_reponse_etudiant($login,$ligne_q->id,$ligne_q->id_etab,$ligne_e->id_examen,$ligne_e->id_etab
                              ,$ligne_r->num);
		                $checked=$rep? "checked='checked'":"";   //rev 981 compat W3C
		                $tpl->newBlock("checkbox");
		                $tpl->assign ("corrige","");
		                $tpl->assign("checked",$checked); // ancienne réponse
		                $tpl->assign ("numrep",$numrep);  // indice dans le tableau des réponses envoyés
		                if ($mode==QCM_NORMAL) {
			                $tpl->newBlock("onclick_ch");
			                $tpl->assign("idex", $ligne_e->id_examen);
			                $tpl->assign("idexe", $ligne_e->id_etab);
			                $tpl->assign("user_id", $login);
			                $tpl->assign("numrep", $numrep);
			                $tpl->assign("session_ch", $CFG->session_nom."=".session_id());
		                }
	                }

	                if ($mode==QCM_CORRECTION || $mode==QCM_CORRIGE ) { //corrigé avec/sans  réponses étudiant
		                if ($mode==QCM_CORRECTION)
			                $rep=get_reponse_etudiant($login,$ligne_q->id,$ligne_q->id_etab,$ligne_e->id_examen,$ligne_e->id_etab
                            ,$ligne_r->num);
		                else
			                $rep=false;
		                $tpl->newBlock("img");
		                $img="<img src='".$CFG->chemin_images."/i_valide_a.gif"."' alt='' />";
		                if ($ligne_r->bonne=='OUI')
			                $tpl->assign("corrige", "<span class='bonne'>$img</span>");
		                else
			                $tpl->assign("corrige", "<span class='mauvaise'>&nbsp;</span>");
		                if ($rep)
			                $tpl->assign("url_image",$CFG->chemin_images."/case1.gif");
		                else
			                $tpl->assign("url_image",$CFG->chemin_images."/case0.gif");

	                }

                }  //end reponses

    return array($tpl->getOutputContent(),$num_r);

}


/**
 * renvoie une image HTML d'un examen prét é étre affiché
 * fonctionne aussi si l'examen est en mode tirage lors du passage
 * @param mode :	0 mode previsualisation
 *             		1 mode reprise 'on coche les cases des réponses enregistrées'
 * 					2 mode correction on montre les réponses de l'étudiant ET les réponses bonnes
 * 					3 mode correction on montre les réponses bonnes (pas les réponses de l'étudiant')
 * 					4 passage sans enregistrer les réponses mais avec correction é la fin (similaire au cas type_tirage="passage" MAIS
 * 					  avec un jeu de questions déja connues (ex: un prof veut passer son examen pour voir . NB: pas d'AJAx, pas de
 * 					  reprise si il ferme ou recharge la page ...'))
 *
 * @param login : requis en mode 1 et 2 (sinon defaut a USER->id_user)
 * @param liste_questions  requis en mode 4 QCM_TEST
 * @param url_retour       url de rafraichissement de la liste si nécessaire
 * @return array le template ET la liste des questions utilisées
 *
 * attention au cas de l'examen de type 'tirage lors du passage , il faut en mode "corrigé" renvoyrer la liste des questions !

 */
function imprime_examen ($id_examen,$id_etabexamen,
		$melange_questions=true,
		$melange_reponses=true,
		$version_imprimable=false,
		$montre_ref=false,
		$mode=0,$login="",$liste_questions="",$url_retour="") {
	global $CFG,$USER;

	if (!$login) $login =$USER->id_user;

	$ligne = get_examen($id_examen, $id_etabexamen);

	$fiche=<<<EOF
<div id="qcm">

<!-- START BLOCK : form_begin -->

<form id="formulaire" action="{action}" method="post" onsubmit="return confirm('{conf_validation}');">

<input type="hidden" name="idex" value="{idex}"/>
<input type="hidden" name="idexe" value="{idexe}"/>
<input type="hidden" name="mode" value="{mode}"/>
<input type="hidden" name="url_retour" value="{url_retour}"/>
<input type="hidden" name="temps_expire" value="0"/>

<!-- END BLOCK : form_begin -->

<p><span class="rouge">{texte_choix_multiple}</span></p>
<table class="qcm">
	<thead>
	  <tr>
	  	<th>{liste_questions}</th>
	  </tr>
	</thead>
    <tbody>
 <!-- START BLOCK : question -->
   {question}
 <!-- END BLOCK : question -->
    </tbody>
</table>


<!-- START BLOCK : form_end -->
<div class="centre">
      <input name="questions" type="hidden" value="{liste}" />
      <input type="hidden" name="nbquestions" value="{nbquestions}" />
	  <input type="hidden" name="nbreponses" value="{nbreponses}"/>
	  <input name="valider" type="submit" class="saisie_bouton" id="valider" value="{terminer}" />

	<!-- START BLOCK : id_session -->
		<input name="{session_nom}" type="hidden" value="{session_id}"/>
	<!-- END BLOCK : id_session -->
</div>
	</form>
<!-- END BLOCK : form_end -->

</div>
EOF;


	$ligne=get_examen($id_examen,$id_etabexamen);
	$tpl= new SubTemplatePower($fiche,T_BYVAR);    //créer une instance

	$tpl->prepare($CFG->chemin);

	if ($mode==QCM_NORMAL || $mode==QCM_PASSAGE || $mode==QCM_TEST) {
		if ($mode==QCM_NORMAL) {
			$CFG->enregistre_reponses_ajax=1; //pas pour un prof qui teste son examen !
        }
		$tpl->newBlock ("form_begin");
		$tpl->assign("idex", $id_examen);
		$tpl->assign("idexe", $id_etabexamen);
		$tpl->assign("action",$CFG->chemin."/codes/qcm/action.php");
		$tpl->assign("mode",$mode);
        $tpl->assign("url_retour",$url_retour);
		form_session ($tpl);

	}

	$melange_questions= $melange_questions && $ligne->ordre_q !='fixe';
	//attention ici !
	if ($ligne->type_tirage==EXAMEN_TIRAGE_PASSAGE) { //cas particulier
		switch ($mode) {
			case  QCM_PREVISUALISATION :;
			case  QCM_NORMAL :;
            case  QCM_PASSAGE :;
			case  QCM_TEST :
                //TODO et pourquoi pas tirage_questions qui respecte les regles ????
			    $questions=tire_questions($id_examen,$id_etabexamen);  //choisies au hasard maintenant ()
			    break;
			 case QCM_CORRECTION:;
			 case   QCM_CORRIGE :;
			 	$questions =retrouve_questions ($liste_questions); //redecoupage liste codée ici avant
			 	break;
		}
	}else $questions=get_questions($id_examen,$id_etabexamen,$melange_questions); //lecture BD
	$num_q = 0;  //nombre de questions
	$liste_des_questions = ""; //liste codée
	$num_reponses=0;   //nombre total de cases cochables
	foreach ($questions as $ligne_q) {
		$num_q++;
		$tpl->newBlock("question");
        list($fiche,$nbr)=imprime_question ($num_q,$ligne,$ligne_q,$melange_questions,
            $melange_reponses,$version_imprimable,$montre_ref,$mode,$login);
        $tpl->assign("question",$fiche);
        $num_reponses+=$nbr;

		if ($liste_des_questions != "")
				$liste_des_questions .= "_";
		$liste_des_questions .= $ligne_q->id_etab . "." . $ligne_q->id;
	} // end questions


	if ($mode==QCM_NORMAL || $mode==QCM_PASSAGE || $mode==QCM_TEST) {
		$tpl->newBlock ("form_end");
	}

	//liste des questions pour la correction en mode "tirage lors du passage" et pour une vérification
    // des réponses enregistrées au fr et é mesure par ajax
    $tpl->assign("liste", $liste_des_questions); // ROOT de Ce sous-template !
    $tpl->assign("nbquestions", $num_q);
    $tpl->assign("nbreponses", $num_reponses);

    $tpl->assign("terminer", traduction("bouton_terminer"));
	return array($tpl->getOutputContent(),$liste_des_questions);
}


/**
 * la fontion supprime_examen permet de supprimer un examen ($ide.$idq) et dans le cas d'un pool de questions de supprimer les examens liés
 * @param $idq numéro de l'examen (unique par établissement)'
 * @param $ide numéro de l'établissement (unique nationale)'
 * rev 1.5 pas besoin  de type_p
*/

function supprime_examen($idq,$ide){

	v_d_o_d("es");  //on ne sait jamais ...

	$ligne=get_examen($idq,$ide); // erreur fatale si inconnu .

	// suppression des attachements de questions
	delete_records('questionsexamen','id_examen='.$idq." and id_examen_etab=".$ide);


	// suppression des inscriptions a l'examen
	delete_records('qcm','id_examen='.$idq." and id_etab=".$ide);


	$critere=$ide.".".$idq;  // critere unique examen dans les tables suivantes
	// suppression des réponses des étudiants  a cet examen" .
	delete_records("resultats","examen=".$critere);

	//suppression des résultats (scores, par competence et detailles)
    $critere=$ide."_".$idq;
	delete_records("resultatsexamens","examen=".$critere);
	delete_records("resultatsalinea","examen=".$critere);
	delete_records("resultatsdetailles","examen=".$critere);
    delete_records("resultatscompetences","examen=".$critere);
    delete_records("resultatsreferentiels","examen=".$critere);

    // suppression de l'examen
    delete_records('examens','id_examen='.$idq." and id_etab=" .$ide);

	//tracking
	espion3("suppression", "examen", $critere,$ligne);

	$ret=get_records("examens","pool_pere=".$idq);
	foreach ($ret as $examen)
		supprime_examen($examen->id_examen,$examen->id_etab);

}
/**
 * la fontion copie_examen permet de copier un examen ($ide.$idq)
 * @param $idq numéro de l'examen (unique par établissement)'
 * @param $ide numéro de l'établissement (unique nationale)'
 * @return int le nouvel id autoincrement de la copie
 * rev 1.5 pas besoin  de type_p
 * TODO v 1.5  dans le cas d'un pool de questions il faudra faire attention !!!
 *
*/
function copie_examen ($idq,$ide,$copie_questions=true) {
	global $USER;

	v_d_o_d("ed");  //on ne sait jamais ...
	$ligne=get_examen($idq,$ide); //erreur fatale si inconnu

    $ligne->id_etab=$USER->id_etab_perso; //on se l'approprie

	$ligne->auteur=get_fullname($USER->id_user);
	$ligne->auteur_mail=get_mail($USER->id_user);

	$ligne->nom_examen=traduction("copie_de")." ".$ligne->nom_examen;
	$ligne->ts_datecreation=$ligne->ts_datemodification=time();

	$ligne->anonyme=0; // attention un seul anonyme donc surement pas pas celui la
    //rev 978 RAZ date de remontée des stats é la nationale
    $ligne->ts_dateenvoi=0;

	// on indique a insert_record de renvoyer le nouvel id ET de virer la cle id_examen avant (autonum)...
	$newidq=insert_record("examens",$ligne,true,'id_examen');//,true,"err_duplication",$ide.".".$idq);


     // V 1.5 l'original est membre d'un pool
     //le clone doit dont recevoir ses questions du pool, pas de la BD
     if ($copie_questions && $ligne->pool_pere !=0) {
        $origine=array();
        $origine['type'] = 'examen';
        $origine['idq'] = $ligne->pool_pere;
        $origine['ide'] = $ligne->id_etab;
        attrib_q_exam($newidq, $ligne->id_etab, $origine,
            //config_nb_aleatoire($USER->id_user));
            config_nb_aleatoire($ligne->id_etab)); //rev 944

        $copie_questions=false;
     }
    //cas normal
	if ($copie_questions) {
        $ligneq=new StdClass();
        $ligneq->id_examen_etab=$ligne->id_etab;
        $ligneq->id_examen=$newidq;
		$questions=get_questions ($idq,$ide,false); // ses questions sans les mélanger et sans erreur si aucune
		foreach ($questions as $question) {
			//appartiennent aussi a ce nouvel examen
			$ligneq->id=$question->id;
			$ligneq->id_etab=$question->id_etab;
			insert_record("questionsexamen",$ligneq,"",true);//,"err_duplication",$ide.".".$idq);
		}
	}
	//on ne duplique pas les inscrits, ni les résultats
	espion3("duplication","examen",$ide.".".$idq."->".$ligne->id_etab.".".$newidq,$ligne);
	return $newidq;
}


////////////// exame anonyme


/**
 * parametre de l'examen anonyme ($idetab + $idex)
 * si il y en a un ET est "en cours"
 */
function get_examen_anonyme($id_etab="",$regarde_date=true){
	global $USER,$CFG;

    if (!$CFG->examen_anonyme) return false; // rev 809
	if (!$id_etab) $id_etab=$USER->id_etab_perso;
   
	$ret=get_record("examens","anonyme=1 and positionnement='OUI'" ,false);
    //print_r($ret);
	if ($ret && (examen_en_cours($ret) || !$regarde_date)) {
	    //print "OK";
    	return array($ret->id_examen, $ret->id_etab);
    }
	return false;
}


/**
 * rend anonyme un examen, comme il n'y en a qu'un seul, vire l'eventuel ancien '
 */
function set_examen_anonyme ($id_examen,$id_etab="") {
	global $USER,$CFG;
	if (!$id_etab) $id_etab=$USER->id_etab_perso;
	$ligne=new StdClass();
	if ( $old=get_examen_anonyme($id_etab,false)) {
		//TODO changer ca !
		$ligne->id_examen=$old[0];
		$ligne->id_etab=$old[1];
		$ligne->anonyme=0;
		// exmeple d'usage d'update_record avec deux clés identifiant le bon record ...
		update_record("examens",$ligne, 'id_etab','id_examen',true,"err_mise_a_jour_examen_anonyme",$ligne->id_etab.".".$ligne->id_examen);
	}
	$ligne->id_examen=$id_examen;
	$ligne->id_etab=$id_etab;
	$ligne->anonyme=1;

	// exmeple d'usage d'update_record avec deux clés identifiant le bon record ...
	update_record("examens",$ligne, 'id_etab','id_examen',true,"err_mise_a_jour_examen_anonyme",$ligne->id_etab.".".$ligne->id_examen);


}

///////////////////////////////////////////////////////////////////////////////  FIN V1.5


/**
 * retourne le nombre de questions assoicées é un examen d'identifiant idq pour l'examen, ide pour l'établissement
 * fonction préseente avec ce nom en V 1.4
 *
 */

function nbqe($idq,$ide){
   return count_records("questionsexamen","id_examen_etab=$ide and id_examen=$idq" );
}



/**
 * recupere le template de resultat de l'examen
 *
 */
function get_template_examen($id_examen,$id_etab=""){
	global $USER;
	if (!$id_etab) $id_etab=$USER->id_etab_perso;
	$ret=get_examen ($id_examen,$id_etab,$die=1);
	if ($ret)
		return $ret->template_resultat;
	return null;
}

/**
 *crée ou modifie le template de resultat de l'examen
 */
function set_template_examen ($id_examen,$id_etab="", $template) {
        global $USER;
        if (!$id_etab) $id_etab=$USER->id_etab_perso;
        $ligne=new StdClass();
        $ligne->id_examen=$id_examen;
        $ligne->id_etab=$id_etab;
        $ligne->template_resultat=$template;
        // exmeple d'usage d'update_record avec deux clés identifiant le bon record ...
        update_record("examens",$ligne, 'id_etab','id_examen',true,"err_mise_a_jour_template_examen",$ligne->id_etab.".".$ligne->id_examen);


}


/*
 * Fonction qui renvoie les champs possible du formulaire pour le template personnalisé
 *
 */
function generate_champs_template($tpl) {
	global $CFG;

        $refs = get_referentiels();
        include ($CFG->chemin."/langues/preconisations_".$CFG->langue.".php");
        $select =<<<EOT
<select name="champs" size="25" onclick="document.getElementById('vocab').innerHTML = this.value">
		<optgroup label="{description_domaine}">
        <!-- START BLOCK : domaine -->
                <option value="{domaine_ref}">{domaine} {libl_domaine_ref}</option>
        <!-- END BLOCK : domaine -->
        <!-- START BLOCK : domaine_revoir -->
                <option value="{domaine_ref}">{libl_domaine_ref}</option>
        <!-- END BLOCK : domaine_revoir -->
        </optgroup>
        <optgroup label="{score_obtenu}"><option value="[[ScoreGlobal]]">{score_global}</option>
        <!-- START BLOCK : score -->
                <option value="{score_ref}">{score} {libl_score_ref}</option>
        <!-- END BLOCK : score -->
        </optgroup>
        <optgroup label="{conditions}">
        <!-- START BLOCK : conditions -->
                <option value="{condition_value}">{libl_condition}</option>
        <!-- END BLOCK : conditions -->
        </optgroup>
        <optgroup label="{constantes}">
        <!-- START BLOCK : constantes -->
                <option value="{condition_value}">{libl_condition}</option>
        <!-- END BLOCK : constantes -->
        </optgroup>
</select>
EOT;

		$conditions = "<optgroup label=\"{conditions}\">";
		foreach ($test_score as $value)
			$conditions .= $value;
		$conditions .= "</optgroup>";

        $tmptpl= new SubTemplatePower($select, T_BYVAR);    //créer une instance
		$tmptpl->prepare($tpl->chemin);

		foreach ($refs as $domaine) {
			$ref = $domaine->referentielc2i;
			$tmptpl->newBlock("domaine");
			$tmptpl->assign("domaine_ref", "[[Domaine$ref]]");
			$tmptpl->assign("libl_domaine_ref", $ref);
			$tmptpl->newBlock("score");
			$tmptpl->assign("score_ref", "[[Score$ref]]");
			$tmptpl->assign("libl_score_ref", $ref);
        }
		foreach ($domaine_a_revoir as $value=>$libl){
			$tmptpl->newBlock("domaine_revoir");
			$tmptpl->assign("domaine_ref", "$libl");
			$tmptpl->assign("libl_domaine_ref", "$value");
		}
		foreach ($test_score as $value=>$libl){
			$tmptpl->newBlock("conditions");
			$tmptpl->assign("condition_value", "$libl");
			$tmptpl->assign("libl_condition", "$value");
		}

		foreach ($constantes as $value=>$libl){
			$tmptpl->newBlock("constantes");
			$tmptpl->assign("condition_value", "$libl");
			$tmptpl->assign("libl_condition", "$value");
		}

        return $tmptpl->getOutputContent();
}

/*
 * Fonction qui renvoie le tableau des scores pour le template personnalisé
 *
 */
function generate_tableau_template($tpl) {
	global $CFG;
        $refs = get_referentiels();
        $table =<<<EOT
<table width="90%" class="resultats">
<tbody>
<tr><th>{domaines_referentiel}</th><th  class="droite">{score} [[ScoreGlobal]]</th></tr>
<!-- START BLOCK : ligne -->
<tr  class="{paire_impaire}"><td>{domaine_ref}</td><td class="{class_couleur} droite">{score_ref}</td></tr>
<!-- END BLOCK : ligne -->
</tbody>
</table>
EOT;

        $tmptpl= new SubTemplatePower($table, T_BYVAR);    //créer une instance
		$tmptpl->prepare($tpl->chemin);
		$compteur_ligne = 0;
		foreach ($refs as $domaine) {
			$ref = $domaine->referentielc2i;
			$tmptpl->newBlock("ligne");
			$tmptpl->setCouleurLigne($compteur_ligne);
			$tmptpl->assign("domaine_ref", "[[Domaine$ref]]");
			$tmptpl->assign("score_ref", "[[Score$ref]]");
			$compteur_ligne++;
        }
        return $tmptpl->getOutputContent();
}

/*
 * Fonction qui renvoie le tableau des scores pour le template personnalisé
 *
 */
function generate_modele_template($tpl) {
	global $CFG, $USER;
	include ($CFG->chemin."/langues/preconisations_".$CFG->langue.".php");
		$texte=<<<EOT
<p>
{texte_dom}
</p>
<hr/>
<p>{dh_ip_passage}</p>
<p>{domaines_a_revoir} : [[Domaine_a_revoir]] </p>
<p>{score_global_perso} [[ScoreGlobal]]. </p>
##SI score_global &lt; 40%##<br />
<p>$preconisations[40]</p>
##FIN SI##
<br /><br />
##SI score_global &gt;= 40% &amp;&amp; score_global &lt; 70%##<br />
<p>$preconisations[70]</p>
##FIN SI##
<br /><br />
##SI score_global &gt;= 70%##<br />
<p>$preconisations[100]</p>
##FIN SI##
<br /><br />
EOT;
        $tmptpl= new SubTemplatePower($texte, T_BYVAR);    //créer une instance
		$tmptpl->prepare($tpl->chemin);
		$tmptpl->assign("texte_dom", generate_tableau_template($tmptpl));
		$tmptpl->assignGlobal ("dh_ip_passage",traduction("info_examen_passe_le",false, "[[Date_du_jour]]" , "[[Client]]"));
        return $tmptpl->getOutputContent();
}



/**
 * rev 982 les examens créés par Web Service sont verouillés par défaut
 */

function verouille_examen ($idq,$ide) {
    return _do_verouille_examen($idq,$ide,1);
}

function deverouille_examen ($idq,$ide) {
    return _do_verouille_examen($idq,$ide,0);
}

function _do_verouille_examen ($idq,$ide,$do) {
    global $USER,$CFG;
    $ligne=new StdClass();
    if ( $old=get_examen($idq,$ide,false)) {
        //TODO changer ca !
        $ligne->id_examen=$idq;
        $ligne->id_etab=$ide;
        $ligne->verouille=$do;
        if ($do)
            espion3("verouillage","examen",$ide.'.'.$idq, $old); //tracking et evenements
        else
            espion3("deverouillage","examen",$ide.'.'.$idq, $old); //tracking et evenements
        // exmeple d'usage d'update_record avec deux clés identifiant le bon record ..
        return update_record("examens",$ligne, 'id_etab','id_examen',false,"err_verouillage_examen",$ligne->id_etab.".".$ligne->id_examen);
    } else return false;
}


function evt_examen_modification ($data) {
    dump_event(__FUNCTION__,$data);
    return true;
}

function evt_examen_inscription ($data) {
    dump_event(__FUNCTION__,$data);
    return true;
}

function evt_examen_desinscription ($data) {
    dump_event(__FUNCTION__,$data);
    return true;
}

function evt_examen_ajout ($data) {
    dump_event(__FUNCTION__,$data);
    return true;
}


function evt_examen_suppression ($data) {
    dump_event(__FUNCTION__,$data);
    return true;
}


function evt_examen_envoi ($data) {
    dump_event(__FUNCTION__,$data);
    return true;
}

function evt_examen_verouillage ($data) {
    dump_event(__FUNCTION__,$data);
    return true;
}

function evt_examen_deverouillage ($data) {
    dump_event(__FUNCTION__,$data);
    return true;
}

function evt_qcm_passage ($data) {
    dump_event(__FUNCTION__,$data);
    return true;
}



//if(1) print_r(get_stats_examen(320,65));
