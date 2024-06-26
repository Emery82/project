function deleteProject(projectId) {
    if (confirm('Biztosan törölni szeretné ezt a projektet?')) {
        var xhttp = new XMLHttpRequest();
        xhttp.onreadystatechange = function () {
            if (this.readyState == 4 && this.status == 200) {
                var response = JSON.parse(this.responseText);
                if (response.success) {
                    document.getElementById('project-' + projectId).remove();
                    document.getElementById('formmessage').innerHTML = 'Projekt törölve!';
                    //alert(response.message);
                } else {
                    document.getElementById('formmessage').innerHTML = 'Projekt törlés sikertelen.';
                    //alert(response.message);
                }
            }
        };
        xhttp.open('GET', '/?delProjectsAjax=' + projectId, true);
        xhttp.send();
    }
}