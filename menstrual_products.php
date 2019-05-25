<?php
require __DIR__."/twilio-php-master/Twilio/autoload.php";
use Twilio\Twiml;

$sql = new mysqli("localhost", "facilities", null, "menstrual_products");

$msg = strtoupper($_REQUEST["Body"]);
$number = $_REQUEST["From"];

$response = new Twiml;

if ($msg == "YES" || $msg == "NO") {
    $number_san = $sql->escape_string($number);
    $res = $sql->query("SELECT * FROM `requests` WHERE `number`='$number_san' AND `confirmed` IS NULL");

    if ($res->num_rows > 0) {
        $ext_req = $res->fetch_assoc();
        $id = $ext_req["id"];

        if ($msg == "YES") {
            $sql->query("UPDATE `requests` SET `confirmed`=1 WHERE `id`=$id");
            $response->message("Your request has been confirmed. Facilities Management will be notified. Thank you!");
        } else {
            $sql->query("UPDATE `requests` SET `confirmed`=0 WHERE `id`=$id");
            $response->message("Your request has been cancelled. Reply with a new bathroom code to start a new request.");
        }

        die($response);
    } else {
        $response->message("We couldn't find an existing request for you. To report that menstrual products are running low in a certain bathroom, please reply with that bathroom's code.");
        die($response);
    }
} else {
    $number_san = $sql->escape_string($number);
    $res = $sql->query("SELECT * FROM `requests` WHERE `number`='$number_san' AND `confirmed` IS NULL");

    if ($res->num_rows > 0) {
        $ext_req = $res->fetch_assoc();

        $name_str = get_real_name($sql, $ext_req["space_id"]);

        $response->message("You have an existing request that you have not yet confirmed. Please reply \"YES\" to confirm that you would like to report that menstrual products are running low in $name_str, or \"NO\" to cancel your request.");
        die($response);
    }

    $req_space_id = $msg;

    $res2 = $sql->query("SELECT * FROM `spaces` WHERE asg_id = '$req_space_id'");
    if ($res2->num_rows == 0) {
        $response->message("You have specified an invalid bathroom. Please try again.");
        die($response);
    }

    $space_obj = $res2->fetch_assoc();
    $off_space_id = $sql->escape_string($space_obj["asg_id"]);

    $sql->query("INSERT INTO `requests` (`number`,`space_id`) VALUES ('$number','$off_space_id')");
    if ($sql->errno) {
        $response->message("An error has occurred. Please contact asg-technology@u.northwestern.edu and report this error message: ".$sql->error);
        die($response);
    }

    $desc_str = get_real_name($sql, $off_space_id);
    $final_msg = "You have reported that menstrual products are running low in $desc_str. Is this correct? Reply with YES or NO.";
    $response->message($final_msg);

    exit($response);
}

function get_real_name($sql, $space_id) {
    $res = $sql->query("SELECT * FROM `spaces` WHERE `asg_id`='$space_id'");
    if ($res->num_rows == 0)
        return null;

    $space = $res->fetch_assoc();
    $sc = $space["space_classification"];
    $scres = $sql->query("SELECT * FROM `space_classifications` WHERE `num`='$sc'");
    $scobj = $scres->fetch_assoc();
    $scstr = strtolower($scobj["name_ext"]);

    $bldg = $space["building"];

    $floorstr = null;
    $floor = filter_var(ltrim($space["floor"], "0"), FILTER_VALIDATE_INT);
    if ($floor === false) {
        $floorstr = "floor ".$space["floor"];
    } else {
        $end = $floor % 10;
        switch ($end) {
            case 1: {
                $floorstr = "the ".strval($floor) . "st floor";
                break;
            }
            case 2: {
                $floorstr = "the ".strval($floor) . "nd floor";
                break;
            }
            case 3: {
                $floorstr = "the ".strval($floor) . "rd floor";
                break;
            }
            default: {
                $floorstr = "the ".strval($floor) . "th floor";
                break;
            }
        }
    }

    return "the $scstr on $floorstr of $bldg";
}
