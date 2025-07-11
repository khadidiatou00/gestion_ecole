-- Création de la base de données
CREATE DATABASE IF NOT EXISTS gestion_ecole CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE gestion_ecole;

-- Table utilisateurs (admin, enseignant, étudiant)
CREATE TABLE utilisateurs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(50) NOT NULL,
    prenom VARCHAR(50),
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'enseignant', 'etudiant') NOT NULL,
    telephone VARCHAR(20),
    adresse TEXT,
    sexe ENUM('M', 'F'),
    date_naissance DATE,
    photo_profil VARCHAR(255),
    specialite VARCHAR(100),
    grade VARCHAR(50),
    contrat ENUM('titulaire', 'contractuel', 'vacataire'),
    groupe_sanguin VARCHAR(3),
    statut TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP
);

-- Années scolaires
CREATE TABLE annees_scolaires (
    id INT AUTO_INCREMENT PRIMARY KEY,
    annee VARCHAR(20) NOT NULL,
    date_debut DATE,
    date_fin DATE,
    statut ENUM('planifie', 'en_cours', 'termine') DEFAULT 'planifie'
);

-- Cycles
CREATE TABLE cycles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100),
    code VARCHAR(20) UNIQUE,
    duree_annees INT DEFAULT 1
);

-- Classes
CREATE TABLE classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(50),
    niveau VARCHAR(50),
    cycle_id INT,
    annee_id INT,
    responsable_id INT,
    capacite_max INT DEFAULT 30,
    FOREIGN KEY (cycle_id) REFERENCES cycles(id),
    FOREIGN KEY (annee_id) REFERENCES annees_scolaires(id),
    FOREIGN KEY (responsable_id) REFERENCES utilisateurs(id)
);

-- Matières
CREATE TABLE matieres (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) UNIQUE,
    nom VARCHAR(100),
    coefficient INT DEFAULT 1,
    heures_annuelles INT DEFAULT 60,
    cycle_id INT,
    FOREIGN KEY (cycle_id) REFERENCES cycles(id)
);

-- Inscriptions
CREATE TABLE inscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    etudiant_id INT,
    classe_id INT,
    annee_id INT,
    date_inscription DATE,
    statut ENUM('actif', 'suspendu', 'desinscrit') DEFAULT 'actif',
    FOREIGN KEY (etudiant_id) REFERENCES utilisateurs(id),
    FOREIGN KEY (classe_id) REFERENCES classes(id),
    FOREIGN KEY (annee_id) REFERENCES annees_scolaires(id)
);

-- Affectation enseignants/matières
CREATE TABLE enseignant_matieres (
    id INT AUTO_INCREMENT PRIMARY KEY,
    enseignant_id INT,
    matiere_id INT,
    classe_id INT,
    annee_id INT,
    FOREIGN KEY (enseignant_id) REFERENCES utilisateurs(id),
    FOREIGN KEY (matiere_id) REFERENCES matieres(id),
    FOREIGN KEY (classe_id) REFERENCES classes(id),
    FOREIGN KEY (annee_id) REFERENCES annees_scolaires(id)
);

-- Salles
CREATE TABLE salles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(50),
    batiment VARCHAR(50),
    capacite INT,
    type ENUM('classe', 'labo', 'sport', 'autre') DEFAULT 'classe'
);

-- Emploi du temps
CREATE TABLE emploi_temps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    classe_id INT,
    matiere_id INT,
    enseignant_id INT,
    salle_id INT,
    jour ENUM('lundi','mardi','mercredi','jeudi','vendredi','samedi'),
    heure_debut TIME,
    heure_fin TIME,
    annee_id INT,
    FOREIGN KEY (classe_id) REFERENCES classes(id),
    FOREIGN KEY (matiere_id) REFERENCES matieres(id),
    FOREIGN KEY (enseignant_id) REFERENCES utilisateurs(id),
    FOREIGN KEY (salle_id) REFERENCES salles(id),
    FOREIGN KEY (annee_id) REFERENCES annees_scolaires(id)
);

-- Notes
CREATE TABLE notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    etudiant_id INT,
    matiere_id INT,
    enseignant_id INT,
    classe_id INT,
    note DECIMAL(4,2),
    type_note ENUM('devoir', 'composition', 'examen', 'oral'),
    date_note DATE,
    annee_id INT,
    FOREIGN KEY (etudiant_id) REFERENCES utilisateurs(id),
    FOREIGN KEY (matiere_id) REFERENCES matieres(id),
    FOREIGN KEY (enseignant_id) REFERENCES utilisateurs(id),
    FOREIGN KEY (classe_id) REFERENCES classes(id),
    FOREIGN KEY (annee_id) REFERENCES annees_scolaires(id)
);

-- Absences
CREATE TABLE absences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    etudiant_id INT,
    matiere_id INT,
    classe_id INT,
    date_absence DATE,
    heure_absence TIME,
    justifiee TINYINT(1) DEFAULT 0,
    enseignant_id INT,
    annee_id INT,
    FOREIGN KEY (etudiant_id) REFERENCES utilisateurs(id),
    FOREIGN KEY (matiere_id) REFERENCES matieres(id),
    FOREIGN KEY (classe_id) REFERENCES classes(id),
    FOREIGN KEY (enseignant_id) REFERENCES utilisateurs(id),
    FOREIGN KEY (annee_id) REFERENCES annees_scolaires(id)
);

-- Retards
CREATE TABLE retards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    etudiant_id INT,
    date_retard DATE,
    heure_retard TIME,
    duree INT,
    classe_id INT,
    annee_id INT,
    FOREIGN KEY (etudiant_id) REFERENCES utilisateurs(id),
    FOREIGN KEY (classe_id) REFERENCES classes(id),
    FOREIGN KEY (annee_id) REFERENCES annees_scolaires(id)
);

-- Sanctions
CREATE TABLE sanctions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    etudiant_id INT,
    type_sanction ENUM('avertissement', 'retenue', 'exclusion', 'autre'),
    date_sanction DATE,
    gravite ENUM('leger', 'moyen', 'grave'),
    donnee_par INT,
    annee_id INT,
    FOREIGN KEY (etudiant_id) REFERENCES utilisateurs(id),
    FOREIGN KEY (donnee_par) REFERENCES utilisateurs(id),
    FOREIGN KEY (annee_id) REFERENCES annees_scolaires(id)
);

-- Cours
CREATE TABLE cours (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titre VARCHAR(255),
    matiere_id INT,
    classe_id INT,
    enseignant_id INT,
    fichier_path VARCHAR(255),
    type_fichier ENUM('pdf', 'doc', 'ppt', 'video', 'lien', 'autre'),
    date_publication DATETIME DEFAULT CURRENT_TIMESTAMP,
    annee_id INT,
    FOREIGN KEY (matiere_id) REFERENCES matieres(id),
    FOREIGN KEY (classe_id) REFERENCES classes(id),
    FOREIGN KEY (enseignant_id) REFERENCES utilisateurs(id),
    FOREIGN KEY (annee_id) REFERENCES annees_scolaires(id)
);

-- Devoirs
CREATE TABLE devoirs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titre VARCHAR(255),
    matiere_id INT,
    classe_id INT,
    enseignant_id INT,
    fichier_path VARCHAR(255),
    type ENUM('ecrit', 'oral', 'projet') DEFAULT 'ecrit',
    date_limite DATETIME,
    annee_id INT,
    FOREIGN KEY (matiere_id) REFERENCES matieres(id),
    FOREIGN KEY (classe_id) REFERENCES classes(id),
    FOREIGN KEY (enseignant_id) REFERENCES utilisateurs(id),
    FOREIGN KEY (annee_id) REFERENCES annees_scolaires(id)
);

CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    expediteur_id INT NOT NULL,
    destinataire_id INT NOT NULL,
    sujet VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    date_envoi DATETIME DEFAULT CURRENT_TIMESTAMP,
    statut_lecture ENUM('non_lu', 'lu') DEFAULT 'non_lu',
    supprime_expediteur TINYINT(1) DEFAULT 0,
    supprime_destinataire TINYINT(1) DEFAULT 0,
    FOREIGN KEY (expediteur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    FOREIGN KEY (destinataire_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS annonces (
    id INT AUTO_INCREMENT PRIMARY KEY,
    enseignant_id INT NOT NULL,
    titre VARCHAR(255) NOT NULL,
    contenu TEXT NOT NULL,
    date_publication DATETIME DEFAULT CURRENT_TIMESTAMP,
    -- Pour savoir à qui s'adresse l'annonce. NULL signifie "toutes les classes de l'enseignant".
    classe_id INT DEFAULT NULL, 
    annee_id INT NOT NULL,
    FOREIGN KEY (enseignant_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    FOREIGN KEY (classe_id) REFERENCES classes(id) ON DELETE SET NULL,
    FOREIGN KEY (annee_id) REFERENCES annees_scolaires(id) ON DELETE CASCADE
);

CREATE TABLE ressources_bibliotheque (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titre VARCHAR(255) NOT NULL,
    description TEXT,
    type ENUM('livre', 'numerique', 'article', 'video', 'audio', 'lien') NOT NULL,
    fichier VARCHAR(255),
    lien VARCHAR(255),
    categorie_id INT NOT NULL,
    date_ajout DATETIME DEFAULT CURRENT_TIMESTAMP,
    statut ENUM('actif', 'inactif') DEFAULT 'actif',
    FOREIGN KEY (categorie_id) REFERENCES categories_ressources(id)
);
CREATE TABLE categories_ressources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL
);

CREATE TABLE activites_scolaires (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titre VARCHAR(255) NOT NULL,
    description TEXT,
    type ENUM('sport', 'culture', 'scientifique', 'educatif', 'sortie') NOT NULL,
    date_debut DATETIME NOT NULL,
    date_fin DATETIME NOT NULL,
    lieu VARCHAR(100),
    organisateur_id INT NOT NULL,
    classe_id INT,
    annee_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (organisateur_id) REFERENCES utilisateurs(id),
    FOREIGN KEY (classe_id) REFERENCES classes(id),
    FOREIGN KEY (annee_id) REFERENCES annees_scolaires(id)
);

CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT NOT NULL, -- L'ID de l'utilisateur qui reçoit la notif (étudiant, enseignant...)
    type ENUM('note', 'devoir', 'annonce', 'absence', 'message', 'info') NOT NULL,
    message TEXT NOT NULL,
    lien VARCHAR(255), -- Lien optionnel pour rediriger l'utilisateur (ex: vers la page des notes)
    statut ENUM('non_lu', 'lu') DEFAULT 'non_lu',
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
);

CREATE TABLE remises_devoirs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    devoir_id INT NOT NULL,
    etudiant_id INT NOT NULL,
    date_remise DATETIME DEFAULT CURRENT_TIMESTAMP,
    fichier_remis_path VARCHAR(255),
    reponse_texte TEXT,
    note DECIMAL(4,2) NULL, -- Pour que l'enseignant puisse noter la remise
    commentaire_enseignant TEXT, -- Pour le feedback de l'enseignant
    FOREIGN KEY (devoir_id) REFERENCES devoirs(id) ON DELETE CASCADE,
    FOREIGN KEY (etudiant_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    UNIQUE KEY (devoir_id, etudiant_id) -- Un étudiant ne peut remettre qu'une seule fois le même devoir (on mettra à jour sa remise)
);