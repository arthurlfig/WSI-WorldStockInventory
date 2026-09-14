<?php

/** Sinaliza que a validação dos campos específicos de uma categoria de produto falhou. */

class ValidacaoException extends Exception
{
    /** @var string[] */
    private array $erros;

    /** @param string[] $erros */

    public function __construct(array $erros)
    {
        parent::__construct('Falha de validação: ' . implode(' | ', $erros));
        $this->erros = $erros;
    }

    /** @return string[] */

    public function getErros(): array
    {
        return $this->erros;
    }
}