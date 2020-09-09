

-- Update sys config categories and descriptions
drop procedure IF EXISTS update_sys_config();

-- Exchange rates a,b,c,d with r's if not income rated.
drop procedure IF EXISTS fix_rates();

