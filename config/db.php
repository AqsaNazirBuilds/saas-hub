<?php



if (session_status() === PHP_SESSION_NONE) {

    session_start();

}



$host_name = $_SERVER['HTTP_HOST'];



/* ================= LOCALHOST ================= */

if ($host_name === 'localhost') {



    // IMPORTANT: yahan folder name soos_project hai

    define('BASE_URL', 'http://localhost/SAAS_PROJECT/');
    $db_host = "localhost";

    $db_user = "root";

    $db_pass = "";

    $db_name = "saas";   // confirm your local database name

    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
/* ================= LIVE SERVER ================= */
} else {

    define('BASE_URL', 'https://laiba-lms.great-site.net/SaaS/');



    $db_host = "sql303.infinityfree.com";

    $db_user = "if0_40800821";

    $db_pass = "r7890laiba1";

    $db_name = "if0_40800821_saas_system";  // confirm live DB name

}



$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);



if ($conn->connect_error) {

    die("Database Connection Failed: " . $conn->connect_error);

}



$db = $conn;



?>