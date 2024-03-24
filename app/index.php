<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/classes/class.php';


$conn = mysqli_connect($config['db']['dbhost'], $config['db']['dbuser'], $config['db']['dbpass'], $config['db']['dbname']);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$project = new Project($conn);

// If Ajax projectdel
if (isset($_GET['delProjectsAjax'])) {
    $project->delProjectsAjax($_GET['delProjectsAjax']);
}

require_once('template/header.html');



if (isset($_GET['project']) && $_GET['project'] == "new") {
    $project->newProjects();
} elseif (isset($_GET['projectedit']) && is_numeric($_GET['projectedit'])) {
    $project->editProjects($_GET['projectedit']);
} elseif (isset($_GET['projectdel']) && is_numeric($_GET['projectdel'])) {
    $project->delProjects($_GET['projectdel']);
} else {
    $params = array();
    if (isset($_GET['listStatus']) && is_numeric($_GET['listStatus'])) {
        $params['qstatus'] = $_GET['listStatus'];
    }
    $project->listProjects($params);
}

$conn->close();

require_once('template/footer.html');
