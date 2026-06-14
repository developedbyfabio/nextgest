<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Admin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * Cria um super-admin central. A senha é definida pelo operador na execução
 * (prompt secreto) — nunca em código ou git.
 */
class CriarAdmin extends Command
{
    protected $signature = 'nextgest:criar-admin
                            {--name= : Nome do admin}
                            {--email= : E-mail (login)}';

    protected $description = 'Cria um super-admin central do Nextgest';

    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('Nome');
        $email = $this->option('email') ?: $this->ask('E-mail');

        $password = $this->secret('Senha (mínimo 8 caracteres)');
        $confirmacao = $this->secret('Confirme a senha');

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $confirmacao,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:'.Admin::class.',email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $erro) {
                $this->error($erro);
            }

            return self::FAILURE;
        }

        // O cast 'hashed' do model faz o hash da senha.
        Admin::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'ativo' => true,
        ]);

        $this->info("Super-admin criado: {$email}");

        return self::SUCCESS;
    }
}
