-- Rôles pour LibraryHub (à exécuter une fois sur une base neuve)
-- Base : libreryhub (USE libreryhub; ou exécuter dans la bonne base)

INSERT IGNORE INTO `role` (name, description) VALUES
('ROLE_ADMIN', 'Administrateur'),
('ROLE_MEMBER', 'Membre standard'),
('ROLE_LIBRARIAN', 'Bibliothécaire');
