-- Migration 010: Add rich editable profile fields (bio, location, contact links, skills) to users table
ALTER TABLE users
    ADD COLUMN bio TEXT NULL AFTER job_title,
    ADD COLUMN location VARCHAR(120) NULL AFTER bio,
    ADD COLUMN linkedin_url VARCHAR(255) NULL AFTER location,
    ADD COLUMN github_url VARCHAR(255) NULL AFTER linkedin_url,
    ADD COLUMN website_url VARCHAR(255) NULL AFTER github_url,
    ADD COLUMN phone VARCHAR(30) NULL AFTER website_url,
    ADD COLUMN skills JSON NULL AFTER phone;
