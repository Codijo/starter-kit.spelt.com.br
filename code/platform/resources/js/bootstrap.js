// Carga dos módulos globais do Platform (enxuto — Alpine + axios + Flowbite).
// `helpers` ANTES do alpine: os componentes x-shared.* usam copyText/formatDate
// direto nas expressões, avaliadas no escopo global.
import './modules/axios';
import './modules/helpers';
import 'flowbite';
import './modules/alpine';
