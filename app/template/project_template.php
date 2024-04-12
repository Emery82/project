<div class="projectbox" id="project-%project_id%">
    <div class="row">
        <div class="projectleft col-md-8"> 
            <h4>%project_title%</h4>
            <small class="projectowner">%owner_name% (%owner_email%)</small>
            <div class="projectbuttons">
                <a href="/?projectedit=%project_id%" class="btn btn-primary">Szerkesztés</a>
                <!-- a href="/?projectdel=%project_id%" class="btn btn-danger">Törlés</a -->
                <a href="javascript:void(0);" onclick="deleteProject(%project_id%)" class="btn btn-danger">Törlés</a>
            </div>
        </div>
        <div class="projectright col-md-4">
            <small class="prjectstatus">%status_name%</small>      
        </div>
    </div>
</div>
