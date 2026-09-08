<?php

use App\Enums\Status\Export\ExportStatus;
use App\Jobs\Export\RunExportJob;
use App\Models\Billing\Subscription;
use App\Models\Core\Account\Account;
use App\Models\Core\Account\User;
use App\Models\Export\Export;
use App\Services\Export\ExportCatalog;
use App\Services\Export\ExportWriter;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Export\StubExporter;

/**
 * A máquina de exportação do kit.
 *
 * O kit não tem entidade de produto para exportar, então o catálogo real é vazio. A suíte
 * injeta um tipo de mentira (`ExportCatalog::fake`) e exercita o caminho inteiro — é o que
 * garante que quem copiar o kit recebe uma máquina que funciona, e não só arquivos.
 */
beforeEach(function () {
    Storage::fake(ExportCatalog::DISK);

    ExportCatalog::fake([
        'stub' => [
            'label' => 'Usuários',
            'exporter' => StubExporter::class,
            'formats' => ExportCatalog::FORMATS,
            'ttl_days' => 7,
        ],
    ]);

    $this->account = Account::factory()->create();
    Subscription::create(['account_id' => $this->account->id, 'status' => 'active', 'has_access' => true]);
    $this->user = User::factory()->create(['account_id' => $this->account->id]);
    $this->actingAs($this->user, 'api');
});

afterEach(fn () => ExportCatalog::restore());

function runExport(Export $export): Export
{
    (new RunExportJob($export->id))->handle(app(ExportWriter::class));

    return $export->fresh();
}

it('o catálogo nasce vazio: o kit traz a máquina, o produto traz os tipos', function () {
    ExportCatalog::restore();

    expect(ExportCatalog::all())->toBe([]);
});

it('pedir uma exportação cria a linha, guarda o recorte e enfileira', function () {
    Queue::fake();

    $response = $this->postJson('/api/export/exports', [
        'type' => 'stub', 'format' => 'csv', 'filters' => ['search' => 'ana'],
    ])->assertCreated();

    Queue::assertPushed(RunExportJob::class);

    expect($response->json('data.status'))->toBe(ExportStatus::PENDING->value)
        ->and($response->json('data.filters'))->toBe(['search' => 'ana'])
        // Já nasce com prazo: a exportação que falhar nunca chegaria à conclusão que o renova.
        ->and($response->json('data.expires_at'))->not->toBeNull();
});

it('recusa tipo fora do catálogo', function () {
    Queue::fake();

    // O tipo vira nome de classe a instanciar; aceitar qualquer string é deixar o cliente
    // escolher o que roda no servidor.
    $this->postJson('/api/export/exports', ['type' => 'App\\Models\\User', 'format' => 'csv'])
        ->assertStatus(400);

    $this->postJson('/api/export/exports', ['type' => 'stub', 'format' => 'pdf'])
        ->assertStatus(400);

    expect(Export::count())->toBe(0);
});

it('recusa um segundo pedido do mesmo tipo enquanto o primeiro não termina', function () {
    Queue::fake();

    $this->postJson('/api/export/exports', ['type' => 'stub', 'format' => 'csv'])->assertCreated();
    $this->postJson('/api/export/exports', ['type' => 'stub', 'format' => 'csv'])->assertStatus(400);
});

it('gera o CSV com BOM, ponto-e-vírgula e os metadados do arquivo', function () {
    Queue::fake();
    $this->postJson('/api/export/exports', ['type' => 'stub', 'format' => 'csv'])->assertCreated();

    $export = runExport(Export::first());

    expect($export->status)->toBe(ExportStatus::COMPLETED)
        ->and($export->row_count)->toBe(1)
        ->and($export->checksum)->toHaveLength(64)
        ->and($export->isDownloadable())->toBeTrue();

    $csv = Storage::disk(ExportCatalog::DISK)->get($export->file_path);

    // Sem BOM o Excel quebra o acento; sem ';' ele joga tudo numa coluna só.
    expect($csv)->toStartWith("\u{FEFF}")
        ->and($csv)->toContain('Nome;E-mail')
        ->and($csv)->toContain($this->user->email);
});

it('gera XLSX de verdade, não um CSV com outro nome', function () {
    Queue::fake();
    $this->postJson('/api/export/exports', ['type' => 'stub', 'format' => 'xlsx'])->assertCreated();

    $export = runExport(Export::first());

    // XLSX é um zip: os dois primeiros bytes dizem isso.
    expect(substr(Storage::disk(ExportCatalog::DISK)->get($export->file_path), 0, 2))->toBe('PK')
        ->and($export->file_name)->toEndWith('.xlsx');
});

it('o download exige a conta dona e fecha quando o arquivo vence', function () {
    Queue::fake();
    $this->postJson('/api/export/exports', ['type' => 'stub', 'format' => 'csv'])->assertCreated();
    $export = runExport(Export::first());

    $this->get("/api/export/exports/{$export->id}/download")->assertOk();

    $vizinha = Account::factory()->create();
    Subscription::create(['account_id' => $vizinha->id, 'status' => 'active', 'has_access' => true]);
    $this->actingAs(User::factory()->create(['account_id' => $vizinha->id]), 'api');
    $this->get("/api/export/exports/{$export->id}/download")->assertForbidden();

    $this->actingAs($this->user, 'api');
    $export->expires_at = now()->subMinute();
    $export->save();
    $this->get("/api/export/exports/{$export->id}/download")->assertStatus(410);
});

/**
 * A falha precisa chegar à tela.
 *
 * Num produto que serviu de modelo, `error_message` ficou de fora do `$fillable` e o job
 * gravava esse campo: o mass assignment descartava em silêncio, e toda exportação que falhava
 * virava um "Falhou" sem explicação. Por isso as transições aqui são por atribuição direta.
 */
it('a falha guarda a mensagem, e não some no fillable', function () {
    Queue::fake();
    $this->postJson('/api/export/exports', ['type' => 'stub', 'format' => 'csv'])->assertCreated();

    $export = Export::first();
    $export->markFailed('O disco encheu.');

    expect($export->fresh()->error_message)->toBe('O disco encheu.');
});

it('o expurgo apaga o que venceu e os arquivos que ninguém reivindica', function () {
    Queue::fake();
    $this->postJson('/api/export/exports', ['type' => 'stub', 'format' => 'csv'])->assertCreated();
    $export = runExport(Export::first());

    $orfao = $export->account_id.'/sobrou-de-alguma-coisa.csv';
    Storage::disk(ExportCatalog::DISK)->put($orfao, 'a;b');

    $this->artisan('export:purge')->assertSuccessful();
    expect(Export::count())->toBe(1)
        ->and(Storage::disk(ExportCatalog::DISK)->exists($orfao))->toBeFalse();

    $export->expires_at = now()->subDay();
    $export->save();

    $this->artisan('export:purge')->assertSuccessful();
    expect(Export::count())->toBe(0);
});

/**
 * A permissão do disco é decisão, não acidente.
 *
 * O arquivo é escrito pelo worker da fila e lido pelo PHP-FPM — usuários diferentes. Com o
 * padrão do Flysystem a pasta nasce 0700: o FPM não entra, `exists()` responde false, e a
 * tela mostra "Pronto" sem oferecer o download. `Storage::fake` não reproduz isso (mesmo
 * processo, mesmo usuário), então o que se trava aqui é a configuração que resolve.
 */
it('o disco de exportação é legível por outro processo do app', function () {
    $disk = config('filesystems.disks.'.ExportCatalog::DISK);

    expect($disk['driver'])->toBe('local')
        ->and($disk['permissions']['dir']['private'])->toBe(0755)
        ->and($disk['permissions']['file']['private'])->toBe(0644)
        ->and($disk)->not->toHaveKey('url');
});
