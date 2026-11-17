-- ==============================================================================
-- Fix & Exchange - Comprehensive Presentation & Demonstration Sample Data
-- Database: fix_and_exchange
-- All accounts use password: Password123!
-- ==============================================================================

USE fix_and_exchange;

-- Temporarily disable foreign key constraints for a clean reset
SET FOREIGN_KEY_CHECKS = 0;

DELETE FROM messages;
DELETE FROM conversation;
DELETE FROM reported_issues;
DELETE FROM points_transactions;
DELETE FROM repair_images;
DELETE FROM repair_assignments;
DELETE FROM repair_requests;
DELETE FROM exchange_offers;
DELETE FROM item_images;
DELETE FROM item_listings;
DELETE FROM volunteer_profiles;
DELETE FROM categories;
DELETE FROM users;

ALTER TABLE messages AUTO_INCREMENT = 1;
ALTER TABLE conversation AUTO_INCREMENT = 1;
ALTER TABLE reported_issues AUTO_INCREMENT = 1;
ALTER TABLE points_transactions AUTO_INCREMENT = 1;
ALTER TABLE repair_images AUTO_INCREMENT = 1;
ALTER TABLE repair_assignments AUTO_INCREMENT = 1;
ALTER TABLE repair_requests AUTO_INCREMENT = 1;
ALTER TABLE exchange_offers AUTO_INCREMENT = 1;
ALTER TABLE item_images AUTO_INCREMENT = 1;
ALTER TABLE item_listings AUTO_INCREMENT = 1;
ALTER TABLE volunteer_profiles AUTO_INCREMENT = 1;
ALTER TABLE categories AUTO_INCREMENT = 1;
ALTER TABLE users AUTO_INCREMENT = 1;

SET FOREIGN_KEY_CHECKS = 1;

-- ==============================================================================
-- 1. CATEGORIES
-- ==============================================================================
INSERT INTO categories (category_id, category_name, description) VALUES
(1, 'Textbooks & Study Materials', 'Academic textbooks, revision notes, lab manuals, and stationary.'),
(2, 'Laptops & Computing', 'Laptops, keyboards, monitors, chargers, components, and accessories.'),
(3, 'Phones & Tablets', 'Smartphones, iPads, tablets, styluses, and screen protectors.'),
(4, 'Audio & Sound', 'Headphones, earphones, bluetooth speakers, and microphones.'),
(5, 'Dorm Living & Appliances', 'Kettles, desk fans, study lamps, organizers, and mini appliances.'),
(6, 'Sports & Campus Gear', 'Badminton rackets, sports shoes, gym equipment, and umbrellas.');

-- ==============================================================================
-- 2. USERS
-- All accounts have password: Password123!
-- Bcrypt Hash: $2y$12$E9yOpMA9KX7o6vLdet0RHe8uEbqUbKSFQDNztNxFOW7GRFGNM6Toe
-- ==============================================================================
INSERT INTO users (user_id, full_name, email, password_hash, role, account_status, total_points, tp_number, phone_number, intake_code, academic_program, created_at) VALUES
-- 1. Campus Administrator
(1, 'Dr. Sarah Tan (Campus Admin)', 'admin@apu.edu.my', '$2y$12$E9yOpMA9KX7o6vLdet0RHe8uEbqUbKSFQDNztNxFOW7GRFGNM6Toe', 'Admin', 'Active', 0, 'TP000001', '+60 12-888 1234', 'STAFF-ADMIN', 'APU Student Services & Sustainability Centre', '2026-08-01 09:00:00'),

-- 2. Student Leader & Top Volunteer (Planet Hero - 🥇 Rank 1)
(2, 'Alex Lee Chen Wei', 'alex.lee@mail.apu.edu.my', '$2y$12$E9yOpMA9KX7o6vLdet0RHe8uEbqUbKSFQDNztNxFOW7GRFGNM6Toe', 'Student', 'Active', 255, 'TP054321', '+60 12-345 6789', 'UC3F2311SE', 'BSc (Hons) in Software Engineering', '2026-08-15 10:30:00'),

-- 3. Student & Active Volunteer (Eco Guardian - 🥈 Rank 2)
(3, 'Priya Kumar', 'priya.kumar@mail.apu.edu.my', '$2y$12$E9yOpMA9KX7o6vLdet0RHe8uEbqUbKSFQDNztNxFOW7GRFGNM6Toe', 'Student', 'Active', 160, 'TP062145', '+60 13-987 6543', 'UC2F2405CS', 'BSc (Hons) in Computer Science', '2026-09-01 11:20:00'),

-- 4. Student & Pending Volunteer Applicant (Green Contributor)
(4, 'Marcus Wong', 'marcus.wong@mail.apu.edu.my', '$2y$12$E9yOpMA9KX7o6vLdet0RHe8uEbqUbKSFQDNztNxFOW7GRFGNM6Toe', 'Student', 'Active', 75, 'TP071234', '+60 16-222 3344', 'UC1F2502IT', 'Diploma in Information Technology', '2026-09-10 14:15:00'),

-- 5. Student & Rejected Volunteer Applicant (Eco Starter)
(5, 'Aisha Nor', 'aisha.nor@mail.apu.edu.my', '$2y$12$E9yOpMA9KX7o6vLdet0RHe8uEbqUbKSFQDNztNxFOW7GRFGNM6Toe', 'Student', 'Active', 20, 'TP078901', '+60 17-555 8899', 'UC2F2408BA', 'BA (Hons) in Business Management', '2026-09-20 16:45:00'),

-- 6. Student Requester & Seller (Green Contributor)
(6, 'Kevin Tan', 'kevin.tan@mail.apu.edu.my', '$2y$12$E9yOpMA9KX7o6vLdet0RHe8uEbqUbKSFQDNztNxFOW7GRFGNM6Toe', 'Student', 'Active', 95, 'TP065432', '+60 14-333 7788', 'UC3F2311CS', 'BSc (Hons) in Computer Science', '2026-09-25 13:00:00'),

-- 7. Student Eco Champion (Green Contributor - 🥉 Rank 3)
(7, 'Fatimah Zahra', 'fatimah.zahra@mail.apu.edu.my', '$2y$12$E9yOpMA9KX7o6vLdet0RHe8uEbqUbKSFQDNztNxFOW7GRFGNM6Toe', 'Student', 'Active', 120, 'TP068899', '+60 11-444 9900', 'UC2F2405SE', 'BSc (Hons) in Software Engineering', '2026-10-01 09:40:00'),

-- 8. Suspended Account (For Admin Moderation Demo)
(8, 'Daniel Cheong', 'daniel.cheong@mail.apu.edu.my', '$2y$12$E9yOpMA9KX7o6vLdet0RHe8uEbqUbKSFQDNztNxFOW7GRFGNM6Toe', 'Student', 'Suspended', 0, 'TP049988', '+60 19-777 0011', 'UC3F2208CS', 'BSc (Hons) in Cyber Security', '2026-10-05 15:20:00');

-- ==============================================================================
-- 3. VOLUNTEER PROFILES
-- Demonstrates: Approved, Pending (for live approval demo), and Rejected status
-- ==============================================================================
INSERT INTO volunteer_profiles (volunteer_id, user_id, skills_description, approval_status, approved_by, approved_at) VALUES
-- Approved Volunteer 1 (Alex Lee)
(1, 2, 'Soldering, laptop heatsink cleaning & thermal repasting, LCD screen replacement, keyboard repair, Arduino and IoT circuit diagnostics.', 'Approved', 1, '2026-09-15 10:30:00'),

-- Approved Volunteer 2 (Priya Kumar)
(2, 3, 'Smartphone battery replacement, mechanical keyboard switch lubing/desoldering, textbook spine rebinding, audio cable jack repairs.', 'Approved', 1, '2026-10-01 14:00:00'),

-- Pending Volunteer Applicant (Marcus Wong) -> LIVE DEMO TARGET FOR ADMIN APPROVAL
(3, 4, 'Experience fixing desktop computers, upgrading SSDs and RAM, basic multimeter testing for small dorm appliances.', 'Pending', NULL, NULL),

-- Rejected Volunteer (Aisha Nor) -> Shows rejection reason handling & re-apply flow
(4, 5, 'Campus sustainability enthusiast. Eager to help organize tool kits and manage student intake forms.', 'Rejected', 1, '2026-10-20 16:45:00');

-- ==============================================================================
-- 4. ITEM LISTINGS
-- Marketplace items in Available, Pending, Completed, and Removed states
-- ==============================================================================
INSERT INTO item_listings (listing_id, seller_id, category_id, title, description, price, listing_type, status, created_at) VALUES
-- 1. Available Sale
(1, 6, 1, 'Casio fx-570EX ClassWiz Scientific Calculator', 'Original APU approved model. Excellent condition, solar cell and battery working perfectly. Comes with hard cover.', 65.00, 'Sale', 'Available', '2026-11-01 09:30:00'),

-- 2. Available Exchange
(2, 2, 1, 'Data Structures & Algorithms in Java (4th Edition)', 'Looking to trade with Clean Code by Robert C. Martin or any Software Architecture book. No missing pages or scribbles.', NULL, 'Exchange', 'Available', '2026-11-02 11:15:00'),

-- 3. Available Sale
(3, 3, 2, 'Keychron K2 Wireless Mechanical Keyboard (Gateron Brown)', '75% layout, RGB backlight, Mac and Windows keycaps included. Works via Bluetooth and USB-C. Battery health is great.', 185.00, 'Sale', 'Available', '2026-11-03 14:20:00'),

-- 4. Available Giveaway
(4, 7, 1, 'APU Official Lanyard + Semester Exam Clear Folder Set', 'Graduating this semester, giving away unused APU lanyard with safety clip and 3 clear examination document folders.', 0.00, 'Giveaway', 'Available', '2026-11-04 16:00:00'),

-- 5. Available Sale
(5, 6, 5, 'Xiaomi Mijia LED Smart Desk Lamp 1S', 'Stepless brightness and color temperature adjustment via knob. Suitable for night study sessions. Power adapter included.', 55.00, 'Sale', 'Available', '2026-11-05 10:45:00'),

-- 6. Pending Sale with Report (Demonstrates Admin Reports & Offer Review)
(6, 8, 4, 'Sony WH-1000XM4 Wireless Noise Cancelling Headphones', 'Black color, comes with original hard case and airline adapter. Active noise cancelling works like a charm.', 450.00, 'Sale', 'Pending', '2026-11-06 13:00:00'),

-- 7. Completed Sale (Demonstrates Past Transaction & Points Awarded)
(7, 2, 2, 'Logitech MX Master 3 Ergonomic Mouse', 'Dark grey, USB-C fast charging, connects to 3 devices. Handed over successfully to student on campus.', 160.00, 'Sale', 'Completed', '2026-10-18 12:00:00'),

-- 8. Completed Giveaway (Demonstrates Campus Circular Economy & Free Sharing)
(8, 7, 1, 'Discrete Mathematics & Its Applications (Kenneth Rosen)', 'Hardcover edition. Passed down to first year student for zero cost to support APU circular campus reuse.', 0.00, 'Giveaway', 'Completed', '2026-10-25 15:30:00'),

-- 9. Available Exchange
(9, 4, 6, 'Yonex Nanoray 10F Badminton Racket + 3 RSL Shuttles', 'Strung at 24 lbs. Will exchange for any tennis racket or gym resistance band set.', NULL, 'Exchange', 'Available', '2026-11-07 17:30:00'),

-- 10. Available Sale
(10, 5, 5, 'Tefal Mini Electric Rice Cooker (1.2L)', 'Compact size perfect for on-campus student hostel. Non-stick pot is clean, keeps food warm for hours.', 45.00, 'Sale', 'Available', '2026-11-08 19:00:00'),

-- 11. Available Sale
(11, 6, 2, 'Dell SE2422H 24-inch Full HD Monitor (75Hz)', 'VGA and HDMI ports, thin bezel, tilt stand. Ideal for dual-monitor coding setup.', 220.00, 'Sale', 'Available', '2026-11-09 11:10:00'),

-- 12. Removed Listing (Demonstrates Admin Moderation & Takedown)
(12, 8, 4, 'Brand New Unopened Apple AirPods Pro 2 (Rep)', 'High quality 1:1 replica with active noise cancel logo. Great budget alternative.', 99.00, 'Sale', 'Removed', '2026-11-07 08:00:00'),

-- 13. Available Sale
(13, 2, 3, 'iPad 9th Gen 64GB Space Grey (Wi-Fi)', 'Screen in immaculate condition with paper-like matte screen protector applied. Includes magnetic trifold smart case and original 20W USB-C charger.', 850.00, 'Sale', 'Available', '2026-11-08 12:00:00'),

-- 14. Available Sale
(14, 6, 2, 'Logitech C920 Pro HD Webcam (1080p)', 'Full HD 1080p video with stereo audio and privacy shutter. Used for online tutorials and interviews during semester 1.', 130.00, 'Sale', 'Available', '2026-11-08 15:30:00'),

-- 15. Available Exchange
(15, 3, 1, 'Operating System Concepts (10th Dinosaur Edition)', 'The classic APU Operating Systems coursebook. Looking to trade with Computer Networking: A Top-Down Approach (Kurose & Ross).', NULL, 'Exchange', 'Available', '2026-11-09 10:15:00'),

-- 16. Available Sale
(16, 7, 5, 'KDK 16-inch Stand Fan (3-Speed with Timer)', 'Sturdy 3-speed pedestal stand fan. Runs whisper-quiet with strong airflow for campus hostel living. Selling due to graduation.', 60.00, 'Sale', 'Available', '2026-11-09 13:40:00'),

-- 17. Available Sale
(17, 4, 3, 'Anker 65W GaN Fast Wall Charger (Dual USB-C + USB-A)', 'Compact dual USB-C and USB-A wall charger. Powers MacBook Air, Dell XPS, iPhone and Android phones at full speed.', 75.00, 'Sale', 'Available', '2026-11-09 16:00:00'),

-- 18. Available Sale
(18, 2, 4, 'JBL Flip 5 Waterproof Portable Bluetooth Speaker', 'Squad camouflage edition. IPX7 waterproof with 12 hours playtime. Great for campus outdoor activities and gym sessions.', 190.00, 'Sale', 'Available', '2026-11-10 11:00:00'),

-- 19. Available Giveaway
(19, 4, 6, 'Spalding NBA Street Outdoor Basketball (Size 7)', 'High-durability outdoor rubber basketball with inflation needle and mini pump. Free to any APU student who plays at the campus court.', 0.00, 'Giveaway', 'Available', '2026-11-10 14:20:00'),

-- 20. Available Sale
(20, 5, 2, 'Baseus Aluminium Ergonomic Adjustable Laptop Stand', 'Foldable ergonomic laptop riser with silicone anti-slip pads and cable cutout. Compatible with 11-inch to 16-inch laptops.', 35.00, 'Sale', 'Available', '2026-11-10 17:00:00'),

-- 21. Available Exchange
(21, 6, 2, 'Arduino Uno R3 Starter Kit with 30+ Sensors & Breadboard', 'Includes Uno board, breadboard, LCD 1602, ultrasonic sensor, stepper motor, and 65 jumper wires. Will trade for a Raspberry Pi Zero or USB logic analyzer.', NULL, 'Exchange', 'Available', '2026-11-11 09:30:00'),

-- 22. Available Sale
(22, 3, 5, 'Philips Dry Iron (1000W Classic Lightweight)', 'Non-stick soleplate with temperature control dial. Lightweight and reliable for ironing formal presentation shirts.', 30.00, 'Sale', 'Available', '2026-11-11 12:15:00'),

-- 23. Available Giveaway
(23, 7, 1, 'College Ruled Spiral Notebooks & Highlighters Set (Unused)', 'Surplus A4 wire-bound lined notebooks from semester start. Free for any junior or coursemate.', 0.00, 'Giveaway', 'Available', '2026-11-11 15:00:00'),

-- 24. Available Sale
(24, 5, 6, 'Decathlon Quechua 20L Campus Daypack Backpack', 'Navy blue, padded back and shoulder straps, dual water bottle mesh pockets and laptop sleeve.', 25.00, 'Sale', 'Available', '2026-11-11 18:30:00');

-- ==============================================================================
-- 5. ITEM IMAGES
-- Points to images inside assets/images/
-- ==============================================================================
INSERT INTO item_images (image_id, listing_id, image_path) VALUES
(1, 1, 'assets/images/calculator.jpg'),
(2, 2, 'assets/images/java-book.jpg'),
(3, 3, 'assets/images/keyboard.jpg'),
(4, 4, 'assets/images/lanyard.jpg'),
(5, 5, 'assets/images/lamp.jpg'),
(6, 6, 'assets/images/headphones.jpg'),
(7, 7, 'assets/images/mouse.jpg'),
(8, 8, 'assets/images/math-book.jpg'),
(9, 9, 'assets/images/badminton.jpg'),
(10, 10, 'assets/images/rice-cooker.jpg'),
(11, 11, 'assets/images/dell.jpg'),
(12, 12, 'assets/images/airpods.jpg'),
(13, 13, 'assets/images/ipad.jpg'),
(14, 14, 'assets/images/webcam.jpg'),
(15, 15, 'assets/images/os-book.jpg'),
(16, 16, 'assets/images/stand-fan.jpg'),
(17, 17, 'assets/images/charger.jpg'),
(18, 18, 'assets/images/jbl-speaker.jpg'),
(19, 19, 'assets/images/basketball.jpg'),
(20, 20, 'assets/images/laptop-stand.jpg'),
(21, 21, 'assets/images/arduino-kit.jpg'),
(22, 22, 'assets/images/iron.jpg'),
(23, 23, 'assets/images/notebooks.jpg'),
(24, 24, 'assets/images/backpack.jpg');

-- ==============================================================================
-- 6. EXCHANGE OFFERS
-- Demonstrates Buy, Trade, and Claim Giveaway offers in various statuses
-- ==============================================================================
INSERT INTO exchange_offers (offer_id, listing_id, buyer_id, offer_type, offered_item_details, status, created_at) VALUES
-- Pending buy offer for Casio calculator (Can be accepted/rejected live by Kevin)
(1, 1, 4, 'Buy', 'Willing to pay full RM 65 asking price. Can meet today at Level 4 cafe.', 'Pending', '2026-11-09 14:00:00'),

-- Pending trade offer for Java Algorithms textbook (Can be traded live by Alex)
(2, 2, 6, 'Trade', 'Trading with Clean Code by Robert C. Martin in near-mint condition.', 'Pending', '2026-11-09 16:30:00'),

-- Accepted claim giveaway offer for Lanyard
(3, 4, 5, 'Claim Giveaway', 'First year student, would love to claim this! Can collect anytime at APU library.', 'Accepted', '2026-11-08 10:15:00'),

-- Pending buy offer for Sony XM4 (linked to reported issue)
(4, 6, 7, 'Buy', 'Offering RM 420. Can pay cash upon testing audio and bluetooth pairing.', 'Pending', '2026-11-09 17:00:00'),

-- Accepted buy offer for Logitech MX Master mouse (Completed transaction)
(5, 7, 7, 'Buy', 'Paid RM 160 cash. Handover completed at campus foyer.', 'Accepted', '2026-10-19 14:00:00'),

-- Accepted claim offer for Discrete Mathematics book (Completed transaction)
(6, 8, 2, 'Claim Giveaway', 'Claimed for juniors in APU Google Developer Student Club.', 'Accepted', '2026-10-26 11:00:00');

-- ==============================================================================
-- 7. REPAIR REQUESTS
-- Covers all repair statuses: Open, Verified, In Progress, Completed, Cancelled
-- ==============================================================================
INSERT INTO repair_requests (request_id, requester_id, category_id, title, problem_description, status, created_at) VALUES
-- 1. In Progress Repair (Alex Lee working on it)
(1, 6, 2, 'ASUS ROG Strix G15 - Extreme Fan Noise & Thermal Throttling', 'Left cooling fan makes a loud buzzing vibration and CPU reaches 95C within 10 minutes of running Android Studio. Needs dust removal, fan lubrication or bearing repaste.', 'In Progress', '2026-11-05 09:30:00'),

-- 2. In Progress Repair (Priya Kumar working on it)
(2, 5, 3, 'iPhone 11 - Cracked Front Glass & Ghost Touch', 'Fell off classroom table. Screen displays green lines and touches spontaneously on the upper right corner. Replacement LCD panel already purchased from Shopee, need experienced volunteer to install.', 'In Progress', '2026-11-06 12:45:00'),

-- 3. Open Repair Request -> LIVE DEMO TARGET FOR VOLUNTEER ACCEPT JOB!
(3, 7, 5, 'Philips Dorm Electric Kettle (1.7L) - Fails to Heat Up', 'Indicator light illuminates when flipped on, but base plate does not transfer heat to boil water. Suspect bimetal thermostat contact or blown thermal fuse.', 'Open', '2026-11-10 15:00:00'),

-- 4. Verified Repair Request (Admin verified, awaiting volunteer)
(4, 4, 4, 'Sony WH-CH510 Bluetooth Headphones - Broken Headband Hinge', 'Right side folding swivel joint cracked. Drivers and bluetooth connection work fine. Needs plastic adhesive welding or 3D-printed reinforcement clip.', 'Verified', '2026-11-08 14:20:00'),

-- 5. Completed Repair Request (Shows successful fix & points awarded)
(5, 8, 2, 'Royal Kludge RK61 Mechanical Keyboard - Stuttering Spacebar', 'Spacebar key registers erratic double spaces or drops input when typing fast. Non-hotswap PCB requires desoldering and soldering a replacement switch.', 'Completed', '2026-10-28 11:00:00'),

-- 6. Cancelled Repair Request (Requester decided to upgrade)
(6, 6, 2, 'MacBook Pro 13-inch 2017 - Battery Service Warning', 'Battery cycle count exceeds 1100. Discharges from 100% to 0% in under 20 minutes.', 'Cancelled', '2026-10-22 10:00:00'),

-- 7. Open Repair Request (Laptops & Computing)
(7, 4, 2, 'Dell Inspiron 15 (5510) - Loose DC Power Jack & Intermittent Charging', 'Barrel charging plug is extremely loose inside the chassis. Battery only charges when the cable is pulled upwards at a tight angle. Requires opening bottom shell and securing or replacing the DC-in harness jack.', 'Open', '2026-11-11 10:15:00'),

-- 8. Verified Repair Request (Dorm Living & Appliances)
(8, 5, 5, 'Midea 16-inch Dorm Stand Fan - Stiff Motor Shaft & Oscillation Jam', 'Blades barely turn on speed 1 and motor housing gets hot with a humming sound. Dried grease on sleeve bearings. Needs cage removal, rotor shaft cleaning with isopropyl alcohol, and 3-in-1 machine oil lubrication.', 'Verified', '2026-11-11 14:30:00'),

-- 9. Open Repair Request (Audio & Sound)
(9, 6, 4, 'Apple AirPods (2nd Gen) - Right Earbud Low Volume & Muffled Sound', 'Right earbud audio output is roughly 25% lower than the left one. Bluetooth and microphone work fine. Suspected clogged acoustic mesh screen. Needs precision ultrasonic or Blu-Tack / isopropyl alcohol cleaning.', 'Open', '2026-11-12 09:00:00'),

-- 10. Verified Repair Request (Phones & Tablets)
(10, 7, 3, 'Apple iPad Air (3rd Gen) - Lightning Port Packed with Lint & Not Syncing', 'Lightning cable will not click all the way in and disconnects with slight movement. Port has compressed pocket lint preventing pin contact. Needs fine non-conductive ESD probe cleaning and contact restoration.', 'Verified', '2026-11-12 11:20:00'),

-- 11. Open Repair Request (Dorm Living & Appliances)
(11, 4, 5, 'Tefal Mini Dorm Rice Cooker (0.8L) - Cook Switch Springs Back Early', 'The mechanical lever switch clicks from Cook to Warm after just two minutes, leaving rice undercooked. Center magnetic thermostat spring mechanism needs tension adjustment or microswitch contact cleaning.', 'Open', '2026-11-12 16:45:00');

-- ==============================================================================
-- 8. REPAIR IMAGES
-- ==============================================================================
INSERT INTO repair_images (repair_image_id, request_id, image_path) VALUES
(1, 1, 'assets/images/welcomebanner.jpeg'),
(2, 2, 'assets/images/ipad.jpg'),
(3, 3, 'assets/images/iron.jpg'),
(4, 4, 'assets/images/headphones.jpg'),
(5, 5, 'assets/images/keyboard.jpg'),
(6, 7, 'assets/images/dell.jpg'),
(7, 8, 'assets/images/stand-fan.jpg'),
(8, 9, 'assets/images/airpods.jpg'),
(9, 10, 'assets/images/ipad.jpg'),
(10, 11, 'assets/images/rice-cooker.jpg');

-- ==============================================================================
-- 9. REPAIR ASSIGNMENTS
-- Tracks volunteer repair workflow: Accepted -> Diagnosing -> Repairing -> Completed
-- ==============================================================================
INSERT INTO repair_assignments (assignment_id, request_id, volunteer_id, assigned_at, progress_status, completion_notes, completed_at, points_value) VALUES
-- Assignment 1: In 'Repairing' step
(1, 1, 1, '2026-11-06 10:00:00', 'Repairing', 'Opened back chassis, blew out heatsink copper fins with compressed air, dismantled left fan and lubricated bearing. Cleaned dried paste and applied Arctic MX-4. Currently running 30-minute thermal stress test.', NULL, 0),

-- Assignment 2: In 'Diagnosing' step
(2, 2, 2, '2026-11-07 14:00:00', 'Diagnosing', 'Heated screen perimeter, separated display assembly. Tested new screen dry-fit before final adhesive transfer.', NULL, 0),

-- Assignment 3: Completed repair with notes and +25 points
(3, 5, 1, '2026-10-29 11:30:00', 'Completed', 'Desoldered malfunctioning Outemu switch. Sucked out old lead-free solder, cleaned contact traces with isopropyl alcohol, soldered new Gateron Yellow switch with 60/40 rosin-core solder. Key tested 100% functional with VIA tester.', '2026-11-02 16:00:00', 25);

-- ==============================================================================
-- 10. CONVERSATIONS & MESSAGES
-- Demonstrates Marketplace chat and Repair Requester/Volunteer chat
-- ==============================================================================
INSERT INTO conversation (conversation_id, seller_id, buyer_id, listing_id, context_type, context_id, created_at) VALUES
-- Conversation 1: Marketplace inquiry on Casio Calculator
(1, 6, 4, 1, 'Listing', 1, '2026-11-09 14:05:00'),

-- Conversation 2: Repair coordination for ROG Laptop
(2, 6, 2, NULL, 'Repair', 1, '2026-11-06 10:15:00');

INSERT INTO messages (message_id, sender_id, receiver_id, conversation_id, message_text, sent_at) VALUES
-- Messages for Conversation 1 (Marketplace)
(1, 4, 6, 1, 'Hi Kevin, is the Casio 570EX still available? Does it come with the sliding protective cover?', '2026-11-09 14:05:00'),
(2, 6, 4, 1, 'Yes Marcus! It is in top shape with the original cover and solar panel working. Are you on campus today?', '2026-11-09 14:12:00'),
(3, 4, 6, 1, 'Yes, I have tutorial at Level 4 until 3 PM. Can we meet near Starbucks or cafeteria?', '2026-11-09 14:18:00'),
(4, 6, 4, 1, 'Sure, let\'s meet at 3:15 PM at the cafeteria entrance. See you there!', '2026-11-09 14:22:00'),

-- Messages for Conversation 2 (Repair)
(5, 2, 6, 2, 'Hey Kevin, I\'ve accepted your ROG G15 repair request. I have precision screwdrivers and thermal paste ready. When are you free to drop it off?', '2026-11-06 10:15:00'),
(6, 6, 2, 2, 'Hi Alex! Thanks for taking the job. I can bring it to the Library Discussion Room at 1 PM.', '2026-11-06 10:25:00'),
(7, 2, 6, 2, 'Sounds good. Make sure to bring your charger so we can verify the fan curves.', '2026-11-06 10:30:00'),
(8, 2, 6, 2, 'Update: The fin stack had dust clogging the exhaust. I\'ve cleared it and applied fresh Arctic MX-4 paste. Stress test temps dropped from 95C to 74C!', '2026-11-06 16:45:00'),
(9, 6, 2, 2, 'That is incredible! You saved me RM 150 from sending it to a computer shop. Thank you so much!', '2026-11-06 17:00:00');

-- ==============================================================================
-- 11. REPORTED ISSUES (ADMIN MODERATION)
-- Demonstrates Pending report (live investigation) and Resolved report
-- ==============================================================================
INSERT INTO reported_issues (report_id, reporter_id, listing_id, reason, status, handled_by, created_at, resolved_at) VALUES
-- Report 1: Pending (LIVE DEMO TARGET FOR ADMIN INVESTIGATION & TAKEDOWN)
(1, 6, 6, 'Seller requested upfront online deposit before meeting and refused to provide genuine serial number. Suspected scam listing.', 'Pending', NULL, '2026-11-09 17:30:00', NULL),

-- Report 2: Resolved by Admin
(2, 3, 12, 'Listing describes knockoff replica electronics as genuine Apple audio. Violates campus rules on counterfeit goods.', 'Resolved', 1, '2026-11-07 09:00:00', '2026-11-07 10:30:00');

-- ==============================================================================
-- 12. SUSTAINABILITY POINTS TRANSACTIONS
-- Accurately populates the Leaderboard, Impact Charts, and User Totals
-- Sale: +10 pts | Giveaway: +20 pts | Exchange: +15 pts | Repair: +25 pts
-- ==============================================================================
INSERT INTO points_transactions (transaction_id, user_id, points_awarded, source_type, reference_id, created_at) VALUES
-- Alex Lee (user_id = 2) -> Total: 255 pts (Planet Hero)
(1, 2, 25, 'Repair', 3, '2026-11-02 16:00:00'),
(2, 2, 10, 'Sale', 5, '2026-10-19 14:00:00'),
(3, 2, 25, 'Repair', 101, '2026-09-18 11:00:00'),
(4, 2, 25, 'Repair', 102, '2026-09-28 15:30:00'),
(5, 2, 25, 'Repair', 103, '2026-10-05 14:00:00'),
(6, 2, 25, 'Repair', 104, '2026-10-12 16:30:00'),
(7, 2, 20, 'Giveaway', 105, '2026-10-20 09:00:00'),
(8, 2, 100, 'Repair', 106, '2026-10-22 17:00:00'),

-- Priya Kumar (user_id = 3) -> Total: 160 pts (Eco Guardian)
(9, 3, 25, 'Repair', 111, '2026-10-04 10:00:00'),
(10, 3, 25, 'Repair', 112, '2026-10-11 12:30:00'),
(11, 3, 25, 'Repair', 113, '2026-10-18 14:00:00'),
(12, 3, 25, 'Repair', 114, '2026-10-26 16:15:00'),
(13, 3, 20, 'Giveaway', 115, '2026-10-28 11:00:00'),
(14, 3, 20, 'Giveaway', 116, '2026-11-01 13:45:00'),
(15, 3, 20, 'Giveaway', 117, '2026-11-04 15:20:00'),

-- Fatimah Zahra (user_id = 7) -> Total: 120 pts (Green Contributor)
(16, 7, 20, 'Giveaway', 6, '2026-10-26 11:00:00'),
(17, 7, 20, 'Giveaway', 121, '2026-10-15 10:30:00'),
(18, 7, 20, 'Giveaway', 122, '2026-10-22 14:00:00'),
(19, 7, 15, 'Exchange', 123, '2026-10-29 16:00:00'),
(20, 7, 15, 'Exchange', 124, '2026-11-02 09:45:00'),
(21, 7, 15, 'Exchange', 125, '2026-11-05 11:30:00'),
(22, 7, 15, 'Exchange', 126, '2026-11-08 14:15:00'),

-- Kevin Tan (user_id = 6) -> Total: 95 pts (Green Contributor)
(23, 6, 25, 'Repair', 131, '2026-10-10 11:00:00'),
(24, 6, 25, 'Repair', 132, '2026-10-24 15:00:00'),
(25, 6, 15, 'Exchange', 133, '2026-10-30 16:30:00'),
(26, 6, 15, 'Exchange', 134, '2026-11-03 12:00:00'),
(27, 6, 15, 'Exchange', 135, '2026-11-06 14:45:00'),

-- Marcus Wong (user_id = 4) -> Total: 75 pts (Green Contributor)
(28, 4, 20, 'Giveaway', 141, '2026-10-14 10:00:00'),
(29, 4, 20, 'Giveaway', 142, '2026-10-21 11:30:00'),
(30, 4, 20, 'Giveaway', 143, '2026-10-28 14:00:00'),
(31, 4, 15, 'Exchange', 144, '2026-11-04 16:15:00'),

-- Aisha Nor (user_id = 5) -> Total: 20 pts (Eco Starter)
(32, 5, 10, 'Sale', 151, '2026-10-18 12:00:00'),
(33, 5, 10, 'Sale', 152, '2026-11-01 15:30:00');

-- ==============================================================================
-- End of sample data script
-- ==============================================================================
