-- Otorgar permisos para crear bases de datos de tenants (claim_guard_<slug>)
-- El usuario 'claimguard' necesita poder crear DBs en runtime al onboarding de cada tenant
GRANT ALL PRIVILEGES ON `claim_guard_%`.* TO 'claimguard'@'%';
FLUSH PRIVILEGES;
