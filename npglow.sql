CREATE DATABASE IF NOT EXISTS npglow;
USE npglow;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    google_id VARCHAR(255) DEFAULT NULL,
    profile_photo VARCHAR(500) DEFAULT NULL,
    password VARCHAR(255) DEFAULT NULL,
    has_purchased BOOLEAN DEFAULT FALSE,
    role ENUM('user', 'admin', 'expert') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    image_url VARCHAR(255)
);

CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'completed') DEFAULT 'pending',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS chats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    sender ENUM('user', 'admin', 'expert') NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS user_face_photos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    photo_path VARCHAR(500) NOT NULL,
    photo_type ENUM('initial', 'progress') DEFAULT 'initial',
    notes TEXT,
    taken_at DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS consultation_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    expert_id INT DEFAULT NULL,
    face_photo_id INT DEFAULT NULL,
    summary TEXT,
    skin_condition VARCHAR(255),
    recommendation TEXT,
    consultation_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (expert_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (face_photo_id) REFERENCES user_face_photos(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS ai_chats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    sender ENUM('user', 'ai') NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Add online status tracking to users (for expert availability)
ALTER TABLE users ADD COLUMN is_online BOOLEAN DEFAULT FALSE;
ALTER TABLE users ADD COLUMN last_active TIMESTAMP NULL;

-- Insert dummy admin
-- Password is 'password'
INSERT IGNORE INTO users (name, email, password, role) VALUES ('Admin NPGLOW', 'admin@npglow.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Insert dummy expert
INSERT IGNORE INTO users (name, email, password, role) VALUES ('Tim Ahli NPGLOW', 'expert@npglow.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'expert');

-- Insert dummy products
INSERT IGNORE INTO products (id, name, description, price, image_url) VALUES (1, 'Acne Glow Package', 'Paket perawatan wajah untuk kulit berjerawat.', 150000.00, 'assets/images/product-acne.jpg');
INSERT IGNORE INTO products (id, name, description, price, image_url) VALUES (2, 'Whitening Glow Package', 'Paket perawatan wajah untuk mencerahkan kulit.', 175000.00, 'assets/images/product-whitening.jpg');
