-- ==========================================================
-- Parliament of Uganda Cafeteria Management System
-- Database Version: 1.0
-- ==========================================================

DROP DATABASE IF EXISTS parliament_cafeteria;

CREATE DATABASE parliament_cafeteria;

USE parliament_cafeteria;

-- -- ==========================================================
-- -- USERS TABLE
-- -- ==========================================================

-- CREATE TABLE users (

--     id INT AUTO_INCREMENT PRIMARY KEY,

--     fullname VARCHAR(100) NOT NULL,

--     username VARCHAR(50) UNIQUE NOT NULL,

--     password VARCHAR(255) NOT NULL,

--     role VARCHAR(30) NOT NULL,

--     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP

-- );

-- -- ==========================================================
-- -- DEMO USERS
-- -- ==========================================================

-- INSERT INTO users
-- (fullname, username, password, role)

-- VALUES

-- (
-- 'System Administrator',
-- 'admin',
-- 'admin123',
-- 'Administrator'
-- ),

-- (
-- 'Sarah Nabachwa',
-- 'sarah.nabachwa',
-- 'password123',
-- 'Accountant'
-- );

-- -- ==========================================================
-- -- VERIFY DATA
-- -- ==========================================================

-- SELECT * FROM users;

-- =====================================================================
-- Parliament Cafeteria - Daily Menu module
-- Base tables below are exactly as defined in the ERD (parliament_cafeteria_erd).
-- Two additions were required to support the "Daily Menu Registry" screen
-- you shared, because the ERD as drawn doesn't carry this information:
--
--   1. FOOD_ITEMS has no meal_type (breakfast/lunch/dinner/drinks) or
--      in_stock flag, both of which the UI filters/toggles on.
--   2. CUSTOMER_CATEGORIES only stores ONE standard_price per category,
--      but the "Category Pricing Tier Matrix" on each card shows a
--      DIFFERENT price per (item, category) pair — e.g. a Rolex is
--      21,000 for an MP but a Luwombo is also 21,000, while the base
--      price of each item differs. That needs its own join table.
--
-- Everything else (naming, PK/FK style) matches the ERD as-is.
-- =====================================================================

CREATE TABLE IF NOT EXISTS departments (
    department_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
);

-- Sample departments (used by the "Parliament Department" dropdown)
INSERT INTO departments (name) VALUES
    ('ICT'),
    ('The Parliamentary Budget Office'),
    ('Department of the Clerks'),
    ('Department of Official Report (Hansard)'),
    ('Department of Research Services'),
    ('Department of Administration and Transport Logistics'),
    ('Department of Communication and Public Affairs'),
    ('Department of Corporate Planning and Strategy'),
    ('Department of Finance'),
    ('Department of Human Resource'),
    ('Department of Information and Communication Technology (ICT): Manages computers, networks, and internet.'),
    ('Department of Library Services'),
    ('Department of Sergeant-At-Arms'),
    ('Department of Legislative Services'),
    ('Department of Legal and Compliance Services'),
    ('Department of Corporate Planning and Strategy')
ON DUPLICATE KEY UPDATE name = VALUES(name);


CREATE TABLE IF NOT EXISTS customer_categories (
    category_id     INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100) NOT NULL,      -- e.g. Member of Parliament, Standard Staff...
    standard_price  DECIMAL(10,2) NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS daily_menus (
    menu_id     INT AUTO_INCREMENT PRIMARY KEY,
    menu_date   DATE NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS food_items (
    item_id     INT AUTO_INCREMENT PRIMARY KEY,
    menu_id     INT NOT NULL,
    name        VARCHAR(150) NOT NULL,
    meal_type   ENUM('breakfast','lunch','dinner','drinks') NOT NULL,  -- ADDED
    base_price  DECIMAL(10,2) NOT NULL,                                -- "Standard VIP" price on the card
    in_stock    TINYINT(1) NOT NULL DEFAULT 1,                         -- ADDED
    CONSTRAINT fk_fooditems_menu FOREIGN KEY (menu_id)
        REFERENCES daily_menus(menu_id) ON DELETE CASCADE
);

-- ADDED: the per-item, per-category price shown in the "Category Pricing Tier Matrix"
CREATE TABLE IF NOT EXISTS item_category_prices (
    price_id     INT AUTO_INCREMENT PRIMARY KEY,
    item_id      INT NOT NULL,
    category_id  INT NOT NULL,
    price        DECIMAL(10,2) NOT NULL,
    CONSTRAINT fk_icp_item FOREIGN KEY (item_id) REFERENCES food_items(item_id) ON DELETE CASCADE,
    CONSTRAINT fk_icp_category FOREIGN KEY (category_id) REFERENCES customer_categories(category_id) ON DELETE CASCADE,
    UNIQUE KEY uniq_item_category (item_id, category_id)
);

-- Sample data matching the screenshots -------------------------------------

INSERT INTO customer_categories (name, standard_price) VALUES
    ('Member of Parliament', 21000),
    ('Standard Staff', 13000),
    ('Intern Clerks', 13000),
    ('Invited Guests', 25000);

INSERT INTO daily_menus (menu_date) VALUES (CURDATE());
SET @menu_id = LAST_INSERT_ID();

INSERT INTO food_items (menu_id, name, meal_type, base_price, in_stock) VALUES
    (@menu_id, 'Katogo with Offals & Beans',        'breakfast', 12000, 1),
    (@menu_id, 'Ugandan Rolex (2 Eggs, Veggies)',    'breakfast', 8000,  1),
    (@menu_id, 'African Ginger Tea',                 'breakfast', 5000,  1),
    (@menu_id, 'Beef Samosa (Portion of 2)',         'breakfast', 6000,  1),
    (@menu_id, 'Beef Luwombo with Matooke & Rice',   'lunch',     21000, 1),
    (@menu_id, 'Chicken Luwombo with Mash',          'lunch',     23000, 1),
    (@menu_id, 'Traditional Matooke & Groundnut Sauce','lunch',  13000, 1),
    (@menu_id, 'Steamed Rice & Yellow Beans',        'lunch',     10000, 1),
    (@menu_id, 'Whole Fried Tilapia with Chips',     'dinner',    25000, 1),
    (@menu_id, 'Goat Muchomo with Steamed Cassava',  'dinner',    18000, 1),
    (@menu_id, 'Fresh Passion Fruit Juice',          'drinks',    4000,  1),
    (@menu_id, 'Bushera (Millet Drink)',             'drinks',    5000,  1);

-- Every item uses the same 4 tier prices in the screenshots, so seed all of them the same way.
INSERT INTO item_category_prices (item_id, category_id, price)
SELECT fi.item_id, cc.category_id, cc.standard_price
FROM food_items fi
CROSS JOIN customer_categories cc
WHERE fi.menu_id = @menu_id;