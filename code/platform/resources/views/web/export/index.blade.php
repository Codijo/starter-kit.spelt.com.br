@extends('layouts.guard')

@section('title', 'Exportações')

@section('content')
    <div x-data="exportsIndex()" x-init="load()" @destroy="stop()">
        <x-layout.breadcrumb :items="[['label' => 'Exportações', 'url' => route('export.index')]]" />

        <div class="mt-4">
            <x-layout.title title="Exportações"
                subtitle="Os arquivos que você pediu. Cada um fica disponível por alguns dias e depois é apagado.">
                <template x-if="hasOpen()">
                    <span class="inline-flex items-center gap-2 rounded-md bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700">
                        <x-ui.spinner size="xs" />
                        Gerando
                    </span>
                </template>
            </x-layout.title>
        </div>

        <div class="mt-6 flex flex-wrap gap-3">
            <select x-model="type" @change="reload()"
                class="rounded-md border border-border-muted px-3 py-2 text-sm focus:border-accent-blue focus:outline-none focus:ring-2 focus:ring-accent-blue/20">
                <option value="">Todos os tipos</option>
                <template x-for="t in types" :key="t.value">
                    <option :value="t.value" x-text="t.label"></option>
                </template>
            </select>

            <select x-model="status" @change="reload()"
                class="rounded-md border border-border-muted px-3 py-2 text-sm focus:border-accent-blue focus:outline-none focus:ring-2 focus:ring-accent-blue/20">
                <option value="">Todas as situações</option>
                <template x-for="s in statuses" :key="s.value">
                    <option :value="s.value" x-text="s.label"></option>
                </template>
            </select>
        </div>

        <div x-show="loading" class="mt-8 text-sm text-linear-gray-dark">Carregando…</div>

        <template x-if="!loading && items.length === 0">
            <div x-cloak class="mt-8 rounded-lg border border-dashed border-border-muted bg-canvas-white/40 p-10 text-center">
                <p class="text-sm font-medium text-linear-gray-dark">Nenhuma exportação ainda.</p>
                <p class="mt-1 text-sm text-linear-gray-light">
                    Use o botão <span class="font-medium">Exportar</span> nas telas de Leads, Contatos ou Fluxo de Venda.
                    O arquivo sai com o recorte que estiver filtrado.
                </p>
            </div>
        </template>

        <template x-if="!loading && items.length > 0">
            <div x-cloak class="mt-6 overflow-hidden rounded-lg border border-border-light bg-canvas-white shadow-subtle">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-steel-gray">
                        <thead class="bg-subtle-ash text-xs uppercase text-steel-gray">
                            <tr>
                                <th scope="col" class="px-4 py-3">Arquivo</th>
                                <th scope="col" class="px-4 py-3">Situação</th>
                                <th scope="col" class="px-4 py-3">Linhas</th>
                                <th scope="col" class="px-4 py-3">Pedido</th>
                                <th scope="col" class="px-4 py-3">Validade</th>
                                <th scope="col" class="px-4 py-3 text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="item in items" :key="item.id">
                                <tr class="border-t border-border-light hover:bg-subtle-ash/40">
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <span class="font-medium text-ink-black" x-text="item.type_label"></span>
                                            <span class="rounded bg-subtle-ash px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-steel-gray"
                                                x-text="item.format"></span>
                                        </div>
                                        <div class="text-xs text-linear-gray-light" x-text="item.file_name || '—'"></div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="{
                                                'bg-subtle-ash text-steel-gray': item.status_color === 'gray',
                                                'bg-blue-50 text-blue-700': item.status_color === 'blue',
                                                'bg-green-100 text-green-800': item.status_color === 'green',
                                                'bg-red-50 text-red-700': item.status_color === 'red',
                                            }"
                                            x-text="item.status_label"></span>
                                        {{-- A mensagem da falha vem da API e fica à vista: "Falhou" sem
                                             motivo obriga o cliente a abrir chamado para saber o quê. --}}
                                        <template x-if="item.error_message">
                                            <div class="mt-1 max-w-xs text-xs text-red-600" x-text="item.error_message"></div>
                                        </template>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span x-text="item.row_count ?? '—'"></span>
                                        <span class="text-xs text-linear-gray-light" x-text="item.file_size ? ` · ${fileSize(item.file_size)}` : ''"></span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-xs">
                                        <div x-text="formatDateTime(item.created_at)"></div>
                                        <div class="text-linear-gray-light">
                                            <span x-text="item.requested_by || ''"></span>
                                            {{-- Quantos filtros o pedido levava: é o que explica um arquivo
                                                 de 12 linhas numa conta com 900. --}}
                                            <span x-show="filterCount(item) > 0"
                                                x-text="`${item.requested_by ? ' · ' : ''}${filterCount(item)} filtro(s)`"></span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-xs"
                                        :class="item.expired ? 'text-red-600' : 'text-linear-gray-light'"
                                        x-text="expiryLabel(item)"></td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">
                                        <template x-if="item.downloadable">
                                            <button type="button" @click="download(item)"
                                                class="text-xs font-medium text-accent-blue hover:underline">Baixar</button>
                                        </template>
                                        <button type="button" @click="confirmRemove(item)" :disabled="removing === item.id"
                                            class="ml-3 text-xs font-medium text-red-600 hover:underline disabled:opacity-50">Excluir</button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <x-shared.pagination label="exportações" />
            </div>
        </template>
    </div>

    @vite('resources/views/web/export/index.js')
@endsection
