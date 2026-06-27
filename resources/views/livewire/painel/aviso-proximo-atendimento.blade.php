{{-- Checa o próximo atendimento do profissional logado começando em ≤ 15 min e dispara
     UM toast (idempotente por sessão). `wire:init` faz a 1ª checagem assim que a página
     REALMENTE carrega no cliente (não em telas que redirecionam no mount); `wire:poll`
     repete a cada 60s. Sem WebSocket; só lê a agenda. D69. Sem UI própria — o aviso é o
     toast do Flux (no layout). --}}
<div wire:init="verificar" wire:poll.60s="verificar"></div>
