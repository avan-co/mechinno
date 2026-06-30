<?php

declare(strict_types=1);

final class Workflow
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function approveMember(int $id): array
    {
        $row = $this->memberRow($id);
        if (($row['approval_status'] ?? '') !== 'pending') {
            throw new InvalidArgumentException('این عضو در انتظار تأیید نیست.');
        }

        $today = JalaliDate::todayParts()['formatted'];
        $this->pdo->prepare(
            "UPDATE members SET approval_status = 'approved', reviewed_at = :reviewed_at, rejection_reason = NULL WHERE id = :id"
        )->execute(['reviewed_at' => $today, 'id' => $id]);

        return $this->fetchMember($id);
    }

    public function rejectMember(int $id, string $reason = ''): array
    {
        $row = $this->memberRow($id);
        if (($row['approval_status'] ?? '') !== 'pending') {
            throw new InvalidArgumentException('این عضو در انتظار تأیید نیست.');
        }

        $today = JalaliDate::todayParts()['formatted'];
        $this->pdo->prepare(
            "UPDATE members SET approval_status = 'rejected', reviewed_at = :reviewed_at, rejection_reason = :reason WHERE id = :id"
        )->execute([
            'reviewed_at' => $today,
            'reason' => $reason !== '' ? $reason : null,
            'id' => $id,
        ]);

        return $this->fetchMember($id);
    }

    public function approvePayment(int $id): array
    {
        $row = $this->transactionRow($id);
        if (($row['category'] ?? '') !== 'واریز تیم') {
            throw new InvalidArgumentException('فقط اعلام واریز نهاد قابل تأیید است.');
        }
        if (($row['payment_status'] ?? '') !== 'pending') {
            throw new InvalidArgumentException('این واریز در انتظار تأیید نیست.');
        }

        $today = JalaliDate::todayParts()['formatted'];
        $this->pdo->prepare(
            "UPDATE transactions SET payment_status = 'approved', confirmed = 1, reviewed_at = :reviewed_at WHERE id = :id"
        )->execute(['reviewed_at' => $today, 'id' => $id]);

        return $this->fetchTransaction($id);
    }

    public function rejectPayment(int $id, string $reason = ''): array
    {
        $row = $this->transactionRow($id);
        if (($row['category'] ?? '') !== 'واریز تیم') {
            throw new InvalidArgumentException('فقط اعلام واریز نهاد قابل رد است.');
        }
        if (($row['payment_status'] ?? '') !== 'pending') {
            throw new InvalidArgumentException('این واریز در انتظار تأیید نیست.');
        }

        $today = JalaliDate::todayParts()['formatted'];
        $note = trim($reason);
        $existing = trim((string) ($row['notes'] ?? ''));
        if ($note !== '') {
            $note = ($existing !== '' ? $existing . ' — ' : '') . 'رد: ' . $note;
        } else {
            $note = $existing !== '' ? $existing : null;
        }

        $this->pdo->prepare(
            "UPDATE transactions SET payment_status = 'rejected', confirmed = 0, notes = :notes, reviewed_at = :reviewed_at WHERE id = :id"
        )->execute(['notes' => $note, 'reviewed_at' => $today, 'id' => $id]);

        return $this->fetchTransaction($id);
    }

    /**
     * @return array<string, mixed>
     */
    private function memberRow(int $id): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM members WHERE id = :id');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        if ($row === false) {
            throw new InvalidArgumentException('عضو پیدا نشد.');
        }

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchMember(int $id): array
    {
        return (new Crud($this->pdo))->find('members', $id);
    }

    /**
     * @return array<string, mixed>
     */
    private function transactionRow(int $id): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM transactions WHERE id = :id');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        if ($row === false) {
            throw new InvalidArgumentException('تراکنش پیدا نشد.');
        }

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchTransaction(int $id): array
    {
        return (new Crud($this->pdo))->find('transactions', $id);
    }
}
