<?php

namespace App\Http\Controllers\Export;

use App\Enums\Status\Export\ExportStatus;
use App\Http\Controllers\Controller;
use App\Models\Export\Export;
use App\Services\Export\ExportCatalog;
use App\Services\Export\ExportException;
use App\Services\Export\ExportService;
use App\Support\Pagination;
use App\Traits\Core\Account\Authorization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * O histórico de exportações da conta.
 *
 * ── Por que o download passa por aqui ─────────────────────────────────────
 *
 * O arquivo fica em disco privado, e a rota confere a conta antes de servir. A alternativa
 * usada num produto vizinho — guardar a URL pública do bucket na linha — significa que
 * qualquer um com o link baixa a base de leads de outra conta, para sempre, sem sessão.
 */
class ExportController extends Controller
{
    use Authorization;

    public function __construct(private readonly ExportService $exports) {}

    public function index(Request $request): JsonResponse
    {
        $exports = Export::forCurrentAccount()
            ->with('user')
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->query('type')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->orderByDesc('created_at')
            ->paginate(Pagination::perPage($request));

        return $this->ok([
            'items' => collect($exports->items())->map(fn (Export $e) => $e->toApi())->all(),
            'meta' => Pagination::meta($exports),
            'types' => ExportCatalog::forUi(),
            'statuses' => ExportStatus::options(),
        ]);
    }

    /**
     * Abre uma exportação com o recorte que a tela estava mostrando.
     *
     * Os filtros chegam soltos em `filters` e são guardados como vieram: é o exportador de
     * cada tipo que decide quais entende. Validar aqui obrigaria este controller a conhecer
     * os filtros de todo tipo que existir — inclusive os que o produto ainda vai escrever.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|string',
            'format' => 'required|string',
            'filters' => 'nullable|array',
        ]);

        try {
            $export = $this->exports->request(
                $request->user()->account_id,
                $request->user()->getKey(),
                $request->string('type')->toString(),
                $request->string('format')->toString(),
                $request->input('filters', []),
            );
        } catch (ExportException $e) {
            return $this->fail($e->getMessage());
        }

        return $this->created($export->toApi());
    }

    public function show(Export $export): JsonResponse
    {
        $this->ensureAccountOwnership($export);

        return $this->ok($export->load('user')->toApi());
    }

    public function download(Export $export): StreamedResponse|JsonResponse
    {
        $this->ensureAccountOwnership($export);

        if (! $export->isDownloadable()) {
            // Três motivos possíveis (não terminou, expirou, sumiu do disco) e uma frase só:
            // para quem está do outro lado, a ação é a mesma — pedir de novo.
            return $this->fail('Esse arquivo não está mais disponível. Peça a exportação de novo.', 410);
        }

        return Storage::disk(ExportCatalog::DISK)->download($export->file_path, $export->file_name);
    }

    /** Apagar é do cliente: o arquivo é dele, e o expurgo automático pode demorar dias. */
    public function destroy(Export $export): JsonResponse
    {
        $this->ensureAccountOwnership($export);

        $export->deleteFile();
        $export->delete();

        return $this->ok(['deleted' => true]);
    }
}
