-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Sep 05, 2025 at 03:45 AM
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
-- Database: `lms_neust_normalized`
--

-- --------------------------------------------------------

--
-- Table structure for table `sections`
--

CREATE TABLE `sections` (
  `id` int(11) NOT NULL,
  `year_level` int(11) NOT NULL DEFAULT 1,
  `section_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `academic_period_id` int(11) NOT NULL,
  `students` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON array of student IDs',
  `teachers` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON array of teacher IDs',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sections`
--

INSERT INTO `sections` (`id`, `year_level`, `section_name`, `description`, `is_active`, `academic_period_id`, `students`, `teachers`, `created_at`) VALUES
(2, 1, 'A', 'Section A for first year', 1, 1, '[\"4\",\"5\",\"16\",\"17\",\"18\",\"19\",\"20\",\"21\",\"22\",\"23\",\"24\",\"25\",\"26\",\"27\",\"66\"]', NULL, '2025-09-01 03:03:59'),
(3, 1, 'B', 'Section B for 1st Year', 1, 3, '[\"28\",\"29\",\"30\",\"31\",\"32\",\"33\",\"34\",\"35\",\"36\",\"37\",\"38\",\"39\",\"40\",\"41\"]', NULL, '2025-09-01 03:16:41'),
(4, 1, 'C', 'Section C for 1st Year', 1, 1, '[\"42\",\"43\",\"44\",\"45\",\"46\",\"47\",\"48\",\"49\",\"50\",\"51\",\"52\",\"53\",\"54\",\"55\",\"122\",\"101\",\"26\",\"108\",\"5\",\"119\"]', NULL, '2025-09-05 01:23:34'),
(5, 1, 'D', 'Section D for 1st Year', 1, 1, '[]', NULL, '2025-09-05 01:23:34'),
(6, 2, 'A', 'Section A for 2nd Year', 1, 1, '[\"66\",\"67\",\"68\",\"69\",\"70\",\"71\",\"72\",\"73\",\"74\",\"75\",\"76\",\"77\",\"78\",\"79\",\"80\"]', NULL, '2025-09-05 01:23:34'),
(7, 2, 'B', 'Section B for 2nd Year', 1, 1, '[\"81\",\"82\",\"83\",\"84\",\"85\",\"86\",\"87\",\"88\",\"89\",\"90\",\"91\",\"92\",\"93\",\"94\",\"95\"]', NULL, '2025-09-05 01:23:34'),
(8, 2, 'C', 'Section C for 2nd Year', 1, 1, '[\"96\",\"97\",\"98\",\"99\",\"100\",\"101\",\"102\",\"103\",\"104\",\"105\",\"106\",\"107\",\"108\",\"109\",\"110\"]', NULL, '2025-09-05 01:23:34'),
(9, 2, 'D', 'Section D for 2nd Year', 1, 1, '[\"111\",\"112\",\"113\",\"114\",\"115\",\"116\",\"117\",\"118\",\"119\",\"120\",\"121\",\"122\",\"123\",\"124\",\"125\"]', NULL, '2025-09-05 01:23:34'),
(10, 3, 'A', 'Section A for 3rd Year', 1, 1, '[\"126\",\"127\",\"128\",\"129\",\"130\",\"131\",\"132\",\"133\",\"134\",\"135\",\"136\",\"137\",\"138\",\"139\",\"140\"]', NULL, '2025-09-05 01:23:34'),
(11, 3, 'B', 'Section B for 3rd Year', 1, 1, '[\"141\",\"142\",\"143\",\"144\",\"145\",\"146\",\"147\",\"148\",\"149\",\"150\",\"151\",\"152\",\"153\",\"154\",\"155\"]', NULL, '2025-09-05 01:23:34'),
(12, 3, 'C', 'Section C for 3rd Year', 1, 1, '[\"156\",\"157\",\"158\",\"159\",\"160\",\"161\",\"162\",\"163\",\"164\",\"165\",\"166\",\"167\",\"168\",\"169\",\"170\"]', NULL, '2025-09-05 01:23:34'),
(13, 3, 'D', 'Section D for 3rd Year', 1, 1, '[\"171\",\"172\",\"173\",\"174\",\"175\",\"176\",\"177\",\"178\",\"179\",\"180\",\"181\",\"182\",\"183\",\"184\",\"185\"]', NULL, '2025-09-05 01:23:34'),
(14, 4, 'A', 'Section A for 4th Year', 1, 1, '[\"186\",\"187\",\"188\",\"189\",\"190\",\"191\",\"192\",\"193\",\"194\",\"195\",\"196\",\"197\",\"198\",\"199\",\"200\"]', NULL, '2025-09-05 01:23:34'),
(15, 4, 'B', 'Section B for 4th Year', 1, 1, '[\"201\",\"202\",\"203\",\"204\",\"205\",\"206\",\"207\",\"208\",\"209\",\"210\",\"211\",\"212\",\"213\",\"214\",\"215\"]', NULL, '2025-09-05 01:23:34'),
(16, 4, 'C', 'Section C for 4th Year', 1, 1, '[\"216\",\"217\",\"218\",\"219\",\"220\",\"221\",\"222\",\"223\",\"224\",\"225\",\"226\",\"227\",\"228\",\"229\",\"230\"]', NULL, '2025-09-05 01:23:34'),
(17, 4, 'D', 'Section D for 4th Year', 1, 1, '[\"231\",\"232\",\"233\",\"234\",\"235\",\"236\",\"237\",\"238\",\"239\",\"240\",\"241\",\"242\",\"243\",\"244\",\"245\"]', NULL, '2025-09-05 01:23:34');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `sections`
--
ALTER TABLE `sections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_year_level` (`year_level`),
  ADD KEY `idx_academic_period_id` (`academic_period_id`),
  ADD KEY `idx_active` (`is_active`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `sections`
--
ALTER TABLE `sections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `sections`
--
ALTER TABLE `sections`
  ADD CONSTRAINT `fk_sections_academic_period` FOREIGN KEY (`academic_period_id`) REFERENCES `academic_periods` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
