#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Prime Care — Script de regroupement et minification CSS sans outil de build (I1)
 *
 * Concatène dans l'ordre :
 *   1. public/assets/css/tokens.css
 *   2. public/assets/css/base.css
 *   3. public/assets/css/components.css
 *   4. public/assets/css/utilities.css
 *   5. public/assets/css/app.css
 *
 * Supprime les commentaires et espaces superflus tout en préservant les espaces
 * requis dans calc(), var() et les sélecteurs descendants, puis écrit :
 *   public/assets/css/app.min.css
 *
 * Usage :
 *   php bin/build-css.php
 */

$rootDir = dirname(__DIR__);
$cssDir = $rootDir . '/public/assets/css';

$sourceFiles = [
    'tokens.css',
    'base.css',
    'components.css',
    'utilities.css',
    'app.css',
];

$combined = '';
$rawBytes = 0;

foreach ($sourceFiles as $file) {
    $path = $cssDir . '/' . $file;
    if (!is_file($path)) {
        fwrite(STDERR, "[ERREUR] Fichier CSS introuvable : {$path}\n");
        exit(1);
    }
    $content = (string) file_get_contents($path);
    $rawBytes += strlen($content);
    $combined .= "\n" . $content;
}

/**
 * Minifie une feuille CSS sans altérer les expressions calc() ni les chaînes.
 */
function minifyCss(string $css): string
{
    // 1. Supprimer les commentaires /* ... */
    $css = (string) preg_replace('~/\*.*?\*/~s', '', $css);

    // 2. Protéger les chaînes entre guillemets ("..." et '...')
    $placeholders = [];
    $css = (string) preg_replace_callback('/"(?:\\\\.|[^"\\\\])*"|\'(?:\\\\.|[^\'\\\\])*\'/', static function (array $m) use (&$placeholders): string {
        $key = '___CSS_STR_' . count($placeholders) . '___';
        $placeholders[$key] = $m[0];
        return $key;
    }, $css);

    // 3. Normaliser tous les retours à la ligne et tabulations en un seul espace
    $css = (string) preg_replace('/\s+/', ' ', $css);

    // 4. Supprimer les espaces autour des délimiteurs structurels { } ; ,
    $css = (string) preg_replace('/\s*([{};,])\s*/', '$1', $css);

    // 5. Supprimer les espaces après ':' (sans toucher à l'espace avant ':' pour préserver les sélecteurs descendants)
    $css = (string) preg_replace('/:\s+/', ':', $css);

    // 6. Supprimer les points-virgules finaux avant '}'
    $css = str_replace(';}', '}', $css);

    // 7. Restaurer les chaînes entre guillemets intactes
    if ($placeholders !== []) {
        $css = strtr($css, $placeholders);
    }

    return trim($css) . "\n";
}

$minified = minifyCss($combined);
$outPath = $cssDir . '/app.min.css';

if (file_put_contents($outPath, $minified) === false) {
    fwrite(STDERR, "[ERREUR] Impossible d'écrire {$outPath}\n");
    exit(1);
}

$minBytes = strlen($minified);
$savingPct = $rawBytes > 0 ? round((1 - ($minBytes / $rawBytes)) * 100, 1) : 0.0;

fwrite(
    STDOUT,
    sprintf(
        "[OK] public/assets/css/app.min.css généré (%d octets -> %d octets, -%.1f%%)\n",
        $rawBytes,
        $minBytes,
        $savingPct
    )
);
