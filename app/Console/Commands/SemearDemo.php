<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Agendamento;
use App\Models\Avaliacao;
use App\Models\CategoriaProduto;
use App\Models\Cliente;
use App\Models\ComissaoProfissional;
use App\Models\KanbanCartao;
use App\Models\KanbanQuadro;
use App\Models\Produto;
use App\Models\Servico;
use App\Models\Tenant;
use App\Models\Unidade;
use App\Models\User;
use App\Models\Venda;
use App\Services\Estoque\MovimentadorEstoque;
use App\Services\Venda\Comanda;
use App\Support\Aparencia;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Popula um cenário de DEMONSTRAÇÃO no banco de um tenant (uso local de teste):
 * unidade, serviços, profissionais (com serviços e horários), gerente/recepção,
 * clientes e agendamentos em status variados.
 *
 * Idempotente: catálogo/usuários/clientes via firstOrCreate; agendamentos só são
 * criados se ainda não houver agendamentos de demo (marcados em `observacoes`).
 * Use --recriar para refazer apenas os agendamentos de demo.
 *
 * Não é para produção. A senha padrão é de demonstração (--senha para trocar).
 */
class SemearDemo extends Command
{
    protected $signature = 'nextgest:demo
                            {tenant : slug/id do tenant}
                            {--senha=password : senha de demo dos logins criados}
                            {--recriar : recria os agendamentos de demonstração}';

    protected $description = 'Popula um cenário de demonstração no tenant (local)';

    private const MARCA_DEMO = '[demo]';

    public function handle(): int
    {
        $slug = $this->argument('tenant');
        $tenant = Tenant::find($slug);

        // Cria o estabelecimento se ainda não existir (dispara banco/migrations/seed).
        if (! $tenant) {
            $tenant = Tenant::create([
                'id' => $slug,
                'nome' => Str::of($slug)->headline(),
                'slug' => $slug,
                'ativo' => true,
            ]);
            $this->info("Estabelecimento criado: {$slug}");
        }

        $senha = (string) $this->option('senha');

        $tenant->run(function () use ($senha) {
            $unidade = $this->unidade();
            $servicos = $this->servicos($unidade);
            $profissionais = $this->profissionais($unidade, $servicos, $senha);
            $this->equipeDeApoio($unidade, $senha);
            $this->dono($senha);
            Aparencia::salvar([]); // grava o tema padrão (base editável)
            $this->catalogoProdutos($unidade);
            $this->comissoesPersonalizadas($servicos, $profissionais);
            $clientes = $this->clientes($senha);
            $this->agendamentos($unidade, $servicos, $profissionais, $clientes);
            $this->avaliacoes();
            $this->comandas($unidade, $servicos, $profissionais, $clientes);
            $this->historicoVendas($unidade, $profissionais);
            $this->backfillPagamentos($profissionais);
            $this->kanban($clientes, $profissionais);
        });

        $this->newLine();
        $this->info("Cenário de demonstração pronto em /{$slug} (senha de todos: {$senha}).");
        $this->line('  Dono (painel):     dono@demo.test');
        $this->line('  Gerente (painel):  gerente@demo.test');
        $this->line('  Profissional:      jorge@demo.test');
        $this->line('  Cliente (portal):  maria@cliente.test');

        return self::SUCCESS;
    }

    private function dono(string $senha): void
    {
        $dono = User::firstOrCreate(
            ['email' => 'dono@demo.test'],
            ['name' => 'Dona Demo', 'password' => $senha, 'e_profissional' => false, 'ativo' => true],
        );
        $dono->syncRoles(['Dono']);
    }

    private function unidade(): Unidade
    {
        return Unidade::firstOrCreate(
            ['nome' => 'Matriz Centro'],
            ['endereco' => 'Rua Principal, 100', 'telefone' => '(11) 3333-0000', 'ativo' => true],
        );
    }

    /**
     * Catálogo de demonstração (Fatia 2A): categorias, produtos (com e sem controle
     * de estoque) e estoque inicial por unidade via movimentação. Idempotente.
     */
    private function catalogoProdutos(Unidade $unidade): void
    {
        $categorias = collect(['Pomadas e ceras', 'Cuidados', 'Bebidas'])
            ->mapWithKeys(fn ($nome) => [$nome => CategoriaProduto::firstOrCreate(['nome' => $nome], ['ativo' => true])]);

        // [nome, categoria|null, preco_venda, preco_custo|null, controla_estoque, % comissão, estoque inicial|null]
        $itens = [
            ['Pomada modeladora', 'Pomadas e ceras', 39.90, 18.00, true, 10, 24],
            ['Cera fixação forte', 'Pomadas e ceras', 45.00, 20.00, true, 10, 15],
            ['Óleo para barba', 'Cuidados', 35.00, 15.00, true, 10, 12],
            ['Shampoo masculino', 'Cuidados', 29.90, 12.00, true, 5, 0],   // esgotado (demo)
            ['Água 500ml', 'Bebidas', 5.00, 2.00, true, 0, 40],
            ['Cerveja long neck', 'Bebidas', 12.00, 6.00, true, 0, 30],
            ['Vale-presente', null, 100.00, null, false, 0, null],          // não controla estoque
        ];

        $movimentador = app(MovimentadorEstoque::class);

        foreach ($itens as [$nome, $cat, $venda, $custo, $controla, $comissao, $estoque]) {
            $produto = Produto::firstOrCreate(
                ['nome' => $nome],
                [
                    'categoria_id' => $cat ? $categorias[$cat]->id : null,
                    'preco_venda' => $venda,
                    'preco_custo' => $custo,
                    'controla_estoque' => $controla,
                    'percentual_comissao' => $comissao ?: null,
                    'ativo' => true,
                ],
            );

            // Estoque inicial: só quando controla estoque, tem quantidade e ainda não
            // há movimentação (idempotente e retroativo, sem duplicar em re-run).
            if ($controla && $estoque && $produto->movimentacoes()->doesntExist()) {
                $movimentador->entrada($produto->id, $unidade->id, $estoque, 'Estoque inicial (demo)');
            }
        }
    }

    /**
     * Comandas de demonstração (Fatia 2B): uma paga de balcão (produto + serviço,
     * com desconto → baixa estoque + comissão), uma aberta, e uma a partir de um
     * atendimento concluído. Idempotente: só semeia se ainda não houver vendas.
     *
     * @param  array<string, Servico>  $servicos
     * @param  array<int, User>  $profissionais
     * @param  array<int, Cliente>  $clientes
     */
    private function comandas(Unidade $unidade, array $servicos, array $profissionais, array $clientes): void
    {
        if (Venda::exists()) {
            return;
        }

        $comanda = app(Comanda::class);
        $jorge = $profissionais[0] ?? null;
        $maria = $clientes[0] ?? null;
        $pomada = Produto::where('nome', 'Pomada modeladora')->first();
        $agua = Produto::where('nome', 'Água 500ml')->first();
        $corte = $servicos['Corte masculino'] ?? null;

        // 1) Balcão PAGA (produto + serviço, com desconto).
        $v1 = $comanda->abrir($unidade->id, $maria?->id, $jorge?->id);
        if ($pomada) {
            $comanda->adicionarProduto($v1, $pomada, 1, $jorge?->id);
        }
        if ($corte) {
            $comanda->adicionarServico($v1, $corte, $jorge?->id);
        }
        $comanda->definirDesconto($v1, 5);
        // Pagamento DIVIDIDO (metade dinheiro, metade pix) — demonstra o split.
        $metade = round((float) $v1->valor_total / 2, 2);
        $comanda->pagarPresencial($v1, [
            ['metodo' => 'dinheiro', 'valor' => $metade],
            ['metodo' => 'pix', 'valor' => round((float) $v1->valor_total - $metade, 2)],
        ], $jorge?->id);

        // 2) ABERTA (em montagem no balcão).
        $v2 = $comanda->abrir($unidade->id, null, $jorge?->id);
        if ($agua) {
            $comanda->adicionarProduto($v2, $agua, 2, null);
        }

        // 3) A partir de um atendimento concluído (o financeiro do atendimento).
        $ag = Agendamento::where('status', 'concluido')->whereHas('itens')->orderBy('id')->first();
        if ($ag) {
            $v3 = $comanda->apartirDeAgendamento($ag, $jorge?->id);
            $comanda->pagar($v3, $jorge?->id);
        }
    }

    /**
     * Faturamento REAL ao longo do histórico (Fatia 2D): ~70% dos atendimentos
     * concluídos passados viram comanda paga (serviços), parte com um produto de
     * balcão (se houver estoque). A `data` da venda é retroagida para a data do
     * atendimento, dando forma aos gráficos. Coerente (usa Comanda/MovimentadorEstoque:
     * baixa de estoque e comissão batem). Idempotente: só gera se ainda não há vendas
     * com data passada.
     *
     * @param  array<int, User>  $profissionais
     */
    private function historicoVendas(Unidade $unidade, array $profissionais): void
    {
        if (Venda::where('data', '<', Carbon::today()->subDay())->exists()) {
            return;
        }

        $comanda = app(Comanda::class);
        $estoque = app(MovimentadorEstoque::class);
        $userId = $profissionais[0]->id ?? null;
        $produtosBalcao = Produto::where('controla_estoque', true)->where('ativo', true)->get();

        mt_srand(20260621); // reproduzível

        $concluidos = Agendamento::where('status', 'concluido')
            ->where('data_hora_inicio', '<', Carbon::today())
            ->whereHas('itens')
            ->orderBy('data_hora_inicio')
            ->get();

        foreach ($concluidos as $ag) {
            // ~30% dos atendimentos não geram comanda (não foi cobrado pelo sistema).
            if (mt_rand(1, 100) > 70 || Venda::where('agendamento_id', $ag->id)->exists()) {
                continue;
            }

            $venda = $comanda->apartirDeAgendamento($ag, $userId);

            // ~35% também levam um produto de balcão, se houver estoque na unidade.
            if (mt_rand(1, 100) <= 35 && $produtosBalcao->isNotEmpty()) {
                $p = $produtosBalcao[mt_rand(0, $produtosBalcao->count() - 1)];
                if ($estoque->disponivel($p->id, $ag->unidade_id) >= 1) {
                    $comanda->adicionarProduto($venda, $p, 1, $ag->profissional_id);
                }
            }

            // Pagamento presencial com forma variada (dinheiro/pix/cartões/maquininha).
            $metodo = ['dinheiro', 'pix', 'cartao_credito', 'cartao_debito', 'maquininha'][mt_rand(0, 4)];
            $comanda->pagarPresencial($venda, [['metodo' => $metodo, 'valor' => (float) $venda->valor_total]], $userId);

            // Retroage a data da venda E dos pagamentos para a data do atendimento.
            $venda->forceFill(['data' => $ag->data_hora_inicio])->save();
            $venda->pagamentos()->update(['pago_em' => $ag->data_hora_inicio, 'created_at' => $ag->data_hora_inicio]);
        }

        mt_srand();
    }

    /**
     * Backfill de pagamentos para vendas pagas ANTES de existir o registro de
     * pagamento (tenants de demo antigos): cria um pagamento presencial coerente
     * (valor = total, aprovado, na data da venda). Idempotente: só onde falta.
     *
     * @param  array<int, User>  $profissionais
     */
    private function backfillPagamentos(array $profissionais): void
    {
        $userId = $profissionais[0]->id ?? null;
        $metodos = ['dinheiro', 'pix', 'cartao_credito', 'cartao_debito', 'maquininha'];

        Venda::where('status', 'paga')->whereDoesntHave('pagamentos')->each(function (Venda $venda) use ($userId, $metodos) {
            $venda->pagamentos()->create([
                'metodo' => $metodos[$venda->id % count($metodos)],
                'valor' => $venda->valor_total,
                'status' => 'aprovado',
                'pago_em' => $venda->data,
                'created_at' => $venda->data,
                'criado_por_user_id' => $userId,
            ]);
        });
    }

    /**
     * Override de comissão de demonstração (Fatia 2C): o Jorge ganha 50% no
     * "Corte masculino" (acima dos 40% padrão do serviço). Idempotente.
     *
     * @param  array<string, Servico>  $servicos
     * @param  array<int, User>  $profissionais
     */
    private function comissoesPersonalizadas(array $servicos, array $profissionais): void
    {
        $jorge = $profissionais[0] ?? null;
        $corte = $servicos['Corte masculino'] ?? null;

        if ($jorge && $corte) {
            ComissaoProfissional::firstOrCreate(
                ['user_id' => $jorge->id, 'servico_id' => $corte->id, 'produto_id' => null],
                ['percentual' => 50],
            );
        }
    }

    /** @return array<string, Servico> */
    private function servicos(Unidade $unidade): array
    {
        // [duração (min), preço, % comissão padrão (2C)]
        $catalogo = [
            'Corte masculino' => [30, 45.00, 40],
            'Barba' => [20, 30.00, 40],
            'Corte + Barba' => [50, 70.00, 40],
            'Sobrancelha' => [15, 20.00, 30],
            'Coloração' => [60, 90.00, 50],
        ];

        $servicos = [];
        foreach ($catalogo as $nome => [$duracao, $preco, $comissao]) {
            $servico = Servico::firstOrCreate(
                ['nome' => $nome],
                ['duracao_minutos' => $duracao, 'preco' => $preco, 'percentual_comissao' => $comissao, 'ativo' => true],
            );
            $servico->unidades()->syncWithoutDetaching([$unidade->id]);
            $servicos[$nome] = $servico;
        }

        return $servicos;
    }

    /**
     * @param  array<string, Servico>  $servicos
     * @return array<int, User>
     */
    private function profissionais(Unidade $unidade, array $servicos, string $senha): array
    {
        $definicoes = [
            ['Jorge Tesoura', 'jorge@demo.test', ['Corte masculino', 'Barba', 'Corte + Barba', 'Sobrancelha']],
            ['Ana Navalha', 'ana@demo.test', ['Corte masculino', 'Coloração', 'Sobrancelha']],
            ['Bruno Máquina', 'bruno@demo.test', ['Corte masculino', 'Barba', 'Corte + Barba']],
        ];

        $profissionais = [];
        foreach ($definicoes as [$nome, $email, $fazServicos]) {
            $prof = User::firstOrCreate(
                ['email' => $email],
                ['name' => $nome, 'password' => $senha, 'e_profissional' => true, 'ativo' => true],
            );
            $prof->syncRoles(['Profissional']);
            $prof->unidades()->syncWithoutDetaching([$unidade->id]);
            $prof->servicos()->syncWithoutDetaching(
                collect($fazServicos)->map(fn ($n) => $servicos[$n]->id)->all()
            );

            // Horários: seg–sex 09–12 e 13–18; sáb 09–13. Só se ainda não houver.
            if ($prof->horariosTrabalho()->count() === 0) {
                foreach ([1, 2, 3, 4, 5] as $dia) {
                    $prof->horariosTrabalho()->create(['unidade_id' => $unidade->id, 'dia_semana' => $dia, 'hora_inicio' => '09:00', 'hora_fim' => '12:00']);
                    $prof->horariosTrabalho()->create(['unidade_id' => $unidade->id, 'dia_semana' => $dia, 'hora_inicio' => '13:00', 'hora_fim' => '18:00']);
                }
                $prof->horariosTrabalho()->create(['unidade_id' => $unidade->id, 'dia_semana' => 6, 'hora_inicio' => '09:00', 'hora_fim' => '13:00']);
            }

            $profissionais[] = $prof;
        }

        return $profissionais;
    }

    private function equipeDeApoio(Unidade $unidade, string $senha): void
    {
        $apoio = [
            ['Gerente Demo', 'gerente@demo.test', 'Gerente'],
            ['Recepção Demo', 'recepcao@demo.test', 'Recepção'],
        ];

        foreach ($apoio as [$nome, $email, $papel]) {
            $user = User::firstOrCreate(
                ['email' => $email],
                ['name' => $nome, 'password' => $senha, 'e_profissional' => false, 'ativo' => true],
            );
            $user->syncRoles([$papel]);
            $user->unidades()->syncWithoutDetaching([$unidade->id]);
        }
    }

    /** @return array<int, Cliente> */
    private function clientes(string $senha): array
    {
        $definicoes = [
            ['Maria Souza', 'maria@cliente.test', '(11) 90000-0001'],
            ['Carlos Lima', 'carlos@cliente.test', '(11) 90000-0002'],
            ['Paula Dias', 'paula@cliente.test', '(11) 90000-0003'],
        ];

        $clientes = collect($definicoes)->map(function ($def) use ($senha) {
            [$nome, $email, $tel] = $def;

            return Cliente::firstOrCreate(
                ['email' => $email],
                ['nome' => $nome, 'telefone' => $tel, 'password' => $senha],
            );
        })->all();

        // Pool extra com created_at espalhado nos últimos ~85 dias, para que o
        // indicador "clientes novos" varie por período. Backdate só na criação.
        for ($i = 1; $i <= 8; $i++) {
            $cliente = Cliente::firstOrCreate(
                ['email' => "cliente{$i}@demo.test"],
                ['nome' => "Cliente Demo {$i}", 'telefone' => sprintf('(11) 90000-1%03d', $i), 'password' => $senha],
            );

            if ($cliente->wasRecentlyCreated) {
                $cliente->forceFill(['created_at' => Carbon::today()->subDays(($i * 11) % 85)])->save();
            }

            $clientes[] = $cliente;
        }

        return $clientes;
    }

    /**
     * @param  array<string, Servico>  $servicos
     * @param  array<int, User>  $profissionais
     * @param  array<int, Cliente>  $clientes
     */
    private function agendamentos(Unidade $unidade, array $servicos, array $profissionais, array $clientes): void
    {
        $existem = Agendamento::where('observacoes', self::MARCA_DEMO)->exists();

        if ($existem && ! $this->option('recriar')) {
            $this->line('Agendamentos de demo já existem (use --recriar para refazer).');

            return;
        }

        if ($existem && $this->option('recriar')) {
            // Remoção escopada aos agendamentos de demo (não toca em dados reais).
            Agendamento::where('observacoes', self::MARCA_DEMO)->each(function ($ag) {
                $ag->itens()->delete();
                $ag->delete();
            });
        }

        [$jorge, $ana, $bruno] = $profissionais;
        [$maria, $carlos, $paula] = $clientes;

        $amanha = Carbon::tomorrow()->setTime(0, 0);
        $ontem = Carbon::yesterday()->setTime(0, 0);
        $hoje = Carbon::today();

        // [cliente, profissional, [servicos], inicio, status]
        $plano = [
            [$maria, $jorge, ['Corte masculino', 'Barba'], $amanha->copy()->setTime(9, 0), 'confirmado'],
            [$carlos, $ana, ['Coloração'], $amanha->copy()->setTime(10, 0), 'pendente'],
            [$paula, $bruno, ['Corte masculino'], $amanha->copy()->setTime(14, 0), 'confirmado'],
            [$maria, $ana, ['Sobrancelha'], $hoje->copy()->setTime(16, 0), 'em_andamento'],
            [$carlos, $jorge, ['Corte + Barba'], $ontem->copy()->setTime(10, 0), 'concluido'],
            [$paula, $jorge, ['Corte masculino'], $ontem->copy()->setTime(11, 0), 'nao_compareceu'],
            [$maria, $bruno, ['Barba'], $amanha->copy()->setTime(15, 0), 'cancelado'],
        ];

        foreach ($plano as [$cliente, $prof, $nomesServicos, $inicio, $status]) {
            $this->criarAgendamento($unidade, $cliente, $prof, $inicio, $status, collect($nomesServicos)->map(fn ($n) => $servicos[$n]));
        }

        $this->historico($unidade, $servicos, $profissionais, $clientes);
    }

    /**
     * Histórico DETERMINÍSTICO (~90 dias) para os gráficos terem forma. Seg–sáb,
     * 2–6 agendamentos/dia, em horário comercial; passado já resolvido (concluído
     * na maioria, com não comparecimentos e cancelamentos). Semente fixa torna o
     * conteúdo reproduzível; tudo marcado [demo] (limpo por --recriar).
     *
     * @param  array<string, Servico>  $servicos
     * @param  array<int, User>  $profissionais
     * @param  array<int, Cliente>  $clientes
     */
    private function historico(Unidade $unidade, array $servicos, array $profissionais, array $clientes): void
    {
        mt_srand(20260614);

        $catalogo = array_values($servicos);
        $hoje = Carbon::today();

        for ($d = 90; $d >= 1; $d--) {
            $dia = $hoje->copy()->subDays($d);

            if ($dia->dayOfWeek === Carbon::SUNDAY) {
                continue; // domingo fechado
            }

            $quantidade = mt_rand(2, 6);

            for ($i = 0; $i < $quantidade; $i++) {
                $hora = mt_rand(9, 17);
                $minuto = [0, 15, 30, 45][mt_rand(0, 3)];
                $inicio = $dia->copy()->setTime($hora, $minuto);

                $cliente = $clientes[mt_rand(0, count($clientes) - 1)];
                $prof = $profissionais[mt_rand(0, count($profissionais) - 1)];

                $itens = collect($catalogo)->shuffle()->take(mt_rand(1, 2));

                // Passado já resolvido: ~70% concluído, ~15% faltou, ~15% cancelado.
                $sorte = mt_rand(1, 100);
                $status = $sorte <= 70 ? 'concluido' : ($sorte <= 85 ? 'nao_compareceu' : 'cancelado');

                $this->criarAgendamento($unidade, $cliente, $prof, $inicio, $status, $itens);
            }
        }

        mt_srand();
    }

    /**
     * Semeia alguns cartões nos quadros padrão (criados pelo seeder do tenant).
     * Idempotente: só cria se o quadro ainda não tiver cartões.
     *
     * @param  array<int, Cliente>  $clientes
     * @param  array<int, User>  $profissionais
     */
    private function kanban(array $clientes, array $profissionais): void
    {
        [$maria, $carlos, $paula] = $clientes;
        $prof = $profissionais[0] ?? null;

        $atendimento = KanbanQuadro::where('tipo', 'atendimento')->first();
        $crm = KanbanQuadro::where('tipo', 'crm')->first();

        if ($atendimento && $atendimento->colunas()->whereHas('cartoes')->doesntExist()) {
            $col = $atendimento->colunas()->orderBy('ordem')->pluck('id', 'nome');
            $this->cartao($col['Aguardando'] ?? $col->first(), 'Maria · Corte', ['cliente_id' => $maria->id, 'responsavel_user_id' => $prof?->id]);
            $this->cartao($col['Em atendimento'] ?? $col->first(), 'Carlos · Coloração', ['cliente_id' => $carlos->id, 'responsavel_user_id' => $prof?->id]);
            $this->cartao($col['Concluído'] ?? $col->first(), 'Paula · Sobrancelha', ['cliente_id' => $paula->id]);
        }

        if ($crm && $crm->colunas()->whereHas('cartoes')->doesntExist()) {
            $col = $crm->colunas()->orderBy('ordem')->pluck('id', 'nome');
            $this->cartao($col['Novo contato'] ?? $col->first(), 'Lead do Instagram', ['descricao' => 'Pediu preço de combo corte+barba.']);
            $this->cartao($col['Em conversa'] ?? $col->first(), 'João — retorno', ['descricao' => 'Aguardando confirmar horário.', 'valor_estimado' => 70]);
            $this->cartao($col['Fidelizado'] ?? $col->first(), 'Maria — mensalista', ['cliente_id' => $maria->id, 'valor_estimado' => 90]);
        }
    }

    private function cartao(int $colunaId, string $titulo, array $extra = []): void
    {
        KanbanCartao::create(array_merge([
            'coluna_id' => $colunaId,
            'titulo' => $titulo,
            'ordem' => (int) KanbanCartao::where('coluna_id', $colunaId)->max('ordem') + 1,
        ], $extra));
    }

    /** @param  Collection<int, Servico>  $itens */
    private function criarAgendamento(Unidade $unidade, Cliente $cliente, User $prof, Carbon $inicio, string $status, $itens): void
    {
        $duracao = (int) $itens->sum('duracao_minutos');

        $agendamento = Agendamento::create([
            'unidade_id' => $unidade->id,
            'cliente_id' => $cliente->id,
            'profissional_id' => $prof->id,
            'data_hora_inicio' => $inicio,
            'data_hora_fim' => $inicio->copy()->addMinutes($duracao),
            'status' => $status,
            'origem' => 'equipe',
            'valor_total' => (float) $itens->sum('preco'),
            'observacoes' => self::MARCA_DEMO,
        ]);

        foreach ($itens as $servico) {
            $agendamento->itens()->create([
                'servico_id' => $servico->id,
                'preco' => $servico->preco,
                'duracao_minutos' => $servico->duracao_minutos,
            ]);
        }
    }

    /**
     * Avaliações de exemplo (D51) — alguns atendimentos concluídos já avaliados,
     * para o painel (Prompt 2) ter dados. Idempotente (firstOrCreate por
     * agendamento). Deixa concluídos sem avaliação de propósito (para o popup
     * aparecer ao logar como cliente na demo).
     */
    private function avaliacoes(): void
    {
        $concluidos = Agendamento::where('observacoes', self::MARCA_DEMO)
            ->where('status', 'concluido')
            ->whereDoesntHave('avaliacao')
            ->orderBy('id')
            ->take(3)
            ->get();

        $exemplos = [
            [5, 'Atendimento impecável, voltarei sempre!'],
            [4, 'Muito bom — só atrasou alguns minutos.'],
            [5, null],
        ];

        foreach ($concluidos as $i => $ag) {
            [$nota, $comentario] = $exemplos[$i % count($exemplos)];

            Avaliacao::firstOrCreate(
                ['agendamento_id' => $ag->id],
                [
                    'cliente_id' => $ag->cliente_id,
                    'profissional_id' => $ag->profissional_id,
                    'unidade_id' => $ag->unidade_id,
                    'nota' => $nota,
                    'comentario' => $comentario,
                ],
            );

            // Popup já considerado exibido para os avaliados (coerência).
            $ag->update(['avaliacao_popup_exibido_em' => now()]);
        }
    }
}
