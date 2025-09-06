-- =====================================================
-- Student Distribution Script
-- Distributes all students across sections for each year level
-- =====================================================

-- Update sections with proper student distribution
-- 1st Year - Section A (ID: 2) - 15 students (IDs 4,5,16-27)
UPDATE `sections` SET `students` = '["4","5","16","17","18","19","20","21","22","23","24","25","26","27"]' WHERE `id` = 2;

-- 1st Year - Section B (ID: 3) - 15 students (IDs 28-42)
UPDATE `sections` SET `students` = '["28","29","30","31","32","33","34","35","36","37","38","39","40","41","42"]' WHERE `id` = 3;

-- 1st Year - Section C (ID: 4) - 15 students (IDs 43-57)
UPDATE `sections` SET `students` = '["43","44","45","46","47","48","49","50","51","52","53","54","55","56","57"]' WHERE `id` = 4;

-- 1st Year - Section D (ID: 5) - 15 students (IDs 58-72)
UPDATE `sections` SET `students` = '["58","59","60","61","62","63","64","65","66","67","68","69","70","71","72"]' WHERE `id` = 5;

-- 2nd Year - Section A (ID: 6) - 15 students (IDs 73-87)
UPDATE `sections` SET `students` = '["73","74","75","76","77","78","79","80","81","82","83","84","85","86","87"]' WHERE `id` = 6;

-- 2nd Year - Section B (ID: 7) - 15 students (IDs 88-102)
UPDATE `sections` SET `students` = '["88","89","90","91","92","93","94","95","96","97","98","99","100","101","102"]' WHERE `id` = 7;

-- 2nd Year - Section C (ID: 8) - 15 students (IDs 103-117)
UPDATE `sections` SET `students` = '["103","104","105","106","107","108","109","110","111","112","113","114","115","116","117"]' WHERE `id` = 8;

-- 2nd Year - Section D (ID: 9) - 15 students (IDs 118-132)
UPDATE `sections` SET `students` = '["118","119","120","121","122","123","124","125","126","127","128","129","130","131","132"]' WHERE `id` = 9;

-- 3rd Year - Section A (ID: 10) - 15 students (IDs 133-147)
UPDATE `sections` SET `students` = '["133","134","135","136","137","138","139","140","141","142","143","144","145","146","147"]' WHERE `id` = 10;

-- 3rd Year - Section B (ID: 11) - 15 students (IDs 148-162)
UPDATE `sections` SET `students` = '["148","149","150","151","152","153","154","155","156","157","158","159","160","161","162"]' WHERE `id` = 11;

-- 3rd Year - Section C (ID: 12) - 15 students (IDs 163-177)
UPDATE `sections` SET `students` = '["163","164","165","166","167","168","169","170","171","172","173","174","175","176","177"]' WHERE `id` = 12;

-- 3rd Year - Section D (ID: 13) - 15 students (IDs 178-192)
UPDATE `sections` SET `students` = '["178","179","180","181","182","183","184","185","186","187","188","189","190","191","192"]' WHERE `id` = 13;

-- 4th Year - Section A (ID: 14) - 15 students (IDs 193-207)
UPDATE `sections` SET `students` = '["193","194","195","196","197","198","199","200","201","202","203","204","205","206","207"]' WHERE `id` = 14;

-- 4th Year - Section B (ID: 15) - 15 students (IDs 208-222)
UPDATE `sections` SET `students` = '["208","209","210","211","212","213","214","215","216","217","218","219","220","221","222"]' WHERE `id` = 15;

-- 4th Year - Section C (ID: 16) - 15 students (IDs 223-237)
UPDATE `sections` SET `students` = '["223","224","225","226","227","228","229","230","231","232","233","234","235","236","237"]' WHERE `id` = 16;

-- 4th Year - Section D (ID: 17) - 15 students (IDs 238-252)
UPDATE `sections` SET `students` = '["238","239","240","241","242","243","244","245","246","247","248","249","250","251","252"]' WHERE `id` = 17;

-- Display distribution summary
SELECT 'Student Distribution Complete!' as Status;
SELECT 
    s.year_level,
    s.section_name,
    JSON_LENGTH(s.students) as student_count
FROM sections s 
WHERE s.students IS NOT NULL AND s.students != '[]'
ORDER BY s.year_level, s.section_name;
