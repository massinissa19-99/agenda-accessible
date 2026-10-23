-- ============================================================
-- Agenda Accessible : schéma de la base de données + données d'exemple
-- Utilisation : mysql -u root -p < sql/schema.sql
-- ATTENTION : ce script SUPPRIME puis recrée les tables (usage pédagogique).
--
-- Comptes de démonstration (à changer avant toute mise en ligne) :
--   Administrateur : admin@exemple.fr   / Admin-Agenda-2026
--   Membre         : membre@exemple.fr  / Membre-Agenda-2026
-- ============================================================

CREATE DATABASE IF NOT EXISTS agenda_accessible
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE agenda_accessible;

-- Indique que CE FICHIER est en UTF-8, quel que soit le réglage du client
-- (sans cela, certains clients MySQL déforment les accents : « Ã© » au lieu de « é »)
SET NAMES utf8mb4;

-- On supprime d'abord les tables qui dépendent des autres (clés étrangères)
DROP TABLE IF EXISTS inscriptions;
DROP TABLE IF EXISTS messages;
DROP TABLE IF EXISTS evenements;
DROP TABLE IF EXISTS utilisateurs;
DROP TABLE IF EXISTS categories;

-- ------------------------------------------------------------
-- Table des catégories (relation 1-N avec les événements)
-- ------------------------------------------------------------
CREATE TABLE categories (
  id  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nom VARCHAR(60)  NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_categories_nom (nom)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Table des utilisateurs
-- Le mot de passe n'est JAMAIS stocké en clair : seulement son hachage
-- (fonction PHP password_hash). "role" distingue membres et administrateurs.
-- ------------------------------------------------------------
CREATE TABLE utilisateurs (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nom               VARCHAR(100) NOT NULL,
  email             VARCHAR(150) NOT NULL,
  mot_de_passe_hash VARCHAR(255) NOT NULL,
  role              ENUM('membre', 'admin') NOT NULL DEFAULT 'membre',
  created_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_utilisateurs_email (email)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Table des événements
-- Les colonnes booléennes décrivent l'accessibilité de chaque événement.
-- "publie" = 1 : visible par tous ; 0 : brouillon visible des administrateurs seulement.
-- ------------------------------------------------------------
CREATE TABLE evenements (
  id                INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  titre             VARCHAR(150)  NOT NULL,
  description       TEXT          NOT NULL,
  date_debut        DATETIME      NOT NULL,
  duree_minutes     SMALLINT UNSIGNED NOT NULL DEFAULT 60,
  lieu              VARCHAR(120)  NOT NULL,
  adresse           VARCHAR(200)  NOT NULL,
  categorie_id      INT UNSIGNED  NOT NULL,
  prix              DECIMAL(6,2)  NOT NULL DEFAULT 0.00,
  acces_pmr         TINYINT(1)    NOT NULL DEFAULT 0,
  lsf               TINYINT(1)    NOT NULL DEFAULT 0,
  boucle_magnetique TINYINT(1)    NOT NULL DEFAULT 0,
  audiodescription  TINYINT(1)    NOT NULL DEFAULT 0,
  infos_acces       TEXT          NULL,
  publie            TINYINT(1)    NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  KEY idx_evenements_date (date_debut),
  CONSTRAINT fk_evenements_categorie
    FOREIGN KEY (categorie_id) REFERENCES categories (id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Table des inscriptions (relation N-N entre utilisateurs et événements)
-- La clé primaire composée empêche de s'inscrire deux fois au même événement.
-- ON DELETE CASCADE : si l'événement ou le compte disparaît, l'inscription aussi.
-- ------------------------------------------------------------
CREATE TABLE inscriptions (
  utilisateur_id INT UNSIGNED NOT NULL,
  evenement_id   INT UNSIGNED NOT NULL,
  created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (utilisateur_id, evenement_id),
  KEY idx_inscriptions_evenement (evenement_id),
  CONSTRAINT fk_inscriptions_utilisateur
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs (id)
    ON DELETE CASCADE,
  CONSTRAINT fk_inscriptions_evenement
    FOREIGN KEY (evenement_id) REFERENCES evenements (id)
    ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Table des messages envoyés via le formulaire de contact
-- ------------------------------------------------------------
CREATE TABLE messages (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nom        VARCHAR(100) NOT NULL,
  email      VARCHAR(150) NOT NULL,
  sujet      VARCHAR(40)  NOT NULL,
  message    TEXT         NOT NULL,
  created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB;

-- ============================================================
-- Données d'exemple (lieux, événements et personnes fictifs)
-- ============================================================
INSERT INTO categories (id, nom) VALUES
  (1, 'Musique'),
  (2, 'Jeunesse'),
  (3, 'Ateliers'),
  (4, 'Théâtre'),
  (5, 'Expositions'),
  (6, 'Conférences'),
  (7, 'Cinéma');

-- Comptes de démonstration (mots de passe indiqués en haut du fichier)
INSERT INTO utilisateurs (id, nom, email, mot_de_passe_hash, role) VALUES
  (1, 'Administrateur', 'admin@exemple.fr',  '$2y$10$pzKZmtDxqYRH2vxY43aCkur/Qi1HnHljzwKi7wiFG1Xlp0vRLSgDq',  'admin'),
  (2, 'Camille Martin', 'membre@exemple.fr', '$2y$10$Ou0C/ougbcAJi2rdsG/V1uVSNTsWthEg13Xwt2A1GEoqy1mFVT4ty', 'membre');

INSERT INTO evenements
  (titre, description, date_debut, duree_minutes, lieu, adresse, categorie_id, prix,
   acces_pmr, lsf, boucle_magnetique, audiodescription, infos_acces)
VALUES
  ('Soirée jazz à la salle des fêtes',
   'Un quartet de jazz vous propose un répertoire de standards revisités. Buvette sur place avant et après le concert.',
   '2026-11-14 20:30:00', 120, 'Salle des fêtes', '12 rue des Tilleuls', 1, 8.00,
   1, 0, 1, 0,
   'Entrée de plain-pied. Quatre places réservées aux fauteuils roulants au premier rang. Toilettes adaptées au rez-de-chaussée. Boucle magnétique dans la salle.'),

  ('Lecture contée pour les enfants',
   'Un conteur et une interprète en langue des signes française racontent trois histoires pour les 4-8 ans. Un moment partagé, sensible et drôle.',
   '2026-11-18 15:00:00', 45, 'Médiathèque municipale', '3 place de la Mairie', 2, 0.00,
   1, 1, 1, 0,
   'Ascenseur accessible aux fauteuils roulants. Espace de lecture avec coussins et chaises. Chiens guides et chiens d''assistance acceptés.'),

  ('Atelier d''écriture et de poésie',
   'Venez écrire en petit groupe, sans prérequis. Les consignes sont proposées à l''oral et par écrit en gros caractères.',
   '2026-11-21 10:00:00', 150, 'Maison des associations', '8 avenue de la République', 3, 0.00,
   1, 0, 0, 0,
   'Salle au rez-de-chaussée, porte large, tables réglables en hauteur. Documents disponibles en gros caractères sur demande.'),

  ('Théâtre : Le Voyage de Léa',
   'Une pièce tout public sur le thème du départ et des retrouvailles. Cette représentation est audiodécrite et interprétée en langue des signes française.',
   '2026-11-27 20:00:00', 95, 'Théâtre du Parc', '1 allée du Parc', 4, 15.00,
   1, 1, 1, 1,
   'Parking réservé devant le théâtre. Casques d''audiodescription et boucle magnétique à retirer à l''accueil. Places en première rangée pour les personnes en LSF.'),

  ('Exposition de photographies : Regards de quartier',
   'Une trentaine de portraits et de scènes de vie réalisés par des habitants. Les légendes sont lisibles à hauteur de fauteuil roulant.',
   '2026-12-03 14:00:00', 240, 'Galerie municipale', '27 rue du Commerce', 5, 0.00,
   1, 0, 0, 0,
   'Parcours sans marche, éclairage non éblouissant, bancs répartis dans les salles.'),

  ('Conférence : le numérique pour tous',
   'Comment rendre les services en ligne utilisables par chacun ? Retours d''expérience et échanges avec le public. Support de présentation remis en version texte.',
   '2026-12-05 18:30:00', 90, 'Médiathèque municipale', '3 place de la Mairie', 6, 0.00,
   1, 1, 1, 0,
   'Ascenseur, salle équipée d''une boucle magnétique, transcription en direct projetée à l''écran.'),

  ('Concert de Noël de la chorale',
   'La chorale du quartier interprète des chants traditionnels et des compositions originales.',
   '2026-12-12 17:00:00', 75, 'Église Saint-Martin', 'Place Saint-Martin', 1, 5.00,
   0, 0, 0, 0,
   'Trois marches à l''entrée principale. Une rampe amovible est installée sur demande : merci de nous prévenir 48 h à l''avance via le formulaire de contact.'),

  ('Ciné-club : projection sous-titrée',
   'Projection d''un film français avec sous-titres pour sourds et malentendants, suivie d''un échange avec le public.',
   '2026-12-17 20:00:00', 110, 'Cinéma municipal', '5 rue de la Gare', 7, 6.50,
   1, 0, 0, 0,
   'Salle accessible de plain-pied, emplacements pour fauteuils roulants. Sous-titrage SME (sourds et malentendants) pendant tout le film.');

-- Un événement en brouillon (publie = 0) : visible uniquement dans l'espace administrateur
INSERT INTO evenements
  (titre, description, date_debut, duree_minutes, lieu, adresse, categorie_id, prix,
   acces_pmr, lsf, boucle_magnetique, audiodescription, infos_acces, publie)
VALUES
  ('Rencontre avec les auteurs du quartier',
   'Brouillon : programme à confirmer avec les intervenants avant publication.',
   '2027-01-16 16:00:00', 90, 'Médiathèque municipale', '3 place de la Mairie', 6, 0.00,
   1, 0, 1, 0,
   NULL, 0);

-- Le membre de démonstration est inscrit à deux événements
INSERT INTO inscriptions (utilisateur_id, evenement_id) VALUES
  (2, 2),
  (2, 4);
