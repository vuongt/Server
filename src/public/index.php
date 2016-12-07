<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

require '../vendor/autoload.php';
set_include_path('');
//setup auto loading
spl_autoload_register(function($classname){
	require ("../classes/".$classname.".php");
});
require_once ("../classes/tokenHandler.php");
require_once ("../classes/config.php");

date_default_timezone_set('UTC');

$config['displayErrorDetails'] = true; //get informaton about error
$config['addContentLengthHeader'] = false;

$config['db']['host']   = "localhost";
$config['db']['user']   = "dty-orange";
$config['db']['pass']   = "dty";
$config['db']['dbname'] = "appOrange";


$app = new \Slim\App(["settings"=>$config]);


//=============Dependency Injection Container============
//=======================================================
$container = $app->getContainer(); //this gathers and holds all the dependencies

//Use Monolog to do logging
$container['log'] = function($c) {
    //set up the dependency when it is called for the first time
    $logger = new \Monolog\Logger('Main');
    $logger->pushHandler(new \Monolog\Handler\StreamHandler("../logs/app.log"));
    return $logger;
};
// log for the FileHandler file
$container['fileLog'] = function($c) {
    //set up the dependency when it is called for the first time
    $logger = new \Monolog\Logger('FileHandler');
    $logger->pushHandler(new \Monolog\Handler\StreamHandler("../logs/app.log"));//log all error to a file called logs/app.log
    return $logger;
};

//Log for DbHandler
$container['dbLog'] = function($c) {
    //set up the dependency when it is called for the first time
    $logger = new \Monolog\Logger('DbHandler');
    $logger->pushHandler(new \Monolog\Handler\StreamHandler("../logs/app.log"));//log all error to a file called logs/app.log
    return $logger;
};


//==========================ROUTES=========================
//=========================================================

$app->get('/', function (Request $request, Response $response) {
    echo "hello";
    return $response->withHeader(200, "hello");
});

$app->get('/hello/{name}', function (Request $request, Response $response) {
    $name = $request->getAttribute('name');
    $message = "Hello, $name";
    $data=array();
    $data['message']= $message;
    $this->log->addInfo("Hello name is called");
    $response = $response->withJson($data);
    return $response;
});

$app->get('/user', function(Request $req, Response $res){
    $this->log->addInfo("/user headers ". implode(",", $req->getHeader('Authorization')));
    $jwt = extractTokenFromHeader($req);
    if ($jwt){
        $userId = getUserIdFromToken($jwt);

        if (!$userId){
            $res = $res->withStatus(400, 'Bad request');
        }
        $db = new DbHandler($this->dbLog);
        $user = $db->getUserById($userId);
        $res = $res->withJson($user);
        $this->log->addInfo("User's info sent");
        return $res;
    } else {
        $res = $res->withStatus(400, 'Request without authorization header');
        $this->log->addInfo("Request user's info without authorization header");
        return $res;
    }

});

$app->get('/user/tel/{tel}', function(Request $req, Response $res){
    $tel = $req->getAttribute('tel');
    $db = new DbHandler($this->dbLog);
    $user = $db->getUserByTel($tel);
    $res->withHeader('Content-Type', 'application/json')->withJson($user);
});

//==========Authentication=============
$app->post('/signin', function(Request $req, Response $res){
    $this->log->addInfo("User sign in ");
    $data = $req->getParsedBody();
    $tel = filter_var($data['tel'], FILTER_SANITIZE_STRING);
    $password = filter_var($data['password'], FILTER_SANITIZE_STRING);
    $db = new DbHandler($this->dbLog);
    $auth = $db->signIn($tel,$password);
    if ( $auth ==AUTHENTICATE_SUCCESS){
        $id = $db->getUserByTel($tel)['id'];
        $auth = array();
        $auth['auth']='success';
        $this->log->addInfo("Create and add token to user with id ".$id);
        $token = createToken($id);
        $auth['token']= $token;
        return $res = $res->withJson($auth);
    } else if ($auth ==WRONG_PASSWORD){
        return $res->withHeader(401, 'Wrong password');
    } else if($auth==USER_NON_EXIST){
        return $res->withHeader(400, 'User non exist');
    }

});

$app->post('/signup', function(Request $req, Response $res){
    $this->log->addInfo("User sign up ");
    //get params
    $data = $req->getParsedBody();
    $firstName = filter_var($data['firstName'], FILTER_SANITIZE_STRING);
    $lastName = filter_var($data['lastName'], FILTER_SANITIZE_STRING);
    $tel = filter_var($data['tel'], FILTER_SANITIZE_STRING);
    $password = filter_var($data['password'], FILTER_SANITIZE_STRING);

    $db = new DbHandler($this->dbLog);
    $reg = $db->signUp($firstName,$lastName,$tel,$password);
    if ( $reg== USER_CREATED_SUCCESSFULLY){
        $this->log->addInfo("Create user succeed");
        $id = $db->getUserByTel($tel)['id'];
        $auth = array();
        $auth['auth']='success';
        $this->log->addInfo("Create and add token to user with id ".$id);
        $token = createToken($id);
        $auth['token']= $token;
        $res = $res->withJson($auth);
    } else if ($reg ==USER_ALREADY_EXISTED){
        $res = $res->withHeader(400, 'User already existed');
    } else if($reg == USER_CREATE_FAILED){
        $res = $res->withHeader(500, 'Fail to create user');
    }
    return $res;
});

//===========get media by Id=======

$app->get('/getMedia', function (Request $req, Response $res){
    $id = $req->getQueryParam("id");
    $db = new DbHandler($this->dbLog);
    $path = $db->getMediaPath($id);
    $this->log->addInfo("/getMedia sending media from path ".$path);
    $image=file_get_contents($path);
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $res = $res->write($image);
    $res = $res->withHeader('Content-Type', $finfo->buffer($image));
    return $res;
});

//============Global app action=========

$app->post('/createApp', function(Request $req, Response $res){
    $this->log->addInfo("/createApp is called");
    $db = new DbHandler($this->dbLog);
    $jwt = extractTokenFromHeader($req);
    $creatorId = getUserIdFromToken($jwt);
    $name = $_POST["name"];
    $category = $_POST["category"];
    $font = $_POST['font'];
    $theme = $_POST['theme'];
    $layout = $_POST['layout'];
    $description = $_POST["description"];
    $appId = $db->createAppWithoutMedia($name,$creatorId,$category,$font,$theme,$layout,$description);
    $db->addAdminToApp($appId, $creatorId);
    $this->log->addInfo("Created app without media");
    $fileHandler = new FileHandler($this->fileLog);
    $iconPath ="";
    if ($_POST["icon"]=="default"){
        $iconPath = DEFAULT_ICON_PATH;
    } else if ($_POST["icon"]=="custom"){
        $iconPath = $fileHandler->saveImageToApp($appId,"iconFile");
        if ($iconPath==null){
            return $res->withStatus(400, "File too big or wrong format");
        }
    }
    if($iconPath!=""){
        $db->saveFileToApp($appId, $iconPath, "image", "icon");
        $this->log->addInfo("saved icon to ". $iconPath);
    }
    $backgroundPath="";
    if ($_POST["background"]=="default"){
        $backgroundPath = DEFAULT_BACKGROUND_PATH;
    } else if ($_POST["background"]=="custom"){
        $backgroundPath = $fileHandler->saveImageToApp($appId, "backgroundFile");
        if ($iconPath==null){
            return $res->withStatus(400, "File too big or wrong format");
        }
    }
    if($backgroundPath!=""){
        $db->saveFileToApp($appId, $backgroundPath,"image", "background");
        $this->log->addInfo("Saved background to ".$backgroundPath);
    }
    return $res->withJson(array("appId"=>$appId));
});

$app->post('/createApp/default', function(Request $req, Response $res){
    $this->log->addInfo("/createApp is called");
    $db = new DbHandler($this->dbLog);
    $jwt = extractTokenFromHeader($req);
    $creatorId = getUserIdFromToken($jwt);
    $name = $_POST["name"];
    $category = $_POST["category"];
    $font = $_POST['font'];
    $theme = $_POST['theme'];
    $layout = $_POST['layout'];
    $description = $_POST["description"];
    $appId = $db->createAppWithoutMedia($name,$creatorId,$category,$font,$theme,$layout,$description);
    $db->addAdminToApp($appId, $creatorId);
    $this->log->addInfo("Created app without media");
    $fileHandler = new FileHandler($this->fileLog);
    if ($_POST["icon"]=="default"){
        $iconPath = DEFAULT_ICON_PATH;
    } else {
        $iconPath = $fileHandler->saveImageToApp($appId,"iconFile");
    }
    $db->saveFileToApp($appId, $iconPath, "image", "icon");
    $this->log->addInfo("saved icon to ". $iconPath);
    if ($_POST["background"]=="default"){
        $backgroundPath = DEFAULT_BACKGROUND_PATH;
    } else {
        $backgroundPath = $fileHandler->saveImageToApp($appId, "backgroundFile");
    }
    $db->saveFileToApp($appId, $backgroundPath,"image", "background");
    $this->log->addInfo("Saved background to ".$backgroundPath);
    $db->createDefaultContainers($appId);
    return $res->withJson(array("appId"=>$appId));
});

$app->get('/deleteApp', function(Request $req, Response $res){
    $appId = $req->getQueryParam("appId");
    $db = new DbHandler($this->dbLog);
    if($db->deleteApp($appId)){
        return $res->withHeader(200, "app deleted");
    } else{
        return $res->withHeader(500, "internal error");

    }
});

$app->get('/loadApp', function(Request $req, Response $res){
    $appId = $req->getQueryParam("appId");
    $this->log->addInfo("loading App ".$appId);
    $db = new DbHandler($this->dbLog);
    $app =$db->getApp($appId);
    return $res->withJson($app);
});

$app->post('/updateApp', function(Request $req, Response $res){
    $appId = $_POST["appId"];
    $newName = $_POST["newName"];
    $db = new DbHandler($this->dbLog);
    if ($db->updateApp($appId, $newName)){
        return $res->withHeader(200, "update success");
    }
    return $res->withHeader(500);

});

$app->get('/app/addUser',function(Request $req, Response $res) {
    $tel = $req->getQueryParam('tel');
    $appId = $req->getQueryParam('appId');
    $db = new DbHandler($this->dbLog);
    $userId = $db->getUserIdByTel($tel);
    if ($userId) {
        $this->log->addInfo("add user ".$userId." to app ".$appId);
        if ($db->addUserToApp($appId, $userId)){
            $res = $res->withStatus(200, "Action success");
        } else {
            $res = $res->withStatus(500, "Action fail");
        }
    } else {
        $res = $res->withStatus(400, "Tel not found");
    }
    return $res;
});

$app->get('/app/removeUser',function(Request $req, Response $res){
    $userId = $req->getQueryParam('userId');
    $appId = $req->getQueryParam('appId');
    $this->log->addInfo("remove user ".$userId." from app ".$appId);
    $db = new DbHandler($this->dbLog);
    if ($db->removeUserFromApp($appId, $userId)){
        $res->withStatus(200, "action success");
    } else {
        $res->withStatus(500, "action fail");
    }
    return $res;

});

$app->get('/app/createContainer', function(Request $req, Response $res){
    $appId = $req->getQueryParam("appId",0);
    $name = $req->getQueryParam("name","");
    $type = $req->getQueryParam("type","");
    $db = new DbHandler($this->dbLog);
    $containerId = $db->createContainer($appId, $name, $type);
    if ($containerId){
        return $res->withJson(array("containerId"=>$containerId));
    }
    return $res->withHeader(401);

});

$app->post('/app/upload', function(Request $req, Response $res){
    $this->log->addInfo("Uploading file");
    $appId = $_POST["appId"];
    $type = $_POST["type"];
    $fileHandler  = new FileHandler($this->fileLog);
    $filePath = $fileHandler->saveImageToApp($appId, "fileToUpload");
    echo $filePath;
    //TODO update icon or background in table apps
    return $res->withJson(["path"=>$filePath]);
});

//==========Container global action====================

$app->get('/container/loadDetails', function(Request $req, Response $res){
    $containerId = $req->getQueryParam("containerId",0);
    $this->log->addInfo("load Details for container ".$containerId);
    $db = new DbHandler($this->dbLog);
    $containerDetails = $db->getContainerDetails($containerId);
    if($containerDetails){
        return $res->withJson($containerDetails);
    } else {
        return $res->withHeader(401);
    }
});

$app->post('/container/update', function(Request $req, response $res){
    $containerId = $_POST["containerId"];
    $newName = $_POST["newName"];
    $this->log->addInfo("Update Details for container ".$containerId);
    $db = new DbHandler($this->dbLog);
    $done = $db->updateContainer($containerId, $newName);
    if($done){
        return $res->withHeader(200, "name updated to ". $newName);
    } else {
        return $res->withHeader(401);
    }
});

$app->get('/container/delete', function (Request $req, Response $res){
    $containerId = $req->getQueryParam("containerId", 0);
    $db = new DbHandler($this->dbLog);
    $type = $db->getContainerType($containerId);
    if ($type=="media"){
        $this->log->addInfo("Delete container number ".$containerId);
        if ($db->deleteContainerMedia($containerId)){
            $this->log->addInfo("Delete container number ".$containerId. " done");
            return $res = $res->withHeader(200, "delete success");
        }
        return $res->withHeader(500);
    } else if ($type=="poll"){
        $db->deleteContainerPoll($containerId);
        return $res = $res->withHeader(200, "delete success");
    } else {
        $db->deleteContainer($containerId);
        return $res = $res->withHeader(200, "delete success");
    }
});

//==========Module media sharing=========

$app->get('/container/addModule/media', function(Request $req, Response $res){
    $containerId = $req->getQueryParam("containerId",0);
    $name = $req->getQueryParam("name","");
    $type=$req->getQueryParam("type","");
    $db = new DbHandler($this->dbLog);
    $moduleId = $db->addModuleMedia($containerId, $name, $type);
    if($moduleId){
        return $res->withJson(["id"=>$moduleId]);
    }
    return $res->withHeader(401);
});

$app->post('/module/media/upload', function(Request $req, Response $res){
    $this->log->addInfo("Uploading file");
    $moduleId = $_POST["moduleId"];
    $type = $_POST["type"];
    $fileHandler  = new FileHandler($this->fileLog);
    $filePath="";
    $filePath = $fileHandler->saveContentToModule($moduleId, "fileToUpload", $type);
    echo $filePath;
    if ($filePath!=""){
        $db = new DbHandler($this->dbLog);
        $id = $db->saveFileToModule($moduleId, $filePath, $type);
        return $res->withStatus(200, "content added to module")->withJson(["id"=>$id, "name"=>basename($_FILES["fileToUpload"]["name"])]);
    }
    return $res->withStatus(500);
});

$app->get('/module/media/load', function(Request $req, Response $res){
    $id = $req->getQueryParam("id",0);
    $type=$req->getQueryParam("type","");
    $db = new DbHandler($this->dbLog);
    $listId = $db->getListIdFromModule($id,$type);
    return $res->withJson($listId);
});

$app->post('/module/media/update', function(Request $req, Response $res){
    $moduleId = $_POST["moduleId"];
    $newName = $_POST["newName"];
    $db = new DbHandler($this->dbLog);
    if ($db->updateModuleMedia($moduleId, $newName)){
        return $res->withHeader(200, "update success");
    }
    return $res->withHeader(500);

});

$app->get('/module/media/deleteModule', function (Request $req, Response $res){
    $moduleId = $req->getQueryParam("moduleId", 0);
    $this->log->addInfo("Delete module number ".$moduleId);
    $db = new DbHandler($this->dbLog);
    $fh = new FileHandler($this->fileLog);
    if ($db->deleteModuleMedia($moduleId)){
        $this->log->addInfo("Will delete folder res of module number ".$moduleId);
        $fh->deleteDir(RES_MODULE_PATH.$moduleId); //Delete resources
        $this->log->addInfo("Delete module number ".$moduleId. " done");
        return $res = $res->withHeader(200, "delete success");
    }
    return $res->withHeader(500);
});

$app->get('/module/media/deleteFile', function (Request $req, Response $res){
    $id = $req->getQueryParam("id", 0);
    $this->log->addInfo("Delete file with id ". $id);
    $db = new DbHandler($this->dbLog);
    $fh = new FileHandler($this->fileLog);
    $path= $db->getMediaPath($id);
    if (!$fh->deleteFile($path)){return $res->withHeader(500); }//Delete resources
    if ($db->deleteFile($id)){
        $this->log->addInfo("Delete file with id ".$id. " done");
        return $res = $res->withHeader(200, "delete success");
    }
    return $res->withHeader(500);
});

//==========Module VOTE=========

$app->get('/module/vote/getVote', function(Request $req, Response $res){
    $containerId = $req->getQueryParam("containerId",0);
    $db = new DbHandler($this->dbLog);
    $listId = $db->getListIdVoteModule($containerId);

    return $res->withJson($listId);


});

$app->post('/module/vote/addVote', function(Request $req, Response $res){
    $this->log->addInfo("/addVote is called");
    $db = new DbHandler($this->dbLog);
    $title = $_POST["title"];
    $description = $_POST["description"];
    $container_id = $_POST["container_id"];
    $expire_date=date('Y-m-d',strtotime($_POST["expire_date"]));
    $voteId = $db->createVote($title,$description,$container_id,$expire_date);
    if($voteId){
        $this->log->addInfo("Created vote");
        return $res->withJson(["voteId"=>$voteId]);
    }
    return $res->withStatus(500);
});

$app->post('/module/vote/uploadVote', function(Request $req, Response $res){
    $this->log->addInfo("uploadVote is called");
    $db = new DbHandler($this->dbLog);
    $id = $_POST["id"];
    $title = $_POST["title"];
    $description = $_POST["description"];
    if($db->updateVote($id,$title,$description)){
        $this->log->addInfo("Updated vote");
        return $res->withJson(["voteIdUpdated"=>$id]);
    }
    return $res->withStatus(404);
});

$app->get('/module/vote/deleteVote', function(Request $req, Response $res){
    $this->log->addInfo("deleteVote is called");
    $voteId = $req->getQueryParam("voteId",0);
    $db = new DbHandler($this->dbLog);
    if($db->deleteVoteModule($voteId)){
        $this->log->addInfo("Deleted vote");
        return $res->withHeader(200, "Deleted");
    }
    return $res->withHeader(404, "Not FOUND");
});

$app->get('/module/vote/load', function(Request $req, Response $res){
    $id = $req->getQueryParam("id",0);
    $db = new DbHandler($this->dbLog);
    $options = $db->getModuleVoteOptions($id);
    return $res->withJson($options);
});

$app->post('/module/vote/addOption', function(Request $req, Response $res){
    $moduleId = $_POST["moduleId"];
    $option = $_POST["option"];
    $db = new DbHandler($this->dbLog);
    $optionId = $db->addOptionToModuleVote($moduleId, $option);
    return $res->withJson(array("id"=>$optionId));
});

$app->get('/module/vote/increment', function(Request $req, Response $res){
    $optionId = $req->getQueryParam("optionId");
    $moduleId = $req->getQueryParam("moduleId");
    $userId = $req->getQueryParam("userId");
    $db = new DbHandler($this->dbLog);
    if($db->incrementVoteOption($optionId)){
        if($db->addUserWhoVoted($moduleId,$userId)){
            return $res->withHeader(200, "OK");
        }
    }
    return $res->withHeader(400, "Bad request");

});

$app->post('/module/vote/update', function(Request $req, Response $res){
    $moduleId = $_POST["moduleId"];
    $newName = $_POST["newName"];
    $newDescription = $_POST["newDescription"];
    $db = new DbHandler($this->dbLog);
    if ($db->updateVote($moduleId, $newName, $newDescription)){
        return $res->withHeader(200, "update success");
    }
    return $res->withHeader(500);

});

$app->get('/module/vote/expiredornotexpired', function(Request $req, Response $res){
    $db = new DbHandler($this->dbLog);
    if($db->setToExpirePoll()){
        return $res->withHeader(200, "update success");
    }
    return $res->withHeader(500);

});

$app->get('/module/vote/usersVoters',function(Request $req, Response $res){
    $id = $req->getQueryParam("id",0);
    $db = new DbHandler($this->dbLog);
    $users_voters=$db->getUsersWhoVoted($id);
    return $res->withJson($users_voters);
});

//===============MODULE BUDGET============
$app->post('/module/budget/addCost', function(Request $req, Response $res){
    $containerId = $_POST["containerId"];
    $description = $_POST["description"];
    $value = $_POST["value"];
    $jwt = extractTokenFromHeader($req);
    $userId = getUserIdFromToken($jwt);
    $db = new DbHandler($this->dbLog);
    $cost = $db->addCostModuleBudget($containerId, $description, $value, $userId);
    return $res->withJson($cost);
});

$app->post('/module/budget/update', function(Request $req, Response $res){
    $expenseId = $_POST["expenseId"];
    $newDescription = $_POST["newDescription"];
    $newValue =$_POST["newValue"];
    $db = new DbHandler($this->dbLog);
    if ($db->updateModuleBudget($expenseId, $newDescription, $newValue)){
        return $res->withHeader(200, "update success");
    }
    return $res->withHeader(500);
});

$app->get('/module/budget/deleteExpense', function (Request $req, Response $res){
    $expenseId = $req->getQueryParam("expenseId", 0);
    $this->log->addInfo("Delete expense number ".$expenseId);
    $db = new DbHandler($this->dbLog);
    if ($db->deleteExpense($expenseId)){
        $this->log->addInfo("Delete expense number ".$expenseId. " done");
        return $res = $res->withHeader(200, "delete success");
    }
    return $res->withHeader(500);
});

//=================MODULE MAP==============

$app->post('/module/map/add', function(Request $req, Response $res){
    $containerId = $_POST["containerId"];
    $description = $_POST["description"];
    $address = $_POST["address"];
    $lat = $_POST["lat"];
    $lng = $_POST["lng"];
    $db = new DbHandler($this->dbLog);
    $id = $db->addMapModule($containerId, $description, $address, $lat, $lng);
    return $res->withJson(array("id"=>$id, "containerId"=>$containerId, "description"=>$description, "address"=>$address, "lat"=>$lat, "lng"=>$lng));
});

$app->get('/module/map/delete', function(Request $req, Response $res){
    $moduleId = $req->getQueryParam("moduleId",0);
    $db = new DbHandler($this->dbLog);
    if($db->deleteMapModule($moduleId)){
        return $res->withStatus(200, "delete ok");
    }
    return $res->withStatus(500, "fail to delete");
});

//================MODULE CALENDAR===========
$app->post('/module/calendar/add', function(Request $req, Response $res){
    $containerId = $_POST["containerId"];
    $title = $_POST["title"];
    $date = $_POST["date"];
    $time = $_POST["time"];
    $db = new DbHandler($this->dbLog);
    $id = $db->addCalendarModule($containerId, $title, $date, $time);
    return $res->withJson(array("id"=>$id, "containerId"=>$containerId, "title"=>$title, "date"=>$date, "time"=>$time));
});

$app->get('/module/calendar/delete', function(Request $req, Response $res){
    $moduleId = $req->getQueryParam("moduleId",0);
    $db = new DbHandler($this->dbLog);
    if($db->deleteCalendarModule($moduleId)){
        return $res->withStatus(200, "delete ok");
    }
    return $res->withStatus(500, "fail to delete");
});

//===============MODULE FORUM============

$app->post('/module/forum/addForum', function(Request $req, Response $res){
    $this->log->addInfo("/addForum is called");
    $db = new DbHandler($this->dbLog);
    $title = $_POST["title"];
    $description = $_POST["description"];
    $container_id = $_POST["container_id"];
    $jwt = extractTokenFromHeader($req);
    $creatorId = getUserIdFromToken($jwt);
    $topic = $db->createTopic($title,$description,$container_id,$creatorId);
    if($topic){
        $this->log->addInfo("Created topic");
        return $res->withJson($topic);
    }
    return $res->withStatus(500);
});

$app->get('/module/forum/deleteForum',function(Request $req, Response $res){
    $this->log->addInfo("deleteForum is called");
    $topicId = $req->getQueryParam("topicId",0);
    $db = new DbHandler($this->dbLog);
    if($db->deleteForumModule($topicId)){
        $this->log->addInfo("Deleted topic");
        return $res->withHeader(200, "Deleted");
    }
    return $res->withHeader(404, "Not FOUND");
});

$app->post('/module/forum/update',function(Request $req, Response $res){
    $this->log->addInfo("/updateForum is called");
    $moduleId = $_POST["topicId"];
    $newTopicName = $_POST["newTopicName"];
    $db = new DbHandler($this->dbLog);
    if ($db->updateTopic($moduleId, $newTopicName)){
        $this->log->addInfo("Updated topic");
        return $res->withHeader(200, "updated");
    }
    return $res->withHeader(500);
});

$app->get('/module/forum/topic/loadDetails',function(Request $req, Response $res){
    $topicId = $req->getQueryParam("topicId",0);
    $this->log->addInfo("load Comments for topic ".$topicId);
    $db = new DbHandler($this->dbLog);
    $topicDetails = $db->getTopicDetails($topicId);
    if($topicDetails){
        return $res->withJson($topicDetails);
    } else {
        return $res->withHeader(401);
    }
});

$app->post('/module/forum/topic/addComment',function(Request $req, Response $res){
    $this->log->addInfo("/addComment is called");
    $db = new DbHandler($this->dbLog);
    $comment_text = $_POST["comment_text"];
    $creator = $_POST["creator"];
    $topic_id = $_POST["topic_id"];
    $commentId = $db->createComment($comment_text,$creator,$topic_id);
    if($commentId){
        $this->log->addInfo("Created comment");
        return $res->withJson(["commentId"=>$commentId]);
    }
    return $res->withStatus(500);
});

$app->get('/module/forum/topic/deleteComment',function(Request $req, Response $res){
    $this->log->addInfo("deleteComment is called");
    $commentId = $req->getQueryParam("commentId",0);
    $db = new DbHandler($this->dbLog);
    if($db->deleteTopicComment($commentId)){
        $this->log->addInfo("Deleted comment");
        return $res->withHeader(200, "Deleted");
    }
    return $res->withHeader(404, "Not FOUND");
});

$app->post('/module/forum/topic/update',function(Request $req, Response $res){
    $this->log->addInfo("/updateComment is called");
    $commentId = $_POST["commentId"];
    $newComment = $_POST["newComment"];
    $db = new DbHandler($this->dbLog);
    if ($db->updateComment($commentId, $newComment)){
        $this->log->addInfo("Updated comment");
        return $res->withHeader(200, "updated");
    }
    return $res->withHeader(500);
});

$app->run();

