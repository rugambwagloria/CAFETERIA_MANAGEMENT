<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


session_start();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php");
    exit();
}

require_once("../config/database.php");

$username=trim($_POST['username']);

$password=$_POST['password'];

$sql="SELECT * FROM users WHERE username=?";

$stmt=$conn->prepare($sql);

$stmt->bind_param("s",$username);

$stmt->execute();

$result=$stmt->get_result();

if($result->num_rows==1){

    $user=$result->fetch_assoc();

    if($password==$user['password']){

        $_SESSION['user']=$user['fullname'];

        $_SESSION['role']=$user['role'];

        header("Location: dashboard.php");

        exit();

    }

}

header("Location: index.php?error=1");

exit();

?>