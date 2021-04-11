<?php
require_once ("login.php");

/* *************************************************************************************
    DATABASE TO ARRAY & USER INPUT TO VARIABLE & USERNAME TO USERID

**************************************************************************************** */
// Fetch input for amount of seats to reserve
$requested_seats = $_POST['requestedSeats'] ;

// Fetch seat information from hall in database that already has seats taken
$query = "SELECT seatID, status FROM hall ORDER by seatID";
        $result = (mysqli_query($link, $query));

        $hall_array = [];
        while($row = mysqli_fetch_assoc($result)) {
        $hall_array[] = $row;
        }
// Array with seatID as key and status as value
$hall_array_seatID_status = array_column($hall_array, 'status', 'seatID');

// Array with available seatID's as value
$hall_array_free_seats = array_keys($hall_array_seatID_status, null);

// Get userID from database and convert it to php understandable variable
$username = $_SERVER['PHP_AUTH_USER'];
$get_userID_query = "SELECT userID FROM userauth WHERE username= '$username'";
$userID_mysql = (mysqli_query($link, $get_userID_query));
$userID_string = $userID_mysql->fetch_row()[0];


/* *************************************************************************************
    FUNCTIONS

**************************************************************************************** */

// Check if the requested amount of seats to reserve are available in hall
function areSeatsAvailable($requested_seats, $hall_array_free_seats){
    if (count($hall_array_free_seats) < $requested_seats){
        return false;
    }
    else return true;
}

// Function to check if the requested amount of seats are available uninterrupted
function uninterruptedlyAvailable(int $requested_seats, array $hall_array_free_seats){

    $possible_seats= [];
    foreach($hall_array_free_seats as $key => $value){

        if(count($possible_seats) == $requested_seats){
                break;
        }

        $next_seat = array_key_exists($key + 1, $hall_array_free_seats) ? $hall_array_free_seats[$key + 1] : 0;
        $possible_seats[] = $value;

        if ($value + 1 !== $next_seat && count($possible_seats) !==$requested_seats){
            $possible_seats = [];
        }
        }
        return array_key_exists(0 , $possible_seats) ? $possible_seats : false;
}

// Function to split groups in as large as possible subgroups
function divideGroupToFit(int $requested_seats , array $hall_array_free_seats)
{
    $leftover_requested_seats=0;
    $leftover_requested_seats2=0;

    while (!(uninterruptedlyAvailable($requested_seats, $hall_array_free_seats))) {
        $return_uninterruptedlyAvailable=(uninterruptedlyAvailable(--$requested_seats, $hall_array_free_seats));
        ++$leftover_requested_seats;

    }
    $updated_hall_array_free_seats = array_diff($hall_array_free_seats,$return_uninterruptedlyAvailable);
    $merge_leftover_seats = [];
    $leftover_requested_seats2 = $leftover_requested_seats;
    while ($leftover_requested_seats != 0){
        $return_leftover_uninterruptedlyAvailable=uninterruptedlyAvailable($leftover_requested_seats2,$updated_hall_array_free_seats);
        if($return_leftover_uninterruptedlyAvailable){
            $leftover_requested_seats=$leftover_requested_seats - $leftover_requested_seats2;
            $leftover_requested_seats2 = $leftover_requested_seats;
            $merge_leftover_seats = array_merge($merge_leftover_seats,$return_leftover_uninterruptedlyAvailable);
        }else{
            --$leftover_requested_seats2;
        }
    }
    return array_merge(array_values($return_uninterruptedlyAvailable), array_values($merge_leftover_seats));

}

// Function to render seats from array
function render_seats($hall_array){
    echo "<ul style='max-width: 700px; margin: 0px auto; display: flex; flex-wrap: wrap;'>";
    foreach($hall_array as $key => $value) {

        if ($value["status"] == 1) {
            echo "<li style='display: flex; justify-content: center; align-items: center; width:50px; height:50px; background: crimson; margin:10px; color:#fff;'>". $value["seatID"] ."</li>";
        } else {
            echo "<li style='display: flex; justify-content: center; align-items: center; width:50px; height:50px; background: lightgreen; margin:10px;'>". $value["seatID"] ."</li>";
        }
    }
    echo "</ul>";
}

/* *************************************************************************************
    REQUEST HANDLING

**************************************************************************************** */


// Render seats before the user has submitted a request
if(!isset($_POST['requestedSeats'])) {
    render_seats($hall_array);
}

$seats_available_bool = areSeatsAvailable($requested_seats, $hall_array_free_seats);
// If request is bigger than available seats show message
if (!$seats_available_bool) {
    // Render visual representation of the seats in the hall
    render_seats($hall_array);
    echo "<center> <br>"; //Deprecated code but used just for visuals
    echo "There aren't enough seats available to fulfill your request<br>";
    echo "Please try again with a lower amount";
    echo "</center>";

}
// If user has submitted and the hall fits the user request do the following
if(isset($_POST['requestedSeats']) && $seats_available_bool) {

// Get possible seats from the first function, if it's null run other function to split group and get seats
    $returned_possible_seats = (uninterruptedlyAvailable($requested_seats, $hall_array_free_seats));
    if(!$returned_possible_seats){
        $returned_possible_seats = (divideGroupToFit($requested_seats,$hall_array_free_seats));
        asort($returned_possible_seats);
    }

// Get userID from database and convert it to php understandable variable
    $username = $_SERVER['PHP_AUTH_USER'];
    $get_userID_query = "SELECT userID FROM userauth WHERE username= '$username'";
    $userID_mysql = (mysqli_query($link, $get_userID_query));
    $userID_string = $userID_mysql->fetch_row()[0];

// Update database, change seat status in hall and reservation status in reservation
    foreach ($returned_possible_seats as $key => $value) {

        $update_seat_status = "UPDATE hall SET status='1' WHERE seatID='$value'";
        $result = (mysqli_query($link, $update_seat_status));
        $update_reservation_status = "INSERT into reservation (userID , seatID) VALUES ($userID_string ,  $value)";
        $result = (mysqli_query($link, $update_reservation_status));
    }

// Update the hall array with the reserved seats, because only updating the database doesn't update the map in real time
// In retrospect I could have also done this by pulling from the database again after the submit (I think)
    if ($returned_possible_seats){
    foreach ($hall_array as &$key ) {

        if(in_array($key["seatID"], $returned_possible_seats)){
            $key["status"]=1;
        }

    }
// Render visual representation of the seats in the hall and announce the number of the reserved seats
    render_seats($hall_array);
    echo "<div>";
        echo "<br>You have reserved the following seat(s): ";
        foreach ($returned_possible_seats as $key => $value)
            echo "-".$value . " ";
        echo "</div>";
    }


}
/* *************************************************************************************
    PRINT RESERVATIONS FROM USER

**************************************************************************************** */

$get_userID_seatID_query = "SELECT userID , seatID FROM reservation WHERE userID= '$userID_string'";
$userID_seatID_mysql = (mysqli_query($link, $get_userID_seatID_query));
//    $userID_seatID_array = mysqli_fetch_assoc($userID_seatID_mysql);
$userID_seatID_array = [];
while($row = mysqli_fetch_assoc($userID_seatID_mysql)) {
    $userID_seatID_array[] = $row;
}
if(key_exists(0,$userID_seatID_array)) {
    echo "<div><br> You have the following reservations: ";
    foreach ($userID_seatID_array as $item) {
        echo "-" . $item['seatID'] . " ";
    }
    echo "</div>";
}
echo "<br><br>";
?>

<!doctype html>
<html lang="en">

<head>
    <style>
        h1 {text-align: center;}
        p {text-align: center;}
        div {text-align: center;}
    </style>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Document</title>
</head>
<body>
<form align="center" method="post" action="<?php echo $_SERVER['PHP_SELF']?>">
    <label title="Amount of seats to reserve: " for="requestedSeats">Amount of seats to reserve: </label>
    <input type="number" name="requestedSeats" id="requestedSeats" placeholder="number">
    <input type="submit" id="submitBtn" value="Submit" >
</form>
</body>
</html>


