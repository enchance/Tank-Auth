

-- -----------------------------------------------------
-- Data for table `permissions`
-- -----------------------------------------------------
START TRANSACTION;
INSERT INTO `permissions` (`permission_id`, `permission`, `description`, `parent`, `sort`) VALUES (1, 'open page', 'Open this page', 'Pages you can open', NULL);
INSERT INTO `permissions` (`permission_id`, `permission`, `description`, `parent`, `sort`) VALUES (2, 'open user page', 'Only users can open this', 'User domain', NULL);

COMMIT;

-- -----------------------------------------------------
-- Data for table `role_permissions`
-- -----------------------------------------------------
START TRANSACTION;
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1, 1);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (2, 2);

COMMIT;
