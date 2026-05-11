<?php
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/team_helpers.php';
require_once __DIR__ . '/../core/object_links.php';

function fetchContacts(PDO $pdo, int $teamId, ?string $search = null): array
{
    if ($teamId <= 0) {
        return [];
    }

    $params = [':team_id' => $teamId];
    $where = 'WHERE team_id = :team_id';

    if ($search !== null) {
        $search = trim($search);
    }

    if ($search !== null && $search !== '') {
        $where .= ' AND (firstname LIKE :like_firstname OR surname LIKE :like_surname OR email LIKE :like_email OR phone LIKE :like_phone OR city LIKE :like_city)';
        $like = '%' . $search . '%';
        $params[':like_firstname'] = $like;
        $params[':like_surname'] = $like;
        $params[':like_email'] = $like;
        $params[':like_phone'] = $like;
        $params[':like_city'] = $like;
    }

    $stmt = $pdo->prepare(
        'SELECT id, firstname, surname, email, phone, city, country, updated_at
         FROM contacts
         ' . $where . '
         ORDER BY firstname, surname, id'
    );
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function fetchContact(PDO $pdo, int $teamId, int $contactId): ?array
{
    if ($teamId <= 0 || $contactId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM contacts WHERE id = :id AND team_id = :team_id LIMIT 1');
    $stmt->execute([':id' => $contactId, ':team_id' => $teamId]);
    $contact = $stmt->fetch();

    return $contact ?: null;
}

function deleteContact(int $teamId, int $contactId): bool
{
    if ($teamId <= 0 || $contactId <= 0) {
        return false;
    }

    $pdo = getDatabaseConnection();

    $existing = fetchContact($pdo, $teamId, $contactId);
    if (!$existing) {
        return false;
    }

    $pdo->beginTransaction();
    try {
        clearAllObjectLinks($pdo, 'contact', $contactId);
        $stmt = $pdo->prepare('DELETE FROM contacts WHERE id = :id AND team_id = :team_id');
        $stmt->execute([
            ':id' => $contactId,
            ':team_id' => $teamId
        ]);
        $pdo->commit();
        return true;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function userCanAccessTeam(int $userId, int $teamId): bool
{
    if ($userId <= 0 || $teamId <= 0) {
        return false;
    }

    $pdo = getDatabaseConnection();
    return userHasTeamAccess($pdo, $userId, $teamId);
}

function createContact(int $teamId, array $payload): int
{
    if ($teamId <= 0) {
        throw new InvalidArgumentException('Team id is required.');
    }

    $pdo = getDatabaseConnection();

    $stmt = $pdo->prepare(
        'INSERT INTO contacts
            (team_id, firstname, surname, email, phone, address, postal_code, city, country, website, notes)
         VALUES
            (:team_id, :firstname, :surname, :email, :phone, :address, :postal_code, :city, :country, :website, :notes)'
    );
    $stmt->execute([
        ':team_id' => $teamId,
        ':firstname' => normalizeContactOptionalString((string) ($payload['firstname'] ?? '')),
        ':surname' => trim((string) ($payload['surname'] ?? '')),
        ':email' => normalizeContactOptionalString((string) ($payload['email'] ?? '')),
        ':phone' => normalizeContactOptionalString((string) ($payload['phone'] ?? '')),
        ':address' => normalizeContactOptionalString((string) ($payload['address'] ?? '')),
        ':postal_code' => normalizeContactOptionalString((string) ($payload['postal_code'] ?? '')),
        ':city' => normalizeContactOptionalString((string) ($payload['city'] ?? '')),
        ':country' => normalizeContactOptionalString((string) ($payload['country'] ?? '')),
        ':website' => normalizeContactOptionalString((string) ($payload['website'] ?? '')),
        ':notes' => normalizeContactOptionalString((string) ($payload['notes'] ?? ''))
    ]);

    return (int) $pdo->lastInsertId();
}

function updateContact(int $teamId, int $contactId, array $payload): bool
{
    if ($teamId <= 0 || $contactId <= 0) {
        return false;
    }

    $pdo = getDatabaseConnection();

    $existing = fetchContact($pdo, $teamId, $contactId);
    if (!$existing) {
        return false;
    }

    $stmt = $pdo->prepare(
        'UPDATE contacts
         SET firstname = :firstname,
             surname = :surname,
             email = :email,
             phone = :phone,
             address = :address,
             postal_code = :postal_code,
             city = :city,
             country = :country,
             website = :website,
             notes = :notes
         WHERE id = :id AND team_id = :team_id'
    );
    $stmt->execute([
        ':firstname' => normalizeContactOptionalString((string) ($payload['firstname'] ?? '')),
        ':surname' => trim((string) ($payload['surname'] ?? '')),
        ':email' => normalizeContactOptionalString((string) ($payload['email'] ?? '')),
        ':phone' => normalizeContactOptionalString((string) ($payload['phone'] ?? '')),
        ':address' => normalizeContactOptionalString((string) ($payload['address'] ?? '')),
        ':postal_code' => normalizeContactOptionalString((string) ($payload['postal_code'] ?? '')),
        ':city' => normalizeContactOptionalString((string) ($payload['city'] ?? '')),
        ':country' => normalizeContactOptionalString((string) ($payload['country'] ?? '')),
        ':website' => normalizeContactOptionalString((string) ($payload['website'] ?? '')),
        ':notes' => normalizeContactOptionalString((string) ($payload['notes'] ?? '')),
        ':id' => $contactId,
        ':team_id' => $teamId
    ]);

    return true;
}

function normalizeContactOptionalString(string $value): ?string
{
    $value = trim($value);
    return $value === '' ? null : $value;
}

