USE `syncr_inventory`;

INSERT INTO `settings` (`setting_key`,`setting_value`,`setting_group`,`is_secret`,`updated_at`) VALUES
('app_name','Inventory','general',0,NOW()),
('app_tagline','Real Estate Inventory Management','general',0,NOW()),
('app_frontend_url','https://superfinelabels.in/plots/app','general',0,NOW()),
('mail_protocol','smtp','mail',0,NOW()),
('mail_smtp_host','smtp.hostinger.com','mail',0,NOW()),
('mail_smtp_port','465','mail',0,NOW()),
('mail_smtp_user','info@superfinelabels.in','mail',0,NOW()),
('mail_smtp_pass','Velmurugn0071@!!','mail',1,NOW()),
('mail_smtp_crypto','ssl','mail',0,NOW()),
('mail_from_email','info@superfinelabels.in','mail',0,NOW()),
('mail_from_name','Inventory','mail',0,NOW()),
('mail_enabled','1','mail',0,NOW()),
('test_admin_email','velrke@gmail.com','credentials',0,NOW()),
('test_admin_password','Admin@123','credentials',0,NOW()),
('test_team_admin_email','teamadmin@abc.test','credentials',0,NOW()),
('test_team_admin_password','TeamAdmin@123','credentials',0,NOW()),
('test_team_user_email','user@abc.test','credentials',0,NOW()),
('test_team_user_password','TeamUser@123','credentials',0,NOW());

INSERT INTO `marketing_companies` (`id`,`name`,`email`,`phone`,`address`,`city`,`status`,`permissions`,`created_at`) VALUES
(1,'ABC Marketing','hello@abc.test','9876500001','12 Anna Salai','Coimbatore','active','["view_inventory","submit_block_requests","manage_users"]',NOW()),
(2,'Horizon Sales','hello@horizon.test','9876500002','88 MG Road','Chennai','active','["view_inventory","submit_block_requests","manage_users"]',NOW());

INSERT INTO `users` (`id`,`company_id`,`name`,`email`,`password_hash`,`phone`,`role`,`status`,`created_at`) VALUES
(1,NULL,'Admin','velrke@gmail.com','$2y$10$CHLje7UsoZvdvxkrteyez.9F4C2K15fBp.Jx/99qEvONnyaJ/Bih.','9000000001','promoter_admin','active',NOW()),
(2,1,'Kavitha Raj','teamadmin@abc.test','$2y$10$hDq8X61nOiuKdoJkuzj1Pevu9GBlq.9ltMmfRQ29/V8aK9/3TjmUK','9000000002','marketing_team_admin','active',NOW()),
(3,1,'Arun Sales','user@abc.test','$2y$10$S8Kk4PUYpcLm7z9jpCUHdOK1SSGXPUhijU0HSXybk5JGgzH65LiBK','9000000003','marketing_team_user','active',NOW()),
(4,2,'Hari Horizon','hari@horizon.test','$2y$10$S8Kk4PUYpcLm7z9jpCUHdOK1SSGXPUhijU0HSXybk5JGgzH65LiBK','9000000004','marketing_team_admin','active',NOW());

INSERT INTO `projects` (`id`,`name`,`location`,`city`,`project_type`,`description`,`approval_details`,`contact_name`,`contact_phone`,`contact_email`,`status`,`created_at`) VALUES
(1,'Royal City','Near Airport','Coimbatore','Residential Plot','Premium plotted development with wide roads and DTCP approval.','DTCP Approved','Site Office','0422-111111','royal@syncr.test','active',NOW()),
(2,'Green Valley','OMR','Chennai','Residential Plot','Gated community plots with clubhouse and landscaped avenues.','CMDA Approved','Site Office','044-222222','green@syncr.test','active',NOW()),
(3,'Lake View','Whitefield','Bengaluru','Villa Plot','Lake-facing villa plots with premium corner inventory.','BDA Approved','Site Office','080-333333','lake@syncr.test','active',NOW());

INSERT INTO `company_project_assignments` (`company_id`,`project_id`,`created_at`) VALUES
(1,1,NOW()),(1,2,NOW()),(2,2,NOW()),(2,3,NOW());

INSERT INTO `user_project_assignments` (`user_id`,`project_id`,`created_at`) VALUES
(2,1,NOW()),(2,2,NOW()),(3,1,NOW()),(4,2,NOW()),(4,3,NOW());

INSERT INTO `inventory_units`
(`project_id`,`unit_no`,`block_phase`,`plot_type`,`area_sqft`,`facing`,`road_width_ft`,`dimensions`,`price`,`price_per_sqft`,`is_premium`,`is_corner`,`approval_details`,`remarks`,`status`,`created_at`) VALUES
(1,'A-101','Phase A','Residential Plot',1200,'East',30,'30x40',3600000,3000,0,0,'DTCP Approved','Corner approach','available',NOW()),
(1,'A-102','Phase A','Residential Plot',1200,'West',30,'30x40',3480000,2900,0,0,'DTCP Approved',NULL,'available',NOW()),
(1,'A-103','Phase A','Residential Plot',1400,'North',40,'35x40',4620000,3300,1,1,'DTCP Approved','Premium corner','blocked',NOW()),
(1,'B-201','Phase B','Residential Plot',1000,'South',24,'25x40',2800000,2800,0,0,'DTCP Approved',NULL,'booked',NOW()),
(1,'B-202','Phase B','Residential Plot',1000,'East',24,'25x40',2750000,2750,0,0,'DTCP Approved',NULL,'registered',NOW()),
(1,'C-301','Phase C','Residential Plot',1600,'East',40,'40x40',5600000,3500,1,0,'DTCP Approved','Park facing','available',NOW()),
(1,'C-302','Phase C','Residential Plot',1600,'West',40,'40x40',5440000,3400,0,0,'DTCP Approved',NULL,'on_hold',NOW()),
(2,'P-11','Block P','Residential Plot',1500,'East',30,'30x50',5250000,3500,0,0,'CMDA Approved',NULL,'available',NOW()),
(2,'P-12','Block P','Residential Plot',1500,'North',30,'30x50',5400000,3600,1,1,'CMDA Approved','Corner','booked',NOW()),
(2,'Q-21','Block Q','Residential Plot',1800,'West',40,'36x50',7200000,4000,1,0,'CMDA Approved',NULL,'available',NOW()),
(3,'LV-01','Lakeside','Villa Plot',2400,'East',40,'40x60',12000000,5000,1,1,'BDA Approved','Lake view','available',NOW()),
(3,'LV-02','Lakeside','Villa Plot',2400,'West',40,'40x60',10800000,4500,0,0,'BDA Approved',NULL,'blocked',NOW());

INSERT INTO `customers` (`id`,`name`,`phone`,`email`,`created_at`) VALUES
(1,'Ravi Kumar','9843010101','ravi@example.com',NOW()),
(2,'Priya Sharma','9843020202','priya@example.com',NOW()),
(3,'Suresh Nair','9843030303','suresh@example.com',NOW());

INSERT INTO `block_requests`
(`unit_id`,`company_id`,`requested_by`,`customer_name`,`customer_phone`,`customer_email`,`expected_booking_date`,`remarks`,`status`,`reviewed_by`,`reviewed_at`,`review_notes`,`created_at`) VALUES
(3,1,3,'Meena Iyer','9843040404','meena@example.com','2024-06-10','Customer visiting this weekend','approved',1,NOW(),'Hold for 7 days',NOW()),
(12,2,4,'Karthik R','9843050505','karthik@example.com','2024-06-18','Needs lake-facing plot','pending',NULL,NULL,NULL,NOW()),
(2,1,3,'Anitha Devi','9843060606','anitha@example.com','2024-06-12','Budget discussion pending','rejected',1,NOW(),'Customer budget mismatch',NOW());

INSERT INTO `bookings`
(`unit_id`,`project_id`,`company_id`,`customer_id`,`customer_name`,`customer_phone`,`customer_email`,`amount`,`booking_date`,`status`,`payment_status`,`notes`,`created_by`,`created_at`) VALUES
(4,1,1,1,'Ravi Kumar','9843010101','ravi@example.com',2800000,'2024-05-15','confirmed','partial','Advance collected',1,NOW()),
(9,2,1,2,'Priya Sharma','9843020202','priya@example.com',5400000,'2024-05-20','pending','unpaid','Awaiting token',2,NOW());

INSERT INTO `registrations`
(`unit_id`,`project_id`,`company_id`,`booking_id`,`customer_id`,`customer_name`,`customer_phone`,`customer_email`,`amount`,`registration_date`,`status`,`payment_status`,`notes`,`created_by`,`created_at`) VALUES
(5,1,1,NULL,3,'Suresh Nair','9843030303','suresh@example.com',2750000,'2024-05-28','confirmed','paid','Registered at SRO',1,NOW());

INSERT INTO `activity_logs` (`user_id`,`company_id`,`action`,`entity_type`,`entity_id`,`description`,`ip_address`,`created_at`) VALUES
(1,NULL,'seed','system',0,'Database seeded with demo SYNCR data','127.0.0.1',NOW()),
(1,NULL,'inventory.update','inventory_units',3,'A-103 status changed from Available to Blocked','127.0.0.1',NOW()),
(3,1,'request.create','block_requests',1,'Block request submitted for A-103','127.0.0.1',NOW());
