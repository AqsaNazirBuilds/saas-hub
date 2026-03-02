-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 20, 2026 at 10:54 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `saas`
--

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` int(11) NOT NULL,
  `permission_key` varchar(100) NOT NULL,
  `permission_name` varchar(150) NOT NULL,
  `category` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `is_premium` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`id`, `permission_key`, `permission_name`, `category`, `description`, `is_premium`) VALUES
-- User Management Permissions (Category: user)
(1, 'user.view', 'View Users', 'user', 'View list of users in the company', 0),
(2, 'user.create', 'Create User', 'user', 'Add new users to the system', 0),
(3, 'user.edit', 'Edit User', 'user', 'Edit existing user details', 0),
(4, 'user.delete', 'Delete User', 'user', 'Remove users from the system', 0),
(5, 'user.assign_role', 'Assign Role', 'user', 'Assign roles to users', 0),

-- Role Management Permissions (Category: role)
(6, 'role.view', 'View Roles', 'role', 'View list of roles', 0),
(7, 'role.create', 'Create Role', 'role', 'Create new roles', 0),
(8, 'role.edit', 'Edit Role', 'role', 'Modify existing roles', 0),
(9, 'role.delete', 'Delete Role', 'role', 'Delete roles from system', 0),
(10, 'role.assign_permission', 'Assign Permission', 'role', 'Assign permissions to roles', 0),

-- Reports Permissions (Category: reports)
(11, 'reports.basic', 'Basic Reports', 'reports', 'Access basic reporting features', 0),
(12, 'reports.advanced', 'Advanced Reports', 'reports', 'Access advanced analytics and reports', 1),

-- Audit Logs Permissions (Category: audit)
(13, 'audit.view', 'View Audit Logs', 'audit', 'View activity and audit logs', 0),
(14, 'audit.export', 'Export Audit Logs', 'audit', 'Export audit logs to file', 1),

-- Subscription Permissions (Category: subscription)
(15, 'subscription.view', 'View Subscription', 'subscription', 'View current subscription status', 0),
(16, 'subscription.manage', 'Manage Subscription', 'subscription', 'Upgrade or modify subscription', 0),

-- Settings Permissions (Category: settings)
(17, 'settings.view', 'View Settings', 'settings', 'View company settings', 0),
(18, 'settings.edit', 'Edit Settings', 'settings', 'Modify company settings', 0),

-- Analytics Permissions (Category: analytics)
(19, 'analytics.view', 'View Analytics', 'analytics', 'View basic analytics dashboard', 0),
(20, 'analytics.detailed', 'Detailed Analytics', 'analytics', 'Access detailed analytics', 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `permission_key` (`permission_key`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET CHARACTER_SET_COLLATION=@OLD_COLLATION_CONNECTION */;
