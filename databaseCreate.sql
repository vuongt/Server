DROP DATABASE IF EXISTS appOrange;
CREATE DATABASE appOrange;
USE appOrange;
CREATE USER IF NOT EXISTS 'dty-orange'@'localhost' IDENTIFIED BY 'dty';
GRANT ALL PRIVILEGES ON appOrange.* TO 'dty-orange'@'localhost';

CREATE TABLE users (
	id MEDIUMINT NOT NULL AUTO_INCREMENT,
	first_name VARCHAR(255) NOT NULL,
	last_name VARCHAR(255) NOT NULL,
	tel VARCHAR(50) NOT NULL,
	password VARCHAR(255) NOT NULL,
	photo INT,
  token VARCHAR(255) DEFAULT '',
  status INT NOT NULL DEFAULT '1',
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (id)
)ENGINE=INNODB;

CREATE TABLE contacts (
    associated_user_id MEDIUMINT NOT NULL,
    name VARCHAR(50) NOT NULL,
    tel VARCHAR(50) NOT NULL,
    email VARCHAR(50),
    photo INT
)ENGINE=INNODB;

CREATE TABLE apps (
    id MEDIUMINT NOT NULL AUTO_INCREMENT,
    app_name VARCHAR(50) NOT NULL,
    creator_id MEDIUMINT NOT NULL,
    category VARCHAR(50) DEFAULT '',
    font VARCHAR(20) DEFAULT '',
    theme VARCHAR(20) DEFAULT '',
    background INT DEFAULT 0,
    icon INT DEFAULT 0,
    layout VARCHAR(20) DEFAULT '',
    description VARCHAR(2000) DEFAULT '',
    PRIMARY KEY (id)
)ENGINE=INNODB;

CREATE TABLE module_containers (
    id MEDIUMINT NOT NULL AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    app_id MEDIUMINT NOT NULL,
    type VARCHAR(50),
    PRIMARY KEY (id)
)ENGINE=INNODB;

CREATE TABLE vote_module (
    id MEDIUMINT NOT NULL AUTO_INCREMENT,
    title VARCHAR(50) NOT NULL,
    description VARCHAR(2000),
    vote_answer_id MEDIUMINT NULL,
    container_id MEDIUMINT DEFAULT 0,
    create_date DATETIME,
    expire_date DATETIME,
    PRIMARY KEY (id)

)ENGINE=INNODB;

CREATE TABLE vote_options (
    id MEDIUMINT NOT NULL AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    vote_id MEDIUMINT NOT NULL,
    num_votes int default 0,
    PRIMARY KEY (id)
)ENGINE=INNODB;

CREATE TABLE content_sharing_module (
    id INT NOT NULL AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    container_id INT NOT NULL,
    content_type VARCHAR(50),
    PRIMARY KEY (id)
)ENGINE=INNODB;

CREATE TABLE apps_users (
    user_id MEDIUMINT NOT NULL,
    app_id MEDIUMINT NOT NULL
);

CREATE TABLE apps_admins (
    admin_id MEDIUMINT NOT NULL,
    app_id MEDIUMINT NOT NULL
)ENGINE=INNODB;

CREATE TABLE media_contents(
    id INT NOT NULL AUTO_INCREMENT,
    path VARCHAR(1000),
    content_type VARCHAR(50),
    app_id INT,
    module_id INT,
    PRIMARY KEY (id)
)ENGINE=INNODB;

CREATE TABLE users_token (
  id_token MEDIUMINT NOT NULL AUTO_INCREMENT,
  tel VARCHAR(30) NOT NULL,
  token VARCHAR(200) NOT NULL,
  expire_date DATETIME,
  PRIMARY KEY (id_token)
)ENGINE=INNODB;


INSERT INTO users (first_name, last_name, tel,password)
    VALUES ('Anna','Dupont','06666','aaaa'),
            ('Bob','Dupont','07777','bbbb');

INSERT INTO apps (app_name, creator_id, description)
VALUES ('App1','1','description app1'),
        ('App2','2','description app2'),
        ('App3','1','description app3'),
        ('App4','1','description app4'),
        ('App5','2','description app5');

INSERT INTO apps_users (user_id,app_id)
VALUES ('1','1'),('2','1'),('2','2'),('1','3'),('1','4'),('2','4'),('2','5');

INSERT INTO apps_admins (admin_id,app_id)
VALUES ('1','1'),('2','2'),('1','3'),('1','4'),('2','5');

