<?php
// Projet TraceGPS
// fichier : modele/Trace.class.php
include_once ('PointDeTrace.class.php');

class Trace
{

    // ------------------------------------------------------------------------------------------------------
    // ---------------------------------- Attributs privés de la classe -------------------------------------
    // ------------------------------------------------------------------------------------------------------
    private $id;

    // identifiant de la trace
    private $dateHeureDebut;

    // date et heure de début
    private $dateHeureFin;

    // date et heure de fin
    private $terminee;

    // true si la trace est terminée, false sinon
    private $idUtilisateur;

    // identifiant de l'utilisateur ayant créé la trace
    private $lesPointsDeTrace;

    // la collection (array) des objets PointDeTrace formant la trace

    // ------------------------------------------------------------------------------------------------------
    // ----------------------------------------- Constructeur -----------------------------------------------
    // ------------------------------------------------------------------------------------------------------
    public function __construct($unId, $uneDateHeureDebut, $uneDateHeureFin, $terminee, $unIdUtilisateur)
    {
        $this->id = $unId;
        $this->dateHeureDebut = $uneDateHeureDebut;
        $this->dateHeureFin = $uneDateHeureFin;
        $this->terminee = $terminee;
        $this->idUtilisateur = $unIdUtilisateur;
        $this->lesPointsDeTrace = array();
    }

    // ------------------------------------------------------------------------------------------------------
    // ---------------------------------------- Getters et Setters ------------------------------------------
    // ------------------------------------------------------------------------------------------------------
    public function getId()
    {
        return $this->id;
    }

    public function setId($unId)
    {
        $this->id = $unId;
    }

    public function getDateHeureDebut()
    {
        return $this->dateHeureDebut;
    }

    public function setDateHeureDebut($uneDateHeureDebut)
    {
        $this->dateHeureDebut = $uneDateHeureDebut;
    }

    public function getDateHeureFin()
    {
        return $this->dateHeureFin;
    }

    public function setDateHeureFin($uneDateHeureFin)
    {
        $this->dateHeureFin = $uneDateHeureFin;
    }

    public function getTerminee()
    {
        return $this->terminee;
    }

    public function setTerminee($terminee)
    {
        $this->terminee = $terminee;
    }

    public function getIdUtilisateur()
    {
        return $this->idUtilisateur;
    }

    public function setIdUtilisateur($unIdUtilisateur)
    {
        $this->idUtilisateur = $unIdUtilisateur;
    }

    public function getLesPointsDeTrace()
    {
        return $this->lesPointsDeTrace;
    }

    public function setLesPointsDeTrace($lesPointsDeTrace)
    {
        $this->lesPointsDeTrace = $lesPointsDeTrace;
    }

    // Fournit une chaine contenant toutes les données de l'objet
    public function toString()
    {
        $msg = "Id : " . $this->getId() . "<br>";
        $msg .= "Utilisateur : " . $this->getIdUtilisateur() . "<br>";
        if ($this->getDateHeureDebut() != null) {
            $msg .= "Heure de début : " . $this->getDateHeureDebut() . "<br>";
        }
        if ($this->getTerminee()) {
            $msg .= "Terminée : Oui  <br>";
        } else {
            $msg .= "Terminée : Non  <br>";
        }
        $msg .= "Nombre de points : " . $this->getNombrePoints() . "<br>";
        if ($this->getNombrePoints() > 0) {
            if ($this->getDateHeureFin() != null) {
                $msg .= "Heure de fin : " . $this->getDateHeureFin() . "<br>";
            }
            $msg .= "Durée en secondes : " . $this->getDureeEnSecondes() . "<br>";
            $msg .= "Durée totale : " . $this->getDureeTotale() . "<br>";
            $msg .= "Distance totale en Km : " . $this->getDistanceTotale() . "<br>";
            $msg .= "Dénivelé en m : " . $this->getDenivele() . "<br>";
            $msg .= "Dénivelé positif en m : " . $this->getDenivelePositif() . "<br>";
            $msg .= "Dénivelé négatif en m : " . $this->getDeniveleNegatif() . "<br>";
            $msg .= "Vitesse moyenne en Km/h : " . $this->getVitesseMoyenne() . "<br>";
            $msg .= "Centre du parcours : " . "<br>";
            $msg .= "   - Latitude : " . $this->getCentre()->getLatitude() . "<br>";
            $msg .= "   - Longitude : " . $this->getCentre()->getLongitude() . "<br>";
            $msg .= "   - Altitude : " . $this->getCentre()->getAltitude() . "<br>";
        }
        return $msg;
    }

    public function getNombrePoints()
    {
        if (sizeof($this->lesPointsDeTrace) == 0) {
            return 0;
        }
        $nbPts = sizeof($this->lesPointsDeTrace);
        return $nbPts;
    }

    public function getCentre()
    {
        if (sizeof($this->lesPointsDeTrace) == 0) {
            return null;
        }

        $premierPt = $this->lesPointsDeTrace[0];

        $latMin = $premierPt->getLatitude();
        $longMin = $premierPt->getLongitude();
        $latMax = $premierPt->getLatitude();
        $longMax = $premierPt->getLongitude();

        $nbPts = $this->getNombrePoints();

        for ($i = 0; $i < $nbPts; $i ++) {
            $lePoint = $this->lesPointsDeTrace[0];
            if ($lePoint->getLatitude() > $latMax)
                $latMax = $lePoint->getLatitude();
            if ($lePoint->getLatitude() < $latMin)
                $latMin = $lePoint->getLatitude();
            if ($lePoint->getLongitude() > $longMax)
                $longMax = $lePoint->getLongitude();
            if ($lePoint->getLongitude() < $longMin)
                $longMin = $lePoint->getLongitude();
        }

        $latMoy = ($latMin + $latMax) / 2;
        $longMoy = ($longMin + $longMax) / 2;
        $premierPt = $this->lesPointsDeTrace[0];
        $centre = new Point($latMoy, $longMoy, 0);
        return $centre;
    }

    public function getDenivele()
    {
        if (sizeof($this->lesPointsDeTrace) == 0) {
            return 0;
        }

        $premierPt = $this->lesPointsDeTrace[0];

        $altMin = $premierPt->getAltitude();
        $altMax = $premierPt->getAltitude();
        $nbPts = $this->getNombrePoints();

        for ($i = 0; $i < $nbPts; $i ++) {
            $lePoint = $this->lesPointsDeTrace[$i];
            if ($lePoint->getAltitude() > $altMax)
                $altMax = $lePoint->getAltitude();
            if ($lePoint->getAltitude() < $altMin)
                $altMin = $lePoint->getAltitude();
        }
        $altMoy = ($altMax - $altMin);
        return $altMoy;
    }

    public function getDureeEnSecondes()
    {
        $dureeSecondes = strtotime($this->getDateHeureFin()) - strtotime($this->getDateHeureDebut());
        return $dureeSecondes;
    }

    public function getDureeTotale()
    {
        if (sizeof($this->lesPointsDeTrace) == 0) {
            return "00:00:00";
        }
        $heures = 0;
        $minutes = 0;
        $secondes = 0;
        $reste = 0;
        $temps = $this->getDureeEnSecondes();

        $heures = $temps / 3600;
        $reste = $temps % 3600;
        $minutes = $reste / 60;
        $secondes = $reste % 60;

        return sprintf("%02d", $heures) . ":" . sprintf("%02d", $minutes) . ":" . sprintf("%02d", $secondes);
    }

    public function getDistanceTotale()
    {
        if (sizeof($this->lesPointsDeTrace) == 0) {
            return 0;
        }

        $nbPts = sizeof($this->lesPointsDeTrace);
        $distance = $this->lesPointsDeTrace[$nbPts - 1];
        $distanceCumulee = $distance->getDistanceCumulee();

        return $distanceCumulee;
    }

    public function getDenivelePositif()
    {
        if (sizeof($this->lesPointsDeTrace) == 0) {
            return 0;
        }

        $premierPt = $this->lesPointsDeTrace[0];

        $alt = $premierPt->getAltitude();
        $altPosi = $premierPt->getAltitude();
        $nbPts = $this->getNombrePoints();

        for ($i = 2; $i < $nbPts; $i ++) {

            $lePoint = $this->lesPointsDeTrace[$i];

            if ($alt < $lePoint->getAltitude()) {
                $altPosi += ($lePoint->getAltitude() - $alt);
            }
            $alt = $lePoint->getAltitude();
        }
        return $altPosi;
    }

    public function getDeniveleNegatif()
    {
        if (sizeof($this->lesPointsDeTrace) == 0) {
            return 0;
        }

        $premierPt = $this->lesPointsDeTrace[0];

        $alt = $premierPt->getAltitude();
        $altPosi = $premierPt->getAltitude();
        $nbPts = $this->getNombrePoints();

        for ($i = 2; $i < $nbPts; $i ++) {
            $lePoint = $this->lesPointsDeTrace[$i];
            if ($alt > $lePoint->getAltitude()) {
                $altPosi += ($alt - $lePoint->getAltitude());
            }
            $alt = $lePoint->getAltitude();
        }
        return $altPosi;
    }

    public function getVitesseMoyenne()
    {
        if (sizeof($this->lesPointsDeTrace) == 0) {
            return 0;
        }

        $heure = $this->getDureeEnSecondes() / 3600;
        $vitesseMoy = $this->getDistanceTotale() / $heure;
        return $vitesseMoy;
    }

    public function ajouterPoint($unPoint)
    {
        if (sizeof($this->lesPointsDeTrace) == 0) {
            $unPoint->setVitesse(0);
            $unPoint->setTempsCumule(0);
            $unPoint->setDistanceCumulee(0);
        } else {
            $nbPts = sizeof($this->lesPointsDeTrace);
            $leDernierPoint = $this->lesPointsDeTrace[$nbPts - 1];
            $dist = $unPoint->getDistance($unPoint, $leDernierPoint);
            $distTotale = $dist + $leDernierPoint->getDistanceCumulee();

            if (strtotime($unPoint->getDateHeure()) > strtotime($leDernierPoint->getDateHeure())) {
                $temps = strtotime($unPoint->getDateHeure()) - strtotime($leDernierPoint->getDateHeure());
            } else
                $temps = 1;
            // a voir
            $tempsCumule = $temps + $leDernierPoint->getTempsCumule();

            $vitesse = $dist / ($temps / 3600);

            $unPoint->setVitesse($vitesse);
            $unPoint->setTempsCumule($tempsCumule);
            $unPoint->setDistanceCumulee($distTotale);
        }
        $this->lesPointsDeTrace[] = $unPoint;
    }

    public function viderListePoints()
    {
        $this->lesPointsDeTrace . Clear();
    }
} // fin de la classe Trace
// ATTENTION : on ne met pas de balise de fin de script pour ne pas prendre le risque
// d'enregistrer d'espaces après la balise de fin de script !!!!!!!!!!!!
