CREATE TABLE user_profiles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    area ENUM('comercial','tecnico','administracion','gerencial') NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE profile_module_permissions (
    profile_id INT UNSIGNED NOT NULL,
    module_key VARCHAR(60) NOT NULL,
    can_view TINYINT(1) NOT NULL DEFAULT 0,
    can_manage TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY(profile_id,module_key),
    CONSTRAINT fk_profile_module_profile FOREIGN KEY(profile_id) REFERENCES user_profiles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE users
    ADD COLUMN profile_id INT UNSIGNED NULL AFTER role,
    ADD INDEX idx_users_profile_id (profile_id),
    ADD CONSTRAINT fk_users_profile FOREIGN KEY(profile_id) REFERENCES user_profiles(id) ON DELETE SET NULL;
