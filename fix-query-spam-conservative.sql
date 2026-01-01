-- Conservative Fix: Reduces queries by ~75%
-- Good for most servers
-- Run this against your database (replace DBPREFIX with your actual prefix, usually empty or 'tswebsite_')

-- Increase cache times to reduce TeamSpeak server queries
UPDATE DBPREFIXconfig SET value = '30' WHERE identifier = 'cache_serverinfo';
UPDATE DBPREFIXconfig SET value = '30' WHERE identifier = 'cache_clientlist';

-- Verify changes
SELECT identifier, value FROM DBPREFIXconfig WHERE identifier LIKE 'cache_%' ORDER BY identifier;
