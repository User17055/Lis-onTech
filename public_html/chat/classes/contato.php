<?php
declare(strict_types=1);

class Contato
{
    public function __construct(private PDO $pdo) {}

    private function limparTelefone(string $telefone): string
    {
        return preg_replace('/\D+/', '', $telefone) ?? '';
    }

    public function buscarPorId(int $id): ?array
    {
        $sql = "SELECT * FROM contatos WHERE id = :id LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function buscarPorTelefone(string $telefone): ?array
    {
        $telefone = $this->limparTelefone($telefone);

        $sql = "SELECT * FROM contatos WHERE telefone = :telefone LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':telefone' => $telefone]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function buscarOuCriar(string $telefone, ?string $nome = null, ?string $waId = null): int
    {
        $telefone = $this->limparTelefone($telefone);
        $contato = $this->buscarPorTelefone($telefone);

        if ($contato) {
            $sql = "UPDATE contatos
                    SET nome = COALESCE(:nome, nome),
                        wa_id = COALESCE(:wa_id, wa_id)
                    WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':nome' => $nome ?: null,
                ':wa_id' => $waId ?: null,
                ':id' => $contato['id'],
            ]);

            return (int) $contato['id'];
        }

        $sql = "INSERT INTO contatos (nome, telefone, wa_id)
                VALUES (:nome, :telefone, :wa_id)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':nome' => $nome ?: 'Sem nome',
            ':telefone' => $telefone,
            ':wa_id' => $waId ?: null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}