<?php
// •	pseudo : le pseudo de l'utilisateur qui demande l'autorisation
// •	mdp : le mot de passe hashé en sha1 de l'utilisateur qui demande l'autorisation
// •	pseudoDestinataire : le pseudo de l'utilisateur à qui on demande l'autorisation
// •	texteMessage : le texte d'un message accompagnant la demande
// •	nomPrenom : le nom et le prénom du demandeur
// •	lang : le langage utilisé pour le flux de données ("xml" ou "json")

// ces variables globales sont définies dans le fichier modele/parametres.php
global $ADR_MAIL_EMETTEUR, $ADR_SERVICE_WEB;

// connexion du serveur web à la base MySQL
$dao = new DAO();

// Récupération des données transmises
$pseudo = ( empty($this->request['pseudo'])) ? "" : $this->request['pseudo'];
$mdpSha1 = ( empty($this->request['mdp'])) ? "" : $this->request['mdp'];
$pseudoAretirer = ( empty($this->request['pseudoARetirer'])) ? "" : $this->request['pseudoARetirer'];
$texteMessage = ( empty($this->request['texteMessage'])) ? "" : $this->request['texteMessage'];
$lang = ( empty($this->request['lang'])) ? "" : $this->request['lang'];

if ($lang != "json") $lang = "xml";

// La méthode HTTP utilisée doit être GET
if ($this->getMethodeRequete() != "GET")
{	$msg = "Erreur : méthode HTTP incorrecte.";
    $code_reponse = 406;
}
else {
    // Les paramètres doivent être présents et corrects
    if ( $pseudo == "" || $mdpSha1 == "" || $pseudoAretirer == "" || $texteMessage == "")
    {	
        $msg = "Erreur : données incomplètes.";
        $code_reponse = 400;
    }
    else
    {
        if ( $dao->getNiveauConnexion($pseudo, $mdpSha1) == 0 ) {
            $msg = "Erreur : authentification incorrecte.";
            $code_reponse = 401;
        }
        
        else
        {
            if ( ! $dao->existePseudoUtilisateur($pseudo))
            {
                $msg="Erreur : pseudo inexistant.";
                $code_reponse = 500;
            }
            
            else
            {
                $unDestinataire = $dao->getUnUtilisateur($pseudoAretirer);
                $unUtilisateur = $dao->getUnUtilisateur($pseudo);
                if ($unDestinataire == null)
                {
                    $msg="Erreur : pseudo utilisateur inexistant.";
                    $code_reponse=500;
                }
                else
                {
                    $idAutorisant = $dao->getUnUtilisateur($pseudo)->getId();
                    $idAutorise = $dao->getUnUtilisateur($pseudoAretirer)->getId();
                    $ok = $dao->autoriseAConsulter($idAutorisant, $idAutorise);
                    
                    if ( ! $ok)
                    {
                        $msg = "Erreur : l'autorisation n'était pas accordée.";
                        $code_reponse= 500;
                    }
                    else 
                    {
                        $ok = $dao->supprimerUneAutorisation($idAutorisant, $idAutorise);
                        if ( ! $ok)
                        {
                            $msg = "Erreur : problème lors de la suppression de l'autorisation.";
                            $code_reponse = 500;
                        }
                        
                        $ADR_MAIL_EMETTEUR = $unUtilisateur->getAdrMail();
                        $adrMailDemandeur = $unDestinataire->getAdrMail();
                        
                        $sujetMail = "Supression d'autorisation de la part d'un utilisateur du système TraceGPS";
                        $contenuMail = "Cher ou chère " . $pseudoAretirer . "\n\n";
                        $contenuMail .= "L'utilisateur ".$pseudo." du système TraceGPS vous retire l'autorisation de suivre ses parcours"."\n\n";
                        $contenuMail .= "Son message : ".$texteMessage."\n\n";
                        $contenuMail .= "Cordialement.\n";
                        $contenuMail .= "L'administrateur du système TraceGPS";
                        $ok = Outils::envoyerMail($adrMailDemandeur, $sujetMail, $contenuMail, $ADR_MAIL_EMETTEUR);
                        if ( ! $ok ) {
                            $msg = "Erreur : l'envoi du courriel au demandeur a rencontré un problème.";
                            $code_reponse = 500;
                        }
                        else {
                            $msg = "Autorisation supprimée ; ".$pseudoAretirer." va recevoir un courriel de notification.";
                            $code_reponse = 200;
                        }
                    }
                }
            }
        }
    }
}
unset($dao);   // ferme la connexion à MySQL

// création du flux en sortie
if ($lang == "xml") {
    $content_type = "application/xml; charset=utf-8";      // indique le format XML pour la réponse
    $donnees = creerFluxXML($msg);
}
else {
    $content_type = "application/json; charset=utf-8";      // indique le format Json pour la réponse
    $donnees = creerFluxJSON($msg);
}

// envoi de la réponse HTTP
$this->envoyerReponse($code_reponse, $content_type, $donnees);

// fin du programme (pour ne pas enchainer sur les 2 fonctions qui suivent)
exit;

// ================================================================================================

// création du flux XML en sortie
function creerFluxXML($msg)
{	// crée une instance de DOMdocument (DOM : Document Object Model)
    $doc = new DOMDocument();
    
    // specifie la version et le type d'encodage
    $doc->version = '1.0';
    $doc->encoding = 'UTF-8';
    
    // crée un commentaire et l'encode en UTF-8
    $elt_commentaire = $doc->createComment('Service web RetirerUneAutorisation - BTS SIO - Lycée De La Salle - Rennes');
    // place ce commentaire à la racine du document XML
    $doc->appendChild($elt_commentaire);
    
    // crée l'élément 'data' à la racine du document XML
    $elt_data = $doc->createElement('data');
    $doc->appendChild($elt_data);
    
    // place l'élément 'reponse' dans l'élément 'data'
    $elt_reponse = $doc->createElement('reponse', $msg);
    $elt_data->appendChild($elt_reponse);
    
    // Mise en forme finale
    $doc->formatOutput = true;
    
    // renvoie le contenu XML
    return $doc->saveXML();
}

// ================================================================================================

// création du flux JSON en sortie
function creerFluxJSON($msg)
{
    /* Exemple de code JSON
     {
     "data": {
     "reponse": "Erreur : authentification incorrecte."
     }
     }
     */
    
    // construction de l'élément "data"
    $elt_data = ["reponse" => $msg];
    
    // construction de la racine
    $elt_racine = ["data" => $elt_data];
    
    // retourne le contenu JSON (l'option JSON_PRETTY_PRINT gère les sauts de ligne et l'indentation)
    return json_encode($elt_racine, JSON_PRETTY_PRINT);
}

// ================================================================================================
