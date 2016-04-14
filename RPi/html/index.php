<html>
<head>
<meta http-equiv="refresh" content="60" >
<title>Sprinkler Project Group 15</title>
<style>
div.content { width: 295px } 
body { font-family: Verdana, Geneva, sans-serif; }
</style>
</head>

<body>


<?php
// sets up php udp socket client (can ask the python server to do things)

$server = 'localhost';
$port = 8675;
 
if(!($sock = socket_create(AF_INET, SOCK_DGRAM, 0)))
{
    $errorcode = socket_last_error();
    $errormsg = socket_strerror($errorcode);
     
    die("Couldn't create socket: [$errorcode] $errormsg \n");
}

// requests the information to fill out the soil moisture data (and later temp data)

$toSendMessage = "data";

$soilMoistureNumber = "0";

    if(! socket_sendto($sock, $toSendMessage , strlen($toSendMessage) , 0 , $server , $port))
    {
            $errorcode = socket_last_error();
            $errormsg = socket_strerror($errorcode);
         
    }
         
    //Now receive reply from server (we don't use this right now)
    if(socket_recv ( $sock , $reply , 2045 , MSG_WAITALL ) === FALSE)
    {
            $errorcode = socket_last_error();
            $errormsg = socket_strerror($errorcode);
         
    }
        
$soilMoistureNumber = $reply;

// logic for figuring out the soil moisture string    
$soilMoisture = "Dry"; // defaults to dry

if ((int)$soilMoistureNumber == 1) {
    $soilMoisture = "Wet";
}
else if ((int)$soilMoistureNumber == 2) {
    $soilMoisture = "Moist";
}
else if ((int)$soilMoistureNumber == 3) {
    $soilMoisture = "Moist";
}
else if ((int)$soilMoistureNumber == 4) {
    $soilMoisture = "Dry";
}

// sets up MYSQL
    
    $username="group15";
    $password="finalproject";
    $database="finalproject";
    mysql_connect("localhost",$username,$password);
    mysql_select_db($database) or die( "Unable to select database");
    
    $curday = ((date("w") + 6) % 7) + 1;
    
    // SCHEDULE
    $query= "SELECT * FROM Schedule";
    $result=mysql_query($query);
    $schedNumbers = "0";
    while($row = mysql_fetch_array($result))
    {
        if ($row['Day'] == (string)$curday) {
            $schedNumbers = $row['Time'];
        }
    }
    
    $schedStr = "No Program";
    
    if ((int)$schedNumbers == 0) {
        $schedStr = "No Program";
    }
    else if ((int)$schedNumbers < 4){
        $schedStr = "Program {$schedNumbers}";
    }
    else {
        $schedStr = "Programs ";
        $strlen = strlen($schedNumbers);
        for ($i = 0; $i < ($strlen - 1); $i++) {
            $schedStr .= $schedNumbers[$i].",";
        }
        $schedStr .= $schedNumbers[$i];
    }
        
    

// sets up weather forecast information

include('lib/forecast.io.php');

$api_key = '9c41787b0de72946392e028d071e96d5';
$latitude = '34.0227';
$longitude = '-118.2798';
$units = 'us';  // Can be set to 'us', 'si', 'ca', 'uk' or 'auto' (see forecast.io API); default is auto
$lang = 'en'; // Can be set to 'en', 'de', 'pl', 'es', 'fr', 'it', 'tet' or 'x-pig-latin' (see forecast.io API); default is 'en'

$forecast = new ForecastIO($api_key, $units, $lang);

$condition = $forecast->getCurrentConditions($latitude, $longitude);

// sets the filename based on the current condition icon
$icon = $condition->getIcon();
// echo $icon;
$weatherFile = "Climacons/SVG/Sun.svg"; // defaults to the sun image
if ($icon == "clear-day") {
    $weatherFile = "Climacons/SVG/Sun.svg";
}
else if ($icon == "clear-night") {
    $weatherFile = "Climacons/SVG/Moon.svg";
}
else if ($icon == "rain") {
    $weatherFile = "Climacons/SVG/Cloud-Drizzle.svg";
    $toSendMessage = "skip";
    // we also send the skip message on rain
    if(! socket_sendto($sock, $toSendMessage , strlen($toSendMessage) , 0 , $server , $port))
        {
            $errorcode = socket_last_error();
            $errormsg = socket_strerror($errorcode);
         
    }
         
        //Now receive reply from server (we don't use this right now)
    if(socket_recv ( $sock , $reply , 2045 , MSG_WAITALL ) === FALSE)
    {
            $errorcode = socket_last_error();
            $errormsg = socket_strerror($errorcode);
         
    }
}
else if ($icon == "snow") {
    $weatherFile = "Climacons/SVG/Cloud-Snow.svg";
}
else if ($icon == "sleet") {
    $weatherFile = "Climacons/SVG/Cloud-Hail.svg";
}
else if ($icon == "wind") {
    $weatherFile = "Climacons/SVG/Cloud-Wind.svg";
}
else if ($icon == "fog") {
    $weatherFile = "Climacons/SVG/Cloud-Fog-Alt.svg";
}
else if ($icon == "cloudy") {
    $weatherFile = "Climacons/SVG/Cloud.svg";
}
else if ($icon == "partly-cloudy-day") {
    $weatherFile = "Climacons/SVG/Cloud-Sun.svg";
}
else if ($icon == "partly-cloudy-night") {
    $weatherFile = "Climacons/SVG/Cloud-Moon.svg";
}

//echo 'Current Weather: '.$condition->getSummary(). "<br>";
//echo 'Current Temp: '.$condition->getTemperature(). "(˙F)<br>";

// sets up sending messages to python server on button presses
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $toSendFlag = 0; // turns to 1 when there is something to send
    $toSendMessage = ""; // sends nothing by default
    // if a button is pressed, send the appropriate message to the python server
    if (isset($_POST["z1"])) {
        $toSendFlag = 1;
        $toSendMessage = "z1";
    }
    else if (isset($_POST["z2"])) {
        $toSendFlag = 1;
        $toSendMessage = "z2";
    }
    else if (isset($_POST["z3"])) {
        $toSendFlag = 1;
        $toSendMessage = "z3";
    }
    else if (isset($_POST["z4"])) {
        $toSendFlag = 1;
        $toSendMessage = "z4";
    }
    else if (isset($_POST["z5"])) {
        $toSendFlag = 1;
        $toSendMessage = "z5";
    }
    else if (isset($_POST["z6"])) {
        $toSendFlag = 1;
        $toSendMessage = "z6";
    }
    else if (isset($_POST["off"])) {
        $toSendFlag = 1;
        $toSendMessage = "off";
    }
    else if (isset($_POST["skip"])) {
        $toSendFlag = 1;
        $toSendMessage = "skip";
        // ALSO UPDATES THE SCHEDULE STRING TO "SKIPPING" (need to fix this)
        $schedStr = "SKIP";
    }
    
    
    //Send the message to the server if flag is sent
    if ($toSendFlag == 1) {
        if(! socket_sendto($sock, $toSendMessage , strlen($toSendMessage) , 0 , $server , $port))
        {
            $errorcode = socket_last_error();
            $errormsg = socket_strerror($errorcode);
         
        }
         
        //Now receive reply from server (we don't use this right now)
        if(socket_recv ( $sock , $reply , 2045 , MSG_WAITALL ) === FALSE)
        {
            $errorcode = socket_last_error();
            $errormsg = socket_strerror($errorcode);
         
        }
    }
}

?>

<div class="content">
<div style="float:left; width:30%;border-right: 1px solid #888888;">
<center>
<img src="<?php echo htmlspecialchars($weatherFile); ?>" alt="Weather.img" style="width:90px;height:90px;"><br>
<?php echo htmlspecialchars($condition->getSummary()); ?> <br>
<?php echo htmlspecialchars($condition->getTemperature()); ?> (˙F) <br>
Soil: <?php echo htmlspecialchars($soilMoisture); ?> <br>
<input type="button" value="Settings" onclick="location='settings.php'"/>
<br>
<br>
</center>
</div>
<div style="float:right; width:67%; margin-left:2px;">

<div style="color:#008ae6"><center><strong><u>SPRINKLER - GROUP 15</u></strong></center></div>
<br>
<b> Today: </b> <?php echo htmlspecialchars($schedStr); ?> <br>
<hr>
<center><b>Website</b></center>
http://<?php echo exec('hostname -I'); ?>/
<br>
<hr>
<center>
<strong><u> Manual Zone Controls </u></strong>
<form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>" method="post">
<input type="submit" value="Skip Today" name = "skip"/>
<input type="submit" value="All Off" name = "off"/>
<br>
<input type="submit" value="1" name = "z1"/>
<input type="submit" value="2" name = "z2"/>
<input type="submit" value="3" name = "z3"/>
<input type="submit" value="4" name = "z4"/>
<input type="submit" value="5" name = "z5"/>
<input type="submit" value="6" name = "z6"/>
</form>
</center>
</div>
</div>


</body>

</html>