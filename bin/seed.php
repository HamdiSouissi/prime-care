<?php

declare(strict_types=1);

/**
 * Prime Care — Database Seeder CLI
 * Usage: php bin/seed.php
 */

require_once dirname(__DIR__) . '/app/Helpers/bootstrap.php';

use App\Core\Config;
use Database\Seeds\Phase1Seeder;
use Database\Seeds\Phase2Seeder;
use Database\Seeds\Phase3Seeder;
use Database\Seeds\Phase4Seeder;

// Phase 4 §6.1: bin/seed.php refuses to run in production
if ((string) Config::get('app.env', 'development') === 'production') {
    fwrite(STDERR, "[REFUS] bin/seed.php ne peut pas être exécuté en mode production (app.env = production).\n");
    exit(1);
}

echo "Insertion des données de démonstration (Phases 1, 2, 3 & 4)...\n";
Phase1Seeder::run();
Phase2Seeder::run();
Phase3Seeder::run();
Phase4Seeder::run();
echo "Seed terminé avec succès.\n";
