-- Aggressive Fix: Reduces queries by ~90%
-- Use if you have high traffic or severe query spam
-- Run this against your database (replace DBPREFIX with your actual prefix, usually empty or 'tswebsite_')

-- Significantly increase cache times
UPDATE DBPREFIXconfig SET value = '60' WHERE identifier = 'cache_serverinfo';
UPDATE DBPREFIXconfig SET value = '60' WHERE identifier = 'cache_clientlist';
UPDATE DBPREFIXconfig SET value = '120' WHERE identifier = 'cache_channelist';

-- Verify changes
SELECT identifier, value FROM DBPREFIXconfig WHERE identifier LIKE 'cache_%' ORDER BY identifier;
