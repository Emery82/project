<?php
class Project {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function getProjects($projectId = null, $params = array()) {
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
            $sql .= " AND projects.id = ".$projectId." ";
        }

        if(array_key_exists('qstatus', $params)){
            $sql .= " AND statuses.id = ".$params['qstatus']." ";
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

    public function listProjects() {
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

    public function saveProject($title, $description, $ownerId, $statusId) {
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

    public function updateProject($projectId, $title, $description, $ownerId, $statusId) {
        $sql = "UPDATE projects SET title = '$title', description = '$description' WHERE id = $projectId";
        $this->conn->query($sql);

        // Update project_owner_pivot
        $sql = "UPDATE project_owner_pivot SET owner_id = $ownerId WHERE project_id = $projectId";
        $this->conn->query($sql);

        // Update project_status_pivot
        $sql = "UPDATE project_status_pivot SET status_id = $statusId WHERE project_id = $projectId";
        $this->conn->query($sql);

        echo '<div class="alert alert-success" role="alert">A <strong>'.$title.'</strong> projekt sikeresen frissítve!</div>';
    }

    public function deleteProject($projectId) {
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
 
    public function getOwnerIdByEmail($email) {
        $ownerId = null;

        $sql = "SELECT id FROM owners WHERE email = '$email'";
        $result = $this->conn->query($sql);

        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $ownerId = $row['id'];
        }

        return $ownerId;
    }

    public function saveOwner($name, $email) {
        $sql = "INSERT INTO owners (name, email) VALUES ('$name', '$email')";
        if ($this->conn->query($sql) === TRUE) {
            return $this->conn->insert_id;
        } else {            
            return null;
        }
    }
}