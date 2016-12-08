 <?php
 use Firebase\JWT\JWT;

 /**
 * Classe pour gérer toutes les opérations de db
 * Cette classe aura les méthodes CRUD pour les tables de base de données
 *

 */
class DbHandler {

    private $conn;
    private $log;
    function __construct($logger) {
        require_once dirname(__FILE__) . '/DbConnect.php';
        include_once dirname(__FILE__) . '/config.php';
        include_once dirname(__FILE__) . '/tokenHandler.php';
        //Open connexion db
        $db = new DbConnect();
        $this->conn = $db->connect();
        // create a log channel
        $this->log = $logger;
    }

    //===========Authentication=========================
    //===========and functions for general purpose==========

    /**
     * @param $firstName
     * @param $lastName
     * @param $tel
     * @param $password
     * @return int authentication code
     * Insert a new user to database with hashed password
     */
    public function signUp($firstName, $lastName, $tel, $password) {
        require_once 'PassHash.php';
        if (!$this->isUserExists($tel)) {
            //Generate a hashed password
            $password_hash = PassHash::hash($password);

            //insert user
            $stmt = $this->conn->prepare("INSERT INTO users(first_name, last_name, tel, password, status) values(?, ?, ?, ?, 1)");
            $stmt->bind_param('ssss', $firstName, $lastName, $tel, $password_hash);
            $result = $stmt->execute();
            $stmt->store_result();
            $userId = $stmt->insert_id;
            $stmt->close();

            //Verify if the insertion is succeeded
            //see config.php
            if ($result) {
                return USER_CREATED_SUCCESSFULLY;
            } else {
                return USER_CREATE_FAILED;
            }
        } else {
            return USER_ALREADY_EXISTED;
        }

    }

    /**
     * @param $tel
     * @return bool
     * Check if the tel number has already been registered
     */
    private function isUserExists($tel) {
        $stmt = $this->conn->prepare("SELECT id from users WHERE tel = ?");
        $stmt->bind_param("s", $tel);
        $stmt->execute();
        $stmt->store_result();
        $num_rows = $stmt->num_rows;
        $stmt->close();
        return $num_rows > 0;
    }

    /**
     * @param $tel
     * @param $password
     * @return int Authentication status
     * Check if the password is correct for this phone number
     */
    public function signIn($tel, $password) {
        // Obtention de l'utilisateur par tel
        $stmt = $this->conn->prepare("SELECT password FROM users WHERE tel = ?");

        $stmt->bind_param("s", $tel);

        $stmt->execute();

        $stmt->bind_result($password_hash);

        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            // Found user with tel
            // verify password
            $stmt->fetch();

            $stmt->close();

            if (PassHash::check_password($password_hash, $password)) {
                return AUTHENTICATE_SUCCESS;
            } else {
                return WRONG_PASSWORD;
            }
        } else {
            $stmt->close();
            //user doesn't exist
            return USER_NON_EXIST;
        }
    }

    /**
     * @param $tel
     * @return null or userId
     * get user's Id with a given phone number
     */
    public function getUserIdByTel($tel){
        $stmt = $this->conn->prepare("SELECT id FROM users WHERE tel = ?");
        $stmt->bind_param("s", $tel);
        if ($stmt->execute()) {
            $stmt->bind_result($user_id);
            $stmt->fetch();
            $stmt->close();
            return $user_id;
        } else {
            return NULL;
        }
    }

    /**
     * @param $tel
     * @return array user's information
     * get user's full information given his phone number
     */
    public function getUserByTel($tel) {
        $user = array();
        $this->conn->autocommit(FALSE);
        $stmt = $this->conn->prepare("SELECT id, first_name, last_name, tel, token, status, created_at FROM users WHERE tel = ?");
        $stmt->bind_param("s", $tel);
        if ($stmt->execute()) {
            $stmt->bind_result($id, $firstName, $lastName, $tel, $token, $status, $created_at);
            $stmt->fetch();
            $user["id"]= $id;
            $user["firstName"] = $firstName;
            $user["lastName"] = $lastName;
            $user["tel"] = $tel;
            $user["token"] = $token;
            $user["status"] = $status;
            $user["created_at"] = $created_at;
            $stmt->close();
        } else {
            return NULL;
        }

        $stmt_2= $this->conn->prepare("SELECT apps.id, apps.app_name, apps.icon FROM apps_users INNER JOIN apps ON apps_users.app_id = apps.id WHERE apps_users.user_id = ?");
        $stmt_2->bind_param("s", $user["id"]);
        if($stmt_2->execute()){
            $apps_users = array();
            $stmt_2->bind_result($app_id, $app_name,$app_icon);
            while($stmt_2->fetch()){
                $apps_users[] = array("id"=>$app_id, "name" =>$app_name, "icon"=>$app_icon);
            }
            $user["sharedApps"] = $apps_users;
            $stmt_2->close();
        } else {
            return $user;
        }

        $stmt_1= $this->conn->prepare("SELECT apps.id, apps.app_name, apps.icon FROM apps_admins INNER JOIN apps ON apps_admins.app_id = apps.id WHERE apps_admins.admin_id = ?");
        $stmt_1->bind_param("s", $user["id"]);
        if($stmt_1->execute()){
            $apps_admins = array();
            $stmt_1->bind_result($app_id, $app_name, $app_icon);
            while($stmt_1->fetch()){
                $apps_admins[] = array("id"=>$app_id, "name" =>$app_name, "icon"=>$app_icon);
            }
            $user["createdApps"] = $apps_admins;
            $stmt_1->close();
        } else {
            return $user;
        }
        return $user;
    }

    /**
     * @param $id
     * @return array user's information
     * get user's full information given his id
     */
    public function getUserById($id) {
        $user = array();
        $this->conn->autocommit(FALSE);
        $stmt = $this->conn->prepare("SELECT id, first_name, last_name, tel, token, status, created_at FROM users WHERE id = ?");
        $stmt->bind_param("s", $id);
        if ($stmt->execute()) {
            $stmt->bind_result($id, $firstName, $lastName, $tel, $token, $status, $created_at);
            $stmt->fetch();
            $user["id"]= $id;
            $user["firstName"] = $firstName;
            $user["lastName"] = $lastName;
            $user["tel"] = $tel;
            $user["token"] = $token;
            $user["status"] = $status;
            $user["created_at"] = $created_at;
            $stmt->close();
        } else {
            return NULL;
        }

        $stmt_2= $this->conn->prepare("SELECT apps.id, apps.app_name, apps.icon FROM apps_users INNER JOIN apps ON apps_users.app_id = apps.id WHERE apps_users.user_id = ?");
        $stmt_2->bind_param("s", $user["id"]);
        if($stmt_2->execute()){
            $apps_users = array();
            $stmt_2->bind_result($app_id, $app_name,$app_icon);
            while($stmt_2->fetch()){
                $apps_users[] = array("id"=>$app_id, "name" =>$app_name, "icon"=>$app_icon);
            }
            $user["sharedApps"] = $apps_users;
            $stmt_2->close();
        } else {
            return $user;
        }

        $stmt_1= $this->conn->prepare("SELECT apps.id, apps.app_name, apps.icon FROM apps_admins INNER JOIN apps ON apps_admins.app_id = apps.id WHERE apps_admins.admin_id = ?");
        $stmt_1->bind_param("s", $user["id"]);
        if($stmt_1->execute()){
            $apps_admins = array();
            $stmt_1->bind_result($app_id, $app_name,$app_icon);
            while($stmt_1->fetch()){
                $apps_admins[] = array("id"=>$app_id, "name" =>$app_name, "icon"=>$app_icon);
            }
            $user["createdApps"] = $apps_admins;
            $stmt_1->close();
        } else {
            return $user;
        }
        return $user;
    }

    /**
     * @param $id : id of the file
     * @return bool
     * get the path of a file
     */
    public function getMediaPath($id){
        $this->log->addInfo("getMediaPath of ".$id);
        $stmt = $this->conn->prepare("SELECT path FROM media_contents WHERE id = ?");
        $stmt->bind_param("i",$id);
        if($stmt->execute()){
            $stmt->bind_result($path);
            $stmt->fetch();
            $stmt->close();
            return $path;
        }
        return FALSE;
    }

    /**
     * @param $id
     * @return bool
     * delete a file in the database. The file isn't actually deleted, only its path.
     */
    public function deleteFile($id){
        $stmt1 = $this->conn->prepare("DELETE FROM media_contents WHERE id = ?");
        $stmt1->bind_param("i", $id);
        if ($stmt1->execute()) {
            $stmt1->close();
            $this->log->addInfo("Deleted file with id ".$id. " from media_contents");
        } else {
            return false;
        }
        return true;
    }

//============Application global action==========

     /**
      * @param $name
      * @param $creatorId
      * @param $category
      * @param $font
      * @param $theme
      * @param $layout
      * @param $description
      * @return int|null : the id of the application
      * create a new application without information about icon and background
      */
    public function createAppWithoutMedia($name, $creatorId,$category,$font,$theme,$layout,$description){
        $stmt = $this->conn->prepare("INSERT INTO apps (app_name,creator_id,category,font,theme,layout,description) VALUES (?,?,?,?,?,?,?)");
        $stmt->bind_param("sisssss", $name,$creatorId,$category,$font,$theme,$layout,$description);
        if ($stmt->execute()) {
            $appId = $stmt->insert_id;
            $stmt->close();
        } else {
            return NULL;
        }
        return $appId;
    }

     /**
      * @param $appId : the application that the file is associated to
      * @param $path : the path of the file
      * @param $type :
      * @param $role : is it the icon or the background of the application ?
      * @return int|null
      * save a file to an application as an icon or a background
      */
    public function saveFileToApp($appId, $path, $type, $role){
        $this->log->addInfo("addAppRes of role ".$role." for app number ".$appId.", path: ".$path);
        //save the path to the media_contents table the retrieve the inserted id
        $stmt = $this->conn->prepare("INSERT INTO media_contents (path, content_type, app_id) VALUES (?,?,?)");
        $stmt->bind_param("ssi",$path, $type,$appId);
        if ($stmt->execute()) {
            $id = $stmt->insert_id;
            $stmt->close();
        } else {
            return NULL;
        }
        // save the id of the file to table apps
        if ($role=="icon"){
            $stmt1 = $this->conn->prepare('UPDATE apps SET icon = ? WHERE id = ?');
            $stmt1->bind_param("ii", $id, $appId);
            if ($stmt1->execute()) {
                $stmt1->close();
            } else {
                return NULL;
            }
        }
        if ($role=="background"){
            $stmt2 = $this->conn->prepare('UPDATE apps SET background = ? WHERE id = ?');
            $stmt2->bind_param("ii", $id, $appId);
            if ($stmt2->execute()) {
                $stmt2->close();
            } else {
                return NULL;
            }
        }
        return $id;
    }

     /**
      * @param $appId
      * @return bool
      * delete an application and all the container belong to it.
      * TODO delete all the module and contents belong to each container
      */
    public function deleteApp($appId){
        $stmt = $this->conn->prepare("DELETE FROM apps WHERE id = ?");
        $stmt->bind_param("i", $appId);
        if ($stmt->execute()) {
            $stmt->close();
            $this->log->addInfo("Deleted app number ".$appId. " from apps");
        } else {
            return false;
        }
        $stmt1 = $this->conn->prepare("DELETE FROM module_containers WHERE app_id = ?");
        $stmt1->bind_param("i", $appId);
        if ($stmt1->execute()) {
            $stmt1->close();
            $this->log->addInfo("Deleted container of app ".$appId. " from module_containers");
        }else {
            return false;
        }
        //TODO delete modules
        return true;
    }

     /**
      * @param $appId
      * @param $newName
      * @return bool
      * Update the name of an application
      */
    public function updateApp($appId, $newName){
        $stmt = $this->conn->prepare("UPDATE apps SET app_name = ? WHERE id = ?");
        $stmt->bind_param("si", $newName, $appId);
        if ($stmt->execute()) {
            $stmt->close();
            return true;
        } else {
            return false;
        }
    }

     /**
      * @param $appId
      * @return bool
      * create a det of default containers for a given app (use for templates)
      */
    public function createDefaultContainers($appId){
        $this->log->addInfo("creating defaults containers");
        $this->conn->query("INSERT INTO module_containers (name,type,app_id) VALUES ('Media Box','media',".$appId.")");
        $this->conn->query("INSERT INTO module_containers (name,type,app_id) VALUES ('Poll Box','poll',".$appId.")");
        $this->conn->query("INSERT INTO module_containers (name,type,app_id) VALUES ('Calendar','calendar',".$appId.")");
        $this->conn->query("INSERT INTO module_containers (name,type,app_id) VALUES ('Budget Management','budget',".$appId.")");
        $this->conn->query("INSERT INTO module_containers (name,type,app_id) VALUES ('Chat Box','chat',".$appId.")");
        $this->conn->query("INSERT INTO module_containers (name,type,app_id) VALUES ('Map','map',".$appId.")");
        return true;
    }

     /**
      * @param $appId
      * @param $userId
      * @return bool
      * add an user to the user list of an application
      */
    public function addUserToApp($appId, $userId){
        //Verify if this user has already been added
        $stmt1 = $this->conn->prepare("SELECT * FROM apps_users WHERE app_id = ? AND user_id = ?");
        $stmt1->bind_param("ii", $appId, $userId);
        $stmt1->execute();
        $stmt1->store_result();
        if($stmt1->num_rows>0){
            return FALSE;
        }
        $stmt1->close();

        $stmt2 = $this->conn->prepare("INSERT INTO apps_users (user_id, app_id) VALUES (?,?)");
        $stmt2->bind_param("ii",$userId, $appId);
        if($stmt2->execute()){
            $stmt2->close();
            return TRUE;
        }
        return FALSE;
    }

     /**
      * @param $appId
      * @param $userId
      * @return bool
      * remove an user from an application
      */
    public function removeUserFromApp($appId, $userId){
        //Verify if this user is in the app's list
        $this->log->addInfo("Call function removeUserFromApp");
        $stmt1 = $this->conn->prepare("SELECT * FROM apps_users WHERE app_id = ? AND user_id = ?");
        $stmt1->bind_param("ii", $appId, $userId);
        $stmt1->execute();
        $stmt1->store_result();
        if($stmt1->num_rows==0){
            $this->log->addInfo("User not found from app's list");
            return FALSE;
        }
        $stmt1->close();
        $stmt2 = $this->conn->prepare("DELETE FROM apps_users WHERE app_id = ? AND user_id = ?");
        $stmt2->bind_param("ii",$appId, $userId);
        if($stmt2->execute()){
            $stmt2->close();
            $this->log->addInfo("Deleted user from app's list");
            return TRUE;
        }
        $this->log->addInfo("Failed to delete");
        return FALSE;
    }

     /**
      * @param $appId
      * @param $adminId
      * @return bool
      * add an user to the admin list
      */
    public function addAdminToApp($appId, $adminId){
        //Verify if this user has already been added
        $stmt1 = $this->conn->prepare("SELECT * FROM apps_admins WHERE app_id = ? AND admin_id = ?");
        $stmt1->bind_param("ii", $appId, $adminId);
        $stmt1->execute();
        $stmt1->store_result();
        if($stmt1->num_rows>0){
            return FALSE;
        }
        $stmt1->close();

        $stmt2 = $this->conn->prepare("INSERT INTO apps_admins (admin_id, app_id) VALUES (?,?)");
        $stmt2->bind_param("ii",$adminId, $appId);
        if($stmt2->execute()){
            $stmt2->close();
            return TRUE;
        }
        return FALSE;
    }

     /**
      * @param $appId
      * @return null
      * return all the information about an application + the user list + the admin list + container list
      */
    public function getApp($appId){
        $stmt = $this->conn->prepare("SELECT app_name,creator_id,category,font,theme,background, icon,layout,description FROM apps WHERE id = ?");
        $stmt->bind_param("i", $appId);
        if($stmt->execute()){
            $stmt->bind_result($name, $creatorId,$category,$font,$theme,$background, $icon,$layout,$description);
            $stmt->fetch();
            $app["id"]=$appId;
            $app["name"]=$name;
            $app["creatorId"]=$creatorId;
            $app["category"]=$category;
            $app["font"]=$font;
            $app["theme"]=$theme;
            $app["background"]=$background;
            $app["icon"]=$icon;
            $app["layout"]=$layout;
            $app["description"]=$description;
            $stmt->close();
        } else return null;
        //get user of this app
        $stmt1 = $this->conn->prepare("SELECT apps_users.user_id, users.first_name, users.last_name, users.tel FROM apps_users INNER JOIN users  ON apps_users.user_id = users.id WHERE apps_users.app_id = ?");
        $stmt1->bind_param("i", $appId);
        $users = array();
        if($stmt1->execute()){
            $stmt1->bind_result($userId, $user_first, $user_last, $user_tel);
            while ($stmt1->fetch()){
                $users[] = array("id"=>$userId, "firstName" =>$user_first, "lastName"=>$user_last, "tel"=>$user_tel);
            }
            $stmt1->close();
            $app["users"] = $users;
        } else return null;

        //get admin of this app
        $stmt2 = $this->conn->prepare("SELECT apps_admins.admin_id, users.first_name, users.last_name, users.tel FROM apps_admins INNER JOIN users  ON apps_admins.admin_id = users.id WHERE apps_admins.app_id = ?");
        $stmt2->bind_param("i", $appId);
        $admins = array();
        if ($stmt2->execute()){
            $stmt2->bind_result($adminId, $admin_first, $admin_last, $admin_tel);
            while ($stmt2->fetch()){
                $admins[] = array("id"=>$adminId, "firstName" =>$admin_first, "lastName"=>$admin_last, "tel"=>$admin_tel);
            }
            $stmt2->close();
            $app["admins"] = $admins;
        } else return NULL;

        //get list of containers

        $stmt3 = $this->conn->prepare("SELECT m.id, m.name, m.type FROM module_containers as m WHERE app_id = ?");
        $stmt3->bind_param("i", $appId);
        $containers = array();
        if ($stmt3->execute()){
            $stmt3->bind_result($cId, $cName, $cType);
            while ($stmt3->fetch()){
                $containers[] = array("id"=>$cId, "name" =>$cName, "type"=>$cType);
            }
            $stmt3->close();
            $app["containers"] = $containers;
        } else return NULL;
        return $app;
    }


    //=============Container actions=================
     //==============================================
     /**
      * @param $appId
      * @param $name
      * @param $type
      * @return int|null containerId
      * create a new container associated with an application
      */
     public function createContainer($appId, $name, $type){
         $this->log->addInfo("creating container");
         $stmt = $this->conn->prepare("INSERT INTO module_containers (app_id, name, type) VALUES (?,?,?)");
         $stmt->bind_param("iss", $appId, $name, $type);
         if ($stmt->execute()) {
             $containerId = $stmt->insert_id;
             $stmt->close();
         } else {
             return NULL;
         }
         $this->log->addInfo("container created with id ".$containerId);
         return $containerId;
     }

     /**
      * @param $containerId
      * @return array|null container details with the list of its modules' ids.
      * The return array depends on the type of the container
      */
     public function getContainerDetails($containerId){
         $details = array();
         $this->log->addInfo("Get details in module_containers for container no ".$containerId);
         $stmt = $this->conn->prepare("SELECT m.app_id, m.name, m.type FROM module_containers as m WHERE id=?");
         $stmt->bind_param("i", $containerId);
         if ($stmt->execute()) {
             $stmt->bind_result($appId, $name, $type);
             $stmt->fetch();
             $details["appId"] = $appId;
             $details["name"] = $name;
             $details["type"] = $type;
             $stmt->close();
         } else return null;
         if ($details["type"] =="media"){
             $this->log->addInfo("Get list of media modules");
             $modules = array();
             $stmt1 = $this->conn->prepare("SELECT c.id, c.name, c.content_type FROM content_sharing_module as c WHERE container_id=?");
             $stmt1->bind_param("i", $containerId);
             if ($stmt1->execute()) {
                 $stmt1->bind_result($moduleId, $moduleName, $content_type);
                 while ($stmt1->fetch()){
                     $module = array();
                     $module["id"]= $moduleId;
                     $module["name"] = $moduleName;
                     $module["content_type"]=$content_type;
                     $modules[] = $module;
                 }
                 $stmt1->close();
                 $details["modules"] = $modules;
             } else return null;
         } else if ($details["type"] =="poll"){
             $this->log->addInfo("Get list of vote modules for container ".$containerId);
             $modules = array();
             $stmt2 = $this->conn->prepare("SELECT id, title, description,expire_date,is_expired FROM vote_module WHERE container_id= ? ");
             $stmt2->bind_param("i", $containerId);
             if ($stmt2->execute()) {
                 $stmt2->bind_result($moduleId, $moduleName, $description, $expire_date, $is_expired);
                 while ($stmt2->fetch()){
                     $module = array();
                     $module["id"]= $moduleId;
                     $module["title"] = $moduleName;
                     $module["description"]=$description;
                     $module["expire_date"]=$expire_date;
                     $module["is_expired"]=$is_expired;
                     $modules[] = $module;
                 }
                 $stmt2->close();
                 $details["modules"] = $modules;

             } else return null;
         } else if($details["type"]=="budget"){
             $this->log->addInfo("Get details for container budget ".$containerId);
             $modules = array();
             $stmt3 = $this->conn->prepare("SELECT b.id, b.user_id, b.user_name, b.description, b.value FROM budget_module as b WHERE container_id= ? ");
             $stmt3->bind_param("i", $containerId);
             if ($stmt3->execute()) {
                 $stmt3->bind_result($id, $user_id, $user_name, $description, $value);
                 while ($stmt3->fetch()){
                     $module = array();
                     $module["id"]= $id;
                     $module["userId"]= $user_id;
                     $module["description"]=$description;
                     $module["userName"]=$user_name;
                     $module["value"]=$value;
                     $modules[] = $module;
                 }
                 $stmt3->close();
                 $details["expenses"] = $modules;
             } else return null;
         } else if($details["type"]=="map") {
             $this->log->addInfo("Get details for container map " . $containerId);
             $modules = array();
             $stmt3 = $this->conn->prepare("SELECT m.id, m.description, m.address, m.lat,m.lng FROM map_module as m WHERE container_id= ? ");
             $stmt3->bind_param("i", $containerId);
             if ($stmt3->execute()) {
                 $stmt3->bind_result($id, $description, $address, $lat, $lng);
                 while ($stmt3->fetch()) {
                     $module = array();
                     $module["id"] = $id;
                     $module["description"] = $description;
                     $module["address"] = $address;
                     $module["lat"] = $lat;
                     $module["lng"] = $lng;
                     $modules[] = $module;
                 }
                 $stmt3->close();
                 $details["modules"] = $modules;
             } else return null;
         } else if($details["type"]=="calendar") {
             $this->log->addInfo("Get details for container calendar " . $containerId);
             $modules = array();
             $stmt3 = $this->conn->prepare("SELECT c.id, c.title, c.date, c.time FROM calendar_module as c WHERE container_id= ? ");
             $stmt3->bind_param("i", $containerId);
             if ($stmt3->execute()) {
                 $stmt3->bind_result($id, $title, $date, $time);
                 while ($stmt3->fetch()) {
                     $module = array();
                     $module["id"] = $id;
                     $module["title"] = $title;
                     $module["date"] = $date;
                     $module["time"] = $time;
                     $modules[] = $module;
                 }
                 $stmt3->close();
                 $details["modules"] = $modules;
             } else return null;
         }else if($details["type"]=="chat"){
             $this->log->addInfo("Get list of topics for container ".$containerId);
             $topics = array();
             $stmt4 = $this->conn->prepare("SELECT id, title,create_date,nb_replies,creator, description FROM forum_module WHERE container_id= ? ");
             $stmt4->bind_param("i", $containerId);
             if ($stmt4->execute()) {
                 $this->log->addInfo("Query ok");
                 $stmt4->bind_result($moduleId, $moduleName,$date,$nb_replies,$creator, $description);
                 $this->log->addInfo($stmt4->num_rows);
                 while ($stmt4->fetch()){
                     $topic = array();
                     $topic["id"]= $moduleId;
                     $topic["title"] = $moduleName;
                     $topic["creator"] = $creator;
                     $topic["date"] = $date;
                     $topic["replies"] = $nb_replies;
                     $topic["description"] = $description;
                     $topics[]=$topic;
                 }
                 $stmt4->close();
                 $details["topics"] = $topics;
             } else return null;
         }
         //TODO for other type
         return $details;
     }

     /**
      * @param $containerId
      * @param $newName
      * @return bool true if succeeded
      * update a new name for a container
      */
     public function updateContainer($containerId, $newName){
         $stmt = $this->conn->prepare("UPDATE module_containers SET name = ? WHERE id = ?");
         $stmt->bind_param("si", $newName, $containerId);
         if ($stmt->execute()) {
             $stmt->close();
             return true;
         } else {
             return false;
         }
     }

     /**
      * @param $containerId
      * @return bool true if success
      * delete a container
      * TODO delete the contents of all its module
      */
     public function deleteContainer($containerId){
         $stmt = $this->conn->prepare("DELETE FROM module_containers WHERE id = ?");
         $stmt->bind_param("i", $containerId);
         if ($stmt->execute()) {
             $stmt->close();
             $this->log->addInfo("Deleted container number ".$containerId );
         } else {
             return false;
         }
         return true;
     }

     /**
      * @param $containerId
      * @return null|string
      * get the type of a container
      */
     public function getContainerType($containerId){
         $stmt = $this->conn->prepare("SELECT m.type FROM module_containers as m WHERE id=?");
         $stmt->bind_param("i", $containerId);
         if ($stmt->execute()) {
             $stmt->bind_result($type);
             $stmt->fetch();
             $stmt->close();
             return $type;
         } else return null;
     }

    //=============Content sharing Module============
     //==============================================
     /**
      * @param $moduleId
      * @param $type : type of contents that the module holds
      * @return array|null
      * if image or video : list of id
      * if documents : list of id and name
      */
    public function getListIdFromModule($moduleId, $type){
        if ($type=="image"||$type=="video"){
            $this->log->addInfo("Get list of content id of module ". $moduleId);
            $stmt = $this->conn->prepare("SELECT id FROM media_contents WHERE module_id = ?");
            $stmt->bind_param("i",$moduleId);
            if($stmt->execute()){
                $listId = array();
                $stmt->bind_result($id);
                while($stmt->fetch()){
                    $listId[] = $id;
                }
                $stmt->close();
                return $listId;
            }
            return null;
        } else if ($type=="document"){
            $this->log->addInfo("Get list of content id of module ". $moduleId);
            $stmt2 = $this->conn->prepare("SELECT id, path FROM media_contents WHERE module_id = ?");
            $stmt2->bind_param("i",$moduleId);
            if($stmt2->execute()){
                $listId = array();
                $stmt2->bind_result($id, $path);
                while($stmt2->fetch()){
                    $doc = array();
                    $doc["id"]=$id;
                    $doc["name"]=basename($path);
                    $listId[] = $doc;
                }
                return $listId;
            }
            return null;
        }
        return null;
    }

     /**
      * @param $moduleId
      * @param $path
      * @param $type
      * @return int|null the id of the file in media_contents table
      * associate a file with a module in media_contents table
      */
    public function saveFileToModule($moduleId, $path, $type){
        $this->log->addInfo("save file of type ".$type." to module content sharing number ".$moduleId.", path: ".$path);
        $stmt = $this->conn->prepare("INSERT INTO media_contents (path, content_type, module_id) VALUES (?,?,?)");
        $stmt->bind_param("ssi",$path, $type, $moduleId);
        if ($stmt->execute()) {
            $id = $stmt->insert_id;
            $stmt->close();
        } else {
            return NULL;
        }
        return $id;
    }

     /**
      * @param $containerId
      * @param $name
      * @param $type
      * @return int|null the id of the new module
      */
    public function addModuleMedia($containerId, $name, $type){
        $stmt = $this->conn->prepare("INSERT INTO content_sharing_module (container_id, name, content_type) VALUES (?,?,?)");
        $stmt->bind_param("iss", $containerId, $name, $type);
        if ($stmt->execute()) {
            $moduleId = $stmt->insert_id;
            $stmt->close();
        } else {
            return NULL;
        }
        return $moduleId;
    }

     /**
      * @param $moduleId
      * @return bool true if success
      * delete a module media from content-sharing_module and all file associated with it from media_contents
      * The actual files are not deleted here, they are deleted by FileHandler
      */
    public function deleteModuleMedia($moduleId){
        $stmt = $this->conn->prepare("DELETE FROM content_sharing_module WHERE id = ?");
        $stmt->bind_param("i", $moduleId);
        if ($stmt->execute()) {
            $stmt->close();
            $this->log->addInfo("Deleted module number ".$moduleId. " from content_sharing_module");
        } else {
            return false;
        }
        $stmt1 = $this->conn->prepare("DELETE FROM media_contents WHERE module_id = ?");
        $stmt1->bind_param("i", $moduleId);
        if ($stmt1->execute()) {
            $stmt1->close();
            $this->log->addInfo("Deleted module number ".$moduleId. " from media_contents");
        } else {
            return false;
        }
        return true;
    }

     /**
      * @param $moduleId
      * @param $newName
      * @return bool
      * Update a new name for the module media
      */
    public function updateModuleMedia($moduleId, $newName){
        $stmt = $this->conn->prepare("UPDATE content_sharing_module SET name = ? WHERE id = ?");
        $stmt->bind_param("si", $newName, $moduleId);
        if ($stmt->execute()) {
            $stmt->close();
            return true;
        } else {
            return false;
        }
    }

     /**
      * @param $containerId
      * @return bool
      * delete a media container and all the modules it contains
      */
    public function deleteContainerMedia($containerId){
        $details = $this->getContainerDetails($containerId);
        $modules = $details["modules"];
        for ($i=0, $len=count($modules);$i<$len; $i++ ){
            $moduleId = $modules[$i]["id"];
            $this->deleteModuleMedia($moduleId);
        }
        $stmt = $this->conn->prepare("DELETE FROM module_containers WHERE id = ?");
        $stmt->bind_param("i", $containerId);
        if ($stmt->execute()) {
            $stmt->close();
            $this->log->addInfo("Deleted container number ".$containerId. " from content_sharing_module");
        } else {
            return false;
        }
        return true;
    }



   // =========Module VOTE===========================
     //==============================================
     /**
      * @param $title
      * @param $description
      * @param $container_id
      * @param $expire_date
      * @return int|null
      * Create anew vote module
      */
    public function createVote($title,$description,$container_id,$expire_date){
        $this->log->addInfo("creating a vote");
        $stmt = $this->conn->prepare("INSERT INTO vote_module (title,description,container_id,expire_date) VALUES (?,?,?,?)");
        $stmt->bind_param('ssis',$title,$description,$container_id,$expire_date);
        if ($stmt->execute()) {
            $voteId = $stmt->insert_id;
            $this->log->addInfo("vote created");
            $stmt->close();
        }else {
            return NULL;
        }
        return $voteId;
    }

     /**
      * @param $id
      * @param $title
      * @param $description
      * @return bool
      * Update the new name and description for a vote
      */
    public function updateVote($id,$title,$description){
        $stmt = $this->conn->prepare("UPDATE vote_module SET title=?,description=? WHERE id=?");
        $stmt->bind_param("ssi",$title,$description,$id);

        if($stmt->execute()){
            $this->log->addInfo("update vote with id ". $id);
            $stmt->close();

        }else {
            return FALSE;
        }

        return $id;
    }

     /**
      * @param $id
      * @return bool : true if success
      * Delete a vote module
      */
    public function deleteVoteModule($id){
        $stmt = $this->conn->prepare("DELETE FROM vote_module WHERE id =?");
        $stmt->bind_param("i",$id);
        if($stmt->execute()){
            $stmt->close();
            $this->log->addInfo("Deleted vote");
        }
        $stmt = $this->conn->prepare("DELETE FROM vote_options WHERE vote_id =?");
        $stmt->bind_param("i",$id);
        if($stmt->execute()){
            $stmt->close();
            $this->log->addInfo("Deleted vote options");
            return TRUE;
        }
        $this->log->addInfo("Failed to delete");
        return FALSE;
    }

     /**
      * @param $moduleId
      * @return array|null
      * Get list of options of a module
      */
    public function getModuleVoteOptions($moduleId){
        $this->log->addInfo("Get list of options of module vote". $moduleId);
        $stmt = $this->conn->prepare("SELECT id, name, num_votes FROM vote_options WHERE vote_id = ?");
        $stmt->bind_param("i",$moduleId);
        if($stmt->execute()){
            $options = array();
            $stmt->bind_result($id, $name, $num_vote);
            while($stmt->fetch()){
                $option = array();
                $option["id"]=$id;
                $option["name"]=$name;
                $option["value"]=$num_vote;
                $options[] = $option;
            }
            $stmt->close();
            return $options;
        }
        return null;
    }

     /**
      * @param $moduleId
      * @param $option
      * @return int the id of the option
      * Add a nex option to a module
      */
    public function addOptionToModuleVote($moduleId, $option){
        $this->log->addInfo("Add option to module vote". $moduleId);
        $stmt = $this->conn->prepare("INSERT INTO vote_options(name, vote_id, num_votes) VALUES (?,?,0)");
        $stmt->bind_param("si",$option,$moduleId);
        if($stmt->execute()){
            $optionId = $stmt->insert_id;
            return $optionId;
        }
    }

     /**
      * @param $optionId
      * @return bool : true if success
      * Increment the number of vote for an option
      */
    public function incrementVoteOption($optionId){
        $this->log->addInfo("Increment value of option ". $optionId);
        $stmt = $this->conn->prepare("UPDATE vote_options SET num_votes = num_votes +1 WHERE id = ?");
        $stmt->bind_param("i",$optionId);
        if($stmt->execute()){
            return true;
        }
        return false;
    }

    //TODO
    public function setToExpirePoll(){
        $this->log->addInfo("set to true all the expired polls");
        $stmt = $this->conn->prepare( "SELECT id FROM vote_module WHERE expire_date <= DATE(now()) AND is_expired=FALSE" );
        if($stmt->execute()){
            $votes_expired=array();
            $this->log->addInfo("Query ok");
            $stmt->bind_result($id);
            while($stmt->fetch()){
                $votes_expired[]=$id;
            }
            $stmt->close();
            foreach ($votes_expired as $value) {
                echo $value;
                $stmt2 = $this->conn->prepare("UPDATE vote_module SET is_expired = TRUE  WHERE id = ?");
                $stmt2->bind_param("i",$value);
                $stmt2->execute();
                $this->log->addInfo("UPDATED VOTE MODULE EXPIRED");

                # code...
            }
            $stmt2->close();

            return true;
        }
        return false;
    }

     /**
      * @param $containerId
      * @return bool
      * Delete a poll container
      */
     public function deleteContainerPoll($containerId){
         $details = $this->getContainerDetails($containerId);
         $modules = $details["modules"];
         //TODO delete all module vote of that containers
         $stmt = $this->conn->prepare("DELETE FROM module_containers WHERE id = ?");
         $stmt->bind_param("i", $containerId);
         if ($stmt->execute()) {
             $stmt->close();
             $this->log->addInfo("Deleted container number ".$containerId. " from content_sharing_module");
         } else {
             return false;
         }
         return true;
     }

     /**
      * @param $moduleId
      * @param $userId
      * @return bool
      * Add an user to the list of people who have voted in that module
      */
    public function addUserWhoVoted($moduleId,$userId){
        $this->log->addInfo("adding users who voted");
        $stmt = $this->conn->prepare("INSERT INTO vote_options_users(vote_id,user_id) VALUES(?,?)");
        $stmt->bind_param("ii",$moduleId,$userId);
        if($stmt->execute()){
            return true;
        }
        return false;

    }

     /**
      * @param $vote_id
      * @return array|null
      * Get list of users who voted
      */
    public function getUsersWhoVoted($vote_id){
        $who_voted=array();
        $stmt3 = $this->conn->prepare("SELECT user_id FROM vote_options_users WHERE vote_id= ?");
        $stmt3->bind_param("i", $vote_id);
        if ($stmt3->execute()) {
            $this->log->addInfo("Query ok");
            $stmt3->bind_result($userId);
            while($stmt3->fetch()){
                $who_voted[]=$userId;
            }
            $stmt3->close();
            return $who_voted;
        }
        return null;
    }

    //=============Budget Module====================
     //==============================================

     /**
      * @param $containerId
      * @param $description
      * @param $value
      * @param $userId
      * @return array|int
      * add a new expense
      */
     public function addCostModuleBudget($containerId, $description, $value, $userId){
        $this->log->addInfo("Add cost to container budget". $containerId);
        $stmt = $this->conn->prepare("SELECT first_name, last_name FROM users WHERE id=?");
        $stmt->bind_param("i",$userId);
        if($stmt->execute()){
            $stmt->bind_result($firstName, $lastName);
            $stmt->fetch();
            $userName = $firstName . " ". $lastName;
            $stmt->close();
        }

        $stmt1 = $this->conn->prepare("INSERT INTO budget_module(user_id, user_name, container_id, description, budget_module.value) VALUES (?,?,?,?,?)");
        $stmt1->bind_param("isisd" ,$userId,$userName, $containerId, $description, $value);
        if($stmt1->execute()){
            $id = $stmt1->insert_id;
            return array("id"=>$id, "userId"=>$userId, "userName"=>$userName, "containerId"=>$containerId, "description"=>$description, "value"=>$value);
        }

        return 0;
    }

     /**
      * @param $moduleId
      * @param $newDescription
      * @param $newValue
      * @return bool
      * set a new description and value for an expense
      */
    public function updateModuleBudget($moduleId, $newDescription, $newValue){
        $stmt = $this->conn->prepare("UPDATE budget_module as b SET b.description = $newDescription AND b.value = $newValue WHERE id = ?");
        $stmt->bind_param("ssi", $newDescription,$newValue, $moduleId);
        if ($stmt->execute()) {
            $stmt->close();
            return true;
        } else {
            return false;
        }
    }

     /**
      * @param $expenseId
      * @return bool
      * delete an expense
      */
    public function deleteExpense($expenseId){
        $stmt = $this->conn->prepare("DELETE FROM budget_module WHERE id = ?");
        $stmt->bind_param("i", $expenseId);
        if ($stmt->execute()) {
            $stmt->close();
            $this->log->addInfo("Deleted expense number ".$expenseId. " from budget_module");
        } else {
            return false;
        }
        return true;
    }

     //=============Map Module=======================
     //==============================================

     /**
      * @param $containerId
      * @param $description
      * @param $address
      * @param $lat
      * @param $lng
      * @return int : the  id of the address
      * Add a new address
      */
    public function addMapModule($containerId, $description, $address, $lat,$lng){
    $stmt = $this->conn->prepare("INSERT INTO map_module(container_id, description, address, lat, lng) VALUES (?,?,?,?,?)");
    $stmt->bind_param("issdd", $containerId, $description, $address, $lat,$lng);
    if ($stmt->execute()) {
        $id = $stmt->insert_id;
        $stmt->close();
        $this->log->addInfo("insert map number ".$id. " to map_module");
    } else {
        return 0;
    }
    return $id;
}

     /**
      * @param $moduleId
      * @return bool
      * Delete an address
      */
    public function deleteMapModule($moduleId){
        $stmt = $this->conn->prepare("DELETE FROM map_module WHERE id = ?");
        $stmt->bind_param("i", $moduleId);
        if ($stmt->execute()) {
            $stmt->close();
            $this->log->addInfo("Deleted map number ".$moduleId. " from map_module");
        } else {
            return false;
        }
        return true;
    }

     //=============Calendar Module==================
     //==============================================
     /**
      * @param $containerId
      * @param $title
      * @param $date
      * @param $time
      * @return int : the id of the event
      * Add a new event
      */
    public function addCalendarModule($containerId, $title, $date, $time){
        $stmt = $this->conn->prepare("INSERT INTO calendar_module (container_id, title, date, time) VALUES (?,?,?,?)");
        $stmt->bind_param("isss", $containerId, $title, $date, $time);
        if ($stmt->execute()) {
            $id = $stmt->insert_id;
            $stmt->close();
            $this->log->addInfo("insert calendar number ".$id. " to calendar_module");
        } else {
            return 0;
        }
        return $id;
    }

     /**
      * @param $moduleId
      * @return bool
      * Delete an event
      */
    public function deleteCalendarModule($moduleId){
        $stmt = $this->conn->prepare("DELETE FROM calendar_module WHERE id = ?");
        $stmt->bind_param("i", $moduleId);
        if ($stmt->execute()) {
            $stmt->close();
            $this->log->addInfo("Deleted calendar number ".$moduleId. " from calendar_module");
        } else {
            return false;
        }
        return true;
    }

     //=============Forum Module==================
     //==============================================

     /**
      * @param $title
      * @param $description
      * @param $container_id
      * @param $creatorId
      * @return array|null : fill description of that topic, with its id
      * Create a new topic
      */
    public function createTopic($title,$description,$container_id,$creatorId){
        $this->log->addInfo("creating a topic");
        $stmt1 = $this->conn->prepare("SELECT first_name, last_name FROM users WHERE id=?");
        $creator ="";
        $stmt1->bind_param('i',$creatorId);
        if ($stmt1->execute()) {
            $stmt1->bind_result($f , $l);
            $stmt1->fetch();
            $creator = $f ." ". $l;
            $stmt1->close();
        }

        $stmt = $this->conn->prepare("INSERT INTO forum_module (title,description,container_id,nb_replies,creator, creator_id) VALUES (?,?,?,0,?,?)");
        $stmt->bind_param('ssisi',$title,$description,$container_id,$creator, $creatorId);
        if ($stmt->execute()) {
                $topicId = $stmt->insert_id;
                $this->log->addInfo("topic created");
                $stmt->close();
        }else {
            return NULL;
        }
        return array("id"=>$topicId, "title"=>$title, "description"=>$description, "creator"=>$creator, "date"=>date("F j, Y, g:i a"), "creatorId"=>$creatorId, "replies"=>0);
        }

     /**
      * @param $topicId
      * @return bool
      * DElete a topic
      */
    public function deleteForumModule($topicId){
        $stmt = $this->conn->prepare("DELETE FROM forum_module WHERE id =?");
        $stmt->bind_param("i",$topicId);
        if($stmt->execute()){
            $this->log->addInfo("Deleted topic");
            $stmt->close();
            return TRUE;
        }
        $this->log->addInfo("Failed to delete");
        return FALSE;
    }

     /**
      * @param $moduleId
      * @param $newTopicName
      * @return bool
      * Update a new name for a topic
      */
    public function updateTopic($moduleId, $newTopicName){
        $stmt = $this->conn->prepare("UPDATE forum_module SET title=? WHERE id=?");
        $stmt->bind_param("si",$newTopicName,$moduleId);
        if($stmt->execute()){
                $this->log->addInfo("update topic with id ". $moduleId);
                $stmt->close();
            return TRUE;
        }else {
            return FALSE;
        }
    }

     /**
      * @param $topicId
      * @return array|null
      * Get details of a topic
      */
    public function getTopicDetails($topicId){
        $stmt1 = $this->conn->prepare("SELECT title, description ,container_id ,nb_replies,creator, creator_id, create_date FROM forum_module WHERE id=?");
        $stmt1->bind_param('i',$topicId);
        if ($stmt1->execute()) {
            $this->log->addInfo("topic created");
            $stmt1->bind_result($title,$description, $containerId, $replies, $creator, $creatorId, $date);
            $stmt1->close();
        }else {
            return NULL;
        }
        $topic_details=array();
        $topic_details["title"]=$title;
        $topic_details["description"]=$description;
        $topic_details["creator"]=$creator;
        $topic_details["replies"]=$replies;
        $topic_details["containerId"]=$containerId;
        $topic_details["date"]=$date;
        $comments=array();
        $this->log->addInfo("Get comments for topic no ".$topicId);
        $stmt = $this->conn->prepare("SELECT c.id, c.comment, c.creator, c.create_date FROM forum_comments as c WHERE topic_id=?");
        $stmt->bind_param("i", $topicId);
        if ($stmt->execute()) {
            $stmt->bind_result($id,$comment_text,$creator,$date);
            while ($stmt->fetch()){
                $comment=array();
                $comment["topicId"]=$id;
                $comment["comment"]=$comment_text;
                $comment["author"]=$creator;
                $comment["date"]=$date;
                $comments[]=$comment;
            }
            $stmt->close();
            $topic_details["comments"]=$comments;

        } else return null;


        return $topic_details;
    }

     /**
      * @param $comment_text
      * @param $creator
      * @param $topic_id
      * @return int|null : the id of the comment
      * Create a new comment
      */
    public function createComment($comment_text,$creator,$topic_id){
        $this->log->addInfo("creating a comment");
        $date=date('Y-m-d H:i:s');
        $this->log->addInfo($date);
        $stmt = $this->conn->prepare("INSERT INTO forum_comments (comment,topic_id,creator,create_date) VALUES (?,?,?,?)");
        $stmt->bind_param('siss',$comment_text,$topic_id,$creator,$date);
        if ($stmt->execute()) {

                $commentId = $stmt->insert_id;
                $this->log->addInfo("comment created");
                $stmt->close();
                return $commentId;
            }else {
                return NULL;
            }

    }

     /**
      * @param $commentId
      * @return bool : true if success
      * Delete a comment
      */
    public function deleteTopicComment($commentId){
        $stmt = $this->conn->prepare("DELETE FROM forum_comments WHERE id =?");
        $stmt->bind_param("i",$commentId);
        if($stmt->execute()){
            $this->log->addInfo("Deleted comment");
            $stmt->close();
            return TRUE;
        }
        $this->log->addInfo("Failed to delete");
        return FALSE;

    }

     /**
      * @param $commentId
      * @param $newComment
      * @return bool : true if success
      * Update a comment
      */
    public function updateComment($commentId, $newComment){
        $stmt = $this->conn->prepare("UPDATE forum_comments SET comment=? WHERE id=?");
        $stmt->bind_param("si",$newComment,$commentId);
        if($stmt->execute()){
                $this->log->addInfo("update comment with id ". $commentId);
                $stmt->close();

        }else {
            return FALSE;
        }
        return TRUE;

    }
}
?>