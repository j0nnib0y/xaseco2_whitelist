<?php

Aseco::registerEvent('onSync', 'jwl_event_onSync');
Aseco::registerEvent('onPlayerConnect2', 'jwl_event_onPlayerConnect');

// chat commands
Aseco::addChatCommand('whitelist', 'Whitelist commands');

$jwl_config = false;

$jwl_cache = array();
$jwl_cache['whitelist'] = array();

function jwl_event_onSync($aseco) {
	global $jwl_config;
	
	jwl_reloadConfig();
	print_r($jwl_config);
	echo "[jonni.whitelist] Initialized plugin!\n";
	
}

function jwl_event_onPlayerConnect($aseco, $player) {
	
	// whitelist check & kick/ban or not
	
}

function chat_whitelist($aseco, $command) {
	
	// whitelist chat commands
	
}

function jwl_reloadConfig() {
	global $jwl_config;
	
	$jwl_config = jwl_xml2array(simplexml_load_file("jonni.whitelist.xml"));
	
}

function jwl_refreshCache() {
	global $jwl_cache;
	global $jwl_config;
	
	switch($jwl_config['database']) {

		case 'MySQL':
		
			// TODO
		
		break;
		
		case 'XML':
		
			$jwl_cache['whitelist'] = array_values($jwl_config['whitelist']);
		
		
		break;
	
	}
	
}

function jwl_whitelistPlayer($login) {
	
	
	
}

function jwl_removePlayerFromWhitelist($login) {
	
	
	
}

/**
 * function xml2array
 *
 * This function is part of the PHP manual.
 *
 * The PHP manual text and comments are covered by the Creative Commons 
 * Attribution 3.0 License, copyright (c) the PHP Documentation Group
 *
 * @author  k dot antczak at livedata dot pl
 * @date    2011-04-22 06:08 UTC
 * @link    http://www.php.net/manual/en/ref.simplexml.php#103617
 * @license http://www.php.net/license/index.php#doc-lic
 * @license http://creativecommons.org/licenses/by/3.0/
 * @license CC-BY-3.0 <http://spdx.org/licenses/CC-BY-3.0>
 */
function jwl_xml2array ( $xmlObject, $out = array () )
{
    foreach ( (array) $xmlObject as $index => $node )
        $out[$index] = ( is_object ( $node ) ) ? jwl_xml2array ( $node ) : $node;

    return $out;
}

?>