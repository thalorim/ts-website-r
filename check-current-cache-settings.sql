-- Check current cache settings
-- Run this to see your current configuration
-- Replace DBPREFIX with your actual prefix (usually empty or 'tswebsite_')

SELECT 
    identifier, 
    value as 'seconds',
    CASE 
        WHEN CAST(value AS UNSIGNED) < 20 THEN '⚠️ TOO LOW - Causes query spam'
        WHEN CAST(value AS UNSIGNED) < 60 THEN '✓ OK'
        ELSE '✓ GOOD'
    END as 'status'
FROM DBPREFIXconfig 
WHERE identifier LIKE 'cache_%' 
ORDER BY identifier;
