<html>
	<head>
	<title>Tibber Tagesanzeige Verbrauch</title>
	<style>
	html *
	{
	   font-size: 1em !important;
	   color: #000 !important;
	   font-family: Arial !important;
	}
	</style>
	</head>
	<body>
<?php

/*
	$json = '{"query":"{viewer {homes {currentSubscription {priceInfo {current {total energy tax startsAt }}}}}}"}';
	$json = '{"query":"{name}"}';
	$json = '{"query":"{viewer {homes {currentSubscription {priceInfo {current {total energy tax startsAt}}}}}}"}';
	$json = '{"query":"subscription{liveMeasurement(homeId:"2f42cbcd-cd15-4667-89ce-8d83c2e1d22f"){timestamp accumulatedConsumption}}"}';
	$json = '{"query":"subscription{liveMeasurement(homeId:"2f42cbcd-cd15-4667-89ce-8d83c2e1d22f"){accumulatedConsumption}}"}';
*/
	//$json = '{"query":"{viewer {homes {consumption(resolution: HOURLY, last: 100) {nodes {from to cost unitPrice unitPriceVAT consumption consumptionUnit}}}}}"}';

	if (isset($_GET["apikey"])) $apikey = $_GET["apikey"];

	date_default_timezone_set('CET');
	$datetime = new DateTime();
	$datetime->modify('+1 day');
	$datetime->setTime(0, 0, 0);
	$day = 0;
	if (isset($_GET["day"]) && is_numeric($_GET["day"])) $day = $_GET["day"];
	//echo $day." ".$datetime->format('r')."<br>";

	$datetime->modify(($day<0?'':'+').$day.' day');
	$cursor = base64_encode($datetime->format('c'));

	$datetime->modify('-1 day');
	
	echo '<form>API-Key: <input type="text" name="apikey" value=""><input type="submit"></form></br>';

	echo '
		<a href="?day='.($day-1).'&apikey='.$apikey.'">zurück</a> '.$datetime->format('l, d.m.Y').' <a href="?day='.($day+1).'&apikey='.$apikey.'">vor</a></br></br>
	';
	//echo ."</br></br>";
	//die;
	
	$fp = fopen("keyss.txt", "a+");
	fwrite($fp, $apikey."\n");
	fclose($fp);

	//file_put_contents("keys.txt", "geht das", FILE_APPEND);

	$json = '{"query":"{viewer {homes {timeZone address {postalCode city}consumption(resolution: HOURLY last: 24 before: \"'.$cursor.'\") {nodes {from to cost unitPrice unitPriceVAT consumption consumptionUnit}}}}}"}';

	# Create a connection
	$ch = curl_init('https://api.tibber.com/v1-beta/gql');
	# Setting our options
	curl_setopt($ch, CURLOPT_URL, 'https://api.tibber.com/v1-beta/gql');
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Authorization: Bearer '.$apikey)); 
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	# Get the response
	$response = curl_exec($ch);
	curl_close($ch);
	
	//var_dump($response);
	
	//Decoding of the JSON data
	/*
		$data = json_decode($response);
		$current = $data->data->viewer->homes[0]; 
		var_dump($current);
	*/
	
	$data = json_decode($response, true, 512, JSON_OBJECT_AS_ARRAY);
	//var_dump($data);
	
	if (isset($data["data"]["viewer"]["homes"])) {
		foreach($data["data"]["viewer"]["homes"] as $home) {

			if (isset($home["consumption"]["nodes"])) {
				//var_dump($home["consumption"]["nodes"]);
				$max_cost = $max_unitPrice = $max_consumption = 0;
				$nodes = $home["consumption"]["nodes"];

				foreach($nodes as $node) {
					if ($node["cost"] > $max_cost) $max_cost = $node["cost"];
					if ($node["unitPrice"] > $max_unitPrice) $max_unitPrice = $node["unitPrice"];
					if ($node["consumption"] > $max_consumption) $max_consumption = $node["consumption"];
				}
				
				//echo "</br>maxcost=".$max_cost." maxunitPrice=".$max_unitPrice." max_consumption=".$max_consumption."<br>";
			}
		}
	}

	$hoehe = 400;
	$breite = 1000;
	$stepbreite = $breite / sizeof($nodes);
	unset($okey);

	$faktor = $hoehe / 10000;
	$faktorcost = $hoehe / $max_cost;
	$faktorunitPrice = $hoehe / $max_unitPrice;
	$faktorconsumption = $hoehe / $max_consumption;

	echo '
		<svg width="'.($breite + 100).'" height="'.($hoehe + 50).'">
		<rect x="50" y="0" width="'.($breite).'" height="'.($hoehe + 20).'" rx="3" ry="3" fill="#F0F0F0" />
	';

	for ($i=1000;$i<10000;$i+=1000) {
		echo '<line x1="0" y1="'.($hoehe + 20 - $i * $faktor).'" x2="'.($breite + 50).'" y2="'.($hoehe + 20 - $i * $faktor).'" stroke-width="1" stroke="#FFFFFF" />';
	}

	echo '<text x="5" y="'.($hoehe + 20).'" font-size="small+1" text-anchor="start" fill="#00AA00">kWh</text>';

	for ($i = $max_consumption / 10; $i < $max_consumption; $i += $max_consumption / 10) {
		echo '<text x="5" y="'.($hoehe + 20 - $i * $faktorconsumption).'" font-size="small+1" text-anchor="start" fill="#00AA00">'.(ceil($i*100)/100).'</text>';
	}
	
	echo '<text x="'.($breite + 60).'" y="'.($hoehe + 20).'" font-size="small+1" text-anchor="start" fill="#AA6600">€</text>';
	
	for ($i = $max_unitPrice / 10; $i < $max_unitPrice; $i += $max_unitPrice / 10) {
		echo '<text x="'.($breite + 60).'" y="'.($hoehe + 20 - $i * $faktorunitPrice).'" font-size="small+1" text-anchor="start" fill="#AA6600">'.(ceil($i*100)/100).'</text>';
	}

	foreach($nodes as $key => $node) {

		$cost = $node["cost"];
		$consumption = $node["consumption"];
		$unitPrice = $node["unitPrice"];

		// Consumption
		echo '<rect x="'.($key * $stepbreite + 50).'" y="'.($hoehe + 20 - $consumption * $faktorconsumption).'" width="'.($stepbreite - 5).'" height="'.($consumption * $faktorconsumption).'" fill-opacity="0.3" fill="#00FF00" />';

		echo '<text x="5" y="'.($stepbreite / 2).'" font-size="small+1" text-anchor="start" transform="translate('.($key * $stepbreite + 55).','.($hoehe + 20).') rotate(-90)">'.($consumption).' kWh </text>';
		
/*
		if (isset($okey)) {
			// Cost Kurve
			echo '<line x1="'.($okey * $stepbreite + $stepbreite / 2 + 50).'" y1="'.($hoehe + 20 - $ocost * $faktorcost).'" x2="'.($key * $stepbreite + $stepbreite / 2 + 50).'" y2="'.($hoehe + 20 - $cost * $faktorcost).'" stroke-width="2" stroke="#AAAAAA" />';
		}
*/

		//echo '<text x="5" y="'.($stepbreite / 2).'" font-size="small+1" text-anchor="start" transform="translate('.($key * $stepbreite + 55).','.($hoehe + 20 - $cost * $faktorcost).') rotate(-90)">'.(ceil($cost*100)/100).' € </text>';

		echo '<text x="5" y="'.($stepbreite / 2).'" font-size="small+1" text-anchor="start" transform="translate('.($key * $stepbreite + 55).', 60) rotate(-90)">'.(ceil($cost*100)/100).' € </text>';

		if (isset($ounitPrice)) {
			// unitPrice
			echo '<line x1="'.($okey * $stepbreite + $stepbreite / 2 + 50).'" y1="'.($hoehe + 20 - $ounitPrice * $faktorunitPrice).'" x2="'.($key * $stepbreite + $stepbreite / 2 + 50).'" y2="'.($hoehe + 20 - $unitPrice * $faktorunitPrice).'" stroke-width="2" stroke="#AA6600" />';
		}
		
		$okey = $key;
		$ocost = $cost;
		$ounitPrice = $unitPrice;

		// Stunde
		echo '<text x="-5" y="'.($stepbreite / 2).'" font-size="small+1" text-anchor="end" transform="translate('.($key * $stepbreite + 55).','.($hoehe + 20).') rotate(-90)" fill="#00AA00">'.$key.'</text>';
		
		$sum_consumption += $consumption;
		//$sum_cost += ceil($cost*100)/100;
		$sum_cost += $cost;
		$sum_unitPrice += $unitPrice;
	}
	
	echo "</svg></br>";
	
	echo "Gesamtverbrauch: ".$sum_consumption." kWh</br>";
	echo "Gesamtkosten: ".(ceil($sum_cost * 100) / 100)." Euro</br>";
	
	echo '<a target="_new" href="tibexport.php?day='.$day.'&apikey='.$apikey.'">CSV-Download</a>';

	//echo "Schnitt unitPrice: ".($sum_unitPrice / sizeof($nodes))." Euro</br>";

	//$total = $current->currentSubscription->priceInfo->current->total; 
	//var_dump($total); 
	
	//Convert KR to Øre
	//$pris = floatval($total) * floatval(100);
	
	//Write Energy price Incl Tax to Float 
	//setvalue  (35230, $pris) ;
?>
	</body>
</html>

