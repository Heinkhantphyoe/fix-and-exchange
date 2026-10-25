-- Fix and Exchange - database setup.
-- IF NOT EXISTS keeps existing tables and data. It does not update old tables.
-- Status and type fields use plain text. Check the listed values in PHP forms.
CREATE DATABASE IF NOT EXISTS fix_and_exchange
    CHARACTER SET utf8mb4;

USE fix_and_exchange;

-- Accounts: Admin or Student.
-- Students can buy, sell and request repairs.
-- Approved students can also volunteer for repairs.
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'Student',
    -- Account status: Active, Suspended, Disabled.
    account_status VARCHAR(20) NOT NULL DEFAULT 'Active',
    total_points INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tp_number VARCHAR(20) NOT NULL UNIQUE,
    phone_number VARCHAR(30),
    intake_code VARCHAR(50),
    academic_program VARCHAR(100)
);

-- Categories for item listings and repair requests.
CREATE TABLE IF NOT EXISTS categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL,
    description TEXT
);

-- Repair volunteer applications and admin approval.
CREATE TABLE IF NOT EXISTS volunteer_profiles (
    volunteer_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    skills_description TEXT NOT NULL,
    -- Approval status: Pending, Approved, Rejected.
    approval_status VARCHAR(20) NOT NULL DEFAULT 'Pending',
    approved_by INT,
    approved_at DATETIME,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (approved_by) REFERENCES users(user_id)
);

-- Second-hand items posted by students.
CREATE TABLE IF NOT EXISTS item_listings (
    listing_id INT AUTO_INCREMENT PRIMARY KEY,
    seller_id INT NOT NULL,
    category_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    -- Price can be NULL for an exchange, or 0 for a giveaway.
    price DECIMAL(10, 2),
    -- Listing type: Sale, Exchange, Giveaway.
    listing_type VARCHAR(20) NOT NULL,
    -- Listing status: Available, Pending, Completed, Removed.
    status VARCHAR(20) NOT NULL DEFAULT 'Available',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id) REFERENCES users(user_id),
    FOREIGN KEY (category_id) REFERENCES categories(category_id)
);

-- Image file paths for each listing.
CREATE TABLE IF NOT EXISTS item_images (
    image_id INT AUTO_INCREMENT PRIMARY KEY,
    listing_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    FOREIGN KEY (listing_id) REFERENCES item_listings(listing_id)
);

-- Requests to buy, trade or claim a giveaway.
CREATE TABLE IF NOT EXISTS exchange_offers (
    offer_id INT AUTO_INCREMENT PRIMARY KEY,
    listing_id INT NOT NULL,
    buyer_id INT NOT NULL,
    -- Offer type: Buy, Trade, Claim Giveaway.
    offer_type VARCHAR(20) NOT NULL,
    offered_item_details TEXT,
    -- Offer status: Pending, Accepted, Rejected, Cancelled.
    status VARCHAR(20) NOT NULL DEFAULT 'Pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (listing_id) REFERENCES item_listings(listing_id),
    FOREIGN KEY (buyer_id) REFERENCES users(user_id)
);

-- Damaged items submitted for repair.
CREATE TABLE IF NOT EXISTS repair_requests (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    requester_id INT NOT NULL,
    category_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    problem_description TEXT NOT NULL,
    -- Request status: Open, Verified, In Progress, Completed, Cancelled.
    status VARCHAR(20) NOT NULL DEFAULT 'Open',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (requester_id) REFERENCES users(user_id),
    FOREIGN KEY (category_id) REFERENCES categories(category_id)
);

-- Image file paths for each repair request.
CREATE TABLE IF NOT EXISTS repair_images (
    repair_image_id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    FOREIGN KEY (request_id) REFERENCES repair_requests(request_id)
);

-- Jobs accepted by repair volunteers.
CREATE TABLE IF NOT EXISTS repair_assignments (
    assignment_id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    volunteer_id INT NOT NULL,
    assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    -- Progress status: Accepted, Diagnosing, Repairing, Completed.
    progress_status VARCHAR(20) NOT NULL DEFAULT 'Accepted',
    completion_notes TEXT,
    completed_at DATETIME,
    points_value INT NOT NULL DEFAULT 0,
    FOREIGN KEY (request_id) REFERENCES repair_requests(request_id),
    FOREIGN KEY (volunteer_id) REFERENCES volunteer_profiles(volunteer_id)
);

-- Conversations about listings or repair requests.
CREATE TABLE IF NOT EXISTS conversation (
    conversation_id INT AUTO_INCREMENT PRIMARY KEY,
    seller_id INT NOT NULL,
    buyer_id INT NOT NULL,
    listing_id INT,
    -- Context type: Listing, Repair.
    context_type VARCHAR(20) NOT NULL,
    -- The listing ID or repair request ID. Check this ID in PHP.
    context_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id) REFERENCES users(user_id),
    FOREIGN KEY (buyer_id) REFERENCES users(user_id),
    FOREIGN KEY (listing_id) REFERENCES item_listings(listing_id)
);

-- Messages sent in a conversation.
CREATE TABLE IF NOT EXISTS messages (
    message_id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    conversation_id INT NOT NULL,
    message_text TEXT NOT NULL,
    sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(user_id),
    FOREIGN KEY (receiver_id) REFERENCES users(user_id),
    FOREIGN KEY (conversation_id) REFERENCES conversation(conversation_id)
);

-- Listing reports reviewed by an admin.
CREATE TABLE IF NOT EXISTS reported_issues (
    report_id INT AUTO_INCREMENT PRIMARY KEY,
    reporter_id INT NOT NULL,
    listing_id INT NOT NULL,
    reason TEXT NOT NULL,
    -- Report status: Pending, Investigating, Resolved, Dismissed.
    status VARCHAR(20) NOT NULL DEFAULT 'Pending',
    handled_by INT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at DATETIME,
    FOREIGN KEY (reporter_id) REFERENCES users(user_id),
    FOREIGN KEY (listing_id) REFERENCES item_listings(listing_id),
    FOREIGN KEY (handled_by) REFERENCES users(user_id)
);

-- Points history for the sustainability tracker and leaderboard.
CREATE TABLE IF NOT EXISTS points_transactions (
    transaction_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    points_awarded INT NOT NULL,
    -- Source type: Sale, Giveaway, Exchange, Repair.
    source_type VARCHAR(20) NOT NULL,
    -- The offer ID or repair assignment ID.
    reference_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);
