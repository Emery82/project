<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/classes/class.php';


$conn = mysqli_connect($config['db']['dbhost'], $config['db']['dbuser'], $config['db']['dbpass'], $config['db']['dbname']);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

require_once('template/header.html');

if(isset($_GET['project']) && $_GET['project']=="new"){
    $project= new Project($conn);
    if(isset($_POST["project_name"])){
        $projectName = $_POST["project_name"];
        $projectDesc = $_POST["project_desc"];
        $projectStatus = $_POST["project_status"];
        $ownerName = $_POST["owner_name"];
        $ownerEmail = $_POST["owner_email"];
        
        //Check if owner exists or create new one
        if($project->getOwnerIdByEmail($ownerEmail)){
            $ownerId=$project->getOwnerIdByEmail($ownerEmail);
        }
        else {
            $ownerId=$project->saveOwner($ownerName, $ownerEmail);
        }

        $projectsave = $project->saveProject($projectName, $projectDesc, $ownerId, $projectStatus);
        if($projectsave) echo '<div class="alert alert-success" role="alert">A <strong>'.$title.'</strong> projekt sikeresen mentve!</div>';
    }
    else {
        $currvals = array(
            "%%project_name%%" => "",
            "%%project_desc%%" => "",
            "%%owner_name%%" => "",
            "%%owner_email%%" => "",
            "%%project_status-1%%" => "",
            "%%project_status-2%%" => "",
            "%%project_status-3%%" => ""
        );
        $pattern = '/%%(.*?)%%/';
        $replacement = '';
        $template=file_get_contents('template/project.html');
        $template=preg_replace($pattern, $replacement, $template);
        echo $template;
    }
}
elseif(isset($_GET['projectedit']) && is_numeric($_GET['projectedit'])){
    $project = new Project($conn);
    $projectDetails = $project->getProjects($_GET['projectedit']);
    if(isset($_POST["project_name"])){
        $projectName = $_POST["project_name"];
        $projectDesc = $_POST["project_desc"];
        $projectStatus = $_POST["project_status"];
        $ownerName = $_POST["owner_name"];
        $ownerEmail = $_POST["owner_email"];
        
        if($project->getOwnerIdByEmail($ownerEmail)){
            $ownerId=$project->getOwnerIdByEmail($ownerEmail);
        }
        else {
            $ownerId=$project->saveOwner($ownerName, $ownerEmail);
        }

        $projectsave = $project->updateProject($projectDetails[0]['project_id'], $projectName, $projectDesc, $ownerId, $projectStatus);
        if($projectsave) echo '<div class="alert alert-success" role="alert">A <strong>'.$title.'</strong> projekt sikeresen mentve!</div>';
    }
    else {
        $currvals = array(
            "%%project_name%%" => $projectDetails[0]['project_title'],
            "%%project_desc%%" => $projectDetails[0]['project_description'],
            "%%owner_name%%" => $projectDetails[0]['owner_name'],
            "%%owner_email%%" => $projectDetails[0]['owner_email'],
            "%%project_status-1%%" => ($projectDetails[0]['status_id'] == 1 ? ' selected ' : ''),
            "%%project_status-2%%" => ($projectDetails[0]['status_id'] == 2 ? ' selected ' : ''),
            "%%project_status-3%%" => ($projectDetails[0]['status_id'] == 3 ? ' selected ' : '')
        );
        $template=file_get_contents('template/project.html');
        $template=str_replace(array_keys($currvals), array_values($currvals), $template);
        echo $template;    
    }
}
elseif(isset($_GET['projectdel']) && is_numeric($_GET['projectdel'])){
    $project= new Project($conn);    
    $projectDetails = $project->getProjects($_GET['projectdel']);
    $projectdel = $project->deleteProject($_GET['projectdel']);
    if($projectdel) echo '<div class="alert alert-success" role="alert">A <strong>'.$title.'</strong> projekt törlésre került!</div>';
}    
else {
    // ProjektList példány létrehozása és projektek lekérése
    $projectList = new Project($conn);
    $params=array();

    if(isset($_GET['listStatus']) && is_numeric($_GET['listStatus'])){
        $params['qstatus'] = $_GET['listStatus'];
    }

    $projects = $projectList->getProjects("all", $params);
    
    $projectStatuses = array(
        1 => "Fejlesztésre vár",
        2 => "Folyamatban",
        3 => "Kész",
    );

    // List projects
    $projectList_html = '
        <div class="row">
            <div class="col-md-6">
                <h3>Projektek</h3>
            </div>
            <div class="col-md-6 text-right">                                
                <form method="get" action="" class="form-control  text-right">
                <select name="listStatus">
                    <option value="">Szűrés státuszra</option>';
                    foreach($projectStatuses as $key => $value){
                        $projectList_html .='<option value="'.$key.'" '.(isset($params['qstatus']) && $params['qstatus']==$key ? " selected " : "").'>'.$value.'</option>';
                    }
                
                $projectList_html .='</select>
                <button class="btn btn-sm btn-secondary">OK</button>
                </form>
            </div>
        </div>
    ';

    foreach ($projects as $project) {
        $projectList_html.='
            <div class="projectbox">
                <div class="row">
                    <div class="projectleft col-md-8"> 
                        <h4>' . $project['project_title'] . '</h4>
                        <small class="projectowner">' . $project['owner_name'] . ' (' . $project['owner_email'] . ')</small>
                        <div class="projectbuttons">
                            <a href="/?projectedit=' . $project['project_id'] . '" class="btn btn-primary">Szerkesztés</a>
                            <a href="/?projectdel=' . $project['project_id'] . '" class="btn btn-danger">Törlés</a>
                        </div>
                    </div>
                    <div class="projectright col-md-4">
                        <small class="prjectstatus">' . $project['status_name'] . '</small>      
                        
                    </div>
                </div>
            </div>';


        
    }
    echo $projectList_html;
}


$conn->close();

require_once('template/footer.html');
?>