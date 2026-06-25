<?php

declare(strict_types=1);

final class Identifier
{
  private const PREFIXES = [
    'team' => 'T',
    'company' => 'C',
    'student' => 'S',
    'member' => 'M',
  ];

  public function __construct(private readonly PDO $pdo)
  {
  }

  public function nextEntityCode(string $entityType): string
  {
    $prefix = self::PREFIXES[$entityType] ?? 'E';
    $statement = $this->pdo->prepare(
      "SELECT entity_code FROM teams
       WHERE entity_code LIKE :pattern
       ORDER BY id DESC
       LIMIT 1"
    );
    $statement->execute(['pattern' => $prefix . '-%']);
    $last = (string) ($statement->fetchColumn() ?: '');

    return $prefix . '-' . str_pad((string) ($this->sequence($last) + 1), 3, '0', STR_PAD_LEFT);
  }

  public function nextMemberCode(): string
  {
    $statement = $this->pdo->query(
      "SELECT member_code FROM members
       WHERE member_code LIKE 'M-%'
       ORDER BY id DESC
       LIMIT 1"
    );
    $last = (string) ($statement->fetchColumn() ?: '');

    return 'M-' . str_pad((string) ($this->sequence($last) + 1), 3, '0', STR_PAD_LEFT);
  }

  private function sequence(string $code): int
  {
    if (!preg_match('/-(\d+)$/', $code, $matches)) {
      return 0;
    }

    return (int) $matches[1];
  }
}
