-- Permisos para la DB landlord y para crear DBs de tenants en runtime.
-- Las DBs de tenants usan el prefijo 'claimguard_<slug>'.
GRANT ALL PRIVILEGES ON `claim_guard_%`.* TO 'claimguard'@'%';
GRANT ALL PRIVILEGES ON `claimguard_%`.*  TO 'claimguard'@'%';
FLUSH PRIVILEGES;
