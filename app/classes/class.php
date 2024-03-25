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
                    statuses ON project_status_pivot.status_id = statuses.id
                WHERE 1";

        if ($projectId !== null && is_numeric($projectId)) {
            $sql .= " AND projects.id = ?";
        }

        if (array_key_exists('qstatus', $params)) {
            $sql .= " AND statuses.id = ?";
        }

        $sql .= " ORDER BY projects.id DESC";

        $stmt = $this->conn->prepare($sql);

        if ($stmt === false) {
            // Handle error
            return $projects;
        }

        if ($projectId !== null && is_numeric($projectId)) {
            $stmt->bind_param("i", $projectId);
        }

        if (array_key_exists('qstatus', $params)) {
            $stmt->bind_param("i", $params['qstatus']);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        if ($result) {
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
        $sql = "INSERT INTO projects (title, description) VALUES (?, ?)";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            // Handle error
            return false;
        }

        $stmt->bind_param("ss", $title, $description);
        $stmt->execute();
        $projectId = $stmt->insert_id;
        $stmt->close();

        if ($projectId === false) {
            // Handle error
            return false;
        }

        // Insert into project_owner_pivot
        $sql = "INSERT INTO project_owner_pivot (project_id, owner_id) VALUES (?, ?)";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            // Handle error
            return false;
        }

        $stmt->bind_param("ii", $projectId, $ownerId);
        $stmt->execute();
        $stmt->close();

        // Insert into project_status_pivot
        $sql = "INSERT INTO project_status_pivot (project_id, status_id) VALUES (?, ?)";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            // Handle error
            return false;
        }

        $stmt->bind_param("ii", $projectId, $statusId);
        $stmt->execute();
        $stmt->close();

        return $projectId;
    }

    private function updateProject($projectId, $title, $description, $ownerId, $statusId)
    {
        // Previous project details
        $prev = $this->getProjects($projectId);

        $sql = "UPDATE projects SET title = ?, description = ? WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            // Handle error
            return false;
        }

        $stmt->bind_param("ssi", $title, $description, $projectId);
        $stmt->execute();
        $stmt->close();

        // Update project_owner_pivot
        $sql = "UPDATE project_owner_pivot SET owner_id = ? WHERE project_id = ?";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            // Handle error
            return false;
        }

        $stmt->bind_param("ii", $ownerId, $projectId);
        $stmt->execute();
        $stmt->close();

        // Update project_status_pivot
        $sql = "UPDATE project_status_pivot SET status_id = ? WHERE project_id = ?";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            // Handle error
            return false;
        }

        $stmt->bind_param("ii", $statusId, $projectId);
        $stmt->execute();
        $stmt->close();

        // Project details now
        $now = $this->getProjects($projectId);

        $this->notifyChanges($prev[0], $now[0]);

        return true;
    }

    private function deleteProject($projectId)
    {
        // Delete from project_owner_pivot
        $sqlOwner = "DELETE FROM project_owner_pivot WHERE project_id = ?";
        $stmtOwner = $this->conn->prepare($sqlOwner);
        if ($stmtOwner === false) {
            // Handle error
            return false;
        }

        $stmtOwner->bind_param("i", $projectId);
        $stmtOwner->execute();
        $stmtOwner->close();

        // Delete from project_status_pivot
        $sqlStatus = "DELETE FROM project_status_pivot WHERE project_id = ?";
        $stmtStatus = $this->conn->prepare($sqlStatus);
        if ($stmtStatus === false) {
            // Handle error
            return false;
        }

        $stmtStatus->bind_param("i", $projectId);
        $stmtStatus->execute();
        $stmtStatus->close();

        // Delete from projects
        $sqlProject = "DELETE FROM projects WHERE id = ?";
        $stmtProject = $this->conn->prepare($sqlProject);
        if ($stmtProject === false) {
            // Handle error
            return false;
        }

        $stmtProject->bind_param("i", $projectId);
        $stmtProject->execute();
        $stmtProject->close();

        return true;
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

    private function notifyChanges($prev = array(), $now = array())
    {
        global $projectarrays;
        $texts = $projectarrays['formNames'];

        $diff = array_diff_assoc($now, $prev);
        if ($diff) {
            global $config;

            $emailbody = "\nKedves " . $prev['owner_name'] . "!\nA Projekt adataiban változások történtek!\n";
            $emailbody .= "Projekt neve: " . $prev['project_title'] . "\n\nVáltozások:\n";

            foreach ($diff as $key => $value) {
                if ($texts[$key]) $emailbody .= "" . $texts[$key] . ": " . $value . "\n";
            }

            $emailbody .= "\nÜdvözlettel:\nProject Handler";
            if ($config['sendmail']) {
                // Recipient
                $to = $prev['owner_email'];

                // Subject
                $subject = '=?UTF-8?B?' . base64_encode('[Project Handler]] Módosítások történtek egy projekt adataiban') . '?=';

                // Message
                $message = $emailbody;

                // Headers
                $headers = "From: noreply@projecthandler.org\r\n";
                $headers .= "Reply-To: noreply@\r\n";
                $headers .= "MIME-Version: 1.0\r\n";
                $headers .= "Content-type: text/plain; charset=UTF-8\r\n";

                // Send email
                if (mail($to, $subject, $message, $headers)) {
                    return true;
                } else {
                    return false;
                }
            } else {
                echo "<p><strong>A következő email kerülne kiküldésre a kapcsolattartó számára, ha az email küldés engedélyezve van a config.php-ben és a mail fv. engedélyezve van.</strong></p>";
                echo '<code>' . nl2br($emailbody) . '</code>';
            }
        }
    }

    public function listProjects($params)
    {
        global $config, $projectarrays;

        $projects = $this->getProjects("all", $params);

        $projectStatuses = $projectarrays['projectStatuses'];

        // List projects
        $projectList_html = '
                <div class="row">
                    <div class="col-md-12">
                        <h3>Projektek</h3>
                    </div>
                    <div class="col-md-12">                                
                        
                        <form method="get" action="" class="form-control">
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

        // Paginate

        $page = isset($params['page']) ? $params['page'] : 1;
        $limit = $config['projectsperpage'];
        $offset = ($page - 1) * $limit;

        $totalPages = ceil(count($projects) / $limit);
        $currentPage = min($page, $totalPages);

        $pagination = "";
        if ($totalPages != 1) {
            $pagination .= '<ul class="pagination">';
            for ($i = 1; $i <= $totalPages; $i++) {
                $active = $i == $currentPage ? 'active' : '';
                $pagination .= '<li class="page-item ' . $active . '"><a class="page-link" href="?page=' . $i . '">' . $i . '</a></li>';
            }
            $pagination .= '</ul>';
        }
        $projectList_html .= '<div id="formmessage"></div>';
        foreach (array_slice($projects, $offset, $limit) as $project) {
            $projectList_html .= '
                    <div class="projectbox" id="project-' . $project['project_id'] . '">
                        <div class="row">
                            <div class="projectleft col-md-8"> 
                                <h4>' . $project['project_title'] . '</h4>
                                <small class="projectowner">' . $project['owner_name'] . ' (' . $project['owner_email'] . ')</small>
                                <div class="projectbuttons">
                                    <a href="/?projectedit=' . $project['project_id'] . '" class="btn btn-primary">Szerkesztés</a>
                                    <!-- a href="/?projectdel=' . $project['project_id'] . '" class="btn btn-danger">Törlés</a -->
                                    <a href="javascript:void(0);" onclick="deleteProject(' . $project['project_id'] . ')" class="btn btn-danger">Törlés</a>

                                </div>
                            </div>
                            <div class="projectright col-md-4">
                                <small class="prjectstatus">' . $project['status_name'] . '</small>      
                                
                            </div>
                        </div>
                    </div>';
        }
        echo $projectList_html . $pagination;
    }

    public function delProjects($projectId)
    {
        $projectDetails = $this->getProjects($projectId);
        $projectdel = $this->deleteProject($projectId);
        if ($projectdel) echo '<div class="alert alert-success" role="alert">A <strong>' . $projectDetails[0]['project_title'] . '</strong> projekt törlésre került!</div>';
    }

    public function manageProject($projectId = null)
    {
        if ($projectId !== null) {
            // Editing existing project
            $projectDetails = $this->getProjects($projectId);
        }

        if (isset($_POST["project_name"])) {
            // Form submitted, handle project creation or update
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

            if ($projectId !== null) {
                // Editing existing project
                $projectSave = $this->updateProject($projectId, $projectName, $projectDesc, $ownerId, $projectStatus);
                $messagePrefix = "szerkesztve";
            } else {
                // Creating new project
                $projectSave = $this->saveProject($projectName, $projectDesc, $ownerId, $projectStatus);
                $messagePrefix = "létrehozva";
            }

            if ($projectSave) {
                echo '<div class="alert alert-success" role="alert">A <strong>' . $projectName . '</strong> projekt sikeresen ' . $messagePrefix . '!</div>';
            }
        } else {
            // Display form
            $currvals = array(
                "%%project_name%%" => isset($projectDetails) ? $projectDetails[0]['project_title'] : "",
                "%%project_desc%%" => isset($projectDetails) ? $projectDetails[0]['project_description'] : "",
                "%%owner_name%%" => isset($projectDetails) ? $projectDetails[0]['owner_name'] : "",
                "%%owner_email%%" => isset($projectDetails) ? $projectDetails[0]['owner_email'] : "",
                "%%project_status-1%%" => (isset($projectDetails) && $projectDetails[0]['status_id'] == 1) ? ' selected ' : '',
                "%%project_status-2%%" => (isset($projectDetails) && $projectDetails[0]['status_id'] == 2) ? ' selected ' : '',
                "%%project_status-3%%" => (isset($projectDetails) && $projectDetails[0]['status_id'] == 3) ? ' selected ' : ''
            );
            $template = file_get_contents('template/project.html');
            $template = str_replace(array_keys($currvals), array_values($currvals), $template);
            echo $template;
        }
    }

    public function delProjectsAjax($projectId)
    {
        $projectDetails = $this->getProjects($projectId);
        $projectdel = $this->deleteProject($projectId);
        if ($projectdel) {
            $response = array(
                'success' => true,
                'message' => '<div class="alert alert-success" role="alert">A projekt törlése sikeres!</div>'
            );
        } else {
            $response = array(
                'success' => false,
                'message' => '<div class="alert alert-danger" role="alert">A projekt törlése sikertelen!</div>'
            );
        }
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
}
