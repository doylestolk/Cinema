<?php
// Database credentials, change these to your servers needs
$un = "root";
$pw = "Kikkerskwaken!5";
$db = "cinema";
$hn = "localhost";

// Login popup
function authenticate_user() {
    header('WWW-Authenticate: Basic realm="Cinema"');
    header("HTTP/1.0 401 Unauthorized");
    exit;
}
// If user is blank
if (!isset ($_SERVER['PHP_AUTH_USER'])) {
    authenticate_user();
} else {
    // Connect to MySQL database
    $link = mysqli_connect($hn, $un, $pw, $db)
    or die("Can't connect to database server!");

    // Create and execute query.
    $query = "SELECT username, password FROM userauth WHERE username='$_SERVER[PHP_AUTH_USER]' AND password='$_SERVER[PHP_AUTH_PW]'";
    $result = mysqli_query($link, $query);
    // If nothing was found prompt the user to login again
    if (mysqli_num_rows($result) == 0) {
        authenticate_user();
    } else {
        echo "<div style=\"text-align: center;\"><h3>You are logged in as " . $_SERVER['PHP_AUTH_USER'] . "</h3></div>";
    }
}

?>