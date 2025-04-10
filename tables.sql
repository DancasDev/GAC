SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

DROP TABLE IF EXISTS `gac_module`;
CREATE TABLE IF NOT EXISTS `gac_module` (
  `id` int NOT NULL AUTO_INCREMENT,
  `module_category_id` int NOT NULL,
  `name` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `base_route` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_developing` enum('0','1') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1' COMMENT '0 = No, 1 = Sí',
  `is_disabled` enum('0','1') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `created_at` bigint NOT NULL,
  `updated_at` bigint DEFAULT NULL,
  `deleted_at` bigint DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `module_category_id` (`module_category_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `gac_module` (`id`, `module_category_id`, `name`, `code`, `description`, `base_route`, `is_developing`, `is_disabled`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 2, 'Mi perfil', 'my_profile', 'Permite al usuario editar sus datos personales.', '/my/profile', '1', '0', 1726150616, NULL, NULL),
(2, 2, 'Mi usuario', 'my_user', 'Permite al usuario editar sus datos de acceso (usuario, contraseña, etc.).', '/my/user', '1', '0', 1726150616, NULL, NULL),
(3, 1, 'Usuarios', 'users', 'Permite la administración de los usuarios del sistema.', '/users', '1', '0', 1726150616, NULL, NULL),
(4, 1, 'Accesos de usuario', 'users_access', 'Permite la administración del acceso de los usuarios  al sistema.', '/users/{:user_id}/access', '1', '0', 1726150616, NULL, NULL),
(5, 1, 'Roles de usuario', 'users_roles', 'Permite la administración de roles de usuarios.', '/users/{:user_id}/roles', '1', '0', 1726150616, NULL, NULL),
(6, 1, 'Roles', 'roles', 'Permite la administración de roles.', '/roles', '1', '0', 1726150616, NULL, NULL),
(7, 1, 'Accesos de roles', 'roles_access', 'Permite la administración del acceso de los roles.', '/roles/{:role_id}/access', '1', '0', 1726150616, NULL, NULL),
(8, 1, 'Módulos', 'modules', 'Permite la administración de módulos del sistema.', '/modules', '1', '0', 1726150616, NULL, NULL),
(9, 1, 'Módulos categorías', 'modules_categories', 'Permite la administración de las categorías de los módulos.', '/modules/categories', '1', '0', 1726150616, NULL, NULL);

DROP TABLE IF EXISTS `gac_module_access`;
CREATE TABLE IF NOT EXISTS `gac_module_access` (
  `id` int NOT NULL AUTO_INCREMENT,
  `from_entity_type` enum('0','1','2') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Desde la entidad: 0 = Rol (acc_role), 1 = Usuario (acc_user), 2 = Token externo (acc_token_external)',
  `from_entity_id` int NOT NULL,
  `to_entity_type` enum('0','1') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'A la entidad: 0 = Categoría (acc_module_category), 1 = Módulo (acc_module)',
  `to_entity_id` int NOT NULL,
  `feature` set('0','1','2','3','4','5') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Con acceso a las caracteristicas (acciones): 0 = Crear, 1 = Leer, 2 = Actualizar, 3 = Eliminar, 4 = Papelera (valor funciona en combinación con los valores 1, 2 y 3), 5 = Modo desarrollo',
  `level` enum('0','1','2') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1' COMMENT 'Con un nivel de acceso: 0 = Bajo, 1 = Normal, 2 = Alto',
  `is_disabled` enum('0','1') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `created_at` bigint NOT NULL,
  `updated_at` bigint DEFAULT NULL,
  `deleted_at` bigint DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `access_unique` (`from_entity_type`,`from_entity_id`,`to_entity_type`,`to_entity_id`),
  KEY `from_entity_type` (`from_entity_type`),
  KEY `to_entity_type` (`to_entity_type`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `gac_module_access` (`id`, `from_entity_type`, `from_entity_id`, `to_entity_type`, `to_entity_id`, `feature`, `level`, `is_disabled`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, '0', 1, '0', 1, '0,1,2,3,4,5', '1', '0', 1726150616, NULL, NULL),
(2, '0', 1, '0', 2, '0,1,2,3,4,5', '1', '0', 1726150616, NULL, NULL),
(3, '0', 1, '0', 3, '0,1,2,3,4,5', '1', '0', 1726150616, NULL, NULL),
(4, '0', 2, '0', 1, '0,1,2,3,4,5', '2', '0', 1726150616, NULL, NULL);

DROP TABLE IF EXISTS `gac_module_access_restriction`;
CREATE TABLE IF NOT EXISTS `gac_module_access_restriction` (
  `id` int NOT NULL AUTO_INCREMENT,
  `module_access_id` int NOT NULL,
  `restriction_id` int NOT NULL,
  `value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Valor de la restricción (JSON)',
  `is_disabled` enum('0','1') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `created_at` bigint NOT NULL,
  `updated_at` bigint DEFAULT NULL,
  `deleted_at` bigint DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `restriction_unique` (`module_access_id`,`restriction_id`),
  KEY `module_access_id` (`module_access_id`),
  KEY `restriction_id` (`restriction_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `gac_module_access_restriction` (`id`, `module_access_id`, `restriction_id`, `value`, `is_disabled`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 1, 1, '[1,2]', '1', 156454545646, NULL, NULL),
(2, 1, 6, '{\"date\": \"%Y-02-07 00:00:00\"}', '0', 156454545647, NULL, NULL);


DROP TABLE IF EXISTS `gac_module_category`;
CREATE TABLE IF NOT EXISTS `gac_module_category` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_disabled` enum('0','1') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `created_at` bigint NOT NULL,
  `updated_at` bigint DEFAULT NULL,
  `deleted_at` bigint DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `gac_module_category` (`id`, `name`, `description`, `is_disabled`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'Sistema', 'Módulos relacionados a la administración del sistema sistema', '0', 1726150616, NULL, NULL),
(2, 'Usuario', 'Módulos relacionados a la administración de los registros relacionado al usuario (ejemplo: Datos personales).', '0', 1726150616, NULL, NULL);

DROP TABLE IF EXISTS `gac_person`;
CREATE TABLE IF NOT EXISTS `gac_person` (
  `id` int NOT NULL AUTO_INCREMENT,
  `first_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sex` enum('0','1') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '0 = Masculino, 1 = Femenino',
  `is_disabled` enum('0','1') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `created_at` bigint NOT NULL,
  `updated_at` bigint DEFAULT NULL,
  `deleted_at` bigint DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Directorio de personas, adapta esta tabla (nombre y campos) a tu conveniencia';

INSERT INTO `gac_person` (`id`, `first_name`, `last_name`, `email`, `sex`, `is_disabled`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'Admin', 'Test', 'admin@test.com', NULL, '0', 1729261890, NULL, NULL);

DROP TABLE IF EXISTS `gac_restriction`;
CREATE TABLE IF NOT EXISTS `gac_restriction` (
  `id` int NOT NULL AUTO_INCREMENT,
  `restriction_category_id` int NOT NULL,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_disabled` enum('0','1') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `created_at` bigint NOT NULL,
  `updated_at` bigint DEFAULT NULL,
  `deleted_at` bigint DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code_unique` (`restriction_category_id`,`code`),
  KEY `restriction_category_id` (`restriction_category_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `gac_restriction` (`id`, `restriction_category_id`, `code`, `description`, `is_disabled`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 1, 'blacklist', NULL, '0', 1738853181, NULL, NULL),
(2, 1, 'whitelist', NULL, '0', 1738853181, NULL, NULL),
(3, 2, 'in_range', NULL, '0', 1738853181, NULL, NULL),
(4, 2, 'out_range', NULL, '0', 1738853181, NULL, NULL),
(5, 2, 'before', NULL, '0', 1738853181, NULL, NULL),
(6, 2, 'after', NULL, '0', 1738853181, NULL, NULL);

DROP TABLE IF EXISTS `gac_restriction_category`;
CREATE TABLE IF NOT EXISTS `gac_restriction_category` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_disabled` enum('0','1') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `created_at` bigint NOT NULL,
  `updated_at` bigint DEFAULT NULL,
  `deleted_at` bigint DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `gac_restriction_category` (`id`, `code`, `description`, `is_disabled`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'branch', NULL, '0', 1738853181, NULL, NULL),
(2, 'date', NULL, '0', 1738853181, NULL, NULL);

DROP TABLE IF EXISTS `gac_role`;
CREATE TABLE IF NOT EXISTS `gac_role` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_disabled` enum('0','1') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `created_at` bigint NOT NULL,
  `updated_at` bigint DEFAULT NULL,
  `deleted_at` bigint DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `gac_role` (`id`, `name`, `code`, `description`, `is_disabled`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'Administrador de sistema', 'system_administrator', NULL, '0', 1726150616, NULL, NULL),
(2, 'Supervisor de sistema', 'system_supervisor', NULL, '0', 1726150616, NULL, NULL);

DROP TABLE IF EXISTS `gac_role_entity`;
CREATE TABLE IF NOT EXISTS `gac_role_entity` (
  `id` int NOT NULL AUTO_INCREMENT,
  `role_id` int NOT NULL,
  `entity_type` enum('1','2') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '1 = Usuario (acc_user), 2 = Token externo (acc_token_external)',
  `entity_id` int NOT NULL,
  `priority` enum('0','1','2','3','4') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0' COMMENT 'Prioridad del rol: 0 = rol principal, cualquier otro valor diferente a 0 = rol secundario. El usuario solo puede tener un rol asociado por cada valor de este campo',
  `is_disabled` enum('0','1') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `created_at` bigint NOT NULL,
  `updated_at` bigint DEFAULT NULL,
  `deleted_at` bigint DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_unique` (`role_id`,`entity_type`,`entity_id`),
  UNIQUE KEY `priority_unique` (`entity_type`,`entity_id`,`priority`),
  KEY `role_id` (`role_id`),
  KEY `entity_type` (`entity_type`,`entity_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `gac_role_entity` (`id`, `role_id`, `entity_type`, `entity_id`, `priority`, `is_disabled`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 1, '1', 1, '0', '0', 1729261890, NULL, NULL),
(2, 2, '1', 1, '1', '0', 1729261890, NULL, NULL);

DROP TABLE IF EXISTS `gac_token_external`;
CREATE TABLE IF NOT EXISTS `gac_token_external` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_disabled` enum('0','1') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `created_at` bigint NOT NULL,
  `updated_at` bigint DEFAULT NULL,
  `deleted_at` bigint DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Directorio de tokens para aplicaciones externa, (modifique esta tabla a su conveniencia)';

DROP TABLE IF EXISTS `gac_user`;
CREATE TABLE IF NOT EXISTS `gac_user` (
  `id` int NOT NULL AUTO_INCREMENT,
  `person_id` int NOT NULL,
  `username` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_attempt_count` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Conteo de intentos fallidos de sesión',
  `failed_attempt_date` bigint DEFAULT NULL COMMENT 'Fecha del último intento de sesión fallido',
  `last_login` bigint DEFAULT NULL,
  `last_login_ip` varchar(39) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_disabled` enum('0','1') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `created_at` bigint NOT NULL,
  `updated_at` bigint DEFAULT NULL,
  `deleted_at` bigint DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `person_id` (`person_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `gac_user` (`id`, `person_id`, `username`, `password`, `failed_attempt_count`, `failed_attempt_date`, `last_login`, `last_login_ip`, `is_disabled`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 1, 'admin', 'my_password_hash', 0, NULL, 1730405023, '127.0.0.1', '0', 1729261890, NULL, NULL);

ALTER TABLE `gac_module`
  ADD CONSTRAINT `gac_module_ibfk_1` FOREIGN KEY (`module_category_id`) REFERENCES `gac_module_category` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE `gac_module_access_restriction`
  ADD CONSTRAINT `gac_module_access_restriction_ibfk_1` FOREIGN KEY (`module_access_id`) REFERENCES `gac_module_access` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT `gac_module_access_restriction_ibfk_2` FOREIGN KEY (`restriction_id`) REFERENCES `gac_restriction` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE `gac_restriction`
  ADD CONSTRAINT `gac_restriction_ibfk_1` FOREIGN KEY (`restriction_category_id`) REFERENCES `gac_restriction_category` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE `gac_role_entity`
  ADD CONSTRAINT `gac_role_entity_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `gac_role` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE `gac_user`
  ADD CONSTRAINT `gac_user_ibfk_1` FOREIGN KEY (`person_id`) REFERENCES `gac_person` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
COMMIT;