<?php
// Projet TraceGPS
// fichier : modele/DAO.class.php   (DAO : Data Access Object)
// RÃ´le : fournit des mÃ©thodes d'accÃ¨s Ã  la bdd tracegps (projet TraceGPS) au moyen de l'objet PDO
// modifiÃ© par Jim le 12/8/2018

// liste des mÃ©thodes dÃ©jÃ  dÃ©veloppÃ©es (dans l'ordre d'apparition dans le fichier) :

// __construct() : le constructeur crÃ©e la connexion $cnx Ã  la base de donnÃ©es
// __destruct() : le destructeur ferme la connexion $cnx Ã  la base de donnÃ©es
// getNiveauConnexion($login, $mdp) : fournit le niveau (0, 1 ou 2) d'un utilisateur identifiÃ© par $login et $mdp
// existePseudoUtilisateur($pseudo) : fournit true si le pseudo $pseudo existe dans la table tracegps_utilisateurs, false sinon
// getUnUtilisateur($login) : fournit un objet Utilisateur Ã  partir de $login (son pseudo ou son adresse mail)
// getTousLesUtilisateurs() : fournit la collection de tous les utilisateurs (de niveau 1)
// creerUnUtilisateur($unUtilisateur) : enregistre l'utilisateur $unUtilisateur dans la bdd
// modifierMdpUtilisateur($login, $nouveauMdp) : enregistre le nouveau mot de passe $nouveauMdp de l'utilisateur $login daprÃ¨s l'avoir hashÃ© en SHA1
// supprimerUnUtilisateur($login) : supprime l'utilisateur $login (son pseudo ou son adresse mail) dans la bdd, ainsi que ses traces et ses autorisations
// envoyerMdp($login, $nouveauMdp) : envoie un mail Ã  l'utilisateur $login avec son nouveau mot de passe $nouveauMdp

// liste des mÃ©thodes restant Ã  dÃ©velopper :

// existeAdrMailUtilisateur($adrmail) : fournit true si l'adresse mail $adrMail existe dans la table tracegps_utilisateurs, false sinon
// getLesUtilisateursAutorises($idUtilisateur) : fournit la collection  des utilisateurs (de niveau 1) autorisÃ©s Ã  suivre l'utilisateur $idUtilisateur
// getLesUtilisateursAutorisant($idUtilisateur) : fournit la collection  des utilisateurs (de niveau 1) autorisant l'utilisateur $idUtilisateur Ã  voir leurs parcours
// autoriseAConsulter($idAutorisant, $idAutorise) : vÃ©rifie que l'utilisateur $idAutorisant) autorise l'utilisateur $idAutorise Ã  consulter ses traces
// creerUneAutorisation($idAutorisant, $idAutorise) : enregistre l'autorisation ($idAutorisant, $idAutorise) dans la bdd
// supprimerUneAutorisation($idAutorisant, $idAutorise) : supprime l'autorisation ($idAutorisant, $idAutorise) dans la bdd
// getLesPointsDeTrace($idTrace) : fournit la collection des points de la trace $idTrace
// getUneTrace($idTrace) : fournit un objet Trace Ã  partir de identifiant $idTrace
// getToutesLesTraces() : fournit la collection de toutes les traces
// getMesTraces($idUtilisateur) : fournit la collection des traces de l'utilisateur $idUtilisateur
// getLesTracesAutorisees($idUtilisateur) : fournit la collection des traces que l'utilisateur $idUtilisateur a le droit de consulter
// creerUneTrace(Trace $uneTrace) : enregistre la trace $uneTrace dans la bdd
// terminerUneTrace($idTrace) : enregistre la fin de la trace d'identifiant $idTrace dans la bdd ainsi que la date de fin
// supprimerUneTrace($idTrace) : supprime la trace d'identifiant $idTrace dans la bdd, ainsi que tous ses points
// creerUnPointDeTrace(PointDeTrace $unPointDeTrace) : enregistre le point $unPointDeTrace dans la bdd


// certaines mÃ©thodes nÃ©cessitent les classes suivantes :
include_once ('Utilisateur.class.php');
include_once ('Trace.class.php');
include_once ('PointDeTrace.class.php');
include_once ('Point.class.php');
include_once ('Outils.class.php');

// inclusion des paramÃ¨tres de l'application
include_once ('parametres.php');

// dÃ©but de la classe DAO (Data Access Object)
class DAO
{
    // ------------------------------------------------------------------------------------------------------
    // ---------------------------------- Membres privÃ©s de la classe ---------------------------------------
    // ------------------------------------------------------------------------------------------------------

    private $cnx;                // la connexion Ã  la base de donnÃ©es

    // ------------------------------------------------------------------------------------------------------
    // ---------------------------------- Constructeur et destructeur ---------------------------------------
    // ------------------------------------------------------------------------------------------------------
    public function __construct()
    {
        global $PARAM_HOTE, $PARAM_PORT, $PARAM_BDD, $PARAM_USER, $PARAM_PWD;
        try {
            $this->cnx = new PDO ("mysql:host=" . $PARAM_HOTE . ";port=" . $PARAM_PORT . ";dbname=" . $PARAM_BDD,
                $PARAM_USER,
                $PARAM_PWD);
            return true;
        } catch (Exception $ex) {
            echo("Echec de la connexion a la base de donnees <br>");
            echo("Erreur numero : " . $ex->getCode() . "<br />" . "Description : " . $ex->getMessage() . "<br>");
            echo("PARAM_HOTE = " . $PARAM_HOTE);
            return false;
        }
    }

    public function __destruct()
    {
        // ferme la connexion Ã  MySQL :
        unset($this->cnx);
    }

    // ------------------------------------------------------------------------------------------------------
    // -------------------------------------- MÃ©thodes d'instances ------------------------------------------
    // ------------------------------------------------------------------------------------------------------

    // fournit le niveau (0, 1 ou 2) d'un utilisateur identifiÃ© par $pseudo et $mdpSha1
    // cette fonction renvoie un entier :
    //     0 : authentification incorrecte
    //     1 : authentification correcte d'un utilisateur (pratiquant ou personne autorisÃ©e)
    //     2 : authentification correcte d'un administrateur
    // modifiÃ© par Jim le 11/1/2018
    public function getNiveauConnexion($pseudo, $mdpSha1)
    {
        // prÃ©paration de la requÃªte de recherche
        $txt_req = "Select niveau from tracegps_utilisateurs";
        $txt_req .= " where pseudo = :pseudo";
        $txt_req .= " and mdpSha1 = :mdpSha1";
        $req = $this->cnx->prepare($txt_req);
        // liaison de la requÃªte et de ses paramÃ¨tres
        $req->bindValue("pseudo", $pseudo, PDO::PARAM_STR);
        $req->bindValue("mdpSha1", $mdpSha1, PDO::PARAM_STR);
        // extraction des donnÃ©es
        $req->execute();
        $uneLigne = $req->fetch(PDO::FETCH_OBJ);
        // traitement de la rÃ©ponse
        $reponse = 0;
        if ($uneLigne) {
            $reponse = $uneLigne->niveau;
        }
        // libÃ¨re les ressources du jeu de donnÃ©es
        $req->closeCursor();
        // fourniture de la rÃ©ponse
        return $reponse;
    }


    // fournit true si le pseudo $pseudo existe dans la table tracegps_utilisateurs, false sinon
    // modifiÃ© par Jim le 27/12/2017
    public function existePseudoUtilisateur($pseudo)
    {
        // prÃ©paration de la requÃªte de recherche
        $txt_req = "Select count(*) from tracegps_utilisateurs where pseudo = :pseudo";
        $req = $this->cnx->prepare($txt_req);
        // liaison de la requÃªte et de ses paramÃ¨tres
        $req->bindValue("pseudo", $pseudo, PDO::PARAM_STR);
        // exÃ©cution de la requÃªte
        $req->execute();
        $nbReponses = $req->fetchColumn(0);
        // libÃ¨re les ressources du jeu de donnÃ©es
        $req->closeCursor();

        // fourniture de la rÃ©ponse
        if ($nbReponses == 0) {
            return false;
        } else {
            return true;
        }
    }


    // fournit un objet Utilisateur Ã  partir de son pseudo $pseudo
    // fournit la valeur null si le pseudo n'existe pas
    // modifiÃ© par Jim le 9/1/2018
    public function getUnUtilisateur($pseudo)
    {
        // prÃ©paration de la requÃªte de recherche
        $txt_req = "Select id, pseudo, mdpSha1, adrMail, numTel, niveau, dateCreation, nbTraces, dateDerniereTrace";
        $txt_req .= " from tracegps_vue_utilisateurs";
        $txt_req .= " where pseudo = :pseudo";
        $req = $this->cnx->prepare($txt_req);
        // liaison de la requÃªte et de ses paramÃ¨tres
        $req->bindValue("pseudo", $pseudo, PDO::PARAM_STR);
        // extraction des donnÃ©es
        $req->execute();
        $uneLigne = $req->fetch(PDO::FETCH_OBJ);
        // libÃ¨re les ressources du jeu de donnÃ©es
        $req->closeCursor();

        // traitement de la rÃ©ponse
        if (!$uneLigne) {
            return null;
        } else {
            // crÃ©ation d'un objet Utilisateur
            $unId = utf8_encode($uneLigne->id);
            $unPseudo = utf8_encode($uneLigne->pseudo);
            $unMdpSha1 = utf8_encode($uneLigne->mdpSha1);

            $uneAdrMail = utf8_encode($uneLigne->adrMail);
            $unNumTel = utf8_encode($uneLigne->numTel);
            $unNiveau = utf8_encode($uneLigne->niveau);
            $uneDateCreation = utf8_encode($uneLigne->dateCreation);
            $unNbTraces = utf8_encode($uneLigne->nbTraces);
            $uneDateDerniereTrace = utf8_encode($uneLigne->dateDerniereTrace);

            $unUtilisateur = new Utilisateur($unId, $unPseudo, $unMdpSha1, $uneAdrMail, $unNumTel, $unNiveau, $uneDateCreation, $unNbTraces, $uneDateDerniereTrace);
            return $unUtilisateur;
        }
    }


    // fournit la collection  de tous les utilisateurs (de niveau 1)
    // le rÃ©sultat est fourni sous forme d'une collection d'objets Utilisateur
    // modifiÃ© par Jim le 27/12/2017
    public function getTousLesUtilisateurs()
    {
        // prÃ©paration de la requÃªte de recherche
        $txt_req = "Select id, pseudo, mdpSha1, adrMail, numTel, niveau, dateCreation, nbTraces, dateDerniereTrace";
        $txt_req .= " from tracegps_vue_utilisateurs";
        $txt_req .= " where niveau = 1";
        $txt_req .= " order by pseudo";

        $req = $this->cnx->prepare($txt_req);
        // extraction des donnÃ©es
        $req->execute();
        $uneLigne = $req->fetch(PDO::FETCH_OBJ);

        // construction d'une collection d'objets Utilisateur
        $lesUtilisateurs = array();
        // tant qu'une ligne est trouvÃ©e :
        while ($uneLigne) {
            // crÃ©ation d'un objet Utilisateur
            $unId = utf8_encode($uneLigne->id);
            $unPseudo = utf8_encode($uneLigne->pseudo);
            $unMdpSha1 = utf8_encode($uneLigne->mdpSha1);
            $uneAdrMail = utf8_encode($uneLigne->adrMail);
            $unNumTel = utf8_encode($uneLigne->numTel);
            $unNiveau = utf8_encode($uneLigne->niveau);
            $uneDateCreation = utf8_encode($uneLigne->dateCreation);
            $unNbTraces = utf8_encode($uneLigne->nbTraces);
            $uneDateDerniereTrace = utf8_encode($uneLigne->dateDerniereTrace);

            $unUtilisateur = new Utilisateur($unId, $unPseudo, $unMdpSha1, $uneAdrMail, $unNumTel, $unNiveau, $uneDateCreation, $unNbTraces, $uneDateDerniereTrace);
            // ajout de l'utilisateur Ã  la collection
            $lesUtilisateurs[] = $unUtilisateur;
            // extrait la ligne suivante
            $uneLigne = $req->fetch(PDO::FETCH_OBJ);
        }
        // libÃ¨re les ressources du jeu de donnÃ©es
        $req->closeCursor();
        // fourniture de la collection
        return $lesUtilisateurs;
    }


    // enregistre l'utilisateur $unUtilisateur dans la bdd
    // fournit true si l'enregistrement s'est bien effectuÃ©, false sinon
    // met Ã  jour l'objet $unUtilisateur avec l'id (auto_increment) attribuÃ© par le SGBD
    // modifiÃ© par Jim le 9/1/2018
    public function creerUnUtilisateur($unUtilisateur)
    {
        // on teste si l'utilisateur existe dÃ©jÃ 
        if ($this->existePseudoUtilisateur($unUtilisateur->getPseudo())) return false;

        // prÃ©paration de la requÃªte
        $txt_req1 = "insert into tracegps_utilisateurs (pseudo, mdpSha1, adrMail, numTel, niveau, dateCreation)";
        $txt_req1 .= " values (:pseudo, :mdpSha1, :adrMail, :numTel, :niveau, :dateCreation)";
        $req1 = $this->cnx->prepare($txt_req1);
        // liaison de la requÃªte et de ses paramÃ¨tres
        $req1->bindValue("pseudo", utf8_decode($unUtilisateur->getPseudo()), PDO::PARAM_STR);
        $req1->bindValue("mdpSha1", utf8_decode(sha1($unUtilisateur->getMdpsha1())), PDO::PARAM_STR);
        $req1->bindValue("adrMail", utf8_decode($unUtilisateur->getAdrmail()), PDO::PARAM_STR);
        $req1->bindValue("numTel", utf8_decode($unUtilisateur->getNumTel()), PDO::PARAM_STR);
        $req1->bindValue("niveau", utf8_decode($unUtilisateur->getNiveau()), PDO::PARAM_INT);
        $req1->bindValue("dateCreation", utf8_decode($unUtilisateur->getDateCreation()), PDO::PARAM_STR);
        // exÃ©cution de la requÃªte
        $ok = $req1->execute();
        // sortir en cas d'Ã©chec
        if (!$ok) {
            return false;
        }

        // recherche de l'identifiant (auto_increment) qui a Ã©tÃ© attribuÃ© Ã  la trace
        $unId = $this->cnx->lastInsertId();
        $unUtilisateur->setId($unId);
        return true;
    }


    // enregistre le nouveau mot de passe $nouveauMdp de l'utilisateur $pseudo daprÃ¨s l'avoir hashÃ© en SHA1
    // fournit true si la modification s'est bien effectuÃ©e, false sinon
    // modifiÃ© par Jim le 9/1/2018
    public function modifierMdpUtilisateur($pseudo, $nouveauMdp)
    {
        // prÃ©paration de la requÃªte
        $txt_req = "update tracegps_utilisateurs set mdpSha1 = :nouveauMdp";
        $txt_req .= " where pseudo = :pseudo";
        $req = $this->cnx->prepare($txt_req);
        // liaison de la requÃªte et de ses paramÃ¨tres
        $req->bindValue("nouveauMdp", sha1($nouveauMdp), PDO::PARAM_STR);
        $req->bindValue("pseudo", $pseudo, PDO::PARAM_STR);
        // exÃ©cution de la requÃªte
        $ok = $req->execute();
        return $ok;
    }


    // supprime l'utilisateur $pseudo dans la bdd, ainsi que ses traces et ses autorisations
    // fournit true si l'effacement s'est bien effectuÃ©, false sinon
    // modifiÃ© par Jim le 9/1/2018
    /*public function supprimerUnUtilisateur($pseudo) {
        $unUtilisateur = $this->getUnUtilisateur($pseudo);
        if ($unUtilisateur == null) {
            return false;
        }
        else {
            $idUtilisateur = $unUtilisateur->getId();
            
            // suppression des traces de l'utilisateur (et des points correspondants)
            $lesTraces = $this->getLesTraces($idUtilisateur);
            foreach ($lesTraces as $uneTrace) {
                $this->supprimerUneTrace($uneTrace->getId());
            }
            
            // prÃ©paration de la requÃªte de suppression des autorisations
            $txt_req1 = "delete from tracegps_autorisations" ;
            $txt_req1 .= " where idAutorisant = :idUtilisateur or idAutorise = :idUtilisateur";
            $req1 = $this->cnx->prepare($txt_req1);
            // liaison de la requÃªte et de ses paramÃ¨tres
            $req1->bindValue("idUtilisateur", utf8_decode($idUtilisateur), PDO::PARAM_INT);
            // exÃ©cution de la requÃªte
            $ok = $req1->execute();
            
            // prÃ©paration de la requÃªte de suppression de l'utilisateur
            $txt_req2 = "delete from tracegps_utilisateurs" ;
            $txt_req2 .= " where pseudo = :pseudo";
            $req2 = $this->cnx->prepare($txt_req2);
            // liaison de la requÃªte et de ses paramÃ¨tres
            $req2->bindValue("pseudo", utf8_decode($pseudo), PDO::PARAM_STR);
            // exÃ©cution de la requÃªte
            $ok = $req2->execute();
            return $ok;
        }
    }*/


    // envoie un mail Ã  l'utilisateur $pseudo avec son nouveau mot de passe $nouveauMdp
    // retourne true si envoi correct, false en cas de problÃ¨me d'envoi
    // modifiÃ© par Jim le 9/1/2018
    public function envoyerMdp($pseudo, $nouveauMdp)
    {
        global $ADR_MAIL_EMETTEUR;
        // si le pseudo n'est pas dans la table tracegps_utilisateurs :
        if ($this->existePseudoUtilisateur($pseudo) == false) return false;

        // recherche de l'adresse mail
        $adrMail = $this->getUnUtilisateur($pseudo)->getAdrMail();

        // envoie un mail Ã  l'utilisateur avec son nouveau mot de passe
        $sujet = "Modification de votre mot de passe d'accÃ¨s au service TraceGPS";
        $message = "Cher(chÃ¨re) " . $pseudo . "\n\n";
        $message .= "Votre mot de passe d'accÃ¨s au service service TraceGPS a Ã©tÃ© modifiÃ©.\n\n";
        $message .= "Votre nouveau mot de passe est : " . $nouveauMdp;
        $ok = Outils::envoyerMail($adrMail, $sujet, $message, $ADR_MAIL_EMETTEUR);
        return $ok;
    }


    // Le code restant Ã  dÃ©velopper va Ãªtre rÃ©parti entre les membres de l'Ã©quipe de dÃ©veloppement.
    // Afin de limiter les conflits avec GitHub, il est dÃ©cidÃ© d'attribuer une zone de ce fichier Ã  chaque dÃ©veloppeur.
    // DÃ©veloppeur 1 : lignes 350 Ã  549
    // DÃ©veloppeur 2 : lignes 550 Ã  749
    // DÃ©veloppeur 3 : lignes 750 Ã  949
    // DÃ©veloppeur 4 : lignes 950 Ã  1150

    // Quelques conseils pour le travail collaboratif :
    // avant d'attaquer un cycle de dÃ©veloppement (dÃ©but de sÃ©ance, nouvelle mÃ©thode, ...), faites un Pull pour rÃ©cupÃ©rer 
    // la derniÃ¨re version du fichier.
    // AprÃ¨s avoir testÃ© et validÃ© une mÃ©thode, faites un commit et un push pour transmettre cette version aux autres dÃ©veloppeurs.


    // --------------------------------------------------------------------------------------
    // dÃ©but de la zone attribuÃ©e a JulouDu56800
    // --------------------------------------------------------------------------------------
    public function existeAdrMailUtilisateur($adrMail)
    {
        // prÃ©paration de la requÃªte de recherche
        $txt_req = "Select count(*) from tracegps_utilisateurs where adrMail = :adrMail";
        $req = $this->cnx->prepare($txt_req);
        // liaison de la requÃªte et de ses paramÃ¨tres
        $req->bindValue("adrMail", $adrMail, PDO::PARAM_STR);
        // exÃ©cution de la requÃªte
        $req->execute();
        $nbReponses = $req->fetchColumn(0);
        // libÃ¨re les ressources du jeu de donnÃ©es
        $req->closeCursor();

        // fourniture de la rÃ©ponse
        if ($nbReponses == 0) {
            return false;
        } else {
            return true;
        }
    }
    // Rôle : fournit la collection des points de la trace $idTrace 
    public function getLesPointsDeTrace($idTrace){
        // prÃ©paration de la requÃªte de recherche
        $txt_req = "Select idTrace, id, latitude, longitude, altitude, dateHeure, rythmeCardio";
        $txt_req .= " from tracegps_points";
        $txt_req .= " where idTrace = :idTrace";
        $req = $this->cnx->prepare($txt_req);
        // liaison de la requÃªte et de ses paramÃ¨tres
        $req->bindValue("idTrace", $idTrace, PDO::PARAM_STR);
        // extraction des donnÃ©es
        $req->execute();
        $uneLigne = $req->fetch(PDO::FETCH_OBJ);
        
        // construction d'une collection d'objets Utilisateur
        $lesPointsDeTrace = array();
        // tant qu'une ligne est trouvÃ©e :
        while ($uneLigne) {
            // crÃ©ation d'un objet Utilisateur
            $unIdTrace = utf8_encode($uneLigne->idTrace);
            $unID = utf8_encode($uneLigne->id);
            $uneLatitude = utf8_encode($uneLigne->latitude);
            $uneLongitude = utf8_encode($uneLigne->longitude);
            $uneAltitude = utf8_encode($uneLigne->altitude);
            $uneDateHeure = utf8_encode($uneLigne->dateHeure);
            $unRythmeCardio = utf8_encode($uneLigne->rythmeCardio);
            $unTempsCumule = 0;
            $uneDistanceCumulee = 0;
            $uneVitesse = 0;
            
            $unPointDeTrace = new PointDeTrace($unIdTrace, $unID, $uneLatitude, $uneLongitude, $uneAltitude, $uneDateHeure, $unRythmeCardio, $unTempsCumule, $uneDistanceCumulee, $uneVitesse);
            // ajout de l'utilisateur Ã  la collectio
            $lesPointsDeTrace[] = $unPointDeTrace;
            // extrait la ligne suivante
            $uneLigne = $req->fetch(PDO::FETCH_OBJ);
        }
        // libÃ¨re les ressources du jeu de donnÃ©es
        $req->closeCursor();
        // fourniture de la collection
        return $lesPointsDeTrace;
    }

    public function creerUnPointDeTrace($unPointDeTrace){
        $txt_req = "insert into tracegps_points (idTrace, id, latitude, longitude, altitude, dateHeure, rythmeCardio)";
        $txt_req .= " values (:idTrace,:id, :latitude, :longitude, :altitude, :dateHeure, :rythmeCardio) ";
        $req = $this->cnx->prepare($txt_req);
        $req->bindValue("idTrace", utf8_decode($unPointDeTrace->GetIdTrace()), PDO::PARAM_STR);
        $req->bindValue("id", utf8_decode(sha1($unPointDeTrace->GetId())), PDO::PARAM_STR);
        $req->bindValue("latitude", utf8_decode($unPointDeTrace->GetLatitude()), PDO::PARAM_STR);
        $req->bindValue("longitude", utf8_decode($unPointDeTrace->GetLongitude()), PDO::PARAM_STR);
        $req->bindValue("altitude", utf8_decode($unPointDeTrace->GetAltitude()), PDO::PARAM_STR);
        $req->bindValue("dateHeure", utf8_decode($unPointDeTrace->GetDateHeure()), PDO::PARAM_STR);
        $req->bindValue("rythmeCardio", utf8_decode($unPointDeTrace->GetRythmeCardio()), PDO::PARAM_STR);
        $ok = $req->execute();
        if (!$ok) {
            return false;
        } else return true;
    }

    // --------------------------------------------------------------------------------------
    // dÃ©but de la zone attribuÃ©e a XXXDarkAubinXXX
    // --------------------------------------------------------------------------------------


    public function getLesUtilisateursAutorisant($idAutorise)
    {
        // prÃ©paration de la requÃªte de recherche
        $txt_req = "Select id, pseudo, mdpSha1, adrMail, numTel, niveau, dateCreation, nbTraces, dateDerniereTrace";
        $txt_req .= " from tracegps_vue_utilisateurs INNER JOIN tracegps_autorisations ON tracegps_vue_utilisateurs.id = tracegps_autorisations.idAutorisant";
        $txt_req .= " where idAutorise = :idAutorise";
        $req = $this->cnx->prepare($txt_req);
        // liaison de la requÃªte et de ses paramÃ¨tres
        $req->bindValue("idAutorise", $idAutorise, PDO::PARAM_STR);
        // extraction des donnÃ©es
        $req->execute();
        $uneLigne = $req->fetch(PDO::FETCH_OBJ);
        // libÃ¨re les ressources du jeu de donnÃ©es

        $lesUtilisateurs = array();

        while ($uneLigne) {
            // crÃ©ation d'un objet Utilisateur
            $unId = utf8_encode($uneLigne->id);
            $unPseudo = utf8_encode($uneLigne->pseudo);
            $unMdpSha1 = utf8_encode($uneLigne->mdpSha1);
            $uneAdrMail = utf8_encode($uneLigne->adrMail);
            $unNumTel = utf8_encode($uneLigne->numTel);
            $unNiveau = utf8_encode($uneLigne->niveau);
            $uneDateCreation = utf8_encode($uneLigne->dateCreation);
            $unNbTraces = utf8_encode($uneLigne->nbTraces);
            $uneDateDerniereTrace = utf8_encode($uneLigne->dateDerniereTrace);

            $unUtilisateur = new Utilisateur($unId, $unPseudo, $unMdpSha1, $uneAdrMail, $unNumTel, $unNiveau, $uneDateCreation, $unNbTraces, $uneDateDerniereTrace);
            // ajout de l'utilisateur Ã  la collection
            $lesUtilisateurs[] = $unUtilisateur;
            // extrait la ligne suivante
            $uneLigne = $req->fetch(PDO::FETCH_OBJ);
        }

        // libÃ¨re les ressources du jeu de donnÃ©es
        $req->closeCursor();
        // fourniture de la collection
        return $lesUtilisateurs;
    }


    public function getLesUtilisateursAutorises($idAutorisant)
    {
        // prÃ©paration de la requÃªte de recherche
        $txt_req = "Select idAutorise, pseudo, mdpSha1, adrMail, numTel, niveau, dateCreation, nbTraces, dateDerniereTrace";
        $txt_req .= " from tracegps_vue_utilisateurs INNER JOIN tracegps_autorisations ON tracegps_vue_utilisateurs.id = tracegps_autorisations.idAutorisant";
        $txt_req .= " where idAutorisant = :idAutorisant";
        $req = $this->cnx->prepare($txt_req);
        // liaison de la requÃªte et de ses paramÃ¨tres
        $req->bindValue("idAutorisant", $idAutorisant, PDO::PARAM_STR);
        // extraction des donnÃ©es
        $req->execute();
        $uneLigne = $req->fetch(PDO::FETCH_OBJ);
        // libÃ¨re les ressources du jeu de donnÃ©es

        $lesUtilisateurs = array();

        while ($uneLigne) {
            // crÃ©ation d'un objet Utilisateur
            $unId = utf8_encode($uneLigne->idAutorise);
            $unPseudo = utf8_encode($uneLigne->pseudo);
            $unMdpSha1 = utf8_encode($uneLigne->mdpSha1);
            $uneAdrMail = utf8_encode($uneLigne->adrMail);
            $unNumTel = utf8_encode($uneLigne->numTel);
            $unNiveau = utf8_encode($uneLigne->niveau);
            $uneDateCreation = utf8_encode($uneLigne->dateCreation);
            $unNbTraces = utf8_encode($uneLigne->nbTraces);
            $uneDateDerniereTrace = utf8_encode($uneLigne->dateDerniereTrace);

            $unUtilisateur = new Utilisateur($unId, $unPseudo, $unMdpSha1, $uneAdrMail, $unNumTel, $unNiveau, $uneDateCreation, $unNbTraces, $uneDateDerniereTrace);
            // ajout de l'utilisateur Ã  la collection
            $lesUtilisateurs[] = $unUtilisateur;
            // extrait la ligne suivante
            $uneLigne = $req->fetch(PDO::FETCH_OBJ);
        }

        // libÃ¨re les ressources du jeu de donnÃ©es
        $req->closeCursor();
        // fourniture de la collection
        return $lesUtilisateurs;
    }


    public function autoriseAConsulter($idAutorisant, $idAutorise)
    {
        $txt_req = "Select *";
        $txt_req .= " from tracegps_autorisations";
        $txt_req .= " where idAutorisant = :idAutorisant AND idAutorise =:idAutorise";
        $req = $this->cnx->prepare($txt_req);
        // liaison de la requÃªte et de ses paramÃ¨tres
        $req->bindValue("idAutorisant", $idAutorisant, PDO::PARAM_STR);
        $req->bindValue("idAutorise", $idAutorise, PDO::PARAM_STR);
        // extraction des donnÃ©es
        $req->execute();
        $uneLigne = $req->fetch(PDO::FETCH_OBJ);
        // libÃ¨re les ressources du jeu de donnÃ©es
        $req->closeCursor();

        // traitement de la rÃ©ponse
        if ($uneLigne) {
            return true;
        }
        return false;
    }

    public function creerUneAutorisation($idAutorisant, $idAutorise)
    {

        $txt_req = "INSERT INTO tracegps_autorisations VALUES (:idAutorisant,:idAutorise)";
        $req = $this->cnx->prepare($txt_req);
        $req->bindValue("idAutorisant", $idAutorisant, PDO::PARAM_STR);
        $req->bindValue("idAutorise", $idAutorise, PDO::PARAM_STR);
        $ok = $req->execute();
        if (!$ok) {
            return false;
        } else return true;

    }

    public function supprimerUneAutorisation($idAutorisant, $idAutorise)
    {
        $txt_req = "DELETE FROM  tracegps_autorisations where idAutorisant = :idAutorisant AND idAutorise =:idAutorise";
        $req = $this->cnx->prepare($txt_req);
        // liaison de la requÃªte et de ses paramÃ¨tresd
        $req->bindValue("idAutorisant", $idAutorisant, PDO::PARAM_STR);
        $req->bindValue("idAutorise", $idAutorise, PDO::PARAM_STR);
        $ok = $req->execute();
        if (!$ok) {
            return false;
        } else return true;
    }

    public function getUneTrace($idTrace)
    {
        $txt_req = "Select id, dateDebut, DateFin, terminee, idUtilisateur";
        $txt_req .= " from tracegps_traces";
        $txt_req .= " where id = :id";

        $req = $this->cnx->prepare($txt_req);
        // liaison de la requÃªte et de ses paramÃ¨tres
        $req->bindValue("id", $idTrace, PDO::PARAM_STR);
        // extraction des donnÃ©es
        $req->execute();
        $uneLigne = $req->fetch(PDO::FETCH_OBJ);
        // libÃ¨re les ressources du jeu de donnÃ©es
        $req->closeCursor();

        // traitement de la rÃ©ponse
        if (!$uneLigne) {
            return null;
        } else {
            $unId = utf8_encode($uneLigne->id);
            $uneDateDebut = utf8_encode($uneLigne->dateDebut);
            $uneDateFin = utf8_encode($uneLigne->DateFin);
            $terminee = utf8_encode($uneLigne->terminee);
            $idUtilisateur = utf8_encode($uneLigne->idUtilisateur);

            $uneTrace = new Trace($unId,$uneDateDebut,$uneDateFin,$terminee,$idUtilisateur);
            $lesPoint = $this->getLesPointsDeTrace($unId);

            foreach ($lesPoint as $lesNouveauxPoint ) {
                $uneTrace->ajouterPoint($lesNouveauxPoint);
            }

            return $uneTrace;
        }
    }

    public function getToutesLesTraces () {
        // préparation de la requête de recherche
        $txt_req = "Select id, dateDebut, DateFin, terminee, idUtilisateur";
        $txt_req .= " from tracegps_traces order by id";


        $req = $this->cnx->prepare($txt_req);
        // extraction des données
        $req->execute();
        $uneLigne = $req->fetch(PDO::FETCH_OBJ);

        // construction d'une collection d'objets Utilisateur
        $lesTraces = array();
        // tant qu'une ligne est trouvée :
        while ($uneLigne) {
            // création d'un objet Utilisateur
            $unId = utf8_encode($uneLigne->id);
            $uneDateDebut = utf8_encode($uneLigne->dateDebut);
            $uneDateFin = utf8_encode($uneLigne->DateFin);
            $terminee = utf8_encode($uneLigne->terminee);
            $idUtilisateur = utf8_encode($uneLigne->idUtilisateur);

            $uneTrace = new Trace($unId, $uneDateDebut, $uneDateFin, $terminee, $idUtilisateur);

            $lespoint = $this->getLesPointsDeTrace($unId);
            foreach ($lespoint as $leNouveauPoint){
                $uneTrace->ajouterPoint($leNouveauPoint);
            }

            // ajout de l'utilisateur à la collection
            $lesTraces[] = $uneTrace;
            // extrait la ligne suivante
            $uneLigne = $req->fetch(PDO::FETCH_OBJ);
        }
        // libère les ressources du jeu de données
        $req->closeCursor();
        // fourniture de la collection
        return $lesTraces;
    }


    public function getLesTraces($idUtilisateur)
    {
        // préparation de la requête de recherche
        $txt_req = "Select id, dateDebut, dateFin, terminee, idUtilisateur, pseudo ,nbPoints";
        $txt_req .= " from tracegps_vue_traces";
        $txt_req .= " where idUtilisateur = :idUtilisateur";


        $req = $this->cnx->prepare($txt_req);
        $req->bindValue(":idUtilisateur", $idUtilisateur, PDO::PARAM_INT);
        // extraction des données
        $req->execute();
        $uneLigne = $req->fetch(PDO::FETCH_OBJ);

        // construction d'une collection d'objets Utilisateur
        $lesTraces = array();
        $lesPoints = array();
        // tant qu'une ligne est trouvée :
        while ($uneLigne) {
            // création d'un objet Utilisateur
            $unId = utf8_encode($uneLigne->id);
            $uneDateDebut = utf8_encode($uneLigne->dateDebut);
            $uneDateFin = utf8_encode($uneLigne->dateFin);
            $terminee = utf8_encode($uneLigne->terminee);
            $idUtilisateur = utf8_encode($uneLigne->idUtilisateur);
            $pseudo = utf8_encode($uneLigne->pseudo);
            $nbPoints = utf8_encode($uneLigne->nbPoints);

            $uneTrace =  new Trace($unId, $uneDateDebut, $uneDateFin, $terminee, $idUtilisateur,$pseudo, $nbPoints);

            $lesPoints = $this->getLesPointsDeTrace($unId);
            foreach($lesPoints as $leNouveauPoint){
                $uneTrace->ajouterPoint($leNouveauPoint);
            }
            // ajout de l'utilisateur à la collection
            $lesTraces[] = $uneTrace;
            // extrait la ligne suivante
            $uneLigne = $req->fetch(PDO::FETCH_OBJ);

        }
        // libère les ressources du jeu de données
        $req->closeCursor();
        // fourniture de la collection
        return $lesTraces;
    }


    public function getLesTracesAutorisees($idUtilisateur){

        $txt_req = "Select id, dateDebut, dateFin, terminee, idUtilisateur, pseudo ,nbPoints";
        $txt_req .= " from tracegps_vue_traces INNER JOIN tracegps_autorisations ON tracegps_vue_traces.id = tracegps_autorisations.idAutorise";
        $txt_req .= " where idAutorisant = :idTrace";
        $req = $this->cnx->prepare($txt_req);
        // liaison de la requête et de ses paramètres
        $req->bindValue("idTrace", $idUtilisateur, PDO::PARAM_INT);
        // extraction des données
        $req->execute();
        $uneLigne = $req->fetch(PDO::FETCH_OBJ);
        // libère les ressources du jeu de données

        $lesTraces = array();
        // tant qu'une ligne est trouvée :
        while ($uneLigne) {
            // création d'un objet Utilisateur
            $unId = utf8_encode($uneLigne->id);
            $uneDateDebut = utf8_encode($uneLigne->dateDebut);
            $uneDateFin = utf8_encode($uneLigne->dateFin);
            $terminee = utf8_encode($uneLigne->terminee);
            $idUtilisateur = utf8_encode($uneLigne->idUtilisateur);
            $pseudo = utf8_encode($uneLigne->pseudo);
            $nbPoints = utf8_encode($uneLigne->nbPoints);

            $uneTrace =  new Trace($unId, $uneDateDebut, $uneDateFin, $terminee, $idUtilisateur,$pseudo, $nbPoints);
            // ajout de l'utilisateur à la collection
            $lesTraces[] = $uneTrace;
            // extrait la ligne suivante
            $uneLigne = $req->fetch(PDO::FETCH_OBJ);
        }
        // libère les ressources du jeu de données
        $req->closeCursor();
        // fourniture de la collection
        return $lesTraces;


    }


    public function creerUneTrace($uneTrace ) {
        // on teste si l'utilisateur existe déjà

        // préparation de la requête
        $txt_req1 = "insert into tracegps_traces (dateDebut, dateFin, terminee, idUtilisateur)";
        $txt_req1 .= " values (:dateDebut, :dateFin, :terminee, :idUtilisateur)";
        $req1 = $this->cnx->prepare($txt_req1);
        // liaison de la requête et de ses paramètres
        $req1->bindValue("dateDebut", utf8_decode($uneTrace->getDateHeureDebut()), PDO::PARAM_STR);
        $req1->bindValue("dateFin", utf8_decode($uneTrace->getDateHeureFin()), PDO::PARAM_STR);
        $req1->bindValue("terminee", utf8_decode($uneTrace->getTerminee()), PDO::PARAM_INT);
        $req1->bindValue("idUtilisateur", utf8_decode($uneTrace->getIdUtilisateur()), PDO::PARAM_INT);
        // exécution de la requête
        $ok = $req1->execute();
        // sortir en cas d'échec
        if ( ! $ok) { return false; }

        // recherche de l'identifiant (auto_increment) qui a été attribué à la trace
        $unId = $this->cnx->lastInsertId();
        $uneTrace->setId($unId);
        return true;
    }


    public function supprimerUneTrace($idTrace) {
        $txt_req = "DELETE FROM tracegps_points where idTrace = :id;DELETE FROM tracegps_traces where id = :id";
        $req = $this->cnx->prepare($txt_req);
        // liaison de la requête et de ses paramètres
        $req->bindValue("id", $idTrace, PDO::PARAM_STR);
        $ok = $req->execute();
        if ( ! $ok) { return false; } else return true;
    }


    public function terminerUneTrace($idTrace){


        $uneTrace = $this->getUneTrace($idTrace);

        if(sizeof($uneTrace->getLesPointsDeTrace())==0) { $dateFin = date('Y-m-d H:i:s'); }

        else {

            $DernierPoint = $uneTrace->getLesPointsDeTrace()[$uneTrace->getNombrePoints()-1];
            $dateFin = $DernierPoint->getDateHeure();
        }


        $dateFin = $uneTrace->getDateheureFin();


        $txt_req = "update tracegps_traces set datefin = :datefin, terminee = 1";
        $txt_req .= " where id = :id";
        $req = $this->cnx->prepare($txt_req);
        $req->bindValue("id", $idTrace, PDO::PARAM_INT);
        $req->bindValue("datefin", $dateFin, PDO::PARAM_STR);
        $ok = $req->execute();
        return $ok;
    }
}






























































































































































