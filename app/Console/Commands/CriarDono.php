<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * Cria um usuário com papel "Dono" no banco de um tenant. A senha é definida
 * pelo operador na execução (prompt secreto) — nunca em código ou git.
 */
class CriarDono extends Command
{
    protected $signature = 'nextgest:criar-dono
                            {tenant : slug/id do tenant}
                            {--name= : Nome do dono}
                            {--email= : E-mail (login)}';

    protected $description = 'Cria um usuário Dono no banco de um tenant';

    public function handle(): int
    {
        $slug = $this->argument('tenant');

        $tenant = Tenant::find($slug);

        if (! $tenant) {
            $this->error("Tenant não encontrado: {$slug}");

            return self::FAILURE;
        }

        $name = $this->option('name') ?: $this->ask('Nome');
        $email = $this->option('email') ?: $this->ask('E-mail');
        $password = $this->secret('Senha (mínimo 8 caracteres)');
        $confirmacao = $this->secret('Confirme a senha');

        // Validação básica fora do contexto do tenant (unicidade é checada dentro).
        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $confirmacao,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $erro) {
                $this->error($erro);
            }

            return self::FAILURE;
        }

        $resultado = $tenant->run(function () use ($name, $email, $password) {
            if (User::where('email', $email)->exists()) {
                return "E-mail já cadastrado neste tenant: {$email}";
            }

            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => $password, // cast 'hashed'
                'e_profissional' => false,
                'ativo' => true,
            ]);

            $user->assignRole('Dono');

            return true;
        });

        if ($resultado !== true) {
            $this->error($resultado);

            return self::FAILURE;
        }

        $this->info("Dono criado no tenant {$slug}: {$email}");

        return self::SUCCESS;
    }
}
