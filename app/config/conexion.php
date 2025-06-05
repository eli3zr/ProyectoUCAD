<?php

    //error_reporting(E_ERROR);
    $servidor = "localhost";
    $usuario = "root";
    $clave = "";
    $base = "jobstrack";

    $con = mysqli_connect($servidor, $usuario, $clave, $base, 3306);

    if($con){
        $con->set_charset("utf8");
    }else{
        $response = array('success'=>false, 'error'=>"No hay conexión a la base de datos");

        echo json_encode($response);
        exit();
    }