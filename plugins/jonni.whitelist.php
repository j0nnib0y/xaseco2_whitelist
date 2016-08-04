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
	global $jwl_config;
	
	if(!jwl_checkPlayer($player->login)) {
		
		$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($jwl_config['punishment_message']), $player->login);
		
	}
	
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

function jwl_checkPlayer($login) {
	global $jwl_cache;
	global $jwl_config;
	
	if($jwl_config['use_cache'] == 'true') {
		
		// in whitelist?
		if(in_array($login, $jwl_cache['whitelist'])) {
			
			// in ignored players?
			if(in_array($login, $jwl_config['ignored_players'])) {
				
				return false;
				
			} else {
				
				return true;
				
			}
			
		} else {
			
			return false;
			
		}
		
	} else {
		
		if(jwl_query_countWhitelistEntries($login) == 0) {
			
			if(in_array($login, $jwl_config['ignored_players'])) {
				
				return true;
			
			} else {
				
				return false;
				
			}
			
		} else {
			
			return true;
			
		}
	
	}
	
}

function jwl_query_countWhitelistEntries($login = false) {
	
	if($login) {
		
		$sql = 'SELECT * FROM whitelist WHERE login = ' . mysql_escape_string($login) . ';';
		
	} else {
		
		$sql = 'SELECT * FROM whitelist;';
	
	}
	
	$result = mysql_query($sql);
	return mysql_num_rows($result);	
	
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