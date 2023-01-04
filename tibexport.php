<?php
	if (isset($_GET["apikey"])) $apikey = $_GET["apikey"];

	date_default_timezone_set('CET');
	$datetime = new DateTime();
	$datetime->modify('+1 day');
	$datetime->setTime(0, 0, 0);
	$day = 0;
	if (isset($_GET["day"]) && is_numeric($_GET["day"])) $day = $_GET["day"];

	$datetime->modify(($day<0?'':'+').$day.' day');
	$cursor = base64_encode($datetime->format('c'));

	$json = '{"query":"{viewer {homes {timeZone address {postalCode city}consumption(resolution: HOURLY last: 24 before: \"'.$cursor.'\") {nodes {from to cost unitPrice unitPriceVAT consumption consumptionUnit}}}}}"}';

	$ch = curl_init('https://api.tibber.com/v1-beta/gql');
	curl_setopt($ch, CURLOPT_URL, 'https://api.tibber.com/v1-beta/gql');
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Authorization: Bearer '.$apikey)); 
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$response = curl_exec($ch);
	curl_close($ch);
	
	$data = json_decode($response, true, 512, JSON_OBJECT_AS_ARRAY);
	//var_dump($data);

	header("Content-type: text/plain");
	header("Content-Disposition: attachment; filename=".$datetime->format('Y.m.d').".csv");

	if (isset($data["data"]["viewer"]["homes"])) {
		foreach($data["data"]["viewer"]["homes"] as $home) {
			if (isset($home["consumption"]["nodes"])) {
				$nodes = $home["consumption"]["nodes"];
				$out = fopen('php://output', 'w');
				fputcsv($out, array_keys($nodes[0]));
				foreach($nodes as $node) {
					fputcsv($out, $node);
				}
				fclose($out);
			}
		}
	}

?>

