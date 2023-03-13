/**
	live.js
	Last change: 28.2.2023
	
	a super simple Javascript to check for Tibber live data

	show Tibber PULSE realtime live data
	Github: https://github.com/humpataa/tibber-liveMeasurement
	Big thank you to: https://github.com/gulars/Tibber-Realtime-Kw-Meter

	Required: div named "display" to write to
	check console for messages

	Questions? Ask me: admin@hopp.la
**/

const host = 'wss://websocket-api.tibber.com/v1-beta/gql/subscriptions';

var homeId = '96a14971-525a-4420-aae9-e5aedaa129ff'					// demo id
var tibberToken = '5K4MVS-OjfWhK_4yrjOlFe1F6kJXPVf7eQYggo8ebAE'		// demo token

function createId() {
    function _p8(s) {
        var p = (Math.random().toString(16) + "000000000").substr(2, 8);
        return s ? "-" + p.substr(0, 4) + "-" + p.substr(4, 4) : p;
    }
    return _p8() + _p8(true) + _p8(true) + _p8();
}

const id = createId()
console.log(id)

const options = {
	headers: {
	"User-Agent": window.navigator.userAgent
	}
};

let ws = new WebSocket(host, 'graphql-transport-ws', options);

ws.onopen = function(){
	json= '{"type":"connection_init","payload":{"token":"'+tibberToken+'"}}';
	ws.send(json)
}

ws.onmessage = function(msg) {
	//console.log(msg)
	reply = JSON.parse(msg.data)
	console.log(msg.data)

	if (reply["type"] == "connection_ack") {
		console.log(reply["type"])

		query = '{"id": "'+id+'","type": "subscribe","payload": {"query": "subscription{liveMeasurement(homeId:\\\"'+homeId+'\\\"){timestamp power powerProduction lastMeterConsumption lastMeterProduction}}"}}';
		ws.send(query);
	}

	if (reply["type"] == "next") {
		console.log(reply["type"])
		lastMeterConsumption = reply["payload"]["data"]["liveMeasurement"]["lastMeterConsumption"]
		lastMeterProduction = reply["payload"]["data"]["liveMeasurement"]["lastMeterProduction"]
		powerProduction = reply["payload"]["data"]["liveMeasurement"]["powerProduction"]
		power = reply["payload"]["data"]["liveMeasurement"]["power"]

		display.innerHTML = 'live Verbrauch: ' + power + ' W / Einspeisung: ' + powerProduction + ' W </br>Zähler 1.8.0: ' + lastMeterConsumption + '</br>Zähler 2.8.0: ' + lastMeterProduction + '</br><input type="button" onClick="stopSocket()" value="Stop it">'
	}
}

ws.addEventListener('error', (event) => {
	console.log('WebSocket error: ', event);
});

function stopSocket() {
    try {
        query = '{"id": "'+id+'","type": "stop"}'
        ws.send(query)
		console.log("Client stopped")
		display.innerHTML = 'Client stopped'
    } catch (e) {
        console.log("Stop error")
    }
}
