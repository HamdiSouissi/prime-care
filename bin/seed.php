<?php

declare(strict_types=1);

/**
 * Prime Care — Database Seeder CLI
 * Usage: php bin/seed.php
 */

require_once dirname(__DIR__) . '/app/Helpers/bootstrap.php';

use Database\Seeds\Phase1Seeder;

echo "Insertion des données de démonstration...\n";
Phase1Seeder::run();
echo "Seed terminé avec succès.\n";
