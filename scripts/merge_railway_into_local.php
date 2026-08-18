<?php

declare(strict_types=1);

$pdo = new PDO('mysql:host=127.0.0.1;port=3306;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$source = 'cafeon_railway_import';
$target = 'cafeon';
$userMap = [];
$storeMap = [];
$inserted = ['users' => 0, 'stores' => 0, 'social_accounts' => 0, 'store_members' => 0];

function insertRow(PDO $pdo, string $database, string $table, array $row): int
{
    $columns = array_keys($row);
    $quoted = implode(', ', array_map(fn (string $column): string => "`$column`", $columns));
    $params = implode(', ', array_map(fn (string $column): string => ":$column", $columns));
    $statement = $pdo->prepare("INSERT INTO `$database`.`$table` ($quoted) VALUES ($params)");
    $statement->execute($row);

    return (int) $pdo->lastInsertId();
}

$pdo->beginTransaction();

try {
    foreach ($pdo->query("SELECT * FROM `$source`.`users` ORDER BY id") as $row) {
        $sourceId = (int) $row['id'];
        $existing = $pdo->prepare("SELECT id FROM `$target`.`users` WHERE email = ? LIMIT 1");
        $existing->execute([$row['email']]);
        $targetId = $existing->fetchColumn();

        if ($targetId === false) {
            unset($row['id']);
            if (strtoupper((string) $row['role']) === 'OWNER') {
                $row['role'] = 'ADMIN';
            }
            $targetId = insertRow($pdo, $target, 'users', $row);
            $inserted['users']++;
        }

        $userMap[$sourceId] = (int) $targetId;
    }

    foreach ($pdo->query("SELECT * FROM `$source`.`stores` ORDER BY id") as $row) {
        $sourceId = (int) $row['id'];
        $existing = $pdo->prepare("SELECT id FROM `$target`.`stores` WHERE slug = ? LIMIT 1");
        $existing->execute([$row['slug']]);
        $targetId = $existing->fetchColumn();

        if ($targetId === false) {
            unset($row['id']);
            $targetId = insertRow($pdo, $target, 'stores', $row);
            $inserted['stores']++;
        }

        $storeMap[$sourceId] = (int) $targetId;
    }

    foreach ($pdo->query("SELECT * FROM `$source`.`social_accounts` ORDER BY id") as $row) {
        $existing = $pdo->prepare("SELECT id FROM `$target`.`social_accounts` WHERE provider = ? AND provider_user_id = ? LIMIT 1");
        $existing->execute([$row['provider'], $row['provider_user_id']]);

        if ($existing->fetchColumn() === false) {
            unset($row['id']);
            $row['user_id'] = $userMap[(int) $row['user_id']];
            insertRow($pdo, $target, 'social_accounts', $row);
            $inserted['social_accounts']++;
        }
    }

    foreach ($pdo->query("SELECT * FROM `$source`.`store_members` ORDER BY id") as $row) {
        $targetStoreId = $storeMap[(int) $row['store_id']];
        $targetUserId = $userMap[(int) $row['user_id']];
        $existing = $pdo->prepare("SELECT id FROM `$target`.`store_members` WHERE store_id = ? AND user_id = ? LIMIT 1");
        $existing->execute([$targetStoreId, $targetUserId]);

        if ($existing->fetchColumn() === false) {
            unset($row['id']);
            $row['store_id'] = $targetStoreId;
            $row['user_id'] = $targetUserId;
            insertRow($pdo, $target, 'store_members', $row);
            $inserted['store_members']++;
        }
    }

    $pdo->commit();
    echo json_encode($inserted, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $exception) {
    $pdo->rollBack();
    fwrite(STDERR, $exception->getMessage().PHP_EOL);
    exit(1);
}
