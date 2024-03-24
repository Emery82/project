<?php
class Project
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    private function getProjects($projectId = null, $params = array())
    {

        $projects = array();

        $sql = "SELECT 
                    projects.id AS project_id,
                    projects.title AS project_title,
                    projects.description AS project_description,
                    owners.id AS owner_id,
                    owners.name AS owner_name,
                    owners.email AS owner_email,
                    statuses.id AS status_id,
                    statuses.name AS status_name
                FROM 
                    projects
                JOIN 
                    project_owner_pivot ON projects.id = project_owner_pivot.project_id
                JOIN 
                    owners ON project_owner_pivot.owner_id = owners.id
                JOIN 
                    project_status_pivot ON projects.id = project_status_pivot.project_id
                JOIN 
                    statuses ON project_status_pivot.status_id = statuses.id";

        $sql .= " WHERE 1 ";
        if ($projectId !== null && is_numeric($projectId)) {
            $sql .= " AND projects.id = " . $projectId . " ";
        }

        if (array_key_exists('qstatus', $params)) {
            $sql .= " AND statuses.id = " . $params['qstatus'] . " ";
        }

        $sql .= " ORDER BY projects.id DESC ";

        $result = $this->conn->query($sql);

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $project = array(
                    'project_id' => $row['project_id'],
                    'project_title' => $row['project_title'],
                    'project_description' => $row['project_description'],
                    'owner_id' => $row['owner_id'],
                    'owner_name' => $row['owner_name'],
                    'owner_email' => $row['owner_email'],
                    'status_id' => $row['status_id'],
                    'status_name' => $row['status_name']
                );
                $projects[] = $project;
            }
        }

        return $projects;
    }



    private function saveProject($title, $description, $ownerId, $statusId)
    {
        $sql = "INSERT INTO projects (title, description) VALUES ('$title', '$description')";
        $this->conn->query($sql);
        $projectId = $this->conn->insert_id;

        // Insert into project_owner_pivot
        $sql = "INSERT INTO project_owner_pivot (project_id, owner_id) VALUES ($projectId, $ownerId)";
        $this->conn->query($sql);

        // Insert into project_status_pivot
        $sql = "INSERT INTO project_status_pivot (project_id, status_id) VALUES ($projectId, $statusId)";
        $this->conn->query($sql);

        return $projectId;
    }

    private function updateProject($projectId, $title, $description, $ownerId, $statusId)
    {
        $sql = "UPDATE projects SET title = '$title', description = '$description' WHERE id = $projectId";
        $this->conn->query($sql);

        // Update project_owner_pivot
        $sql = "UPDATE project_owner_pivot SET owner_id = $ownerId WHERE project_id = $projectId";
        $this->conn->query($sql);

        // Update project_status_pivot
        $sql = "UPDATE project_status_pivot SET status_id = $statusId WHERE project_id = $projectId";
        $this->conn->query($sql);

        echo '<div class="alert alert-success" role="alert">A <strong>' . $title . '</strong> projekt sikeresen frissítve!</div>';
    }

    private function deleteProject($projectId)
    {
        // Delete from project_owner_pivot
        $sqlOwner = "DELETE FROM project_owner_pivot WHERE project_id = $projectId";
        $resultOwner = $this->conn->query($sqlOwner);

        // Delete from project_status_pivot
        $sqlStatus = "DELETE FROM project_status_pivot WHERE project_id = $projectId";
        $resultStatus = $this->conn->query($sqlStatus);

        // Delete from projects
        $sqlProject = "DELETE FROM projects WHERE id = $projectId";
        $resultProject = $this->conn->query($sqlProject);

        // Check if all deletions were successful
        if ($resultOwner && $resultStatus && $resultProject) {
            return true;
        } else {
            return false;
        }
    }

    private function getOwnerIdByEmail($email)
    {
        $ownerId = null;

        $sql = "SELECT id FROM owners WHERE email = '$email'";
        $result = $this->conn->query($sql);

        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $ownerId = $row['id'];
        }

        return $ownerId;
    }

    private function saveOwner($name, $email)
    {
        $sql = "INSERT INTO owners (name, email) VALUES ('$name', '$email')";
        if ($this->conn->query($sql) === TRUE) {
            return $this->conn->insert_id;
        } else {
            return null;
        }
    }

    public function listProjects($params)
    {

        $projects = $this->getProjects("all", $params);

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
        foreach ($projectStatuses as $key => $value) {
            $projectList_html .= '<option value="' . $key . '" ' . (isset($params['qstatus']) && $params['qstatus'] == $key ? " selected " : "") . '>' . $value . '</option>';
        }

        $projectList_html .= '</select>
                        <button class="btn btn-sm btn-secondary">OK</button>
                        </form>
                    </div>
                </div>
            ';

        foreach ($projects as $project) {
            $projectList_html .= '
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

    public function delProjects($projectId)
    {
        $projectDetails = $this->getProjects($projectId);
        $projectdel = $this->deleteProject($projectId);
        if ($projectdel) echo '<div class="alert alert-success" role="alert">A <strong>' . $projectDetails[0]['project_title'] . '</strong> projekt törlésre került!</div>';
    }

    public function newProjects()
    {
        if (isset($_POST["project_name"])) {
            $projectName = $_POST["project_name"];
            $projectDesc = $_POST["project_desc"];
            $projectStatus = $_POST["project_status"];
            $ownerName = $_POST["owner_name"];
            $ownerEmail = $_POST["owner_email"];

            //Check if owner exists or create new one
            if ($this->getOwnerIdByEmail($ownerEmail)) {
                $ownerId = $this->getOwnerIdByEmail($ownerEmail);
            } else {
                $ownerId = $this->saveOwner($ownerName, $ownerEmail);
            }

            $projectsave = $this->saveProject($projectName, $projectDesc, $ownerId, $projectStatus);
            if ($projectsave) echo '<div class="alert alert-success" role="alert">A <strong>' . $projectName . '</strong> projekt sikeresen mentve!</div>';
        } else {
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
            $template = file_get_contents('template/project.html');
            $template = preg_replace($pattern, $replacement, $template);
            echo $template;
        }
    }

    public function editProjects($projectId)
    {
        $projectDetails = $this->getProjects($projectId);
        if (isset($_POST["project_name"])) {
            $projectName = $_POST["project_name"];
            $projectDesc = $_POST["project_desc"];
            $projectStatus = $_POST["project_status"];
            $ownerName = $_POST["owner_name"];
            $ownerEmail = $_POST["owner_email"];

            if ($this->getOwnerIdByEmail($ownerEmail)) {
                $ownerId = $this->getOwnerIdByEmail($ownerEmail);
            } else {
                $ownerId = $this->saveOwner($ownerName, $ownerEmail);
            }

            $projectsave = $this->updateProject($projectDetails[0]['project_id'], $projectName, $projectDesc, $ownerId, $projectStatus);
            if ($projectsave) echo '<div class="alert alert-success" role="alert">A <strong>' . $projectName . '</strong> projekt sikeresen mentve!</div>';
        } else {
            $currvals = array(
                "%%project_name%%" => $projectDetails[0]['project_title'],
                "%%project_desc%%" => $projectDetails[0]['project_description'],
                "%%owner_name%%" => $projectDetails[0]['owner_name'],
                "%%owner_email%%" => $projectDetails[0]['owner_email'],
                "%%project_status-1%%" => ($projectDetails[0]['status_id'] == 1 ? ' selected ' : ''),
                "%%project_status-2%%" => ($projectDetails[0]['status_id'] == 2 ? ' selected ' : ''),
                "%%project_status-3%%" => ($projectDetails[0]['status_id'] == 3 ? ' selected ' : '')
            );
            $template = file_get_contents('template/project.html');
            $template = str_replace(array_keys($currvals), array_values($currvals), $template);
            echo $template;
        }
    }
}
