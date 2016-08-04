<?php

Aseco::registerEvent('onSync', 'jwl_event_onSync');
Aseco::registerEvent('onPlayerConnect2', 'jwl_event_onPlayerConnect');

// chat commands
Aseco::addChatCommand('whitelist', 'Whitelist commands');

$jwl_config = false;

$jwl_cache = array();
$jwl_cache['whitelist'] = array();

function jwl_event_onSync($aseco) {
	
	jwl_reloadConfig();
	echo "[jonni.whitelist] Initialized plugin!";
	
}

function jwl_event_onPlayerConnect($aseco, $player) {
	
	// whitelist check & kick/ban or not
	
}

function chat_whitelist($aseco, $command) {
	
	// whitelist chat commands
	
}

function jwl_reloadConfig() {
	global $jwl_config;
	
	$jwl_config = (array) simplexml_load_file("jonni.whitelist.xml");
	
}

function jwl_whitelistPlayer($login) {
	
	
	
}

function jwl_removePlayerFromWhitelist($login) {
	
	
	
}

?>