-- ============================================================
-- PawCare: Pet Adoption and Foster Tracker
-- schema.sql — Database DDL and seed data
-- Main entity: PETS (with supporting SPECIES table from the ERD)
-- ============================================================

CREATE DATABASE IF NOT EXISTS pawcare_db
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE pawcare_db;

-- ------------------------------------------------------------
-- SPECIES (supporting lookup table, per ERD)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS SPECIES (
    species_id   INT AUTO_INCREMENT PRIMARY KEY,
    species_name VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- PETS (main entity for this exam)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS PETS (
    pet_id       INT AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(100) NOT NULL,
    breed        VARCHAR(100) NOT NULL,
    age          INT NOT NULL DEFAULT 0,
    gender       VARCHAR(20)  NOT NULL,
    intake_date  DATE         NOT NULL,
    status       VARCHAR(50)  NOT NULL DEFAULT 'Available',
    species_id   INT NOT NULL,
    CONSTRAINT fk_pets_species
        FOREIGN KEY (species_id) REFERENCES SPECIES(species_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Seed data
-- ------------------------------------------------------------
INSERT INTO SPECIES (species_name) VALUES
    ('Dog'),
    ('Cat');

INSERT INTO PETS (name, breed, age, gender, intake_date, status, species_id) VALUES
    ('Biscuit', 'Aspin Mix',          2, 'Male',   '2026-06-01', 'Available', 1),
    ('Luna',    'Domestic Shorthair', 1, 'Female', '2026-07-12', 'Fostered',  2),
    ('Max',     'Labrador Mix',       3, 'Male',   '2026-05-20', 'Adopted',   1),
    ('Coco',    'Persian',            4, 'Female', '2026-08-02', 'Available', 2);
