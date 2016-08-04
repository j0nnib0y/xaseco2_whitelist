<?php

Aseco::registerEvent('onSync', 'jwl_event_onSync');
Aseco::registerEvent('onPlayerConnect2', 'jwl_event_onPlayerConnect');

// chat commands
Aseco::addChatCommand('whitelist', 'Whitelist commands');

$jwl_config = false;
$jwl_aseco = false;

$jwl_cache = array();
$jwl_cache['whitelist'] = array();

function jwl_console($message) {
	global $jwl_aseco;
	
	if($jwl_aseco) {
		
		$jwl_aseco->console('[jonni.whitelist.php] ' . $message);

	}
	
}

function jwl_event_onSync($aseco) {
	global $jwl_config;
	global $jwl_aseco;
	
	$jwl_aseco = $aseco;
	
	jwl_console('Initializing...');
	
	jwl_reloadConfig();
	
	if($jwl_config['use_cache'] == 'true') {
		
		jwl_refreshCache();
		
	}
	
	if($jwl_config['database'] == 'MySQL') {
		
		jwl_verifyDatabaseStructure();
		
	}
	
	jwl_console('Initialized plugin!');
	
}

function jwl_event_onPlayerConnect($aseco, $player) {
	global $jwl_config;
	
	jwl_console("Player '" . $player->login . "' connected, checking for whitelist entry...");
	
	if(!jwl_checkPlayer($player->login)) {
		
		jwl_console("Player '" . $player->login . "' failed the whitelist check. Punishing...");
		
		$method = $jwl_config['punishment_method'];
		$method_past = $jwl_config['punishment_method'] . 'ed';
		
		// small language fixes
		switch($method) {
			
			case 'forcespec':
				$method = 'move to spectator';
				$method_past = 'moved to spectator';
			break;
			
			case 'ban':
				$method_past = 'banned';
			break;
			
			default:
				// nothing
			break;
		}
		
		$message = str_replace(array('{name}', '{login}', '{punish}', '{punished}'), array($player->nickname, $player->login, $method, $method_past), $jwl_config['punishment_message']);
		$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
		
		switch($jwl_config['punishment_method']) {
			
			case 'forcespec':
				jwl_forceSpectator($player);
			break;
			
			case 'kick':
				jwl_kickPlayer($player);
			break;
			
			case 'blacklist':
				jwl_blacklistPlayer($player);
			break;
			
			case 'ban':
				jwl_banPlayer($player);
			break;
			
			default:
				jwl_kickPlayer($player->login);
			break;
			
		}
		
		if($jwl_config['chat_message'] != 'false') {

			$message = str_replace(array('{name}', '{login}', '{punish}', '{punished}'), array($player->nickname, $player->login, $method, $method_past), $jwl_config['chat_message']);
			$aseco->client->query('ChatSendServerMessage', $aseco->formatColors($message));

		}
		
		
		#jwl_console("Punishment finished. Exiting whitelist check.");
		
	} else {
		
		jwl_console("Player '" . $player->login . "' succeeded the whitelist check. Aborting!");
		
	}
	
}

function chat_whitelist($aseco, $command) {
	
	// whitelist chat commands
	
}

function jwl_verifyDatabaseStructure() {
	
	$sql = 
	'CREATE TABLE IF NOT EXISTS `whitelist`  (
		`login` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
		UNIQUE KEY `login` (`login`)
	) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;';
	
	mysql_query($sql) or die(mysql_error());
	
	jwl_console('>> Verified database structure!');
	
}

function jwl_reloadConfig() {
	global $jwl_config;
	
	$jwl_config = xml2array(simplexml_load_file("jonni.whitelist.xml"));
	
	jwl_console('>> Reloaded config!');
	
}

function jwl_refreshCache() {
	global $jwl_cache;
	global $jwl_config;
	
	switch($jwl_config['database']) {

		case 'MySQL':
		
			$jwl_cache['whitelist'] = array_values(jwl_query_getWhitelistEntries());
			print_r(jwl_query_getWhitelistEntries());
		break;
		
		case 'XML':
		
			$jwl_cache['whitelist'] = array_values($jwl_config['whitelist']);
		
		
		break;
	
	}
	
	jwl_console('>> Refreshed cache!');
	#print_r($jwl_cache['whitelist']);
	
}

function jwl_forceSpectator($target) {
	global $jwl_aseco;
	
	if (!$jwl_aseco->isSpectator($target)) {
		
		// force player into free spectator
		$rtn = $jwl_aseco->client->query('ForceSpectator', $target->login, 1);
		
		if (!$rtn) {
			
			trigger_error('[' . $aseco->client->getErrorCode() . '] ForceSpectator - ' . $aseco->client->getErrorMessage(), E_USER_WARNING);
		
		} else {
			
			// allow spectator to switch back to player
			$rtn = $jwl_aseco->client->query('ForceSpectator', $target->login, 0);
			
			// force free camera mode on spectator
			$jwl_aseco->client->addCall('ForceSpectatorTarget', array($target->login, '', 2));
			
			// free up player slot
			$jwl_aseco->client->addCall('SpectatorReleasePlayerSlot', array($target->login));
		
			jwl_console(">> Forced player '" . $target->login . "' to spectator mode!");
			
		}
	
	} else {
		
		jwl_console(">> Player '" . $target->login . "' is already a spectator!");
	
	}
	
}

function jwl_kickPlayer($player) {
	global $jwl_aseco;

	$jwl_aseco->client->query('Kick', $player->login);
	jwl_console(">> Kicked player '" . $player->login . "'!");
	
}

function jwl_blacklistPlayer($player) {
	global $jwl_aseco;
	
	// blacklist the player and then kick him
	$jwl_aseco->client->query('BlackList', $player->login);
	$jwl_aseco->client->query('Kick', $player->login);

	// update blacklist file
	$filename = $jwl_aseco->settings['blacklist_file'];
	$rtn = $jwl_aseco->client->query('SaveBlackList', $filename);
	if (!$rtn) {
		trigger_error('[' . $jwl_aseco->client->getErrorCode() . '] SaveBlackList (kick) - ' . $jwl_aseco->client->getErrorMessage(), E_USER_WARNING);
	}
	
	jwl_console(">> Blacklisted & kicked player '" . $player->login . "'!");

}

function jwl_banPlayer($player) {	
	global $jwl_aseco;
	
	// update banned IPs file
	$jwl_aseco->bannedips[] = $player->ip;
	$jwl_aseco->writeIPs();

	// ban the player and also kick him
	$jwl_aseco->client->query('Ban', $player->login);
	
	jwl_console(">> Banned & kicked player '" . $player->login . "'!");
	
}

function jwl_checkPlayer($login) {
	global $jwl_cache;
	global $jwl_config;
	
	if($jwl_config['use_cache'] == 'true') {
		
		// in whitelist?
		if(in_array($login, $jwl_cache['whitelist'])) {
			
			jwl_console('>> Player is in whitelist! No need to worry...');
			return true;
			
		} else {
			
			// in ignored players?
			if(in_array($login, $jwl_config['ignored_players'])) {
				
				jwl_console(">> Player isn't in whitelist, but is an ignored player! Pfeeewww...");
				return true;
				
			} else {
				
				jwl_console(">> OMG, player isn't in whitelist and ignored players list! Punish him!");
				return false;
				
			}
			
		}
		
	} else {
		
		switch($jwl_config['database']) {
			
			case 'MySQL':
			
				if(jwl_query_countWhitelistEntries($login) == 0) {
					
					if(in_array($login, $jwl_config['ignored_players'])) {
						
						jwl_console(">> Player isn't in whitelist, but is an ignored player! Pfeeewww...");
						return true;
					
					} else {
						
						jwl_console(">> OMG, player isn't in whitelist and ignored players list! Punish him!");
						return false;
						
					}
					
				} else {
					
					jwl_console('>> Player is in whitelist! No need to worry...');
					return true;
					
				}
				
			break;
			
			case 'XML':
			
				jwl_reloadConfig();
				
				// in whitelist?
				if(in_array($login, $jwl_config['whitelist'])) {
					
					jwl_console('>> Player is in whitelist! No need to worry...');
					return true;
					
				} else {
					
					// in ignored players?
					if(in_array($login, $jwl_config['ignored_players'])) {
						
						jwl_console(">> Player isn't in whitelist, but is an ignored player! Pfeeewww...");
						return true;
						
					} else {
						
						jwl_console(">> OMG, player isn't in whitelist and ignored players list! Punish him!");
						return false;
						
					}
					
				}
			
			break;
			
		}
	
	}
	
}

function jwl_query_countWhitelistEntries($login = false) {
	
	if($login) {
		
		$sql = "SELECT * FROM whitelist WHERE login = '" . mysql_real_escape_string($login) . "';";
		
	} else {
		
		$sql = 'SELECT * FROM whitelist;';
	
	}

	$result = mysql_query($sql) or die(mysql_error());
	return mysql_num_rows($result);	
	
}

function jwl_query_getWhitelistEntries($login = false) {
	
	if($login) {
		
		$sql = "SELECT * FROM whitelist WHERE login = '" . mysql_real_escape_string($login) . "';";
		
	} else {
		
		$sql = 'SELECT * FROM whitelist;';
	
	}
	
	$result = mysql_query($sql);
	
	$array = array();
	while($row = mysql_fetch_row($result)){
		$array[] = $row[0];
	}

	return $array;
	
}


function jwl_whitelistPlayer($login) {
	
	
	
}

function jwl_removePlayerFromWhitelist($login) {
	
	
	
}

// -------------------- USEFUL FUNCTIONS BELOW --------------------

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
if(!function_exists('xml2array')) {
	
	function xml2array ( $xmlObject, $out = array () )
	{
		foreach ( (array) $xmlObject as $index => $node )
			$out[$index] = ( is_object ( $node ) ) ? xml2array ( $node ) : $node;

		return $out;
	}
	
}

?>