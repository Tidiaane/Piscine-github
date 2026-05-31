-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3306
-- Généré le : dim. 31 mai 2026 à 14:13
-- Version du serveur : 8.4.7
-- Version de PHP : 8.3.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `voyagevista`
--

-- --------------------------------------------------------

--
-- Structure de la table `activite`
--

DROP TABLE IF EXISTS `activite`;
CREATE TABLE IF NOT EXISTS `activite` (
  `id_activite` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `destination` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `categorie` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `niveau` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `moment` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `duree` decimal(5,2) NOT NULL,
  `prix` decimal(10,2) NOT NULL,
  `note` decimal(3,1) DEFAULT '0.0',
  `places_disponibles` int DEFAULT '0',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `image` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `options` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `tags` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `date_activite` date DEFAULT NULL,
  `recommande` int DEFAULT '1',
  PRIMARY KEY (`id_activite`)
) ENGINE=MyISAM AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `activite`
--

INSERT INTO `activite` (`id_activite`, `nom`, `destination`, `categorie`, `niveau`, `moment`, `duree`, `prix`, `note`, `places_disponibles`, `description`, `image`, `options`, `tags`, `date_activite`, `recommande`) VALUES
(1, 'Randonnée dans les rizières', 'Bali', 'nature', 'facile', 'matin', 3.00, 45.00, 4.7, 8, 'Balade guidée dans les rizières avec découverte des paysages et traditions locales.', 'https://images.unsplash.com/photo-1555400038-63f5ba517a47?auto=format&fit=crop&w=900&q=80', '[\"guide\", \"famille\", \"exterieur\", \"annulation\"]', '[\"Nature\", \"Guide\", \"Famille\"]', '2026-07-11', 1),
(2, 'Visite de l\'Acropole', 'Athènes', 'culture', 'facile', 'matin', 2.00, 35.00, 4.8, 14, 'Visite culturelle guidée autour de l\'Acropole et des monuments historiques d\'Athènes.', 'https://images.unsplash.com/photo-1555993539-1732b0258235?auto=format&fit=crop&w=900&q=80', '[\"guide\", \"famille\", \"annulation\"]', '[\"Culture\", \"Histoire\", \"Guide\"]', '2026-08-06', 2),
(3, 'Parapente au-dessus des Alpes', 'Interlaken', 'sport', 'sportif', 'apres-midi', 2.00, 160.00, 4.9, 4, 'Expérience sportive avec vue panoramique sur les montagnes suisses.', 'https://images.unsplash.com/photo-1500534314209-a25ddb2bd429?auto=format&fit=crop&w=900&q=80', '[\"exterieur\", \"transport\"]', '[\"Sport\", \"Aventure\", \"Extérieur\"]', '2026-06-17', 3),
(4, 'Cours de cuisine locale', 'Marrakech', 'gastronomie', 'facile', 'soir', 4.00, 75.00, 4.6, 8, 'Atelier culinaire avec préparation de plats locaux et dégustation sur place.', 'https://images.unsplash.com/photo-1556911220-bff31c812dba?auto=format&fit=crop&w=900&q=80', '[\"repas\", \"famille\", \"annulation\"]', '[\"Gastronomie\", \"Repas inclus\", \"Famille\"]', '2026-09-14', 4),
(5, 'Journée bateau et snorkeling', 'Maldives', 'detente', 'moyen', 'journee', 8.00, 220.00, 4.9, 5, 'Sortie en mer avec snorkeling, détente et découverte des lagons.', 'https://images.unsplash.com/photo-1544551763-46a013bb70d5?auto=format&fit=crop&w=900&q=80', '[\"repas\", \"transport\", \"exterieur\"]', '[\"Détente\", \"Snorkeling\", \"Repas inclus\"]', '2026-12-07', 5),
(6, 'Balade nocturne à Tokyo', 'Tokyo', 'culture', 'facile', 'soir', 3.00, 55.00, 4.5, 12, 'Découverte des quartiers animés, lumières urbaines et lieux emblématiques de Tokyo.', 'https://images.unsplash.com/photo-1542051841857-5f90071e7989?auto=format&fit=crop&w=900&q=80', '[\"guide\", \"exterieur\", \"annulation\"]', '[\"Culture\", \"Soir\", \"Guide\"]', '2026-08-04', 6),
(8, 'Tour des belvédères de Lisbonne', 'Lisbonne', 'culture', 'facile', 'matin', 3.00, 38.00, 4.6, 18, 'Balade guidée entre les points de vue, ruelles historiques et tramways.', 'https://images.unsplash.com/photo-1529260830199-42c24126f198?auto=format&fit=crop&w=900&q=80', '[\"guide\", \"famille\", \"exterieur\"]', '[\"Culture\", \"Ville\", \"Guide\"]', '2026-07-03', 20),
(9, 'Excursion Cercle d’Or', 'Reykjavik', 'nature', 'moyen', 'journee', 8.00, 135.00, 4.8, 12, 'Découverte des geysers, cascades et paysages volcaniques islandais.', 'https://images.unsplash.com/photo-1500534314209-a25ddb2bd429?auto=format&fit=crop&w=900&q=80', '[\"transport\", \"guide\", \"exterieur\"]', '[\"Nature\", \"Volcan\", \"Guide\"]', '2026-11-19', 21),
(10, 'Atelier street-food coréenne', 'Séoul', 'gastronomie', 'facile', 'soir', 3.00, 58.00, 4.7, 14, 'Dégustation et atelier autour des spécialités populaires de Séoul.', 'https://images.unsplash.com/photo-1556911220-bff31c812dba?auto=format&fit=crop&w=900&q=80', '[\"repas\", \"guide\", \"annulation\"]', '[\"Gastronomie\", \"Soir\", \"Local\"]', '2026-10-13', 22),
(11, 'Croisière skyline de Manhattan', 'New York', 'culture', 'facile', 'soir', 2.00, 72.00, 4.6, 20, 'Croisière urbaine pour admirer la skyline et les monuments depuis l’eau.', 'https://images.unsplash.com/photo-1529260830199-42c24126f198?auto=format&fit=crop&w=900&q=80', '[\"exterieur\", \"annulation\"]', '[\"Ville\", \"Skyline\", \"Soir\"]', '2026-09-06', 23),
(12, 'Visite du Vieux-Québec', 'Québec', 'culture', 'facile', 'matin', 2.00, 35.00, 4.5, 16, 'Parcours guidé dans les rues historiques de la vieille ville.', 'https://images.unsplash.com/photo-1529260830199-42c24126f198?auto=format&fit=crop&w=900&q=80', '[\"guide\", \"famille\"]', '[\"Patrimoine\", \"Culture\", \"Famille\"]', '2026-12-05', 24),
(13, 'Randonnée au Pain de Sucre', 'Rio de Janeiro', 'sport', 'moyen', 'matin', 4.00, 65.00, 4.8, 10, 'Sortie sportive avec vues panoramiques sur Rio et la baie.', 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=900&q=80', '[\"guide\", \"exterieur\"]', '[\"Sport\", \"Panorama\", \"Nature\"]', '2026-08-15', 25),
(14, 'Route des vignobles du Cap', 'Le Cap', 'gastronomie', 'facile', 'journee', 7.00, 120.00, 4.7, 12, 'Journée de découverte des vignobles et paysages autour du Cap.', 'https://images.unsplash.com/photo-1556911220-bff31c812dba?auto=format&fit=crop&w=900&q=80', '[\"transport\", \"repas\", \"guide\"]', '[\"Gastronomie\", \"Nature\", \"Guide\"]', '2026-10-04', 26),
(15, 'Safari marin à Zanzibar', 'Zanzibar', 'detente', 'moyen', 'journee', 6.00, 145.00, 4.8, 8, 'Sortie bateau, snorkeling et découverte des lagons turquoise.', 'https://images.unsplash.com/photo-1544551763-46a013bb70d5?auto=format&fit=crop&w=900&q=80', '[\"transport\", \"repas\", \"exterieur\"]', '[\"Snorkeling\", \"Plage\", \"Détente\"]', '2026-12-09', 27),
(16, 'Rome antique guidée', 'Rome', 'culture', 'facile', 'matin', 3.00, 49.00, 4.7, 20, 'Visite du Colisée, du Forum et des grands sites antiques.', 'https://images.unsplash.com/photo-1529260830199-42c24126f198?auto=format&fit=crop&w=900&q=80', '[\"guide\", \"famille\", \"annulation\"]', '[\"Histoire\", \"Culture\", \"Guide\"]', '2026-06-19', 28),
(17, 'Gaudí et quartiers créatifs', 'Barcelone', 'culture', 'facile', 'apres-midi', 3.00, 42.00, 4.5, 18, 'Découverte de l’architecture de Gaudí et des quartiers vivants.', 'https://images.unsplash.com/photo-1529260830199-42c24126f198?auto=format&fit=crop&w=900&q=80', '[\"guide\", \"exterieur\"]', '[\"Architecture\", \"Ville\", \"Culture\"]', '2026-07-12', 29),
(18, 'Remparts de Dubrovnik', 'Dubrovnik', 'culture', 'facile', 'matin', 2.00, 32.00, 4.6, 16, 'Promenade guidée sur les remparts avec vue sur l’Adriatique.', 'https://images.unsplash.com/photo-1529260830199-42c24126f198?auto=format&fit=crop&w=900&q=80', '[\"guide\", \"exterieur\"]', '[\"Patrimoine\", \"Mer\", \"Culture\"]', '2026-07-23', 30),
(19, 'Prague nocturne et légendes', 'Prague', 'culture', 'facile', 'soir', 2.50, 30.00, 4.4, 22, 'Balade de soirée autour des places historiques et légendes locales.', 'https://images.unsplash.com/photo-1529260830199-42c24126f198?auto=format&fit=crop&w=900&q=80', '[\"guide\", \"exterieur\"]', '[\"Soir\", \"Culture\", \"Ville\"]', '2026-09-19', 31),
(20, 'Croisière sur le Bosphore', 'Istanbul', 'detente', 'facile', 'soir', 2.00, 39.00, 4.6, 18, 'Croisière entre Europe et Asie avec vues sur les monuments.', 'https://images.unsplash.com/photo-1544551763-46a013bb70d5?auto=format&fit=crop&w=900&q=80', '[\"exterieur\", \"annulation\"]', '[\"Bosphore\", \"Détente\", \"Soir\"]', '2026-05-21', 32),
(21, 'Marchés flottants de Bangkok', 'Bangkok', 'culture', 'facile', 'matin', 5.00, 68.00, 4.5, 14, 'Excursion vers les marchés flottants avec dégustations locales.', 'https://images.unsplash.com/photo-1529260830199-42c24126f198?auto=format&fit=crop&w=900&q=80', '[\"transport\", \"guide\", \"repas\"]', '[\"Culture\", \"Street-food\", \"Guide\"]', '2026-12-03', 33),
(22, 'Street-food du vieux Hanoï', 'Hanoï', 'gastronomie', 'facile', 'soir', 3.00, 36.00, 4.7, 16, 'Parcours culinaire dans le vieux quartier avec guide local.', 'https://images.unsplash.com/photo-1556911220-bff31c812dba?auto=format&fit=crop&w=900&q=80', '[\"guide\", \"repas\"]', '[\"Gastronomie\", \"Local\", \"Soir\"]', '2026-10-26', 34),
(23, 'Cours de surf à Bondi', 'Sydney', 'sport', 'moyen', 'matin', 2.00, 75.00, 4.6, 10, 'Initiation au surf sur une plage emblématique de Sydney.', 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=900&q=80', '[\"exterieur\", \"equipement\"]', '[\"Surf\", \"Plage\", \"Sport\"]', '2026-11-06', 35),
(24, 'Jet boat sur le lac Wakatipu', 'Queenstown', 'sport', 'sportif', 'apres-midi', 2.00, 120.00, 4.8, 8, 'Activité à sensations sur les eaux de Queenstown.', 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=900&q=80', '[\"exterieur\", \"transport\"]', '[\"Aventure\", \"Sport\", \"Lac\"]', '2026-10-09', 36),
(25, 'Vallée Sacrée des Incas', 'Cusco', 'culture', 'moyen', 'journee', 8.00, 95.00, 4.9, 12, 'Excursion culturelle dans les sites majeurs de la Vallée Sacrée.', 'https://images.unsplash.com/photo-1529260830199-42c24126f198?auto=format&fit=crop&w=900&q=80', '[\"transport\", \"guide\", \"repas\"]', '[\"Culture\", \"Patrimoine\", \"Guide\"]', '2026-06-07', 37),
(26, 'Coucher de soleil à Oia', 'Santorin', 'detente', 'facile', 'soir', 2.00, 44.00, 4.6, 18, 'Sortie accompagnée pour profiter des meilleurs points de vue au coucher du soleil.', 'https://images.unsplash.com/photo-1544551763-46a013bb70d5?auto=format&fit=crop&w=900&q=80', '[\"guide\", \"exterieur\"]', '[\"Couple\", \"Vue mer\", \"Soir\"]', '2026-08-20', 38),
(27, 'Copenhague à vélo', 'Copenhague', 'sport', 'facile', 'matin', 3.00, 40.00, 4.5, 14, 'Circuit à vélo pour découvrir les canaux, quartiers design et lieux iconiques.', 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=900&q=80', '[\"equipement\", \"guide\"]', '[\"Vélo\", \"Ville\", \"Design\"]', '2026-06-04', 39),
(28, 'Safari désert à Dubaï', 'Dubaï', 'aventure', 'moyen', 'soir', 6.00, 110.00, 4.7, 16, 'Excursion dans le désert avec dîner et coucher de soleil.', 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=900&q=80', '[\"transport\", \"repas\", \"exterieur\"]', '[\"Désert\", \"Aventure\", \"Soir\"]', '2026-12-19', 40),
(29, 'Fjord d’Oslo en bateau', 'Oslo', 'nature', 'facile', 'apres-midi', 3.00, 55.00, 4.4, 18, 'Découverte du fjord et des îles proches d’Oslo.', 'https://images.unsplash.com/photo-1500534314209-a25ddb2bd429?auto=format&fit=crop&w=900&q=80', '[\"exterieur\", \"annulation\"]', '[\"Fjord\", \"Nature\", \"Bateau\"]', '2026-07-05', 41),
(30, 'Randonnée au volcan', 'La Réunion', 'sport', 'sportif', 'matin', 6.00, 70.00, 4.8, 10, 'Randonnée encadrée sur un itinéraire volcanique spectaculaire.', 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=900&q=80', '[\"guide\", \"exterieur\"]', '[\"Volcan\", \"Randonnée\", \"Nature\"]', '2026-09-10', 42),
(31, 'Tour des murales de Montréal', 'Montréal', 'culture', 'facile', 'apres-midi', 2.50, 28.00, 4.4, 20, 'Balade artistique autour des fresques et quartiers créatifs.', 'https://images.unsplash.com/photo-1529260830199-42c24126f198?auto=format&fit=crop&w=900&q=80', '[\"guide\", \"exterieur\"]', '[\"Art\", \"Ville\", \"Culture\"]', '2026-08-03', 43),
(32, 'Soirée flamenco à Séville', 'Séville', 'culture', 'facile', 'soir', 2.00, 52.00, 4.7, 18, 'Spectacle flamenco dans un cadre traditionnel avec introduction culturelle.', 'https://images.unsplash.com/photo-1529260830199-42c24126f198?auto=format&fit=crop&w=900&q=80', '[\"annulation\", \"famille\"]', '[\"Flamenco\", \"Soir\", \"Culture\"]', '2026-05-15', 44);

-- --------------------------------------------------------

--
-- Structure de la table `destination`
--

DROP TABLE IF EXISTS `destination`;
CREATE TABLE IF NOT EXISTS `destination` (
  `id_destination` int NOT NULL AUTO_INCREMENT,
  `nom_destination` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `pays` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `categorie` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `note_moyenne` decimal(2,1) DEFAULT NULL,
  `prix` decimal(10,2) DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `duree` int DEFAULT '7',
  `saison` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'ete',
  `styles` json DEFAULT NULL,
  `tags` json DEFAULT NULL,
  `recommande` int DEFAULT '1',
  PRIMARY KEY (`id_destination`)
) ENGINE=MyISAM AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `destination`
--

INSERT INTO `destination` (`id_destination`, `nom_destination`, `pays`, `image`, `categorie`, `note_moyenne`, `prix`, `description`, `duree`, `saison`, `styles`, `tags`, `recommande`) VALUES
(1, 'Bali', 'Indonésie', 'https://images.unsplash.com/photo-1537996194471-e657df975ab4?auto=format&fit=crop&w=900&q=80', 'plage', 4.8, 899.00, 'Plages tropicales, rizières et temples.', 7, 'ete', '[\"couple\", \"amis\", \"nature\"]', '[\"Plage\", \"Nature\", \"Détente\"]', 1),
(2, 'Athènes', 'Grèce', 'https://images.unsplash.com/photo-1603565816030-6b389eeb23cb?auto=format&fit=crop&w=900&q=80', 'culture', 4.4, 529.00, 'Ville historique avec monuments antiques.', 4, 'printemps', '[\"culture\", \"couple\", \"famille\"]', '[\"Culture\", \"Ville\", \"Histoire\"]', 3),
(3, 'Interlaken', 'Suisse', 'https://images.unsplash.com/photo-1500048993953-d23a436266cf?auto=format&fit=crop&w=900&q=80', 'montagne', 4.7, 749.00, 'Montagnes, randonnées et activités sportives.', 5, 'hiver', '[\"sport\", \"nature\", \"amis\"]', '[\"Montagne\", \"Sport\", \"Nature\"]', 2),
(4, 'Marrakech', 'Maroc', 'https://images.unsplash.com/photo-1597212720419-b3d8300b5004?auto=format&fit=crop&w=900&q=80', 'culture', 4.5, 610.00, 'Souks, palais, jardins et excursions dans le désert pour un voyage dépaysant.', 5, 'automne', '[\"culture\", \"couple\", \"amis\"]', '[\"Culture\", \"Désert\", \"Gastronomie\"]', 4),
(5, 'Tokyo', 'Japon', 'https://images.unsplash.com/photo-1540959733332-eab4deabeeaf?auto=format&fit=crop&w=900&q=80', 'aventure', 4.9, 1490.00, 'Grande ville moderne, quartiers animés, temples, technologie et découverte culturelle.', 10, 'printemps', '[\"culture\", \"amis\", \"aventure\"]', '[\"Ville\", \"Culture\", \"Aventure\"]', 5),
(6, 'Maldives', 'Maldives', 'https://images.unsplash.com/photo-1514282401047-d79a71a590e8?auto=format&fit=crop&w=900&q=80', 'detente', 4.9, 1790.00, 'Séjour premium orienté détente, lagons, plages et hébergements proches de l’eau.', 7, 'hiver', '[\"couple\", \"nature\"]', '[\"Détente\", \"Plage\", \"Premium\"]', 6),
(7, 'Lisbonne', 'Portugal', 'https://images.unsplash.com/photo-1477959858617-67f85cf4f1df?auto=format&fit=crop&w=900&q=80', 'ville', 4.6, 690.00, 'Capitale lumineuse avec tramways, belvédères, azulejos et ambiance atlantique.', 5, 'printemps', '[\"culture\", \"couple\", \"amis\"]', '[\"Ville\", \"Culture\", \"Océan\"]', 20),
(8, 'Reykjavik', 'Islande', 'https://images.unsplash.com/photo-1500534314209-a25ddb2bd429?auto=format&fit=crop&w=900&q=80', 'nature', 4.8, 1290.00, 'Base idéale pour découvrir geysers, cascades, volcans et aurores boréales.', 6, 'hiver', '[\"nature\", \"aventure\", \"couple\"]', '[\"Nature\", \"Aurores\", \"Volcans\"]', 21),
(9, 'Séoul', 'Corée du Sud', 'https://images.unsplash.com/photo-1477959858617-67f85cf4f1df?auto=format&fit=crop&w=900&q=80', 'ville', 4.7, 1180.00, 'Métropole dynamique mêlant palais, street-food, quartiers modernes et culture pop.', 8, 'automne', '[\"culture\", \"amis\", \"aventure\"]', '[\"Ville\", \"Culture\", \"Gastronomie\"]', 22),
(10, 'New York', 'États-Unis', 'https://images.unsplash.com/photo-1477959858617-67f85cf4f1df?auto=format&fit=crop&w=900&q=80', 'ville', 4.8, 1590.00, 'Séjour urbain intense entre musées, skyline, quartiers iconiques et spectacles.', 7, 'printemps', '[\"amis\", \"culture\", \"aventure\"]', '[\"Ville\", \"Musées\", \"Shopping\"]', 23),
(11, 'Québec', 'Canada', 'https://images.unsplash.com/photo-1529260830199-42c24126f198?auto=format&fit=crop&w=900&q=80', 'culture', 4.5, 980.00, 'Ville fortifiée au charme européen, idéale pour un séjour historique et nature.', 6, 'hiver', '[\"famille\", \"culture\", \"nature\"]', '[\"Culture\", \"Neige\", \"Patrimoine\"]', 24),
(12, 'Rio de Janeiro', 'Brésil', 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=900&q=80', 'plage', 4.7, 1320.00, 'Plages mythiques, montagnes, samba et panoramas spectaculaires.', 8, 'ete', '[\"amis\", \"plage\", \"aventure\"]', '[\"Plage\", \"Fête\", \"Panorama\"]', 25),
(13, 'Le Cap', 'Afrique du Sud', 'https://images.unsplash.com/photo-1500534314209-a25ddb2bd429?auto=format&fit=crop&w=900&q=80', 'nature', 4.8, 1450.00, 'Ville entre océan, montagnes, vignobles et routes panoramiques.', 9, 'automne', '[\"nature\", \"amis\", \"couple\"]', '[\"Nature\", \"Océan\", \"Safari\"]', 26),
(14, 'Zanzibar', 'Tanzanie', 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=900&q=80', 'plage', 4.6, 1390.00, 'Archipel tropical avec plages blanches, épices, villages et lagons.', 7, 'hiver', '[\"couple\", \"plage\", \"nature\"]', '[\"Plage\", \"Détente\", \"Épices\"]', 27),
(15, 'Rome', 'Italie', 'https://images.unsplash.com/photo-1529260830199-42c24126f198?auto=format&fit=crop&w=900&q=80', 'culture', 4.7, 720.00, 'Capitale historique avec monuments antiques, musées et gastronomie italienne.', 5, 'printemps', '[\"culture\", \"couple\", \"famille\"]', '[\"Culture\", \"Histoire\", \"Gastronomie\"]', 28),
(16, 'Barcelone', 'Espagne', 'https://images.unsplash.com/photo-1477959858617-67f85cf4f1df?auto=format&fit=crop&w=900&q=80', 'ville', 4.5, 640.00, 'Ville méditerranéenne entre architecture, plage, tapas et vie nocturne.', 5, 'ete', '[\"amis\", \"culture\", \"plage\"]', '[\"Ville\", \"Plage\", \"Architecture\"]', 29),
(17, 'Dubrovnik', 'Croatie', 'https://images.unsplash.com/photo-1529260830199-42c24126f198?auto=format&fit=crop&w=900&q=80', 'culture', 4.6, 760.00, 'Cité fortifiée au bord de l’Adriatique, idéale pour culture et mer.', 5, 'ete', '[\"couple\", \"culture\", \"plage\"]', '[\"Culture\", \"Mer\", \"Patrimoine\"]', 30),
(18, 'Prague', 'Tchéquie', 'https://images.unsplash.com/photo-1529260830199-42c24126f198?auto=format&fit=crop&w=900&q=80', 'culture', 4.5, 560.00, 'Ville romantique et historique, parfaite pour un court séjour européen.', 4, 'automne', '[\"couple\", \"culture\", \"amis\"]', '[\"Culture\", \"Ville\", \"Patrimoine\"]', 31),
(19, 'Istanbul', 'Turquie', 'https://images.unsplash.com/photo-1529260830199-42c24126f198?auto=format&fit=crop&w=900&q=80', 'culture', 4.7, 690.00, 'Ville entre Europe et Asie, mosquées, bazars, Bosphore et gastronomie.', 6, 'printemps', '[\"culture\", \"amis\", \"gastronomie\"]', '[\"Culture\", \"Bosphore\", \"Gastronomie\"]', 32),
(20, 'Bangkok', 'Thaïlande', 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=900&q=80', 'aventure', 4.6, 990.00, 'Capitale animée avec temples, marchés flottants, rooftops et cuisine de rue.', 7, 'hiver', '[\"amis\", \"aventure\", \"culture\"]', '[\"Ville\", \"Temples\", \"Street-food\"]', 33),
(21, 'Hanoï', 'Vietnam', 'https://images.unsplash.com/photo-1529260830199-42c24126f198?auto=format&fit=crop&w=900&q=80', 'culture', 4.5, 940.00, 'Ville vivante avec vieux quartier, lacs, cuisine locale et excursions vers la baie.', 8, 'automne', '[\"culture\", \"nature\", \"amis\"]', '[\"Culture\", \"Gastronomie\", \"Nature\"]', 34),
(22, 'Sydney', 'Australie', 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=900&q=80', 'plage', 4.8, 1890.00, 'Grande ville côtière entre opéra, plages, surf et quartiers modernes.', 10, 'hiver', '[\"plage\", \"amis\", \"aventure\"]', '[\"Plage\", \"Ville\", \"Surf\"]', 35),
(23, 'Queenstown', 'Nouvelle-Zélande', 'https://images.unsplash.com/photo-1483728642387-6c3bdd6c93e5?auto=format&fit=crop&w=900&q=80', 'montagne', 4.9, 1790.00, 'Destination aventure entre lacs, montagnes, randonnées et sports extrêmes.', 9, 'automne', '[\"sport\", \"nature\", \"aventure\"]', '[\"Montagne\", \"Aventure\", \"Nature\"]', 36),
(24, 'Cusco', 'Pérou', 'https://images.unsplash.com/photo-1529260830199-42c24126f198?auto=format&fit=crop&w=900&q=80', 'culture', 4.8, 1280.00, 'Ancienne capitale inca, porte d’entrée vers la Vallée Sacrée et le Machu Picchu.', 8, 'printemps', '[\"culture\", \"nature\", \"aventure\"]', '[\"Culture\", \"Randonnée\", \"Patrimoine\"]', 37),
(25, 'Santorin', 'Grèce', 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=900&q=80', 'plage', 4.7, 1120.00, 'Île volcanique célèbre pour ses villages blancs, couchers de soleil et mer Égée.', 6, 'ete', '[\"couple\", \"plage\", \"detente\"]', '[\"Plage\", \"Couple\", \"Vue mer\"]', 38),
(26, 'Copenhague', 'Danemark', 'https://images.unsplash.com/photo-1477959858617-67f85cf4f1df?auto=format&fit=crop&w=900&q=80', 'ville', 4.5, 820.00, 'Capitale nordique agréable, design, canaux, vélo et gastronomie.', 4, 'ete', '[\"culture\", \"couple\", \"famille\"]', '[\"Ville\", \"Design\", \"Vélo\"]', 39),
(27, 'Dubaï', 'Émirats arabes unis', 'https://images.unsplash.com/photo-1512453979798-5ea266f8880c?auto=format&fit=crop&w=900&q=80', 'luxe', 4.6, 1190.00, 'Ville moderne avec gratte-ciel, désert, shopping et expériences premium.', 5, 'hiver', '[\"couple\", \"amis\", \"premium\"]', '[\"Luxe\", \"Désert\", \"Shopping\"]', 40),
(28, 'Oslo', 'Norvège', 'https://images.unsplash.com/photo-1500534314209-a25ddb2bd429?auto=format&fit=crop&w=900&q=80', 'nature', 4.4, 890.00, 'Capitale scandinave entre fjord, musées, forêts et architecture moderne.', 5, 'ete', '[\"nature\", \"culture\", \"couple\"]', '[\"Nature\", \"Fjord\", \"Culture\"]', 41),
(29, 'La Réunion', 'France', 'https://images.unsplash.com/photo-1500534314209-a25ddb2bd429?auto=format&fit=crop&w=900&q=80', 'nature', 4.8, 1350.00, 'Île intense avec cirques, volcans, cascades, plages et randonnées.', 9, 'automne', '[\"nature\", \"sport\", \"famille\"]', '[\"Nature\", \"Randonnée\", \"Volcan\"]', 42),
(30, 'Montréal', 'Canada', 'https://images.unsplash.com/photo-1477959858617-67f85cf4f1df?auto=format&fit=crop&w=900&q=80', 'ville', 4.5, 960.00, 'Ville multiculturelle avec festivals, quartiers vivants et gastronomie.', 6, 'ete', '[\"amis\", \"culture\", \"gastronomie\"]', '[\"Ville\", \"Festival\", \"Gastronomie\"]', 43),
(31, 'Séville', 'Espagne', 'https://images.unsplash.com/photo-1529260830199-42c24126f198?auto=format&fit=crop&w=900&q=80', 'culture', 4.6, 590.00, 'Ville andalouse chaleureuse avec flamenco, palais, patios et tapas.', 5, 'printemps', '[\"culture\", \"couple\", \"gastronomie\"]', '[\"Culture\", \"Flamenco\", \"Gastronomie\"]', 44);

-- --------------------------------------------------------

--
-- Structure de la table `destination_voyageur`
--

DROP TABLE IF EXISTS `destination_voyageur`;
CREATE TABLE IF NOT EXISTS `destination_voyageur` (
  `id_destination_voyageur` int NOT NULL AUTO_INCREMENT,
  `id_destination` int NOT NULL,
  `id_utilisateur` int NOT NULL,
  `role_voyageur` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'voyageur',
  `date_ajout` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_destination_voyageur`),
  UNIQUE KEY `uq_destination_voyageur` (`id_destination`,`id_utilisateur`),
  KEY `idx_destination_voyageur_destination` (`id_destination`),
  KEY `idx_destination_voyageur_utilisateur` (`id_utilisateur`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `destination_voyageur`
--

INSERT INTO `destination_voyageur` (`id_destination_voyageur`, `id_destination`, `id_utilisateur`, `role_voyageur`, `date_ajout`) VALUES
(1, 2, 1, 'voyageur', '2026-05-29 17:19:54');

-- --------------------------------------------------------

--
-- Structure de la table `hebergement`
--

DROP TABLE IF EXISTS `hebergement`;
CREATE TABLE IF NOT EXISTS `hebergement` (
  `id_hebergement` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `destination` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `pays` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `capacite` int DEFAULT '2',
  `prix` decimal(10,2) NOT NULL,
  `note` decimal(3,1) DEFAULT '0.0',
  `etoiles` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `disponibilite` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `image` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `equipements` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `tags` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `date_arrivee` date DEFAULT NULL,
  `date_depart` date DEFAULT NULL,
  `recommande` int DEFAULT '1',
  PRIMARY KEY (`id_hebergement`)
) ENGINE=MyISAM AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `hebergement`
--

INSERT INTO `hebergement` (`id_hebergement`, `nom`, `destination`, `pays`, `type`, `capacite`, `prix`, `note`, `etoiles`, `disponibilite`, `description`, `image`, `equipements`, `tags`, `date_arrivee`, `date_depart`, `recommande`) VALUES
(1, 'Paris Boutique Hotel', 'Paris', 'France', 'hotel', 2, 135.00, 4.4, '★★★★☆', 'Disponible', 'Hôtel élégant proche du centre de Paris, idéal pour un séjour culturel.', 'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=900&q=80', '[\"centre\", \"wifi\", \"petit-dejeuner\", \"annulation\"]', '[\"Hôtel\", \"Centre-ville\", \"Petit-déjeuner\", \"Wi-Fi\"]', '2026-07-10', '2026-07-15', 6),
(2, 'Tokyo Urban Stay', 'Tokyo', 'Japon', 'appartement', 3, 145.00, 4.6, '★★★★☆', 'Disponible', 'Appartement moderne dans un quartier animé de Tokyo, proche des transports.', 'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?auto=format&fit=crop&w=900&q=80', '[\"centre\", \"wifi\", \"annulation\"]', '[\"Appartement\", \"Centre-ville\", \"Wi-Fi\", \"Flexible\"]', '2026-08-03', '2026-08-10', 7),
(3, 'Marrakech Riad Palace', 'Marrakech', 'Maroc', 'hotel', 2, 105.00, 4.7, '★★★★★', 'Plus que 4 chambres disponibles', 'Riad traditionnel avec décoration marocaine, patio intérieur et accès rapide aux souks.', 'https://images.unsplash.com/photo-1597212720419-b3d8300b5004?auto=format&fit=crop&w=900&q=80', '[\"piscine\", \"petit-dejeuner\", \"wifi\"]', '[\"Riad\", \"Piscine\", \"Petit-déjeuner\", \"Culture\"]', '2026-09-12', '2026-09-18', 8),
(4, 'Maldives Lagoon Resort', 'Maldives', 'Maldives', 'resort', 2, 420.00, 4.9, '★★★★★', 'Dernières villas disponibles', 'Resort premium au bord du lagon, parfait pour un séjour détente.', 'https://images.unsplash.com/photo-1573843981267-be1999ff37cd?auto=format&fit=crop&w=900&q=80', '[\"piscine\", \"petit-dejeuner\", \"annulation\", \"wifi\"]', '[\"Resort\", \"Lagon\", \"Premium\", \"Piscine\"]', '2026-12-05', '2026-12-12', 9),
(5, 'Swiss Alpine Chalet', 'Interlaken', 'Suisse', 'villa', 5, 260.00, 4.8, '★★★★★', 'Disponible', 'Chalet confortable avec vue sur les montagnes, adapté aux familles et groupes.', 'https://images.unsplash.com/photo-1445019980597-93fa8acb246c?auto=format&fit=crop&w=900&q=80', '[\"wifi\", \"petit-dejeuner\", \"annulation\"]', '[\"Chalet\", \"Montagne\", \"5 voyageurs\", \"Vue alpine\"]', '2026-02-14', '2026-02-21', 10),
(6, 'Athens History Hotel', 'Athènes', 'Grèce', 'hotel', 2, 92.00, 4.2, '★★★★☆', 'Disponible', 'Hôtel pratique pour visiter les monuments historiques et le centre ville.', 'https://images.unsplash.com/photo-1564501049412-61c2a3083791?auto=format&fit=crop&w=900&q=80', '[\"centre\", \"wifi\", \"petit-dejeuner\"]', '[\"Hôtel\", \"Culture\", \"Centre-ville\", \"Petit-déjeuner\"]', '2026-08-05', '2026-08-12', 11),
(7, 'Bali Garden Villa', 'Bali', 'Indonésie', 'villa', 4, 195.00, 4.6, '★★★★☆', 'Plus que 2 disponibilités', 'Villa avec jardin privé et piscine, idéale pour un séjour entre amis ou en famille.', 'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?auto=format&fit=crop&w=900&q=80', '[\"piscine\", \"wifi\", \"annulation\"]', '[\"Villa\", \"Piscine\", \"4 voyageurs\", \"Jardin privé\"]', '2026-07-10', '2026-07-20', 12),
(8, 'Lisbon Alfama Hotel', 'Lisbonne', 'Portugal', 'hotel', 2, 118.00, 4.5, '★★★★☆', 'Disponible', 'Hôtel de charme dans l’Alfama, proche des belvédères et du tram 28.', 'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=900&q=80', '[\"centre\", \"wifi\", \"petit-dejeuner\"]', '[\"Hôtel\", \"Centre-ville\", \"Wi-Fi\"]', '2026-07-02', '2026-07-07', 20),
(9, 'Northern Lights Lodge', 'Reykjavik', 'Islande', 'lodge', 2, 210.00, 4.7, '★★★★☆', 'Plus que 3 chambres disponibles', 'Lodge confortable pour observer les paysages islandais et les aurores.', 'https://images.unsplash.com/photo-1445019980597-93fa8acb246c?auto=format&fit=crop&w=900&q=80', '[\"wifi\", \"petit-dejeuner\", \"annulation\"]', '[\"Lodge\", \"Nature\", \"Aurores\"]', '2026-11-18', '2026-11-24', 21),
(10, 'Seoul Hanok Stay', 'Séoul', 'Corée du Sud', 'maison', 3, 132.00, 4.6, '★★★★☆', 'Disponible', 'Hébergement traditionnel rénové dans un quartier historique de Séoul.', 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=900&q=80', '[\"wifi\", \"centre\", \"annulation\"]', '[\"Hanok\", \"Culture\", \"Centre-ville\"]', '2026-10-12', '2026-10-20', 22),
(11, 'Manhattan Skyline Hotel', 'New York', 'États-Unis', 'hotel', 2, 245.00, 4.4, '★★★★☆', 'Disponible', 'Hôtel urbain avec accès rapide aux principales attractions de Manhattan.', 'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=900&q=80', '[\"centre\", \"wifi\"]', '[\"Ville\", \"Skyline\", \"Métro proche\"]', '2026-09-05', '2026-09-12', 23),
(12, 'Quebec Old Town Inn', 'Québec', 'Canada', 'hotel', 2, 128.00, 4.5, '★★★★☆', 'Disponible', 'Auberge chaleureuse au cœur du Vieux-Québec.', 'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=900&q=80', '[\"wifi\", \"petit-dejeuner\"]', '[\"Patrimoine\", \"Centre-ville\", \"Petit-déjeuner\"]', '2026-12-04', '2026-12-10', 24),
(13, 'Copacabana Beach Hotel', 'Rio de Janeiro', 'Brésil', 'hotel', 2, 165.00, 4.6, '★★★★☆', 'Dernières chambres disponibles', 'Hôtel proche de la plage de Copacabana avec vue partielle mer.', 'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=900&q=80', '[\"wifi\", \"piscine\", \"petit-dejeuner\"]', '[\"Plage\", \"Piscine\", \"Vue mer\"]', '2026-08-14', '2026-08-22', 25),
(14, 'Cape Town Ocean Villa', 'Le Cap', 'Afrique du Sud', 'villa', 4, 260.00, 4.8, '★★★★★', 'Disponible', 'Villa moderne entre montagne et océan, idéale pour un séjour en groupe.', 'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?auto=format&fit=crop&w=900&q=80', '[\"piscine\", \"wifi\", \"annulation\"]', '[\"Villa\", \"Océan\", \"Groupe\"]', '2026-10-03', '2026-10-12', 26),
(15, 'Zanzibar Spice Resort', 'Zanzibar', 'Tanzanie', 'resort', 2, 310.00, 4.7, '★★★★★', 'Disponible', 'Resort au bord de l’eau avec ambiance tropicale et jardin d’épices.', 'https://images.unsplash.com/photo-1573843981267-be1999ff37cd?auto=format&fit=crop&w=900&q=80', '[\"piscine\", \"petit-dejeuner\", \"wifi\"]', '[\"Resort\", \"Plage\", \"Détente\"]', '2026-12-08', '2026-12-15', 27),
(16, 'Rome Centro Boutique', 'Rome', 'Italie', 'hotel', 2, 140.00, 4.5, '★★★★☆', 'Disponible', 'Boutique hôtel central pour visiter les monuments antiques à pied.', 'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=900&q=80', '[\"centre\", \"wifi\", \"petit-dejeuner\"]', '[\"Hôtel\", \"Histoire\", \"Centre-ville\"]', '2026-06-18', '2026-06-23', 28),
(17, 'Barcelona Design Apartment', 'Barcelone', 'Espagne', 'appartement', 3, 125.00, 4.4, '★★★★☆', 'Disponible', 'Appartement design proche des transports et des quartiers animés.', 'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?auto=format&fit=crop&w=900&q=80', '[\"wifi\", \"centre\", \"annulation\"]', '[\"Appartement\", \"Design\", \"Flexible\"]', '2026-07-11', '2026-07-16', 29),
(18, 'Dubrovnik Sea View Rooms', 'Dubrovnik', 'Croatie', 'hotel', 2, 155.00, 4.6, '★★★★☆', 'Plus que 4 chambres disponibles', 'Chambres avec vue sur l’Adriatique et accès rapide à la vieille ville.', 'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=900&q=80', '[\"wifi\", \"petit-dejeuner\"]', '[\"Mer\", \"Patrimoine\", \"Vue\"]', '2026-07-22', '2026-07-27', 30),
(19, 'Prague Old Bridge Hotel', 'Prague', 'Tchéquie', 'hotel', 2, 98.00, 4.3, '★★★★☆', 'Disponible', 'Hôtel confortable proche du pont Charles et du centre historique.', 'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=900&q=80', '[\"centre\", \"wifi\"]', '[\"Culture\", \"Centre-ville\", \"Patrimoine\"]', '2026-09-18', '2026-09-22', 31),
(20, 'Istanbul Bosphorus Stay', 'Istanbul', 'Turquie', 'hotel', 2, 115.00, 4.5, '★★★★☆', 'Disponible', 'Hôtel avec accès rapide au Bosphore, aux mosquées et aux bazars.', 'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=900&q=80', '[\"wifi\", \"petit-dejeuner\", \"centre\"]', '[\"Bosphore\", \"Culture\", \"Petit-déjeuner\"]', '2026-05-20', '2026-05-26', 32),
(21, 'Bangkok Riverside Hotel', 'Bangkok', 'Thaïlande', 'hotel', 2, 95.00, 4.4, '★★★★☆', 'Disponible', 'Hôtel moderne en bord de rivière avec accès facile aux temples.', 'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=900&q=80', '[\"piscine\", \"wifi\", \"petit-dejeuner\"]', '[\"Rivière\", \"Piscine\", \"Temples\"]', '2026-12-02', '2026-12-09', 33),
(22, 'Hanoi Old Quarter House', 'Hanoï', 'Vietnam', 'maison', 3, 82.00, 4.5, '★★★☆☆', 'Disponible', 'Maison d’hôtes dans le vieux quartier, parfaite pour découvrir la ville à pied.', 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=900&q=80', '[\"wifi\", \"centre\"]', '[\"Vieux quartier\", \"Culture\", \"Local\"]', '2026-10-25', '2026-11-02', 34),
(23, 'Sydney Harbour Hotel', 'Sydney', 'Australie', 'hotel', 2, 220.00, 4.6, '★★★★☆', 'Disponible', 'Hôtel proche du port, des plages et des transports urbains.', 'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=900&q=80', '[\"wifi\", \"piscine\", \"centre\"]', '[\"Port\", \"Plage\", \"Ville\"]', '2026-11-05', '2026-11-15', 35),
(24, 'Queenstown Lake Chalet', 'Queenstown', 'Nouvelle-Zélande', 'chalet', 4, 280.00, 4.9, '★★★★★', 'Disponible', 'Chalet avec vue sur le lac et les montagnes, idéal pour les amateurs d’aventure.', 'https://images.unsplash.com/photo-1445019980597-93fa8acb246c?auto=format&fit=crop&w=900&q=80', '[\"wifi\", \"annulation\", \"petit-dejeuner\"]', '[\"Chalet\", \"Montagne\", \"Aventure\"]', '2026-10-08', '2026-10-17', 36),
(25, 'Cusco Sacred Valley Inn', 'Cusco', 'Pérou', 'hotel', 2, 105.00, 4.6, '★★★★☆', 'Disponible', 'Hôtel calme pour préparer les excursions vers la Vallée Sacrée.', 'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=900&q=80', '[\"wifi\", \"petit-dejeuner\"]', '[\"Culture\", \"Vallée Sacrée\", \"Randonnée\"]', '2026-06-06', '2026-06-14', 37),
(26, 'Santorini Caldera Suites', 'Santorin', 'Grèce', 'suite', 2, 340.00, 4.8, '★★★★★', 'Dernières suites disponibles', 'Suites avec vue sur la caldeira et coucher de soleil.', 'https://images.unsplash.com/photo-1564501049412-61c2a3083791?auto=format&fit=crop&w=900&q=80', '[\"piscine\", \"wifi\", \"petit-dejeuner\"]', '[\"Vue mer\", \"Couple\", \"Premium\"]', '2026-08-19', '2026-08-25', 38),
(27, 'Copenhagen Canal Hotel', 'Copenhague', 'Danemark', 'hotel', 2, 150.00, 4.4, '★★★★☆', 'Disponible', 'Hôtel proche des canaux, du centre et des pistes cyclables.', 'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=900&q=80', '[\"wifi\", \"centre\", \"petit-dejeuner\"]', '[\"Canaux\", \"Design\", \"Vélo\"]', '2026-06-03', '2026-06-07', 39),
(28, 'Dubai Marina Resort', 'Dubaï', 'Émirats arabes unis', 'resort', 2, 290.00, 4.7, '★★★★★', 'Disponible', 'Resort moderne proche de la marina, du désert et des centres commerciaux.', 'https://images.unsplash.com/photo-1573843981267-be1999ff37cd?auto=format&fit=crop&w=900&q=80', '[\"piscine\", \"wifi\", \"petit-dejeuner\"]', '[\"Luxe\", \"Marina\", \"Piscine\"]', '2026-12-18', '2026-12-23', 40),
(29, 'Oslo Fjord Hotel', 'Oslo', 'Norvège', 'hotel', 2, 145.00, 4.3, '★★★★☆', 'Disponible', 'Hôtel confortable avec accès au fjord, musées et quartiers modernes.', 'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=900&q=80', '[\"wifi\", \"centre\"]', '[\"Fjord\", \"Culture\", \"Nordique\"]', '2026-07-04', '2026-07-09', 41),
(30, 'Reunion Volcano Lodge', 'La Réunion', 'France', 'lodge', 3, 170.00, 4.7, '★★★★☆', 'Disponible', 'Lodge au calme pour rayonner vers les cirques, cascades et volcans.', 'https://images.unsplash.com/photo-1445019980597-93fa8acb246c?auto=format&fit=crop&w=900&q=80', '[\"wifi\", \"annulation\", \"petit-dejeuner\"]', '[\"Volcan\", \"Randonnée\", \"Nature\"]', '2026-09-09', '2026-09-18', 42),
(31, 'Montreal Downtown Loft', 'Montréal', 'Canada', 'appartement', 3, 120.00, 4.4, '★★★★☆', 'Disponible', 'Loft pratique en centre-ville, adapté aux festivals et sorties urbaines.', 'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?auto=format&fit=crop&w=900&q=80', '[\"wifi\", \"centre\", \"annulation\"]', '[\"Loft\", \"Festival\", \"Centre-ville\"]', '2026-08-02', '2026-08-08', 43),
(32, 'Seville Patio Hotel', 'Séville', 'Espagne', 'hotel', 2, 102.00, 4.5, '★★★★☆', 'Disponible', 'Hôtel andalou avec patio, proche de l’Alcazar et des quartiers animés.', 'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=900&q=80', '[\"wifi\", \"petit-dejeuner\", \"centre\"]', '[\"Flamenco\", \"Patio\", \"Culture\"]', '2026-05-14', '2026-05-19', 44);

-- --------------------------------------------------------

--
-- Structure de la table `itineraire`
--

DROP TABLE IF EXISTS `itineraire`;
CREATE TABLE IF NOT EXISTS `itineraire` (
  `id_itineraire` int NOT NULL AUTO_INCREMENT,
  `id_utilisateur` int NOT NULL,
  `nom` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `statut` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'actif',
  PRIMARY KEY (`id_itineraire`),
  KEY `idx_itineraire_utilisateur` (`id_utilisateur`,`statut`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `itineraire_element`
--

DROP TABLE IF EXISTS `itineraire_element`;
CREATE TABLE IF NOT EXISTS `itineraire_element` (
  `id_itineraire_element` int NOT NULL AUTO_INCREMENT,
  `id_itineraire` int NOT NULL,
  `type_element` enum('transport','hebergement','activite') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `id_element` int NOT NULL,
  `nom_element` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `details` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `prix_unitaire` decimal(10,2) NOT NULL DEFAULT '0.00',
  `quantite` int NOT NULL DEFAULT '1',
  `ordre` int NOT NULL DEFAULT '1',
  `date_ajout` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_itineraire_element`),
  KEY `idx_itineraire_element_itineraire` (`id_itineraire`,`ordre`),
  KEY `idx_itineraire_element_type` (`type_element`,`id_element`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `itineraire_voyageur`
--

DROP TABLE IF EXISTS `itineraire_voyageur`;
CREATE TABLE IF NOT EXISTS `itineraire_voyageur` (
  `id_itineraire_voyageur` int NOT NULL AUTO_INCREMENT,
  `id_itineraire` int NOT NULL,
  `id_utilisateur` int NOT NULL,
  `role_voyageur` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'voyageur',
  `date_ajout` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_itineraire_voyageur`),
  UNIQUE KEY `uq_itineraire_voyageur` (`id_itineraire`,`id_utilisateur`),
  KEY `idx_itineraire_voyageur_itineraire` (`id_itineraire`),
  KEY `idx_itineraire_voyageur_utilisateur` (`id_utilisateur`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `ligne_panier`
--

DROP TABLE IF EXISTS `ligne_panier`;
CREATE TABLE IF NOT EXISTS `ligne_panier` (
  `id_ligne` int NOT NULL AUTO_INCREMENT,
  `id_panier` int NOT NULL,
  `type_element` enum('destination','transport','hebergement','activite') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `id_element` int NOT NULL,
  `nom_element` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `prix_unitaire` decimal(10,2) NOT NULL,
  `quantite` int DEFAULT '1',
  `date_arrivee` date DEFAULT NULL,
  `date_depart` date DEFAULT NULL,
  `nb_nuits` int DEFAULT NULL,
  PRIMARY KEY (`id_ligne`),
  KEY `id_panier` (`id_panier`)
) ENGINE=MyISAM AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `ligne_panier`
--

INSERT INTO `ligne_panier` (`id_ligne`, `id_panier`, `type_element`, `id_element`, `nom_element`, `prix_unitaire`, `quantite`, `date_arrivee`, `date_depart`, `nb_nuits`) VALUES
(33, 2, 'transport', 2, 'TGV Lyria - Paris vers Interlaken', 145.00, 3, NULL, NULL, NULL),
(34, 2, 'hebergement', 6, 'Athens History Hotel', 92.00, 3, NULL, NULL, 3),
(35, 2, 'activite', 2, 'Visite de l\'Acropole', 35.00, 3, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `notification`
--

DROP TABLE IF EXISTS `notification`;
CREATE TABLE IF NOT EXISTS `notification` (
  `id_notification` int NOT NULL AUTO_INCREMENT,
  `id_utilisateur` int NOT NULL,
  `titre` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `date_envoi` datetime DEFAULT CURRENT_TIMESTAMP,
  `statut_lecture` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id_notification`),
  KEY `id_utilisateur` (`id_utilisateur`)
) ENGINE=MyISAM AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `notification`
--

INSERT INTO `notification` (`id_notification`, `id_utilisateur`, `titre`, `message`, `date_envoi`, `statut_lecture`) VALUES
(1, 1, 'Bienvenue sur VoyageVista', 'Votre compte a bien été créé et vous pouvez maintenant utiliser votre espace voyage.', '2026-05-27 22:57:33', 1),
(2, 3, 'Réservation validée', 'Votre réservation a été validée pour un montant total de 789 €.', '2026-05-28 17:23:58', 0),
(3, 3, 'Paiement validé - Réservation confirmée', 'Votre paiement a été validé avec succès.\n\nMontant total : 179,00 €\nNombre d\'éléments : 1\n\nRécapitulatif :\n- Activité : Parapente au-dessus des Alpes x1 (160,00 €)\n', '2026-05-28 17:34:39', 1),
(4, 3, 'Paiement validé - Réservation confirmée', 'Votre paiement a été validé avec succès.\n\nMontant total : 164,00 €\nNombre d\'éléments : 1\n\nRécapitulatif :\n- Transport : TGV Lyria - Paris vers Interlaken x1 (145,00 €)\n', '2026-05-28 17:36:32', 0),
(5, 3, 'Message envoyé au support', 'Votre message a bien été envoyé à VoyageVista. Sujet : Paiement. Nom : es te. E-mail : test@mail.com. Message : coucou test test', '2026-05-28 17:43:17', 0),
(6, 1, 'Paiement validé - Réservation confirmée', 'Votre paiement a été validé avec succès.\n\nMontant total : 1 063,00 €\nNombre d\'éléments : 3\n\nRécapitulatif :\n- Activité : Journée bateau et snorkeling x1 (220,00 €)\n- Activité : Visite de l\'Acropole x1 (35,00 €)\n- Transport : Air France - Paris vers Bali x1 (789,00 €)\n', '2026-05-29 13:37:39', 0),
(7, 1, 'Paiement validé - Réservation confirmée', 'Votre paiement a été validé avec succès.\n\nMontant total : 1 208,00 €\nNombre d\'éléments : 3\nFrais de dossier : 19,00 €\n\nRécapitulatif :\n- Activité : Journée bateau et snorkeling x1 (220,00 €)\n- Activité : Journée bateau et snorkeling x1 (220,00 €)\n- Destination : Interlaken x1 (749,00 €)\n', '2026-05-29 13:49:04', 0),
(8, 1, 'Réservation confirmée - Journée bateau et snorkeli', 'Votre réservation a été confirmée.\n\nType : Activité\nNom : Journée bateau et snorkeling\nQuantité : 1\nPrix unitaire : 220,00 €\nTotal : 220,00 €\n\nLes places disponibles de cette activité ont été mises à jour.', '2026-05-29 13:49:04', 1),
(9, 1, 'Réservation confirmée - Journée bateau et snorkeli', 'Votre réservation a été confirmée.\n\nType : Activité\nNom : Journée bateau et snorkeling\nQuantité : 1\nPrix unitaire : 220,00 €\nTotal : 220,00 €\n\nLes places disponibles de cette activité ont été mises à jour.', '2026-05-29 13:49:04', 0),
(10, 1, 'Réservation confirmée - Interlaken', 'Votre réservation a été confirmée.\n\nType : Destination\nNom : Interlaken\nQuantité : 1\nPrix unitaire : 749,00 €\nTotal : 749,00 €', '2026-05-29 13:49:04', 0),
(11, 1, 'Annulation confirmée - Journée bateau et snorkelin', 'Votre réservation a bien été annulée.\n\nType : Activité\nNom : Journée bateau et snorkeling\nQuantité annulée : 1\nNotification d\'origine : #8\n\nLes places disponibles de cette activité ont été réaugmentées dans la base de données.', '2026-05-29 13:56:22', 0),
(12, 1, 'Paiement validé - Réservation confirmée', 'Votre paiement a été validé avec succès.\n\nMontant total : 289,00 €\nNombre d\'éléments / nuits : 2\nFrais de dossier : 19,00 €\n\nRécapitulatif :\n- Hébergement : Paris Boutique Hotel du 29/05/2026 au 31/05/2026 (2 nuit(s), 270,00 €)\n', '2026-05-29 15:05:12', 0),
(13, 1, 'Réservation confirmée - Paris Boutique Hotel', 'Votre réservation a été confirmée.\n\nType : Hébergement\nID élément : 1\nNom : Paris Boutique Hotel\nDate d\'arrivée : 29/05/2026\nDate de départ : 31/05/2026\nNombre de nuits : 2\nPrix par nuit : 135,00 €\nTotal hébergement : 270,00 €\n\nLes dates sont maintenant bloquées dans la base de données.', '2026-05-29 15:05:12', 1),
(14, 1, 'Réservation modifiée - Paris Boutique Hotel', 'Votre réservation d\'hébergement a bien été modifiée.\n\nType : Hébergement\nID élément : 1\nID réservation hébergement : 2\nNom : Paris Boutique Hotel\n\nAnciennes dates :\nDate d\'arrivée : 29/05/2026\nDate de départ : 31/05/2026\nNombre de nuits : 2\n\nNouvelles dates :\nDate d\'arrivée : 23/07/2026\nDate de départ : 25/07/2026\nNombre de nuits : 2\nNotification d\'origine : #13\n\nLes nouvelles dates sont maintenant enregistrées dans la base de données.', '2026-05-29 15:17:49', 1),
(15, 1, 'Annulation confirmée - Paris Boutique Hotel', 'Votre réservation d\'hébergement a bien été annulée.\n\nType : Hébergement\nID élément : 1\nID réservation hébergement : 2\nNom : Paris Boutique Hotel\nDate d\'arrivée annulée : 29/05/2026\nDate de départ annulée : 31/05/2026\nNombre de nuits annulées : 2\nNotification d\'origine : #14\n\nLes dates sont de nouveau disponibles dans la base de données.', '2026-05-29 15:17:59', 1),
(16, 1, 'Paiement validé - Réservation confirmée', 'Votre paiement a été validé avec succès.\n\nMontant total : 2 926,00 €\nNombre d\'éléments / nuits : 7\nFrais de dossier : 19,00 €\n\nRécapitulatif :\n- Transport : Air France - Paris vers Bali | Paris → Bali | Départ : 10/07/2026 à 08:30 | Arrivée : 23:15 | Quantité : 3 (2 367,00 €)\n- Hébergement : Paris Boutique Hotel du 15/06/2026 au 19/06/2026 (4 nuit(s), 540,00 €)\n', '2026-05-29 15:53:59', 0),
(17, 1, 'Réservation confirmée - Air France - Paris vers Ba', 'Votre réservation a été confirmée.\n\nType : Transport\nID élément : 1\nNom : Air France - Paris vers Bali\nID réservation transport : 1\nCompagnie : Air France\nTrajet : Paris → Bali\nDate de départ : 10/07/2026\nHeure de départ : 08:30\nHeure d\'arrivée : 23:15\nDate de retour : 20/07/2026\nQuantité réservée : 3\nPrix par personne : 789,00 €\nTotal transport : 2 367,00 €\n\nLes places disponibles ont été diminuées dans la base de données.', '2026-05-29 15:53:59', 0),
(18, 1, 'Réservation confirmée - Paris Boutique Hotel', 'Votre réservation a été confirmée.\n\nType : Hébergement\nID élément : 1\nNom : Paris Boutique Hotel\nID réservation hébergement : 3\nDate d\'arrivée : 15/06/2026\nDate de départ : 19/06/2026\nNombre de nuits : 4\nPrix par nuit : 135,00 €\nTotal hébergement : 540,00 €\n\nLes dates sont maintenant bloquées dans la base de données.', '2026-05-29 15:53:59', 0),
(19, 1, 'Paiement validé - Réservation confirmée', 'Votre paiement a été validé avec succès.\n\nMontant total : 808,00 €\nNombre d\'éléments / nuits : 1\nFrais de dossier : 19,00 €\n\nRécapitulatif :\n- Transport : Air France - Paris vers Bali | Paris → Bali | Départ : 10/07/2026 à 08:30 | Arrivée : 23:15 | Quantité : 1 (789,00 €)\n', '2026-05-29 15:54:32', 0),
(20, 1, 'Réservation confirmée - Air France - Paris vers Ba', 'Votre réservation a été confirmée.\n\nType : Transport\nID élément : 1\nNom : Air France - Paris vers Bali\nID réservation transport : 2\nCompagnie : Air France\nTrajet : Paris → Bali\nDate de départ : 10/07/2026\nHeure de départ : 08:30\nHeure d\'arrivée : 23:15\nDate de retour : 20/07/2026\nQuantité réservée : 1\nPrix par personne : 789,00 €\nTotal transport : 789,00 €\n\nLes places disponibles ont été diminuées dans la base de données.', '2026-05-29 15:54:32', 0),
(21, 1, 'Annulation confirmée - Trajet réservé', 'Votre trajet réservé a bien été annulé.\n\nType : Transport\nID transport : 1\nCompagnie : Air France\nTrajet : Paris → Bali\nDate de départ : 10/07/2026\nHeure de départ : 08:30\nQuantité annulée : 3\n\nLes places sont de nouveau disponibles.', '2026-05-29 15:55:14', 0),
(22, 1, 'Annulation confirmée - Trajet réservé', 'Votre trajet réservé a bien été annulé.\n\nType : Transport\nID transport : 1\nCompagnie : Air France\nTrajet : Paris → Bali\nDate de départ : 10/07/2026\nHeure de départ : 08:30\nQuantité annulée : 1\n\nLes places sont de nouveau disponibles.', '2026-05-29 15:55:29', 0),
(23, 1, 'Paiement validé - Réservation confirmée', 'Votre paiement a été validé avec succès.\n\nMontant total : 808,00 €\nNombre d\'éléments / nuits : 1\nFrais de dossier : 19,00 €\n\nRécapitulatif :\n- Transport : Air France - Paris vers Bali | Paris → Bali | Départ : 10/07/2026 à 08:30 | Arrivée : 23:15 | Quantité : 1 (789,00 €)\n', '2026-05-29 16:06:47', 0),
(24, 1, 'Réservation confirmée - Air France - Paris vers Ba', 'Votre réservation a été confirmée.\n\nType : Transport\nID élément : 1\nNom : Air France - Paris vers Bali\nID réservation transport : 3\nCompagnie : Air France\nTrajet : Paris → Bali\nDate de départ : 10/07/2026\nHeure de départ : 08:30\nHeure d\'arrivée : 23:15\nDate de retour : 20/07/2026\nQuantité réservée : 1\nPrix par personne : 789,00 €\nTotal transport : 789,00 €\n\nLes places disponibles ont été diminuées dans la base de données.', '2026-05-29 16:06:47', 1),
(25, 1, 'Annulation confirmée - Transport', 'Votre réservation de transport a bien été annulée.\n\nType : Transport\nID élément : 1\nID réservation transport : 3\nNom : Air France - Paris vers Bali\nCompagnie : Air France\nTrajet : Paris → Bali\nDate de départ : 10/07/2026\nHeure de départ : 08:30\nHeure d\'arrivée : 23:15\nQuantité annulée : 1\nNotification d\'origine : #24\n\nLa réservation transport est annulée dans la base et les places disponibles ont été mises à jour.', '2026-05-29 16:07:27', 0),
(26, 1, 'Paiement validé - Réservation confirmée', 'Votre paiement a été validé avec succès.\n\nMontant total : 164,00 €\nNombre d\'éléments / nuits : 1\nFrais de dossier : 19,00 €\n\nRécapitulatif :\n- Transport : TGV Lyria - Paris vers Interlaken | Paris → Interlaken | Départ : 15/06/2026 à 09:15 | Arrivée : 15:40 | Quantité : 1 (145,00 €)\n', '2026-05-29 16:08:18', 0),
(27, 1, 'Réservation confirmée - TGV Lyria - Paris vers Int', 'Votre réservation a été confirmée.\n\nType : Transport\nID élément : 2\nNom : TGV Lyria - Paris vers Interlaken\nID réservation transport : 4\nCompagnie : TGV Lyria\nTrajet : Paris → Interlaken\nDate de départ : 15/06/2026\nHeure de départ : 09:15\nHeure d\'arrivée : 15:40\nDate de retour : 21/06/2026\nQuantité réservée : 1\nPrix par personne : 145,00 €\nTotal transport : 145,00 €\n\nLes places disponibles ont été diminuées dans la base de données.', '2026-05-29 16:08:18', 0),
(28, 1, 'Paiement validé - Réservation confirmée', 'Votre paiement a été validé avec succès.\n\nMontant total : 808,00 €\nNombre d\'éléments / nuits : 1\nFrais de dossier : 19,00 €\n\nRécapitulatif :\n- Transport : Air France - Paris vers Bali | Paris → Bali | Départ : 10/07/2026 à 08:30 | Arrivée : 23:15 | Quantité : 1 (789,00 €)\n', '2026-05-29 16:15:44', 0),
(29, 1, 'Réservation confirmée - Air France - Paris vers Ba', 'Votre réservation a été confirmée.\n\nType : Transport\nID élément : 1\nNom : Air France - Paris vers Bali\nID réservation transport : 5\nCompagnie : Air France\nTrajet : Paris → Bali\nDate de départ : 10/07/2026\nHeure de départ : 08:30\nHeure d\'arrivée : 23:15\nDate de retour : 20/07/2026\nQuantité réservée : 1\nPrix par personne : 789,00 €\nTotal transport : 789,00 €\n\nLes places disponibles ont été diminuées dans la base de données.', '2026-05-29 16:15:44', 0),
(30, 2, 'Paiement validé - Réservation confirmée', 'Votre paiement a été validé avec succès.\n\nMontant total : 3 147,00 €\nNombre d\'éléments / nuits : 7\nFrais de dossier : 19,00 €\n\nRécapitulatif :\n- Activité : Cours de cuisine locale x2 (150,00 €)\n- Transport : TGV Lyria - Paris vers Interlaken | Paris → Interlaken | Départ : 15/06/2026 à 09:15 | Arrivée : 15:40 | Quantité : 1 (145,00 €)\n- Destination : Bali x1 (899,00 €)\n- Transport : Location Auto - Athènes vers Athènes | Athènes → Athènes | Départ : 07/08/2026 à 10:00 | Arrivée : 18:00 | Quantité : 2 (144,00 €)\n- Destination : Maldives x1 (1 790,00 €)\n', '2026-05-29 23:14:21', 0),
(31, 2, 'Réservation confirmée - Cours de cuisine locale', 'Votre réservation a été confirmée.\n\nType : Activité\nID élément : 4\nNom : Cours de cuisine locale\nQuantité : 2\nPrix unitaire : 75,00 €\nTotal : 150,00 €\n\nLes places disponibles de cette activité ont été mises à jour.', '2026-05-29 23:14:21', 0),
(32, 2, 'Réservation confirmée - TGV Lyria - Paris vers Int', 'Votre réservation a été confirmée.\n\nType : Transport\nID élément : 2\nNom : TGV Lyria - Paris vers Interlaken\nID réservation transport : 6\nCompagnie : TGV Lyria\nTrajet : Paris → Interlaken\nDate de départ : 15/06/2026\nHeure de départ : 09:15\nHeure d\'arrivée : 15:40\nDate de retour : 21/06/2026\nQuantité réservée : 1\nPrix par personne : 145,00 €\nTotal transport : 145,00 €\n\nLes places disponibles ont été diminuées dans la base de données.', '2026-05-29 23:14:21', 1),
(33, 2, 'Réservation confirmée - Bali', 'Votre réservation a été confirmée.\n\nType : Destination\nID élément : 1\nNom : Bali\nQuantité : 1\nPrix unitaire : 899,00 €\nTotal : 899,00 €', '2026-05-29 23:14:21', 0),
(34, 2, 'Réservation confirmée - Location Auto - Athènes ve', 'Votre réservation a été confirmée.\n\nType : Transport\nID élément : 5\nNom : Location Auto - Athènes vers Athènes\nID réservation transport : 7\nCompagnie : Location Auto\nTrajet : Athènes → Athènes\nDate de départ : 07/08/2026\nHeure de départ : 10:00\nHeure d\'arrivée : 18:00\nDate de retour : 12/08/2026\nQuantité réservée : 2\nPrix par personne : 72,00 €\nTotal transport : 144,00 €\n\nLes places disponibles ont été diminuées dans la base de données.', '2026-05-29 23:14:21', 0),
(37, 3, 'Paiement validé - Réservation confirmée', 'Votre paiement a été validé avec succès.\n\nMontant total : 829,00 €\nNombre d\'éléments / nuits : 6\nFrais de dossier : 19,00 €\n\nRécapitulatif :\n- Hébergement : Paris Boutique Hotel du 01/06/2026 au 07/06/2026 (6 nuit(s), 810,00 €)\n', '2026-05-29 23:18:02', 0),
(38, 3, 'Réservation confirmée - Paris Boutique Hotel', 'Votre réservation a été confirmée.\n\nType : Hébergement\nID élément : 1\nNom : Paris Boutique Hotel\nID réservation hébergement : 4\nDate d\'arrivée : 01/06/2026\nDate de départ : 07/06/2026\nNombre de nuits : 6\nPrix par nuit : 135,00 €\nTotal hébergement : 810,00 €\n\nLes dates sont maintenant bloquées dans la base de données.', '2026-05-29 23:18:02', 1),
(39, 3, 'Annulation confirmée - Paris Boutique Hotel', 'Votre réservation d\'hébergement a bien été annulée.\n\nType : Hébergement\nID élément : 1\nID réservation hébergement : 4\nNom : Paris Boutique Hotel\nDate d\'arrivée annulée : 01/06/2026\nDate de départ annulée : 07/06/2026\nNombre de nuits annulées : 6\nNotification d\'origine : #38\n\nLes dates sont de nouveau disponibles dans la base de données.', '2026-05-29 23:18:27', 0);

-- --------------------------------------------------------

--
-- Structure de la table `panier`
--

DROP TABLE IF EXISTS `panier`;
CREATE TABLE IF NOT EXISTS `panier` (
  `id_panier` int NOT NULL AUTO_INCREMENT,
  `id_utilisateur` int NOT NULL,
  `date_creation` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_panier`),
  KEY `id_utilisateur` (`id_utilisateur`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `panier`
--

INSERT INTO `panier` (`id_panier`, `id_utilisateur`, `date_creation`) VALUES
(1, 1, '2026-05-27 23:04:03'),
(2, 1, '2026-05-27 23:12:12'),
(3, 2, '2026-05-28 09:24:28'),
(4, 3, '2026-05-28 17:23:26');

-- --------------------------------------------------------

--
-- Structure de la table `reservation`
--

DROP TABLE IF EXISTS `reservation`;
CREATE TABLE IF NOT EXISTS `reservation` (
  `id_reservation` int NOT NULL AUTO_INCREMENT,
  `id_utilisateur` int NOT NULL,
  `date_reservation` datetime DEFAULT CURRENT_TIMESTAMP,
  `montant_total` decimal(10,2) DEFAULT NULL,
  `statut` enum('en_attente','validee','annulee') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'en_attente',
  PRIMARY KEY (`id_reservation`),
  KEY `id_utilisateur` (`id_utilisateur`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `reservation`
--

INSERT INTO `reservation` (`id_reservation`, `id_utilisateur`, `date_reservation`, `montant_total`, `statut`) VALUES
(1, 3, '2026-05-28 17:23:58', 789.00, 'validee');

-- --------------------------------------------------------

--
-- Structure de la table `reservation_hebergement`
--

DROP TABLE IF EXISTS `reservation_hebergement`;
CREATE TABLE IF NOT EXISTS `reservation_hebergement` (
  `id_reservation_hebergement` int NOT NULL AUTO_INCREMENT,
  `id_hebergement` int NOT NULL,
  `id_utilisateur` int DEFAULT NULL,
  `date_arrivee` date NOT NULL,
  `date_depart` date NOT NULL,
  `quantite` int NOT NULL DEFAULT '1',
  `statut` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'confirmee',
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_reservation_hebergement`),
  KEY `idx_hebergement_dates` (`id_hebergement`,`date_arrivee`,`date_depart`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `reservation_hebergement`
--

INSERT INTO `reservation_hebergement` (`id_reservation_hebergement`, `id_hebergement`, `id_utilisateur`, `date_arrivee`, `date_depart`, `quantite`, `statut`, `date_creation`) VALUES
(1, 1, 1, '2026-06-10', '2026-06-15', 1, 'confirmee', '2026-05-29 14:08:41'),
(2, 1, 1, '2026-07-23', '2026-07-25', 1, 'annulee', '2026-05-29 15:05:12'),
(3, 1, 1, '2026-06-15', '2026-06-19', 1, 'confirmee', '2026-05-29 15:53:59'),
(4, 1, 3, '2026-06-01', '2026-06-07', 1, 'annulee', '2026-05-29 23:18:02');

-- --------------------------------------------------------

--
-- Structure de la table `reservation_transport`
--

DROP TABLE IF EXISTS `reservation_transport`;
CREATE TABLE IF NOT EXISTS `reservation_transport` (
  `id_reservation_transport` int NOT NULL AUTO_INCREMENT,
  `id_transport` int NOT NULL,
  `id_utilisateur` int NOT NULL,
  `quantite` int NOT NULL DEFAULT '1',
  `statut` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'confirmee',
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_reservation_transport`),
  KEY `idx_transport_statut` (`id_transport`,`statut`),
  KEY `idx_utilisateur_transport` (`id_utilisateur`,`statut`)
) ENGINE=MyISAM AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `reservation_transport`
--

INSERT INTO `reservation_transport` (`id_reservation_transport`, `id_transport`, `id_utilisateur`, `quantite`, `statut`, `date_creation`) VALUES
(1, 1, 1, 3, 'annulee', '2026-05-29 15:53:59'),
(2, 1, 1, 1, 'annulee', '2026-05-29 15:54:32'),
(3, 1, 1, 1, 'annulee', '2026-05-29 16:06:47'),
(4, 2, 1, 1, 'confirmee', '2026-05-29 16:08:18'),
(5, 1, 1, 1, 'confirmee', '2026-05-29 16:15:44'),
(6, 2, 2, 1, 'annulee', '2026-05-29 23:14:21'),
(7, 5, 2, 2, 'confirmee', '2026-05-29 23:14:21');

-- --------------------------------------------------------

--
-- Structure de la table `transport`
--

DROP TABLE IF EXISTS `transport`;
CREATE TABLE IF NOT EXISTS `transport` (
  `id_transport` int NOT NULL AUTO_INCREMENT,
  `type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `icone` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `compagnie` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `ville_depart` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `ville_arrivee` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `date_depart` date DEFAULT NULL,
  `date_retour` date DEFAULT NULL,
  `heure_depart` time DEFAULT NULL,
  `heure_arrivee` time DEFAULT NULL,
  `duree` decimal(5,2) DEFAULT NULL,
  `prix` decimal(10,2) DEFAULT NULL,
  `places_disponibles` int DEFAULT NULL,
  `options` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `tags` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `recommande` int DEFAULT '1',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  PRIMARY KEY (`id_transport`)
) ENGINE=MyISAM AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `transport`
--

INSERT INTO `transport` (`id_transport`, `type`, `icone`, `compagnie`, `ville_depart`, `ville_arrivee`, `date_depart`, `date_retour`, `heure_depart`, `heure_arrivee`, `duree`, `prix`, `places_disponibles`, `options`, `tags`, `recommande`, `description`) VALUES
(1, 'avion', '✈️', 'Air France', 'Paris', 'Bali', '2026-07-10', '2026-07-20', '08:30:00', '23:15:00', 16.00, 789.00, 3, '[\"bagage\", \"confort\", \"wifi\"]', '[\"Bagage inclus\", \"Wi-Fi\", \"Confort +\"]', 1, 'Vol long-courrier avec correspondance, adapté à un séjour international.'),
(2, 'train', '🚆', 'TGV Lyria', 'Paris', 'Interlaken', '2026-06-15', '2026-06-21', '09:15:00', '15:40:00', 6.50, 145.00, 23, '[\"direct\", \"eco\", \"wifi\"]', '[\"Trajet direct\", \"Faible émission\", \"Wi-Fi\"]', 2, 'Trajet confortable en train avec faible émission carbone.'),
(3, 'avion', '✈️', 'Aegean Airlines', 'Paris', 'Athènes', '2026-08-05', '2026-08-12', '11:20:00', '15:35:00', 3.25, 159.00, 12, '[\"direct\", \"bagage\", \"annulation\"]', '[\"Direct\", \"Bagage inclus\", \"Annulation possible\"]', 3, 'Vol direct vers Athènes, pratique pour un court séjour culturel.'),
(4, 'bus', '🚌', 'EuroBus', 'Paris', 'Marrakech', '2026-09-01', '2026-09-10', '06:00:00', '05:30:00', 23.50, 99.00, 30, '[\"bagage\", \"wifi\"]', '[\"Prix bas\", \"Bagage inclus\", \"Wi-Fi\"]', 4, 'Trajet économique en bus, adapté aux voyageurs avec un budget réduit.'),
(5, 'voiture', '🚗', 'Location Auto', 'Athènes', 'Athènes', '2026-08-07', '2026-08-12', '10:00:00', '18:00:00', 8.00, 72.00, 2, '[\"annulation\", \"confort\"]', '[\"Location\", \"Flexible\", \"Confort +\"]', 5, 'Location de voiture pour organiser librement les déplacements sur place.'),
(6, 'avion', '✈️', 'TAP Air Portugal', 'Paris', 'Lisbonne', '2026-07-02', '2026-07-07', '07:45:00', '09:25:00', 2.40, 149.00, 34, '[\"direct\", \"bagage\", \"annulation\"]', '[\"Direct\", \"Europe\", \"Bagage inclus\"]', 20, 'Vol direct court vers Lisbonne.'),
(7, 'avion', '✈️', 'Icelandair', 'Paris', 'Reykjavik', '2026-11-18', '2026-11-24', '13:10:00', '15:50:00', 3.70, 310.00, 22, '[\"bagage\", \"confort\", \"annulation\"]', '[\"Aurores\", \"Bagage inclus\", \"Flexible\"]', 21, 'Vol vers l’Islande pour un séjour nature.'),
(8, 'avion', '✈️', 'Korean Air', 'Paris', 'Séoul', '2026-10-12', '2026-10-20', '21:00:00', '16:35:00', 11.50, 820.00, 18, '[\"bagage\", \"repas\", \"wifi\"]', '[\"Long-courrier\", \"Repas inclus\", \"Confort\"]', 22, 'Vol long-courrier vers Séoul.'),
(9, 'avion', '✈️', 'Delta Airlines', 'Paris', 'New York', '2026-09-05', '2026-09-12', '10:30:00', '12:55:00', 8.25, 610.00, 26, '[\"bagage\", \"wifi\", \"direct\"]', '[\"Direct\", \"Ville\", \"Wi-Fi\"]', 23, 'Vol transatlantique vers New York.'),
(10, 'avion', '✈️', 'Air Canada', 'Paris', 'Québec', '2026-12-04', '2026-12-10', '14:20:00', '16:10:00', 7.80, 690.00, 20, '[\"bagage\", \"confort\"]', '[\"Canada\", \"Neige\", \"Bagage inclus\"]', 24, 'Vol vers le Québec en saison hivernale.'),
(11, 'avion', '✈️', 'LATAM', 'Paris', 'Rio de Janeiro', '2026-08-14', '2026-08-22', '22:15:00', '06:40:00', 11.20, 870.00, 16, '[\"bagage\", \"repas\", \"confort\"]', '[\"Plage\", \"Long-courrier\", \"Repas inclus\"]', 25, 'Vol vers Rio avec arrivée matinale.'),
(12, 'avion', '✈️', 'South African Airways', 'Paris', 'Le Cap', '2026-10-03', '2026-10-12', '20:45:00', '09:50:00', 12.10, 930.00, 14, '[\"bagage\", \"repas\"]', '[\"Nature\", \"Océan\", \"Bagage inclus\"]', 26, 'Vol vers l’Afrique du Sud.'),
(13, 'avion', '✈️', 'Qatar Airways', 'Paris', 'Zanzibar', '2026-12-08', '2026-12-15', '15:05:00', '07:30:00', 13.40, 960.00, 12, '[\"bagage\", \"repas\", \"confort\"]', '[\"Plage\", \"Escale\", \"Confort\"]', 27, 'Vol avec correspondance vers Zanzibar.'),
(14, 'train', '🚆', 'Trenitalia', 'Paris', 'Rome', '2026-06-18', '2026-06-23', '06:45:00', '17:10:00', 10.40, 189.00, 38, '[\"eco\", \"wifi\"]', '[\"Train\", \"Faible émission\", \"Wi-Fi\"]', 28, 'Trajet ferroviaire vers Rome.'),
(15, 'train', '🚆', 'Renfe SNCF', 'Paris', 'Barcelone', '2026-07-11', '2026-07-16', '09:42:00', '16:35:00', 6.90, 129.00, 42, '[\"direct\", \"wifi\"]', '[\"Direct\", \"Train\", \"Méditerranée\"]', 29, 'Train direct vers Barcelone.'),
(16, 'avion', '✈️', 'Croatia Airlines', 'Paris', 'Dubrovnik', '2026-07-22', '2026-07-27', '12:05:00', '14:25:00', 2.30, 230.00, 19, '[\"bagage\", \"direct\"]', '[\"Adriatique\", \"Direct\", \"Bagage\"]', 30, 'Vol vers Dubrovnik.'),
(17, 'train', '🚆', 'EuroCity', 'Paris', 'Prague', '2026-09-18', '2026-09-22', '07:10:00', '18:40:00', 11.50, 165.00, 33, '[\"eco\", \"wifi\"]', '[\"Train\", \"Ville\", \"Éco\"]', 31, 'Trajet européen vers Prague.'),
(18, 'avion', '✈️', 'Turkish Airlines', 'Paris', 'Istanbul', '2026-05-20', '2026-05-26', '11:25:00', '15:55:00', 3.50, 240.00, 28, '[\"bagage\", \"repas\", \"direct\"]', '[\"Direct\", \"Culture\", \"Repas inclus\"]', 32, 'Vol direct vers Istanbul.'),
(19, 'avion', '✈️', 'Thai Airways', 'Paris', 'Bangkok', '2026-12-02', '2026-12-09', '13:40:00', '06:20:00', 11.70, 790.00, 24, '[\"bagage\", \"repas\", \"wifi\"]', '[\"Asie\", \"Long-courrier\", \"Confort\"]', 33, 'Vol long-courrier vers Bangkok.'),
(20, 'avion', '✈️', 'Vietnam Airlines', 'Paris', 'Hanoï', '2026-10-25', '2026-11-02', '14:00:00', '06:35:00', 11.60, 740.00, 21, '[\"bagage\", \"repas\"]', '[\"Vietnam\", \"Culture\", \"Bagage inclus\"]', 34, 'Vol direct vers Hanoï.'),
(21, 'avion', '✈️', 'Qantas', 'Paris', 'Sydney', '2026-11-05', '2026-11-15', '19:30:00', '06:10:00', 22.50, 1220.00, 17, '[\"bagage\", \"repas\", \"confort\"]', '[\"Australie\", \"Long-courrier\", \"Confort\"]', 35, 'Vol long-courrier avec correspondance vers Sydney.'),
(22, 'avion', '✈️', 'Air New Zealand', 'Paris', 'Queenstown', '2026-10-08', '2026-10-17', '18:20:00', '11:30:00', 25.00, 1380.00, 13, '[\"bagage\", \"repas\"]', '[\"Aventure\", \"Montagne\", \"Long-courrier\"]', 36, 'Vol vers Queenstown avec correspondance.'),
(23, 'avion', '✈️', 'Iberia', 'Paris', 'Cusco', '2026-06-06', '2026-06-14', '12:30:00', '08:15:00', 17.20, 980.00, 15, '[\"bagage\", \"repas\"]', '[\"Pérou\", \"Culture\", \"Escale\"]', 37, 'Vol vers Cusco via correspondance.'),
(24, 'avion', '✈️', 'Aegean Airlines', 'Paris', 'Santorin', '2026-08-19', '2026-08-25', '08:10:00', '13:05:00', 3.90, 270.00, 25, '[\"bagage\", \"annulation\"]', '[\"Île\", \"Plage\", \"Flexible\"]', 38, 'Vol vers Santorin.'),
(25, 'avion', '✈️', 'SAS', 'Paris', 'Copenhague', '2026-06-03', '2026-06-07', '09:50:00', '11:45:00', 1.90, 135.00, 36, '[\"direct\", \"eco\"]', '[\"Nordique\", \"Direct\", \"Ville\"]', 39, 'Vol court vers Copenhague.'),
(26, 'avion', '✈️', 'Emirates', 'Paris', 'Dubaï', '2026-12-18', '2026-12-23', '21:55:00', '07:20:00', 6.40, 540.00, 30, '[\"bagage\", \"repas\", \"confort\"]', '[\"Luxe\", \"Direct\", \"Confort\"]', 40, 'Vol direct vers Dubaï.'),
(27, 'avion', '✈️', 'Norwegian', 'Paris', 'Oslo', '2026-07-04', '2026-07-09', '10:15:00', '12:35:00', 2.30, 160.00, 32, '[\"direct\", \"eco\"]', '[\"Fjord\", \"Direct\", \"Éco\"]', 41, 'Vol vers Oslo.'),
(28, 'avion', '✈️', 'Air Austral', 'Paris', 'La Réunion', '2026-09-09', '2026-09-18', '19:20:00', '08:45:00', 11.25, 850.00, 18, '[\"bagage\", \"repas\", \"direct\"]', '[\"Volcan\", \"Nature\", \"Direct\"]', 42, 'Vol direct vers La Réunion.'),
(29, 'avion', '✈️', 'Air Transat', 'Paris', 'Montréal', '2026-08-02', '2026-08-08', '13:25:00', '15:10:00', 7.70, 590.00, 27, '[\"bagage\", \"direct\"]', '[\"Canada\", \"Ville\", \"Direct\"]', 43, 'Vol direct vers Montréal.'),
(30, 'avion', '✈️', 'Vueling', 'Paris', 'Séville', '2026-05-14', '2026-05-19', '07:20:00', '09:45:00', 2.40, 125.00, 40, '[\"direct\", \"eco\"]', '[\"Andalousie\", \"Direct\", \"Prix bas\"]', 44, 'Vol direct vers Séville.');

-- --------------------------------------------------------

--
-- Structure de la table `utilisateur`
--

DROP TABLE IF EXISTS `utilisateur`;
CREATE TABLE IF NOT EXISTS `utilisateur` (
  `id_utilisateur` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `prenom` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `mot_de_passe` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `role` enum('client','admin','gestionnaire','fournisseur') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'client',
  `date_inscription` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_utilisateur`),
  UNIQUE KEY `email` (`email`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `utilisateur`
--

INSERT INTO `utilisateur` (`id_utilisateur`, `nom`, `prenom`, `email`, `mot_de_passe`, `role`, `date_inscription`) VALUES
(1, 'chaus', 'luca', 'viagioo.tv@gmail.com', '$2y$10$DAJ1qJ2jEeNU4keeS.iQB.AUa98P0I3AIqNiMnnZYZQTGsl8dapxu', 'client', '2026-05-27 22:52:56'),
(2, 'chaus', 'Milo', 'viagioo@gmail.com', '$2y$10$3lcM2REOLc/r0MJQ8zZ44ugPy55orNgoTIFoLmScKcgGeEVsCIdIi', 'client', '2026-05-28 09:23:59'),
(3, 'te', 'es', 'test@mail.com', '$2y$10$9UFiMaGzL9VFkGLihYmiK.jNQB2YMUhMrVIDOkVdpNjQiMINlGv/6', 'admin', '2026-05-28 17:22:55'),
(4, 'Admin', 'Admin', 'admin@mail.com', '$2y$10$OmiH8tEDEInApMcaeaEKkOaqjHlcR8cjxfRhDQneAn2/2bc70twNe', 'admin', '2026-05-31 15:27:19'),
(5, 'Client', 'Client', 'client@mail.com', '$2y$10$VeoCIVzMsUUx5o43DPxed.LjE1U7nAUK2G6JXSMbnGlZN/i.7s/2u', 'client', '2026-05-31 15:27:55'),
(6, 'Gestionnaire', 'Gestionnaire', 'gestionnaire@mail.com', '$2y$10$EXg4lLsqXhtWRThCiSgSw.dPdkQcwVIxciqq5ZiqqnMDObj7BKeQG', 'gestionnaire', '2026-05-31 15:29:06');
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
