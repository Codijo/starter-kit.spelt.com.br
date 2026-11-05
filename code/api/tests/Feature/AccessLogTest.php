<?php

use App\Models\Core\Account\AccessLog;
use App\Models\Core\Account\Account;
use App\Models\Core\Account\User;
use Illuminate\Support\Facades\File;

/**
 * Registro de acesso (Marco Civil, art. 15): data, hora e IP de uso da API autenticada, por 7 meses.
 * O login é do Spelt; aqui é o uso.
 */
const CLOUDFLARE_EDGE = '162.158.10.20';

const COOLIFY_PROXY = '10.0.1.5';

function accessUser(): User
{
    return User::factory()->create(['account_id' => Account::factory()->create()->id]);
}

/**
 * Uma requisição autenticada como chega em produção: o proxy do Coolify abre a conexão, apaga o
 * X-Forwarded-For e escreve a borda do Cloudflare; o IP do cliente sobrevive no CF-Connecting-IP.
 */
function hitApi(User $user, string $clientIp, string $edge = CLOUDFLARE_EDGE, array $headers = [])
{
    return test()->actingAs($user, 'api')
        ->withServerVariables(['REMOTE_ADDR' => COOLIFY_PROXY])
        ->withHeaders([
            'X-Forwarded-For' => $edge,
            'X-Real-Ip' => $edge,
            'CF-Connecting-IP' => $clientIp,
            'User-Agent' => 'Mozilla/5.0 Teste',
        ] + $headers)
        ->getJson('/api/me');
}

it('requisição autenticada grava o IP real do cliente, não o do Cloudflare', function () {
    $user = accessUser();

    hitApi($user, '200.150.10.5')->assertOk();

    $log = AccessLog::sole();
    expect($log->ip)->toBe('200.150.10.5')
        ->and($log->account_id)->toBe($user->account_id)
        ->and($log->email)->toBe($user->email)
        ->and($log->user_agent)->toBe('Mozilla/5.0 Teste')
        ->and($log->port)->toBe(0)
        ->and($log->date->toDateString())->toBe(now()->toDateString());
});

it('fora das faixas do Cloudflare, o cabeçalho é ignorado: o cliente não escolhe o próprio IP', function () {
    hitApi(accessUser(), '1.2.3.4', edge: '187.40.1.1')->assertOk();

    expect(AccessLog::sole()->ip)->toBe('187.40.1.1');
});

it('uma linha por usuário, IP e dia, com o primeiro e o último uso', function () {
    $user = accessUser();
    $start = now()->startOfDay()->addHours(9);
    $this->travelTo($start);

    hitApi($user, '200.150.10.5');
    $this->travelTo($start->copy()->addSeconds(30));
    hitApi($user, '200.150.10.5');                 // mesmo minuto: não toca o banco

    expect(AccessLog::sole()->last_seen_at->toDateTimeString())->toBe($start->toDateTimeString());

    $this->travelTo($start->copy()->addHours(3));
    hitApi($user, '200.150.10.5');
    hitApi($user, '177.20.30.40');                 // outra rede: outra linha
    $this->travelTo($start->copy()->addDay());
    hitApi($user, '200.150.10.5');                 // outro dia: outra linha

    $rows = AccessLog::orderBy('first_seen_at')->get();

    expect($rows)->toHaveCount(3)
        ->and($rows[0]->first_seen_at->toDateTimeString())->toBe($start->toDateTimeString())
        ->and($rows[0]->last_seen_at->toDateTimeString())->toBe($start->copy()->addHours(3)->toDateTimeString())
        ->and($rows[1]->ip)->toBe('177.20.30.40')
        ->and($rows[2]->date->toDateString())->toBe($start->copy()->addDay()->toDateString());
});

it('com o cabeçalho da porta configurado, cada porta vira uma linha', function () {
    config(['access-log.port_header' => 'X-Client-Port']);
    $user = accessUser();

    hitApi($user, '200.150.10.5', headers: ['X-Client-Port' => '40123']);
    hitApi($user, '200.150.10.5', headers: ['X-Client-Port' => '51877']);

    expect(AccessLog::orderBy('port')->pluck('port')->all())->toBe([40123, 51877]);
});

it('sem autenticação nada é gravado', function () {
    $this->withServerVariables(['REMOTE_ADDR' => CLOUDFLARE_EDGE])->getJson('/api/me')->assertUnauthorized();

    expect(AccessLog::count())->toBe(0);
});

it('o registro sobrevive à remoção do usuário, porque a obrigação é de 7 meses', function () {
    $user = accessUser();
    hitApi($user, '200.150.10.5');

    $user->delete();

    expect(AccessLog::sole()->email)->toBe($user->email);
});

it('o prune apaga só o que passou de 7 meses', function () {
    $user = accessUser();
    $this->travelTo(now()->subMonths(AccessLog::RETENTION_MONTHS)->subDays(2));
    hitApi($user, '10.0.0.1', edge: '10.0.0.1');
    $this->travelBack();
    $this->travelTo(now()->subMonths(AccessLog::RETENTION_MONTHS)->addDays(2));
    hitApi($user, '10.0.0.2', edge: '10.0.0.2');
    $this->travelBack();

    $this->artisan('model:prune', ['--model' => [AccessLog::class]])->assertExitCode(0);

    expect(AccessLog::pluck('ip')->all())->toBe(['10.0.0.2']);
});

it('o comando consulta por e-mail e exige um filtro', function () {
    $user = accessUser();
    hitApi($user, '200.150.10.5');

    $this->artisan('account:access-log')->assertExitCode(2);
    $this->artisan('account:access-log', ['--email' => $user->email])
        ->expectsOutputToContain('200.150.10.5')
        ->assertSuccessful();
});

it('nenhum controller da API lê a tabela', function () {
    // Sigilo (art. 15): a consulta é só pelo comando.
    $readers = collect(File::allFiles(app_path('Http/Controllers')))
        ->filter(fn ($file) => str_contains($file->getContents(), 'AccessLog'));

    expect($readers->map->getRelativePathname()->values()->all())->toBe([]);
});
