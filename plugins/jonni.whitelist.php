<?php

/*
 * Plugin: Whitelist
 * ~~~~~~~~~~~~~~~~~~~
 * Whitelist is a xAseco 2 plugin which manages a whitelist 
 * and kicks/bans (or what ever you like to do) every connecting player not being 
 * whitelisted on the server.
 * It can be useful for tournaments where people may spread the password of the 
 * server and players not allowed to play a match can join the server 
 * and falsify the results.
 * ----------------------------------------------------------------------------------
 * Author:		Jonniboy (http://jonni.it/)
 * Version:		0.1
 * Date:		2016-08-05
 * Copyright:	2016 by Jonniboy (http://jonni.it)
 * System:		XAseco2/1.00+
 * Game:		ManiaPlanet Trackmania2 (TM2)
 * ----------------------------------------------------------------------------------
 *
 * LICENSE: This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as published
 * by the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
 *
 * ----------------------------------------------------------------------------------
 *
 * Dependencies:
 *  - none
 */

Aseco::registerEvent('onStartup', 'jwl_event_onStartup');
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

function jwl_event_onStartup($aseco) {
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

function jwl_onSync() {
	
	// Register this to the global version pool (for up-to-date checks)
	$aseco->plugin_versions[] = array(
		'plugin'	=> 'jonni.whitelist.php',
		'author'	=> 'Jonniboy',
		'version'	=> '0.1'
	);
	
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
		
		if($jwl_config['messages']['announcement'] != 'false') {

			$message = str_replace(array('{name}', '{login}', '{punish}', '{punished}'), array($player->nickname, $player->login, $method, $method_past), $jwl_config['messages']['announcement']);
			$aseco->client->query('ChatSendServerMessage', $aseco->formatColors($message));

		}
		
		
		#jwl_console("Punishment finished. Exiting whitelist check.");
		
	} else {
		
		jwl_console("Player '" . $player->login . "' succeeded the whitelist check. Aborting!");
		
	}
	
}

function chat_whitelist($aseco, $command) {
	global $jwl_config;
	
	if($command['params']) {
		
		$params = explode(' ', $command['params']);
		
		switch(strtolower($params[0])) {
			
			case 'add':
			
				if(jonni_checkPermission($aseco, $jwl_config['permissions'], $command['author'], 'add')) {
					
					if(isset($params[1])) {
						
						if(jwl_whitelistPlayer($params[1])) {
						
							$message = str_replace(array('{login}'), array($params[1]), $jwl_config['messages']['success_add']);
							$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $command['author']->login);
						
						} else {
							
							$message = str_replace(array('{login}'), array($params[1]), $jwl_config['messages']['fail_add']);
							$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $command['author']->login);
							
						}
					
					} else {
						
						$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($jwl_config['messages']['missing_parameters'] . 'player login'), $command['author']->login);
						
					}
					
				} else {
					
					$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($jwl_config['messages']['no_permission']), $command['author']->login);
					return;
					
				}
			
			break;
			
			case 'remove':
			
				if(jonni_checkPermission($aseco, $jwl_config['permissions'], $command['author'], 'remove')) {
					
					if(isset($params[1])) {
						
						if(jwl_removePlayerFromWhitelist($params[1])) {
						
							$message = str_replace(array('{login}'), array($params[1]), $jwl_config['messages']['success_remove']);
							$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $command['author']->login);
						
						} else {
							
							$message = str_replace(array('{login}'), array($params[1]), $jwl_config['messages']['fail_remove']);
							$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $command['author']->login);
							
						}
					
					} else {
						
						$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($jwl_config['messages']['missing_parameters'] . 'player login'), $command['author']->login);
						
					}
					
				} else {
					
					$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($jwl_config['messages']['no_permission']), $command['author']->login);
					return;
					
				}
			
			break;
			
			case 'list':
			
				if(jonni_checkPermission($aseco, $jwl_config['permissions'], $command['author'], 'list')) {
					
					$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($jwl_config['messages']['list_start']), $command['author']->login);
					
					if($jwl_config['use_cache'] == 'true') {
						
						foreach($jwl_cache['whitelist'] as $login) {
							
							$message = str_replace(array('{login}'), array($login), $jwl_config['messages']['list_entry']);
							$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $command['author']->login);
							
						}							
						
					} else {
						
						switch($jwl_config['database']) {

							case 'MySQL':
							
								$whitelist = jwl_query_getWhitelistEntries();
								
								foreach($whitelist as $login) {
							
									$message = str_replace(array('{login}'), array($login), $jwl_config['messages']['list_entry']);
									$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $command['author']->login);
							
								}	
							
							break;
							
							case 'XML':
							
								jwl_reloadConfig();
								
								foreach($jwl_config['whitelist'] as $login) {
							
									$message = str_replace(array('{login}'), array($login), $jwl_config['messages']['list_entry']);
									$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $command['author']->login);
							
								}				
									
							break;

						}
						
					}
					
					$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($jwl_config['messages']['list_end']), $command['author']->login);
				
				} else {
					
					$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($jwl_config['messages']['no_permission']), $command['author']->login);	
					return;
					
				}
			
			break;
			
			case 'reload_config':
			
				if(jonni_checkPermission($aseco, $jwl_config['permissions'], $command['author'], 'reload_config')) {
					
					jwl_reloadConfig();
					$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($jwl_config['messages']['success_reload']), $command['author']->login);
				
				} else {
					
					$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($jwl_config['messages']['no_permission']), $command['author']->login);	
					return;
					
				}
			
			break;
			
			case 'refresh_cache':
			
				if(jonni_checkPermission($aseco, $jwl_config['permissions'], $command['author'], 'refresh_cache')) {
					
					jwl_refreshCache();
					$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($jwl_config['messages']['success_refresh']), $command['author']->login);
				
				} else {
					
					$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($jwl_config['messages']['no_permission']), $command['author']->login);	
					return;
					
				}
			
			break;
			
		}
	
	}
	
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
			jwl_query_getWhitelistEntries();
			
		break;
		
		case 'XML':
			
			if(isset($jwl_config['whitelist']['player'])) {

				if(is_array($jwl_config['whitelist']['player'])) {
					
					$jwl_cache['whitelist'] = $jwl_config['whitelist']['player'];
					
				} else {
					
					$jwl_cache['whitelist'] = array($jwl_config['whitelist']['player']);
					
				}
				
			}
		
		break;
	
	}
	
	jwl_console('>> Refreshed cache!');
	
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
		// I've very no clue why this thing is giving a warning message without @ and without any player defined in the XML file, I mean.. I initialize the array just as it have to be and I'm using strict mode for preventing array() == false...
		if(@in_array($login, $jwl_cache['whitelist'], true)) {
			
			jwl_console('>> Player is in whitelist! No need to worry...');
			return true;
			
		} else {
			
			// in ignored players?
			if(in_array($login, $jwl_config['ignored_players'], true)) {
				
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
					
					if(in_array($login, $jwl_config['ignored_players'], true)) {
						
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
				if(in_array($login, $jwl_config['whitelist'], true)) {
					
					jwl_console('>> Player is in whitelist! No need to worry...');
					return true;
					
				} else {
					
					// in ignored players?
					if(in_array($login, $jwl_config['ignored_players'], true)) {
						
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

function jwl_query_addWhitelistEntry($login) {
	
	$sql = "INSERT INTO whitelist (login) VALUES ('" . mysql_real_escape_string($login) . "')";
	
	// if it won't be added because of duplicate, it will return false, and if it succeeded, it will return true!
	return mysql_query($sql);

}

function jwl_query_removeWhitelistEntry($login) {
	
	$sql = "DELETE FROM whitelist WHERE login = '" . $login . "';";
	
	return mysql_query($sql);

}

function jwl_whitelistPlayer($login) {
	global $jwl_config;
	global $jwl_cache;
	
	switch($jwl_config['database']) {
		
		case 'MySQL':
		
			if(!jwl_query_addWhitelistEntry($login)) {
				
				return false;
			
			}
		
		break;
		
		case 'XML':
			
			$file = simplexml_load_file('jonni.whitelist.xml');
			
			if(!in_array($login, (array) $file->whitelist->player)) {
				
				$file->whitelist->addChild('player', $login);
			
			} else {
				
				return false;
			
			}
			
			$dom = dom_import_simplexml($file)->ownerDocument;
			$dom->formatOutput = true;
			
			if(!file_put_contents('jonni.whitelist.xml', $dom->saveXML($dom, LIBXML_NOEMPTYTAG))) {
				
				return false;
			
			}
		
		break;
		
	}
	
	if($jwl_config['use_cache'] == 'true') {
		
		$jwl_cache['whitelist'] = $login;
		
	}
	
	return true;
	
}

function jwl_removePlayerFromWhitelist($login) {
	global $jwl_config;
	global $jwl_cache;
	
	switch($jwl_config['database']) {
		
		case 'MySQL':
		
			if(!jwl_query_removeWhitelistEntry($login)) {
				
				return false;
			
			}
		
		break;
		
		case 'XML':
			
			$file = simplexml_load_file('jonni.whitelist.xml');
			$dom = dom_import_simplexml($file)->ownerDocument;
			$dom->formatOutput = true;
			
			if(in_array($login, (array) $file->whitelist->player)) {
				
				$x = false;
				
				foreach($dom->getElementsByTagName('player') as $player) {
					
					if($player->nodeValue == $login) {
						
						$player->parentNode->removeChild($player);
						$x = true;
						
					}
				
				}
				
				if(!$x) {
					
					return false;
					
				}
			
			} else {
				
				return false;
			
			}
			
			if(!file_put_contents('jonni.whitelist.xml', $dom->saveXML($dom, LIBXML_NOEMPTYTAG))) {
				
				return false;
			
			}
		
		break;
		
	}
	
	if($jwl_config['use_cache'] == 'true') {
		
		if(($key = array_search($login, $jwl_cache['whitelist'])) !== false) {
			
			unset($jwl_cache['whitelist'][$key]);

		}
		
	}
	
	return true;
	
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

if(!function_exists('jonni_checkPermission')) {

	function jonni_checkPermission($aseco, $permissions, $player, $action) {
		
		if(isset($permissions[$action])) {
			
			switch($permissions[$action]) {
				
				case 'MasterAdmin':
					if($aseco->isMasterAdmin($player)) {
						return true;
					}
				break;
				
				case 'Admin':
					if($aseco->isAdmin($player) or $aseco->isMasterAdmin($player)) {
						return true;
					}
				break;
				
				case 'Operator':
					if($aseco->isAnyAdmin($player)) {
						return true;
					}
				break;
				
				default:
					return true;
				break;
				
			}
			
			// if someone hasn't got the right he needs, he isn't allowed to do
			return false;
		
		} else {
			
			return false;
			
		}
		
	}
	
}

?>