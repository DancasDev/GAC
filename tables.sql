SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;


DROP TABLE IF EXISTS `gac_client`;
CREATE TABLE IF NOT EXISTS `gac_client` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `client_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Identificador público del cliente (similar a un username)',
  `client_secret` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Secreto del cliente, DEBE ser almacenado HASHEADO',
  `failed_attempt_count` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Conteo de intentos fallidos de sesión',
  `failed_attempt_date` bigint DEFAULT NULL COMMENT 'Fecha del último intento de sesión fallido',
  `last_login` bigint DEFAULT NULL,
  `last_login_ip` varchar(39) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_disabled` enum('0','1') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `created_at` bigint NOT NULL,
  `updated_at` bigint DEFAULT NULL,
  `deleted_at` bigint DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `client_id` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Directorio de tokens para aplicaciones externa, (modifique esta tabla a su conveniencia)';

DROP TABLE IF EXISTS `gac_module`;
CREATE TABLE IF NOT EXISTS `gac_module` (
  `id` int NOT NULL AUTO_INCREMENT,
  `module_category_id` int NOT NULL,
  `name` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `base_route` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_developing` enum('0','1') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1' COMMENT '0 = No, 1 = Sí',
  `is_disabled` enum('0','1') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `created_at` bigint NOT NULL,
  `updated_at` bigint DEFAULT NULL,
  `deleted_at` bigint DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `module_category_id` (`module_category_id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `gac_module` (`id`, `module_category_id`, `name`, `code`, `description`, `base_route`, `is_developing`, `is_disabled`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 2, 'Mi perfil', 'my_profile', 'Permite al usuario editar sus datos personales.', '/my/profile', '0', '0', 1726150616, NULL, NULL),
(2, 2, 'Mi usuario', 'my_user', 'Permite al usuario editar sus datos de acceso (usuario, contraseña, etc.).', '/my/user', '0', '0', 1726150616, NULL, NULL),
(3, 1, 'Usuarios', 'users', 'Permite la administración de los usuarios del sistema.', '/users', '0', '0', 1726150616, 1749664403, NULL),
(4, 1, 'Accesos de usuario', 'user_access', 'Permite la administración del acceso de los usuarios  al sistema.', '/users/{:user_id}/access', '0', '0', 1726150616, NULL, NULL),
(5, 1, 'Roles de usuario', 'user_roles', 'Permite la administración de roles de usuarios.', '/users/{:user_id}/roles', '0', '0', 1726150616, NULL, NULL),
(6, 1, 'Roles', 'roles', 'Permite la administración de roles.', '/roles', '0', '0', 1726150616, NULL, NULL),
(7, 1, 'Accesos de roles', 'role_access', 'Permite la administración del acceso de los roles.', '/roles/{:role_id}/access', '0', '0', 1726150616, NULL, NULL),
(8, 1, 'Módulos', 'modules', 'Permite la administración de módulos del sistema.', '/modules', '0', '0', 1726150616, NULL, NULL),
(9, 1, 'Módulos categorías', 'modules_categories', 'Permite la administración de las categorías de los módulos.', '/modules/categories', '0', '0', 1726150616, NULL, NULL),
(10, 3, 'Directorio de personas', 'directory_people', 'Permite la administración del directorio de personas.', '/directories/people', '0', '0', 1726150616, NULL, NULL),
(11, 1, 'Usuarios de roles', 'role_users', 'Permite ver los usuarios que posee un rol.', '/roles/{:role_id}/users', '0', '0', 1726150616, NULL, NULL),
(12, 1, 'Clientes', 'clients', 'Permite la administración de los clientes (Sistemas de información externos) del sistema.', '/clients', '0', '0', 1726150616, NULL, NULL),
(13, 1, 'Roles de un cliente', 'client_roles', 'Permite la administración de los roles relacionado a un cliente.', '/clients/{:client_id}/roles', '0', '0', 1726150616, NULL, NULL),
(14, 1, 'Accesos de un cliente', 'client_access', 'Permite la administración de los accesos relacionado a un cliente.', '/clients/{:client_id}/access', '0', '0', 1726150616, NULL, NULL);

DROP TABLE IF EXISTS `gac_module_access`;
CREATE TABLE IF NOT EXISTS `gac_module_access` (
  `id` int NOT NULL AUTO_INCREMENT,
  `from_entity_type` enum('0','1','2') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Desde la entidad: 0 = Rol (acc_role), 1 = Usuario (acc_user), 2 = cliente (gac_client)',
  `from_entity_id` int NOT NULL,
  `to_entity_type` enum('0','1') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'A la entidad: 0 = Categoría (acc_module_category), 1 = Módulo (acc_module)',
  `to_entity_id` int NOT NULL,
  `feature` set('0','1','2','3','4','5') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Con acceso a las caracteristicas (acciones): 0 = Crear, 1 = Leer, 2 = Actualizar, 3 = Eliminar, 4 = Papelera (valor funciona en combinación con los valores 1, 2 y 3), 5 = Modo desarrollo',
  `level` enum('0','1','2') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1' COMMENT 'Con un nivel de acceso: 0 = Bajo, 1 = Normal, 2 = Alto',
  `is_disabled` enum('0','1') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `created_at` bigint NOT NULL,
  `updated_at` bigint DEFAULT NULL,
  `deleted_at` bigint DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `access_unique` (`from_entity_type`,`from_entity_id`,`to_entity_type`,`to_entity_id`),
  KEY `from_entity_type` (`from_entity_type`),
  KEY `to_entity_type` (`to_entity_type`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `gac_module_access` (`id`, `from_entity_type`, `from_entity_id`, `to_entity_type`, `to_entity_id`, `feature`, `level`, `is_disabled`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, '0', 1, '0', 1, '0,1,2,3,4,5', '1', '0', 1726150616, NULL, NULL),
(2, '0', 1, '0', 2, '0,1,2,3,4,5', '1', '0', 1726150616, NULL, NULL),
(3, '0', 1, '0', 3, '0,1,2,3,4,5', '1', '0', 1726150616, NULL, NULL);

DROP TABLE IF EXISTS `gac_module_category`;
CREATE TABLE IF NOT EXISTS `gac_module_category` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_disabled` enum('0','1') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `created_at` bigint NOT NULL,
  `updated_at` bigint DEFAULT NULL,
  `deleted_at` bigint DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `gac_module_category` (`id`, `name`, `description`, `is_disabled`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'Sistema', 'Módulos relacionados a la administración del sistema sistema', '0', 1726150616, 1749667793, NULL),
(2, 'Usuario', 'Módulos relacionados a la administración de los registros relacionado al usuario (ejemplo: Datos personales).', '0', 1726150616, NULL, NULL),
(3, 'Directorios', 'Módulos relacionados a la administración de los directorios (personas, bancos, etc).', '0', 1726150616, NULL, NULL);

DROP TABLE IF EXISTS `gac_restriction`;
CREATE TABLE IF NOT EXISTS `gac_restriction` (
  `id` int NOT NULL AUTO_INCREMENT,
  `entity_type` enum('0','1','2') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '0 = Rol (acc_role), 1 = Usuario (acc_user), 2 = Cliente (gac_client), NULL = Todos',
  `entity_id` int NOT NULL,
  `restriction_type_id` int NOT NULL COMMENT 'Tipo de restricción',
  `data` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Datos para la validación de la restricción.',
  `is_disabled` enum('0','1') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `created_at` bigint NOT NULL,
  `updated_at` bigint DEFAULT NULL,
  `deleted_at` bigint DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `restriction_unique` (`entity_type`,`entity_id`,`restriction_type_id`),
  KEY `entity_type` (`entity_type`),
  KEY `restriction_type_id` (`restriction_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `gac_restriction_category`;
CREATE TABLE IF NOT EXISTS `gac_restriction_category` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_disabled` enum('0','1') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `created_at` bigint NOT NULL,
  `updated_at` bigint DEFAULT NULL,
  `deleted_at` bigint DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `gac_restriction_category` (`id`, `name`, `code`, `description`, `is_disabled`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'Por entidad', 'by_entity', NULL, '0', 1738853181, NULL, NULL),
(2, 'Por fecha', 'by_date', NULL, '0', 1738853181, NULL, NULL);

DROP TABLE IF EXISTS `gac_restriction_type`;
CREATE TABLE IF NOT EXISTS `gac_restriction_type` (
  `id` int NOT NULL AUTO_INCREMENT,
  `restriction_category_id` int NOT NULL,
  `name` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_disabled` enum('0','1') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `created_at` bigint NOT NULL,
  `updated_at` bigint DEFAULT NULL,
  `deleted_at` bigint DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code_unique` (`restriction_category_id`,`code`),
  KEY `restriction_category_id` (`restriction_category_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `gac_restriction_type` (`id`, `restriction_category_id`, `name`, `code`, `description`, `is_disabled`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 1, 'Sucursal', 'branch', NULL, '0', 1738853181, NULL, NULL),
(2, 2, 'En el rango', 'in_range', NULL, '0', 1738853181, NULL, NULL),
(3, 2, 'Fuera del rango', 'out_range', NULL, '0', 1738853181, NULL, NULL),
(4, 2, 'Antes de', 'before', NULL, '0', 1738853181, NULL, NULL),
(5, 2, 'Despues de', 'after', NULL, '0', 1738853181, NULL, NULL);

DROP TABLE IF EXISTS `gac_role`;
CREATE TABLE IF NOT EXISTS `gac_role` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_disabled` enum('0','1') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
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
  `entity_type` enum('1','2') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '1 = Usuario (acc_user), 2 = cliente (gac_client)',
  `entity_id` int NOT NULL,
  `priority` enum('0','1','2','3','4') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0' COMMENT 'Prioridad del rol: 0 = rol principal, cualquier otro valor diferente a 0 = rol secundario. El usuario solo puede tener un rol asociado por cada valor de este campo',
  `is_disabled` enum('0','1') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `created_at` bigint NOT NULL,
  `updated_at` bigint DEFAULT NULL,
  `deleted_at` bigint DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_unique` (`role_id`,`entity_type`,`entity_id`),
  UNIQUE KEY `priority_unique` (`entity_type`,`entity_id`,`priority`),
  KEY `role_id` (`role_id`),
  KEY `entity_type` (`entity_type`,`entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `gac_user`;
CREATE TABLE IF NOT EXISTS `gac_user` (
  `id` int NOT NULL AUTO_INCREMENT,
  `person_id` int NOT NULL,
  `username` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_attempt_count` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Conteo de intentos fallidos de sesión',
  `failed_attempt_date` bigint DEFAULT NULL COMMENT 'Fecha del último intento de sesión fallido',
  `last_login` bigint DEFAULT NULL,
  `last_login_ip` varchar(39) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_login_type` enum('0','1') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '0 = Manual, 1 = Google',
  `is_disabled` enum('0','1') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `created_at` bigint NOT NULL,
  `updated_at` bigint DEFAULT NULL,
  `deleted_at` bigint DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `person_id` (`person_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `glb_person`;
CREATE TABLE IF NOT EXISTS `glb_person` (
  `id` int NOT NULL AUTO_INCREMENT,
  `first_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_date` bigint DEFAULT NULL,
  `google_id` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `google_link_date` bigint DEFAULT NULL,
  `sex` enum('0','1') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '0 = Masculino, 1 = Femenino',
  `is_disabled` enum('0','1') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `created_at` bigint NOT NULL,
  `updated_at` bigint DEFAULT NULL,
  `deleted_at` bigint DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `google_id` (`google_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Directorio de personas, adapta esta tabla (nombre y campos) a tu conveniencia';


ALTER TABLE `gac_module`
  ADD CONSTRAINT `gac_module_ibfk_1` FOREIGN KEY (`module_category_id`) REFERENCES `gac_module_category` (`id`);

ALTER TABLE `gac_restriction`
  ADD CONSTRAINT `gac_restriction_ibfk_1` FOREIGN KEY (`restriction_type_id`) REFERENCES `gac_restriction_type` (`id`);

ALTER TABLE `gac_restriction_type`
  ADD CONSTRAINT `gac_restriction_type_ibfk_1` FOREIGN KEY (`restriction_category_id`) REFERENCES `gac_restriction_category` (`id`);

ALTER TABLE `gac_role_entity`
  ADD CONSTRAINT `gac_role_entity_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `gac_role` (`id`);

ALTER TABLE `gac_user`
  ADD CONSTRAINT `gac_user_ibfk_1` FOREIGN KEY (`person_id`) REFERENCES `glb_person` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;