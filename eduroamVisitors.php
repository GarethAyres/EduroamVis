<?php 
//Visualisation of Visitors at a Eduroam Institutions using google maps v3
//SQL statement based on standard FreeRadius radacct table
//GEOIP with PHP from http://www.php.net/manual/en/book.geoip.php. 
//I installed it using my linux package manager and it worked fine.
//Once this is run it can take a minute or more, depending on the SQL used.
//Best to pipe output to a new html/php file, so others dont re-run code just view its output.
//
//Author = Gareth Ayres of Swansea University
//EMail = g.j.ayres@swansea.ac.uk
//Version 0.3
//
//Copyright 2011 Gareth Ayres
//
//   Licensed under the Apache License, Version 2.0 (the "License");
//   you may not use this file except in compliance with the License.
//   You may obtain a copy of the License at
//
//       http://www.apache.org/licenses/LICENSE-2.0
//
//   Unless required by applicable law or agreed to in writing, software
//   distributed under the License is distributed on an "AS IS" BASIS,
//   WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
//   See the License for the specific language governing permissions and
//   limitations under the License.

?>

<!DOCTYPE html>
<head>
<title>EduroamVis</title>
<meta name="viewport" content="initial-scale=1.0, user-scalable=no" />
<style type="text/css">
html {
	height: 100%
}

body {
	height: 100%;
	margin: 0;
	padding: 0
}

#map_canvas {
	height: 100%
}
</style>
<script type="text/javascript"
	src="http://maps.googleapis.com/maps/api/js?sensor=true">
</script>
<script type="text/javascript">

var map;
var geocoder;
//to = swansea latlong
var to = new google.maps.LatLng(51.63330078125,-3.9667000770569);

//Called on page load
  function initialize() 
  {
	//Sets Swansea at center of map
    var latlng = new google.maps.LatLng(51.609222,-3.981478);
    var myOptions = {
      zoom: 6,
      center: latlng,
      mapTypeId: google.maps.MapTypeId.ROADMAP
    };
    map = new google.maps.Map(document.getElementById("map_canvas"), myOptions);
    geocoder = new google.maps.Geocoder();
    
//***PLOTROAM FUNCTION CALLS
<?php 
$hostName = "radacc.myinstitute.ac.uk";
$usernamedb = "radiususer";
$password = "password";
//make sure the radius user is allowed to access the radius table from the host running this script.
//NASIP is teh range to match wireless NAS IP Addresses
$NASIP = "10.10.246.";
//Eduroam range is the range of IP addresses to match which are assigned to eduroam visitors at institution
$EduroamRange = "123.210.123.";

if (!($db= mysql_connect($hostName,$usernamedb,$password))) { echo "error connecting to DB on $hostName.";  exit();}
if (!(mysql_select_db("radius",$db))) { echo "error connecting to DB-radius."; exit(); }

$lastMonth = date("Y-m",mktime(0,0,0,date("m")-1,date("y")));

//Edit this query to change the data set to get
$query = "SELECT DISTINCT (username), FramedIPAddress, NASIPAddress FROM radacct WHERE  AcctStartTime LIKE '2010-%'";
$result=mysql_query($query,$db);
$numunique=mysql_num_rows($result);

$jrsvisited=array();
$jrshome=array();

while ($row=mysql_fetch_row($result))
{
	$username=$row[0];
	$ip=$row[1];
	$fromip=$row[2];

	//IF LOCAL NAS IP Address
	if (stristr($fromip,$NASIP))
	{
	//IF FramedIP is an Eduroam Vistor Subnet IP address, then its a local eduroam user
	if (stristr($ip,$EduroamRange))
		{
			array_push($jrsvisited,$username);
		}	
	}
	//ELSE LOCAL USER FROM ANOTHER NASIP
	else 
	{
		array_push($jrshome,$username);
	}
}

//remove duplicates
$jrsvisited_all = $jrsvisited;
//strip off usernames and leave just the realms
$jrsvisited_unique = array();
$ainstitute = "";
foreach ($jrsvisited_all as $auser)
	{
		if (stripos($auser,"@")) 
			{
				if ($email=explode('@',$auser))
					{
							$ainstitute = $email[1];
					}
			}
			if (strlen($ainstitute)>0){	array_push($jrsvisited_unique,$ainstitute); }
	}
	
$jrsvisited=array_unique($jrsvisited_unique);
$jrshome=array_unique($jrshome);
//new array to store details of each institution
$jrsvisited_details = array();

//Count of unique number of users at each realm
$jrsvistedcount=count($jrsvisited_all);
$jrshomecount=count($jrshome);

//ForEach institution
foreach ($jrsvisited as $tmp)
{
	//reset variables
				$user="";
				$institute="";
				$country="";
				$error=0;
				$lat="";
				$longx="";
				$description="";
				$institutefull="";
				$visitcount=0;
				$city="";
	
				$institute = $tmp;
				//Exclude any local users that somehow appear here
				if (strcmp("swansea.ac.uk",$institute)==0 || strcmp("swan.ac.uk",$institute)==0) { continue; }
				//Bug with cam.ac.uk in geoip, so rewrite to cambridge
				if (strcmp("cam.ac.uk",$institute)==0) { $institute="cambridge.ac.uk"; }
				
				//get GEOIP dat fot institute
				//Using http://www.php.net/manual/en/book.geoip.php
				if ($geoarray = geoip_record_by_name($institute))
				{
					$lat=$geoarray["latitude"];
					$longx=$geoarray["longitude"];
					$city=htmlentities($geoarray["city"]);
				}
				else 
				{
					$error=1;
					//echo "error with geoip";
				}
				
				//get institude without the .ac.uk etc bit
				$institutefull = $institute;
				if ($institute2=explode('.',$institute))
				{
					$institute = $institute2[0];
					$country = array_pop($institute2);
				}
				else
				{
					$error=1;
					//echo "error with explode institute";
				}
				
				//count up number of visits at each institution
				foreach ($jrsvisited_all as $tmp2)
				{
					if (stripos($tmp2,"@")) 
						{
						if ($email2=explode('@',$tmp2))
							{
								$user2 = $email2[0];
								$institute2 = $email2[1];
								if (strcmp($institutefull,$institute2)==0) { $visitcount++; } 
								//echo "//$institutefull and $institute";
							}
						}
				}

	//IF NO ERROR then write out the javescript function call
	if (!$error) 
	{
		$description="<div id=\"content\"><div id=\"siteNotice\"></div><h2 id=\"firstHeading\" class=\"firstHeading\">$institutefull</h2><div id=\"bodyContent\"><p><b>$institute</b> ($city) has authenticated users on eduroam at Swansea $visitcount times.</p><p>Link to website: <a href=\"http://$institutefull\">$institute</a>.</p></div></div>";
		//echo "Visited user=$tmp  institute=$institute, country=$country, geoip=$lat,$longx<br>";
		array_push($jrsvisited_details[$institute],$description);
		echo "plotRoam(new google.maps.LatLng($lat,$longx),'$description','$institutefull'); \n";
	}
	else 
	{ 
		//echo "Error: Visited user=$tmp<br>"; 
	}
}

?>
//***END
  }

	function plotRoam(loc,desc,title)
	{
		var from = loc;
		//information window
		var infowindow = new google.maps.InfoWindow({content: desc});

		var marker = new google.maps.Marker({position:from, map:map, title:title});
		google.maps.event.addListener(marker, 'click', function() { infowindow.open(map,marker); });
		
	    var link = [from,to];
	    var eduroamlink = new google.maps.Polyline({
	        path: link,
	        strokeColor: "#FF0000",
	        strokeOpacity: 1.0,
	        strokeWeight: 2
	      });
	    eduroamlink.setMap(map);      
	}
	  
</script>
</head>
<body onload="initialize()">
<div style=""><b>Eduroam @ My Institution</b> Eduroam Visitors = ~<?php echo $jrsvistedcount; ?>, Local Roamers = ~<?php echo $jrshomecount; ?></div>
<div id="map_canvas" style="width: 100%; height: 100%"></div>
</div>
</div>
<script>
</script>
</body>

</html>
