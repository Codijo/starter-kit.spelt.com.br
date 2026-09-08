<?php

namespace Tests\Support\Export;

use App\Models\Core\Account\User;
use App\Services\Export\Exporter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Um exportador de mentira, só para a suíte.
 *
 * O kit não tem entidade de produto para exportar — quem tem é quem o copia. Este stub
 * exporta os usuários da conta, que sempre existem, e serve para exercitar a máquina inteira:
 * arquivo, formato, contagem de linhas, prazo e expurgo.
 *
 * Serve também de exemplo mínimo: três métodos, e nada mais.
 */
class StubExporter extends Exporter
{
    public function headings(): array
    {
        return ['Nome', 'E-mail', 'Criado em'];
    }

    public function query(): Builder
    {
        // Sem `forCurrentAccount()`: o job roda fora de uma requisição. O isolamento vem da
        // própria linha do Export.
        return User::query()
            ->where('account_id', $this->accountId())
            ->when($this->hasFilter('search'), fn (Builder $q) => $q->where('name', 'like', '%'.$this->filter('search').'%'))
            // Ordenação estável: sem ela o chunk pode repetir ou pular linha entre os lotes.
            ->orderBy('id');
    }

    /** @param  User  $row */
    public function map(Model $row): array
    {
        return [$row->name, $row->email, $this->date($row->created_at)];
    }
}
