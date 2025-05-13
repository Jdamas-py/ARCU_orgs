-- Create the database if it doesn't exist
CREATE DATABASE IF NOT EXISTS db_arcu;

-- Use the database
USE db_arcu;

-- Create the accounts table
CREATE TABLE IF NOT EXISTS acc (
    acc_id INT PRIMARY KEY,
    acc_pass VARCHAR(255) NOT NULL,
    acc_type ENUM('admin', 'staff', 'student') NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create events table
CREATE TABLE IF NOT EXISTS events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    eventname VARCHAR(255) NOT NULL,
    status SET('Scheduled', 'Ongoing', 'Completed', 'Cancelled') NOT NULL DEFAULT 'Scheduled',
    startdate DATE NOT NULL,
    enddate DATE NOT NULL,
    description TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create participants table
CREATE TABLE IF NOT EXISTS participants (
    participant_id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT,
    acc_id INT,
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('registered', 'attended', 'cancelled') DEFAULT 'registered',
    FOREIGN KEY (event_id) REFERENCES events(id),
    FOREIGN KEY (acc_id) REFERENCES acc(acc_id)
);

-- Create announcements table
CREATE TABLE IF NOT EXISTS announcements (
    announcement_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    content TEXT NOT NULL,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('active', 'archived') DEFAULT 'active',
    FOREIGN KEY (created_by) REFERENCES acc(acc_id)
);

-- Create gallery table
CREATE TABLE IF NOT EXISTS gallery (
    image_id INT AUTO_INCREMENT PRIMARY KEY,
    image_name VARCHAR(100) NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    description TEXT,
    uploaded_by INT,
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES acc(acc_id)
);

-- Insert default admin account
INSERT INTO acc (acc_id, acc_pass, acc_type, first_name, last_name, email) 
VALUES (12345, 'admin123', 'admin', 'Admin', 'User', 'admin@arcu.edu');

-- Insert sample events
INSERT INTO events (eventname, description, startdate, enddate)
VALUES 
('Art Exhibition Opening', 'Annual student art exhibition showcasing various artworks', '2024-04-15', '2024-04-15'),
('Cultural Dance Workshop', 'Traditional dance workshop for students', '2024-04-20', '2024-04-20');

-- Insert sample announcement
INSERT INTO announcements (title, content, created_by)
VALUES 
('Welcome to ARCU', 'Welcome to the Arts and Culture Unit! We have exciting events planned for this semester.', 12345); 