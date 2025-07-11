-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3306
-- Généré le : mar. 08 juil. 2025 à 21:16
-- Version du serveur : 9.1.0
-- Version de PHP : 8.1.31

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `gestion_ecole`
--

-- --------------------------------------------------------

--
-- Structure de la table `absences`
--

DROP TABLE IF EXISTS `absences`;
CREATE TABLE IF NOT EXISTS `absences` (
  `id` int NOT NULL AUTO_INCREMENT,
  `etudiant_id` int DEFAULT NULL,
  `matiere_id` int DEFAULT NULL,
  `classe_id` int DEFAULT NULL,
  `date_absence` date DEFAULT NULL,
  `heure_absence` time DEFAULT NULL,
  `justifiee` tinyint(1) DEFAULT '0',
  `enseignant_id` int DEFAULT NULL,
  `annee_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `etudiant_id` (`etudiant_id`),
  KEY `matiere_id` (`matiere_id`),
  KEY `classe_id` (`classe_id`),
  KEY `enseignant_id` (`enseignant_id`),
  KEY `annee_id` (`annee_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `activites_scolaires`
--

DROP TABLE IF EXISTS `activites_scolaires`;
CREATE TABLE IF NOT EXISTS `activites_scolaires` (
  `id` int NOT NULL AUTO_INCREMENT,
  `titre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `type` enum('sport','culture','scientifique','educatif','sortie') COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_debut` datetime NOT NULL,
  `date_fin` datetime NOT NULL,
  `lieu` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `organisateur_id` int NOT NULL,
  `classe_id` int DEFAULT NULL,
  `annee_id` int NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `organisateur_id` (`organisateur_id`),
  KEY `classe_id` (`classe_id`),
  KEY `annee_id` (`annee_id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `activites_scolaires`
--

INSERT INTO `activites_scolaires` (`id`, `titre`, `description`, `type`, `date_debut`, `date_fin`, `lieu`, `organisateur_id`, `classe_id`, `annee_id`, `created_at`) VALUES
(1, '🎓 Journée de l’Innovation et de la Créativité Étudiante', 'La Journée de l’Innovation et de la Créativité Étudiante est un événement organisé par l’université pour mettre en lumière les talents et les projets novateurs des étudiants. Elle réunit des exposants issus de différentes filières (informatique, art, sciences, entrepreneuriat, etc.) qui présentent leurs réalisations sous forme de stands, démonstrations ou mini-conférences. Cette journée favorise l’échange, le réseautage, et l’émulation autour de la créativité étudiante, avec à la clé des prix pour les meilleurs projets présentés. C’est également l’occasion de renforcer le lien entre étudiants, enseignants, professionnels et partenaires de l’université.', 'culture', '2025-06-20 12:00:00', '2025-08-30 12:00:00', 'ENO Dakar', 1, 1, 1, '2025-06-14 15:47:17');

-- --------------------------------------------------------

--
-- Structure de la table `annees_scolaires`
--

DROP TABLE IF EXISTS `annees_scolaires`;
CREATE TABLE IF NOT EXISTS `annees_scolaires` (
  `id` int NOT NULL AUTO_INCREMENT,
  `annee` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_debut` date DEFAULT NULL,
  `date_fin` date DEFAULT NULL,
  `statut` enum('planifie','en_cours','termine') COLLATE utf8mb4_unicode_ci DEFAULT 'planifie',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `annees_scolaires`
--

INSERT INTO `annees_scolaires` (`id`, `annee`, `date_debut`, `date_fin`, `statut`) VALUES
(1, '2024-2025', '2025-06-13', '2025-07-06', 'en_cours');

-- --------------------------------------------------------

--
-- Structure de la table `annonces`
--

DROP TABLE IF EXISTS `annonces`;
CREATE TABLE IF NOT EXISTS `annonces` (
  `id` int NOT NULL AUTO_INCREMENT,
  `enseignant_id` int NOT NULL,
  `titre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contenu` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_publication` datetime DEFAULT CURRENT_TIMESTAMP,
  `classe_id` int DEFAULT NULL,
  `annee_id` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `enseignant_id` (`enseignant_id`),
  KEY `classe_id` (`classe_id`),
  KEY `annee_id` (`annee_id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `annonces`
--

INSERT INTO `annonces` (`id`, `enseignant_id`, `titre`, `contenu`, `date_publication`, `classe_id`, `annee_id`) VALUES
(1, 2, 'EXAMEN FINAL S6 P9', 'BONJOURS', '2025-06-13 23:48:35', 1, 1),
(2, 2, 'DEVOIR PYTHON', 'sur les fonctions', '2025-06-14 15:07:36', 1, 1);

-- --------------------------------------------------------

--
-- Structure de la table `categories_ressources`
--

DROP TABLE IF EXISTS `categories_ressources`;
CREATE TABLE IF NOT EXISTS `categories_ressources` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `categories_ressources`
--

INSERT INTO `categories_ressources` (`id`, `nom`) VALUES
(1, 'numérique');

-- --------------------------------------------------------

--
-- Structure de la table `classes`
--

DROP TABLE IF EXISTS `classes`;
CREATE TABLE IF NOT EXISTS `classes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `niveau` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cycle_id` int DEFAULT NULL,
  `annee_id` int DEFAULT NULL,
  `responsable_id` int DEFAULT NULL,
  `capacite_max` int DEFAULT '30',
  PRIMARY KEY (`id`),
  KEY `cycle_id` (`cycle_id`),
  KEY `annee_id` (`annee_id`),
  KEY `responsable_id` (`responsable_id`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `classes`
--

INSERT INTO `classes` (`id`, `nom`, `niveau`, `cycle_id`, `annee_id`, `responsable_id`, `capacite_max`) VALUES
(1, 'Amphie A', 'L3', 1, 1, 2, 50),
(2, 'Amphie B', 'L2', 1, 1, 2, 100);

-- --------------------------------------------------------

--
-- Structure de la table `cours`
--

DROP TABLE IF EXISTS `cours`;
CREATE TABLE IF NOT EXISTS `cours` (
  `id` int NOT NULL AUTO_INCREMENT,
  `titre` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `matiere_id` int DEFAULT NULL,
  `classe_id` int DEFAULT NULL,
  `enseignant_id` int DEFAULT NULL,
  `fichier_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type_fichier` enum('pdf','doc','ppt','video','lien','autre') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_publication` datetime DEFAULT CURRENT_TIMESTAMP,
  `annee_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `matiere_id` (`matiere_id`),
  KEY `classe_id` (`classe_id`),
  KEY `enseignant_id` (`enseignant_id`),
  KEY `annee_id` (`annee_id`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `cours`
--

INSERT INTO `cours` (`id`, `titre`, `matiere_id`, `classe_id`, `enseignant_id`, `fichier_path`, `type_fichier`, `date_publication`, `annee_id`) VALUES
(2, 'IONIC', 1, 1, 2, 'uploads/cours/pdf/cours_684cc5644da8e_EXAMEN.pdf', NULL, '2025-06-14 00:42:12', 1),
(3, 'ALGORITHME', 1, 1, 2, 'uploads/cours/pdf/cours_684ccda5cb804_bdcrudjsf.sql', NULL, '2025-06-14 01:17:00', 1);

-- --------------------------------------------------------

--
-- Structure de la table `cycles`
--

DROP TABLE IF EXISTS `cycles`;
CREATE TABLE IF NOT EXISTS `cycles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `duree_annees` int DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `cycles`
--

INSERT INTO `cycles` (`id`, `nom`, `code`, `duree_annees`) VALUES
(1, 'universitaire', '236010', 4);

-- --------------------------------------------------------

--
-- Structure de la table `devoirs`
--

DROP TABLE IF EXISTS `devoirs`;
CREATE TABLE IF NOT EXISTS `devoirs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `titre` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `matiere_id` int DEFAULT NULL,
  `classe_id` int DEFAULT NULL,
  `enseignant_id` int DEFAULT NULL,
  `fichier_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` enum('ecrit','oral','projet') COLLATE utf8mb4_unicode_ci DEFAULT 'ecrit',
  `date_limite` datetime DEFAULT NULL,
  `annee_id` int DEFAULT NULL,
  `rendu` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `matiere_id` (`matiere_id`),
  KEY `classe_id` (`classe_id`),
  KEY `enseignant_id` (`enseignant_id`),
  KEY `annee_id` (`annee_id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `devoirs`
--

INSERT INTO `devoirs` (`id`, `titre`, `matiere_id`, `classe_id`, `enseignant_id`, `fichier_path`, `type`, `date_limite`, `annee_id`, `rendu`) VALUES
(1, 'DEVOIR IONIC', 1, 1, 2, 'uploads/devoirs/devoir_684cb760ba3e1_EXAMEN.pdf', 'ecrit', '2025-06-20 23:41:00', 1, 0),
(2, 'EXAMEN FINAL S6 P9', 1, 1, 2, '', 'projet', '2025-07-15 23:43:00', 1, 0);

-- --------------------------------------------------------

--
-- Structure de la table `emploi_temps`
--

DROP TABLE IF EXISTS `emploi_temps`;
CREATE TABLE IF NOT EXISTS `emploi_temps` (
  `id` int NOT NULL AUTO_INCREMENT,
  `classe_id` int DEFAULT NULL,
  `matiere_id` int DEFAULT NULL,
  `enseignant_id` int DEFAULT NULL,
  `salle_id` int DEFAULT NULL,
  `jour` enum('lundi','mardi','mercredi','jeudi','vendredi','samedi') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `heure_debut` time DEFAULT NULL,
  `heure_fin` time DEFAULT NULL,
  `annee_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `classe_id` (`classe_id`),
  KEY `matiere_id` (`matiere_id`),
  KEY `enseignant_id` (`enseignant_id`),
  KEY `salle_id` (`salle_id`),
  KEY `annee_id` (`annee_id`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `emploi_temps`
--

INSERT INTO `emploi_temps` (`id`, `classe_id`, `matiere_id`, `enseignant_id`, `salle_id`, `jour`, `heure_debut`, `heure_fin`, `annee_id`) VALUES
(1, 1, 1, 2, 1, 'mardi', '10:15:00', '12:15:00', 1),
(2, 1, 1, 2, 1, 'lundi', '10:00:00', '12:00:00', 1),
(3, 1, 1, 2, 1, 'jeudi', '12:00:00', '14:00:00', 1),
(4, 1, 1, 2, 1, 'samedi', '22:00:00', '00:00:00', 1);

-- --------------------------------------------------------

--
-- Structure de la table `enseignant_matieres`
--

DROP TABLE IF EXISTS `enseignant_matieres`;
CREATE TABLE IF NOT EXISTS `enseignant_matieres` (
  `id` int NOT NULL AUTO_INCREMENT,
  `enseignant_id` int DEFAULT NULL,
  `matiere_id` int DEFAULT NULL,
  `classe_id` int DEFAULT NULL,
  `annee_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `enseignant_id` (`enseignant_id`),
  KEY `matiere_id` (`matiere_id`),
  KEY `classe_id` (`classe_id`),
  KEY `annee_id` (`annee_id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `enseignant_matieres`
--

INSERT INTO `enseignant_matieres` (`id`, `enseignant_id`, `matiere_id`, `classe_id`, `annee_id`) VALUES
(1, 2, 1, 1, 1);

-- --------------------------------------------------------

--
-- Structure de la table `inscriptions`
--

DROP TABLE IF EXISTS `inscriptions`;
CREATE TABLE IF NOT EXISTS `inscriptions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `etudiant_id` int DEFAULT NULL,
  `classe_id` int DEFAULT NULL,
  `annee_id` int DEFAULT NULL,
  `date_inscription` date DEFAULT NULL,
  `statut` enum('actif','suspendu','desinscrit') COLLATE utf8mb4_unicode_ci DEFAULT 'actif',
  PRIMARY KEY (`id`),
  KEY `etudiant_id` (`etudiant_id`),
  KEY `classe_id` (`classe_id`),
  KEY `annee_id` (`annee_id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `inscriptions`
--

INSERT INTO `inscriptions` (`id`, `etudiant_id`, `classe_id`, `annee_id`, `date_inscription`, `statut`) VALUES
(1, 3, 1, 1, '2025-06-14', 'actif'),
(2, 4, 1, 1, '2025-06-14', 'actif');

-- --------------------------------------------------------

--
-- Structure de la table `matieres`
--

DROP TABLE IF EXISTS `matieres`;
CREATE TABLE IF NOT EXISTS `matieres` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `coefficient` int DEFAULT '1',
  `heures_annuelles` int DEFAULT '60',
  `cycle_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `cycle_id` (`cycle_id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `matieres`
--

INSERT INTO `matieres` (`id`, `code`, `nom`, `coefficient`, `heures_annuelles`, `cycle_id`) VALUES
(1, '236010', 'PHP', 4, 50, 1),
(2, '762930', 'JSF', 3, 40, 1);

-- --------------------------------------------------------

--
-- Structure de la table `messages`
--

DROP TABLE IF EXISTS `messages`;
CREATE TABLE IF NOT EXISTS `messages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `expediteur_id` int NOT NULL,
  `destinataire_id` int NOT NULL,
  `sujet` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_envoi` datetime DEFAULT CURRENT_TIMESTAMP,
  `statut_lecture` enum('non_lu','lu') COLLATE utf8mb4_unicode_ci DEFAULT 'non_lu',
  `supprime_expediteur` tinyint(1) DEFAULT '0',
  `supprime_destinataire` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `expediteur_id` (`expediteur_id`),
  KEY `destinataire_id` (`destinataire_id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `messages`
--

INSERT INTO `messages` (`id`, `expediteur_id`, `destinataire_id`, `sujet`, `message`, `date_envoi`, `statut_lecture`, `supprime_expediteur`, `supprime_destinataire`) VALUES
(1, 2, 3, 'EXAMEN FINAL S6', 'le projet final de s6 doit etre rendre avant le 15/07/1015', '2025-06-13 23:47:50', 'non_lu', 0, 0);

-- --------------------------------------------------------

--
-- Structure de la table `notes`
--

DROP TABLE IF EXISTS `notes`;
CREATE TABLE IF NOT EXISTS `notes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `etudiant_id` int DEFAULT NULL,
  `matiere_id` int DEFAULT NULL,
  `enseignant_id` int DEFAULT NULL,
  `classe_id` int DEFAULT NULL,
  `note` decimal(4,2) DEFAULT NULL,
  `type_note` enum('devoir','composition','examen','oral') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_note` date DEFAULT NULL,
  `annee_id` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `etudiant_id` (`etudiant_id`),
  KEY `matiere_id` (`matiere_id`),
  KEY `enseignant_id` (`enseignant_id`),
  KEY `classe_id` (`classe_id`),
  KEY `annee_id` (`annee_id`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `notes`
--

INSERT INTO `notes` (`id`, `etudiant_id`, `matiere_id`, `enseignant_id`, `classe_id`, `note`, `type_note`, `date_note`, `annee_id`, `created_at`, `updated_at`) VALUES
(1, 3, 1, 1, 1, 14.00, 'devoir', '2025-06-14', 1, '2025-06-14 20:33:40', '2025-07-08 20:41:36'),
(2, 4, 1, 1, 1, 18.00, 'devoir', '2025-06-14', 1, '2025-06-14 20:33:40', '2025-07-08 20:41:39'),
(3, 3, 1, 2, 1, 19.00, 'composition', '2025-07-08', 1, '2025-07-08 20:48:27', '2025-07-08 20:48:27'),
(4, 4, 1, 2, 1, 20.00, 'composition', '2025-07-08', 1, '2025-07-08 20:48:27', '2025-07-08 20:48:27');

-- --------------------------------------------------------

--
-- Structure de la table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `utilisateur_id` int NOT NULL,
  `type` enum('note','devoir','annonce','absence','message','info') COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `lien` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `statut` enum('non_lu','lu') COLLATE utf8mb4_unicode_ci DEFAULT 'non_lu',
  `date_creation` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `utilisateur_id` (`utilisateur_id`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `notifications`
--

INSERT INTO `notifications` (`id`, `utilisateur_id`, `type`, `message`, `lien`, `statut`, `date_creation`) VALUES
(1, 1, 'devoir', '🔔 Notification de Devoir :\r\n\r\nBonjour,\r\nUn nouveau devoir est disponible pour la matière Mathématiques.\r\nMerci de le compléter avant la date limite fixée.\r\nCliquez sur le lien ci-dessous pour accéder au devoir et y répondre directement.\r\n\r\n👉 Répondre au devoir', 'repondre_devoir.php?devoir_id=42', 'non_lu', '2025-06-14 23:09:34'),
(2, 2, 'devoir', '🔔 Notification de Devoir :\r\n\r\nBonjour,\r\nUn nouveau devoir est disponible pour la matière Mathématiques.\r\nMerci de le compléter avant la date limite fixée.\r\nCliquez sur le lien ci-dessous pour accéder au devoir et y répondre directement.\r\n\r\n👉 Répondre au devoir', 'repondre_devoir.php?devoir_id=42', 'non_lu', '2025-06-14 23:09:34'),
(3, 3, 'devoir', '🔔 Notification de Devoir :\r\n\r\nBonjour,\r\nUn nouveau devoir est disponible pour la matière Mathématiques.\r\nMerci de le compléter avant la date limite fixée.\r\nCliquez sur le lien ci-dessous pour accéder au devoir et y répondre directement.\r\n\r\n👉 Répondre au devoir', 'repondre_devoir.php?devoir_id=42', 'lu', '2025-06-14 23:09:34'),
(4, 4, 'devoir', '🔔 Notification de Devoir :\r\n\r\nBonjour,\r\nUn nouveau devoir est disponible pour la matière Mathématiques.\r\nMerci de le compléter avant la date limite fixée.\r\nCliquez sur le lien ci-dessous pour accéder au devoir et y répondre directement.\r\n\r\n👉 Répondre au devoir', 'repondre_devoir.php?devoir_id=42', 'non_lu', '2025-06-14 23:09:34');

-- --------------------------------------------------------

--
-- Structure de la table `remises_devoirs`
--

DROP TABLE IF EXISTS `remises_devoirs`;
CREATE TABLE IF NOT EXISTS `remises_devoirs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `devoir_id` int NOT NULL,
  `etudiant_id` int NOT NULL,
  `date_remise` datetime DEFAULT CURRENT_TIMESTAMP,
  `fichier_remis_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reponse_texte` text COLLATE utf8mb4_unicode_ci,
  `note` decimal(4,2) DEFAULT NULL,
  `commentaire_enseignant` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `devoir_id` (`devoir_id`,`etudiant_id`),
  KEY `etudiant_id` (`etudiant_id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `remises_devoirs`
--

INSERT INTO `remises_devoirs` (`id`, `devoir_id`, `etudiant_id`, `date_remise`, `fichier_remis_path`, `reponse_texte`, `note`, `commentaire_enseignant`) VALUES
(1, 1, 3, '2025-06-15 00:31:35', 'uploads/remises/remise_1_3_1749947495.pdf', 'remise', NULL, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `ressources_bibliotheque`
--

DROP TABLE IF EXISTS `ressources_bibliotheque`;
CREATE TABLE IF NOT EXISTS `ressources_bibliotheque` (
  `id` int NOT NULL AUTO_INCREMENT,
  `titre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `type` enum('livre','numerique','article','video','audio','lien') COLLATE utf8mb4_unicode_ci NOT NULL,
  `fichier` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lien` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `categorie_id` int NOT NULL,
  `date_ajout` datetime DEFAULT CURRENT_TIMESTAMP,
  `statut` enum('actif','inactif') COLLATE utf8mb4_unicode_ci DEFAULT 'actif',
  PRIMARY KEY (`id`),
  KEY `categorie_id` (`categorie_id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `ressources_bibliotheque`
--

INSERT INTO `ressources_bibliotheque` (`id`, `titre`, `description`, `type`, `fichier`, `lien`, `categorie_id`, `date_ajout`, `statut`) VALUES
(2, 'Livre', 'livre pdf imoblier', 'numerique', '', NULL, 1, '2025-06-14 22:04:07', 'actif');

-- --------------------------------------------------------

--
-- Structure de la table `retards`
--

DROP TABLE IF EXISTS `retards`;
CREATE TABLE IF NOT EXISTS `retards` (
  `id` int NOT NULL AUTO_INCREMENT,
  `etudiant_id` int DEFAULT NULL,
  `date_retard` date DEFAULT NULL,
  `heure_retard` time DEFAULT NULL,
  `duree` int DEFAULT NULL,
  `classe_id` int DEFAULT NULL,
  `annee_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `etudiant_id` (`etudiant_id`),
  KEY `classe_id` (`classe_id`),
  KEY `annee_id` (`annee_id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `retards`
--

INSERT INTO `retards` (`id`, `etudiant_id`, `date_retard`, `heure_retard`, `duree`, `classe_id`, `annee_id`) VALUES
(1, 4, '2025-06-14', '20:35:00', 11, 1, 1);

-- --------------------------------------------------------

--
-- Structure de la table `salles`
--

DROP TABLE IF EXISTS `salles`;
CREATE TABLE IF NOT EXISTS `salles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `batiment` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `capacite` int DEFAULT NULL,
  `type` enum('classe','labo','sport','autre') COLLATE utf8mb4_unicode_ci DEFAULT 'classe',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `salles`
--

INSERT INTO `salles` (`id`, `nom`, `batiment`, `capacite`, `type`) VALUES
(1, 'Amphie 3', 'A', 100, 'classe'),
(2, 'Salle 4 A', 'C', 200, 'classe');

-- --------------------------------------------------------

--
-- Structure de la table `sanctions`
--

DROP TABLE IF EXISTS `sanctions`;
CREATE TABLE IF NOT EXISTS `sanctions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `etudiant_id` int DEFAULT NULL,
  `type_sanction` enum('avertissement','retenue','exclusion','autre') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_sanction` date DEFAULT NULL,
  `gravite` enum('leger','moyen','grave') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `donnee_par` int DEFAULT NULL,
  `annee_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `etudiant_id` (`etudiant_id`),
  KEY `donnee_par` (`donnee_par`),
  KEY `annee_id` (`annee_id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `sanctions`
--

INSERT INTO `sanctions` (`id`, `etudiant_id`, `type_sanction`, `date_sanction`, `gravite`, `donnee_par`, `annee_id`) VALUES
(1, 3, 'avertissement', '2025-06-14', 'leger', 1, 1);

-- --------------------------------------------------------

--
-- Structure de la table `utilisateurs`
--

DROP TABLE IF EXISTS `utilisateurs`;
CREATE TABLE IF NOT EXISTS `utilisateurs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prenom` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('admin','enseignant','etudiant') COLLATE utf8mb4_unicode_ci NOT NULL,
  `telephone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `adresse` text COLLATE utf8mb4_unicode_ci,
  `sexe` enum('M','F') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_naissance` date DEFAULT NULL,
  `photo_profil` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `specialite` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `grade` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contrat` enum('titulaire','contractuel','vacataire') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `groupe_sanguin` varchar(3) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `statut` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `utilisateurs`
--

INSERT INTO `utilisateurs` (`id`, `nom`, `prenom`, `email`, `password`, `role`, `telephone`, `adresse`, `sexe`, `date_naissance`, `photo_profil`, `specialite`, `grade`, `contrat`, `groupe_sanguin`, `statut`, `created_at`, `updated_at`) VALUES
(1, 'Faye', 'Ibrahima', 'ibsibzo97@gmail.com', '$2y$10$Ygc7I5Zszl6wJTcTNb8bSOCPKQ6RJCM93dvG6llGWB0a35HCW4QE.', 'admin', '773588475', 'Cité soprim', 'M', '2025-06-04', 'uploads/profiles/user_684b73e91ab04_IMG_3364.JPG', NULL, NULL, NULL, 'o+', 1, '2025-06-13 00:42:17', NULL),
(2, 'Faye', 'Ibrahima', 'ibrahima.faye42@unchk.edu.sn', '$2y$10$BrbXNkUDxXSRtliMb2bt8O.BNCLUPzNB77cexbOhaYiDTkgjGQ0LO', 'enseignant', '773588475', 'Cité soprim', 'M', NULL, 'uploads/profiles/teacher_684b805a32427_IMG_3364.JPG', 'HG', '5 ans', 'titulaire', NULL, 1, '2025-06-13 01:35:22', NULL),
(3, 'Diouf', 'Ibou', 'birame01@gmail.com', '$2y$10$oCPvvzwxC..qzFbfN0xg1eeCsgms06gogZiDUvfVAu3oXZs.z.q9C', 'etudiant', '70 336 29 64', 'Parcelle assainies unité 14', 'M', '2025-01-14', 'uploads/profiles/student_684b82db0f514_IMG_3366.JPG', NULL, NULL, NULL, 'o+', 1, '2025-06-13 01:46:03', NULL),
(4, 'Faye', 'Ibrahima', 'etudiant@eshop.com', '$2y$10$6pp/bRYdHWb4FRXP4A8eUeqHnDAaRTDJ4CURKGlXbKPxIqbwb24FK', 'etudiant', '773588475', 'Cité soprim', 'M', '2025-06-02', 'uploads/profiles/student_684df692b4eff_IMG_3367.JPG', NULL, NULL, NULL, 'AB', 1, '2025-06-14 20:13:04', '2025-06-14 22:24:18');
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
