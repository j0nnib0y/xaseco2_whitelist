# Whitelist plugin (xAseco 2)
Whitelist is a xAseco 2 plugin which manages a whitelist and kicks/bans (or what ever you like to do) every connecting player not being whitelisted on the server.

It can be useful for tournaments where people may spread the password of the server and players not allowed to play a match can join the server and falsify the results.

##Features
- manages a whitelist of players which should play on the server
- non-whitelisted players get a punishment defined by you
	- force spectator
	- kick
	- IP ban
	- blacklist (ban of Trackmania login)
- a XML config file full of options
	- modify the style of the messages displayed in the chat
- 2 data sources (where the whitelisted players get saved)
	- MySQL database (xAseco db)
	- nodes within the XML config file
- activatable system for reducing amount of queries to the database on high frequented servers
- automatic MySQL preparation

##Planned
- GUI list with buttons and search field
- little documentation here on Github about the config file options

Do you've ideas? Give them to me and I will do my best if I've time! :-)

##Installation
The installation is the same with every plugin for xAseco 2.

1. Just download the latest release and extract the ZIP.
2. Copy the jonni.whitelist.xml and the plugins folder to your xAseco 2 root directory.
3. Add `<plugin>jonni.whitelist.php</plugin>` to your plugins.xml.
4. Restart xAseco 2 (you may edit the config file before, but the standard settings are okay).

Done!

##Thanks to
- the **xAseco developers** for a nice statistic system on which most of the servers are based on
- the **undef.de** / **undef.name** for awesome tutorials and nice cheat sheets for xAseco developers
- the **PHP manual** for awesome examples and the xml2array function used in this plugin