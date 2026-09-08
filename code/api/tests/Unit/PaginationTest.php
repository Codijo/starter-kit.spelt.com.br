<?php

use App\Support\Pagination;
use Illuminate\Http\Request;

$ask = fn (?string $value) => Pagination::perPage(
    Request::create('/', 'GET', $value === null ? [] : ['per_page' => $value])
);

it('usa dez por página quando ninguém pede outra coisa', function () use ($ask) {
    expect($ask(null))->toBe(10)
        ->and(Pagination::PER_PAGE)->toBe(10);
});

it('respeita o que o cliente pede, até o teto', function () use ($ask) {
    expect($ask('25'))->toBe(25)
        ->and($ask('100'))->toBe(100)
        // Sem teto, `?per_page=999999` viraria uma varredura da tabela inteira.
        ->and($ask('999999'))->toBe(Pagination::MAX_PER_PAGE);
});

it('valor torto na URL cai no padrão, e não em "traga tudo"', function () use ($ask) {
    // O paginador do Laravel trata `0` como "sem limite" — de URL, isso é uma porta aberta.
    expect($ask('0'))->toBe(10)
        ->and($ask('-5'))->toBe(10)
        ->and($ask('abacaxi'))->toBe(10);
});
