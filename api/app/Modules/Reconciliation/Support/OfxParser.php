<?php

namespace App\Modules\Reconciliation\Support;

class OfxParser
{
    /**
     * Extrai as transações (STMTTRN) de um arquivo OFX (1.x ou 2.x).
     *
     * @return list<array{type: string, date: ?string, value: float, description: ?string, transaction_id: ?string}>
     */
    public function parse(string $content): array
    {
        $content = $this->normalize($content);

        // Aceita OFX 1.x (SGML) e 2.x (XML): STMTTRN pode ou não ser fechado.
        if (! preg_match_all('/<STMTTRN>(.*?)(?=<\/STMTTRN>|<STMTTRN>|<\/BANKTRANLIST>|$)/is', $content, $matches)) {
            return [];
        }

        $transactions = [];

        foreach ($matches[1] as $block) {
            $transactions[] = $this->parseBlock($block);
        }

        return $transactions;
    }

    private function normalize(string $content): string
    {
        $pos = stripos($content, '<OFX');

        return $pos !== false ? substr($content, $pos) : $content;
    }

    /**
     * @return array{type: string, date: ?string, value: float, description: ?string, transaction_id: ?string}
     */
    private function parseBlock(string $block): array
    {
        $type = strtoupper((string) $this->value($block, 'TRNTYPE'));
        $amount = (float) ($this->value($block, 'TRNAMT') ?? 0);

        return [
            'type' => str_starts_with($type, 'CREDIT') ? 'credit' : (str_starts_with($type, 'DEBIT') ? 'debit' : 'other'),
            'date' => $this->parseDate($this->value($block, 'DTPOSTED')),
            'value' => round(abs($amount), 2),
            'description' => $this->value($block, 'MEMO') ?? $this->value($block, 'NAME'),
            'transaction_id' => $this->value($block, 'FITID'),
        ];
    }

    private function value(string $block, string $tag): ?string
    {
        $tag = preg_quote($tag, '/');

        // OFX 1.x (SGML) costuma não fechar as tags internas; OFX 2.x (XML) fecha.
        // Captura o conteúdo até o fechamento, até a próxima tag ou até o fim do bloco.
        if (preg_match('/<'.$tag.'>(.*?)(?:<\/'.$tag.'>|(?=<)|$)/is', $block, $m)) {
            return trim($m[1]);
        }

        return null;
    }

    private function parseDate(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (preg_match('/^(\d{4})(\d{2})(\d{2})/', $value, $m)) {
            return "{$m[1]}-{$m[2]}-{$m[3]}";
        }

        return null;
    }
}
