<?php
// Projet TraceGPS
// fichier : modele/DAO.class.php   (DAO : Data Access Object)
// Rôle : fournit des méthodes d'accès à la bdd tracegps (projet TraceGPS) au moyen de l'objet PDO
// modifié par Jim le 12/8/2018

// liste des méthodes déjà développées (dans l'ordre d'apparition dans le fichier) :

// __construct() : le constructeur crée la connexion $cnx à la base de données
// __destruct() : le destructeur ferme la connexion $cnx à la base de données
// getNiveauConnexion($login, $mdp) : fournit le niveau (0, 1 ou 2) d'un utilisateur identifié par $login et $mdp
// existePseudoUtilisateur($pseudo) : fournit true si le pseudo $pseudo existe dans la table tracegps_utilisateurs, false sinon
// getUnUtilisateur($login) : fournit un objet Utilisateur à partir de $login (son pseudo ou son adresse mail)
// getTousLesUtilisateurs() : fournit la collection de tous les utilisateurs (de niveau 1)
// creerUnUtilisateur($unUtilisateur) : enregistre l'utilisateur $unUtilisateur dans la bdd
// modifierMdpUtilisateur($login, $nouveauMdp) : enregistre le nouveau mot de passe $nouveauMdp de l'utilisateur $login daprès l'avoir hashé en SHA1
// supprimerUnUtilisateur($login) : supprime l'utilisateur $login (son pseudo ou son adresse mail) dans la bdd, ainsi que ses traces et ses autorisations
// envoyerMdp($login, $nouveauMdp) : envoie un mail à l'utilisateur $login avec son nouveau mot de passe $nouveauMdp

// liste des méthodes restant à développer :

// existeAdrMailUtilisateur($adrmail) : fournit true si l'adresse mail $adrMail existe dans la table tracegps_utilisateurs, false sinon
// getLesUtilisateursAutorises($idUtilisateur) : fournit la collection  des utilisateurs (de niveau 1) autorisés à suivre l'utilisateur $idUtilisateur
// getLesUtilisateursAutorisant($idUtilisateur) : fournit la collection  des utilisateurs (de niveau 1) autorisant l'utilisateur $idUtilisateur à voir leurs parcours
// autoriseAConsulter($idAutorisant, $idAutorise) : vérifie que l'utilisateur $idAutorisant) autorise l'utilisateur $idAutorise à consulter ses traces
// creerUneAutorisation($idAutorisant, $idAutorise) : enregistre l'autorisation ($idAutorisant, $idAutorise) dans la bdd
// supprimerUneAutorisation($idAutorisant, $idAutorise) : supprime l'autorisation ($idAutorisant, $idAutorise) dans la bdd
// getLesPointsDeTrace($idTrace) : fournit la collection des points de la trace $idTrace
// getUneTrace($idTrace) : fournit un objet Trace à partir de identifiant $idTrace
// getToutesLesTraces() : fournit la collection de toutes les traces
// getLesTraces($idUtilisateur) : fournit la collection des traces de l'utilisateur $idUtilisateur
// getLesTracesAutorisees($idUtilisateur) : fournit la collection des traces que l'utilisateur $idUtilisateur a le droit de consulter
// creerUneTrace(Trace $uneTrace) : enregistre la trace $uneTrace dans la bdd
// terminerUneTrace($idTrace) : enregistre la fin de la trace d'identifiant $idTrace dans la bdd ainsi que la date de fin
// supprimerUneTrace($idTrace) : supprime la trace d'identifiant $idTrace dans la bdd, ainsi que tous ses points
// creerUnPointDeTrace(PointDeTrace $unPointDeTrace) : enregistre le point $unPointDeTrace dans la bdd


// certaines méthodes nécessitent les classes suivantes :
include_once ('Utilisateur.class.php');
include_once ('Trace.class.php');
include_once ('PointDeTrace.class.php');
include_once ('Point.class.php');
include_once ('Outils.class.php');

// inclusion des paramètres de l'application
include_once ('parametres.php');

// début de la classe DAO (Data Access Object)
class DAO
{
    // ------------------------------------------------------------------------------------------------------
    // ---------------------------------- Membres privés de la classe ---------------------------------------
    // ------------------------------------------------------------------------------------------------------

    private $cnx;				// la connexion à la base de données

    // ------------------------------------------------------------------------------------------------------
    // ---------------------------------- Constructeur et destructeur ---------------------------------------
    // ------------------------------------------------------------------------------------------------------
    public function __construct() {
        global $PARAM_HOTE, $PARAM_PORT, $PARAM_BDD, $PARAM_USER, $PARAM_PWD;
        try
        {	$this->cnx = new PDO ("mysql:host=" . $PARAM_HOTE . ";port=" . $PARAM_PORT . ";dbname=" . $PARAM_BDD,
            $PARAM_USER,
            $PARAM_PWD);
            return true;
        }
        catch (Exception $ex)
        {	echo ("Echec de la connexion a la base de donnees <br>");
            echo ("Erreur numero : " . $ex->getCode() . "<br />" . "Description : " . $ex->getMessage() . "<br>");
            echo ("PARAM_HOTE = " . $PARAM_HOTE);
            return false;
        }
    }

    public function __destruct() {
        // ferme la connexion à MySQL :
        unset($this->cnx);
    }

    // ------------------------------------------------------------------------------------------------------
    // -------------------------------------- Méthodes d'instances ------------------------------------------
    // ------------------------------------------------------------------------------------------------------

    // fournit le niveau (0, 1 ou 2) d'un utilisateur identifié par $pseudo et $mdpSha1
    // cette fonction renvoie un entier :
    //     0 : authentification incorrecte
    //     1 : authentification correcte d'un utilisateur (pratiquant ou personne autorisée)
    //     2 : authentification correcte d'un administrateur
    // modifié par Jim le 11/1/2018
    public function getNiveauConnexion($pseudo, $mdpSha1) {
        // préparation de la requête de recherche
        $txt_req = "Select niveau from tracegps_utilisateurs";
        $txt_req .= " where pseudo = :pseudo";
        $txt_req .= " and mdpSha1 = :mdpSha1";
        $req = $this->cnx->prepare($txt_req);
        // liaison de la requête et de ses paramètres
        $req->bindValue("pseudo", $pseudo, PDO::PARAM_STR);
        $req->bindValue("mdpSha1", $mdpSha1, PDO::PARAM_STR);
        // extraction des données
        $req->execute();
        $uneLigne = $req->fetch(PDO::FETCH_OBJ);
        // traitement de la réponse
        $reponse = 0;
        if ($uneLigne) {
            $reponse = $uneLigne->niveau;
        }
        // libère les ressources du jeu de données
        $req->closeCursor();
        // fourniture de la réponse
        return $reponse;
    }


    // fournit true si le pseudo $pseudo existe dans la table tracegps_utilisateurs, false sinon
    // modifié par Jim le 27/12/2017
    public function existePseudoUtilisateur($pseudo) {
        // préparation de la requête de recherche
        $txt_req = "Select count(*) from tracegps_utilisateurs where pseudo = :pseudo";
        $req = $this->cnx->prepare($txt_req);
        // liaison de la requête et de ses paramètres
        $req->bindValue("pseudo", $pseudo, PDO::PARAM_STR);
        // exécution de la requête
        $req->execute();
        $nbReponses = $req->fetchColumn(0);
        // libère les ressources du jeu de données
        $req->closeCursor();

        // fourniture de la réponse
        if ($nbReponses == 0) {
            return false;
        }
        else {
            return true;
        }
    }


    // fournit un objet Utilisateur à partir de son pseudo $pseudo
    // fournit la valeur null si le pseudo n'existe pas
    // modifié par Jim le 9/1/2018
    public function getUnUtilisateur($pseudo) {
        // préparation de la requête de recherche
        $txt_req = "Select id, pseudo, mdpSha1, adrMail, numTel, niveau, dateCreation, nbTraces, dateDerniereTrace";
        $txt_req .= " from tracegps_vue_utilisateurs";
        $txt_req .= " where pseudo = :pseudo";
        $req = $this->cnx->prepare($txt_req);
        // liaison de la requête et de ses paramètres
        $req->bindValue("pseudo", $pseudo, PDO::PARAM_STR);
        // extraction des données
        $req->execute();
        $uneLigne = $req->fetch(PDO::FETCH_OBJ);
        // libère les ressources du jeu de données
        $req->closeCursor();

        // traitement de la réponse
        if ( ! $uneLigne) {
            return null;
        }
        else {
            // création d'un objet Utilisateur
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
    // le résultat est fourni sous forme d'une collection d'objets Utilisateur
    // modifié par Jim le 27/12/2017
    public function getTousLesUtilisateurs() {
        // préparation de la requête de recherche
        $txt_req = "Select id, pseudo, mdpSha1, adrMail, numTel, niveau, dateCreation, nbTraces, dateDerniereTrace";
        $txt_req .= " from tracegps_vue_utilisateurs";
        $txt_req .= " where niveau = 1";
        $txt_req .= " order by pseudo";

        $req = $this->cnx->prepare($txt_req);
        // extraction des données
        $req->execute();
        $uneLigne = $req->fetch(PDO::FETCH_OBJ);

        // construction d'une collection d'objets Utilisateur
        $lesUtilisateurs = array();
        // tant qu'une ligne est trouvée :
        while ($uneLigne) {
            // création d'un objet Utilisateur
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
            // ajout de l'utilisateur à la collection
            $lesUtilisateurs[] = $unUtilisateur;
            // extrait la ligne suivante
            $uneLigne = $req->fetch(PDO::FETCH_OBJ);
        }
        // libère les ressources du jeu de données
        $req->closeCursor();
        // fourniture de la collection
        return $lesUtilisateurs;
    }


    // enregistre l'utilisateur $unUtilisateur dans la bdd
    // fournit true si l'enregistrement s'est bien effectué, false sinon
    // met à jour l'objet $unUtilisateur avec l'id (auto_increment) attribué par le SGBD
    // modifié par Jim le 9/1/2018
    public function creerUnUtilisateur($unUtilisateur) {
        // on teste si l'utilisateur existe déjà
        if ($this->existePseudoUtilisateur($unUtilisateur->getPseudo())) return false;

        // préparation de la requête
        $txt_req1 = "insert into tracegps_utilisateurs (pseudo, mdpSha1, adrMail, numTel, niveau, dateCreation)";
        $txt_req1 .= " values (:pseudo, :mdpSha1, :adrMail, :numTel, :niveau, :dateCreation)";
        $req1 = $this->cnx->prepare($txt_req1);
        // liaison de la requête et de ses paramètres
        $req1->bindValue("pseudo", utf8_decode($unUtilisateur->getPseudo()), PDO::PARAM_STR);
        $req1->bindValue("mdpSha1", utf8_decode(sha1($unUtilisateur->getMdpsha1())), PDO::PARAM_STR);
        $req1->bindValue("adrMail", utf8_decode($unUtilisateur->getAdrmail()), PDO::PARAM_STR);
        $req1->bindValue("numTel", utf8_decode($unUtilisateur->getNumTel()), PDO::PARAM_STR);
        $req1->bindValue("niveau", utf8_decode($unUtilisateur->getNiveau()), PDO::PARAM_INT);
        $req1->bindValue("dateCreation", utf8_decode($unUtilisateur->getDateCreation()), PDO::PARAM_STR);
        // exécution de la requête
        $ok = $req1->execute();
        // sortir en cas d'échec
        if ( ! $ok) { return false; }

        // recherche de l'identifiant (auto_increment) qui a été attribué à la trace
        $unId = $this->cnx->lastInsertId();
        $unUtilisateur->setId($unId);
        return true;
    }


    // enregistre le nouveau mot de passe $nouveauMdp de l'utilisateur $pseudo daprès l'avoir hashé en SHA1
    // fournit true si la modification s'est bien effectuée, false sinon
    // modifié par Jim le 9/1/2018
    public function modifierMdpUtilisateur($pseudo, $nouveauMdp) {
        // préparation de la requête
        $txt_req = "update tracegps_utilisateurs set mdpSha1 = :nouveauMdp";
        $txt_req .= " where pseudo = :pseudo";
        $req = $this->cnx->prepare($txt_req);
        // liaison de la requête et de ses paramètres
        $req->bindValue("nouveauMdp", sha1($nouveauMdp), PDO::PARAM_STR);
        $req->bindValue("pseudo", $pseudo, PDO::PARAM_STR);
        // exécution de la requête
        $ok = $req->execute();
        return $ok;
    }


    // supprime l'utilisateur $pseudo dans la bdd, ainsi que ses traces et ses autorisations
    // fournit true si l'effacement s'est bien effectué, false sinon
    // modifié par Jim le 9/1/2018
    public function supprimerUnUtilisateur($pseudo) {
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

            // préparation de la requête de suppression des autorisations
            $txt_req1 = "delete from tracegps_autorisations" ;
            $txt_req1 .= " where idAutorisant = :idUtilisateur or idAutorise = :idUtilisateur";
            $req1 = $this->cnx->prepare($txt_req1);
            // liaison de la requête et de ses paramètres
            $req1->bindValue("idUtilisateur", utf8_decode($idUtilisateur), PDO::PARAM_INT);
            // exécution de la requête
            $ok = $req1->execute();

            // préparation de la requête de suppression de l'utilisateur
            $txt_req2 = "delete from tracegps_utilisateurs" ;
            $txt_req2 .= " where pseudo = :pseudo";
            $req2 = $this->cnx->prepare($txt_req2);
            // liaison de la requête et de ses paramètres
            $req2->bindValue("pseudo", utf8_decode($pseudo), PDO::PARAM_STR);
            // exécution de la requête
            $ok = $req2->execute();
            return $ok;
        }
    }


    // envoie un mail à l'utilisateur $pseudo avec son nouveau mot de passe $nouveauMdp
    // retourne true si envoi correct, false en cas de problème d'envoi
    // modifié par Jim le 9/1/2018
    public function envoyerMdp($pseudo, $nouveauMdp) {
        global $ADR_MAIL_EMETTEUR;
        // si le pseudo n'est pas dans la table tracegps_utilisateurs :
        if ( $this->existePseudoUtilisateur($pseudo) == false ) return false;

        // recherche de l'adresse mail
        $adrMail = $this->getUnUtilisateur($pseudo)->getAdrMail();

        // envoie un mail à l'utilisateur avec son nouveau mot de passe
        $sujet = "Modification de votre mot de passe d'accès au service TraceGPS";
        $message = "Cher(chère) " . $pseudo . "\n\n";
        $message .= "Votre mot de passe d'accès au service service TraceGPS a été modifié.\n\n";
        $message .= "Votre nouveau mot de passe est : " . $nouveauMdp ;
        $ok = Outils::envoyerMail ($adrMail, $sujet, $message, $ADR_MAIL_EMETTEUR);
        return $ok;
    }


    // Le code restant à développer va être réparti entre les membres de l'équipe de développement.
    // Afin de limiter les conflits avec GitHub, il est décidé d'attribuer une zone de ce fichier à chaque développeur.
    // Développeur 1 : lignes 350 à 549
    // Développeur 2 : lignes 550 à 749
    // Développeur 3 : lignes 750 à 949
    // Développeur 4 : lignes 950 à 1150

    // Quelques conseils pour le travail collaboratif :
    // avant d'attaquer un cycle de développement (début de séance, nouvelle méthode, ...), faites un Pull pour récupérer
    // la dernière version du fichier.
    // Après avoir testé et validé une méthode, faites un commit et un push pour transmettre cette version aux autres développeurs.





    // --------------------------------------------------------------------------------------
    // début de la zone attribuée au développeur 1 (Corentin) : lignes 350 à 549
    // --------------------------------------------------------------------------------------

    // Rôle : fournit la collection  des utilisateurs (de niveau 1)
    // autorisés à voir les parcours de l'utilisateur $idUtilisateur
    // Paramètres à fournir : $idUtilisateur : identifiant de l'utilisateur autorisant à consulter ses parcours
    // Valeur de retour : collection d'objets Utilisateur
    // la collection des utilisateurs qui sont autorisés à voir les parcours de l'utilisateur $idUtilisateur
    public function getLesUtilisateursAutorises($idUtilisateur)
    {
        // préparation de la requête de recherche
        $txt_req = "Select id, pseudo, mdpSha1, adrMail, numTel, niveau, dateCreation, nbTraces, dateDerniereTrace";
        $txt_req .= " from tracegps_vue_utilisateurs";
        $txt_req .= " where niveau = 1";
        $txt_req .= " AND id IN (SELECT idAutorise";
        $txt_req .= " from tracegps_autorisations";
        $txt_req .= " where idAutorisant = :idUtilisateur)";
        $txt_req .= " order by pseudo";


        $req = $this->cnx->prepare($txt_req);

        $req->bindValue("idUtilisateur", utf8_decode($idUtilisateur), PDO::PARAM_INT);

        // extraction des données
        $req->execute();
        $uneLigne = $req->fetch(PDO::FETCH_OBJ);

        // construction d'une collection d'objets Utilisateur
        $lesUtilisateurs = array();
        // tant qu'une ligne est trouvée :
        while ($uneLigne) {
            // création d'un objet Utilisateur
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
            // ajout de l'utilisateur à la collection
            $lesUtilisateurs[] = $unUtilisateur;
            // extrait la ligne suivante
            $uneLigne = $req->fetch(PDO::FETCH_OBJ);
        }
        // libère les ressources du jeu de données
        $req->closeCursor();
        // fourniture de la collection
        return $lesUtilisateurs;
    }

    // Rôle : supprime l'autorisation ($idAutorisant, $idAutorise) dans la table tracegps_autorisations
    // Paramètres à fournir :
    // $idAutorisant : l'id de l'utilisateur qui autorise
    // $idAutorise : l'id de l'utilisateur qui est autorisé
    // Valeur de retour : un booléen
    // true si la suppression s'est bien passé
    // false sinon
    public function supprimerUneAutorisation($idAutorisant, $idAutorise)
    {
        if ( ! DAO::autoriseAConsulter($idAutorisant, $idAutorise)) return false;
        // préparation de la requête de suppression des autorisations
        $txt_req1 = "delete from tracegps_autorisations" ;
        $txt_req1 .= " where idAutorisant = :idAutorisant and idAutorise = :idAutorise";
        $req1 = $this->cnx->prepare($txt_req1);
        // liaison de la requête et de ses paramètres
        $req1->bindValue("idAutorisant", utf8_decode($idAutorisant), PDO::PARAM_INT);
        $req1->bindValue("idAutorise", utf8_decode($idAutorise), PDO::PARAM_INT);
        // exécution de la requête
        $ok = $req1->execute();

        return $ok;
    }

    // Rôle : fournit un objet Trace à partir de son identifiant $idTrace
    // Paramètres à fournir :
    // $idTrace : l'identifiant de la trace
    // Valeur de retour : un objet
    // un objet de la classe Trace si $idTrace existe
    // l'objet null si $idTrace n'existe pas
    // Particularité : utiliser la méthode getLesPointsDeTrace($idTrace) pour obtenir les points de la trace
    // et les ajouter à l'objet Trace qui sera retourné
    public function getUneTrace($idTrace) {
        // préparation de la requête de recherche
        $txt_req = "Select *";
        $txt_req .= " from tracegps_traces";
        $txt_req .= " where id = :idTrace";
        $req = $this->cnx->prepare($txt_req);
        // liaison de la requête et de ses paramètres
        $req->bindValue("idTrace", utf8_decode($idTrace), PDO::PARAM_INT);
        // extraction des données
        $req->execute();
        $uneLigne = $req->fetch(PDO::FETCH_OBJ);
        // libère les ressources du jeu de données
        $req->closeCursor();

        // traitement de la réponse
        if ( ! $uneLigne) {
            return null;
        }
        else {
            // création d'un objet Utilisateur
            $unId = utf8_encode($uneLigne->id);
            $uneDateHeureDebut = utf8_encode($uneLigne->dateDebut);
            $uneDateHeureFin = utf8_encode($uneLigne->dateFin);
            $terminee = utf8_encode($uneLigne->terminee);
            $unIdUtilisateur = utf8_encode($uneLigne->idUtilisateur);

            $uneTrace = new Trace($unId, $uneDateHeureDebut, $uneDateHeureFin, $terminee, $unIdUtilisateur);

            $lesPDTs = DAO::getLesPointsDeTrace($idTrace);

            foreach ($lesPDTs as $unPointDeTrace)
            {
                $uneTrace->ajouterPoint($unPointDeTrace);
            }

            return $uneTrace;
        }
    }

    // Rôle : fournit la collection des traces que l'utilisateur $idUtilisateur a le droit de consulter
    // Paramètres à fournir :
    // $idUtilisateur : identifiant de l'utilisateur dont on veut obtenir les traces qu'il peut consulter
    // Valeur de retour : une collection d'objets Trace
    // Particularité : utiliser la méthode getLesPointsDeTrace($idTrace) pour obtenir les points de chaque trace
    // et les ajouter à chaque objet Trace qui sera ajouté à la collection
    public function getLesTracesAutorisees($idUtilisateur) {
        /*SELECT *
        FROM tracegps_traces
        where idUtilisateur IN (select idAutorisant
            from tracegps_autorisations
            where idAutorise = 2)
            */
        $txt_req = "Select *";
        $txt_req .= " from tracegps_traces";
        $txt_req .= " where idUtilisateur IN (select idAutorisant";
        $txt_req .= " from tracegps_autorisations";
        $txt_req .= " where idAutorise = :idUtilisateur)";

        $req = $this->cnx->prepare($txt_req);

        // liaison de la requête et de ses paramètres
        $req->bindValue("idUtilisateur", utf8_decode($idUtilisateur), PDO::PARAM_INT);
        // extraction des données
        $req->execute();
        $uneLigne = $req->fetch(PDO::FETCH_OBJ);

        $lesTraces = array();

        // tant qu'une ligne est trouvée :
        while ($uneLigne) {
            $lesTraces[] = DAO::getUneTrace($uneLigne->id);
            $uneLigne = $req->fetch(PDO::FETCH_OBJ);
        }

        // libère les ressources du jeu de données
        $req->closeCursor();
        // fourniture de la collection
        return $lesTraces;
    }

    // Rôle : enregistre la fin de la trace d'identifiant $idTrace dans la table tracegps_traces ainsi
    // que la date de fin
    // Paramètres à fournir :
    // $idTrace : l'identifiant de la trace à terminer
    // Valeur de retour : un booléen
    // true si la modification s'est bien passée
    // false sinon
    // Particularités :
    // - Le champ terminee doit être mis à 1
    // - Le champ dateFin doit prendre comme valeur la date du dernier point de la trace
    // (si la trace contient des points) ou la date système (si la trace ne contient aucun point)
    public function terminerUneTrace($idTrace) {
        if ( ! DAO::getUneTrace($idTrace)) return false;

        $uneTrace = DAO::getUneTrace($idTrace);

        $txt_req = "UPDATE tracegps_traces";
        $txt_req .= " SET terminee = 1,";

        if( sizeof($uneTrace->getLesPointsDeTrace()) != 0) {
            $dernierPoint = $uneTrace->getLesPointsDeTrace()[sizeof($uneTrace->getLesPointsDeTrace()) - 1];
            $fin = $dernierPoint->getDateHeure();

            $txt_req .= " dateFin = :fin";
            $txt_req .= " where id = :idTrace";

            $req = $this->cnx->prepare($txt_req);
            $req->bindValue("fin", $fin, PDO::PARAM_STR);
        }
        else {
            $txt_req .= " dateFin = NOW()";
            $txt_req .= " where id = :idTrace";
            $req = $this->cnx->prepare($txt_req);
        }

        // liaison de la requête et de ses paramètres
        $req->bindValue("idTrace", utf8_decode($idTrace), PDO::PARAM_INT);

        // éxécution requête
        $ok = $req->execute();

        return $ok;
    }


    // --------------------------------------------------------------------------------------
    // début de la zone attribuée au développeur 2 (Yvan) : lignes 550 à 749
    // --------------------------------------------------------------------------------------
    //Rôle : indique si $adrMail existe dans la table tracegps_utilisateurs
    //On fourni l'adresse à tester en paramètres
    //On renvoie un boléen : True si l'adresse existe, sinon false.
    public function existeAdrMailUtilisateur($adrMail) {
        // préparation de la requête de recherche
        $txt_req = "Select count(*) from tracegps_utilisateurs where adrMail = :adrMail";
        $req = $this->cnx->prepare($txt_req);
        // liaison de la requête et de ses paramètres
        $req->bindValue("adrMail", $adrMail, PDO::PARAM_STR);
        // exécution de la requête
        $req->execute();
        $nbReponses = $req->fetchColumn(0);
        // libère les ressources du jeu de données
        $req->closeCursor();

        // fourniture de la réponse
        if ($nbReponses == 0) {
            return false;
        }
        else {
            return true;
        }
    }


    //Rôle : indique si l'utilisateur $idAutorisant
    //autorise l'utilisateur $idAutorise à consulter ses traces
    //On fourni les ID des utilisateurs autorisant et autorisés
    //On renvoie un boléen : True si M.X autorise M.Y à consulter ses traces, sinon false.
    public function autoriseAConsulter($idAutorisant, $idAutorise){
        $txt_req = "Select count(*) from tracegps_autorisations where idAutorise = :idAutorise AND idAutorisant = :idAutorisant";
        $req = $this->cnx->prepare($txt_req);
        // liaison de la requête et de ses paramètres
        $req->bindValue("idAutorise", $idAutorise, PDO::PARAM_INT);
        $req->bindValue("idAutorisant", $idAutorisant, PDO::PARAM_INT);
        // exécution de la requête
        $req->execute();

        $nbReponses = $req->fetchColumn(0);
        // libère les ressources du jeu de données
        $req->closeCursor();

        // fourniture de la réponse
        if ($nbReponses == 0) {
            return false;
        }
        else {
            return true;
        }
    }


    // fournit la collection  de tous les utilisateurs (de niveau 1)
    // le résultat est fourni sous forme d'une collection d'objets Point de Trace
    public function getLesPointsDeTrace($idTrace) {
        // préparation de la requête de recherche
        $txt_req = "Select *";
        $txt_req .= " from tracegps_points";
        $txt_req .= " where idTrace = :idTrace";

        $req = $this->cnx->prepare($txt_req);

        $req->bindValue("idTrace", $idTrace, PDO::PARAM_INT);
        // extraction des données
        $req->execute();
        $uneLigne = $req->fetch(PDO::FETCH_OBJ);

        // construction d'une collection d'objets Point de Trace
        $lesPointsDeTrace = array();
        // tant qu'une ligne est trouvée :
        while ($uneLigne) {
            // création d'un objet Utilisateur
            $unIdTrace = utf8_encode($uneLigne->idTrace);
            $unId = utf8_encode($uneLigne->id);
            $uneLatitude = utf8_encode($uneLigne->latitude);
            $uneLongitude = utf8_encode($uneLigne->longitude);
            $uneAltitude = utf8_encode($uneLigne->altitude);
            $uneHeureDePassage = utf8_encode($uneLigne->dateHeure);
            $unRythmeCardiaque = utf8_encode($uneLigne->rythmeCardio);
            $unTempsCumulee = 0 ;
            $unTempsCumuleeEnChaine = 0 ;
            $uneDistanceCumulee = 0;
            $uneVitesse = 0;

            $unPointDeTrace = new PointDeTrace($unIdTrace, $unId, $uneLatitude, $uneLongitude, $uneAltitude, $uneHeureDePassage, $unRythmeCardiaque, $unTempsCumulee, $unTempsCumuleeEnChaine,$uneDistanceCumulee,$uneVitesse);
            // ajout de l'utilisateur à la collection
            $lesPointsDeTrace[] = $unPointDeTrace;
            // extrait la ligne suivante
            $uneLigne = $req->fetch(PDO::FETCH_OBJ);
        }
        // libère les ressources du jeu de données
        $req->closeCursor();
        // fourniture de la collection
        return $lesPointsDeTrace;
    }

    // fournit la collection  de tous les traces
    // le résultat est fourni sous forme d'une collection d'objets Trace
    public function getToutesLesTraces(){
        $txt_req = "Select *";
        $txt_req .= " from tracegps_traces";

        $req = $this->cnx->prepare($txt_req);

        // extraction des données
        $req->execute();
        $uneLigne = $req->fetch(PDO::FETCH_OBJ);

        // construction d'une collection d'objets Point de Trace
        $toutesLesTraces = array();
        $lesPointsDeTrace = array();
        // tant qu'une ligne est trouvée :

        while ($uneLigne) {
            $lesPointsDeTrace = $this->getLesPointsDeTrace($uneLigne->id);

            $unId = utf8_encode($uneLigne->id);
            $unIdUtilisateur = utf8_encode(($uneLigne->idUtilisateur));
            $uneHeureDebut = utf8_encode($uneLigne->dateDebut);
            if($uneLigne->terminee == 1)
            {   $terminee = true;
                $uneHeureFin = utf8_encode($uneLigne->dateFin);
            }
            if($uneLigne->terminee == 0)
            {   $terminee = false;
                $uneHeureFin = null;
            }

            $uneTrace = new Trace($unId, $uneHeureDebut, $uneHeureFin, $terminee, $unIdUtilisateur);

            foreach ($lesPointsDeTrace as $unPointDeTrace){
                $uneTrace->ajouterPoint($unPointDeTrace);
            }

            // ajout de l'utilisateur à la collection
            $toutesLesTraces[] = $uneTrace;
            // extrait la ligne suivante
            $uneLigne = $req->fetch(PDO::FETCH_OBJ);
        }
        // libère les ressources du jeu de données
        $req->closeCursor();
        // fourniture de la collection
        return $toutesLesTraces;
    }
    //Rôle : enregistre la trace $uneTrace dans la table tracegps_traces
    //et met à jour l'objet $uneTrace
    //avec l'identifiant (auto_increment) attribué par le SGBD
    //On renvoie un boléen : True si l'enrengistrement s'est bien passé, False s'il ya eu
    //un problème.
    //On fourni en paramètre la trace à enrengistrer.
    //- Si la date de fin est nulle (cas d'une trace non terminée), le champ dateFin prendra une valeur nulle (PDO::PARAM_NULL) ; sinon il prendra une valeur chaine (PDO::PARAM_STR).
    //- On n'enregistre pas les points de la trace, même si l'objet $uneTrace en contient.

    public function creerUneTrace($uneTrace) {


        // préparation de la requête
        $txt_req1 = "insert into tracegps_traces (dateDebut, dateFin, terminee, idUtilisateur)";
        $txt_req1 .= " values (:dateDebut, :dateFin, :terminee, :idUtilisateur)";
        $req1 = $this->cnx->prepare($txt_req1);
        // liaison de la requête et de ses paramètres
        $req1->bindValue(":dateDebut", utf8_encode($uneTrace->getDateHeureDebut()), PDO::PARAM_STR);

        if($uneTrace->getTerminee() == false)
        {
            $req1->bindValue("dateFin", null, PDO::PARAM_NULL);
            $req1->bindValue("terminee", 0 , PDO::PARAM_INT);

        }
        if($uneTrace->getTerminee() == true)
        {
            $req1->bindValue("dateFin", $uneTrace->getDateHeureFin(), PDO::PARAM_STR);
            $req1->bindValue("terminee", 1 , PDO::PARAM_INT);
        }


        $req1->bindValue("idUtilisateur", utf8_encode($uneTrace->getIdUtilisateur()), PDO::PARAM_INT);

        // exécution de la requête
        $ok = $req1->execute();


        //print_r($req1->errorInfo());


        // sortir en cas d'échec
        if ( ! $ok) return false;

        // recherche de l'identifiant (auto_increment) qui a été attribué à la trace
        $unId = $this->cnx->lastInsertId();
        $uneTrace->setId($unId);
        return true;
    }




    // --------------------------------------------------------------------------------------
    // début de la zone attribuée au développeur 3 (Guillaume) : lignes 750 à 949
    // --------------------------------------------------------------------------------------

    // Méthode getLesUtilisateursAutorisant prenant pour paramètre $idUtilisateur
    // Le rôle de cette fonction est de fournir la collection des utilisateurs (de niveau 1) autorisant l'utilisateur $idUtilisateur à voir leurs parcours
    // Le paramètre à fournir est le paramètre $idUtilisateur qui comprend l'identifiant de l'utilisateur autorisé à consulter des parcours
    // Cette méthode retourne la collection des utilisateurs qui ont donné l'autorisation à $idUtilisateur
    public function getLesUtilisateursAutorisant($idUtilisateur)
    {
        // préparation de la requête de recherche
        $txt_req = "Select id, pseudo, mdpSha1, adrMail, numTel, niveau, dateCreation, nbTraces, dateDerniereTrace";
        $txt_req .= " from tracegps_vue_utilisateurs";
        $txt_req .= " where niveau = 1";
        $txt_req .= " and id in (select idAutorisant";
        $txt_req .= " from tracegps_autorisations";
        $txt_req .= " where idAutorise = :idUtilisateur)";
        $txt_req .= " order by pseudo";

        $req = $this->cnx->prepare($txt_req);
        $req->bindValue("idUtilisateur", $idUtilisateur, PDO::PARAM_STR);
        // extraction des données
        $req->execute();
        $uneLigne = $req->fetch(PDO::FETCH_OBJ);

        // construction d'une collection d'objets Utilisateur
        $lesUtilisateurs = array();
        // tant qu'une ligne est trouvée :
        while ($uneLigne) {
            // création d'un objet Utilisateur
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
            // ajout de l'utilisateur à la collection
            $lesUtilisateurs[] = $unUtilisateur;
            // extrait la ligne suivante
            $uneLigne = $req->fetch(PDO::FETCH_OBJ);
        }
        // libère les ressources du jeu de données
        $req->closeCursor();
        // fourniture de la collection
        return $lesUtilisateurs;
    }

    // Méthode creeruneAutorisation($idAutorisant, $idAutorise)
    // Le rôle de cette fonction est d'enregistrer l'autorisation ($idAutorisant, $idAutorise) dans la table tracegps_autorisations.
    // Les paramètres à fournir sont $idAutorisant (l'id de l'utilisateur qui autorise) et $idAutorise (l'id de l'utilisateur qui est autorisé)
    // La fonction renvoie un booléen qui renvoie true si l'enregistrement s'est bien passé et false sinon
    public function creerUneAutorisation($idAutorisant, $idAutorise)
    {
        // préparation de la requête
        $txt_req1 = "insert into tracegps_autorisations (idAutorisant, idAutorise)";
        $txt_req1 .= " values (:idAutorisant, :idAutorise)";
        $req1 = $this->cnx->prepare($txt_req1);

        // liaison de la requête et de ses paramètres
        $req1->bindValue("idAutorisant", $idAutorisant, PDO::PARAM_INT);
        $req1->bindValue("idAutorise", $idAutorise, PDO::PARAM_INT);

        // exécution de la requête
        $ok = $req1->execute();

        // sortir en cas d'échec
        return $ok;
    }

    // Méthode creerUnPointDeTrace($unPointDeTrace)
    // Le rôle de cette méthode est d'enregistrer le point $unPointDeTrace dans la BDD
    // Le paramètre à fournir est $unPointDeTrace qui correspond au point de trace à enregistrer
    // Cette méthode retourne un booléen qui renvoie :
    // True si l'enregistrement est bien passé
    // False sinon
    // La particularité de cette méthode est que si le point enregistré est le premier point d'une trace ($id = 1), il faut modifier la date de début de trace en lui affectant la date du point
    public function creerUnPointDeTrace($unPointDeTrace) {


        // préparation de la requête
        $txt_req1 = "insert into tracegps_points (idTrace, id, latitude, longitude, altitude, dateHeure, rythmeCardio)";
        $txt_req1 .= " values (:idTrace, :id, :latitude, :longitude, :altitude, :dateHeure, :rythmeCardio)";
        $req1 = $this->cnx->prepare($txt_req1);
        // liaison de la requête et de ses paramètres
        $req1->bindValue(":idTrace", utf8_encode($unPointDeTrace->getIdTrace()), PDO::PARAM_INT);
        $req1->bindValue(":id", utf8_encode($unPointDeTrace->getId()), PDO::PARAM_INT);
        $req1->bindValue(":latitude", utf8_encode($unPointDeTrace->getLatitude()), PDO::PARAM_STR);
        $req1->bindValue(":longitude", utf8_encode($unPointDeTrace->getLongitude()), PDO::PARAM_STR);
        $req1->bindValue(":altitude", utf8_encode($unPointDeTrace->getAltitude()), PDO::PARAM_STR);
        $req1->bindValue(":dateHeure", utf8_encode($unPointDeTrace->getDateHeure()), PDO::PARAM_STR);
        $req1->bindValue(":rythmeCardio", utf8_encode($unPointDeTrace->getRythmeCardio()), PDO::PARAM_INT);

        $laTrace = DAO::getUneTrace($unPointDeTrace->getIdTrace());

        if ( $unPointDeTrace->getId() == 1)
        {
            $laTrace->setDateHeureDebut($unPointDeTrace->getDateHeure());
        }
        // exécution de la requête
        $ok = $req1->execute();


        //print_r($req1->errorInfo());


        // sortir en cas d'échec
        if ( ! $ok) return false;

        // recherche de l'identifiant (auto_increment) qui a été attribué à la trace
        $unId = $this->cnx->lastInsertId();
        $unPointDeTrace->setId($unId);
        return true;
    }

    // Méthode getLesTrace($idUtilisateur)
    // Le rôle de cette méthode est de fournit la collection des traces de l'utilisateur $idUtilisateur
    // Le paramètre à fournir est $idUtilisateur qui correspond à l'identifiant de l'utilisateur dont on veut obtenir les traces
    // Cette méthode retourne une collection d'objets Trace
    // La particularité de cette méthode est qu'elle utilise la méthode getLesPointsDeTrace($idTrace) pour obtenir les points de chaque trace
    // et les ajouter à chaque objet Trace qui sera ajouté à la collection
    public function getLesTraces($idUtilisateur) {
        // préparation de la requête de recherche
        $txt_req = "Select *";
        $txt_req .= " from tracegps_traces";
        $txt_req .= " where idUtilisateur = :idUtilisateur";
        $req = $this->cnx->prepare($txt_req);
        // liaison de la requête et de ses paramètres
        $req->bindValue("idUtilisateur", utf8_decode($idUtilisateur), PDO::PARAM_INT);
        // extraction des données
        $req->execute();
        $uneLigne = $req->fetch(PDO::FETCH_OBJ);

        $lesTraces = array();

        // traitement de la réponse
        if ( ! $uneLigne) {
            return null;
        }
        else {
            while($uneLigne){
                // création d'un objet Utilisateur
                $unId = utf8_encode($uneLigne->id);
                $uneDateHeureDebut = utf8_encode($uneLigne->dateDebut);
                $uneDateHeureFin = utf8_encode($uneLigne->dateFin);
                $terminee = utf8_encode($uneLigne->terminee);
                $unIdUtilisateur = utf8_encode($uneLigne->idUtilisateur);

                $uneTrace = new Trace($unId, $uneDateHeureDebut, $uneDateHeureFin, $terminee, $unIdUtilisateur);

                $lesPDTs = DAO::getLesPointsDeTrace($unId);

                foreach ($lesPDTs as $unPointDeTrace)
                {
                    $uneTrace->ajouterPoint($unPointDeTrace);
                }

                $lesTraces[] = $uneTrace;
                // extrait la ligne suivante
                $uneLigne = $req->fetch(PDO::FETCH_OBJ);
            }

            // libère les ressources du jeu de données
            $req->closeCursor();

            return $lesTraces;
        }
    }


    // Méthode supprimerUneTrace($idTrace)
    // Le rôle de cette méthode est de supprimer la trace de l'identifiant idTrace dans la table tracegps_traces
    // ainsi que tous ses points dans la table tracegps_points
    // Le paramètre à fournir est $idTrace qui correspond à l'identifiant de la trace à supprimer
    // Cette méthode retourne un booléen qui renvoie true si la suppression s'est bien passée et false sinon
    public function supprimerUneTrace($idTrace) {
        $uneTrace = $this->getUneTrace($idTrace);
        if ($uneTrace == null) {
            return false;
        }
        else {
            $idTrace = $uneTrace->getId();

            // préparation de la requête de suppression des points
            $txt_req1 = "delete from tracegps_points" ;
            $txt_req1 .= " where idTrace = :idTrace";
            $req1 = $this->cnx->prepare($txt_req1);
            // liaison de la requête et de ses paramètres
            $req1->bindValue("idTrace", utf8_decode($idTrace), PDO::PARAM_INT);
            // exécution de la requête
            $ok = $req1->execute();

            // préparation de la requête de suppression de l'utilisateur
            $txt_req2 = "delete from tracegps_traces" ;
            $txt_req2 .= " where id = :idTrace";
            $req2 = $this->cnx->prepare($txt_req2);
            // liaison de la requête et de ses paramètres
            $req2->bindValue("idTrace", utf8_decode($idTrace), PDO::PARAM_STR);
            // exécution de la requête
            $ok = $req2->execute();
            return $ok;
        }
    }

} // fin de la classe DAO

// ATTENTION : on ne met pas de balise de fin de script pour ne pas prendre le risque
// d'enregistrer d'espaces après la balise de fin de script !!!!!!!!!!!!
