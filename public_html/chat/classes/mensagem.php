<?php
declare(strict_types=1);

class Mensagem
{
    public function __construct(private PDO $pdo) {}

    private function buscarIdPorWaMessageId(string $waMessageId): ?int
    {
        $sql = "SELECT id FROM mensagens WHERE wa_message_id = :wa_message_id LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':wa_message_id' => $waMessageId]);
        $row = $stmt->fetch();

        return $row ? (int) $row['id'] : null;
    }

    public function salvar(
        int $atendimentoId,
        ?string $waMessageId,
        ?string $metaStatus,
        string $direcao,
        string $remetenteTipo,
        ?int $usuarioId,
        string $tipo,
        ?string $conteudo,
        ?string $erroMeta = null,
        ?array $payload = null
    ): int {
        if ($waMessageId) {
            $existente = $this->buscarIdPorWaMessageId($waMessageId);
            if ($existente) {
                return $existente;
            }
        }

        $sql = "INSERT INTO mensagens (
                    atendimento_id,
                    wa_message_id,
                    meta_status,
                    direcao,
                    remetente_tipo,
                    usuario_id,
                    tipo,
                    conteudo,
                    erro_meta,
                    payload_json
                ) VALUES (
                    :atendimento_id,
                    :wa_message_id,
                    :meta_status,
                    :direcao,
                    :remetente_tipo,
                    :usuario_id,
                    :tipo,
                    :conteudo,
                    :erro_meta,
                    :payload_json
                )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':atendimento_id' => $atendimentoId,
            ':wa_message_id' => $waMessageId,
            ':meta_status' => $metaStatus,
            ':direcao' => $direcao,
            ':remetente_tipo' => $remetenteTipo,
            ':usuario_id' => $usuarioId,
            ':tipo' => $tipo,
            ':conteudo' => $conteudo,
            ':erro_meta' => $erroMeta,
            ':payload_json' => $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function listarPorAtendimento(int $atendimentoId): array
    {
        $sql = "SELECT m.*, u.nome AS usuario_nome
                FROM mensagens m
                LEFT JOIN usuarios u ON u.id = m.usuario_id
                WHERE m.atendimento_id = :atendimento_id
                ORDER BY m.id ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':atendimento_id' => $atendimentoId]);

        return $stmt->fetchAll();
    }

    public function atualizarStatusPorWaMessageId(string $waMessageId, string $status, ?string $erroMeta = null): void
    {
        $sql = "UPDATE mensagens
                SET meta_status = :status,
                    erro_meta = :erro_meta
                WHERE wa_message_id = :wa_message_id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':status' => $status,
            ':erro_meta' => $erroMeta,
            ':wa_message_id' => $waMessageId,
        ]);
    }
}