<?php

declare(strict_types=1);

/**
 * Manda ao Fin Pro (outro sistema do dono) as notas pagas por Pix ou Transferência, para conferir no extrato do banco.
 * Contrato: docs/superpowers/specs/2026-10-23-integracao-finpro-design.md. Cada ordem tem um registro em finpro_envios;
 * o envio é tentado na hora e, se falhar, de novo nas consultas que o Monitor e o Painel fazem sozinhos.
 */
final class FinPro
{
    private const FORMAS = ['pix', 'transferencia'];

    public static function configurado(): bool
    {
        return env_str('FINPRO_URL') !== '' && env_str('FINPRO_TOKEN') !== '';
    }

    /** Vai para o Fin Pro: a ordem principal (não as viagens seguintes do fracionado), não cancelada, Pix ou Transferência. */
    public static function elegivel(array $ordem): bool
    {
        return (int) ($ordem['ordem_principal_id'] ?? 0) === 0
            && ($ordem['status'] ?? '') !== 'cancelado'
            && in_array($ordem['nf_pagamento'] ?? null, self::FORMAS, true);
    }

    /** Põe a ordem na fila (ou de volta nela, para mandar os dados novos); false se ela não vai para o Fin Pro. */
    public static function enfileirar(int $ordemId): bool
    {
        $ordem = Operacoes::buscar($ordemId);
        if ($ordem === null || !self::elegivel($ordem)) {
            return false;
        }
        Db::pdo()->prepare(
            "INSERT INTO finpro_envios (ordem_id, situacao, tentativas, proxima_tentativa) VALUES (?, 'pendente', 0, NOW())
             ON DUPLICATE KEY UPDATE situacao = 'pendente', tentativas = 0, ultimo_erro = NULL, proxima_tentativa = NOW()"
        )->execute([$ordemId]);

        return true;
    }

    /** Tenta mandar a ordem agora; true se o Fin Pro aceitou. */
    public static function enviar(int $ordemId): bool
    {
        if (!self::configurado()) {
            return false;
        }

        // Um envio por ordem de cada vez: a ficha e a rodada da fila não mandam a mesma nota juntas.
        return (bool) Db::comTrava('finpro:' . $ordemId, static fn (): bool => self::enviarSemCorrida($ordemId));
    }

    /**
     * Rodada da fila: no máximo uma a cada 5 minutos para o sistema todo e 3 notas por vez,
     * para a consulta do Monitor não ficar presa esperando o Fin Pro.
     */
    public static function tentarPendentes(): void
    {
        if (!self::configurado()) {
            return;
        }
        $pdo = Db::pdo();
        // Só um pedido ganha a rodada: o que conseguiu mudar a marca (0 linhas afetadas = rodada recente).
        $rodada = $pdo->prepare(
            'INSERT INTO finpro_rodada (id, rodada_em) VALUES (1, NOW())
             ON DUPLICATE KEY UPDATE rodada_em = IF(rodada_em <= NOW() - INTERVAL 5 MINUTE, NOW(), rodada_em)'
        );
        $rodada->execute();
        if ($rodada->rowCount() === 0) {
            return;
        }
        $ids = $pdo->query(
            "SELECT ordem_id FROM finpro_envios
             WHERE situacao = 'pendente' AND proxima_tentativa <= NOW()
             ORDER BY proxima_tentativa ASC LIMIT 3"
        )->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ids as $id) {
            self::enviar((int) $id);
        }
    }

    /** O que a ficha da ordem mostra; null quando a ordem não tem nada com o Fin Pro. */
    public static function situacaoDa(int $ordemId): ?string
    {
        $comando = Db::pdo()->prepare('SELECT situacao, ultimo_erro, enviado_em FROM finpro_envios WHERE ordem_id = ?');
        $comando->execute([$ordemId]);
        $envio = $comando->fetch();
        if (!$envio) {
            $ordem = Operacoes::buscar($ordemId);
            if ($ordem === null || !self::elegivel($ordem)) {
                return null;
            }

            return self::configurado() ? 'Ainda não enviada ao Fin Pro' : 'Fin Pro não configurado';
        }
        if ($envio['situacao'] === 'enviado') {
            return 'Enviada ao Fin Pro em ' . date('d/m H:i', (int) strtotime((string) $envio['enviado_em']));
        }
        if (!self::configurado()) {
            return 'Fin Pro não configurado';
        }
        $motivo = (string) ($envio['ultimo_erro'] ?? '');
        if ($envio['situacao'] === 'erro') {
            return 'Não vai para o Fin Pro: ' . $motivo;
        }

        return 'Esperando envio ao Fin Pro' . ($motivo !== '' ? ': ' . $motivo : '');
    }

    private static function enviarSemCorrida(int $ordemId): bool
    {
        $pdo = Db::pdo();
        $ordem = Operacoes::buscar($ordemId);
        if ($ordem === null || !self::elegivel($ordem)) {
            // Cancelada ou com outra forma antes de sair: não tenta mais.
            $pdo->prepare("UPDATE finpro_envios SET situacao = 'erro', ultimo_erro = ? WHERE ordem_id = ? AND situacao = 'pendente'")
                ->execute(['a nota foi cancelada ou não é mais Pix nem Transferência.', $ordemId]);

            return false;
        }

        $erro = self::postar(self::campos($ordem));
        if ($erro === null) {
            $pdo->prepare(
                "UPDATE finpro_envios SET situacao = 'enviado', tentativas = tentativas + 1, ultimo_erro = NULL, enviado_em = NOW()
                 WHERE ordem_id = ?"
            )->execute([$ordemId]);

            return true;
        }
        // Espera 5, 10, 20, 40 e 80 minutos e depois a cada 2 horas (a conta usa as tentativas de antes desta).
        $pdo->prepare(
            "UPDATE finpro_envios
             SET situacao = 'pendente', ultimo_erro = ?,
                 proxima_tentativa = NOW() + INTERVAL LEAST(5 << LEAST(tentativas, 5), 120) MINUTE,
                 tentativas = tentativas + 1
             WHERE ordem_id = ?"
        )->execute([mb_substr($erro, 0, 300), $ordemId]);

        return false;
    }

    /** Os campos do contrato, montados da ordem. */
    private static function campos(array $ordem): array
    {
        $campos = [
            'origem_id' => (string) $ordem['id'],
            'nota' => (string) $ordem['nf'],
            'pedido' => (string) $ordem['pedido'],
            'empresa' => (string) $ordem['cliente'],
            'valor' => $ordem['nf_valor'] === null ? '' : (string) $ordem['nf_valor'],
            'material' => (string) ($ordem['material_nome'] ?? ''),
            // O mesmo dia em que a nota conta no vendido (Operacoes::vendas).
            'emitida_em' => (string) ($ordem['nf_emitida_em'] ?: ($ordem['nf_emitida_data'] ?: substr((string) $ordem['data'], 0, 10))),
            'forma' => (string) $ordem['nf_pagamento'],
            'chave' => (string) ($ordem['nf_chave'] ?? ''),
        ];
        $pdf = NotaFiscal::arquivoPdf($ordem);
        if ($pdf !== null) {
            $campos['arquivo'] = new CURLFile($pdf, 'application/pdf', 'nota-' . $ordem['id'] . '.pdf');
        }

        return $campos;
    }

    /** Faz o POST; null se o Fin Pro aceitou (200 ou 201), senão o motivo para a ficha. O token nunca entra no motivo. */
    private static function postar(array $campos): ?string
    {
        if (!function_exists('curl_init')) {
            return 'o PHP do servidor está sem a extensão cURL.';
        }
        $curl = curl_init(rtrim(env_str('FINPRO_URL'), '/') . '/api/integracao/operacoes/notas');
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $campos,
            // "Expect:" vazio: sem ele o cURL espera até 1 s pelo "100 Continue" antes de mandar o PDF.
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . env_str('FINPRO_TOKEN'), 'Accept: application/json', 'Expect:'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT => 8,
        ]);
        $corpo = curl_exec($curl);
        if ($corpo === false) {
            return 'o Fin Pro não respondeu (' . curl_error($curl) . ').';
        }
        $codigo = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        if ($codigo === 200 || $codigo === 201) {
            return null;
        }
        $resposta = json_decode((string) $corpo, true);
        $detalhe = match ($codigo) {
            401 => 'a chave FINPRO_TOKEN não confere com a OPERACOES_TOKEN do Fin Pro',
            503 => 'a integração está desligada no Fin Pro (falta a OPERACOES_TOKEN)',
            default => is_array($resposta) && is_string($resposta['erro'] ?? null) ? $resposta['erro'] : '',
        };

        return 'o Fin Pro respondeu ' . $codigo . ($detalhe !== '' ? ' (' . $detalhe . ')' : '') . '.';
    }
}
