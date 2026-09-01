<?php

namespace App\Services\Ai\Exceptions;

use RuntimeException;

/**
 * Falha na camada de IA (driver não configurado, erro do fornecedor, resposta
 * malformada). O chamador decide o fallback (ex.: engine do produto cai num stub).
 */
class AiException extends RuntimeException
{
}
