<?php
 ini_set('display_errors', 1);
  ini_set('display_startup_errors', 1);
 error_reporting(E_ALL);

require __DIR__."/phpmailer/src/PHPMailer.php";
require __DIR__."/phpmailer/src/Exception.php";
require __DIR__."/phpmailer/src/SMTP.php";

use PHPMailer\PHPMailer\PHPMailer;

$sql = new mysqli('localhost','facilities',null,'menstrual_products');

$reports = $sql->query("SELECT DISTINCT space_id FROM `requests` WHERE `confirmed`=1 AND `reported`=0");

if ($reports->num_rows == 0) {
    die();
}

$reported_ids = [];
while ($row = $reports->fetch_assoc()) {
    $reported_ids[] = $row["space_id"];
}

$in_str = "'" . implode("','",$reported_ids) . "'";

$locations_res = $sql->query("SELECT * FROM `spaces` WHERE `asg_id` IN ($in_str)");
$locations = [];

while($row = $locations_res->fetch_assoc()) {
    $locations[] = $row;
}

$date = date("Y-m-d");
$email = "<table border='0' cellpadding='0' cellspacing='0' width='100%' style='font-family: sans-serif;'>
  <tr>
    <td bgcolor='#4E2A84' style='padding: 50px'>
      <img alt='Northwestern Logo' src='http://util.asg.northwestern.edu/assets/nu_logo.PNG' height='45px' />
    </td>
  </tr>
  <tr>
    <td style='padding-left: 70px; padding-right: 70px; padding-bottom: 10px;'>
      <h1 style='text-align: center; padding-top: 10px;'>Menstrual Products Daily Report - {$date}</h1>

      <table border='1px' cellpadding='5' cellspacing='0' style='border-collapse: collapse; border: 1px solid black;' width='100%'>
        <tr>
          <th>Campus</th>
          <th>Building</th>
          <th>Floor</th>
          <th>Room</th>
          <th>Type</th>
          <th>Space ID</th>
        </tr>";

foreach ($locations as $location) {
    $fl = ltrim($location["floor"], "0");

    $spc_res = $sql->query("SELECT * FROM `space_classifications` WHERE `num`='{$location["space_classification"]}'");
    $spc = $spc_res->fetch_assoc()["name_int"];

    $email .= "<tr>";
        $email .= "<td>{$location["campus"]}</td>";
        $email .= "<td>{$location["building"]}</td>";
        $email .= "<td>{$fl}</td>";
        $email .= "<td>{$location["space_name"]}</td>";
        $email .= "<td>{$spc}</td>";
        $email .= "<td>{$location["space_id"]}</td>";
    $email .= "</tr>";
}

$email .= '</table>
    </td>
  </tr>
  <tr bgcolor="#4E2A84">
    <td style="padding: 10px">
      <img alt="ASG Logo" align="right" style="float: right;" src="http://util.asg.northwestern.edu/assets/asg_logo.PNG" height="35px">
    </td>
  </tr>
</table>';

$mail = new PHPMailer;
$mail->isSMTP();
$mail->IsHTML(true);

$mail->Timeout = 5;

$mail->Host = 'hostsmtp.northwestern.edu';
$mail->Port = 25;

$mail->setFrom('asg-technology@u.northwestern.edu', 'ASG Menstrual Products');

$mail->addAddress('me@scolton.tech', 'Spencer Colton');

$mail->Subject = "Menstrual Product Shortage Digest - $date";

$mail->Body = $email;
$mail->AltBody = "";
try {
    $mail->send();

    $sql->query("UPDATE `requests` SET `reported`=1 WHERE `space_id` IN ($in_str)");
} catch (Exception $e) {

}