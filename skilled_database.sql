CREATE TABLE Utilisateur (
    id_utilisateur INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    mot_de_passe VARCHAR(255) NOT NULL,
    role ENUM('client', 'prestataire', 'admin') NOT NULL
);

CREATE TABLE Categories (
    id_categorie INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    icone TEXT,
    type ENUM('standard', 'emergency') DEFAULT 'standard'
);

CREATE TABLE Prestataire (
    id_prestataire INT AUTO_INCREMENT PRIMARY KEY,
    id_utilisateur INT NOT NULL,
    id_categorie INT NOT NULL,
    photo TEXT NOT NULL,
    specialite VARCHAR(100) NOT NULL,
    pays VARCHAR(100) NOT NULL,
    ville VARCHAR(100) NOT NULL,
    telephone VARCHAR(20) NOT NULL UNIQUE,
    tarif_journalier DECIMAL(10,2) NOT NULL,
    accepte_budget_global BOOLEAN NOT NULL,
    disponibilite TEXT NOT NULL,
    statut_disponibilite ENUM('available', 'unavailable') DEFAULT 'available' NOT NULL,
    FOREIGN KEY (id_utilisateur) REFERENCES Utilisateur(id_utilisateur),
    FOREIGN KEY (id_categorie) REFERENCES Categories(id_categorie)
);

CREATE TABLE Experience_prestataire (
    id_experience INT AUTO_INCREMENT PRIMARY KEY,
    id_prestataire INT NOT NULL,
    titre_experience VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    date_project YEAR,
    FOREIGN KEY (id_prestataire) REFERENCES Prestataire(id_prestataire)
);

CREATE TABLE Media_experience (
    id_media INT AUTO_INCREMENT PRIMARY KEY,
    id_experience INT NOT NULL,
    type_contenu ENUM('image', 'video') NOT NULL,
    chemin_fichier TEXT NOT NULL,
    description_media TEXT,
    FOREIGN KEY (id_experience) REFERENCES Experience_prestataire(id_experience)
);

CREATE TABLE Client (
    id_client INT AUTO_INCREMENT PRIMARY KEY,
    id_utilisateur INT NOT NULL,
    telephone VARCHAR(20) NOT NULL UNIQUE,
    FOREIGN KEY (id_utilisateur) REFERENCES Utilisateur(id_utilisateur)
);

CREATE TABLE Reservation (
    id_reservation INT AUTO_INCREMENT PRIMARY KEY,
    id_client INT NOT NULL,
    id_prestataire INT NOT NULL,
    description_service TEXT NOT NULL,
    budget_total DECIMAL(10,2),
    tarif_par_jour DECIMAL(10,2),
    date_debut DATE NOT NULL,
    date_fin DATE,
    nb_jours_estime INT NOT NULL,
    statut VARCHAR(50) NOT NULL,
    client_accepted_meeting BOOLEAN DEFAULT FALSE,
    artisan_accepted_meeting BOOLEAN DEFAULT FALSE,
    project_ended_client BOOLEAN DEFAULT FALSE,
    project_ended_artisan BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (id_client) REFERENCES Client(id_client),
    FOREIGN KEY (id_prestataire) REFERENCES Prestataire(id_prestataire),
    CHECK (
        (budget_total IS NULL AND tarif_par_jour IS NOT NULL)
        OR
        (budget_total IS NOT NULL AND tarif_par_jour IS NULL)
    )
);

CREATE TABLE Devis (
    id_devis INT AUTO_INCREMENT PRIMARY KEY,
    id_reservation INT NOT NULL,
    id_prestataire INT NOT NULL,
    date_debut_travaux DATE NOT NULL,
    cout_total DECIMAL(10,2) NOT NULL,
    tarif_journalier DECIMAL(10,2),
    acompte DECIMAL(10,2) NOT NULL,
    statut_devis ENUM('en_attente', 'accepté', 'refusé') DEFAULT 'en_attente' NOT NULL,
    FOREIGN KEY (id_reservation) REFERENCES Reservation(id_reservation),
    FOREIGN KEY (id_prestataire) REFERENCES Prestataire(id_prestataire)
);

CREATE TABLE Evaluation (
    id_evaluation INT AUTO_INCREMENT PRIMARY KEY,
    id_client INT NOT NULL,
    id_prestataire INT NOT NULL,
    note DECIMAL(2,1) NOT NULL CHECK (note >= 0 AND note <= 5),
    commentaire TEXT,
    date_evaluation DATE NOT NULL,
    FOREIGN KEY (id_client) REFERENCES Client(id_client),
    FOREIGN KEY (id_prestataire) REFERENCES Prestataire(id_prestataire)
);

CREATE TABLE Artisan_Availability (
    id_availability INT AUTO_INCREMENT PRIMARY KEY,
    id_prestataire INT NOT NULL,
    unavailable_date DATE NOT NULL,
    FOREIGN KEY (id_prestataire) REFERENCES Prestataire(id_prestataire),
    UNIQUE (id_prestataire, unavailable_date)
);

CREATE TABLE Admin (
    id_admin INT AUTO_INCREMENT PRIMARY KEY,
    id_utilisateur INT NOT NULL,
    FOREIGN KEY (id_utilisateur) REFERENCES Utilisateur(id_utilisateur)
);

CREATE TABLE Paiement (
    id_paiement INT AUTO_INCREMENT PRIMARY KEY,
    id_devis INT NOT NULL,
    id_client INT NOT NULL,
    id_prestataire INT NOT NULL,
    montant DECIMAL(10,2) NOT NULL,
    type_paiement ENUM('acompte', 'par_jour', 'global') NOT NULL,
    methode_paiement ENUM('stripe', 'paypal', 'virement') NOT NULL,
    date_paiement DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_fin_paiement DATE,
    statut_paiement ENUM('en_attente', 'effectué', 'échoué', 'annulé') NOT NULL,
    reference_transaction VARCHAR(255),
    FOREIGN KEY (id_devis) REFERENCES Devis(id_devis),
    FOREIGN KEY (id_client) REFERENCES Client(id_client),
    FOREIGN KEY (id_prestataire) REFERENCES Prestataire(id_prestataire)
);
