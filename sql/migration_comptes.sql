-- ============================================================
-- MIGRATION : ajoute les comptes utilisateurs, les inscriptions et la publication
-- à une base agenda_accessible DÉJÀ importée (version 1 du projet).
--
-- Elle CONSERVE vos données existantes (événements, messages).
-- À exécuter UNE SEULE FOIS (une 2e exécution provoquerait une erreur sans gravité :
-- la colonne "publie" existerait déjà).
--
-- Si vous préférez repartir de zéro, importez plutôt sql/schema.sql.
--
-- Comptes de démonstration créés (à changer avant toute mise en ligne) :
--   Administrateur : admin@exemple.fr   / Admin-Agenda-2026
--   Membre         : membre@exemple.fr  / Membre-Agenda-2026
-- ============================================================

USE agenda_accessible;

-- Ce fichier est en UTF-8, quel que soit le réglage du client
SET NAMES utf8mb4;

-- 1. Les événements existants deviennent "publiés" (publie = 1)
ALTER TABLE evenements
  ADD COLUMN publie TINYINT(1) NOT NULL DEFAULT 1 AFTER infos_acces;

-- 2. Table des utilisateurs
CREATE TABLE IF NOT EXISTS utilisateurs (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nom               VARCHAR(100) NOT NULL,
  email             VARCHAR(150) NOT NULL,
  mot_de_passe_hash VARCHAR(255) NOT NULL,
  role              ENUM('membre', 'admin') NOT NULL DEFAULT 'membre',
  created_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_utilisateurs_email (email)
) ENGINE=InnoDB;

-- 3. Table des inscriptions (utilisateurs <-> événements)
CREATE TABLE IF NOT EXISTS inscriptions (
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

-- 4. Comptes de démonstration (INSERT IGNORE : sans effet si l'adresse existe déjà)
INSERT IGNORE INTO utilisateurs (nom, email, mot_de_passe_hash, role) VALUES
  ('Administrateur', 'admin@exemple.fr',  '$2y$10$pzKZmtDxqYRH2vxY43aCkur/Qi1HnHljzwKi7wiFG1Xlp0vRLSgDq',  'admin'),
  ('Camille Martin', 'membre@exemple.fr', '$2y$10$Ou0C/ougbcAJi2rdsG/V1uVSNTsWthEg13Xwt2A1GEoqy1mFVT4ty', 'membre');
