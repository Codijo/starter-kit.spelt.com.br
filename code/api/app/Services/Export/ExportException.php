<?php

namespace App\Services\Export;

/**
 * Recusa de negócio ao abrir uma exportação — tipo desconhecido, formato indisponível ou
 * pedido repetido. Vira 400 com a mensagem na tela, não 500.
 */
class ExportException extends \RuntimeException {}
