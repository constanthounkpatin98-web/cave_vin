<?php

session_start();

if(isset($_GET["id_vin"]) && isset($_SESSION["panier"]))
{
    $id_vin = $_GET["id_vin"];

    if(isset($_SESSION["panier"][$id_vin]))
    {
        unset($_SESSION["panier"][$id_vin]);
    }
}

header("Location: panier.php");
exit();
