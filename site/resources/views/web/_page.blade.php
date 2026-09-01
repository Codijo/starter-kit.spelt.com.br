{{--
  PADRÃO DE PÁGINA ESPECÍFICA — copie este arquivo e crie a sua.

  1) Copie para  resources/views/web/<slug>.blade.php  e ajuste o conteúdo.
  2) Registre a rota em  routes/web/Page.php :
         Route::view('/<slug>', 'web.<slug>')->name('site.<slug>');
     (Se a página precisar de dados/lógica, troque Route::view por um Controller.)
  3) Se ela aparece no menu, adicione em  config/site.php  → 'nav'.

  Tudo do layout (header, footer, SEO/OG) vem de graça pelo @extends abaixo.
--}}
@extends('layouts.site')

@section('title', 'Título da página')
@section('description', 'Descrição curta para SEO e redes sociais.')

@section('content')
    <x-site.section eyebrow="Seção" title="Título grande" subtitle="Subtítulo opcional.">
        <div class="max-w-3xl space-y-6 text-lg leading-relaxed text-ink-mid">
            <p>Conteúdo da página. Use os componentes <code>&lt;x-site.*&gt;</code> para manter o padrão.</p>
        </div>
    </x-site.section>
@endsection
