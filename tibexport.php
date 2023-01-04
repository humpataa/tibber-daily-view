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

	$json = '{"query":"{viewer {homes {timeZone address {postalCode city}consumption(resolution: HOURLY last: 24 before: \"'.$cursor.'\") {nodes {from to cost unitPrice unitPriceVAT consumption consumptionUnit}}}}}"}';

	$ch = curl_init('https://api.tibber.com/v1-beta/gql');
	curl_setopt($ch, CURLOPT_URL, 'https://api.tibber.com/v1-beta/gql');
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Authorization: Bearer '.$apikey)); 
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
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

	header("Content-type: text/plain");
	header("Content-Disposition: attachment; filename=".$datetime->format('Y.m.d').".csv");

	if (isset($data["data"]["viewer"]["homes"])) {
		foreach($data["data"]["viewer"]["homes"] as $home) {
			var_dump($home);
			if (isset($home["consumption"]["nodes"])) {
				//var_dump($home["consumption"]["nodes"]);

				$nodes = $home["consumption"]["nodes"];

				$out = fopen('php://output', 'w');
				fputcsv($out, array_keys($nodes[0]));

				foreach($nodes as $node) {
					fputcsv($out, $node);
				}

				//fputcsv($out, array("from", "to", "cost"));
				fclose($out);

/*
				$max_cost = $max_unitPrice = $max_consumption = 0;
				$nodes = $home["consumption"]["nodes"];

				foreach($nodes as $node) {
					if ($node["cost"] > $max_cost) $max_cost = $node["cost"];
					if ($node["unitPrice"] > $max_unitPrice) $max_unitPrice = $node["unitPrice"];
					if ($node["consumption"] > $max_consumption) $max_consumption = $node["consumption"];
				}
*/
				//echo "</br>maxcost=".$max_cost." maxunitPrice=".$max_unitPrice." max_consumption=".$max_consumption."<br>";
			}
		}
	}

?>

