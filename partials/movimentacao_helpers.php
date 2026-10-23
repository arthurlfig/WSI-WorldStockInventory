<?php
/*
 * Funções de apoio para as telas de movimentação de estoque e vendas
 * (editar motivo, estornar, cancelar venda).
 */

/** Prefixos de motivo gerados pelo próprio sistema (servem de vínculo entre registros). */
const MOTIVOS_RESERVADOS = ['Venda #', 'Estorno da movimentação #', 'Cancelamento da venda #'];

/**
 * Movimentações geradas por venda, estorno ou cancelamento não podem ter o
 * motivo alterado nem ser estornadas à mão: elas são controladas pelas
 * telas de venda (Cancelar venda) e de estorno.
 */
function motivoReservado(?string $motivo): bool
{
    foreach (MOTIVOS_RESERVADOS as $prefixo) {
        if (strncmp((string)$motivo, $prefixo, strlen($prefixo)) === 0) {
            return true;
        }
    }
    return false;
}

/** Marca gravada no motivo do estorno, usada para saber se uma movimentação já foi estornada. */
function marcaEstorno(int $movimentacaoId): string
{
    return 'Estorno da movimentação #' . $movimentacaoId . ':';
}
