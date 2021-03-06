# Application server

## Introduction

The application server is basically a RESTful API which communicates with the application on each user’s phone. The application on client side sent HTTP request (in this project we only use POST and GET request) and the server answers in JSON form, except when it sends images and documents.


The API is written in PHP using the Slim Framework. Slim is a micro framework PHP which helps us easily write APIs. You can read more about Slim Framework on the  Slim Home Page: <https://www.slimframework.com> The version we used in this project is Slim 3, whose documentation can be found here : <https://www.slimframework.com/docs/>. (Attention to not be confused with the documentation for Slim 2). 


We also use Composer as a dependency manager for the project. With composer we can easily install libraries and keep track of each library’s version. Every time we install a new library, a new dependency is written in composer.json and and the library is put in the vendor folder. This folder is ignored when pulling/pushing to git repository but you can easily install all necessary library with one simple command. Please refer to section 7.How to deloy server for further details. Read more about composer on Composer Home Page : <https://getcomposer.org/>.


We use MySQL for the application database. Read more about the database structure on section 5. Database structure


Finally, you can get the server code source by cloning the repository : <https://github.com/vuongt/Server.git> . Let’s explore the directory structure !


##Server's directory architecture
The root directory contains 2 folders and a .sql file:

* The **res** folder : where we stock binary resources like image, videos, documents,...
* The **src** folder : contains the code source
* **databaseCreate.sql** : a script to initialize the database schema.

In the **src** folder : 

* The **public** folder : the enter point of the server (root document of Apache). It contains: 
	* **index.php** file where Slim and logger are configured. 
	* **.htaccess** : Configuration for Apache server.
	
* The **classes** folder : contains all helper classes.
	* **config.php** : configuration constants for mysql, json web token and file handler.
	* **DbConnect.php** : php class which initializes an instance of mysqli with the configuration written in config.php
	* **DbHandler.php** : this class establishes a connection to the MySQL database using mysqli instance create by DbConnect. The constructor of this class take a logger in argument to create a dedicated log channel. The role of this class in the project is to hold all functions which access to database. For the role of each function, please refers to the comments in code source.
	* **FileHandler.php** : this class holds all functions that do files manipulation (get files, save files, delete files,...) The role of each function is explain in the comment section in code source.
	* **PassHash.php** : this class holds function to hash and check a password.
	* **tokenHandler.php** : this file holds functions that manipulates json web token using firebase/php-jwt library : extract token from header, create a new token with crypted user’s information, get user’s id from a token.

* The **logs** folder : contains the app.log file where real-time logs are written.
* Composer-related folder and files :
	* The folder **vendor** is generated by composer. It holds all the library using by the server.
	* composer.phar, composer.json, conposer.lock
	
In the **res** folder : 

* The **app** folder contains icon and background of each user's application. Each folder's name is the id of the application.
* The **module** folder contains images and documents of the media sharing module. Each folder's name is the id of a module. In side the folder of each module there acan be 2 sub folders : **image** for images and **document** for other kind of document.


## Authentication stategy
In this project we use the Json Web Token for authentication. Each time the user sign up or sign in, the server generate a token which holds user's id and an expiration date (with the help of functions in the tokenHandler.php). The application on the user side uses this token on Authorization header for all HTTP request afterward until he/she logout. 

Everytime the server recieves a request, it checks the expiration date and authentication of the token in the Authorization header then extracts user's id if necessary. For example when an user creates a new application or writes a comment, his identity is extracted from the token that goes along with the request.

The Secret word for crypting and the expiration date of each token is defined on **config.php**

##Logging
We use monolog to handle logging in this project. The logs are written in **/logs/app.log**

There are 3 log channel : 

* **Main** : Logs come from index.php
* **FileHandler** : Logs come from FileHandler.php
* **DbHandler** : Logs come from DbHandler.php

##The database structure.
The database schema is defined by the file **databaseCreate.sql**
In this script, we create a new database named **appOrange** and grant all priviledges to user **dty-orange** identified by **dty**. This configuration is coherent with the definition in config.php so that php has the access to the right database. 

We also defined all the tables necessary for the aplication. For the details of each table, please refer to the comments in **databaseCreate.sql**

##HTTP services
Tables of HTTP services

Method | URL                              | Parameters
-------|----------------------------------|------------------
POST   | /signin                          |tel, password
POST   | /signup| tel, password, firstName, lastName
GET   | /user|
GET   | /getMedia|id
POST   | /createApp|name, category, font, theme, layout, description, icon , background, iconFile, backgroundFile
POST   | /createApp/default|name, category, font, theme, layout, description, icon=default , background= default
GET   | /deleteApp|appId
GET   | /loadApp|appId
POST   | /updateApp|appId, newName
GET   | /app/addUser|appId, tel
GET   | /app/removeUser|appId, userId
GET   | /app/createContainer|appId, name, type
POST   | /app/upload |appId, type
GET   | /container/loadDetails|containerId
POST   | /container/update|containerId, newName
GET   | /container/delete|containerId
GET   | /container/addModule/media|containerId, name, type
POST   | /module/media/upload|moduleId, type
GET   | /module/media/load|id, type
POST   | /module/media/update|moduleId, newName
GET   | /module/media/deleteModule|moduleId
GET   | /module/media/deleteFile|id
POST   | /module/vote/addVote|title, description, container_id
GET   | /module/vote/load|id
GET   | /module/vote/addOption|moduleId, option
GET   | /module/vote/increment|optionId, moduleId, userId
POST   | /module/vote/update|moduleId, newName
POST   | /module/vote/usersVoters |id
GET   | /module/vote/deleteVote|voteId
POST   | /module/budget/addCost|containerId, description, value
POST   | /module/budget/update|expenseId, newDescription, newValue
GET   | /module/budget/deleteExpense|expenseId
POST   | /module/map/add|containerId, description, address, lat, lng
GET   | /module/map/delete|moduleId
POST   | /module/calendar/add|containerId, title, date, time
GET   | /module/calendar/delete|moduleId
POST   | /module/forum/addForum|title, description, container_id
GET   | /module/forum/deleteForum|topicId
POST   | /module/forum/update|topicId, newTopicName
GET   | /module/forum/topic/loadDetails|topicId
POST   | /module/forum/topic/addComment|comment_text, creator, topic_id
GET   | /module/forum/topic/deleteComment|commentId
POST   | /module/forum/topic/update|commentId, newComment

##How to deploy the server
This section explains how to deloy the server on a Linux machine using Apache.

* Clone the git repository of the project from : <https://github.com/vuongt/Server.git>. From now on the instructions is written as if you clone the repository to **/var/www/Server/**
* Install Apache, PHP and Mysql on your Linux machine. There isn't a particular requirement for the version of each component, but we recommend using apache2 and PHP 7. You have to install all helper package for php too (for instance in unbuntu 16.0:

		sudo apt-get install php libapache2-mod-php php-mcrypt php-mysql

* Go to the folder **/var/www/Server/src/** and install all dependencies by this command : 

		php composer.phar install
* Initialize the database : we have prepare the script for initializing the schema of the database in **databaseCreate.sql**. You just have to connect as root while in **/var/www/Server** then execute 

		SOURCE databaseCreate.sql;

* Configure Apache : 
	* point the Apache document root to the folder **/var/www/Server/src/public**. (For example in Ubuntu, you'll have to edit too files in the directory **/etc/apache2/sites-available** : *default-ssl.conf* and *000-default.conf*) 
	* set your Apache virtual host listening to port 8080. if the port 8080 on your machine is in busy, you can chose any port you want, but then you'll have to change API_END_POINT url set in the application too (applicaiton on user side)
	* make sure that Apache virtual host is configured with **AllowOverride All** for your directory (in apache2.conf):
	
	```
	<Directory /var/www/Server/src/public/>
		Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
	</Directory>
	```
* Finally, make sur that the directory res and logs have all the read and write rights so that the php application can retrieve and save files, as well as the logger can write logs to **app.log**. For example you can set:

		chmod 777 app.log
		
Please verify that in **/var/Server/res/app/default** you have 2 files named **icon.png** and **background.png** Theses files are used to be the default icon and background of an application. If they're not there, add 2 new files of your choice with the names mentioned above in directory **/var/Server/res/app/default** !

The server is now up and running !

	

