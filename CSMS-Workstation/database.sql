-- ================================================================
-- ConstructPro v3 — Database Setup
-- Run in phpMyAdmin SQL tab on database: construction_db
-- ================================================================

CREATE DATABASE IF NOT EXISTS construction_db;
USE construction_db;

-- USERS — role now includes 'admin'
CREATE TABLE IF NOT EXISTS users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    employee_id   VARCHAR(20)  UNIQUE NOT NULL,
    full_name     VARCHAR(100) NOT NULL,
    role          ENUM('admin','hr','site_manager','foreman','safety_officer','general_labour') NOT NULL,
    password      VARCHAR(255) NOT NULL,
    site_assigned VARCHAR(100) DEFAULT NULL,
    is_active     TINYINT(1)   DEFAULT 1,
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

-- MESSAGES
CREATE TABLE IF NOT EXISTS messages (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    sender_id   INT  NOT NULL,
    receiver_id INT  NOT NULL,
    subject     VARCHAR(200) NOT NULL,
    body        TEXT NOT NULL,
    is_read     TINYINT(1) DEFAULT 0,
    sent_at     TIMESTAMP  DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id)   REFERENCES users(id),
    FOREIGN KEY (receiver_id) REFERENCES users(id)
);

-- ATTENDANCE
CREATE TABLE IF NOT EXISTS attendance (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT  NOT NULL,
    date        DATE NOT NULL,
    status      ENUM('present','absent','late') DEFAULT 'present',
    recorded_by INT  NOT NULL,
    notes       VARCHAR(255) DEFAULT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES users(id),
    FOREIGN KEY (recorded_by) REFERENCES users(id)
);

-- DAILY REPORTS (Foreman)
CREATE TABLE IF NOT EXISTS daily_reports (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    foreman_id       INT  NOT NULL,
    site_name        VARCHAR(100) NOT NULL,
    report_date      DATE NOT NULL,
    progress_update  TEXT NOT NULL,
    resource_usage   TEXT NOT NULL,
    equipment_needs  TEXT DEFAULT NULL,
    status           ENUM('pending','approved','rejected') DEFAULT 'pending',
    manager_comment  TEXT DEFAULT NULL,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (foreman_id) REFERENCES users(id)
);

-- SAFETY REPORTS
CREATE TABLE IF NOT EXISTS safety_reports (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    officer_id           INT  NOT NULL,
    site_name            VARCHAR(100) NOT NULL,
    report_date          DATE NOT NULL,
    inspection_findings  TEXT NOT NULL,
    unsafe_conditions    TEXT DEFAULT NULL,
    incident_description TEXT DEFAULT NULL,
    severity             ENUM('low','medium','high','critical') DEFAULT 'low',
    status               ENUM('open','in_progress','resolved')  DEFAULT 'open',
    created_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (officer_id) REFERENCES users(id)
);

-- MATERIAL ORDERS
CREATE TABLE IF NOT EXISTS material_orders (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    requested_by   INT  NOT NULL,
    order_type     ENUM('safety','general') DEFAULT 'general',
    items          TEXT NOT NULL,
    reason         TEXT NOT NULL,
    status         ENUM('pending','approved','rejected') DEFAULT 'pending',
    manager_comment TEXT DEFAULT NULL,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (requested_by) REFERENCES users(id)
);

-- COMPLAINTS
CREATE TABLE IF NOT EXISTS complaints (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    worker_id      INT  NOT NULL,
    complaint_text TEXT NOT NULL,
    status         ENUM('open','in_progress','resolved') DEFAULT 'open',
    response       TEXT DEFAULT NULL,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (worker_id) REFERENCES users(id)
);

-- SCHEDULES / SHIFTS
CREATE TABLE IF NOT EXISTS schedules (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    worker_id        INT          NOT NULL,
    site_name        VARCHAR(100) NOT NULL,
    shift_date       DATE         NOT NULL,
    shift_start      TIME         NOT NULL,
    shift_end        TIME         NOT NULL,
    task_description VARCHAR(255) DEFAULT NULL,
    created_by       INT          NOT NULL,
    FOREIGN KEY (worker_id)  REFERENCES users(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- SYSTEM LOGS (Admin only — tracks key actions)
CREATE TABLE IF NOT EXISTS system_logs (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT          NOT NULL,
    action     VARCHAR(200) NOT NULL,
    details    TEXT         DEFAULT NULL,
    ip_address VARCHAR(45)  DEFAULT NULL,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
