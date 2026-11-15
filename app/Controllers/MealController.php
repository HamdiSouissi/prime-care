<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AuditService;
use App\Services\EditWindowService;
use App\Services\MealService;
use App\Services\ScheduleService;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;

/**
 * E10 & E11 — Meals gallery by day (/meals), Meal detail with Before/After photos,
 * 4-segment portion bar and linked before/after glycemias (/meals/{id}), and 30-minute rule enforcement.
 */
final class MealController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('meals', 'view');

        $pdo = Database::connection();
        $days = (int) $request->query('days', 7);
        if (!in_array($days, [7, 14, 30], true)) {
            $days = 7;
        }

        $fromUtc = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->modify("-{$days} days")
            ->format('Y-m-d H:i:s');

        $stmt = $pdo->prepare(
            'SELECT ml.*, u.first_name AS author_first_name, u.last_name AS author_last_name
             FROM `meals` ml
             INNER JOIN `users` u ON u.id = ml.recorded_by
             WHERE ml.patient_id = 1
               AND ml.eaten_at >= :from_utc
               AND ml.deleted_at IS NULL
             ORDER BY ml.eaten_at DESC'
        );
        $stmt->execute(['from_utc' => $fromUtc]);

        $mealsByDay = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $dayLocal = ScheduleService::utcToLocalParis((string) $row['eaten_at'], 'Y-m-d');
            $row['time_local'] = ScheduleService::utcToLocalParis((string) $row['eaten_at'], 'H:i');
            $row['type_label'] = MealService::MEAL_TYPES[(string) $row['meal_type']] ?? 'Repas';

            $pStmt = $pdo->prepare(
                'SELECT `id`, `kind`, `photo_path`, `thumb_path`
                 FROM `meal_photos`
                 WHERE `meal_id` = :mid AND `deleted_at` IS NULL
                 ORDER BY `sort_order` ASC'
            );
            $pStmt->execute(['mid' => (int) $row['id']]);
            $row['photos'] = $pStmt->fetchAll(PDO::FETCH_ASSOC);
            $row['linked_glycemia'] = MealService::findLinkedGlycemias((string) $row['eaten_at'], 1);

            $mealsByDay[$dayLocal][] = $row;
        }

        $favStmt = $pdo->query(
            'SELECT * FROM `food_favorites` WHERE `patient_id` = 1 AND `is_active` = 1 AND `deleted_at` IS NULL ORDER BY `sort_order` ASC'
        );
        $favorites = $favStmt !== false ? $favStmt->fetchAll(PDO::FETCH_ASSOC) : [];

        return $this->view('meals/index', [
            'pageTitle' => 'Repas & Hydratation',
            'activeTab' => 'journal',
            'backUrl' => '/journal',
            'backLabel' => 'Journal',
            'mealsByDay' => $mealsByDay,
            'favorites' => $favorites,
            'days' => $days,
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        $this->authorize('meals', 'view');

        $mealId = (int) $id;
        $pdo = Database::connection();

        $stmt = $pdo->prepare(
            'SELECT ml.*, u.first_name AS author_first_name, u.last_name AS author_last_name
             FROM `meals` ml
             INNER JOIN `users` u ON u.id = ml.recorded_by
             WHERE ml.id = :id AND ml.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['id' => $mealId]);
        $meal = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$meal) {
            throw new RuntimeException('Repas introuvable.', 404);
        }

        $meal['date_local'] = ScheduleService::utcToLocalParis((string) $meal['eaten_at'], 'd/m/Y');
        $meal['time_local'] = ScheduleService::utcToLocalParis((string) $meal['eaten_at'], 'H:i');
        $meal['type_label'] = MealService::MEAL_TYPES[(string) $meal['meal_type']] ?? 'Repas';

        $pStmt = $pdo->prepare(
            'SELECT * FROM `meal_photos` WHERE `meal_id` = :mid AND `deleted_at` IS NULL ORDER BY `sort_order` ASC'
        );
        $pStmt->execute(['mid' => $mealId]);
        $photos = $pStmt->fetchAll(PDO::FETCH_ASSOC);
        $photosByKind = ['avant' => null, 'apres' => null, 'autre' => null];
        foreach ($photos as $ph) {
            $photosByKind[(string) $ph['kind']] = $ph;
        }

        $linkedGlycemia = MealService::findLinkedGlycemias((string) $meal['eaten_at'], 1);
        $editPerm = EditWindowService::checkEditPermission(
            $meal,
            Auth::id() ?? 0,
            Auth::role() ?? 'viewer',
            'check'
        );

        return $this->view('meals/show', [
            'pageTitle' => $meal['type_label'] . ' du ' . $meal['date_local'],
            'activeTab' => 'journal',
            'backUrl' => '/meals',
            'backLabel' => 'Repas',
            'meal' => $meal,
            'photosByKind' => $photosByKind,
            'linkedGlycemia' => $linkedGlycemia,
            'editPerm' => $editPerm,
        ]);
    }

    public function store(Request $request): Response
    {
        $api = new ApiMedicalController();
        $res = $api->storeMeal($request);
        $decoded = json_decode($res->body(), true) ?: [];

        if (!($decoded['ok'] ?? false)) {
            Session::flash('error', (string) ($decoded['error']['message'] ?? 'Erreur lors de l’enregistrement du repas.'));
            return $this->redirect('/meals');
        }

        Session::flash('success', 'Repas enregistré avec succès.');
        $mealId = (int) ($decoded['data']['id'] ?? 0);
        return $this->redirect($mealId > 0 ? '/meals/' . $mealId : '/meals');
    }

    public function update(Request $request, string $id): Response
    {
        $this->authorize('meals', 'update');

        $mealId = (int) $id;
        $userId = Auth::id() ?? 0;
        $role = Auth::role() ?? 'viewer';
        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT * FROM `meals` WHERE `id` = :id AND `deleted_at` IS NULL LIMIT 1');
        $stmt->execute(['id' => $mealId]);
        $meal = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$meal) {
            throw new RuntimeException('Repas introuvable.', 404);
        }

        $modifiedReason = trim((string) $request->input('modified_reason', ''));
        $perm = EditWindowService::checkEditPermission($meal, $userId, $role, $modifiedReason);
        if (!$perm['allowed']) {
            Session::flash('error', (string) $perm['message']);
            return $this->redirect('/meals/' . $mealId);
        }

        $description = trim((string) $request->input('description', (string) $meal['description']));
        $portion = (int) $request->input('portion_eaten', (int) $meal['portion_eaten']);
        if (!in_array($portion, [0, 25, 50, 75, 100], true)) {
            $portion = (int) $meal['portion_eaten'];
        }
        $carbsRaw = trim((string) $request->input('carbs_g', (string) ($meal['carbs_g'] ?? '')));
        $carbsG = ($carbsRaw !== '' && is_numeric($carbsRaw)) ? max(0, (int) $carbsRaw) : null;
        $drinkType = trim((string) $request->input('drink_type', (string) ($meal['drink_type'] ?? ''))) ?: null;
        $comment = trim((string) $request->input('comment', (string) ($meal['comment'] ?? ''))) ?: null;
        $nowUtc = gmdate('Y-m-d H:i:s');

        $upd = $pdo->prepare(
            'UPDATE `meals`
             SET `description` = :desc,
                 `portion_eaten` = :portion,
                 `carbs_g` = :carbs,
                 `drink_type` = :drink,
                 `comment` = :comment,
                 `modified_reason` = :reason,
                 `updated_at` = :now_utc
             WHERE `id` = :id'
        );
        $upd->execute([
            'desc' => $description,
            'portion' => $portion,
            'carbs' => $carbsG,
            'drink' => $drinkType,
            'comment' => $comment,
            'reason' => $modifiedReason !== '' ? $modifiedReason : $meal['modified_reason'],
            'now_utc' => $nowUtc,
            'id' => $mealId,
        ]);

        // Handle optional updated photos
        $filesByKind = [];
        foreach (['avant' => 'photo_avant', 'apres' => 'photo_apres', 'autre' => 'photo_autre'] as $k => $field) {
            $f = $request->file($field);
            if (is_array($f) && ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $filesByKind[$k] = $f;
            }
        }
        if ($filesByKind !== []) {
            MealService::saveMealPhotos($mealId, $filesByKind, $userId, 1);
        }

        AuditService::record('meal.update', 'meal', $mealId, $meal, [
            'description' => $description,
            'portion_eaten' => $portion,
            'carbs_g' => $carbsG,
            'modified_reason' => $modifiedReason,
        ], 1, $userId);

        Session::flash('success', 'Fiche repas mise à jour.');
        return $this->redirect('/meals/' . $mealId);
    }

    public function delete(Request $request, string $id): Response
    {
        $this->authorize('meals', 'delete');

        $mealId = (int) $id;
        $userId = Auth::id() ?? 0;
        $nowUtc = gmdate('Y-m-d H:i:s');
        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT * FROM `meals` WHERE `id` = :id AND `deleted_at` IS NULL LIMIT 1');
        $stmt->execute(['id' => $mealId]);
        $meal = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$meal) {
            throw new RuntimeException('Repas introuvable.', 404);
        }

        $pdo->prepare('UPDATE `meals` SET `deleted_at` = :d, `updated_at` = :u WHERE `id` = :id')
            ->execute(['d' => $nowUtc, 'u' => $nowUtc, 'id' => $mealId]);

        AuditService::record('meal.soft_delete', 'meal', $mealId, $meal, ['deleted_at' => $nowUtc], 1, $userId);
        Session::flash('success', 'Repas archivé (suppression logique).');
        return $this->redirect('/meals');
    }
}
