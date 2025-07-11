gestion_ecole/
│
├── config/
│   ├── db.php                   # Connexion à la base de données
│   ├── config.php               # Constantes globales
│   └── routes.php               # Routes et permissions par rôle
│
├── includes/
│   ├── auth/
│   │   ├── auth_check.php       # Vérification des sessions/rôles
│   │   └── permissions.php      # Gestion des permissions
│   ├── header.php               # En-tête HTML commun
│   ├── footer.php               # Pied de page commun
│   ├── functions.php            # Fonctions utilitaires
│   └── notifications.php        # Gestion des notifications
│
├── auth/
│   ├── login.php                # Page de connexion
│   ├── logout.php               # Déconnexion
│   ├── register.php             # Création de comptes (admin)
│   └── password_reset.php       # Réinitialisation mot de passe
│
├── admin/
│   ├── dashboard.php            # Tableau de bord admin
│   ├── gestion/
│   │   ├── classes.php          # CRUD classes
│   │   ├── matieres.php         # CRUD matières
│   │   ├── cycles.php           # Gestion des sycles crud
│   │   ├── salles.php           # Gestion des salles
│   │   └── annees.php           # Gestion années scolaires
│   ├── personnel/
│   │   ├── enseignants.php      # CRUD enseignants
│   │   └── etudiants.php        # CRUD étudiants
│   ├── pedagogie/
│   │   ├── emploi_temps.php     # Gestion EDT
│   │   ├── activites.php        # les activites scolaires CRUD
│   │   ├── affectations.php     # Affectation enseignants
│   │   └── programmes.php       # Programmes scolaires
│   ├── evaluation/
│   │   ├── notes.php           # Gestion notes
│   │   ├── bulletins.php        # Génération bulletins
│   │   └── deliberations.php    # Délibérations
│   └── surveillance/
│       ├── absences.php         # Suivi absences
│       ├── sanctions.php        # Gestion sanctions
│       └── statistiques.php     # Statistiques
│
├── enseignant/
│   ├── dashboard.php            # Dashboard perso
│   ├── pedagogie/
│   │   ├── cours.php            # Gestion cours
│   │   ├── ressources.php       # Ressources pédagogiques
│   │   └── projets.php          # Gestion projets
│   ├── evaluation/
│   │   ├── notes.php           # Saisie notes
│   │   └── competences.php      # Évaluation compétences
│   ├── presence/
│   │   ├── absences.php         # Marquer absences
│   │   └── retard.php           # Gestion retards
│   └── communication/
│       ├── messagerie.php       # Messagerie interne
│       └── annonces.php         # Publications annonces
│
├── etudiant/
│   ├── dashboard.php            # Tableau de bord
│   ├── scolarite/
│   │   ├── bulletin.php         # Consulter notes
│   │   ├── emploi.php           # Voir EDT
│   │   └── cahier_texte.php     # Cahier de texte
│   ├── ressources/
│   │   ├── cours.php            # Accès cours
│   │   └── bibliotheque.php     # Ressources
│   └── vie_scolaire/
│       ├── absences.php         # Mes absences
│       └── activites.php        # Activités scolaires
│
├── api/                         # Endpoints API
│   ├── v1/
│   │   ├── notes.php
│   │   ├── absences.php
│   │   └── emploi.php
│   └── .htaccess
│
├── assets/
│   ├── css/
│   │   ├── core/
│   │   │   ├── bootstrap.css    # Bootstrap custom
│   │   │   ├── theme.css        # Thème principal
│   │   │   └── utilities.css    # Classes utilitaires
│   │   ├── layouts/
│   │   │   ├── admin.css
│   │   │   ├── enseignant.css
│   │   │   └── etudiant.css
│   │   └── plugins/             # Bibliothèques externes
│   ├── js/
│   │   ├── core/
│   │   │   ├── app.js           # JS principal
│   │   │   └── auth.js          # Gestion auth
│   │   ├── modules/
│   │   │   ├── datatables.js    # Gestion tableaux
│   │   │   └── calendar.js      # Calendrier/EDT
│   │   └── plugins/
│   ├── img/
│   │   ├── logo/
│   │   ├── icons/
│   │   └── profiles/
│   └── vendors/                 # Libs externes
│
├── uploads/
│   ├── cours/
│   │   ├── pdf/
│   │   ├── videos/
│   │   └── autres/
│   ├── profiles/
│   ├── bulletins/
│   └── temp/
│
├── lib/                         # Bibliothèques PHP
│   ├── PHPMailer/
│   └── PDF/
│
├── templates/                   # Templates emails
│   ├── email_reset.html
│   └── notification.html
│
├── index.php                    # Point d'entrée
├── .htaccess                    # Rewrite rules
└── README.md                    # Documentation