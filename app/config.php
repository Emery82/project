<?php
$config = array(
    "db" => array(
        "dbhost" => "db",
        "dbname" => "pamutlabor",
        "dbuser" => "pamutlabor",
        "dbpass" => "pamutlabor",
    ),
    "sendmail" => false,
    "projectsperpage" => 10
);


$projectarrays = array(
    "projectStatuses" => array(
        1 => "Fejlesztésre vár",
        2 => "Folyamatban",
        3 => "Kész"
    ),
    "formNames" => array(
        "status_id" => "",
        "owner_id" => "",
        "project_title" => "Név",
        "project_description" => "Leírás",
        "owner_name" => "Kapcsolattartó neve",
        "owner_email" => "Kapcsolattartó email címe",
        "status_name" => "Státusz"
    )
);