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
$pseudoDestinataire = ( empty($this->request['pseudoDestinataire'])) ? "" : $this->request['pseudoDestinataire'];
$texteMessage = ( empty($this->request['texteMessage'])) ? "" : $this->request['texteMessage'];
$nomPrenom = ( empty($this->request['nomPrenom'])) ? "" : $this->request['nomPrenom'];
$lang = ( empty($this->request['lang'])) ? "" : $this->request['lang'];

if ($lang != "json") $lang = "xml";

// La méthode HTTP utilisée doit être GET
if ($this->getMethodeRequete() != "GET")
{	$msg = "Erreur : méthode HTTP incorrecte.";
    $code_reponse = 406;
}
else {
    // Les paramètres doivent être présents et corrects
    if ( $pseudo == "" || $mdpSha1 == "" || $pseudoDestinataire == "" || $texteMessage == "" || $nomPrenom == "")
    {	$msg = "Erreur : données incomplètes.";
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
            if (!$dao->existePseudoUtilisateur($pseudo))
            {
                $msg="Erreur : pseudo inexistant.";
                $code_reponse = 500;
            }

            else
            {
                $unDestinataire = $dao->getUnUtilisateur($pseudoDestinataire);
                if ($unDestinataire == null)
                {
                    $msg="Erreur : pseudo utilisateur inexistant.";
                    $code_reponse=500;
                }
                else
                {

                    $mdpDesti = $unDestinataire->getMdpSha1();

                    $unUtilisateur = $dao->getUnUtilisateur($pseudo);
                    $msgMail ="Cher ou chère ".$pseudoDestinataire."\n\n"."Un utilisateur du système TraceGPS vous demande l'autorisation de suivre vos parcours"."\n\n"."Voici les données";
                    $msgMail .= " le concernant :"."\n\n"."Son Pseudo : ".$pseudo."\n\n"."Son adresse mail : ".$unUtilisateur->getAdrMail()."\n\n";
                    $msgMail .= "Son numéro de téléphone : ".$unUtilisateur->getNumTel()."\n\n";
                    $msgMail .= "Son Nom et Prenom : ".$nomPrenom."\n\n"."Son message : ".$texteMessage."\n\n";
                    $msgMail .= "Pour accepter la demande, cliquez sur ce lien : ";
                    $msgMail .= "http://localhost/ws-php-romain2/tracegps/api/ValiderDemandeAutorisation?a=".$mdpDesti."&b=".$pseudoDestinataire."&c=".$pseudo."&d=1"."\n\n";
                    $msgMail .= "Pour rejeter la demande, cliquez sur ce lien : ";
                    $msgMail .= "http://localhost/ws-php-romain2/tracegps/api/ValiderDemandeAutorisation?a=".$mdpDesti."&b=".$pseudoDestinataire."&c=".$pseudo."&d=0";




                    $adresseDestinataire = $unDestinataire->getAdrMail();
                    $sujet = "Demande d'autorisation de la part d'un utilisateur du système TraceGps";
                    $adresseEmetteur = $unUtilisateur->getAdrMail();
                    $ok = Outils::envoyerMail($adresseDestinataire, $sujet, $msgMail, $adresseEmetteur);

                    if(! $ok)
                    {
                        $msg="Erreur : l'envoi du courriel de demande d'autorisation a rencontré un problème.";
                        $code_reponse=500;
                    }
                    else
                    {
                        $msg= $pseudoDestinataire." va recevoir un courriel avec votre demande.";
                        $code_reponse=200;
                    }
                }
            }
        }
    }
}
unset($dao);   // ferme la connexion à MySQL

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
    $elt_commentaire = $doc->createComment('Service web DemanderUneAutorisation - BTS SIO - Lycée De La Salle - Rennes');
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
?>