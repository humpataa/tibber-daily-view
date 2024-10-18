<html>
	<head>
	<title>Tibber Tagesanzeige Verbrauch</title>
	<!-- https://hopp.la/tibber/tib.php -->
	<style>
	html *
	{
	   font-size: 1em !important;
	   color: #000 !important;
	   font-family: Arial !important;
	}
	#tooltip {
		background: white;
		border: 1px solid black;
		border-radius: 3px;
		padding: 5px;
	}
	</style>
	<script>
		function showTooltip(evt, text) {
		  let tooltip = document.getElementById("tooltip");
		  tooltip.innerHTML = text;
		  tooltip.style.display = "block";
		  tooltip.style.left = evt.pageX + 10 + 'px';
		  tooltip.style.top = evt.pageY + 10 + 'px';
		}
		
		function hideTooltip() {
		  var tooltip = document.getElementById("tooltip");
		  tooltip.style.display = "none";
		}
	</script>
	<script src="tibber-live.js"></script>
	</head>
	<body>
		<div id="tooltip" display="none" style="position: absolute; display: none;"></div>
<?php

	if (isset($_GET["apikey"])) $apikey = $_GET["apikey"];

	date_default_timezone_set('CET');
	$datetime = new DateTime();
	$datetime->setTime(0, 0, 0);
	$day = 0;
	if (isset($_GET["day"]) && is_numeric($_GET["day"])) $day = $_GET["day"];

	$datetime->modify(($day<0?'':'+').$day.' day');
	$cursor = base64_encode($datetime->format('c'));

// request today's prices
	$json = '{"query":"{viewer {homes {currentSubscription {priceInfo {today {total}}}}}}"}';

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

	$data = json_decode($response, true, 512, JSON_OBJECT_AS_ARRAY);
	$priceInfo = $data["data"]["viewer"]["homes"][0]["currentSubscription"]["priceInfo"]["today"];

	// request today's consumption
	$json = '{"query":"{viewer {homes {timeZone id address {postalCode city}consumption(resolution: HOURLY first: 24 after: \"'.$cursor.'\") {nodes {from to cost unitPrice unitPriceVAT consumption consumptionUnit}}}}}"}';
	
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
	
	$data = json_decode($response, true, 512, JSON_OBJECT_AS_ARRAY);
	$consumptionData = $data["data"]["viewer"]["homes"][0]["consumption"];
	$homeId = $data["data"]["viewer"]["homes"][0]["id"];

	echo '<form>API-Key: <input type="text" name="apikey" value=""><input type="submit"> (Demo API Key: 5K4MVS-OjfWhK_4yrjOlFe1F6kJXPVf7eQYggo8ebAE)</form>';
	echo '
		<div id="display" style="background-color: #e9e9e9"></div>
		</br><a href="?day='.($day-1).'&apikey='.$apikey.'">zurück</a> '.$datetime->format('l, d.m.Y').'
	';

	$today = new DateTime();
	$today->setTime(0, 0, 0);
	
	$nextday = new DateTime();
	$nextday->setTime(0, 0, 0);
	$nextday->modify((($day+1)<0?'':'+').($day+1).' day');

	if ($nextday <= $today) {
		echo '<a href="?day='.($day+1).'&apikey='.$apikey.'">vor</a>';
	}

	echo '
		</br></br>
		<script>tibberToken = "'.$apikey.'"; homeId = "'.$data["data"]["viewer"]["homes"][0]["id"].'";</script>
	';

	foreach($priceInfo as $key=>$price) {
		if (isset($consumptionData["nodes"][$key])) $nodes[$key] = $consumptionData["nodes"][$key];
		if (!isset($nodes[$key]["unitPrice"])) $nodes[$key]["unitPrice"] = $price["total"];
	}

	$max_cost = $max_unitPrice = $max_consumption = 0;

	foreach($nodes as $key=>$node) {
		//echo $key." ".$priceInfo[$key]["total"];
		if ($node["cost"] > $max_cost) $max_cost = $node["cost"];
		if ($node["unitPrice"] > $max_unitPrice) $max_unitPrice = $node["unitPrice"];
		if ($node["consumption"] > $max_consumption) $max_consumption = $node["consumption"];
	}

	if (is_array($nodes) && sizeof($nodes) > 0) {

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
				// unsichtbare Balken für die Tooltips
				echo '<rect x="'.($key * $stepbreite + 50).'" y="'.($hoehe + 10 - $unitPrice * $faktorunitPrice).'" width="'.($stepbreite).'" height="20" fill-opacity="0.0" fill="#FFFFFF" onmousemove="showTooltip(evt,\''.(ceil($unitPrice*10000)/100).' ct\');" onmouseout="hideTooltip();" />';
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
		echo "Preis pro kWh im Schnitt für diesen Tag: ".(ceil($sum_cost / ($sum_consumption>0?$sum_consumption:1) * 10000) / 100)." ct</br>";
		
		echo '<a target="_new" href="tibexport.php?day='.$day.'&apikey='.$apikey.'">CSV-Download</a>';

	} else {
		//var_dump($data);
		if (isset($data["errors"][0]["message"])) {
			echo $data["errors"][0]["message"];
		}
		?>
		</br>
		Fehler bei der Abfrage der Daten. Bitte später neu probieren oder Kontakt mit mir aufnehmen.</br>
		<?php
	}
?>
	</br>
	<h3>Wenn auch Du Strom zum echten stündlichen Preis beziehen möchtest (oder zum monatlichen Durchschnittspreis) dann wechsle doch zu <a href="https://tibber.com/de/invite/0myr44no" target="_new">Tibber</a>!</br>Als Dankeschön bekommen wir beide 50 Euro Guthaben wenn Du den Code <a target="_new" href="https://tibber.com/de/invite/0myr44no">0myr44no</a> bei der Anmeldung angibst.</h3>
		Der Quellcode für diese Seite liegt übrigens bei <a target="_new" href="https://github.com/humpataa/tibber-daily-view">Github</a> (erste Version ohne Livedata)</br>
		Bei Fragen, Wünschen, Beschwerden: <a href="mailto:admin@hopp.la">schreibt mir!</a></br>
	</body>
</html>

