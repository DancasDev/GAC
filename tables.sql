SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;


CREATE TABLE `gac_client` (
  `id` int(11) NOT NULL,
  `name` varchar(60) NOT NULL,
  `description` text DEFAULT NULL,
  `client_id` varchar(255) NOT NULL COMMENT 'Identificador público del cliente (similar a un username)',
  `client_secret` varchar(255) NOT NULL COMMENT 'Secreto del cliente, DEBE ser almacenado HASHEADO',
  `failed_attempt_count` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Conteo de intentos fallidos de sesión',
  `failed_attempt_date` bigint(20) DEFAULT NULL COMMENT 'Fecha del último intento de sesión fallido',
  `last_login` bigint(20) DEFAULT NULL,
  `last_login_ip` varchar(39) DEFAULT NULL,
  `is_disabled` enum('0','1') NOT NULL DEFAULT '0',
  `created_at` bigint(20) NOT NULL,
  `updated_at` bigint(20) DEFAULT NULL,
  `deleted_at` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Directorio de tokens para aplicaciones externa, (modifique esta tabla a su conveniencia)';

CREATE TABLE `gac_module` (
  `id` int(11) NOT NULL,
  `module_category_id` int(11) NOT NULL,
  `name` varchar(60) NOT NULL,
  `code` varchar(40) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `base_route` varchar(255) NOT NULL,
  `is_developing` enum('0','1') NOT NULL DEFAULT '1' COMMENT '0 = No, 1 = Sí',
  `is_disabled` enum('0','1') NOT NULL DEFAULT '0',
  `created_at` bigint(20) NOT NULL,
  `updated_at` bigint(20) DEFAULT NULL,
  `deleted_at` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE `gac_module_access` (
  `id` int(11) NOT NULL,
  `from_entity_type` enum('0','1','2') NOT NULL COMMENT 'Desde la entidad: 0 = Rol (acc_role), 1 = Usuario (acc_user), 2 = Token externo (gac_client)',
  `from_entity_id` int(11) NOT NULL,
  `to_entity_type` enum('0','1') NOT NULL COMMENT 'A la entidad: 0 = Categoría (acc_module_category), 1 = Módulo (acc_module)',
  `to_entity_id` int(11) NOT NULL,
  `feature` set('0','1','2','3','4','5') NOT NULL COMMENT 'Con acceso a las caracteristicas (acciones): 0 = Crear, 1 = Leer, 2 = Actualizar, 3 = Eliminar, 4 = Papelera (valor funciona en combinación con los valores 1, 2 y 3), 5 = Modo desarrollo',
  `level` enum('0','1','2') NOT NULL DEFAULT '1' COMMENT 'Con un nivel de acceso: 0 = Bajo, 1 = Normal, 2 = Alto',
  `is_disabled` enum('0','1') NOT NULL DEFAULT '0',
  `created_at` bigint(20) NOT NULL,
  `updated_at` bigint(20) DEFAULT NULL,
  `deleted_at` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `gac_module_access` (`id`, `from_entity_type`, `from_entity_id`, `to_entity_type`, `to_entity_id`, `feature`, `level`, `is_disabled`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, '0', 1, '0', 1, '0,1,2,3,4,5', '1', '0', 1726150616, NULL, NULL),
(2, '0', 1, '0', 2, '0,1,2,3,4,5', '1', '0', 1726150616, NULL, NULL),
(3, '0', 1, '0', 3, '0,1,2,3,4,5', '1', '0', 1726150616, NULL, NULL);

CREATE TABLE `gac_module_category` (
  `id` int(11) NOT NULL,
  `name` varchar(60) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `is_disabled` enum('0','1') NOT NULL DEFAULT '0',
  `created_at` bigint(20) NOT NULL,
  `updated_at` bigint(20) DEFAULT NULL,
  `deleted_at` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `gac_module_category` (`id`, `name`, `description`, `is_disabled`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'Sistema', 'Módulos relacionados a la administración del sistema sistema', '0', 1726150616, 1749667793, NULL),
(2, 'Usuario', 'Módulos relacionados a la administración de los registros relacionado al usuario (ejemplo: Datos personales).', '0', 1726150616, NULL, NULL),
(3, 'Directorios', 'Módulos relacionados a la administración de los directorios (personas, bancos, etc).', '0', 1726150616, NULL, NULL);

CREATE TABLE `gac_restriction_category` (
  `id` int(11) NOT NULL,
  `code` varchar(30) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `is_disabled` enum('0','1') NOT NULL DEFAULT '0',
  `created_at` bigint(20) NOT NULL,
  `updated_at` bigint(20) DEFAULT NULL,
  `deleted_at` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `gac_restriction_category` (`id`, `code`, `description`, `is_disabled`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'branch', NULL, '0', 1738853181, NULL, NULL),
(2, 'date', NULL, '0', 1738853181, NULL, NULL);

CREATE TABLE `gac_restriction_entity` (
  `id` int(11) NOT NULL,
  `from_entity_type` enum('0','1','2') DEFAULT NULL COMMENT 'Desde la entidad: 0 = Rol (acc_role), 1 = Usuario (acc_user), 2 = Cliente (gac_client), NULL = Todos',
  `from_entity_id` int(11) NOT NULL,
  `to_entity_type` enum('0','1') DEFAULT NULL COMMENT 'A la entidad: 0 = Categoría (acc_module_category), 1 = Módulo (acc_module), NULL = Todos',
  `to_entity_id` int(11) NOT NULL,
  `restriction_type_id` int(11) NOT NULL COMMENT 'Tipo de restricción',
  `data` text NOT NULL COMMENT 'Datos para la validación de la restricción.',
  `is_disabled` enum('0','1') NOT NULL DEFAULT '0',
  `created_at` bigint(20) NOT NULL,
  `updated_at` bigint(20) DEFAULT NULL,
  `deleted_at` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `gac_restriction_type` (
  `id` int(11) NOT NULL,
  `restriction_category_id` int(11) NOT NULL,
  `code` varchar(30) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `is_disabled` enum('0','1') NOT NULL DEFAULT '0',
  `created_at` bigint(20) NOT NULL,
  `updated_at` bigint(20) DEFAULT NULL,
  `deleted_at` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `gac_restriction_type` (`id`, `restriction_category_id`, `code`, `description`, `is_disabled`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 1, 'blacklist', NULL, '0', 1738853181, NULL, NULL),
(2, 1, 'whitelist', NULL, '0', 1738853181, NULL, NULL),
(3, 2, 'in_range', NULL, '0', 1738853181, NULL, NULL),
(4, 2, 'out_range', NULL, '0', 1738853181, NULL, NULL),
(5, 2, 'before', NULL, '0', 1738853181, NULL, NULL),
(6, 2, 'after', NULL, '0', 1738853181, NULL, NULL);

CREATE TABLE `gac_role` (
  `id` int(11) NOT NULL,
  `name` varchar(30) NOT NULL,
  `code` varchar(30) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `is_disabled` enum('0','1') NOT NULL DEFAULT '0',
  `created_at` bigint(20) NOT NULL,
  `updated_at` bigint(20) DEFAULT NULL,
  `deleted_at` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `gac_role` (`id`, `name`, `code`, `description`, `is_disabled`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'Administrador de sistema', 'system_administrator', NULL, '0', 1726150616, NULL, NULL),
(2, 'Supervisor de sistema', 'system_supervisor', NULL, '0', 1726150616, NULL, NULL);

CREATE TABLE `gac_role_entity` (
  `id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `entity_type` enum('1','2') NOT NULL COMMENT '1 = Usuario (acc_user), 2 = Token externo (gac_client)',
  `entity_id` int(11) NOT NULL,
  `priority` enum('0','1','2','3','4') NOT NULL DEFAULT '0' COMMENT 'Prioridad del rol: 0 = rol principal, cualquier otro valor diferente a 0 = rol secundario. El usuario solo puede tener un rol asociado por cada valor de este campo',
  `is_disabled` enum('0','1') NOT NULL DEFAULT '0',
  `created_at` bigint(20) NOT NULL,
  `updated_at` bigint(20) DEFAULT NULL,
  `deleted_at` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `gac_user` (
  `id` int(11) NOT NULL,
  `person_id` int(11) NOT NULL,
  `username` varchar(60) NOT NULL,
  `password` varchar(255) NOT NULL,
  `failed_attempt_count` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Conteo de intentos fallidos de sesión',
  `failed_attempt_date` bigint(20) DEFAULT NULL COMMENT 'Fecha del último intento de sesión fallido',
  `last_login` bigint(20) DEFAULT NULL,
  `last_login_ip` varchar(39) DEFAULT NULL,
  `last_login_type` enum('0','1') DEFAULT NULL COMMENT '0 = Manual, 1 = Google',
  `is_disabled` enum('0','1') NOT NULL DEFAULT '0',
  `created_at` bigint(20) NOT NULL,
  `updated_at` bigint(20) DEFAULT NULL,
  `deleted_at` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `glb_person` (
  `id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(120) NOT NULL,
  `email_verified_date` bigint(20) DEFAULT NULL,
  `google_id` varchar(45) DEFAULT NULL,
  `google_link_date` bigint(20) DEFAULT NULL,
  `sex` enum('0','1') DEFAULT NULL COMMENT '0 = Masculino, 1 = Femenino',
  `is_disabled` enum('0','1') NOT NULL DEFAULT '0',
  `created_at` bigint(20) NOT NULL,
  `updated_at` bigint(20) DEFAULT NULL,
  `deleted_at` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Directorio de personas, adapta esta tabla (nombre y campos) a tu conveniencia';


ALTER TABLE `gac_client`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `client_id` (`client_id`);

ALTER TABLE `gac_module`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `module_category_id` (`module_category_id`);

ALTER TABLE `gac_module_access`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `access_unique` (`from_entity_type`,`from_entity_id`,`to_entity_type`,`to_entity_id`),
  ADD KEY `from_entity_type` (`from_entity_type`),
  ADD KEY `to_entity_type` (`to_entity_type`);

ALTER TABLE `gac_module_category`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

ALTER TABLE `gac_restriction_category`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

ALTER TABLE `gac_restriction_entity`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `restriction_unique` (`from_entity_type`,`from_entity_id`,`to_entity_type`,`to_entity_id`),
  ADD KEY `from_entity_type` (`from_entity_type`),
  ADD KEY `to_entity_type` (`to_entity_type`),
  ADD KEY `restriction_type_id` (`restriction_type_id`);

ALTER TABLE `gac_restriction_type`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code_unique` (`restriction_category_id`,`code`),
  ADD KEY `restriction_category_id` (`restriction_category_id`);

ALTER TABLE `gac_role`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

ALTER TABLE `gac_role_entity`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `role_unique` (`role_id`,`entity_type`,`entity_id`),
  ADD UNIQUE KEY `priority_unique` (`entity_type`,`entity_id`,`priority`),
  ADD KEY `role_id` (`role_id`),
  ADD KEY `entity_type` (`entity_type`,`entity_id`);

ALTER TABLE `gac_user`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `person_id` (`person_id`);

ALTER TABLE `glb_person`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `google_id` (`google_id`);


ALTER TABLE `gac_client`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `gac_module`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

ALTER TABLE `gac_module_access`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

ALTER TABLE `gac_module_category`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

ALTER TABLE `gac_restriction_category`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

ALTER TABLE `gac_restriction_entity`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `gac_restriction_type`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

ALTER TABLE `gac_role`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

ALTER TABLE `gac_role_entity`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `gac_user`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `glb_person`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;


ALTER TABLE `gac_module`
  ADD CONSTRAINT `gac_module_ibfk_1` FOREIGN KEY (`module_category_id`) REFERENCES `gac_module_category` (`id`);

ALTER TABLE `gac_restriction_entity`
  ADD CONSTRAINT `gac_restriction_entity_ibfk_1` FOREIGN KEY (`restriction_type_id`) REFERENCES `gac_restriction_type` (`id`);

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