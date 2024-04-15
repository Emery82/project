<?php

class Project
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;        
    }

    private function getProjects($projectId = null, $params = [])
    {
        $projects = [];
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
                $project = [
                    'project_id' => $row['project_id'],
                    'project_title' => $row['project_title'],
                    'project_description' => $row['project_description'],
                    'owner_id' => $row['owner_id'],
                    'owner_name' => $row['owner_name'],
                    'owner_email' => $row['owner_email'],
                    'status_id' => $row['status_id'],
                    'status_name' => $row['status_name']
                ];
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
            return false;
        }

        $stmt->bind_param("ss", $title, $description);
        $stmt->execute();
        $projectId = $stmt->insert_id;
        $stmt->close();

        if ($projectId === false) {
            return false;
        }

        // Insert into project_owner_pivot
        $sql = "INSERT INTO project_owner_pivot (project_id, owner_id) VALUES (?, ?)";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            return false;
        }

        $stmt->bind_param("ii", $projectId, $ownerId);
        $stmt->execute();
        $stmt->close();

        // Insert into project_status_pivot
        $sql = "INSERT INTO project_status_pivot (project_id, status_id) VALUES (?, ?)";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
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
            return false;
        }

        $stmt->bind_param("ssi", $title, $description, $projectId);
        $stmt->execute();
        $stmt->close();

        // Update project_owner_pivot
        $sql = "UPDATE project_owner_pivot SET owner_id = ? WHERE project_id = ?";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            return false;
        }

        $stmt->bind_param("ii", $ownerId, $projectId);
        $stmt->execute();
        $stmt->close();

        // Update project_status_pivot
        $sql = "UPDATE project_status_pivot SET status_id = ? WHERE project_id = ?";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
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
        $sqlOwner = "DELETE FROM project_owner_pivot WHERE project_id = ?";
        $stmtOwner = $this->conn->prepare($sqlOwner);
        if ($stmtOwner === false) {
            return false;
        }

        $stmtOwner->bind_param("i", $projectId);
        $stmtOwner->execute();
        $stmtOwner->close();

        $sqlStatus = "DELETE FROM project_status_pivot WHERE project_id = ?";
        $stmtStatus = $this->conn->prepare($sqlStatus);
        if ($stmtStatus === false) {
            return false;
        }

        $stmtStatus->bind_param("i", $projectId);
        $stmtStatus->execute();
        $stmtStatus->close();

        $sqlProject = "DELETE FROM projects WHERE id = ?";
        $stmtProject = $this->conn->prepare($sqlProject);
        if ($stmtProject === false) {
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

        $sql = "SELECT id FROM owners WHERE email = ?";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            return null;
        }

        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $ownerId = $row['id'];
        }

        return $ownerId;
    }

    private function saveOwner($name, $email)
    {
        $sql = "INSERT INTO owners (name, email) VALUES (?, ?)";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            return null;
        }

        $stmt->bind_param("ss", $name, $email);
        $stmt->execute();
        $ownerId = $stmt->insert_id;
        $stmt->close();

        return $ownerId;
    }

    private function notifyChanges($prev = [], $now = [])
    {
        global $projectarrays, $config;

        $texts = $projectarrays['formNames'];

        $diff = array_diff_assoc($now, $prev);
        if ($diff) {
            $emailbody = "\nKedves " . htmlspecialchars($prev['owner_name'], ENT_QUOTES, 'UTF-8') . "!\nA Projekt adataiban változások történtek!\n";
            $emailbody .= "Projekt neve: " . htmlspecialchars($prev['project_title'], ENT_QUOTES, 'UTF-8') . "\n\nVáltozások:\n";

            foreach ($diff as $key => $value) {
                if ($texts[$key]) $emailbody .= "" . $texts[$key] . ": " . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . "\n";
            }

            $emailbody .= "\nÜdvözlettel:\nProject Handler";
            if ($config['sendmail']) {
                $to = $prev['owner_email'];
                $subject = '=?UTF-8?B?' . base64_encode('[Project Handler]] Módosítások történtek egy projekt adataiban') . '?';
                $message = $emailbody;
                $headers = "From: noreply@projecthandler.org\r\n";
                $headers .= "Reply-To: noreply@\r\n";
                $headers .= "MIME-Version: 1.0\r\n";
                $headers .= "Content-type: text/plain; charset=UTF-8\r\n";

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

        // Generate status options
        $statusOptions = '';
        foreach ($projectStatuses as $key => $value) {
            $statusOptions .= '<option value="' . $key . '" ' . (isset($params['qstatus']) && $params['qstatus'] == $key ? " selected " : "") . '>' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</option>';
        }

        // Define replacements
        $replacements = [
            'status_options' => $statusOptions,
        ];

        // Import and replace placeholders
        $projectList_html = $this->importAndReplace('template/project_list_header.php', $replacements);


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
                // Use htmlspecialchars() to encode user input when echoing into HTML attributes
                $pagination .= '<li class="page-item ' . htmlspecialchars($active) . '"><a class="page-link" href="?page=' . htmlspecialchars($i) . '">' . htmlspecialchars($i) . '</a></li>';
            }
            $pagination .= '</ul>';
        }

        //$projectList_html .= '<div id="formmessage"></div>';
        foreach (array_slice($projects, $offset, $limit) as $project) {
            // Define replacements
            $replacements = [
                'project_id' => htmlspecialchars($project['project_id']),
                'project_title' => htmlspecialchars($project['project_title'], ENT_QUOTES, 'UTF-8'),
                'owner_name' => htmlspecialchars($project['owner_name'], ENT_QUOTES, 'UTF-8'),
                'owner_email' => htmlspecialchars($project['owner_email'], ENT_QUOTES, 'UTF-8'),
                'status_name' => htmlspecialchars($project['status_name'], ENT_QUOTES, 'UTF-8'),
            ];

            // Import and replace placeholders
            $projectList_html .= $this->importAndReplace('template/project_template.php', $replacements);
        }

        // Echo the HTML after sanitizing user input
        echo $projectList_html . $pagination;

    }

    public function delProjects($projectId)
    {
        $projectDetails = $this->getProjects($projectId);
        $projectdel = $this->deleteProject($projectId);
        if ($projectdel) echo '<div class="alert alert-success" role="alert">A <strong>' . htmlspecialchars($projectDetails[0]['project_title'], ENT_QUOTES, 'UTF-8') . '</strong> projekt törlésre került!</div>';
    }

    public function manageProject($projectId = null)
    {
        if ($projectId !== null) {
            // Editing existing project
            $projectDetails = $this->getProjects($projectId);
        }

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

            if ($projectId !== null) {
                $projectSave = $this->updateProject($projectId, $projectName, $projectDesc, $ownerId, $projectStatus);
                $messagePrefix = "szerkesztve";
            } else {
                $projectSave = $this->saveProject($projectName, $projectDesc, $ownerId, $projectStatus);
                $messagePrefix = "létrehozva";
            }

            if ($projectSave) {
                echo '<div class="alert alert-success" role="alert">A(z) <strong>' . htmlspecialchars($projectName, ENT_QUOTES, 'UTF-8') . '</strong> projekt sikeresen ' . $messagePrefix . '!</div>';
            }
        } else {
            $currvals = [
                "%%project_name%%" => isset($projectDetails) ? htmlspecialchars($projectDetails[0]['project_title'], ENT_QUOTES, 'UTF-8') : "",
                "%%project_desc%%" => isset($projectDetails) ? htmlspecialchars($projectDetails[0]['project_description'], ENT_QUOTES, 'UTF-8') : "",
                "%%owner_name%%" => isset($projectDetails) ? htmlspecialchars($projectDetails[0]['owner_name'], ENT_QUOTES, 'UTF-8') : "",
                "%%owner_email%%" => isset($projectDetails) ? htmlspecialchars($projectDetails[0]['owner_email'], ENT_QUOTES, 'UTF-8') : "",
                "%%project_status-1%%" => (isset($projectDetails) && $projectDetails[0]['status_id'] == 1) ? ' selected ' : '',
                "%%project_status-2%%" => (isset($projectDetails) && $projectDetails[0]['status_id'] == 2) ? ' selected ' : '',
                "%%project_status-3%%" => (isset($projectDetails) && $projectDetails[0]['status_id'] == 3) ? ' selected ' : ''
            ];
            $template = file_get_contents('template/project.html');
            $template = str_replace(array_keys($currvals), array_values($currvals), $template);
            echo $template;
        }
    }

    public function delProjectsAjax($projectId)
    {
        $projectdel = $this->deleteProject($projectId);
        if ($projectdel) {
            $response = [
                'success' => true,
                'message' => '<div class="alert alert-success" role="alert">A projekt törlése sikeres!</div>'
            ];
        } else {
            $response = [
                'success' => false,
                'message' => '<div class="alert alert-danger" role="alert">A projekt törlése sikertelen!</div>'
            ];
        }
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    private function importAndReplace($filePath, $replacements) {
        // Check if the file exists
        if (!file_exists($filePath)) {
            throw new Exception("File '$filePath' not found.");
        }
    
        // Read the content of the file
        $content = file_get_contents($filePath);
    
        // Replace placeholders with provided values
        foreach ($replacements as $placeholder => $replacement) {
            $content = str_replace("%$placeholder%", $replacement, $content);
        }
    
        return $content;
    }
}

?>