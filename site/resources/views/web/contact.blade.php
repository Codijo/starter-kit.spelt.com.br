@extends('layouts.site')

@section('title', 'Contato')
@section('description', 'Fale com a gente — tire dúvidas ou peça uma demonstração.')

@section('content')
    <x-site.section eyebrow="Contato" title="Fale com a gente"
        subtitle="Tire dúvidas, peça uma demonstração ou dê um oi.">
        <div class="grid gap-12 lg:grid-cols-2">
            {{-- Informações --}}
            <div class="space-y-6">
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-ink-soft">E-mail</h3>
                    <a href="mailto:{{ config('site.contact_email') }}" class="mt-1 block text-lg text-ink hover:text-brand">{{ config('site.contact_email') }}</a>
                </div>
                @if (count(config('site.social')))
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-ink-soft">Redes</h3>
                        <ul class="mt-1 space-y-1">
                            @foreach (config('site.social') as $s)
                                <li><a href="{{ $s['url'] }}" class="text-ink-mid hover:text-brand" target="_blank" rel="noopener">{{ $s['label'] }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                <p class="text-sm text-ink-mid">Respondemos normalmente em até um dia útil.</p>
            </div>

            {{-- Formulário --}}
            <div class="rounded-2xl border border-line-soft bg-canvas-raised p-6">
                @if (session('success'))
                    <div class="mb-4 rounded-lg border border-brand/30 bg-brand/10 px-4 py-3 text-sm text-ink">{{ session('success') }}</div>
                @endif

                <form method="POST" action="{{ route('site.contact.store') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label for="name" class="block text-sm font-medium text-ink">Nome</label>
                        <input id="name" name="name" type="text" value="{{ old('name') }}"
                            class="mt-1 w-full rounded-lg border bg-white px-3 py-2 text-sm text-ink focus:border-brand focus:outline-none @error('name') border-red-400 @else border-line @enderror">
                        @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="email" class="block text-sm font-medium text-ink">E-mail</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}"
                            class="mt-1 w-full rounded-lg border bg-white px-3 py-2 text-sm text-ink focus:border-brand focus:outline-none @error('email') border-red-400 @else border-line @enderror">
                        @error('email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="message" class="block text-sm font-medium text-ink">Mensagem</label>
                        <textarea id="message" name="message" rows="5"
                            class="mt-1 w-full rounded-lg border bg-white px-3 py-2 text-sm text-ink focus:border-brand focus:outline-none @error('message') border-red-400 @else border-line @enderror">{{ old('message') }}</textarea>
                        @error('message')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <x-site.button type="submit">Enviar mensagem</x-site.button>
                </form>
            </div>
        </div>
    </x-site.section>
@endsection
