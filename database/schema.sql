-- =====================================================================
--  FindPoint — Lost & Found Platform
--  Database Schema (MySQL / MariaDB)
-- =====================================================================
--  HOW TO USE
--  1. Open phpMyAdmin (or the mysql CLI) on your local server (XAMPP/WAMP).
--  2. Create nothing manually — just import this whole file.
--     phpMyAdmin: Databases -> Import -> choose this file -> Go.
--     CLI:        mysql -u root -p < schema.sql
--  3. This file creates the database AND all the tables, so you don't
--     need to create the database separately first.
-- =====================================================================

CREATE DATABASE IF NOT EXISTS lost_and_found_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE lost_and_found_db;

-- ---------------------------------------------------------------------
-- 1. USERS
--    One table for everybody: members of the public AND institutions
--    (schools, churches, NGOs, police posts, offices, etc).
--    account_type tells the rest of the app how to treat the account.
-- ---------------------------------------------------------------------
CREATE TABLE users (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    account_type      ENUM('individual', 'institution') NOT NULL DEFAULT 'individual',
    full_name         VARCHAR(150) NOT NULL,        -- person's name OR institution's name
    institution_type  VARCHAR(100) DEFAULT NULL,    -- e.g. School, Church, NGO, Police Post (only used when account_type = institution)
    email             VARCHAR(150) NOT NULL UNIQUE,
    phone             VARCHAR(20)  DEFAULT NULL,
    password          VARCHAR(255) NOT NULL,        -- always stored hashed via password_hash()
    location          VARCHAR(150) DEFAULT NULL,
    photo             VARCHAR(255) DEFAULT NULL,    -- profile photo filename, stored in uploads/profiles/
    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 2. CATEGORIES
--    A simple lookup table so item categories stay consistent and are
--    easy to filter by on the Browse page.
-- ---------------------------------------------------------------------
CREATE TABLE categories (
    id    INT AUTO_INCREMENT PRIMARY KEY,
    name  VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB;

INSERT INTO categories (name) VALUES
    ('Mobile Phones'),
    ('Electronics & Gadgets'),
    ('Documents & Cards (ID, Passport, Certificates)'),
    ('Bags & Wallets'),
    ('Keys'),
    ('Jewelry & Watches'),
    ('Clothing & Accessories'),
    ('Books & Stationery'),
    ('Pets'),
    ('Other');

-- ---------------------------------------------------------------------
-- 3. ITEMS
--    Every lost report and every found report lives in this one table,
--    distinguished by item_type. status tracks its lifecycle:
--      open     -> still searching / unclaimed
--      claimed  -> a claim has been approved, arranging hand-over
--      resolved -> item has been physically returned, case closed
-- ---------------------------------------------------------------------
CREATE TABLE items (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    user_id       INT NOT NULL,                       -- who posted this report
    category_id   INT DEFAULT NULL,
    item_type     ENUM('lost', 'found') NOT NULL,
    title         VARCHAR(150) NOT NULL,
    description   TEXT NOT NULL,
    location      VARCHAR(150) NOT NULL,               -- where it was lost / found
    item_date     DATE NOT NULL,                        -- when it was lost / found
    photo         VARCHAR(255) DEFAULT NULL,            -- filename, stored in uploads/items/
    status        ENUM('open', 'claimed', 'resolved') NOT NULL DEFAULT 'open',
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX idx_type_status (item_type, status),
    INDEX idx_category (category_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 4. CLAIMS
--    A claim is someone saying "this is mine" (on a found item) or
--    "I found that" (on a lost item). The poster reviews and approves
--    or rejects it.
-- ---------------------------------------------------------------------
CREATE TABLE claims (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    item_id        INT NOT NULL,
    claimant_id    INT NOT NULL,                 -- the user making the claim
    proof_message  TEXT NOT NULL,                -- identifying details proving ownership / how they found it
    status         ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    FOREIGN KEY (claimant_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_item (item_id),
    INDEX idx_claimant (claimant_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 5. CONVERSATIONS
--    One conversation thread per (item, claimant) pair, so the poster
--    and the claimant can chat about a specific item.
-- ---------------------------------------------------------------------
CREATE TABLE conversations (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    item_id       INT NOT NULL,
    poster_id     INT NOT NULL,         -- the user who posted the item
    claimant_id   INT NOT NULL,         -- the user who claimed it
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_thread (item_id, claimant_id),
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    FOREIGN KEY (poster_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (claimant_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 6. MESSAGES
--    Individual chat messages inside a conversation.
--    NOTE: chat is fully free/unlimited for now. The next phase of this
--    project will add payment-gated messaging (see chat.php comments
--    for the exact hook point) — nothing payment-related lives here yet.
-- ---------------------------------------------------------------------
CREATE TABLE messages (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id  INT NOT NULL,
    sender_id        INT NOT NULL,
    message          TEXT NOT NULL,
    is_read          TINYINT(1) NOT NULL DEFAULT 0,
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_conversation (conversation_id)
) ENGINE=InnoDB;

-- =====================================================================
--  SAMPLE / DEMO DATA
--  Just enough so the site isn't empty the first time you open it.
--  Demo login for both accounts below: password is  Password123
--  Feel free to delete everything in this section for a real client.
-- =====================================================================
INSERT INTO users (account_type, full_name, institution_type, email, phone, password, location) VALUES
('individual', 'Wanjiku Kamau', NULL, 'wanjiku.demo@example.com', '0712345678', '$2y$10$g.0ZcFfxq0F04fbwk1nan..fAp.6qgTA22i5yCFRseLMbTk5tdqxW', 'Wote, Makueni'),
('institution', 'ACK Jericho Church', 'Church', 'ackjericho.demo@example.com', '0723456789', '$2y$10$g.0ZcFfxq0F04fbwk1nan..fAp.6qgTA22i5yCFRseLMbTk5tdqxW', 'Jericho, Nairobi');

INSERT INTO items (user_id, category_id, item_type, title, description, location, item_date, status) VALUES
(1, 1, 'lost', 'Black Samsung phone, cracked screen corner', 'Lost near Wote bus stage in the evening. Has a blue cover and a small crack on the top right corner of the screen. Lock screen wallpaper is a sunset photo.', 'Wote town, Makueni', '2026-06-10', 'open'),
(2, 3, 'found', 'National ID card found at church compound', 'Found a national ID card on the ground near the main gate after Sunday service. Name on the card starts with "K". Holding it at the church office.', 'ACK Jericho, Nairobi', '2026-06-14', 'open'),
(1, 4, 'lost', 'Brown leather wallet', 'Misplaced a brown leather wallet, contains a driving license and a few cards. Reward offered.', 'Makueni County offices', '2026-06-08', 'open'),
(2, 5, 'found', 'Bunch of keys with a red keyholder', 'Found a bunch of about 5 keys with a red rubber keyholder shaped like a heart, left on a pew.', 'ACK Jericho, Nairobi', '2026-06-15', 'open');
